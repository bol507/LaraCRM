<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\Task\UpdateTaskStatusRequest;
use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Vtiger Implementation of Task Repository
 * 
 * Persists Task entities to Vtiger CRM database tables:
 * - vtiger_activity: Main task data (subject, dates, status, priority, etc.)
 * - vtiger_crmentity: Metadata (permissions, soft delete, timestamps, ownership)
 * - vtiger_seactivityrel: Relationships to other CRM entities (projects, quotes, etc.)
 * - vtiger_users: Assigned user information (via join)
 * 
 * This implementation follows Vtiger's data model conventions:
 * - Auto-increment IDs via MAX(id) + 1 (Vtiger legacy pattern)
 * - Soft delete via vtiger_crmentity.deleted flag (0 = active, 1 = deleted)
 * - Module type 'Calendar' for task entities
 * - Ownership via smownerid (assigned) and smcreatorid (creator)
 * 
 * All queries respect:
 * - Soft delete filtering (deleted = 0) unless explicitly bypassed
 * - User permission checks (assigned OR creator)
 * - Activity type filtering (activitytype = 'Task')
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @implements TaskRepositoryInterface
 * @see \App\Application\Repositories\TaskRepositoryInterface
 * @see \App\Domain\Entities\Task
 * @see \App\Application\DTOs\CreateTaskRequest
 * @see \App\Application\UseCases\Task\CreateTaskUseCase
 */
class VtigerTaskRepository implements TaskRepositoryInterface
{
    /**
     * Default pagination settings
     */
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 100;

    /**
     * Vtiger module type for tasks
     */
    private const MODULE_TYPE = 'Calendar';

    /**
     * Valid task statuses in Vtiger
     */
    private const VALID_STATUSES = [
        'Not Started',
        'In Progress',
        'Completed',
        'Pending Input',
        'Planned',
    ];

    /**
     * Valid task priorities in Vtiger
     */
    private const VALID_PRIORITIES = ['Low', 'Medium', 'High'];

