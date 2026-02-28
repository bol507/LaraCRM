<?php

namespace App\Application\Repositories;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\Task\UpdateTaskRequest;
use App\Application\DTOs\Task\UpdateTaskStatusRequest;
use App\Domain\Entities\Task;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Task Repository Interface
 * 
 * Defines the contract for task persistence operations in Vtiger CRM.
 * 
 * This interface abstracts the data access layer for tasks, allowing
 * different implementations (Vtiger, MySQL, PostgreSQL, etc.) while
 * maintaining a consistent API for the application layer.
 * 
 * Key responsibilities:
 * - CRUD operations for tasks (Create, Read, Update, Delete)
 * - Pagination support for task lists
 * - Entity mapping between database rows and domain entities
 * - Transaction management for data consistency
 * - Soft delete support for audit trail
 * - Statistics and aggregation queries
 * 
 * Implementations should:
 * - Return domain entities (Task) from read operations
 * - Accept DTOs (CreateTaskRequest, UpdateTaskRequest) for write operations
 * - Handle soft deletes via vtiger_crmentity.deleted flag
 * - Throw RuntimeException for persistence failures
 * - Respect user permissions and data visibility rules
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Task
 * @see \App\Application\DTOs\CreateTaskRequest
 * @see \App\Application\DTOs\UpdateTaskRequest
 * @see \App\Infrastructure\Repositories\VtigerTaskRepository
 * @see \App\Application\UseCases\Task\CreateTaskUseCase
 * @see \App\Application\UseCases\Task\UpdateTaskUseCase
 * @see \App\Application\UseCases\Task\DeleteTaskUseCase
 */
interface TaskRepositoryInterface
{
    /**
     * Get tasks for a specific user with pagination
     * 
     * Retrieves tasks assigned to or created by a specific user, with support
     * for filtering by status, priority, date range, and related records.
     * 
     * Business rules:
     * - Returns tasks where user is assignee OR creator
     * - Excludes soft-deleted tasks (vtiger_crmentity.deleted = 0)
     * - Only returns tasks with activitytype = 'Task'
     * - Includes related user information via join with vtiger_users
     * - Supports optional filters for status, priority, dates, and search
     * 
     * @param int $userId ID of the user whose tasks to retrieve
     * @param int $limit Maximum number of tasks to return (default: 50, max: 100)
     * @param array<string, mixed> $filters Optional filters for narrowing results
     *        - status: string|array|null - Filter by status (e.g., 'Completed', ['Not Started', 'In Progress'])
     *        - priority: string|array|null - Filter by priority (e.g., 'High', ['High', 'Medium'])
     *        - dateFrom: string|null - Filter tasks with due_date >= this date (YYYY-MM-DD)
     *        - dateTo: string|null - Filter tasks with due_date <= this date (YYYY-MM-DD)
     *        - search: string|null - Search in subject and description
     *        - relatedModule: string|null - Filter by related module type (e.g., 'Project')
     *        - relatedRecordId: int|null - Filter by related record ID
     * @param int $offset Offset for pagination (default: 0)
     * 
     * @return Task[] Array of Task entities (not paginated, use countByUserId for total)
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If userId is invalid (<= 0)
     * 
     * @example
     * // Get first 50 tasks for user #123
     * $tasks = $repository->findByUserId(123, limit: 50, offset: 0);
     * 
     * @example
     * // Get high priority incomplete tasks
     * $tasks = $repository->findByUserId(
     *     userId: 123,
     *     limit: 50,
     *     filters: [
     *         'status' => ['Not Started', 'In Progress'],
     *         'priority' => 'High',
     *     ]
     * );
     * 
     * @example
     * // Get overdue tasks
     * $tasks = $repository->findByUserId(
     *     userId: 123,
     *     limit: 50,
     *     filters: [
     *         'dateTo' => date('Y-m-d'), // Due date <= today
     *     ]
     * );
     */
    public function findByUserId(
        int $userId,
        int $limit = 50,
        array $filters = [],
        int $offset = 0
    ): array;

