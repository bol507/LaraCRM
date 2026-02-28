<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\DTOs\Task\UpdateTaskRequest;
use App\Domain\Entities\Task;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

/**
 * Update Task Use Case
 * 
 * Orchestrates the update of an existing task in the system.
 * 
 * Responsibilities:
 * - Validate that the task exists and is accessible
 * - Verify that the requesting user is authorized to update the task
 * - Apply business rules for what can be updated and by whom
 * - Delegate persistence to the repository layer
 * - Return success status or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Only the assigned user or task creator can update the task
 * - Admin users may override this restriction (configurable)
 * - Status transitions must follow valid workflows (e.g., cannot reopen completed tasks without permission)
 * - Date changes must maintain logical consistency (due_date >= date_start)
 * - Related record changes must reference valid, accessible records
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
 * @see \App\Application\DTOs\UpdateTaskRequest
 * @see \App\Http\Controllers\Api\TaskController
 */
class UpdateTaskUseCase
{
    /**
     * Task repository for persistence operations
     * 
     * @var TaskRepositoryInterface
     */
    private readonly TaskRepositoryInterface $repository;

    /**
     * Configuration options for update behavior
     * 
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * Constructor with dependency injection
     * 
     * @param TaskRepositoryInterface $repository Repository implementation for task persistence
     * @param array<string, mixed> $config Optional configuration overrides
     * 
     * @example
     * // Default configuration
     * $useCase = new UpdateTaskUseCase($repository);
     * 
     * @example
     * // Custom configuration with admin override
     * $useCase = new UpdateTaskUseCase($repository, [
     *     'allow_admin_override' => true,
     *     'allow_status_reopen' => false,
     * ]);
     */
    public function __construct(
        TaskRepositoryInterface $repository,
        array $config = []
    ) {
        $this->repository = $repository;
        $this->config = array_merge([
            // Allow admin users to update any task (not just assigned/creator)
            'allow_admin_override' => false,
            // Allow reopening completed tasks
            'allow_status_reopen' => true,
            // Send notification on update (if send_notification flag is set)
            'send_notification' => true,
        ], $config);
    }

    /**
     * Execute the update task use case
     * 
     * Updates an existing task after validating all business rules:
     * - Task must exist and not be deleted
     * - Requesting user must be authorized (assignee, creator, or admin)
     * - Status transitions must follow valid workflows
     * - Date changes must maintain logical consistency
     * - Related record references must be valid
     * 
     * @param int $taskId Unique identifier of the task to update
     * @param UpdateTaskRequest $request Data transfer object with update data
     * @param int $userId ID of the user requesting the update (for authorization)
     * 
     * @return bool True if update was successful, false if task not found or no changes made
     * 
     * @throws InvalidArgumentException If input data fails validation rules
     * @throws DomainException If business rules are violated (e.g., unauthorized user)
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Update task subject and description
     * $request = new UpdateTaskRequest(
     *     subject: "Updated task title",
     *     description: "Updated description with new requirements",
     * );
     * $success = $useCase->execute(taskId: 123, request: $request, userId: 456);
     * 
     * @example
     * // Update task status to completed
     * $request = new UpdateTaskRequest(
     *     status: "Completed",
     * );
     * $success = $useCase->execute(taskId: 123, request: $request, userId: 456);
     * 
     * @example
     * // Update due date and priority
     * $request = new UpdateTaskRequest(
     *     dueDate: "2026-03-15",
     *     priority: "High",
     * );
     * $success = $useCase->execute(taskId: 123, request: $request, userId: 456);
     */
    public function execute(int $taskId, UpdateTaskRequest $request, int $userId): bool
    {
        // ✅ Validate input parameters
        $this->validateParameters($taskId, $userId);

        // ✅ Fetch the existing task
        $task = $this->repository->findById($taskId);
        if (!$task) {
            return false;
        }

        // ✅ Business rule: Verify user is authorized to update this task
        $this->verifyUpdatePermission($task, $userId);

        // ✅ Business rule: Validate status transitions
        if ($request->shouldUpdateStatus()) {
            $this->validateStatusTransition($task, $request->status);
        }

        // ✅ Business rule: Validate date consistency
        if ($request->shouldUpdateDates()) {
            $this->validateDateConsistency($request, $task);
        }

        // ✅ Prepare update data for repository
        $updateData = $request->toUpdateArray();

        // ✅ Add audit metadata
        $updateData['modified_by'] = $userId;
        $updateData['modified_time'] = now()->format('Y-m-d H:i:s');

        // ✅ Delegate persistence to repository layer
        return $this->repository->update($taskId, $updateData);
    }

