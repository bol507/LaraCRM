<?php

namespace App\Application\DTOs\Task;

use App\Domain\Entities\Task;

/**
 * Task Data Transfer Object
 * 
 * Used to transfer task data between layers and for API responses.
 * Transforms domain Task entities into API-ready data structures.
 * 
 * @package App\Application\DTOs\Task
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Task
 * @see \App\Application\UseCases\Task\GetTasksUseCase
 */
class TaskDto
{
    /**
     * Create a new TaskDto instance
     * 
     * @param int $id Task unique identifier
     * @param string $title Task title/subject
     * @param string|null $description Task detailed description
     * @param string $priority Task priority (Low, Medium, High)
     * @param string $status Task current status
     * @param string $dueDate Task due date (Y-m-d format)
     * @param string|null $dueTime Task end time (HH:MM format)
     * @param string $startDate Task start date (Y-m-d format)
     * @param string|null $startTime Task start time (HH:MM format)
     * @param string|null $location Task location
     * @param int|null $relatedRecordId Related CRM entity ID
     * @param string|null $relatedModuleType Related CRM module type
     * @param int $assignedUserId ID of assigned user
     * @param string|null $assignedUserName Name of assigned user
     * @param string|null $assignedUserEmail Email of assigned user
     * @param bool $completed Whether task is completed
     * @param bool $isOverdue Whether task is overdue
     * @param bool $isHighPriority Whether task is high priority
     * @param string $createdAt Creation timestamp (Y-m-d H:i:s format)
     * @param string $updatedAt Last update timestamp (Y-m-d H:i:s format)
     */
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
     * Create DTO from domain Task entity
     * 
     * Transforms a Task domain entity into a DTO for API responses.
     * Uses entity getters for proper encapsulation.
     * 
     * @param Task $task Domain entity to transform
     * @return self New TaskDto instance
     * 
     * @example
     * $dto = TaskDto::fromEntity($task);
     * $array = $dto->toArray();
     */
    public static function fromEntity(Task $task): self
    {
        return new self(
            id: $task->getId(),                                          
            title: $task->getSubject(),                                  
            description: $task->getDescription(),                        
            priority: $task->getPriority() ?? 'Medium',                  
            status: $task->getStatus() ?? 'Not Started',                 
            dueDate: $task->getDueDate()?->format('Y-m-d') 
                     ?? $task->getDateStart()->format('Y-m-d'),          
            dueTime: $task->getTimeEnd(),                                
            startDate: $task->getDateStart()->format('Y-m-d'),           
            startTime: $task->getTimeStart(),                            
            location: $task->getLocation(),                              
            relatedRecordId: $task->getRelatedRecordId(),                
            relatedModuleType: $task->getRelatedModuleType(),            
            assignedUserId: $task->getAssignedUserId(),                  
            assignedUserName: $task->getAssignedUserName(),              
            assignedUserEmail: $task->getAssignedUserEmail(),             
            completed: $task->isCompleted(),                              
            isOverdue: $task->isOverdue(),                                
            isHighPriority: $task->isHighPriority(),                      
            createdAt: $task->getCreatedAt()->format('Y-m-d H:i:s'),     
            updatedAt: $task->getUpdatedAt()->format('Y-m-d H:i:s'),     
        );
    }

    /**
     * Convert DTO to array for JSON serialization
     * 
     * @return array<string, mixed> Associative array representation
     * 
     * @example
     * // In controller:
     * return response()->json($dto->toArray());
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