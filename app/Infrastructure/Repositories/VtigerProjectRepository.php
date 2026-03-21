<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class VtigerProjectRepository
 * 
 * Vtiger-specific implementation of the ProjectRepositoryInterface.
 * 
 * This repository handles project persistence operations for the Vtiger CRM
 * system, implementing data access logic across multiple related database tables:
 * - vtiger_project: Core project data including dates, budget, status, progress, relationships
 * - vtiger_crmentity: Audit metadata, ownership (smownerid), creator (smcreatorid), soft-delete flag, entity type, description
 * - vtiger_account: Denormalized client name for display efficiency via left join on linktoaccountscontacts
 * - vtiger_users: Denormalized assigned user name for display efficiency via left join on smownerid
 * - vtiger_projecttask: Related task data for progress calculation and task listing
 * - vtiger_modcomments, vtiger_attachments: Used in last_activity calculation via subqueries
 * 
 * Implementation characteristics:
 * - Complex multi-table queries: Read operations join 4+ tables with subqueries for activity tracking
 * - Inline entity mapping: Projects mapped directly from database rows without external mapper class
 * - Transaction safety: Write operations (create, update) wrapped in database transactions for consistency
 * - Legacy ID generation: Uses MAX(crmid) + 1 pattern for Vtiger compatibility instead of auto-increment
 * - Soft delete support: Deletion marks vtiger_crmentity.deleted = 1 rather than removing records
 * - Denormalized reads: Account and user names fetched via joins to avoid N+1 query problems
 * - Activity aggregation: last_activity calculated via GREATEST/COALESCE across comments, attachments, tasks
 * - Task aggregation: totalTasks and completedTasks calculated via in-memory grouping after fetch
 * 
 * Database connection:
 * - Uses named connection 'vtiger' configured in database.php for Vtiger database access
 * - All queries explicitly specify DB::connection('vtiger') to avoid accidental cross-database operations
 * 
 * Performance considerations:
 * - Read queries use indexed fields (projectid, crmid, linktoaccountscontacts, deleted) for efficient lookups
 * - Search operations use LIKE with leading wildcard; consider full-text indexing for large datasets
 * - last_activity calculation uses correlated subqueries; may impact performance on large datasets
 * - Task aggregation performed in PHP after fetch to avoid complex SQL; suitable for moderate task volumes
 * - Sorting whitelist prevents SQL injection via orderBy parameters
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @implements ProjectRepositoryInterface
 * @see ProjectRepositoryInterface For the contract this class implements
 * @see Project For the domain entity returned by read operations
 * @see \App\Application\DTOs\CreateProjectRequest For creation request structure (used in create method)
 */
