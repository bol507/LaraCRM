<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\UpdateTaskStatusRequest;
use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;

class VtigerTaskRepository implements TaskRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findByUserId(int $userId, int $limit = 50, array $filters = []): array
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
                  ->orWhere('vtiger_crmentity.smcreatorid', $userId);
            });

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('vtiger_activity.status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('vtiger_activity.priority', $filters['priority']);
        }

        if (isset($filters['dateFrom'])) {
            $query->where('vtiger_activity.due_date', '>=', $filters['dateFrom']);
        }

        if (isset($filters['dateTo'])) {
            $query->where('vtiger_activity.due_date', '<=', $filters['dateTo']);
        }

        $results = $query
            ->select(
                'vtiger_activity.*',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.description',
                'vtiger_seactivityrel.crmid as related_record_id',
                'vtiger_seactivityrel.setype as related_module_type',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.email1 as email'
            )
            ->orderByRaw('CASE 
                WHEN vtiger_activity.status = "Completed" THEN 1 
                ELSE 0 
            END')
            ->orderBy('vtiger_activity.due_date', 'ASC')
            ->limit($limit)
            ->get();

        return $results->map(function ($row) {
            return $this->mapToEntity($row);
        })->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findDashboardTasks(int $userId, int $limit = 10): array
    {
        return $this->findByUserId($userId, $limit, [
            'status' => ['Not Started', 'In Progress', 'Pending Input'],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $taskId): ?Task
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_seactivityrel', 'vtiger_activity.activityid', '=', 'vtiger_seactivityrel.activityid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->where('vtiger_activity.activityid', $taskId)
            ->where('vtiger_crmentity.deleted', 0)
            ->select(
                'vtiger_activity.*',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.description',
                'vtiger_seactivityrel.crmid as related_record_id',
                'vtiger_seactivityrel.setype as related_module_type',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.email1 as email'
            )
            ->first();

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateTaskRequest $request): int
    {
        return DB::connection('vtiger')->transaction(function () use ($request) {
            // Get next activity ID
            $activityId = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->max('activityid') + 1;

            // Insert into vtiger_activity
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

            // Insert into vtiger_crmentity
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $activityId,
                'smcreatorid' => $request->assignedUserId,
                'smownerid' => $request->assignedUserId,
                'modifiedby' => $request->assignedUserId,
                'setype' => 'Calendar',
                'description' => $request->description,
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
                'version' => 0,
                'presence' => 1,
            ]);

            // Insert relationship if related record exists
            if ($request->relatedRecordId && $request->relatedModuleType) {
                DB::connection('vtiger')->table('vtiger_seactivityrel')->insert([
                    'activityid' => $activityId,
                    'crmid' => $request->relatedRecordId,
                ]);
            }

            return $activityId;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(int $taskId, UpdateTaskStatusRequest $request): bool
    {
        return DB::connection('vtiger')->transaction(function () use ($taskId, $request) {
            $updated = DB::connection('vtiger')
                ->table('vtiger_activity')
                ->where('activityid', $taskId)
                ->update([
                    'status' => $request->status,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return $updated > 0;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getStatistics(int $userId): array
    {
        $tasks = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
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
            ->where('due_date', '<', $today)
            ->count();

        $highPriority = $tasks->where('priority', 'High')
            ->whereNotIn('status', ['Completed'])
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'highPriority' => $highPriority,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getCountByStatus(int $userId): array
    {
        $results = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
                  ->orWhere('vtiger_crmentity.smcreatorid', $userId);
            })
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return $results->pluck('count', 'status')->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getCountByPriority(int $userId): array
    {
        $results = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
                  ->orWhere('vtiger_crmentity.smcreatorid', $userId);
            })
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get();

        return $results->pluck('count', 'priority')->toArray();
    }

    /**
     * Map database row to Task entity
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