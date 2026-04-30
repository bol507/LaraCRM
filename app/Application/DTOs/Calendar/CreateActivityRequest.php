<?php
// app/Application/DTOs/Calendar/CreateActivityRequest.php

namespace App\Application\DTOs\Calendar;

use InvalidArgumentException;

class CreateActivityRequest
{
    

    public function __construct(
        public readonly string $subject,
        public readonly string $activityType, // 'Task', 'Events', 'Call', 'Meeting'
        public readonly string $dateStart,    // Y-m-d
        public readonly int $assignedUserId,
        public readonly string $visibility = 'all',
        public readonly ?string $dueDate = null,
        public readonly ?string $timeStart = null,
        public readonly ?string $timeEnd = null,
        public readonly ?string $priority = 'Medium',
        public readonly ?string $status = 'Not Started',
        public readonly ?string $location = null,
        public readonly ?string $description = null,
        public readonly ?int $relatedRecordId = null,
        public readonly ?string $relatedModuleType = null,
        public readonly ?string $durationHours = null,
        public readonly ?string $durationMinutes = null,
        public readonly ?string $sendNotification = '0',
    ) {
        // Domain validations (optional, controller already validates)
        if (trim($subject) === '') {
            throw new InvalidArgumentException('Subject cannot be empty');
        }
        if (!in_array($activityType, ['Task', 'Events', 'Call', 'Meeting'], true)) {
            throw new InvalidArgumentException("Invalid activity type: {$activityType}");
        }
    }
}
