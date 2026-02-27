<?php

namespace App\Application\DTOs\Task;

/**
 * DTO for creating a new task
 */
class CreateTaskRequest
{
    public function __construct(
        public readonly string $subject,
        public readonly string $activityType,
        public readonly string $dateStart,
        public readonly ?string $dueDate,
        public readonly ?string $timeStart,
        public readonly ?string $timeEnd,
        public readonly ?string $priority,
        public readonly ?string $status,
        public readonly ?string $location,
        public readonly ?string $description,
        public readonly int $assignedUserId,
        public readonly ?int $relatedRecordId,
        public readonly ?string $relatedModuleType,
        public readonly bool $sendNotification = false,
        public readonly ?string $durationHours = null,
        public readonly ?string $durationMinutes = null,
    ) {}
}