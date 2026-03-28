<?php

namespace App\Application\DTOs\Task;

use App\Application\DTOs\Task\UpdateTaskRequest;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

/**
 * DTO for updating an existing Task.
 * 
 * Groups fields from both vtiger_activity and vtiger_crmentity tables.
 * Provides type-safe access and clear separation of concerns.
 * 
 * @package App\Application\DTOs\Task
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\DTOs\Task\UpdateTaskRequest
 * @see \App\Infrastructure\Repositories\VtigerTaskRepository
 */
class UpdateTaskData
{
    /**
     * Fields that belong to vtiger_activity table
     * 
     * @var array<string, mixed>
     */
    private const ACTIVITY_FIELDS = [
        'subject',
        'activitytype',
        'date_start',
        'due_date',
        'time_start',
        'time_end',
        'status',
        'priority',
        'location',
        'sendnotification',
        'duration_hours',
        'duration_minutes',
        'visibility',
        'notime',
    ];

    /**
     * Fields that belong to vtiger_crmentity table (excludes smownerid)
     * 
     * @var array<string, mixed>
     */
    private const CRMENTITY_FIELDS = [
        'description',
        'modifiedtime',  
        'modifiedby',    
        // smownerid is handled separately via assignedUserId
    ];

    /**
     * @param int $taskId ID of the task to update
     * @param array $activityData Fields for vtiger_activity table (only changed fields)
     * @param array $crmentityData Fields for vtiger_crmentity table (only changed fields)
     * @param int|null $assignedUserId New assigned user ID (smownerid). Null = don't change.
     * @param int $authenticatedUserId User performing the update (for audit/fallback)
     * @param bool $shouldUpdateAssignedUser Whether to update the assigned user field
     * @param bool $shouldSyncLabel Whether to sync the label in vtiger_crmentity
     */
    public function __construct(
        public readonly int $taskId,
        public readonly array $activityData,
        public readonly array $crmentityData,
        public readonly ?int $assignedUserId,
        public readonly int $authenticatedUserId,
        public readonly bool $shouldUpdateAssignedUser = false,
        public readonly bool $shouldSyncLabel = true,
    ) {}

    /**
     * Factory method: Create from UpdateTaskRequest
     * 
     * Filters and sanitizes input, separating fields by target table.
     * 
     * @param int $taskId ID of task to update
     * @param UpdateTaskRequest $request Validated update request
     * @param int $authenticatedUserId User ID from auth context
     * @return self New UpdateTaskData instance
     * 
     * @example
     * $data = UpdateTaskData::fromRequest(
     *     taskId: 123,
     *     request: $updateRequest,
     *     authenticatedUserId: 456
     * );
     */
    public static function fromRequest(int $taskId, UpdateTaskRequest $request, int $authenticatedUserId): self
    {
        $activityData = [];
        
        
        // Subject
        if ($request->subject !== null && $request->subject !== '') {
            $activityData['subject'] = trim($request->subject);
        }
        
       
        if ($request->dueDate !== null && $request->dueDate !== '') {
            $activityData['due_date'] = $request->dueDate;
            Log::info('✅ dueDate MAPPED (direct access)', [
                'request_dueDate' => $request->dueDate,
                'activityData_due_date' => $activityData['due_date']
            ]);
        } else {
            Log::warning('❌ dueDate NOT MAPPED (direct access)', [
                'request_dueDate_is_null' => $request->dueDate === null,
                'request_dueDate_is_empty' => $request->dueDate === '',
                'request_dueDate_value' => $request->dueDate,
            ]);
        }
        
        // dateStart → date_start
        if ($request->dateStart !== null && $request->dateStart !== '') {
            $activityData['date_start'] = $request->dateStart;
        }
        
        // timeStart → time_start
        if ($request->timeStart !== null && $request->timeStart !== '') {
            $activityData['time_start'] = $request->timeStart;
        }
        
        // timeEnd → time_end
        if ($request->timeEnd !== null && $request->timeEnd !== '') {
            $activityData['time_end'] = $request->timeEnd;
        }
        
        // Status
        if ($request->status !== null && $request->status !== '') {
            $activityData['status'] = $request->status;
        }
        
        // Priority
        if ($request->priority !== null && $request->priority !== '') {
            $activityData['priority'] = $request->priority;
        }
        
        // Location
        if ($request->location !== null && $request->location !== '') {
            $activityData['location'] = trim($request->location);
        }
        
        // sendNotification → sendnotification
        if ($request->sendNotification !== null) {
            $activityData['sendnotification'] = $request->sendNotification ? '1' : '0';
        }
        
        // Duration fields
        if ($request->durationHours !== null && $request->durationHours !== '') {
            $activityData['duration_hours'] = $request->durationHours;
        }
        if ($request->durationMinutes !== null && $request->durationMinutes !== '') {
            $activityData['duration_minutes'] = $request->durationMinutes;
        }

        // Crmentity fields
        $crmentityData = [];
        if ($request->description !== null && $request->description !== '') {
            $crmentityData['description'] = $request->description;
        }

        // Always update modifiedtime in crmentity ONLY
        $now = now()->format('Y-m-d H:i:s');
        $crmentityData['modifiedtime'] = $now;
        $crmentityData['modifiedby'] = $authenticatedUserId;

        // Handle assigned_user_id
        $shouldUpdateAssignedUser = $request->assignedUserId !== null;
        $assignedUserId = $shouldUpdateAssignedUser && $request->assignedUserId > 0
            ? (int) $request->assignedUserId
            : null;

        // Sync label if subject changed
        $shouldSyncLabel = $request->subject !== null && !empty(trim($request->subject));

        return new self(
            taskId: $taskId,
            activityData: $activityData,
            crmentityData: $crmentityData,
            assignedUserId: $assignedUserId,
            authenticatedUserId: $authenticatedUserId,
            shouldUpdateAssignedUser: $shouldUpdateAssignedUser,
            shouldSyncLabel: $shouldSyncLabel
        );
    }

    /**
     * Get complete data for vtiger_activity update
     * 
     * @return array<string, mixed> Data ready for database update
     */
    public function getActivityData(): array
    {
        return $this->activityData;
    }

    /**
     * Get complete data for vtiger_crmentity update
     * 
     * Includes smownerid if shouldUpdateAssignedUser is true.
     * 
     * @return array<string, mixed> Data ready for database update
     */
    public function getCrmentityData(): array
    {
        $data = $this->crmentityData;

        // Only include smownerid if it should be updated
        if ($this->shouldUpdateAssignedUser && $this->assignedUserId !== null) {
            $data['smownerid'] = $this->assignedUserId;
        }

        return $data;
    }

    /**
     * Get the new subject for label sync (if applicable)
     * 
     * @return string|null New subject value, or null if not changing
     */
    public function getSubjectForLabelSync(): ?string
    {
        if (!$this->shouldSyncLabel) {
            return null;
        }
        return $this->activityData['subject'] ?? null;
    }

    /**
     * Check if there are any changes to persist
     * 
     * @return bool True if any field has changes, false otherwise
     */
    public function hasChanges(): bool
    {
        return !empty($this->activityData) || 
               !empty($this->crmentityData) || 
               ($this->shouldUpdateAssignedUser && $this->assignedUserId !== null);
    }

    /**
     * Get the task ID being updated
     */
    public function getTaskId(): int
    {
        return $this->taskId;
    }

    /**
     * Get the authenticated user ID
     */
    public function getAuthenticatedUserId(): int
    {
        return $this->authenticatedUserId;
    }
}