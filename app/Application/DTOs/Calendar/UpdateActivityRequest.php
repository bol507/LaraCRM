<?php
// app/Application/DTOs/Calendar/UpdateActivityRequest.php

namespace App\Application\DTOs\Calendar;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Request DTO for updating an existing activity
 * 
 * All fields are optional (nullable) - only provided fields will be updated.
 * This follows the PATCH semantics for partial updates.
 */
class UpdateActivityRequest
{
    public function __construct(
        // Nullable: only provided fields will be updated
        public readonly ?string $subject = null,
        public readonly ?string $activityType = null,
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?string $dateStart = null,       // Y-m-d format
        public readonly ?string $dueDate = null,         // Y-m-d format, null to clear
        public readonly ?string $timeStart = null,       // HH:MM format
        public readonly ?string $timeEnd = null,         // HH:MM format
        public readonly ?string $sendNotification = null,
        public readonly ?string $location = null,
        public readonly ?string $description = null,
        public readonly ?string $visibility = null,      // 'all' or 'Owner'
        public readonly ?int $assignedUserId = null,
        public readonly ?int $relatedRecordId = null,    // null to unlink
        public readonly ?string $relatedModuleType = null,
        public readonly ?int $durationHours = null,
        public readonly ?int $durationMinutes = null,
    ) {
        // Basic validation at DTO level (controller handles request validation)
        if ($this->dateStart !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateStart)) {
            throw new InvalidArgumentException('dateStart must be in Y-m-d format');
        }
        if ($this->dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dueDate)) {
            throw new InvalidArgumentException('dueDate must be in Y-m-d format');
        }
        if ($this->timeStart !== null && !preg_match('/^\d{2}:\d{2}$/', $this->timeStart)) {
            throw new InvalidArgumentException('timeStart must be in HH:MM format');
        }
        if ($this->timeEnd !== null && !preg_match('/^\d{2}:\d{2}$/', $this->timeEnd)) {
            throw new InvalidArgumentException('timeEnd must be in HH:MM format');
        }
    }

    /**
     * Create from validated request data (controller → DTO)
     */
    public static function fromValidatedData(array $data): self
    {
        return new self(
            subject: $data['subject'] ?? null,
            status: $data['status'] ?? null,
            activityType: $data['activity_type'] ?? null,
            priority: $data['priority'] ?? null,
            dateStart: $data['date_start'] ?? null,
            dueDate: $data['due_date'] ?? null,
            timeStart: $data['time_start'] ?? null,
            timeEnd: $data['time_end'] ?? null,
            location: $data['location'] ?? null,
            description: $data['description'] ?? null,
            visibility: $data['visibility'] ?? null,
            assignedUserId: $data['assigned_user_id'] ?? null,
            relatedRecordId: $data['related_record_id'] ?? null,
            relatedModuleType: $data['related_module_type'] ?? null,
            durationHours: $data['duration_hours'] ?? null,
            durationMinutes: $data['duration_minutes'] ?? null,
        );
    }

    /**
     * Check if any field is actually provided for update
     */
    public function hasChanges(): bool
    {
        return get_object_vars($this) !== array_fill_keys(array_keys(get_object_vars($this)), null);
    }
}