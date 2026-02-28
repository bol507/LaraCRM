<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\Task\TaskDto;
use App\Application\DTOs\Task\UpdateTaskRequest;
use App\Application\UseCases\Task\CreateTaskUseCase;
use App\Application\UseCases\Task\DeleteTaskUseCase;
use App\Application\UseCases\Task\GetTaskUseCase;
use App\Application\UseCases\Task\GetTasksUseCase;
use App\Application\UseCases\Task\UpdateTaskUseCase;
use App\Application\UseCases\Task\UpdateTaskStatusUseCase;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Task API Controller
 * 
 * Handles HTTP requests for task operations in the CRM system.
 * 
 * Responsibilities:
 * - Parse and validate HTTP request data
 * - Delegate business logic to Application Use Cases
 * - Transform domain responses to JSON API format
 * - Handle exceptions and return appropriate HTTP status codes
 * - Enforce authentication and authorization at HTTP layer
 * 
 * This controller is part of the Presentation/HTTP layer and should not contain:
 * - Business rules or validation logic (delegated to Use Cases)
 * - Database queries or persistence logic (delegated to Repositories)
 * - UI-specific formatting beyond JSON serialization
 * 
 * @package App\Http\Controllers\Api
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Task\GetTasksUseCase
 * @see \App\Application\UseCases\Task\CreateTaskUseCase
 * @see \App\Application\UseCases\Task\UpdateTaskUseCase
 * @see \App\Application\UseCases\Task\DeleteTaskUseCase
 * @see \App\Application\DTOs\TaskDto
 * @see \App\Application\DTOs\CreateTaskRequest
 */
class TaskController extends Controller
{
    /**
     * Use case for listing tasks with pagination and filters
     * 
     * @var GetTasksUseCase
     */
    private readonly GetTasksUseCase $getTasksUseCase;

    /**
     * Use case for retrieving a single task by ID
     * 
     * @var GetTaskUseCase
     */
    private readonly GetTaskUseCase $getTaskUseCase;

    /**
     * Use case for creating new tasks
     * 
     * @var CreateTaskUseCase
     */
    private readonly CreateTaskUseCase $createTaskUseCase;

    /**
     * Use case for updating existing tasks
     * 
     * @var UpdateTaskUseCase
     */
    private readonly UpdateTaskUseCase $updateTaskUseCase;

    /**
     * Use case for updating task status (shortcut)
     * 
     * @var UpdateTaskStatusUseCase
     */
    private readonly UpdateTaskStatusUseCase $updateTaskStatusUseCase;

    /**
     * Use case for soft-deleting tasks
     * 
     * @var DeleteTaskUseCase
     */
    private readonly DeleteTaskUseCase $deleteTaskUseCase;

    /**
     * Constructor with dependency injection
     * 
     * @param GetTasksUseCase $getTasksUseCase Use case for listing tasks
     * @param GetTaskUseCase $getTaskUseCase Use case for retrieving single task
     * @param CreateTaskUseCase $createTaskUseCase Use case for creating tasks
     * @param UpdateTaskUseCase $updateTaskUseCase Use case for updating tasks
     * @param UpdateTaskStatusUseCase $updateTaskStatusUseCase Use case for status updates
     * @param DeleteTaskUseCase $deleteTaskUseCase Use case for soft-deleting tasks
     */
    public function __construct(
        GetTasksUseCase $getTasksUseCase,
        GetTaskUseCase $getTaskUseCase,
        CreateTaskUseCase $createTaskUseCase,
        UpdateTaskUseCase $updateTaskUseCase,
        UpdateTaskStatusUseCase $updateTaskStatusUseCase,
        DeleteTaskUseCase $deleteTaskUseCase,
    ) {
        $this->getTasksUseCase = $getTasksUseCase;
        $this->getTaskUseCase = $getTaskUseCase;
        $this->createTaskUseCase = $createTaskUseCase;
        $this->updateTaskUseCase = $updateTaskUseCase;
        $this->updateTaskStatusUseCase = $updateTaskStatusUseCase;
        $this->deleteTaskUseCase = $deleteTaskUseCase;
    }

