<?php

namespace App\Infrastructure\Repositories\Core;

use App\Application\Repositories\ActivityRepositoryInterface;
use App\Domain\Entities\Activity;
use App\Infrastructure\Mappers\ActivityMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_activity table
 *
 * Handles task/event scheduling data in Vtiger
 */
class ActivityRepository implements ActivityRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const ACTIVITY_TABLE = 'vtiger_activity';

    public function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::ACTIVITY_TABLE);
    }


    /**
     * Insert a new activity record
     *
     * @param array{
     *   activityid: int,
     *   subject: string,
     *   activitytype: string,
     *   date_start: string,
     *   time_start?: string,
     *   due_date?: string,
     *   time_end?: string,
     *   status: string,
     *   priority?: string,
     *   location?: string,
     *   description?: string,
     *   assigned_user_id?: int,
     *   createdtime: string,
     *   modifiedtime: string,
     *   ...additional Vtiger fields
     * } $data
     * @return int The inserted activityid
     */
    public function insert(array $data): int
    {
        $this->query()
            ->insert($this->prepareData($data));

        return (int) $data['activityid'];
    }

    

    /**
     * Update an existing activity
     *
     * @param  int  $activityId  The activity ID to update
     * @param  array  $data  Fields to update (excluding managed fields like modifiedtime)
     * @return bool True if rows were affected
     *
     * @throws \InvalidArgumentException If required fields are missing
     */
    public function update(int $activityId, array $data): bool
    {
        if (empty($data)) {
            return true; // Nothing to update
        }

        // Remove managed fields that should be handled by the repository
        $sanitized = array_diff_key($data, [
            'modifiedtime' => true,  // Repo manages timestamps
            'activityid' => true,    // Primary key should not be updated
            'description' => true,   // Description is crmentity.description
        ]);

        if (empty($sanitized)) {
            return true; // Nothing left to update after sanitization
        }

        $affected = $this->query()
            ->where('activityid', $activityId)
            ->update($sanitized);

        return $affected > 0;
    }



    /**
     * Prepare data with Vtiger defaults
     *
     * Only includes columns that actually exist in vtiger_activity
     */
    private function prepareData(array $data): array
    {
        return [
            'activityid' => $data['activityid'],
            'subject' => $data['subject'],
            'activitytype' => $data['activitytype'] ?? 'Task',
            'date_start' => $data['date_start'],
            'time_start' => $data['time_start'] ?? '',
            'due_date' => $data['due_date'] ?? null,
            'time_end' => $data['time_end'] ?? '',
            'status' => $data['status'] ?? 'Not Started',
            'priority' => $data['priority'] ?? 'Medium',
            'location' => $data['location'] ?? '',
            'sendnotification' => $data['sendnotification'] ?? '0',
            'duration_hours' => $data['duration_hours'] ?? 0,
            'duration_minutes' => $data['duration_minutes'] ?? 0,
            'visibility' => $data['visibility'] ?? 'Everyone',
            'notime' => $data['notime'] ?? '0',
            'recurringtype' => $data['recurringtype'] ?? '',
            'semodule' => $data['semodule'] ?? 'Calendar',
        ];
    }

    /**
     * @inheritDoc
     */
    public function findById(int $activityId): ?object
    {
        if ($activityId <= 0) {
            throw new InvalidArgumentException("Activity ID must be positive, got {$activityId}");
        }

        try {
            // 1. Use the mapper to prepare the query with correct joins and selects
            $query = ActivityMapper::prepareQuery(
                DB::connection(self::CONNECTION)->query(),
                [] // No additional selects needed for findById
            );

            // 2. Filter by activityid and execute (first() for a single record)
            $row = $query
                ->where('act.activityid', $activityId)
                ->first();

            return $row;
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve activity {$activityId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findTasksByOwnerIds(?array $ownerIds, int $limit, array $filters, int $offset): array
    {
        $isGlobalView = $ownerIds === null;

        if (!$isGlobalView && empty($ownerIds)) {
            return ['tasks' => [], 'pagination' => []];
        }

        // 1. Use ActivityMapper::prepareQuery() for the base with joins and selects
        $query = ActivityMapper::prepareQuery(
            DB::connection(self::CONNECTION)->query(),
            [] // Additional selects if needed
        );
        if (!$isGlobalView) {
            $query->whereIn('crm.smownerid', $ownerIds);
        }

        // 2. Apply filters specific to this query
        $query->where('act.activitytype', 'Task')
            ->where('crm.deleted', 0)
            ->orderBy('act.date_start', 'DESC')
            ->orderBy('crm.createdtime', 'DESC')
            ->limit($limit)
            ->offset($offset);

        // 3. Apply additional filters (status, priority, search, etc.)
        $this->applyFilters($query, $filters);

        // 4. Execute and map using ActivityMapper::toEntities()
        $results = $query->get();



       

        // 5. Count total for pagination (reuse the same query logic)
        $totalQuery = ActivityMapper::prepareQuery(
            DB::connection(self::CONNECTION)->query(),
            []
        );
        if (!$isGlobalView) {
            $totalQuery->whereIn('crm.smownerid', $ownerIds);
        }

        $totalQuery->where('act.activitytype', 'Task')
            ->where('crm.deleted', 0);
        $this->applyFilters($totalQuery, $filters);
        $total = $totalQuery->count();

        // Return Collection of stdClass with all fields (usecase mapping to DTO)
        return [
            'activities' => $results->all(),
            'pagination' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => ($offset / $limit) + 1,
                'total_pages' => ceil($total / $limit),
                'has_more' => ($offset + $limit) < $total,
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function calculateStats(?array $ownerIds, array $filters = []): array
    {
        // ✅ Diferenciar: null = admin ve todo, [] = sin acceso
        $isGlobalView = $ownerIds === null;

        if (!$isGlobalView && empty($ownerIds)) {
            return ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'highPriority' => 0];
        }

        // ✅ Base query con mapper
        $baseQuery = ActivityMapper::prepareQuery(
            DB::connection(self::CONNECTION)->query(),
            []
        );
        $baseQuery->where('act.activitytype', 'Task')
            ->where('crm.deleted', 0);

        // ✅ Solo aplicar filtro por ownerIds si NO es vista global
        if (!$isGlobalView) {
            $baseQuery->whereIn('crm.smownerid', $ownerIds);
        }

        $this->applyFilters($baseQuery, $filters);

        return [
            'total' => (clone $baseQuery)->count(),

            'completed' => (clone $baseQuery)
                ->whereIn('act.status', ['Completed', 'Closed', 'Held'])
                ->count(),

            'pending' => (clone $baseQuery)
                ->whereNotIn('act.status', ['Completed', 'Closed', 'Held'])
                ->count(),

            'overdue' => (clone $baseQuery)
                ->whereNotIn('act.status', ['Completed', 'Closed', 'Held'])
                ->where('act.due_date', '<', date('Y-m-d'))
                ->whereNotNull('act.due_date')
                ->where('act.due_date', '!=', '0000-00-00')
                ->count(),

            'highPriority' => (clone $baseQuery)
                ->where('act.priority', 'High')
                ->whereNotIn('act.status', ['Completed', 'Closed', 'Held'])
                ->count(),
        ];
    }

    /**
     * Apply optional filters to a task query
     * 
     * @param \Illuminate\Database\Query\Builder $query Query builder to modify
     * @param array<string, mixed> $filters Filter criteria
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        // Status filter (single value or array)
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('vtiger_activity.status', $filters['status']);
            } else {
                $query->where('vtiger_activity.status', $filters['status']);
            }
        }

        // Priority filter
        if (isset($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $query->whereIn('vtiger_activity.priority', $filters['priority']);
            } else {
                $query->where('vtiger_activity.priority', $filters['priority']);
            }
        }

        // Date range filters (due_date)
        if (isset($filters['dateFrom'])) {
            $query->where('vtiger_activity.due_date', '>=', $filters['dateFrom']);
        }
        if (isset($filters['dateTo'])) {
            $query->where('vtiger_activity.due_date', '<=', $filters['dateTo']);
        }

        // Search filter (subject and description)
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_activity.subject', 'LIKE', $search)
                    ->orWhere('vtiger_crmentity.description', 'LIKE', $search);
            });
        }

        // Related module filter
        if (isset($filters['relatedModule']) && trim($filters['relatedModule']) !== '') {
            $query->where('vtiger_crmentity.setype', $filters['relatedModule']);
        }

        // Related record ID filter
        if (isset($filters['relatedRecordId']) && (int) $filters['relatedRecordId'] > 0) {
            $query->where('vtiger_seactivityrel.crmid', (int) $filters['relatedRecordId']);
        }
    }
}
