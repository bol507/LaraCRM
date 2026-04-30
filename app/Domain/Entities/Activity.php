<?php
namespace App\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Activity Entity
 * 
 * Represents a Calendar activity in Vtiger (Task, Event, Call, Meeting).
 * Encapsulates business logic and domain rules for activity records.
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 */
class Activity
{
    // ==================== CONSTANTS ====================
    public const TYPE_TASK = 'Task';
    public const TYPE_EVENT = 'Events';
    public const TYPE_CALL = 'Call';
    public const TYPE_MEETING = 'Meeting';

    public const STATUS_NOT_STARTED = 'Not Started';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_DEFERRED = 'Deferred';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_HELD = 'Held';
    public const STATUS_NOT_HELD = 'Not Held';

    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';

    /**
     * Constructor - ONLY real database properties or those derived from them
     * 
     * isCompleted, isOverdue, isHighPriority do NOT go here (they are methods)
     */
    public function __construct(
        private readonly int $id,
        private readonly string $subject,
        private readonly string $activityType,
        private readonly string $status,
        private readonly ?string $priority,
        private readonly DateTimeImmutable $dateStart,
        private readonly ?\DateTimeImmutable $dateDue,
        private readonly ?string $timeStart,
        private readonly ?string $timeEnd,
        private readonly string $sendNotification,
        private readonly ?string $location,
        private readonly string $visibility,
        private readonly int $assignedUserId,
        private readonly ?string $assignedUserName,
        private readonly ?string $assignedUserEmail,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
        private readonly ?int $relatedRecordId = null,
        private readonly ?string $relatedModuleType = null,
        private readonly ?string $durationHours = null,
        private readonly ?string $durationMinutes = null,
    ) {
        $this->validate();
    }

    // ==================== VALIDATION ====================
    private function validate(): void
    {
        if (trim($this->subject) === '') {
            throw new InvalidArgumentException('Activity subject cannot be empty');
        }

        $validTypes = [self::TYPE_TASK, self::TYPE_EVENT, self::TYPE_CALL, self::TYPE_MEETING];
        if (!in_array($this->activityType, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid activity type: {$this->activityType}");
        }

        if ($this->assignedUserId <= 0) {
            throw new InvalidArgumentException('Assigned user ID must be positive');
        }
    }

    // ==================== FACTORY ====================
    public static function fromArray(array|object $data): self
    {
        $arr = is_object($data) ? (array) $data : $data;

        // Helper for Vtiger dates
        $parseDate = fn(?string $val): ?\DateTimeImmutable =>
            $val && $val !== '0000-00-00' ? new \DateTimeImmutable($val) : null;

        // Calculate flags (internal use only, do NOT pass to constructor)
        $status = $arr['status'] ?? self::STATUS_NOT_STARTED;
        $priority = $arr['priority'] ?? null;
        $dateDueRaw = $arr['due_date'] ?? null;

        return new self(
            id: (int) ($arr['activityid'] ?? 0),
            subject: (string) ($arr['subject'] ?? ''),
            activityType: (string) ($arr['activitytype'] ?? self::TYPE_TASK),
            status: $status,
            priority: $priority,
            dateStart: $parseDate($arr['date_start']) ?? new \DateTimeImmutable(),
            dateDue: $parseDate($dateDueRaw),
            timeStart: $arr['time_start'] ?? null,
            timeEnd: $arr['time_end'] ?? null,
            sendNotification: $arr['sendnotification'] ?? '0',
            location: $arr['location'] ?? null,
            visibility: (string) ($arr['visibility'] ?? 'all'),
            assignedUserId: (int) ($arr['smownerid'] ?? 0),
            
            // These ARE real properties:
            assignedUserName: isset($arr['assigned_user_name']) 
                ? trim((string) $arr['assigned_user_name']) : null,
            assignedUserEmail: isset($arr['assigned_user_email']) 
                ? trim((string) $arr['assigned_user_email']) : null,
            createdAt: $parseDate($arr['createdtime']) ?? new \DateTimeImmutable(),
            updatedAt: $parseDate($arr['modifiedtime']) ?? new \DateTimeImmutable(),
            relatedRecordId: isset($arr['related_record_id']) ? (int) $arr['related_record_id'] : null,
            relatedModuleType: $arr['related_module_type'] ?? null,
            durationHours: $arr['duration_hours'] ?? null,
            durationMinutes: $arr['duration_minutes'] ?? null,
        );
    }

    // ==================== GETTERS ====================
    public function getId(): int { return $this->id; }
    public function getSubject(): string { return $this->subject; }
    public function getActivityType(): string { return $this->activityType; }
    public function getStatus(): string { return $this->status; }
    public function getPriority(): ?string { return $this->priority; }
    public function getDateStart(): \DateTimeImmutable { return $this->dateStart; }
    public function getDateDue(): ?\DateTimeImmutable { return $this->dateDue; }
    public function getTimeStart(): ?string { return $this->timeStart; }
    public function getTimeEnd(): ?string { return $this->timeEnd; }
    public function getSendNotification(): string { return $this->sendNotification; }
    public function getLocation(): ?string { return $this->location; }
    public function getVisibility(): string { return $this->visibility; }
    public function getAssignedUserId(): int { return $this->assignedUserId; }
    public function getAssignedUserName(): ?string { return $this->assignedUserName; }
    public function getAssignedUserEmail(): ?string { return $this->assignedUserEmail; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getRelatedRecordId(): ?int { return $this->relatedRecordId; }
    public function getRelatedModuleType(): ?string { return $this->relatedModuleType; }
    public function getDurationHours(): ?string { return $this->durationHours; }
    public function getDurationMinutes(): ?string { return $this->durationMinutes; }

    // ==================== BUSINESS LOGIC (Calculated methods) ====================
    
    /**
     * Check if activity is completed/closed
     * Calculated dynamically from $this->status
     */
    public function isCompleted(): bool
    {
        $completedStatuses = [
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED,
            self::STATUS_HELD,
            self::STATUS_DEFERRED,
        ];
        return in_array($this->status, $completedStatuses, true);
    }

    /**
     * Check if activity is overdue
     * Calculated dynamically from $this->dateDue and $this->isCompleted()
     */
    public function isOverdue(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }
        if (!$this->dateDue) {
            return false;
        }
        $today = new \DateTimeImmutable('midnight');
        return $this->dateDue < $today;
    }

    /**
     * Check if activity has high priority
     * Calculated dynamically from $this->priority
     */
    public function isHighPriority(): bool
    {
        return $this->priority === self::PRIORITY_HIGH;
    }

    /**
     * Check if activity is visible to current user based on visibility rule
     */
    public function isVisibleTo(int $currentUserId): bool
    {
        if ($this->visibility === 'all') {
            return true;
        }
        return $this->assignedUserId === $currentUserId;
    }

    /**
     * Get activity type label for UI
     */
    public function getTypeLabel(): string
    {
        return match ($this->activityType) {
            self::TYPE_TASK => 'Task',
            self::TYPE_EVENT => 'Event',
            self::TYPE_CALL => 'Call',
            self::TYPE_MEETING => 'Meeting',
            default => $this->activityType,
        };
    }

    // ==================== SERIALIZATION ====================
    
    
}