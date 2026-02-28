<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use InvalidArgumentException;
use RuntimeException;

/**
 * Get Task Use Case
 * 
 * Orchestrates the retrieval of a single task by its unique identifier.
 * 
 * Responsibilities:
 * - Validate task ID parameter
 * - Delegate data retrieval to the repository layer
 * - Handle not-found scenarios gracefully
 * - Return Task entity or null
 * 
 * This use case is part of the Application layer and should not contain:
 * - HTTP-specific logic (request/response handling)
 * - Database-specific queries (SQL, joins, etc.)
 * - UI-specific formatting (dates, localization, etc.)
 * 
 * @package App\Application\UseCases\Task
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\TaskRepositoryInterface
 * @see \App\Domain\Entities\Task
 * @see \App\Http\Controllers\Api\TaskController
 */
class GetTaskUseCase
{
    /**
     * Task repository for data access operations
     * 
     * @var TaskRepositoryInterface
     */
    private readonly TaskRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param TaskRepositoryInterface $repository Repository implementation for task persistence
     */
    public function __construct(TaskRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case: retrieve a single task by ID
     * 
     * Retrieves a specific task with all related data including:
     * - Task details (subject, description, dates, times)
     * - Priority and status information
     * - Assigned user information
     * - Related record references (project, quote, etc.)
     * - Attachment references (if any)
     * 
     * @param int $taskId Unique identifier of the task (vtiger_activity.activityid)
     * 
     * @return Task|null Task entity if found and not deleted, null otherwise
     * 
     * @throws InvalidArgumentException If taskId is invalid (<= 0)
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Get task by ID
     * $task = $useCase->execute(123);
     * if ($task) {
     *     echo $task->getSubject();
     * }
     * 
     * @example
     * // In a controller with 404 handling
     * $task = $this->getTaskUseCase->execute($taskId);
     * if (!$task) {
     *     return response()->json(['error' => 'Task not found'], 404);
     * }
     * return response()->json(['data' => TaskDto::fromEntity($task)->toArray()]);
     */
    public function execute(int $taskId): ?Task
    {
        //  Validate input parameter
        if ($taskId <= 0) {
            throw new InvalidArgumentException(
                "Task ID must be a positive integer, got {$taskId}"
            );
        }

        //  Delegate to repository layer for data retrieval
        try {
            return $this->repository->findById($taskId);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve task {$taskId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}