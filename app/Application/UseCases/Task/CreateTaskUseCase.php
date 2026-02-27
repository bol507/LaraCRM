<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\ValueObjects\TaskStatus;
use App\Application\ValueObjects\TaskPriority;
use InvalidArgumentException;

class CreateTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Create a new task
     * 
     * @param CreateTaskRequest $request Task creation data
     * @return int Created task ID
     * @throws InvalidArgumentException If validation fails
     */
    public function execute(CreateTaskRequest $request): int
    {
        // Validate subject
        if (empty(trim($request->subject))) {
            throw new InvalidArgumentException('Task subject is required');
        }

        if (strlen($request->subject) > 255) {
            throw new InvalidArgumentException('Task subject cannot exceed 255 characters');
        }

        // Validate date_start
        if (empty($request->dateStart)) {
            throw new InvalidArgumentException('Task start date is required');
        }

        // Validate due_date if provided
        if ($request->dueDate && $request->dueDate < $request->dateStart) {
            throw new InvalidArgumentException('Due date cannot be before start date');
        }

        // Validate time if provided
        if ($request->timeStart && $request->timeEnd) {
            if ($request->timeEnd <= $request->timeStart) {
                throw new InvalidArgumentException('End time must be after start time');
            }
        }

        // Validate status
        if ($request->status && !TaskStatus::isValid($request->status)) {
            throw new InvalidArgumentException(
                "Invalid status: {$request->status}. Valid statuses are: " . 
                implode(', ', TaskStatus::all())
            );
        }

        // Validate priority
        if ($request->priority && !TaskPriority::isValid($request->priority)) {
            throw new InvalidArgumentException(
                "Invalid priority: {$request->priority}. Valid priorities are: " . 
                implode(', ', TaskPriority::all())
            );
        }

        // Validate location length
        if ($request->location && strlen($request->location) > 150) {
            throw new InvalidArgumentException('Location cannot exceed 150 characters');
        }

        // Create task
        return $this->taskRepository->create($request);
    }
}