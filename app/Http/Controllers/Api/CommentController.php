<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Comment\CommentDto;
use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\UseCases\Comment\CreateCommentUseCase;
use App\Application\UseCases\Comment\GetCommentsByRelatedIdUseCase;
use App\Application\UseCases\Comment\UpdateCommentUseCase;
use App\Application\UseCases\Comment\DeleteCommentUseCase;
use App\Application\UseCases\Comment\GetCommentUseCase;
use App\Domain\Exceptions\Comment\CommentTargetNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\CurrentUserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use DomainException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Comment API Controller
 *
 * Handles HTTP requests for comment operations in the CRM system.
 *
 * Responsibilities:
 * - Parse and validate HTTP request data
 * - Delegate business logic to Application Use Cases
 * - Transform domain responses to JSON API format
 * - Handle exceptions and return appropriate HTTP status codes
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

 */
class CommentController extends Controller
{

    private const RELATED_ENTITY_TYPE = [
        'Project' => 'Proyecto',
        'Calendar' => 'Tarea',
        'Tasks' => 'Tarea',
        'Quotes' => 'Cotización',
        'Accounts' => 'Cliente',
        'Contacts' => 'Contacto',
        'Potentials' => 'Oportunidad',
        'HelpDesk' => 'Ticket',
    ];

    private const RELATED_ENTITY_ICON = [
        'Project' => '📋',
        'Calendar' => '✓',
        'Tasks' => '✓',
        'Quotes' => '📄',
        'Accounts' => '🏢',
        'Contacts' => '👤',
        'Potentials' => '💰',
        'HelpDesk' => '🎫',
    ];



    public function __construct(
        private readonly GetCommentsByRelatedIdUseCase $getCommentsByRelatedIdUseCase,
        private readonly CreateCommentUseCase $createCommentUseCase,
        private readonly GetCommentUseCase $getCommentUseCase,
        private readonly UpdateCommentUseCase $updateCommentUseCase,
        private readonly ?DeleteCommentUseCase $deleteCommentUseCase = null

    ) {}

