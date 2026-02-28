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
 * 
 * @package App\Application\DTOs
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Task\UpdateTaskUseCase
 * @see \App\Domain\Entities\Task
 */
class UpdateTaskRequest
{
    /**
     * New task subject/title (optional)
     * 
     * Maximum: 255 characters
     * 
     * @var string|null
     */
    public readonly ?string $subject;

    /**
     * New start date (optional)
     * 
     * Format: YYYY-MM-DD
     * 
     * @var string|null
     */
    public readonly ?string $dateStart;

    /**
     * New due date (optional)
     * 
     * Format: YYYY-MM-DD
     * Must be >= date_start if both are provided
     * 
     * @var string|null
     */
    public readonly ?string $dueDate;

    /**
     * New start time (optional)
     * 
     * Format: HH:MM (24-hour format)
     * 
     * @var string|null
     */
    public readonly ?string $timeStart;

    /**
     * New end time (optional)
     * 
     * Format: HH:MM (24-hour format)
     * Must be >= time_start if both are provided
     * 
     * @var string|null
     */
    public readonly ?string $timeEnd;

    /**
     * New priority level (optional)
     * 
     * Values: Low, Medium, High
     * 
     * @var string|null
     */
    public readonly ?string $priority;

    /**
     * New status (optional)
     * 
     * Values: Not Started, In Progress, Completed, Pending Input, Planned
     * 
     * @var string|null
     */
    public readonly ?string $status;

    /**
     * New location (optional)
     * 
     * Maximum: 150 characters
     * 
     * @var string|null
     */
    public readonly ?string $location;

    /**
     * New description (optional)
     * 
     * No length limit (TEXT field)
     * 
     * @var string|null
     */
    public readonly ?string $description;

    /**
     * New related record ID (optional)
     * 
     * References another CRM entity (project, quote, account, etc.)
     * 
     * @var int|null
     */
    public readonly ?int $relatedRecordId;

    /**
     * New related module type (optional)
     * 
     * Examples: Project, Quotes, Accounts, Potentials
     * Maximum: 50 characters
     * 
     * @var string|null
     */
    public readonly ?string $relatedModuleType;

    /**
     * Send notification to assignee (optional)
     * 
     * @var bool|null
     */
    public readonly ?bool $sendNotification;

    /**
     * Constructor for UpdateTaskRequest
     * 
     * @param string|null $subject New task subject (optional)
     * @param string|null $dateStart New start date (optional)
     * @param string|null $dueDate New due date (optional)
     * @param string|null $timeStart New start time (optional)
     * @param string|null $timeEnd New end time (optional)
     * @param string|null $priority New priority level (optional)
     * @param string|null $status New status (optional)
     * @param string|null $location New location (optional)
     * @param string|null $description New description (optional)
     * @param int|null $relatedRecordId New related record ID (optional)
     * @param string|null $relatedModuleType New related module type (optional)
     * @param bool|null $sendNotification Send notification flag (optional)
     * 
     * @throws \InvalidArgumentException If no fields are provided or validation fails
     */
    public function __construct(
        ?string $subject = null,
        ?string $dateStart = null,
        ?string $dueDate = null,
        ?string $timeStart = null,
        ?string $timeEnd = null,
        ?string $priority = null,
        ?string $status = null,
        ?string $location = null,
        ?string $description = null,
        ?int $relatedRecordId = null,
        ?string $relatedModuleType = null,
        ?bool $sendNotification = null,
    ) {
        // Validate that at least one field is provided for update
        if (
            $subject === null && $dateStart === null && $dueDate === null &&
            $timeStart === null && $timeEnd === null && $priority === null &&
            $status === null && $location === null && $description === null &&
            $relatedRecordId === null && $relatedModuleType === null &&
            $sendNotification === null
        ) {
            throw new \InvalidArgumentException('At least one field must be provided for task update');
        }

        // Validate subject length if provided
        if ($subject !== null && strlen(trim($subject)) > 255) {
            throw new \InvalidArgumentException('Task subject cannot exceed 255 characters');
        }

        // Validate date consistency if both provided
        if ($dateStart !== null && $dueDate !== null) {
            if (strtotime($dueDate) < strtotime($dateStart)) {
                throw new \InvalidArgumentException('Due date cannot be before start date');
            }
        }

        // Validate time consistency if both provided
        if ($timeStart !== null && $timeEnd !== null) {
            if ($timeEnd <= $timeStart) {
                throw new \InvalidArgumentException('End time must be after start time');
            }
        }

        // Validate priority if provided
        if ($priority !== null && !in_array($priority, ['Low', 'Medium', 'High'], true)) {
            throw new \InvalidArgumentException('Priority must be one of: Low, Medium, High');
        }

        // Validate status if provided
        $validStatuses = ['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Planned'];
        if ($status !== null && !in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException(
                "Status must be one of: " . implode(', ', $validStatuses)
            );
        }

        // Validate location length if provided
        if ($location !== null && strlen(trim($location)) > 150) {
            throw new \InvalidArgumentException('Location cannot exceed 150 characters');
        }

        // Validate related module type length if provided
        if ($relatedModuleType !== null && strlen(trim($relatedModuleType)) > 50) {
            throw new \InvalidArgumentException('Related module type cannot exceed 50 characters');
        }

        // Assign validated values
        $this->subject = $subject !== null ? trim($subject) : null;
        $this->dateStart = $dateStart;
        $this->dueDate = $dueDate;
        $this->timeStart = $timeStart;
        $this->timeEnd = $timeEnd;
        $this->priority = $priority;
        $this->status = $status;
        $this->location = $location !== null ? trim($location) : null;
        $this->description = $description;
        $this->relatedRecordId = $relatedRecordId;
        $this->relatedModuleType = $relatedModuleType !== null ? trim($relatedModuleType) : null;
        $this->sendNotification = $sendNotification;
    }

    /**
     * Convert to array for repository layer
     * 
     * @return array<string, mixed> Associative array with fields to update
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
            $data['send_notification'] = $this->sendNotification;
        }
        
        return $data;
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
}