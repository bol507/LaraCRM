<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Calendar\ActivityDto;
use App\Application\DTOs\Calendar\CreateActivityRequest;
use App\Application\DTOs\Calendar\GetActivityFiltersRequest;
use App\Application\DTOs\Calendar\UpdateActivityRequest;
use App\Application\UseCases\Calendar\CreateActivityUseCase;
use App\Application\UseCases\Calendar\GetActivitiesUseCase;
use App\Application\UseCases\Calendar\GetActivityFiltersUseCase;
use App\Application\UseCases\Calendar\GetActivityUseCase;
use App\Application\UseCases\Calendar\UpdateActivityUseCase;
use App\Application\UseCases\User\CanAssignToUserUseCase;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class CalendarController extends Controller
{
    public function __construct(
        private readonly GetActivitiesUseCase $getActivitiesUseCase,
        private readonly GetActivityUseCase $getActivityUseCase,
        private readonly GetActivityFiltersUseCase $getActivityFiltersUseCase,
        private readonly CreateActivityUseCase $createActivityUseCase,
        private readonly UpdateActivityUseCase $updateActivityUseCase,
        private readonly CanAssignToUserUseCase $canAssignToUserUseCase,
    ) {}

    /**
     * GET /api/calendar/activities
     * List all activities for a user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (! $user) {
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
                'assignedTo' => $this->parseIntOrNull($request->get('assignedTo')),
            ];

            $assignedTo = $filters['assignedTo'] ?? null;
            $filterRequest = new GetActivityFiltersRequest(
                currentUserId: $user->getId(),
                requestedUserId: $assignedTo,
                currentUserRoleId: $user->getRoleId(),  
            );
            $filterResponse = $this->getActivityFiltersUseCase->execute($filterRequest);

            // Extract pagination parameters with bounds checking
            $page = max(1, (int) $request->get('page', 1));
            $limit = min(max(1, (int) $request->get('limit', 50)), 100);

            // Execute use case with filtered parameters
            $result = $this->getActivitiesUseCase->execute(
                userId: $user->getId(),
                page: $page,
                limit: $limit,
                filters: array_filter($filters),
                requestedUserId: $filterResponse->requestedUserId,
                subordinateIds: $filterResponse->subordinateIds,
            );

            // Transform entities to DTOs for API response
            

            return response()->json([
                'data' => $result['activities'],
                'meta' => $result['pagination'],
                'stats' => $result['stats'],
            ]);
        } catch (InvalidArgumentException $e) {
            // Invalid query parameters (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Database or infrastructure error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve tasks: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500 Internal Server Error)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            // Execute use case to retrieve task
            $dto = $this->getActivityUseCase->execute($id);

            if (! $dto) {
                return response()->json(['error' => 'Task not found'], 404);
            }
        
            return response()->json(['data' => $dto->toArray()]);
        } catch (InvalidArgumentException $e) {
            // Invalid task ID (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Database error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve task: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            // Unexpected error (500)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');
            if (! $user) {
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
                'assigned_user_id' => 'nullable|integer|exists:vtiger.vtiger_users,id',
                'duration_hours' => 'nullable|integer',
                'duration_minutes' => 'nullable|integer',
            ]);

            // Determine assigned user ID
            $assignedUserId = $user->getId();
            if (isset($validated['assigned_user_id']) && $validated['assigned_user_id'] !== null) {
                $requestedAssigneeId = (int) $validated['assigned_user_id'];
                if (! $this->canAssignToUserUseCase->execute($user->getId(), $requestedAssigneeId)) {
                    return response()->json([
                        'error' => 'You can only assign tasks to yourself or your subordinates',
                        'message' => 'Solo puedes asignar tareas a ti mismo o a tus subordinados',
                    ], 403);
                }
                $assignedUserId = $requestedAssigneeId;
            }

            $createRequest = new CreateActivityRequest(
                subject: $validated['subject'],
                activityType: 'Task', // @todo: use constant
                dateStart: $validated['date_start'],
                dueDate: $validated['due_date'] ?? null,
                timeStart: $validated['time_start'] ?? null,
                timeEnd: $validated['time_end'] ?? null,
                priority: $validated['priority'] ?? 'Medium',
                status: $validated['status'] ?? 'Not Started',
                location: $validated['location'] ?? null,
                description: $validated['description'] ?? null,
                assignedUserId: $assignedUserId,
                relatedRecordId: $validated['related_record_id'] ?? null,
                relatedModuleType: $validated['related_module_type'] ?? null,
                sendNotification: $validated['send_notification'] ?? false,
                durationHours: $validated['duration_hours'] ?? null,
                durationMinutes: $validated['duration_minutes'] ?? null,

            );
            //return activityId
            $activityId = $this->createActivityUseCase->execute($createRequest);
            $dto = $this->getActivityUseCase->execute($activityId);
            

            return response()->json([
                'message' => 'Activity task created successfully',
                'data' => $dto->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Failed to create task: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            // Validate incoming request data
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'date_start' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:date_start',
                'dueDate' => 'nullable|date|after_or_equal:date_start',
                'time_start' => 'nullable|date_format:H:i',
                'time_end' => 'nullable|date_format:H:i|after:time_start',
                'priority' => 'nullable|string|in:Low,Medium,High',
                'status' => 'nullable|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
                'location' => 'nullable|string|max:150',
                'description' => 'nullable|string',
                'assigned_user_id' => 'nullable|integer|exists:vtiger.vtiger_users,id',
                'assignedUserId' => 'nullable|integer|exists:vtiger.vtiger_users,id',
                'related_record_id' => 'nullable|integer',
                'related_module_type' => 'nullable|string|max:50',
                'send_notification' => 'nullable|boolean',
            ]);

            // Determine assigned user ID
            $assignedUserId = $request->attributes->get('auth_user')->getId();            
            if (isset($validated['assigned_user_id']) && $validated['assigned_user_id'] !== null) {
                $requestedAssigneeId = (int) $validated['assigned_user_id'];
                if (! $this->canAssignToUserUseCase->execute($assignedUserId, $requestedAssigneeId)) {
                    return response()->json([
                        'error' => 'You can only assign tasks to yourself or your subordinates',
                        'message' => 'Solo puedes asignar tareas a ti mismo o a tus subordinados',
                    ], 403);
                }
                $assignedUserId = $requestedAssigneeId;
            }

            $updateData = new UpdateActivityRequest(
                subject : $validated['subject'],
                activityType : 'Task', // @todo: use constant
                dateStart : $validated['date_start'] ?? null,
                dueDate : $validated['due_date'] ?? $validated['dueDate'] ?? null,
                timeStart : $validated['time_start'] ?? null,
                timeEnd : $validated['time_end'] ?? null,
                priority : $validated['priority'],
                status : $validated['status'],
                location : $validated['location'],
                description : $validated['description'],
                assignedUserId : $assignedUserId,
                relatedRecordId : $validated['related_record_id'] ?? null,
                relatedModuleType : $validated['related_module_type'] ?? null,
                sendNotification : $validated['send_notification'] ?? false,
                durationHours : null,
                durationMinutes : null,
            );

            
            // Execute use case: authorization + domain validation + persistence
            $success = $this->updateActivityUseCase->execute(
                activityId: $id,
                request: $updateData,
                assignedUserId: $assignedUserId
            );

            if (! $success) {
                return response()->json(['error' => 'Task not found or update failed'], 404);
            }

            $activityDto = $this->getActivityUseCase->execute($id);
            

            return response()->json([
                'message' => 'Task updated successfully',
                'data' => $activityDto?->toArray(),
            ],

            200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }
        catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Failed to update task: ' . $e->getMessage(),
            ], 500);
        }
        catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Parse comma-separated string into array of trimmed values
     *
     * @param  string|null  $value  Comma-separated string or null
     * @return string[]|null Array of trimmed values, or null if input is null/empty
     */
    private function parseCommaSeparated(?string $value): ?array
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Parse value as integer or return null
     *
     * @param  mixed  $value  Value to parse
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
}