    /**
     * Count total tasks for a user
     * 
     * Returns the total number of tasks for a user, respecting the same
     * visibility rules as findByUserId(). Used for pagination metadata.
     * 
     * @param int $userId ID of the user whose tasks to count
     * @param array<string, mixed> $filters Optional filters (same as findByUserId)
     * 
     * @return int Total number of tasks matching criteria
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If userId is invalid (<= 0)
     * 
     * @example
     * // Get total task count for user
     * $total = $repository->countByUserId(123);
     * 
     * @example
     * // Get count of high priority tasks
     * $count = $repository->countByUserId(
     *     userId: 123,
     *     filters: ['priority' => 'High']
     * );
     */
    public function countByUserId(int $userId, array $filters = []): int;

    /**
     * Get tasks for dashboard widget (pending tasks only)
     * 
     * Retrieves a limited set of pending/in-progress tasks optimized for
     * dashboard widget display. Excludes completed tasks by default.
     * 
     * Business rules:
     * - Only returns tasks with status IN ('Not Started', 'In Progress', 'Pending Input')
     * - Ordered by due_date ASC (most urgent first)
     * - Limited to 5-10 tasks for widget performance
     * - Excludes soft-deleted tasks
     * 
     * @param int $userId ID of the user whose tasks to retrieve
     * @param int $limit Maximum number of tasks (default: 10)
     * 
     * @return Task[] Array of Task entities (pending only)
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If userId is invalid (<= 0)
     * 
     * @example
     * // Get 5 pending tasks for dashboard
     * $tasks = $repository->findDashboardTasks(123, limit: 5);
     */
    public function findDashboardTasks(int $userId, int $limit = 10): array;

    /**
     * Get ALL tasks for dashboard (pending + completed)
     * 
     * Retrieves all tasks (including completed) ordered by most recent first.
     * Used for full task list views where completed tasks should be visible.
     * 
     * Business rules:
     * - Returns both pending AND completed tasks
     * - Ordered by createdtime DESC (most recent first)
     * - Frontend handles visual distinction (strikethrough for completed)
     * - Excludes soft-deleted tasks
     * 
     * @param int $userId ID of the user whose tasks to retrieve
     * @param int $limit Maximum number of tasks (default: 10)
     * 
     * @return Task[] Array of Task entities (all statuses)
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If userId is invalid (<= 0)
     * 
     * @example
     * // Get 10 most recent tasks (all statuses)
     * $tasks = $repository->findAllDashboardTasks(123, limit: 10);
     */
    public function findAllDashboardTasks(int $userId, int $limit = 10): array;

    /**
     * Find a single task by its unique identifier
     * 
     * Retrieves a specific task with all related data including:
     * - Task details (subject, description, dates, times)
     * - Priority and status information
     * - Assigned user and creator information
     * - Related record references (project, quote, etc.)
     * - Duration and notification settings
     * 
     * @param int $taskId Unique identifier of the task (vtiger_activity.activityid)
     * 
     * @return Task|null Task entity if found and not deleted, null otherwise
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If taskId is invalid (<= 0)
     * 
     * @example
     * // Get task by ID
     * $task = $repository->findById(456);
     * if ($task) {
     *     echo $task->getSubject();
     * }
     * 
     * @example
     * // In a use case with 404 handling
     * $task = $this->repository->findById($taskId);
     * if (!$task) {
     *     throw new NotFoundException('Task not found');
     * }
     */
    public function findById(int $taskId): ?Task;

    /**
     * Find a task by ID, including soft-deleted ones
     * 
     * Similar to findById() but bypasses the deleted = 0 filter.
     * Used for restore operations, admin audits, and soft-delete recovery.
     * 
     * @param int $taskId Unique identifier of the task
     * 
     * @return Task|null Task entity if found (even if deleted), null otherwise
     * 
     * @throws \RuntimeException If database query fails
     * 
     * @internal Only for admin/restore operations
     */
    public function findByIdIncludingDeleted(int $taskId): ?Task;

