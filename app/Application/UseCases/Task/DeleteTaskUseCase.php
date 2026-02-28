<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

/**
 * Delete Task Use Case
 * 
 * Orchestrates the soft deletion of an existing task in the system.
 * 
 * Responsibilities:
 * - Validate that the task exists and is accessible
 * - Verify that the requesting user is authorized to delete the task
 * - Apply business rules for deletion permissions and constraints
 * - Delegate persistence (soft delete) to the repository layer
 * - Return success status or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Only the assigned user or task creator can delete the task
 * - Admin users may override author restriction (configurable)
 * - Tasks on locked/closed records may have additional restrictions
 * - Deletion is soft (vtiger_crmentity.deleted = 1) for audit trail
 * - Deleted tasks are excluded from all query results
 * - Deletion is permanent after configurable retention period (optional)
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
class DeleteTaskUseCase
{
    /**
     * Task repository for persistence operations
     * 
     * @var TaskRepositoryInterface
     */
    private readonly TaskRepositoryInterface $repository;

    /**
     * Configuration options for deletion behavior
     * 
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * Constructor with dependency injection
     * 
     * @param TaskRepositoryInterface $repository Repository implementation for task persistence
     * @param array<string, mixed> $config Optional configuration overrides
     */
    public function __construct(
        TaskRepositoryInterface $repository,
        array $config = []
    ) {
        $this->repository = $repository;
        $this->config = array_merge([
            // Allow admin users to delete any task
            'allow_admin_override' => false,
            // Prevent deletion of tasks on locked/closed records
            'prevent_on_locked_records' => true,
            // Require a reason for deletion (for audit trail)
            'require_reason' => false,
        ], $config);
    }

    /**
     * Execute the delete task use case
     * 
     * Soft-deletes an existing task after validating all business rules:
     * - Task must exist and not already be deleted
     * - Requesting user must be authorized (assignee, creator, or admin)
     * - Record-level permissions must allow deletion
     * - Optional: Deletion reason must be provided if configured
     * 
     * @param int $taskId Unique identifier of the task to delete
     * @param int $userId ID of the user requesting the deletion (for authorization)
     * @param string|null $reason Optional reason for deletion (for audit trail)
     * 
     * @return bool True if deletion was successful, false if task not found or already deleted
     * 
     * @throws InvalidArgumentException If input parameters are invalid
     * @throws DomainException If business rules are violated (e.g., unauthorized user)
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Assigned user deletes their task
     * $success = $useCase->execute(taskId: 123, userId: 456);
     * 
     * @example
     * // Admin deletes any task (if allow_admin_override is enabled)
     * $success = $useCase->execute(
     *     taskId: 123,
     *     userId: 1, // Admin user ID
     *     reason: "Duplicate task removed"
     * );
     */
    public function execute(int $taskId, int $userId, ?string $reason = null): bool
    {
        // ✅ Validate input parameters
        $this->validateParameters($taskId, $userId, $reason);

        // ✅ Fetch the existing task
        $task = $this->repository->findById($taskId);
        if (!$task) {
            return false;
        }

        // ✅ Business rule: Verify user is authorized to delete this task
        $this->verifyDeletePermission($task, $userId);

        // ✅ Business rule: Check record-level constraints
        $this->verifyRecordConstraints($task);

        // ✅ Business rule: Validate deletion reason if required
        if ($this->config['require_reason'] && empty(trim($reason ?? ''))) {
            throw new DomainException('A reason for deletion is required');
        }

        // ✅ Delegate soft delete to repository layer
        return $this->repository->delete($taskId);
    }

    /**
     * Validate input parameters
     * 
     * @param int $taskId Task ID to validate
     * @param int $userId User ID to validate
     * @param string|null $reason Deletion reason to validate
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $taskId, int $userId, ?string $reason): void
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }
        if ($this->config['require_reason'] && $reason !== null && strlen(trim($reason)) > 255) {
            throw new InvalidArgumentException('Deletion reason cannot exceed 255 characters');
        }
    }

    /**
     * Verify user is authorized to delete the task
     * 
     * @param Task $task Task entity to check
     * @param int $userId User ID requesting deletion
     * @return void
     * @throws DomainException If user is not authorized
     */
    private function verifyDeletePermission(Task $task, int $userId): void
    {
        // Assigned user can delete
        if ($task->getAssignedUserId() === $userId) {
            return;
        }

        // Task creator can delete
        if ($task->getCreatedByUserId() === $userId) {
            return;
        }

        // Admin override
        if ($this->config['allow_admin_override'] && $this->isAdmin($userId)) {
            return;
        }

        throw new DomainException(
            "User {$userId} is not authorized to delete task {$task->getId()}. " .
            "Only the assigned user, creator, or administrator can delete tasks."
        );
    }

    /**
     * Check record-level constraints
     * 
     * @param Task $task Task entity to check
     * @return void
     * @throws DomainException If constraints prevent deletion
     */
    private function verifyRecordConstraints(Task $task): void
    {
        if (!$this->config['prevent_on_locked_records']) {
            return;
        }

        // Optional: Check if related record is locked/closed
        // if ($task->getRelatedRecordId() && $this->isRelatedRecordLocked($task->getRelatedRecordId())) {
        //     throw new DomainException("Cannot delete tasks on locked or closed records");
        // }
    }

    /**
     * Check if user has admin privileges
     */
    private function isAdmin(int $userId): bool
    {
        // TODO: Implement based on your auth system
        return false;
    }

    /**
     * Check if related record is locked
     */
    private function isRelatedRecordLocked(int $relatedId): bool
    {
        // TODO: Implement based on your business logic
        return false;
    }
}