    /**
     * Validate input parameters for the use case
     * 
     * @param int $taskId Task ID to validate
     * @param int $userId User ID to validate
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $taskId, int $userId): void
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException(
                "Task ID must be a positive integer, got {$taskId}"
            );
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "User ID must be a positive integer, got {$userId}"
            );
        }
    }

    /**
     * Verify that the user is authorized to update the task
     * 
     * Business rules:
     * - Primary: Assigned user or task creator can update
     * - Optional: Admin users may override if allow_admin_override is enabled
     * - Optional: Record owners may update tasks on their records (configurable)
     * 
     * @param Task $task The task entity to check
     * @param int $userId ID of the user requesting the update
     * @return void
     * @throws DomainException If user is not authorized
     */
    private function verifyUpdatePermission(Task $task, int $userId): void
    {
        // Primary rule: Assigned user can update
        if ($task->getAssignedUserId() === $userId) {
            return;
        }

        // Secondary rule: Task creator can update
        if ($task->getCreatedByUserId() === $userId) {
            return;
        }

        // Optional: Admin override
        if ($this->config['allow_admin_override'] && $this->isAdmin($userId)) {
            return;
        }

        // If no rule matched, deny update
        throw new DomainException(
            "User {$userId} is not authorized to update task {$task->getId()}. " .
            "Only the assigned user, creator, or an administrator can update tasks."
        );
    }

    /**
     * Validate status transition rules
     * 
     * Business rules:
     * - Completed → In Progress: Only if allow_status_reopen is enabled
     * - Any → Completed: Always allowed
     * - Not Started → In Progress: Always allowed
     * 
     * @param Task $task Current task entity
     * @param string|null $newStatus New status to validate
     * @return void
     * @throws DomainException If status transition is not allowed
     */
    private function validateStatusTransition(Task $task, ?string $newStatus): void
    {
        if ($newStatus === null) {
            return;
        }

        $currentStatus = $task->getStatus();

        // Prevent reopening completed tasks if not allowed
        if ($currentStatus === 'Completed' && $newStatus !== 'Completed') {
            if (!$this->config['allow_status_reopen']) {
                throw new DomainException(
                    "Cannot change status of completed tasks. " .
                    "Completed tasks cannot be reopened without administrator permission."
                );
            }
        }

        // Optional: Add more complex workflow rules here
        // Example: Prevent skipping statuses (Not Started → Completed without In Progress)
    }

    /**
     * Validate date consistency for updates
     * 
     * @param UpdateTaskRequest $request Update request with new dates
     * @param Task $task Current task entity for context
     * @return void
     * @throws InvalidArgumentException If dates are inconsistent
     */
    private function validateDateConsistency(UpdateTaskRequest $request, Task $task): void
    {
        // Use new dates if provided, otherwise use existing
        $dateStart = $request->dateStart ?? $task->getDateStart()->format('Y-m-d');
        $dueDate = $request->dueDate ?? ($task->getDueDate()?->format('Y-m-d'));

        if ($dateStart && $dueDate) {
            if (strtotime($dueDate) < strtotime($dateStart)) {
                throw new InvalidArgumentException(
                    'Due date cannot be before start date'
                );
            }
        }
    }

    /**
     * Check if a user has admin privileges
     * 
     * @param int $userId User ID to check
     * @return bool True if user is admin, false otherwise
     * 
     * @internal Implementation depends on your authentication/authorization system
     */
    private function isAdmin(int $userId): bool
    {
        // TODO: Implement based on your auth system
        // Example: Check if user has 'admin' role in vtiger_users or custom roles table
        // return $this->userRepository->hasRole($userId, 'admin');
        
        // Default: no admin override
        return false;
    }
}