    /**
     * Create a new task
     * 
     * Persists a new task to the database with full transactional support.
     * This method handles:
     * - Insert into vtiger_activity (task data)
     * - Insert into vtiger_crmentity (metadata for permissions, soft delete, etc.)
     * - Optional: Insert into vtiger_seactivityrel (if related to another record)
     * - Auto-generation of task ID (max + 1, Vtiger legacy pattern)
     * - Timestamp management (createdtime, modifiedtime)
     * 
     * Prerequisites (validated by UseCase before calling):
     * - Subject is not empty and within length limits
     * - Dates are valid and logically consistent
     * - User has permission to create tasks
     * - Related record exists (if provided)
     * 
     * @param CreateTaskRequest $request Validated data transfer object with task creation data
     * 
     * @return int The unique identifier (activityid) of the newly created task
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If request data is invalid
     * @throws \DomainException If business rules are violated (e.g., duplicate detection)
     * 
     * @example
     * // Create a new task
     * $request = new CreateTaskRequest(
     *     subject: "Call client about proposal",
     *     activityType: "Task",
     *     dateStart: "2026-02-27",
     *     dueDate: "2026-03-01",
     *     priority: "High",
     *     status: "Not Started",
     *     assignedUserId: 123,
     * );
     * $taskId = $repository->create($request);
     */
    public function create(CreateTaskRequest $request): int;

    /**
     * Update an existing task (full or partial update)
     * 
     * Updates task fields with audit trail support. Only fields provided
     * in the update data array are modified (partial update support).
     * 
     * Updatable fields:
     * - subject: Task title/summary
     * - date_start, due_date: Task dates
     * - time_start, time_end: Task times
     * - status: Current status
     * - priority: Priority level
     * - location: Physical/virtual location
     * - description: Detailed description
     * - related_record_id, related_module_type: Related record references
     * - send_notification: Notification preference
     * 
     * Non-updatable fields (immutable after creation):
     * - activityid, activitytype, assignedUserId, createdByUserId, createdtime
     * 
     * @param int $taskId Unique identifier of the task to update
     * @param array<string, mixed> $data Associative array with fields to update
     *        Supported keys: 'subject', 'date_start', 'due_date', 'time_start',
     *        'time_end', 'status', 'priority', 'location', 'description',
     *        'related_record_id', 'related_module_type', 'send_notification',
     *        'modified_by', 'modified_time'
     * 
     * @return bool True if update was successful, false if task not found or no changes made
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If taskId is invalid or data contains invalid keys
     * @throws \DomainException If user is not authorized to update this task
     * 
     * @example
     * // Update task subject and description
     * $success = $repository->update(456, [
     *     'subject' => 'Updated task title',
     *     'description' => 'Updated description',
     *     'modified_by' => 123,
     *     'modified_time' => '2026-02-27 14:30:00',
     * ]);
     * 
     * @example
     * // Update task status to completed
     * $success = $repository->update(456, [
     *     'status' => 'Completed',
     *     'modified_by' => 123,
     *     'modified_time' => now()->format('Y-m-d H:i:s'),
     * ]);
     */
    public function update(int $taskId, array $data): bool;

    /**
     * Update task status (shortcut method)
     * 
     * Specialized method for updating only the task status field.
     * More efficient than full update when only status changes.
     * 
     * @param int $taskId Unique identifier of the task
     * @param UpdateTaskStatusRequest $request Request with new status value
     * @param int $modifiedBy ID of user performing the update (for audit)
     * 
     * @return bool True if update was successful, false otherwise
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If taskId or status is invalid
     * 
     * @example
     * // Mark task as completed
     * $request = new UpdateTaskStatusRequest(status: 'Completed');
     * $success = $repository->updateStatus(456, $request, modifiedBy: 123);
     */
    public function updateStatus(int $taskId, UpdateTaskStatusRequest $request, int $modifiedBy): bool;