    /**
     * List comments for a related record with pagination
     *
     * GET /api/comments/{module}/{relatedId}
     *
     * Retrieves all non-deleted comments associated with a specific record
     * (e.g., task, project, quote), ordered by creation date (newest first).
     *
     * @param Request $request HTTP request with optional pagination parameters
     * @param string $module Module type of the related record (e.g., 'Calendar', 'Project', 'Quotes')
     * @param int $relatedId ID of the related record (e.g., task ID from vtiger_activity)
     *
     * @return JsonResponse JSON response with paginated comments and metadata
     *
     * @throws InvalidArgumentException If module or relatedId is invalid
     * @throws RuntimeException If repository operation fails
     *
     * @example
     * // Get first page of comments for task #123
     * GET /api/comments/Calendar/123?page=1&limit=20
     *
     * Response:
     * {
     *   "data": [ {...}, {...} ],
     *   "meta": {
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 45,
     *     "last_page": 3,
     *     "has_more": true
     *   }
     * }
     */
    public function index(Request $request, string $module, int $relatedId): JsonResponse
    {
        try {
            //  Extract pagination parameters with defaults and bounds
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(max(1, (int) $request->get('limit', 50)), 100);

            //  Execute use case with validated parameters
            $paginator = $this->getCommentsByRelatedIdUseCase->execute(
                relatedId: $relatedId,
                module: $module,
                page: $page,
                perPage: $perPage
            );

            //  Transform Comment entities to DTOs for API response
            $dtos = $paginator->through(function ($row) {
                if ($row instanceof CommentDto) return $row;
                return CommentDto::fromDatabaseRow($row);
            });

            return response()->json([
                'data' => $dtos->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            //  Invalid input parameters (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (CommentTargetNotFoundException $e) {
            //  Related record not found (404 Not Found)
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            //  Database or infrastructure error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve comments: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            //  Unexpected error (500 Internal Server Error)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            //  Validate incoming request data
            $validated = $request->validate([
                'content' => 'required|string|max:65000',
                'parent_comment_id' => 'nullable|integer|min:1',
                'is_private' => 'nullable|boolean',
                'attachment' => 'nullable|string|max:255',
            ]);

            //  Get authenticated user from JWT middleware
            $authenticatedUser = $request->attributes->get('auth_user');
            if (!$authenticatedUser || !$authenticatedUser->getId()) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            //  Create DTO from validated data
            $createCommentRequest = CreateCommentRequest::fromValidatedData([
                'module' => $module,
                'related_id' => $recordId,
                'content' => $validated['content'],
                'user_id' => $authenticatedUser->getId(),
                'parent_comment_id' => $validated['parent_comment_id'] ?? null,
                'is_private' => $validated['is_private'] ?? null,
                'attachment' => $validated['attachment'] ?? null,
            ]);

            //  Execute UseCase
            $comment = $this->createCommentUseCase->execute($createCommentRequest);

            //  Return response using mapper for API format
            return response()->json([
                'data' => [
                    'id' => $comment->getId(),
                    'relatedTo' => $comment->getRelatedTo()
                ],
                'message' => 'Comment created successfully',
            ], 201);
        } catch (ValidationException $e) {
            //  Handle Laravel validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            //  Handle business rule validation errors (400 Bad Request)
            return response()->json([
                'error' => 'Invalid request: ' . $e->getMessage(),
            ], 400);
        } catch (CommentTargetNotFoundException $e) {
            //  Related record not found (404 Not Found)
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            //  Log error with context for debugging
            Log::error('Error creating comment: ' . $e->getMessage(), [
                'module' => $module,
                'recordId' => $recordId,
                'userId' => $authenticatedUser->getId() ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            //  Return generic error message for unexpected failures (500)
            return response()->json([
                'error' => 'Internal error creating comment',
            ], 500);
        }
    }

    /**
     * Get a single comment by its ID
     *
     * GET /api/comments/{commentId}
     *
     * Retrieves a specific comment with all related data including
     * author information, threading metadata, and attachment reference.
     *
     * @param int $commentId Unique identifier of the comment
     *
     * @return JsonResponse JSON response with comment data or error
     *
     * @throws InvalidArgumentException If commentId is invalid
     * @throws RuntimeException If repository operation fails
     *
     * @response 200 { "data": { CommentDto } }
     * @response 400 { "error": "Invalid comment ID" }
     * @response 404 { "error": "Comment not found" }
     * @response 500 { "error": "Failed to retrieve comment" }
     *
     * @example
     * // Get comment #456
     * GET /api/comments/456
     *
     * Response:
     * {
     *   "data": {
     *     "id": 456,
     *     "content": "Task completed!",
     *     "authorName": "Maria Garcia",
     *     "formattedCreatedAt": "27/02/2026 14:30",
     *     ...
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            $userId = CurrentUserService::idOr(1);
            $dto = $this->getCommentUseCase->execute(
                commentId: $id,
                userId: $userId
            );


            return response()->json($this->buildDetailResponse($dto));
        } catch (InvalidArgumentException $e) {
            // Invalid ID or unauthorized access
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (RuntimeException $e) {
            // Comment not found
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            // Unexpected error
            return response()->json([
                'error' => 'Error fetching comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build the JSON response body for a comment detail endpoint.
     *
     * Maps a CommentDto to the shape consumed by the frontend CommentDetail
     * (id, relatedToId, authorName, isReply, attachment, hasAttachment,
     * formattedCreatedAt, relatedEntityType/Icon, etc.).
     */
    private function buildDetailResponse(CommentDto $dto): array
    {
        return [
            'id' => $dto->id,
            'relatedToId' => $dto->relatedTo,
            'taskId' => $dto->relatedTo,
            'relatedModule' => $dto->relatedModule,
            'content' => $dto->content,
            'authorName' => $dto->userName ?? 'User',
            'userName' => $dto->userName,
            'userEmail' => $dto->userEmail,
            'userId' => $dto->userId,
            'createdAt' => $dto->createdAt,
            'updatedAt' => $dto->updatedAt,
            'formattedCreatedAt' => $this->formatCreatedAt($dto->createdAt),
            'isPrivate' => $dto->isPrivate,
            'isReply' => $dto->parentCommentId !== null,
            'parentCommentId' => $dto->parentCommentId,
            'attachment' => $dto->filename,
            'reasonToEdit' => $dto->reasonToEdit,
            'hasAttachment' => !empty($dto->filename),
            'relatedEntityType' => self::RELATED_ENTITY_TYPE[$dto->relatedModule] ?? 'Entity',
            'relatedEntityIcon' => self::RELATED_ENTITY_ICON[$dto->relatedModule] ?? '🔗',
        ];
    }

    private function formatCreatedAt(?string $createdAt): ?string
    {
        if (!$createdAt) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($createdAt))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $createdAt;
        }
    }

    /**
     * Update an existing comment
     *
     * PATCH /api/comments/{commentId}
     *
     * Updates the content of an existing comment. Only the original author
     * or an administrator can edit a comment.
     *
     * @param Request $request HTTP request with update data
     * @param int $commentId Unique identifier of the comment to update
     *
     * @return JsonResponse JSON response with update result or error
     *
     * @response 200 { "message": "Comment updated successfully" }
     * @response 400 { "error": "Invalid request: <message>" }
     * @response 401 { "error": "User not authenticated" }
     * @response 403 { "error": "You are not authorized to edit this comment" }
     * @response 404 { "error": "Comment not found" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 500 { "error": "Error updating comment: <message>" }
     *
     * @example
     * // Update comment content with reason
     * PATCH /api/comments/9384
     * {
     *   "content": "Pendiente inicios de obra civil - actualizado",
     *   "reason_to_edit": "Corrección de ortografía"
     * }
     */
    public function update(Request $request, string $module,  int $relatedId, int $commentId): JsonResponse
    {
        try {
            // Validate incoming request data
            $validated = $request->validate([
                'content' => 'required|string|max:65000',
                'reason_to_edit' => 'nullable|string|max:255',
            ]);

            // Get authenticated user from JWT middleware
            $authenticatedUser = $request->attributes->get('auth_user');
            if (!$authenticatedUser) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Execute use case to update comment
            $success = $this->updateCommentUseCase->execute(
                commentId: $commentId,
                authenticatedUserId: $authenticatedUser->getId(),
                content: $validated['content'],
                reasonToEdit: $validated['reason_to_edit'] ?? null,
                module: $module,
                relatedId: $relatedId
            );

            if ($success) {
                return response()->json([
                    'message' => 'Comment updated successfully'
                ], 200);
            }

            // Comment not found (404 Not Found)
            return response()->json(['error' => 'Comment not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\DomainException $e) {
            // Handle authorization errors (403 Forbidden)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            // Handle invalid input (400 Bad Request)
            return response()->json(['error' => 'Invalid request: ' . $e->getMessage()], 400);
        } catch (Exception $e) {
            // Log and return error for unexpected failures (500)
            Log::error('Error updating comment: ' . $e->getMessage(), [
                'commentId' => $commentId,
                'userId' => $request->attributes->get('auth_user')?->getId() ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error updating comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft delete) a comment
     *
     * DELETE /api/comments/{commentId}
     *
     * Marks a comment as deleted. Only the author or an admin
     * can delete a comment (enforced by business rules).
     *
     * @param Request $request HTTP request (used for authentication and optional reason)
     * @param int $commentId Unique identifier of the comment to delete
     *
     * @return JsonResponse JSON response with deletion result
     *
     * @throws InvalidArgumentException If commentId is invalid (400)
     * @throws DomainException If user is not authorized to delete (403)
     * @throws RuntimeException If deletion operation fails (500)
     *
     * @response 200 { "message": "Comment deleted successfully", "comment_id": 456 }
     * @response 401 { "error": "Unauthorized" }
     * @response 403 { "error": "Not authorized to delete this comment" }
     * @response 404 { "error": "Comment not found or already deleted" }
     * @response 501 { "error": "Delete not implemented" }
     */
    public function destroy(Request $request, int $commentId): JsonResponse
    {
        try {
            //  Check if delete functionality is implemented
            if (!$this->deleteCommentUseCase) {
                return response()->json(['error' => 'Delete not implemented'], 501);
            }

            //  Verify user authentication
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            //  Optional: Get deletion reason for audit trail
            $reason = $request->input('reason');

            //  Execute use case
            $success = $this->deleteCommentUseCase->execute(
                commentId: $commentId,
                userId: $user->getId(),
                reason: $reason
            );

            if (!$success) {
                //  Comment not found or already deleted (404 Not Found)
                return response()->json(['error' => 'Comment not found or already deleted'], 404);
            }

            //  Successful deletion (200 OK)
            return response()->json([
                'message' => 'Comment deleted successfully',
                'comment_id' => $commentId,
            ]);
        } catch (InvalidArgumentException $e) {
            //  Invalid input parameters (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            //  Authorization error (403 Forbidden)
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            //  Infrastructure error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================================================
    // TASK-SPECIFIC COMMENT METHODS (Nested routes: /api/tasks/{taskId}/comments)
    // ========================================================================

    /**
     * List comments for a specific task
     *
     * GET /api/tasks/{taskId}/comments
     *
     * Wrapper around index() with module hardcoded to 'Calendar'.
     *
     * @param Request $request HTTP request with optional pagination parameters
     * @param int $taskId Task ID (injected from nested route)
     *
     * @return JsonResponse JSON response with paginated comments
     *
     * @see self::index()
     */
    public function indexByTask(Request $request, int $taskId): JsonResponse
    {
        // Delegate to generic method with module = 'Calendar'
        return $this->index($request, module: 'Calendar', relatedId: $taskId);
    }

    /**
     * Create a comment on a specific task
     *
     * POST /api/tasks/{taskId}/comments
     *
     * Wrapper around store() with module hardcoded to 'Calendar'.
     *
     * @param Request $request HTTP request with comment creation data
     * @param int $taskId Task ID (injected from nested route)
     *
     * @return JsonResponse JSON response with created comment
     *
     * @see self::store()
     */
    public function storeByTask(Request $request, int $taskId): JsonResponse
    {
        // Delegate to generic method with module = 'Calendar'
        return $this->store($request, module: 'Calendar', recordId: $taskId);
    }

    /**
     * Update a comment on a task (optional, if needed)
     *
     * PATCH /api/tasks/{taskId}/comments/{commentId}
     *
     * @param Request $request HTTP request with update data
     * @param int $taskId Task ID (for authorization context)
     * @param int $commentId Comment ID to update
     *
     * @return JsonResponse
     */
    /*public function updateByTask(Request $request, int $taskId, int $commentId): JsonResponse
    {
        // Optional: Add task-specific authorization logic here
        // For now, delegate to generic update
        return $this->update($request, $commentId);
    }

    /**
     * Delete a comment on a task (optional, if needed)
     *
     * DELETE /api/tasks/{taskId}/comments/{commentId}
     *
     * @param Request $request HTTP request
     * @param int $taskId Task ID (for authorization context)
     * @param int $commentId Comment ID to delete
     *
     * @return JsonResponse
     */
    /* public function destroyByTask(Request $request, int $taskId, int $commentId): JsonResponse
    {
        // Optional: Verify comment belongs to this task before deleting
        // For now, delegate to generic destroy
        return $this->destroy($request, $commentId);
    }*/
}