    /**
     * List tasks with filtering and pagination
     * 
     * GET /api/tasks?page=1&limit=50&status=In+Progress&priority=High
     * 
     * Retrieves tasks assigned to or created by the authenticated user,
     * with support for filtering by status, priority, date range, and search.
     * 
     * Query parameters:
     * - page: Page number (1-based, default: 1)
     * - limit: Items per page (default: 50, max: 100)
     * - status: Filter by status (comma-separated for multiple: "Not Started,In Progress")
     * - priority: Filter by priority (comma-separated: "High,Medium")
     * - date_from: Filter tasks with due_date >= this date (YYYY-MM-DD)
     * - date_to: Filter tasks with due_date <= this date (YYYY-MM-DD)
     * - related_module: Filter by related module type (e.g., "Project")
     * - related_record_id: Filter by related record ID
     * - search: Search in subject and description fields
     * 
     * @param Request $request HTTP request with optional query parameters
     * 
     * @return JsonResponse JSON response with paginated tasks, metadata, and statistics
     * 
     * @throws InvalidArgumentException If query parameters are invalid
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Get first page of high priority incomplete tasks
     * GET /api/tasks?page=1&limit=20&status=Not+Started,In+Progress&priority=High
     * 
     * Response (200 OK):
     * {
     *   "data": [ {...}, {...} ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 45,
     *     "total_pages": 3,
     *     "has_more": true
     *   },
     *   "stats": {
     *     "total": 45,
     *     "completed": 10,
     *     "pending": 35,
     *     "overdue": 8,
     *     "highPriority": 12
     *   }
     * }
     * 
     * @example
     * // Search tasks by keyword
     * GET /api/tasks?search=proposal&limit=10
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Extract and parse filter parameters
            $filters = [
                'status' => $this->parseCommaSeparated($request->get('status')),
                'priority' => $this->parseCommaSeparated($request->get('priority')),
                'dateFrom' => $request->get('date_from'),
                'dateTo' => $request->get('date_to'),
                'relatedModule' => $request->get('related_module'),
                'relatedRecordId' => $this->parseIntOrNull($request->get('related_record_id')),
                'search' => $request->get('search'),
            ];

            // Extract pagination parameters with bounds checking
            $page = max(1, (int) $request->get('page', 1));
            $limit = min(max(1, (int) $request->get('limit', 50)), 100);

            // Execute use case with filtered parameters
            $result = $this->getTasksUseCase->execute(
                userId: $user->getId(),
                page: $page,
                limit: $limit,
                filters: array_filter($filters) // Remove null values
            );

            // Transform entities to DTOs for API response
            $taskDtos = array_map(
                fn($task) => $task instanceof TaskDto ? $task : TaskDto::fromEntity($task),
                $result['tasks']
            );

            return response()->json([
                'data' => $taskDtos,
                'meta' => $result['pagination'],
                'stats' => $result['stats'],
            ]);

        } catch (InvalidArgumentException $e) {
            // Invalid query parameters (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Database or infrastructure error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve tasks: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500 Internal Server Error)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single task by its unique identifier
     * 
     * GET /api/tasks/{taskId}
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
     * @return JsonResponse JSON response with task data or error
     * 
     * @throws InvalidArgumentException If taskId is invalid
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Get task #123
     * GET /api/tasks/123
     * 
     * Response (200 OK):
     * {
     *   "data": {
     *     "id": 123,
     *     "subject": "Call client about proposal",
     *     "status": "In Progress",
     *     "priority": "High",
     *     "dueDate": "2026-03-01",
     *     "assignedUserId": 456,
     *     "isOverdue": false,
     *     ...
     *   }
     * }
     * 
     * @example
     * // Task not found
     * GET /api/tasks/999
     * 
     * Response (404 Not Found):
     * {
     *   "error": "Task not found"
     * }
     */
    public function show(int $taskId): JsonResponse
    {
        try {
            // Execute use case to retrieve task
            $task = $this->getTaskUseCase->execute($taskId);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Transform entity to DTO for API response
            $dto = $task instanceof TaskDto ? $task : TaskDto::fromEntity($task);

            return response()->json(['data' => $dto->toArray()]);

        } catch (InvalidArgumentException $e) {
            // Invalid task ID (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Database error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve task: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new task
     * 
     * POST /api/tasks
     * 
     * Creates a new task associated with the authenticated user.
     * The user becomes both the creator and assignee by default.
     * 
     * Request body (JSON):
     * {
     *   "subject": "Task title",                    // required, max 255 chars
     *   "date_start": "2026-02-27",                 // required, YYYY-MM-DD
     *   "due_date": "2026-03-01",                   // optional, must be >= date_start
     *   "time_start": "09:00",                      // optional, HH:MM format
     *   "time_end": "17:00",                        // optional, must be >= time_start
     *   "priority": "High",                         // optional: Low, Medium, High
     *   "status": "Not Started",                    // optional: valid status values
     *   "location": "Conference Room A",            // optional, max 150 chars
     *   "description": "Task details...",           // optional, no limit
     *   "related_record_id": 123,                   // optional, related CRM entity ID
     *   "related_module_type": "Project",           // optional, module name
     *   "send_notification": true                   // optional, notify assignee
     * }
     * 
     * @param Request $request HTTP request with task creation data
     * 
     * @return JsonResponse JSON response with created task data or error
     * 
     * @throws ValidationException If request data fails framework validation (422)
     * @throws InvalidArgumentException If domain validation fails (400)
     * @throws DomainException If business rules are violated (403)
     * @throws RuntimeException If persistence operation fails (500)
     * 
     * @example
     * // Create a new high priority task
     * POST /api/tasks
     * {
     *   "subject": "Prepare quarterly report",
     *   "date_start": "2026-02-27",
     *   "due_date": "2026-03-15",
     *   "priority": "High",
     *   "status": "Not Started",
     *   "description": "Compile Q1 financial data and analysis"
     * }
     * 
     * Response (201 Created):
     * {
     *   "message": "Task created successfully",
     *   "data": {
     *     "id": 456,
     *     "subject": "Prepare quarterly report",
     *     "status": "Not Started",
     *     ...
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Framework-level validation (HTTP layer)
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'date_start' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:date_start',
                'time_start' => 'nullable|date_format:H:i',
                'time_end' => 'nullable|date_format:H:i|after:time_start',
                'priority' => 'nullable|string|in:Low,Medium,High',
                'status' => 'nullable|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
                'location' => 'nullable|string|max:150',
                'description' => 'nullable|string',
                'related_record_id' => 'nullable|integer',
                'related_module_type' => 'nullable|string|max:50',
                'send_notification' => 'nullable|boolean',
            ]);

            // Create DTO with validated and sanitized data
            $createRequest = new CreateTaskRequest(
                subject: $validated['subject'],
                activityType: 'Task',
                dateStart: $validated['date_start'],
                dueDate: $validated['due_date'] ?? null,
                timeStart: $validated['time_start'] ?? null,
                timeEnd: $validated['time_end'] ?? null,
                priority: $validated['priority'] ?? 'Medium',
                status: $validated['status'] ?? 'Not Started',
                location: $validated['location'] ?? null,
                description: $validated['description'] ?? null,
                assignedUserId: $user->getId(),
                relatedRecordId: $validated['related_record_id'] ?? null,
                relatedModuleType: $validated['related_module_type'] ?? null,
                sendNotification: $validated['send_notification'] ?? false,
            );

            // Execute use case: domain validation + persistence
            $taskId = $this->createTaskUseCase->execute($createRequest);
            
            // Fetch created task for response
            $task = $this->getTaskUseCase->execute($taskId);

            // Transform to DTO for API response
            $dto = $task ? ($task instanceof TaskDto ? $task : TaskDto::fromEntity($task)) : null;

            return response()->json([
                'message' => 'Task created successfully',
                'data' => $dto?->toArray(),
            ], 201);

        } catch (ValidationException $e) {
            // HTTP validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (InvalidArgumentException $e) {
            // Domain validation errors (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            // Business rule violations (403 Forbidden)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            // Persistence or infrastructure errors (500)
            return response()->json([
                'error' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected errors (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing task (full or partial update)
     * 
     * PATCH /api/tasks/{taskId}
     * 
     * Updates task fields. Only provided fields are modified (PATCH semantics).
     * Only the assigned user, creator, or an admin can update a task.
     * 
     * Request body (JSON) - all fields optional:
     * {
     *   "subject": "Updated title",
     *   "due_date": "2026-03-20",
     *   "priority": "Medium",
     *   "status": "In Progress",
     *   "description": "Updated description",
     *   ...
     * }
     * 
     * @param Request $request HTTP request with update data
     * @param int $taskId Unique identifier of the task to update
     * 
     * @return JsonResponse JSON response with updated task data or error
     * 
     * @throws ValidationException If request data fails validation (422)
     * @throws InvalidArgumentException If domain validation fails (400)
     * @throws DomainException If user is not authorized or business rules violated (403)
     * @throws RuntimeException If update operation fails (500)
     * 
     * @example
     * // Update task status and priority
     * PATCH /api/tasks/123
     * {
     *   "status": "Completed",
     *   "priority": "Low"
     * }
     * 
     * Response (200 OK):
     * {
     *   "message": "Task updated successfully",
     *   "data": {
     *     "id": 123,
     *     "status": "Completed",
     *     "priority": "Low",
     *     ...
     *   }
     * }
     * 
     * @example
     * // Unauthorized update attempt
     * PATCH /api/tasks/123
     * { "subject": "Hacked title" }
     * 
     * Response (403 Forbidden):
     * {
     *   "error": "User 789 is not authorized to update task 123"
     * }
     */
    public function update(Request $request, int $taskId): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Framework-level validation (HTTP layer)
            $validated = $request->validate([
                'subject' => 'nullable|string|max:255',
                'date_start' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:date_start',
                'time_start' => 'nullable|date_format:H:i',
                'time_end' => 'nullable|date_format:H:i|after:time_start',
                'priority' => 'nullable|string|in:Low,Medium,High',
                'status' => 'nullable|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
                'location' => 'nullable|string|max:150',
                'description' => 'nullable|string',
                'related_record_id' => 'nullable|integer',
                'related_module_type' => 'nullable|string|max:50',
                'send_notification' => 'nullable|boolean',
            ]);

            // Create DTO with validated data (null = don't update this field)
            $updateRequest = new UpdateTaskRequest(
                subject: $validated['subject'] ?? null,
                dateStart: $validated['date_start'] ?? null,
                dueDate: $validated['due_date'] ?? null,
                timeStart: $validated['time_start'] ?? null,
                timeEnd: $validated['time_end'] ?? null,
                priority: $validated['priority'] ?? null,
                status: $validated['status'] ?? null,
                location: $validated['location'] ?? null,
                description: $validated['description'] ?? null,
                relatedRecordId: $validated['related_record_id'] ?? null,
                relatedModuleType: $validated['related_module_type'] ?? null,
                sendNotification: $validated['send_notification'] ?? null,
            );

            // Execute use case: authorization + domain validation + persistence
            $success = $this->updateTaskUseCase->execute(
                taskId: $taskId,
                request: $updateRequest,
                userId: $user->getId()
            );

            if (!$success) {
                return response()->json(['error' => 'Task not found or update failed'], 404);
            }

            // Fetch updated task for response
            $task = $this->getTaskUseCase->execute($taskId);
            $dto = $task ? ($task instanceof TaskDto ? $task : TaskDto::fromEntity($task)) : null;

            return response()->json([
                'message' => 'Task updated successfully',
                'data' => $dto?->toArray(),
            ]);

        } catch (ValidationException $e) {
            // HTTP validation errors (422)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (InvalidArgumentException $e) {
            // Domain validation errors (400)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            // Authorization or business rule violations (403)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            // Persistence errors (500)
            return response()->json([
                'error' => 'Failed to update task: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected errors (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status (shortcut endpoint)
     * 
     * PATCH /api/tasks/{taskId}/status
     * 
     * Specialized endpoint for updating only the task status.
     * More efficient than full update when only status changes.
     * 
     * Request body (JSON):
     * {
     *   "status": "Completed"  // required, one of valid status values
     * }
     * 
     * @param Request $request HTTP request with new status value
     * @param int $taskId Unique identifier of the task to update
     * 
     * @return JsonResponse JSON response with update result or error
     * 
     * @throws ValidationException If status value is invalid (422)
     * @throws InvalidArgumentException If taskId is invalid (400)
     * @throws DomainException If user is not authorized (403)
     * @throws RuntimeException If update operation fails (500)
     * 
     * @example
     * // Mark task as completed
     * PATCH /api/tasks/123/status
     * {
     *   "status": "Completed"
     * }
     * 
     * Response (200 OK):
     * {
     *   "message": "Task status updated successfully",
     *   "task_id": 123,
     *   "status": "Completed"
     * }
     */
    public function updateStatus(Request $request, int $taskId): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Validate status value
            $request->validate([
                'status' => 'required|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
            ]);

            // Execute use case
            $success = $this->updateTaskStatusUseCase->execute(
                taskId: $taskId,
                status: $request->input('status'),
                modifiedBy: $user->getId()
            );

            if (!$success) {
                return response()->json(['error' => 'Task not found or update failed'], 404);
            }

            return response()->json([
                'message' => 'Task status updated successfully',
                'task_id' => $taskId,
                'status' => $request->input('status'),
            ]);

        } catch (ValidationException $e) {
            // Invalid status value (422)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (InvalidArgumentException $e) {
            // Invalid task ID (400)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            // Authorization failed (403)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            // Persistence error (500)
            return response()->json([
                'error' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a task (soft delete)
     * 
     * DELETE /api/tasks/{taskId}
     * 
     * Marks a task as deleted. Only the assigned user, creator, or an admin
     * can delete a task. Deletion is soft (vtiger_crmentity.deleted = 1)
     * for audit trail and potential restoration.
     * 
     * @param Request $request HTTP request (for authentication)
     * @param int $taskId Unique identifier of the task to delete
     * 
     * @return JsonResponse JSON response with deletion result or error
     * 
     * @throws InvalidArgumentException If taskId is invalid (400)
     * @throws DomainException If user is not authorized (403)
     * @throws RuntimeException If deletion operation fails (500)
     * 
     * @example
     * // Delete task #123
     * DELETE /api/tasks/123
     * Authorization: Bearer {token}
     * 
     * Response (200 OK):
     * {
     *   "message": "Task deleted successfully"
     * }
     * 
     * @example
     * // Delete non-existent task
     * DELETE /api/tasks/999
     * 
     * Response (404 Not Found):
     * {
     *   "error": "Task not found"
     * }
     */
    public function destroy(Request $request, int $taskId): JsonResponse
    {
        try {
            // Get authenticated user for authorization check
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Execute use case with user ID for authorization
            $success = $this->deleteTaskUseCase->execute(
                taskId: $taskId,
                userId: $user->getId()
            );

            if (!$success) {
                return response()->json(['error' => 'Task not found or already deleted'], 404);
            }

            return response()->json([
                'message' => 'Task deleted successfully',
            ]);

        } catch (InvalidArgumentException $e) {
            // Invalid task ID (400)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            // Authorization failed (403)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            // Persistence error (500)
            return response()->json([
                'error' => 'Failed to delete task: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted task (admin feature)
     * 
     * POST /api/tasks/{taskId}/restore
     * 
     * Reverses a soft delete operation, making the task visible again.
     * Only the original assignee, creator, or an admin can restore tasks.
     * 
     * Note: This endpoint is optional and may be restricted to admin users only.
     * 
     * @param Request $request HTTP request (for authentication)
     * @param int $taskId Unique identifier of the task to restore
     * 
     * @return JsonResponse JSON response with restore result or error
     * 
     * @throws DomainException If user is not authorized (403)
     * @throws RuntimeException If restore operation fails (500)
     * 
     * @example
     * // Restore accidentally deleted task
     * POST /api/tasks/123/restore
     * Authorization: Bearer {admin_token}
     * 
     * Response (200 OK):
     * {
     *   "message": "Task restored successfully",
     *   "task_id": 123
     * }
     */
    public function restore(Request $request, int $taskId): JsonResponse
    {
        try {
            // Get authenticated user
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Optional: Restrict to admin users only
            // if (!$this->isAdmin($user->getId())) {
            //     return response()->json(['error' => 'Admin access required'], 403);
            // }

            // Execute restore via repository (no dedicated use case needed for simple restore)
            // $success = $this->taskRepository->restore($taskId);
            
            // For now, return not implemented
            return response()->json(['error' => 'Restore endpoint not implemented'], 501);

        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Failed to restore task: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Parse comma-separated string into array of trimmed values
     * 
     * @param string|null $value Comma-separated string or null
     * @return string[]|null Array of trimmed values, or null if input is null/empty
     */
    private function parseCommaSeparated(?string $value): ?array
    {
        if (!$value || trim($value) === '') {
            return null;
        }
        
        return array_map('trim', explode(',', $value));
    }

    /**
     * Parse value as integer or return null
     * 
     * @param mixed $value Value to parse
     * @return int|null Parsed integer or null if invalid
     */
    private function parseIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed !== false ? $parsed : null;
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
        
        // Default: no admin users (can be enabled per deployment)
        return false;
    }
}