    /**
     * Delete a task (soft delete)
     * 
     * Marks a task as deleted in vtiger_crmentity.deleted flag.
     * Soft delete is used to maintain data integrity and audit trail.
     * 
     * Business rules:
     * - Only marks deleted = 1, does not remove from database
     * - Deleted tasks are excluded from all query results
     * - Can be restored via restore() method if needed
     * - Deletion is permanent after X days (optional cleanup job)
     * 
     * @param int $taskId Unique identifier of the task to delete
     * 
     * @return bool True if deletion was successful, false if task not found or already deleted
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If taskId is invalid (<= 0)
     * @throws \DomainException If user is not authorized to delete this task
     * 
     * @example
     * // Soft delete a task
     * $success = $repository->delete(456);
     * if ($success) {
     *     // Task is now hidden from all queries
     * }
     */
    public function delete(int $taskId): bool;

    /**
     * Restore a soft-deleted task
     * 
     * Reverses a soft delete operation, making the task visible again.
     * Only the original assignee, creator, or an admin can restore tasks.
     * 
     * @param int $taskId Unique identifier of the task to restore
     * 
     * @return bool True if restore was successful, false if task not found or not deleted
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If taskId is invalid (<= 0)
     * 
     * @example
     * // Restore accidentally deleted task
     * $success = $repository->restore(456);
     * if ($success) {
     *     // Task is now visible again
     * }
     */
    public function restore(int $taskId): bool;

    /**
     * Get task statistics for a user
     * 
     * Returns aggregated statistics about a user's tasks for dashboard widgets
     * and reporting. Includes counts by status, priority, and overdue state.
     * 
     * @param int $userId ID of the user whose statistics to retrieve
     * 
     * @return array{
     *     total: int,           // Total number of tasks
     *     completed: int,       // Tasks with status = 'Completed'
     *     pending: int,         // Tasks not completed
     *     overdue: int,         // Pending tasks with due_date < today
     *     highPriority: int     // Pending tasks with priority = 'High'
     * }
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If userId is invalid (<= 0)
     * 
     * @example
     * // Get statistics for dashboard
     * $stats = $repository->getStatistics(123);
     * // Returns: ['total' => 50, 'completed' => 30, 'pending' => 20, 'overdue' => 5, 'highPriority' => 8]
     */
    public function getStatistics(int $userId): array;

    /**
     * Get tasks grouped by status
     * 
     * Returns count of tasks for each status value. Useful for pipeline
     * visualization and workload distribution reports.
     * 
     * @param int $userId ID of the user whose tasks to group
     * 
     * @return array<string, int> Associative array with status as key and count as value
     *        Example: ['Not Started' => 10, 'In Progress' => 5, 'Completed' => 30, ...]
     * 
     * @throws \RuntimeException If database query fails
     * 
     * @example
     * // Get task distribution by status
     * $byStatus = $repository->getCountByStatus(123);
     * // Returns: ['Not Started' => 10, 'In Progress' => 5, 'Completed' => 30, ...]
     */
    public function getCountByStatus(int $userId): array;

    /**
     * Get tasks grouped by priority
     * 
     * Returns count of tasks for each priority level. Useful for workload
     * prioritization and resource allocation reports.
     * 
     * @param int $userId ID of the user whose tasks to group
     * 
     * @return array<string, int> Associative array with priority as key and count as value
     *        Example: ['High' => 8, 'Medium' => 25, 'Low' => 17]
     * 
     * @throws \RuntimeException If database query fails
     * 
     * @example
     * // Get task distribution by priority
     * $byPriority = $repository->getCountByPriority(123);
     * // Returns: ['High' => 8, 'Medium' => 25, 'Low' => 17]
     */
    public function getCountByPriority(int $userId): array;

    /**
     * Permanently delete soft-deleted tasks older than specified days
     * 
     * Used by scheduled jobs to clean up old deleted tasks and free storage.
     * This operation is irreversible - use with caution.
     * 
     * @param int $olderThanDays Only delete tasks soft-deleted more than this many days ago
     * 
     * @return int Number of tasks permanently deleted
     * 
     * @throws \RuntimeException If database operation fails
     * @throws \InvalidArgumentException If olderThanDays is invalid (<= 0)
     * 
     * @example
     * // In a scheduled job (e.g., daily cleanup)
     * $deletedCount = $repository->deletePermanently(olderThanDays: 90);
     * Log::info("Permanently deleted {$deletedCount} old tasks");
     */
    public function deletePermanently(int $olderThanDays): int;
}