<?php

namespace App\Application\DTOs\Task;

/**
 * Update Task Request DTO
 * 
 * Data transfer object for updating an existing task.
 * Contains only the fields that can be modified, all optional
 * to support partial updates (PATCH semantics).
 * 
 * Validation rules:
 * - At least one field must be provided for update
 * - Subject must not exceed 255 characters if provided
 * - Dates must be valid and logically consistent (due_date >= date_start)
 * - Priority must be one of: Low, Medium, High
 * - Status must be one of: Not Started, In Progress, Completed, Pending Input, Planned
 * - Assigned user ID must reference a valid, active user if provided
 * 
 * @package App\Application\DTOs
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Task\UpdateTaskUseCase
 * @see \App\Domain\Entities\Task
 * @see \App\Application\DTOs\Task\UpdateTaskData
 */
class UpdateTaskRequest
{
    /**
     * @param string|null $subject New task subject (optional)
     * @param string|null $dateStart New start date in YYYY-MM-DD format (optional)
     * @param string|null $dueDate New due date in YYYY-MM-DD format (optional) ⭐ IMPORTANTE
     * @param string|null $timeStart New start time in HH:MM format (optional)
     * @param string|null $timeEnd New end time in HH:MM format (optional)
     * @param string|null $priority New priority level (optional)
     * @param string|null $status New status (optional)
     * @param string|null $location New location (optional)
     * @param string|null $description New description (optional)
     * @param int|null $relatedRecordId New related record ID (optional)
     * @param string|null $relatedModuleType New related module type (optional)
     * @param bool|null $sendNotification Send notification flag (optional)
     * @param int|null $assignedUserId New assigned user ID (optional)
     * @param string|null $durationHours Duration in hours (optional)
     * @param string|null $durationMinutes Duration in minutes (optional)
     */
    public function __construct(
        public readonly ?string $subject = null,
        public readonly ?string $dateStart = null,
        public readonly ?string $dueDate = null,      
        public readonly ?string $timeStart = null,
        public readonly ?string $timeEnd = null,
        public readonly ?string $priority = null,
        public readonly ?string $status = null,
        public readonly ?string $location = null,
        public readonly ?string $description = null,
        public readonly ?int $relatedRecordId = null,
        public readonly ?string $relatedModuleType = null,
        public readonly ?bool $sendNotification = null,
        public readonly ?int $assignedUserId = null,
        public readonly ?string $durationHours = null,
        public readonly ?string $durationMinutes = null,
    ) {
        // Optional: Add validation here if needed
        if ($this->subject !== null && strlen(trim($this->subject)) > 255) {
            throw new \InvalidArgumentException('Subject cannot exceed 255 characters');
        }
    }

    

    /**
     * Convert to array for repository layer
     * 
     * Maps camelCase properties to snake_case database columns.
     * 
     * @return array<string, mixed> Associative array with fields to update
     * 
     * @example
     * $request = new UpdateTaskRequest(subject: 'New title', status: 'Completed');
     * $array = $request->toUpdateArray();
     * // Returns: ['subject' => 'New title', 'status' => 'Completed']
     */
    public function toUpdateArray(): array
    {
        $data = [];
        
        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }
        if ($this->dateStart !== null) {
            $data['date_start'] = $this->dateStart;
        }
        if ($this->dueDate !== null) {
            $data['due_date'] = $this->dueDate;
        }
        if ($this->timeStart !== null) {
            $data['time_start'] = $this->timeStart;
        }
        if ($this->timeEnd !== null) {
            $data['time_end'] = $this->timeEnd;
        }
        if ($this->priority !== null) {
            $data['priority'] = $this->priority;
        }
        if ($this->status !== null) {
            $data['status'] = $this->status;
        }
        if ($this->location !== null) {
            $data['location'] = $this->location;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->relatedRecordId !== null) {
            $data['related_record_id'] = $this->relatedRecordId;
        }
        if ($this->relatedModuleType !== null) {
            $data['related_module_type'] = $this->relatedModuleType;
        }
        if ($this->sendNotification !== null) {
            $data['send_notification'] = $this->sendNotification ? '1' : '0';
        }
        if ($this->assignedUserId !== null) {
            $data['assigned_user_id'] = $this->assignedUserId;
        }
        if ($this->durationHours !== null) {
            $data['duration_hours'] = $this->durationHours;
        }
        if ($this->durationMinutes !== null) {
            $data['duration_minutes'] = $this->durationMinutes;
        }
        
        return $data;
    }

    /**
     * Convert to associative array for general processing
     * 
     * Returns only non-null values with camelCase keys.
     * 
     * @return array<string, mixed> Array with non-null values
     */
    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    // ========================================================================
    // HELPER METHODS FOR CONDITIONAL UPDATES
    // ========================================================================

    /**
     * Check if a specific field is set and not null
     * 
     * @param string $field Field name to check (camelCase property name)
     * @return bool True if field exists and is not null
     * 
     * @example
     * if ($request->hasField('status')) {
     *     // Status is being updated
     * }
     */
    public function hasField(string $field): bool
    {
        return property_exists($this, $field) && $this->$field !== null;
    }

    /**
     * Check if status field should be updated
     */
    public function shouldUpdateStatus(): bool
    {
        return $this->status !== null;
    }

    /**
     * Check if dates should be updated
     */
    public function shouldUpdateDates(): bool
    {
        return $this->dateStart !== null || $this->dueDate !== null;
    }

    /**
     * Check if assigned user should be updated
     */
    public function shouldUpdateAssignedUser(): bool
    {
        return $this->assignedUserId !== null;
    }

    /**
     * Check if duration fields should be updated
     */
    public function shouldUpdateDuration(): bool
    {
        return $this->durationHours !== null || $this->durationMinutes !== null;
    }

    /**
     * Check if notification preference should be updated
     */
    public function shouldUpdateNotification(): bool
    {
        return $this->sendNotification !== null;
    }
}