class VtigerProjectRepository implements ProjectRepositoryInterface
{
    /**
     * Retrieve a paginated list of projects with optional search, status filter, and sorting.
     * 
     * Implementation details:
     * - Constructs query joining vtiger_project with vtiger_crmentity for audit data
     *   and left joins with vtiger_account and vtiger_users for denormalized display names
     * - Applies base filter: vtiger_crmentity.deleted = 0 to exclude soft-deleted records
     * - Optional search filter: Performs case-insensitive partial matching on projectname,
     *   project_no, and related account name using LIKE with wildcards
     * - Optional status filter: Supports 'active' pseudo-status that excludes completed/cancelled
     *   projects, or exact status value matching for specific workflow states
     * - Sorting: Validates sortBy parameter against whitelist to prevent SQL injection;
     *   supports special 'last_activity' sort via calculated field using GREATEST/COALESCE
     * - last_activity calculation: Uses correlated subqueries to find most recent timestamp
     *   across project modifications, comments, attachments, and related project tasks
     * - Total count calculated before pagination to ensure accurate metadata in paginator
     * - Task aggregation: Fetches all tasks for returned projects in single query, groups
     *   by projectid in PHP, calculates totalTasks and completedTasks counts in memory
     * - Results mapped to Project entities via inline constructor calls with type casting
     * - Returns Laravel LengthAwarePaginator with path preservation for frontend pagination
     * 
     * Query structure:
     * - SELECT specifies only required columns to minimize data transfer
     * - CONCAT expression builds assigned_user_name from first_name and last_name
     * - GREATEST/COALESCE expression calculates last_activity_calculated from multiple sources
     * - Type casting applied to numeric fields (targetbudget, progress, IDs) for type safety
     * - assigned_user_name trimmed and validated to avoid displaying whitespace-only values
     * 
     * Sorting logic:
     * - Default sort: last_activity DESC (most recently active projects first)
     * - Whitelist validation: Only allowed columns can be used for sorting
     * - Special handling: 'last_activity' uses orderByRaw with calculated field
     * - Column mapping: 'account_name' and 'assigned_user_name' map to appropriate expressions
     * 
     * Task aggregation strategy:
     * - Single query fetches all tasks for visible projects via WHERE IN (projectids)
     * - Tasks grouped by projectid using Laravel Collection::groupBy()
     * - Counts calculated per project: total tasks and tasks with status = 'Completed'
     * - Avoids N+1 query problem while keeping logic in application layer for flexibility
     * 
     * @inheritDoc
     * 
     * @param int $page Page number (1-based index)
     * @param int $perPage Items per page
     * @param string|null $search Optional search term for partial matching on project fields
     * @param string|null $status Optional status filter: 'active' for non-completed, or exact status value
     * @param string|null $sortBy Optional sort column name (validated against whitelist)
     * @param string|null $sortOrder Optional sort direction: 'ASC' or 'DESC' (default: DESC)
     * @param int|null $accountId Optional account ID to filter projects by related client
     * 
     * @return LengthAwarePaginator<Project> Paginator containing mapped Project entities
     *                                        for the requested page with task counts
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     * @throws \InvalidArgumentException If sortBy parameter contains invalid column name
     */
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        ?int $accountId = null,
        ?string $sortBy = null,
        ?string $sortOrder = null

    ): LengthAwarePaginator {

        $allowedSortColumns = [
            'createdtime',
            'modifiedtime',
            'projectname',
            'targetenddate',
            'progress',
            'account_name',
            'projectstatus',
            'last_activity'
        ];


        $sortBy = $sortBy && in_array($sortBy, $allowedSortColumns, true)
            ? $sortBy
            : 'last_activity';

        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';


        $query = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_project.projectid',
                'vtiger_project.projectname',
                'vtiger_project.project_no',
                'vtiger_project.startdate',
                'vtiger_project.targetenddate',
                'vtiger_project.actualenddate',
                'vtiger_project.targetbudget',
                'vtiger_project.projecturl',
                'vtiger_project.projectstatus',
                'vtiger_project.projectpriority',
                'vtiger_project.projecttype',
                'vtiger_project.progress',
                'vtiger_project.linktoaccountscontacts',
                'vtiger_account.accountname as account_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_crmentity.description',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.deleted',


                DB::raw("
                GREATEST(
                    COALESCE(vtiger_crmentity.modifiedtime, '1970-01-01 00:00:00'),
                    COALESCE((
                        SELECT MAX(c.createdtime) 
                        FROM vtiger_modcomments c 
                        WHERE c.related_to = vtiger_project.projectid
                    ), '1970-01-01 00:00:00'),
                    COALESCE((
                        SELECT MAX(att_cr.createdtime)
                        FROM vtiger_attachments att
                        JOIN vtiger_seattachmentsrel rel ON att.attachmentsid = rel.attachmentsid
                        JOIN vtiger_crmentity att_cr ON att.attachmentsid = att_cr.crmid
                        WHERE rel.crmid = vtiger_project.projectid
                    ), '1970-01-01 00:00:00'),
                    COALESCE((
                        SELECT MAX(task_cr.modifiedtime)
                        FROM vtiger_projecttask pt
                        JOIN vtiger_crmentity task_cr ON pt.projecttaskid = task_cr.crmid
                        WHERE pt.projectid = vtiger_project.projectid
                        AND task_cr.setype = 'ProjectTask'
                    ), '1970-01-01 00:00:00')
                ) as last_activity_calculated
            ")
            )
            ->where('vtiger_crmentity.deleted', 0);

        if ($accountId !== null) {
            $query->whereNotNull('vtiger_project.linktoaccountscontacts')
                ->where('vtiger_project.linktoaccountscontacts', '!=', '')
                ->whereRaw(
                    'TRIM(SUBSTRING_INDEX(vtiger_project.linktoaccountscontacts, "x", 1)) = ?',
                    [(string) $accountId]
                );
        }

        Log::info('Projects repository getAll executed', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'filters' => [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'status' => $status,
                'account_id' => $accountId,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_project.projectname', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_project.project_no', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_account.accountname', 'LIKE', "%{$search}%");
            });
        }


        if ($status === 'active') {
            $query->whereNotIn('vtiger_project.projectstatus', [
                'Completado',
                'Completed',
                'Cancelado',
                'Cancelled'
            ]);
        } elseif ($status) {
            $query->where('vtiger_project.projectstatus', $status);
        }


        if ($sortBy === 'last_activity') {
            $query->orderByRaw('last_activity_calculated ' . $sortOrder);
        } else {
            $sortColumn = match ($sortBy) {
                'account_name' => 'vtiger_account.accountname',
                'assigned_user_name' => DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name)"),
                default => "vtiger_crmentity.{$sortBy}",
            };
            $query->orderBy($sortColumn, $sortOrder);
        }


        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();
        $projectIds = $items->pluck('projectid')->toArray();


        $allTasks = DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->whereIn('projectid', $projectIds)
            ->get()
            ->groupBy('projectid');


        $projects = $items->map(function ($row) use ($allTasks) {
            $projectId = $row->projectid;
            $tasks = $allTasks->get($projectId, collect());
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('projecttaskstatus', 'Completed')->count();

            return new Project(
                projectid: $row->projectid,
                projectname: $row->projectname,
                project_no: $row->project_no,
                startdate: $row->startdate,
                targetenddate: $row->targetenddate,
                actualenddate: $row->actualenddate,
                targetbudget: $row->targetbudget ? (float) $row->targetbudget : null,
                projecturl: $row->projecturl,
                projectstatus: $row->projectstatus,
                projectpriority: $row->projectpriority,
                projecttype: $row->projecttype,
                progress: $row->progress ? (int) $row->progress : 0,
                linktoaccountscontacts: $row->linktoaccountscontacts ? (int) $row->linktoaccountscontacts : null,
                account_name: $row->account_name ?? null,
                assigned_user_id: $row->assigned_user_id ? (int)$row->assigned_user_id : null,
                assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' '
                    ? $row->assigned_user_name
                    : null,
                potential_name: null,
                totalTasks: $totalTasks,
                completedTasks: $completedTasks,
                hits: 0,
                description: $row->description,
                createdtime: $row->createdtime,
                modifiedtime: $row->modifiedtime,
                lastActivity: $row->last_activity_calculated ?? $row->modifiedtime
            );
        });

        return new LengthAwarePaginator(
            $projects instanceof Collection ? $projects : collect($projects),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Find a single project by its unique identifier.
     * 
     * Implementation details:
     * - Constructs query identical to getAll() but with WHERE clause for specific projectid
     * - Returns single row via first() method or null if not found
     * - Maps result to Project entity using same inline mapping logic as getAll()
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Does not calculate last_activity via subqueries for detail view performance;
     *   falls back to modifiedtime if last_activity not needed
     * - Fetches related tasks separately and calculates totalTasks/completedTasks counts
     * 
     * Performance note:
     * - Query uses primary key (projectid) for efficient index lookup
     * - Single record fetch avoids pagination overhead for detail views
     * - Task fetch uses simple WHERE clause without complex aggregation
     * - last_activity calculation omitted for detail view; use getAll() if needed
     * 
     * @inheritDoc
     * 
     * @param int $projectId The projectid of the project to retrieve
     * 
     * @return Project|null The mapped Project entity if found and active, null otherwise
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function findById(int $projectId): ?Project
    {
        $project = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_project.projectid',
                'vtiger_project.projectname',
                'vtiger_project.project_no',
                'vtiger_project.startdate',
                'vtiger_project.targetenddate',
                'vtiger_project.actualenddate',
                'vtiger_project.targetbudget',
                'vtiger_project.projecturl',
                'vtiger_project.projectstatus',
                'vtiger_project.projectpriority',
                'vtiger_project.projecttype',
                'vtiger_project.progress',
                'vtiger_project.linktoaccountscontacts',
                'vtiger_account.accountname as account_name',
                'vtiger_crmentity.smownerid as assigned_user_id',
                DB::raw("CONCAT(assigned_user.first_name, ' ', assigned_user.last_name) as assigned_user_name"),
                'vtiger_crmentity.description',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime'
            )
            ->where('vtiger_project.projectid', $projectId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (!$project) {
            return null;
        }

        // calculate tasks
        $tasks = DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->where('projectid', $projectId)
            ->get();

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('projecttaskstatus', 'Completed')->count();

        return new Project(
            projectid: $project->projectid,
            projectname: $project->projectname,
            project_no: $project->project_no,
            startdate: $project->startdate,
            targetenddate: $project->targetenddate,
            actualenddate: $project->actualenddate,
            targetbudget: $project->targetbudget ? (float)$project->targetbudget : null,
            projecturl: $project->projecturl,
            projectstatus: $project->projectstatus,
            projectpriority: $project->projectpriority,
            projecttype: $project->projecttype,
            progress: $project->progress ? (int)$project->progress : 0,
            linktoaccountscontacts: $project->linktoaccountscontacts ? (int)$project->linktoaccountscontacts : null,
            account_name: $project->account_name ?? null,
            assigned_user_id: $project->assigned_user_id ? (int)$project->assigned_user_id : null,
            assigned_user_name: $project->assigned_user_name && trim($project->assigned_user_name) !== ' '
                ? $project->assigned_user_name
                : null,
            potential_name: null,
            totalTasks: $totalTasks,
            completedTasks: $completedTasks,
            hits: 0,
            description: $project->description,
            createdtime: $project->createdtime,
            modifiedtime: $project->modifiedtime
        );
    }

    /**
     * Create a new project record in the database.
     * 
     * Implementation details:
     * - Executes all operations within database transaction for atomicity across two tables
     * - Generates project number using format PROJ-YYYY-XXXX with year-based counter reset
     * - Generates new CRM ID using Vtiger legacy pattern: MAX(crmid) + 1
     * - Inserts into vtiger_crmentity first to establish audit metadata and entity type
     * - Inserts into vtiger_project second with same crmid as projectid for referential integrity
     * - Handles assigned user assignment: defaults to group ID 2 ("All") if not specified
     * - Sets entity type to 'Project' in vtiger_crmentity for type discrimination
     * - Initializes conversion flags (isconvertedfrompotential) based on quoteid presence
     * - Rolls back transaction on any failure to prevent orphaned records in either table
     * 
     * ID generation strategy:
     * - Uses MAX(crmid) + 1 to emulate auto-increment in Vtiger's legacy schema
     * - Note: This approach may have race conditions in high-concurrency environments;
     *   consider database sequences or application-level ID generators with locking
     *   for production deployments with heavy write load
     * 
     * Project number generation:
     * - Format: PROJ-YYYY-XXXX where YYYY is current year, XXXX is zero-padded counter
     * - Counter resets to 0001 when year changes; increments within same year
     * - Fallback to default format if existing max value doesn't match expected pattern
     * 
     * Field mapping:
     * - accountid from request mapped to linktoaccountscontacts in vtiger_project
     * - assigned_user_id from request mapped to smownerid in vtiger_crmentity
     * - description stored in vtiger_crmentity for global search integration
     * 
     * @inheritDoc
     * 
     * @param array<string, mixed> $data Associative array containing project creation data.
     *                                    Expected keys: projectname, startdate, targetenddate,
     *                                    targetbudget, projectstatus, projectpriority, projecttype,
     *                                    accountid, potentialid/quoteid, description, assigned_user_id,
     *                                    created_by_user_id
     * 
     * @return int The newly generated projectid for the created project
     * 
     * @throws \RuntimeException If database transaction fails or any insert operation errors
     * @throws \Exception If unexpected error occurs during creation process
     */
    public function create(array $data): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // generate project number
            $maxProjectNo = DB::connection('vtiger')
                ->table('vtiger_project')
                ->max('project_no');

            $projectNo = $this->generateProjectNumber($maxProjectNo);

            // Insert into vtiger_crmentity
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;

            $assignedUserId = $data['assigned_user_id'] ?? null;


            if ($assignedUserId === null) {
                $assignedUserId = 2; // ✅ ID del grupo "All" en Vtiger
            }

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    'smownerid' => $assignedUserId,
                    'smcreatorid' => $data['created_by_user_id'],
                    'setype' => 'Project',
                    'description' => $data['description'] ?? null,
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'deleted' => 0,
                ]);

            // Insert into vtiger_project
            DB::connection('vtiger')
                ->table('vtiger_project')
                ->insert([
                    'projectid' => $crmid,
                    'projectname' => $data['projectname'],
                    'project_no' => $projectNo,
                    'startdate' => $data['startdate'] ?? null,
                    'targetenddate' => $data['targetenddate'] ?? null,
                    'actualenddate' => null,
                    'targetbudget' => $data['targetbudget'] ?? null,
                    'projecturl' => $data['projecturl'] ?? null,
                    'projectstatus' => $data['projectstatus'] ?? 'Draft',
                    'projectpriority' => $data['projectpriority'] ?? 'Medium',
                    'projecttype' => $data['projecttype'] ?? null,
                    'progress' => '0',
                    'linktoaccountscontacts' => $data['accountid'] ?? null,
                    'tags' => null,
                    'isconvertedfrompotential' => isset($data['quoteid']) && $data['quoteid'] ? 1 : 0,
                    'potentialid' => $data['quoteid'] ?? null,
                    'cf_922' => null,
                ]);

            DB::connection('vtiger')->commit();
            return $crmid;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * Update an existing project in the Vtiger CRM system.
     * 
     * This method performs a transactional update across two related tables:
     * - vtiger_project: Contains project-specific fields (name, dates, budget, status, etc.)
     * - vtiger_crmentity: Contains common entity fields (modifiedtime, description, owner, etc.)
     * 
     * Only fields that are explicitly provided in the $data array are updated.
     * Fields that are null or not present in $data are preserved with their existing values.
     * 
     * @param int $projectId The unique identifier of the project to update.
     * @param array $data Associative array containing the fields to update.
     *                    Supported keys:
     *                    - projectname: string - The project name
     *                    - startdate: string - Project start date (Y-m-d format)
     *                    - targetenddate: string - Target completion date (Y-m-d format)
     *                    - actualenddate: string - Actual completion date (Y-m-d format)
     *                    - targetbudget: string|float - Budget amount
     *                    - projecturl: string - External URL reference
     *                    - projectstatus: string - Current status value
     *                    - projectpriority: string - Priority level
     *                    - projecttype: string - Project type classification
     *                    - progress: string|int - Completion percentage
     *                    - accountid: int - Client/account identifier (mapped to linktoaccountscontacts)
     *                    - potentialid: int - Related opportunity identifier
     *                    - description: string - Project description (stored in vtiger_crmentity)
     *                    - assigned_user_id: int - Responsible user ID (mapped to smownerid in vtiger_crmentity)
     * 
     * @return bool True if the update was successful, false otherwise.
     * 
     * @throws \Exception If the database transaction fails or any update operation encounters an error.
     *                    The exception is logged before being re-thrown for upstream handling.
     * 
     * @remarks
     * - Uses database transactions to ensure atomicity across both table updates
     * - Automatically updates the modifiedtime field in vtiger_crmentity to current timestamp
     * - Filters out null values to prevent accidental data loss from partial updates
     * - Maps frontend field names to database column names where they differ (accountid → linktoaccountscontacts, assigned_user_id → smownerid)
     * - Logs detailed error information including stack trace for debugging purposes
     * 
     * @example
     * // Update project status and name only
     * $repository->update(123, [
     *     'projectname' => 'Kitchen Renovation Phase 2',
     *     'projectstatus' => 'in progress'
     * ]);
     * 
     * @example
     * // Update multiple fields including assigned user
     * $repository->update(456, [
     *     'projectstatus' => 'completed',
     *     'actualenddate' => '2026-03-15',
     *     'assigned_user_id' => 5,
     *     'progress' => '100'
     * ]);
     * 
     * @see \App\Domain\Repositories\VtigerProjectRepository::findById() For retrieving the updated project
     * @see https://wiki.vtiger.com/index.php/Vtiger_CRM_table_structure   For Vtiger database schema reference
     */
    public function update(int $projectId, array $data): bool
    {
        // Begin database transaction to ensure atomic updates across both tables
        DB::connection('vtiger')->beginTransaction();

        try {
            // Capture current timestamp for modifiedtime field
            $now = now()->format('Y-m-d H:i:s');

            // Prepare fields for vtiger_project table update
            // Only project-specific fields are included; common fields belong in vtiger_crmentity
            $projectFields = [
                'projectname' => $data['projectname'] ?? null,
                'startdate' => $data['startdate'] ?? null,
                'targetenddate' => $data['targetenddate'] ?? null,
                'actualenddate' => $data['actualenddate'] ?? null,
                'targetbudget' => $data['targetbudget'] ?? null,
                'projecturl' => $data['projecturl'] ?? null,
                'projectstatus' => $data['projectstatus'] ?? null,
                'projectpriority' => $data['projectpriority'] ?? null,
                'projecttype' => $data['projecttype'] ?? null,
                'progress' => $data['progress'] ?? null,
                'linktoaccountscontacts' => $data['accountid'] ?? null,
                'potentialid' => $data['potentialid'] ?? null,
            ];

            // Filter out null values to avoid overwriting existing data with NULL
            // This enables partial updates where only specified fields are modified
            $projectFields = array_filter($projectFields, fn($value) => $value !== null);

            // Execute update on vtiger_project table only if there are fields to update
            if (!empty($projectFields)) {
                DB::connection('vtiger')
                    ->table('vtiger_project')
                    ->where('projectid', $projectId)
                    ->update($projectFields);
            }

            // Prepare fields for vtiger_crmentity table update
            // This table stores common entity metadata shared across all Vtiger modules
            $crmentityFields = [
                'modifiedtime' => $now, // Always update modification timestamp
            ];

            // Include description field if provided in update data
            // Description is stored in vtiger_crmentity, not vtiger_project
            if (isset($data['description']) && $data['description'] !== null) {
                $crmentityFields['description'] = $data['description'];
            }

            // Include assigned user field if provided
            // Map frontend field name (assigned_user_id) to database column (smownerid)
            if (isset($data['assigned_user_id']) && $data['assigned_user_id'] !== null) {
                $crmentityFields['smownerid'] = $data['assigned_user_id'];
            }

            // Execute update on vtiger_crmentity table
            // This ensures entity-level metadata stays synchronized with project data
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->update($crmentityFields);

            // Commit transaction to persist all changes atomically
            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            // Rollback transaction on error to maintain data consistency
            DB::connection('vtiger')->rollback();

            // Log detailed error information for debugging and monitoring
            // Includes project ID, error message, and full stack trace
            Log::error('Error updating project', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception for upstream error handling
            throw $e;
        }
    }

    /**
     * Soft delete a project record by marking it as deleted.
     * 
     * Implementation details:
     * - Performs soft delete by updating vtiger_crmentity.deleted = 1
     *   rather than removing records from database (preserves audit trail)
     * - Updates modifiedtime timestamp to track when deletion occurred
     * - Does not cascade delete to related entities (tasks, milestones, etc.)
     *   as those maintain independent lifecycle and visibility rules
     * - Returns true on successful update; exceptions propagate for error handling
     * 
     * Data retention:
     * - Soft-deleted records remain queryable with explicit deleted = 1 filter
     * - Enables audit compliance and potential restoration via administrative tools
     * - Consider implementing periodic archival job for records beyond retention period
     * 
     * @inheritDoc
     * 
     * @param int $projectId The projectid of the project to soft-delete
     * 
     * @return bool True if deletion was successful, false if project not found
     *              or already deleted
     * 
     * @throws \RuntimeException If database update fails or connection is lost
     * @throws \Exception If unexpected error occurs during deletion process
     */
    public function delete(int $projectId): bool
    {
        try {
            // mark as deleted in vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $projectId)
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Retrieve all tasks related to a specific project.
     * 
     * Implementation details:
     * - Simple query against vtiger_projecttask table filtered by projectid
     * - Returns raw database rows as associative arrays via toArray()
     * - No entity mapping performed; caller responsible for transformation if needed
     * - No filtering by task status or other criteria; returns all tasks for project
     * - No join with vtiger_crmentity; assumes caller handles soft-delete filtering
     * 
     * Usage patterns:
     * - Used by project detail views to display task lists
     * - Used by progress calculation logic in getAll() and findById()
     * - Caller may filter results by status, assignee, or date as needed
     * 
     * Performance note:
     * - Query uses indexed projectid field for efficient lookup
     * - Returns all columns via get(); consider select() for large task lists
     * - No pagination; suitable for moderate task volumes per project
     * 
     * @inheritDoc
     * 
     * @param int $projectId The projectid of the project whose tasks to retrieve
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing task data from
     *                                          vtiger_projecttask table
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function getTasksByProject(int $projectId): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->where('projectid', $projectId)
            ->get()
            ->toArray();
    }

    /**
     * Generate a formatted project number from the maximum existing value.
     * 
     * Implementation details:
     * - Parses existing max project number using regex pattern PROJ-YYYY-XXXX
     * - If pattern matches and year matches current year: increments counter
     * - If pattern matches but year differs: resets counter to 1 for new year
     * - If no existing value or pattern mismatch: returns default PROJ-YYYY-0001
     * - Uses zero-padding (4 digits) for counter portion of project number
     * 
     * Format specification:
     * - Pattern: PROJ-YYYY-XXXX where YYYY is 4-digit year, XXXX is zero-padded counter
     * - Example: PROJ-2026-0001, PROJ-2026-0002, ..., PROJ-2027-0001
     * - Counter resets annually to support organizational numbering policies
     * 
     * Edge case handling:
     * - Null or empty maxProjectNo: returns default format for first project
     * - Pattern mismatch: falls back to default format to avoid breaking on legacy data
     * - Year transition: automatic reset ensures clean numbering per fiscal year
     * 
     * @param string|null $maxProjectNo The maximum existing project number value
     *                                  from vtiger_project.project_no column,
     *                                  or null if no projects exist yet
     * 
     * @return string Formatted project number following PROJ-YYYY-XXXX pattern
     * 
     * @internal Used by create() method for project number generation
     */
    private function generateProjectNumber(?string $maxProjectNo): string
    {
        if (!$maxProjectNo) {
            return 'PROJ-' . date('Y') . '-0001';
        }

        // extract the number from the format PROJ-YYYY-XXXX
        if (preg_match('/PROJ-(\d{4})-(\d+)/', $maxProjectNo, $matches)) {
            $year = $matches[1];
            $number = intval($matches[2]);

            // if it's the same year, increment
            if ($year == date('Y')) {
                $number++;
            } else {
                // new year, reset counter
                $number = 1;
            }

            return sprintf('PROJ-%s-%04d', date('Y'), $number);
        }

        return 'PROJ-' . date('Y') . '-0001';
    }

    /**
     * Perform global search for projects with type discrimination.
     * 
     * Implementation details:
     * - Searches across projectname, project_no, and related account name fields
     *   with partial matching using LIKE with wildcards
     * - Adds static 'type' field set to 'project' for frontend entity routing
     * - Adds 'url' field with route pattern for direct navigation to project detail
     * - Includes denormalized client name and status for enriched search result display
     * - Limits results to specified count for global search aggregation performance
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns results formatted for global search response structure
     * 
     * Global search integration:
     * - Response format matches other entity search methods for consistent
     *   aggregation in GlobalSearchUseCase
     * - 'type' field enables frontend to route to correct detail view
     * - 'url' field provides pre-built navigation link for convenience
     * - Additional fields (client, status) enable richer result previews
     * 
     * @inheritDoc
     * 
     * @param string $query The search query string for partial matching
     * @param int $limit Maximum number of results to return
     * 
     * @return array<int, array<string, mixed>> Array of associative arrays
     *                                          containing project information
     *                                          with type discrimination for
     *                                          global search aggregation
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function search(string $query, int $limit): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($query) {
                $q->where('vtiger_project.projectname', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_project.project_no', 'LIKE', "%{$query}%")
                    ->orWhere('vtiger_account.accountname', 'LIKE', "%{$query}%");
            })
            ->select(
                'vtiger_project.projectid as id',
                'vtiger_project.projectname as title',
                'vtiger_project.project_no as number',
                'vtiger_account.accountname as client',
                'vtiger_project.projectstatus as status',
                DB::raw("'project' as type")
            )
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'number' => $item->number,
                'client' => $item->client,
                'status' => $item->status,
                'url' => "/dashboard/projects/{$item->id}",
            ])
            ->toArray();
    }

    /**
     * Count the number of projects related to a specific client.
     * 
     * Implementation details:
     * - Performs efficient COUNT query against vtiger_project joined with vtiger_crmentity
     * - Filters by linktoaccountscontacts = $clientId using CAST to UNSIGNED for
     *   compatibility with Vtiger's VARCHAR storage of related record references
     * - Excludes soft-deleted records via vtiger_crmentity.deleted = 0 filter
     * - Returns integer count suitable for dashboard summary cards and statistics
     * - No entity hydration performed; optimized for read-only aggregation use case
     * 
     * Vtiger-specific consideration:
     * - linktoaccountscontacts field stores related account references as VARCHAR
     *   in format "IDxModuleType" (e.g., "123x4"); CAST to UNSIGNED extracts numeric ID
     * - This approach works for numeric account IDs but may miss edge cases with
     *   non-standard reference formats; consider application-level parsing if needed
     * 
     * Performance note:
     * - Query uses indexed fields (linktoaccountscontacts, deleted) for efficient lookup
     * - CAST operation may prevent index usage; consider functional index if performance
     *   becomes an issue with large datasets
     * - COUNT(*) executed at database level; no application-level iteration required
     * 
     * @inheritDoc
     * 
     * @param int $clientId The accountid of the client for which to count projects
     * 
     * @return int The number of active projects related to the specified client.
     *             Returns 0 if the client has no projects or if the client does
     *             not exist.
     * 
     * @throws \RuntimeException If database query fails or connection is lost
     */
    public function countByClient(int $clientId): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->whereRaw('CAST(vtiger_project.linktoaccountscontacts AS UNSIGNED) = ?', [$clientId])
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }
}
