<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ProjectRepositoryInterface;
use App\Domain\Entities\Project;
use App\Helpers\TimeHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_project - Project data table
 *
 * Handles CRUD operations for project data in Vtiger CRM.
 * This repository implements ProjectRepositoryInterface, providing both
 * simple table operations (for Create/Update UseCases) and complex
 * queries with joins (for Get/List UseCases).
 */
class ProjectRepository implements ProjectRepositoryInterface
{
    private const TABLE = 'vtiger_project';

    private const CONNECTION = 'vtiger';

    // =========================================================================
    // Simple CRUD Methods (used by Create/Update UseCases)
    // =========================================================================

    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        $projectId = $data['projectid'] ?? null;
        if (! $projectId) {
            throw new InvalidArgumentException('projectid is required for insert');
        }

        DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $projectId;
    }

    public function updateProject(int $projectId, array $data): bool
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('projectid must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $allowedFields = [
            'projectname',
            'project_no',
            'startdate',
            'targetenddate',
            'actualenddate',
            'targetbudget',
            'projecturl',
            'projectstatus',
            'projectpriority',
            'projecttype',
            'progress',
            'linktoaccountscontacts',

        ];

        $sanitized = array_intersect_key($data, array_flip($allowedFields));

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('projectid', $projectId)
            ->update($sanitized);

        return $affected > 0;
    }

    public function findProjectById(int $projectId): ?array
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('projectid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('projectid', $projectId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $projectId): bool
    {
        if ($projectId <= 0) {
            return false;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('projectid', $projectId)
            ->exists();
    }

    public function getNextProjectNumber(): string
    {
        $count = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->count();

        return 'PRJ' . date('Y') . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // Interface Implementation: ProjectRepositoryInterface
    // =========================================================================

    /**
     * {@inheritDoc}
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


            $lastActivityRaw = $row->last_activity_calculated ?? $row->modifiedtime;


            $lastActivityFormatted = TimeHelper::formatTimeAgo($lastActivityRaw);

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

                // ✅ CAMBIO PRINCIPAL: Guardar formato "time ago"
                lastActivity: $lastActivityFormatted,  // ← "1 min ago" en lugar de "2026-03-31 14:59:06"

                // ✅ Opcional: También guardar raw si lo necesitas en el frontend
                lastActivityRaw: $lastActivityRaw,  // ← Agregar este campo al entity si es necesario
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
     * {@inheritDoc}
     */
    public function findById(int $projectId): ?Project
    {
        if ($projectId <= 0) {
            return null;
        }

        $row = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->leftJoin('vtiger_users as assigned_user', 'vtiger_crmentity.smownerid', '=', 'assigned_user.id')
            ->select(
                'vtiger_project.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.deleted as crm_deleted',
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

        if (! $row) {
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
            projectid: (int) $row->projectid,
            projectname: (string) $row->projectname,
            project_no: (string) $row->project_no,
            startdate: $row->startdate,
            targetenddate: $row->targetenddate,
            actualenddate: $row->actualenddate,
            targetbudget: $row->targetbudget,
            projecturl: $row->projecturl,
            projectstatus: (string) $row->projectstatus,
            projectpriority: (string) $row->projectpriority,
            projecttype: $row->projecttype,
            progress: $row->progress ? (int)$row->progress : 0,
            linktoaccountscontacts: $row->linktoaccountscontacts,
            assigned_user_id: $row->assigned_user_id ? (int) $row->assigned_user_id : null,
            assigned_user_name: $row->assigned_user_name && trim($row->assigned_user_name) !== ' '
                ? $row->assigned_user_name
                : null,
            account_name: $row->account_name ?? '',
            potential_name: null,
            totalTasks: $totalTasks,
            completedTasks: $completedTasks,
            hits: null,
            description: $row->description,
            createdtime: $row->createdtime,
            modifiedtime: $row->modifiedtime,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): int
    {
        throw new RuntimeException('Use CreateProjectUseCase for project creation');
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $projectId, array $data): bool
    {
        throw new RuntimeException('Use UpdateProjectUseCase for project updates');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $projectId): bool
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Project ID must be positive');
        }

        $updated = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $projectId)
            ->where('setype', 'Project')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getTasksByProject(int $projectId): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_activity.projectid', $projectId)
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->select('vtiger_activity.*', 'vtiger_crmentity.createdtime', 'vtiger_crmentity.modifiedtime')
            ->orderBy('vtiger_activity.due_date')
            ->get();

        return $rows->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limit): array
    {
        $rows = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_account', 'vtiger_project.linktoaccountscontacts', '=', 'vtiger_account.accountid')
            ->select(
                'vtiger_project.projectid',
                'vtiger_project.projectname',
                'vtiger_project.project_no',
                'vtiger_project.projectstatus',
                'vtiger_account.accountname'
            )
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_crmentity.setype', 'Project')
            ->where('vtiger_project.projectname', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get();

        return $rows->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function countByClient(int $clientId): int
    {
        if ($clientId <= 0) {
            return 0;
        }

        return DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_project.linktoaccountscontacts', $clientId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateRequiredFields(array $data): void
    {
        $required = ['projectid', 'projectname'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'projectid' => $data['projectid'],
            'project_no' => $data['project_no'] ?? null,
            'projectname' => $data['projectname'],
            'startdate' => $data['startdate'] ?? null,
            'targetenddate' => $data['targetenddate'] ?? null,
            'actualenddate' => $data['actualenddate'] ?? null,
            'targetbudget' => $data['targetbudget'] ?? null,
            'projecturl' => $data['projecturl'] ?? null,
            'projectstatus' => $data['projectstatus'] ?? 'Draft',
            'projectpriority' => $data['projectpriority'] ?? 'Normal',
            'projecttype' => $data['projecttype'] ?? null,
            'progress' => $data['progress'] ?? null,
            'linktoaccountscontacts' => $data['linktoaccountscontacts'] ?? null,
            'tags' => $data['tags'] ?? null,
            'isconvertedfrompotential' => $data['isconvertedfrompotential'] ?? '0',
            'potentialid' => $data['potentialid'] ?? null,
            'cf_922' => $data['cf_922'] ?? null,
        ];
    }
}
