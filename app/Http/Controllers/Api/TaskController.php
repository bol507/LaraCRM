<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\Task\TaskDto;
use App\Application\UseCases\Task\CreateTaskUseCase;
use App\Application\UseCases\Task\DeleteTaskUseCase;
use App\Application\UseCases\Task\GetTasksUseCase;
use App\Application\UseCases\Task\UpdateTaskStatusUseCase;
use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly GetTasksUseCase $getTasksUseCase,
        private readonly CreateTaskUseCase $createTaskUseCase,
        private readonly UpdateTaskStatusUseCase $updateTaskStatusUseCase,
        private readonly DeleteTaskUseCase $deleteTaskUseCase,
    ) {}

    /**
     * List tasks with filtering and pagination
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $filters = [
                'status' => $request->get('status'),
                'priority' => $request->get('priority'),
                'dateFrom' => $request->get('date_from'),
                'dateTo' => $request->get('date_to'),
                'relatedModule' => $request->get('related_module'),
                'relatedRecordId' => $request->get('related_record_id'),
            ];

            $page = (int) $request->get('page', 1);
            $limit = min((int) $request->get('limit', 50), 100);

            $result = $this->getTasksUseCase->execute($user->id, $page, $limit, $filters);

            return response()->json([
                'data' => array_map(fn(TaskDto $dto) => $dto->toArray(), $result['tasks']),
                'meta' => $result['pagination'],
                'stats' => $result['stats'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new task
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

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
                assignedUserId: $user->id,
                relatedRecordId: $validated['related_record_id'] ?? null,
                relatedModuleType: $validated['related_module_type'] ?? null,
                sendNotification: $validated['send_notification'] ?? false,
            );

            $taskId = $this->createTaskUseCase->execute($createRequest);
            $task = $this->getTasksUseCase->getById($taskId);

            return response()->json([
                'message' => 'Task created successfully',
                'data' => TaskDto::fromEntity($task)->toArray(),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error creating task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status
     * 
     * @param Request $request
     * @param int $taskId
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $taskId): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
            ]);

            $success = $this->updateTaskStatusUseCase->execute($taskId, $request->input('status'));

            if (!$success) {
                return response()->json(['error' => 'Task not found or update failed'], 404);
            }

            return response()->json([
                'message' => 'Task status updated successfully',
                'task_id' => $taskId,
                'status' => $request->input('status'),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a task
     * 
     * @param int $taskId
     * @return JsonResponse
     */
    public function destroy(int $taskId): JsonResponse
    {
        try {
            $success = $this->deleteTaskUseCase->execute($taskId);

            if (!$success) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            return response()->json([
                'message' => 'Task deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error deleting task: ' . $e->getMessage()
            ], 500);
        }
    }
}