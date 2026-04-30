<?php

namespace App\Application\DTOs\Calendar;

use App\Domain\Entities\Activity;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Activity Data Transfer Object
 * 
 * Transforms Activity domain entities or raw DB rows into API-ready structures.
 * Maintains backward compatibility with frontend field names (title, startDate, etc.)
 * 
 * @package App\Application\DTOs\Calendar
 */
class ActivityDto
{
    /**
     * @param int $id activityid
     * @param string $title subject (aliased for frontend compatibility)
     * @param string|null $description Activity description/notes
     * @param string $activitytype activitytype: Task, Events, Call, Meeting
     * @param string $status Current status
     * @param string|null $priority Low, Medium, High
     * @param string $startDate date_start (Y-m-d format)
     * @param string|null $dueDate due_date (Y-m-d format)
     * @param string|null $startTime time_start (HH:MM)
     * @param string|null $dueTime time_end (HH:MM)
     * @param string|null $location Physical/virtual location
     * @param string $visibility 'all' or 'Owner'
     * @param int $assignedUserId smownerid from vtiger_crmentity
     * @param string|null $assignedUserName user_name from vtiger_users JOIN
     * @param string|null $assignedUserEmail email from vtiger_users JOIN
     * @param bool $completed Calculated: status in completed states
     * @param bool $isOverdue Calculated: due_date < today AND not completed
     * @param bool $isHighPriority Calculated: priority === 'High'
     * @param int|null $relatedRecordId crmid from vtiger_seactivityrel
     * @param string|null $relatedModuleType semodule from vtiger_activity
     * @param string $createdAt createdtime from vtiger_crmentity (Y-m-d H:i:s)
     * @param string $updatedAt modifiedtime from vtiger_crmentity (Y-m-d H:i:s)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,              // subject → title (frontend compatibility)
        public readonly string $description,        //crmentity.description
        public readonly string $activityType,
        public readonly string $status,
        public readonly ?string $priority,
        public readonly string $startDate,          // dateStart → startDate
        public readonly ?string $dueDate,           // dateDue → dueDate
        public readonly ?string $startTime,
        public readonly ?string $dueTime,
        public readonly string $sendNotification,
        public readonly ?string $location,
        public readonly string $visibility,
        public readonly int $assignedUserId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $assignedUserName,
        public readonly ?string $assignedUserEmail, // New
        public readonly ?bool $completed,            // isCompleted → completed
        public readonly ?bool $isOverdue,
        public readonly ?bool $isHighPriority,       // New
        public readonly ?int $relatedRecordId,      // New
        public readonly ?string $relatedModuleType, // New
        public readonly ?string $durationHours,      // New
        public readonly ?string $durationMinutes,    // New

    ) {}

    /**
     * Factory from database row (stdClass|array → DTO)
     * 
     * Handles field mapping from Vtiger tables + calculated flags.
     */
    public static function fromArray(array|stdClass $data): self
    {
        $arr = is_object($data) ? (array) $data : $data;

        // Date helper
        $parseDate = fn(?string $val): ?string =>
        $val && $val !== '0000-00-00' ? date('Y-m-d', strtotime($val)) : null;

        $parseDateTime = fn(?string $val): string =>
        $val && $val !== '0000-00-00 00:00:00' ? date('Y-m-d H:i:s', strtotime($val)) : '';

        // Calculate derived flags
        $status = $arr['status'] ?? 'Not Started';
        $completedStatuses = ['Completed', 'Closed', 'Held', 'Deferred'];
        $isCompleted = in_array($status, $completedStatuses, true);

        $dueDateRaw = $arr['due_date'] ?? $arr['date_start'] ?? null;
        $isOverdue = $dueDateRaw && !$isCompleted && strtotime($dueDateRaw) < time();

        $isHighPriority = ($arr['priority'] ?? '') === 'High';
        
        return new self(
            id: (int) ($arr['activityid'] ?? 0),
            // subject → title for frontend compatibility
            title: (string) ($arr['subject'] ?? ''),
            description: $arr['description'] ?? '',
            activityType: (string) ($arr['activitytype'] ?? 'Task'),
            status: $status,
            priority: $arr['priority'] ?? null,
            // date_start → startDate (string Y-m-d)
            startDate: $parseDate($arr['date_start']) ?? '',
            // due_date → dueDate (string Y-m-d or null)
            dueDate: $parseDate($arr['due_date']),
            startTime: $arr['time_start'] ?? null,
            dueTime: $arr['time_end'] ?? null,
            sendNotification: $arr['sendnotification'] ?? '0',
            location: $arr['location'] ?? null,
            visibility: (string) ($arr['visibility'] ?? 'all'),
            assignedUserId: (int) ($arr['smownerid'] ?? 0),
            assignedUserName: $arr['assigned_user_name'] ?? null,
            assignedUserEmail: $arr['assigned_user_email'] ?? null,
            completed: $isCompleted,
            isOverdue: (bool) $isOverdue,
            isHighPriority: $isHighPriority,
            // Relationship fields from vtiger_seactivityrel
            relatedRecordId: isset($arr['related_record_id']) ? (int) $arr['related_record_id'] : null,
            relatedModuleType: $arr['related_module_type'] ?? null,
            createdAt: $parseDateTime($arr['createdtime']),
            updatedAt: $parseDateTime($arr['modifiedtime']),
            durationHours: $arr['duration_hours'] ?? null,
            durationMinutes: $arr['duration_minutes'] ?? null,
        );
    }

    

    /**
     * Convert DTO to array for JSON API response
     * 
     * Maintains field names compatible with existing frontend (title, startDate, completed, etc.)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'startDate' => $this->startDate,
            'dueDate' => $this->dueDate,
            'startTime' => $this->startTime,
            'dueTime' => $this->dueTime,
            'sendNotification' => $this->sendNotification,
            'location' => $this->location,
            'visibility' => $this->visibility,
            'assignedUserId' => $this->assignedUserId,
            'assignedUserName' => $this->assignedUserName,
            'assignedUserEmail' => $this->assignedUserEmail,
            'completed' => $this->completed,
            'isOverdue' => $this->isOverdue,
            'isHighPriority' => $this->isHighPriority,
            'relatedRecordId' => $this->relatedRecordId,
            'relatedModuleType' => $this->relatedModuleType,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'durationHours' => $this->durationHours,    
            'durationMinutes' => $this->durationMinutes,
        ];
    }
}
