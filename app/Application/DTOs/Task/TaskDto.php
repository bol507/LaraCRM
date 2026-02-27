<?php

namespace App\Application\DTOs\Task;

use App\Domain\Entities\Task;

/**
 * Task Data Transfer Object
 * 
 * Used to transfer task data between layers and for API responses.
 */
class TaskDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $priority,
        public readonly string $status,
        public readonly string $dueDate,
        public readonly ?string $dueTime,
        public readonly string $startDate,
        public readonly ?string $startTime,
        public readonly ?string $location,
        public readonly ?int $relatedRecordId,
        public readonly ?string $relatedModuleType,
        public readonly int $assignedUserId,
        public readonly ?string $assignedUserName,
        public readonly ?string $assignedUserEmail,
        public readonly bool $completed,
        public readonly bool $isOverdue,
        public readonly bool $isHighPriority,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create DTO from Entity
     */
    public static function fromEntity(Task $task, ?string $assignedUserName = null, ?string $assignedUserEmail = null): self
    {
        return new self(
            id: $task->id,
            title: $task->subject,
            description: $task->description,
            priority: $task->priority ?: 'Medium',
            status: $task->status ?: 'Not Started',
            dueDate: $task->dueDate?->format('Y-m-d') ?? $task->dateStart->format('Y-m-d'),
            dueTime: $task->timeEnd,
            startDate: $task->dateStart->format('Y-m-d'),
            startTime: $task->timeStart,
            location: $task->location,
            relatedRecordId: $task->relatedRecordId,
            relatedModuleType: $task->relatedModuleType,
            assignedUserId: $task->assignedUserId,
            assignedUserName: $assignedUserName,
            assignedUserEmail: $assignedUserEmail,
            completed: $task->isCompleted(),
            isOverdue: $task->isOverdue(),
            isHighPriority: $task->isHighPriority(),
            createdAt: $task->createdAt->format('Y-m-d H:i:s'),
            updatedAt: $task->updatedAt->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'dueDate' => $this->dueDate,
            'dueTime' => $this->dueTime,
            'startDate' => $this->startDate,
            'startTime' => $this->startTime,
            'location' => $this->location,
            'relatedRecordId' => $this->relatedRecordId,
            'relatedModuleType' => $this->relatedModuleType,
            'assignedUserId' => $this->assignedUserId,
            'assignedUserName' => $this->assignedUserName,
            'assignedUserEmail' => $this->assignedUserEmail,
            'completed' => $this->completed,
            'isOverdue' => $this->isOverdue,
            'isHighPriority' => $this->isHighPriority,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}