    /**
     * {@inheritDoc}
     */
    public function findByUserId(
        int $userId,
        int $limit = 50,
        array $filters = [],
        int $offset = 0
    ): array {
        // Validate input parameters
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }
        if ($limit < 1) {
            throw new InvalidArgumentException("Limit must be at least 1, got {$limit}");
        }
        if ($offset < 0) {
            throw new InvalidArgumentException("Offset cannot be negative, got {$offset}");
        }

        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    // Tasks assigned to user OR created by user
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                });

            // Apply optional filters
            $this->applyFilters($query, $filters);

            $results = $query
                ->select(
                    'vtiger_activity.*',
                    'vtiger_crmentity.smcreatorid',
                    'vtiger_crmentity.smownerid',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    'vtiger_crmentity.description',
                    'vtiger_seactivityrel.crmid as related_record_id',
                    'vtiger_crmentity.setype as related_module_type',
                    'vtiger_users.first_name',
                    'vtiger_users.last_name',
                    'vtiger_users.email1 as email'
                )
                // Order: pending tasks first, then by due date (most urgent first)
                ->orderByRaw('CASE 
                    WHEN vtiger_activity.status = "Completed" THEN 1 
                    ELSE 0 
                END')
                ->orderBy('vtiger_activity.due_date', 'ASC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return $results->map(fn($row) => $this->mapToEntity($row))->toArray();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve tasks for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function countByUserId(int $userId, array $filters = []): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }

        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                });

            $this->applyFilters($query, $filters);

            return $query->count();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to count tasks for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findDashboardTasks(int $userId, int $limit = 10): array
    {
        // Dashboard widget: only pending/in-progress tasks, ordered by urgency
        return $this->findByUserId($userId, $limit, [
            'status' => ['Not Started', 'In Progress', 'Pending Input'],
        ], offset: 0);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $taskId): ?Task
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_activity.activityid', $taskId)
                ->where('vtiger_crmentity.deleted', 0) // Exclude soft-deleted
                ->select(
                    'vtiger_activity.*',
                    'vtiger_crmentity.smcreatorid',
                    'vtiger_crmentity.smownerid',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    'vtiger_crmentity.description',
                    'vtiger_seactivityrel.crmid as related_record_id',
                    'vtiger_crmentity.setype as related_module_type',
                    'vtiger_users.first_name',
                    'vtiger_users.last_name',
                    'vtiger_users.email1 as email'
                )
                ->first();

            if (!$row) {
                return null;
            }

            return $this->mapToEntity($row);

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdIncludingDeleted(int $taskId): ?Task
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_activity.activityid', $taskId)
                // Note: NOT filtering by deleted = 0 to include soft-deleted tasks
                ->select(
                    'vtiger_activity.*',
                    'vtiger_crmentity.smcreatorid',
                    'vtiger_crmentity.smownerid',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    'vtiger_crmentity.description',
                    'vtiger_crmentity.deleted', // Include deleted flag for context
                    'vtiger_seactivityrel.crmid as related_record_id',
                    'vtiger_crmentity.setype as related_module_type',
                    'vtiger_users.first_name',
                    'vtiger_users.last_name',
                    'vtiger_users.email1 as email'
                )
                ->first();

            if (!$row) {
                return null;
            }

            return $this->mapToEntity($row);

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve task {$taskId} (including deleted): " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllDashboardTasks(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }

        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                });

            $results = $query
                ->select(
                    'vtiger_activity.*',
                    'vtiger_crmentity.smcreatorid',
                    'vtiger_crmentity.smownerid',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    'vtiger_crmentity.description',
                    'vtiger_seactivityrel.crmid as related_record_id',
                    'vtiger_crmentity.setype as related_module_type',
                    'vtiger_users.first_name',
                    'vtiger_users.last_name',
                    'vtiger_users.email1 as email'
                )
                // Order by most recent first (for full task list view)
                ->orderBy('vtiger_crmentity.createdtime', 'DESC')
                ->limit($limit)
                ->get();

            return $results->map(fn($row) => $this->mapToEntity($row))->toArray();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve all dashboard tasks for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateTaskRequest $request): int
    {
        try {
            return DB::connection('vtiger')->transaction(function () use ($request) {
                // Generate new task ID using Vtiger legacy pattern: MAX(id) + 1
                $activityId = (int) (DB::connection('vtiger')
                    ->table('vtiger_activity')
                    ->max('activityid') ?? 0) + 1;

                $now = now()->format('Y-m-d H:i:s');

                // Insert main task data into vtiger_activity
                DB::connection('vtiger')->table('vtiger_activity')->insert([
                    'activityid' => $activityId,
                    'subject' => $request->subject,
                    'activitytype' => $request->activityType,
                    'date_start' => $request->dateStart,
                    'due_date' => $request->dueDate ?? $request->dateStart,
                    'time_start' => $request->timeStart,
                    'time_end' => $request->timeEnd,
                    'status' => $request->status ?? 'Not Started',
                    'priority' => $request->priority ?? 'Medium',
                    'location' => $request->location,
                    'sendnotification' => $request->sendNotification ? '1' : '0',
                    'duration_hours' => $request->durationHours,
                    'duration_minutes' => $request->durationMinutes,
                    'visibility' => 'all',
                    'notime' => '0',
                ]);

                // Insert metadata into vtiger_crmentity (required for Vtiger permissions/soft-delete)
                DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $activityId,
                    'smcreatorid' => $request->assignedUserId,
                    'smownerid' => $request->assignedUserId,
                    'modifiedby' => $request->assignedUserId,
                    'setype' => self::MODULE_TYPE,
                    'description' => $request->description,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'deleted' => 0,
                    'version' => 0,
                    'presence' => 1,
                ]);

                // Insert relationship if task is linked to another CRM entity
                if ($request->relatedRecordId && $request->relatedModuleType) {
                    DB::connection('vtiger')->table('vtiger_seactivityrel')->insert([
                        'activityid' => $activityId,
                        'crmid' => $request->relatedRecordId,
                    ]);
                }

                return $activityId;
            });

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create task: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $taskId, array $data): bool
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        try {
            $now = now()->format('Y-m-d H:i:s');
            $updated = false;

            // Prepare update data for vtiger_activity (only include provided fields)
            $activityUpdate = [];
            
            $updatableFields = [
                'subject', 'date_start', 'due_date', 'time_start', 'time_end',
                'status', 'priority', 'location', 'description',
                'related_record_id', 'related_module_type', 'sendnotification',
            ];

            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    // Map PHP naming to database column names
                    $column = match($field) {
                        'related_record_id' => null, // Handled separately via vtiger_seactivityrel
                        'related_module_type' => null,
                        'sendnotification' => 'sendnotification',
                        default => $field,
                    };
                    
                    if ($column) {
                        $activityUpdate[$column] = $data[$field];
                    }
                }
            }

            // Update vtiger_activity if there are fields to update
            if (!empty($activityUpdate)) {
                $activityUpdate['modifiedtime'] = $now;
                
                $updated = DB::connection('vtiger')
                    ->table('vtiger_activity')
                    ->where('activityid', $taskId)
                    ->update($activityUpdate) > 0;
            }

            // Handle related record relationship updates
            if (isset($data['related_record_id']) || isset($data['related_module_type'])) {
                $this->updateTaskRelationship($taskId, $data);
                $updated = true;
            }

            // Always update modifiedtime in vtiger_crmentity for audit consistency
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $taskId)
                ->update([
                    'modifiedtime' => $now,
                    'modifiedby' => $data['modified_by'] ?? 0,
                    'version' => DB::raw('version + 1'),
                ]);

            return $updated;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to update task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(int $taskId, UpdateTaskStatusRequest $request, int $modifiedBy): bool
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }
        if (!in_array($request->status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$request->status}");
        }

        try {
            return DB::connection('vtiger')->transaction(function () use ($taskId, $request, $modifiedBy) {
                $now = now()->format('Y-m-d H:i:s');

                // Update status in vtiger_activity
                $updated = DB::connection('vtiger')
                    ->table('vtiger_activity')
                    ->where('activityid', $taskId)
                    ->update([
                        'status' => $request->status,
                        'modifiedtime' => $now,
                    ]);

                if ($updated === 0) {
                    return false;
                }

                // Update metadata in vtiger_crmentity
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $taskId)
                    ->update([
                        'modifiedtime' => $now,
                        'modifiedby' => $modifiedBy,
                        'version' => DB::raw('version + 1'),
                    ]);

                return true;
            });

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to update status for task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $taskId): bool
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        try {
            // Soft delete: mark as deleted in vtiger_crmentity
            $updated = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $taskId)
                ->where('deleted', 0) // Only delete if not already deleted
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return $updated > 0;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to delete task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function restore(int $taskId): bool
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        try {
            // Restore: mark as not deleted in vtiger_crmentity
            $updated = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $taskId)
                ->where('deleted', 1) // Only restore if currently deleted
                ->update([
                    'deleted' => 0,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return $updated > 0;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to restore task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStatistics(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }

        try {
            $tasks = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                })
                ->select(
                    'vtiger_activity.status',
                    'vtiger_activity.priority',
                    'vtiger_activity.due_date'
                )
                ->get();

            $total = $tasks->count();
            $completed = $tasks->where('status', 'Completed')->count();
            $pending = $tasks->whereNotIn('status', ['Completed'])->count();

            $today = now()->format('Y-m-d');
            $overdue = $tasks->whereNotIn('status', ['Completed'])
                ->where('vtiger_activity.due_date', '<', $today)
                ->count();

            $highPriority = $tasks->where('vtiger_activity.priority', 'High')
                ->whereNotIn('status', ['Completed'])
                ->count();

            return [
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
                'highPriority' => $highPriority,
            ];

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get statistics for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCountByStatus(int $userId): array
    {
        try {
            $results = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                })
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get();

            return $results->pluck('count', 'status')->toArray();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get status counts for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCountByPriority(int $userId): array
    {
        try {
            $results = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_crmentity.deleted', 0)
                ->where('vtiger_activity.activitytype', 'Task')
                ->where(function ($q) use ($userId) {
                    $q->where('vtiger_crmentity.smownerid', $userId)
                        ->orWhere('vtiger_crmentity.smcreatorid', $userId);
                })
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get();

            return $results->pluck('count', 'priority')->toArray();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to get priority counts for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deletePermanently(int $olderThanDays): int
    {
        if ($olderThanDays <= 0) {
            throw new InvalidArgumentException("Retention days must be positive, got {$olderThanDays}");
        }

        try {
            $cutoffDate = now()->subDays($olderThanDays)->format('Y-m-d H:i:s');

            // Get IDs of tasks to permanently delete (for audit logging)
            $taskIds = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('setype', self::MODULE_TYPE)
                ->where('deleted', 1)
                ->where('modifiedtime', '<', $cutoffDate)
                ->pluck('crmid');

            if ($taskIds->isEmpty()) {
                return 0;
            }

            // Delete from vtiger_seactivityrel first (foreign key constraint)
            DB::connection('vtiger')
                ->table('vtiger_seactivityrel')
                ->whereIn('activityid', $taskIds)
                ->delete();

            // Delete from vtiger_activity
            DB::connection('vtiger')
                ->table('vtiger_activity')
                ->whereIn('activityid', $taskIds)
                ->delete();

            // Finally delete from vtiger_crmentity
            $deleted = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->whereIn('crmid', $taskIds)
                ->delete();

            return $deleted;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to permanently delete old tasks: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

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

    /**
     * Update task relationship to another CRM entity
     * 
     * Handles insert/update/delete in vtiger_seactivityrel table.
     * 
     * @param int $taskId Task ID
     * @param array<string, mixed> $data Update data with related_record_id and/or related_module_type
     * @return void
     */
    private function updateTaskRelationship(int $taskId, array $data): void
    {
        $relatedRecordId = $data['related_record_id'] ?? null;
        $relatedModuleType = $data['related_module_type'] ?? null;

        // If both are null or empty, remove existing relationship
        if (!$relatedRecordId || !$relatedModuleType) {
            DB::connection('vtiger')
                ->table('vtiger_seactivityrel')
                ->where('activityid', $taskId)
                ->delete();
            return;
        }

        // Upsert relationship (insert or update)
        DB::connection('vtiger')
            ->table('vtiger_seactivityrel')
            ->updateOrInsert(
                ['activityid' => $taskId],
                [
                    'crmid' => $relatedRecordId,
                ]
            );
    }

    /**
     * Map database row to Task entity using factory method
     * 
     * Centralizes the mapping logic to ensure consistency across all queries.
     * 
     * @param object $row Database row from joined query
     * @return Task Mapped domain entity
     * 
     * @internal Only for internal use by this repository
     */
    private function mapToEntity(object $row): Task
    {
        return new Task(
            id: (int) $row->activityid,
            subject: $row->subject,
            activityType: $row->activitytype,
            dateStart: new DateTimeImmutable($row->date_start),
            dueDate: $row->due_date ? new DateTimeImmutable($row->due_date) : null,
            timeStart: $row->time_start,
            timeEnd: $row->time_end,
            status: $row->status ?: 'Not Started',
            priority: $row->priority ?: 'Medium',
            location: $row->location,
            description: $row->description,
            assignedUserId: (int) $row->smownerid,
            createdByUserId: (int) $row->smcreatorid,
            createdAt: new DateTimeImmutable($row->createdtime),
            updatedAt: new DateTimeImmutable($row->modifiedtime),
            relatedRecordId: $row->related_record_id ? (int) $row->related_record_id : null,
            relatedModuleType: $row->related_module_type,
            sendNotification: $row->sendnotification === '1',
            durationHours: $row->duration_hours,
            durationMinutes: $row->duration_minutes,
        );
    }
}