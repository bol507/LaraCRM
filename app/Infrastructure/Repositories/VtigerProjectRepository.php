<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VtigerProjectRepository implements ProjectRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
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
                assigned_user_id: $row->assigned_user_id ? (int) $row->assigned_user_id : null,
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
     * @see https://wiki.vtiger.com/index.php/Vtiger_CRM_table_structure For Vtiger database schema reference
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

    public function getTasksByProject(int $projectId): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_projecttask')
            ->where('projectid', $projectId)
            ->get()
            ->toArray();
    }

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
}
