<?php

namespace App\Infrastructure\Repositories\Core;

use Illuminate\Support\Facades\DB;

/**
 * Repository for vtiger_activity table
 *
 * Handles task/event scheduling data in Vtiger
 */
class ActivityRepository
{
    private const TABLE = 'vtiger_activity';

    public function __construct(
        private readonly string $connection = 'vtiger'
    ) {}

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
        DB::connection($this->connection)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $data['activityid'];
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
        ]);

        if (empty($sanitized)) {
            return true; // Nothing left to update after sanitization
        }

        $affected = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('activityid', $activityId)
            ->update($sanitized);

        return $affected > 0;
    }

    /**
     * Find activity by ID
     */
    public function findById(int $activityId): ?array
    {
        $result = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('activityid', $activityId)
            ->first();

        return $result ? (array) $result : null;
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
}
