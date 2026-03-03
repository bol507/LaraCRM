<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Comment\CommentDto;
use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\UseCases\Comment\CreateCommentUseCase;
use App\Application\UseCases\Comment\GetCommentsByRelatedIdUseCase;
use App\Application\UseCases\Comment\UpdateCommentUseCase;
use App\Application\UseCases\Comment\DeleteCommentUseCase;
use App\Http\Controllers\Controller;
use App\Infrastructure\Mappers\CommentMapper;
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
 * @see \App\Application\UseCases\Comment\GetCommentsByRelatedIdUseCase
 * @see \App\Application\UseCases\Comment\CreateCommentUseCase
 * @see \App\Application\DTOs\Comment\CommentDto
 * @see \App\Application\DTOs\Comment\CreateCommentRequest
 */
class CommentController extends Controller
{
    /**
     * Use case for retrieving comments by related record ID
     * 
     * @var GetCommentsByRelatedIdUseCase
     */
    private readonly GetCommentsByRelatedIdUseCase $getCommentsByRelatedIdUseCase;

    /**
     * Use case for creating new comments
     * 
     * @var CreateCommentUseCase
     */
    private readonly CreateCommentUseCase $createCommentUseCase;

    /**
     * Use case for updating existing comments (optional)
     * 
     * @var UpdateCommentUseCase|null
     */
    private readonly ?UpdateCommentUseCase $updateCommentUseCase;

    /**
     * Use case for deleting comments (optional)
     * 
     * @var DeleteCommentUseCase|null
     */
    private readonly ?DeleteCommentUseCase $deleteCommentUseCase;

    /**
     * Constructor with dependency injection
     * 
     * @param GetCommentsByRelatedIdUseCase $getCommentsByRelatedIdUseCase Use case for listing comments
     * @param CreateCommentUseCase $createCommentUseCase Use case for creating comments
     * @param UpdateCommentUseCase|null $updateCommentUseCase Use case for updating comments (optional)
     * @param DeleteCommentUseCase|null $deleteCommentUseCase Use case for deleting comments (optional)
     */
    public function __construct(
        GetCommentsByRelatedIdUseCase $getCommentsByRelatedIdUseCase,
        CreateCommentUseCase $createCommentUseCase,
        ?UpdateCommentUseCase $updateCommentUseCase = null,
        ?DeleteCommentUseCase $deleteCommentUseCase = null
    ) {
        $this->getCommentsByRelatedIdUseCase = $getCommentsByRelatedIdUseCase;
        $this->createCommentUseCase = $createCommentUseCase;
        $this->updateCommentUseCase = $updateCommentUseCase;
        $this->deleteCommentUseCase = $deleteCommentUseCase;
    }

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
            $dtos = $paginator->getCollection()->map(
                fn($comment) => $comment instanceof CommentDto
                    ? $comment
                    : CommentDto::fromEntity($comment)
            );

            return response()->json([
                'data' => $dtos->all(),
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
        } catch (RuntimeException $e) {
            //  Database or infrastructure error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve comments: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            //  Unexpected error (500 Internal Server Error)
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new comment on a related record
     * 
     * POST /api/comments/{module}/{relatedId}
     * 
     * Creates a new comment associated with a specific record (e.g., task, project).
     * The authenticated user becomes the author of the comment.
     * 
     * @param Request $request HTTP request with comment creation data
     * @param string $module Module type of the related record (e.g., 'Calendar', 'Project')
     * @param int $relatedId ID of the related record (e.g., task ID)
     * 
     * @return JsonResponse JSON response with created comment data
     * 
     * @throws ValidationException If request data fails framework validation (422)
     * @throws InvalidArgumentException If domain validation fails (400)
     * @throws DomainException If business rules are violated (403)
     * @throws RuntimeException If persistence operation fails (500)
     * 
     * @response 201 {
     *   "data": {
     *     "id": 456,
     *     "content": "Task completed successfully!",
     *     "userName": "Maria Garcia",
     *     "createdAt": "2026-02-27 14:30:00",
     *     "isPrivate": false,
     *     ...
     *   }
     * }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": { field: [errors] } }
     * @response 500 { "error": "Internal error creating comment" }
     * 
     * @example
     * // Create a top-level comment on task #123
     * POST /api/comments/Calendar/123
     * {
     *   "content": "Task completed successfully!",
     *   "parent_comment_id": null,
     *   "attachment": null
     * }
     * 
     * @example
     * // Create a threaded reply with attachment
     * POST /api/comments/Calendar/123
     * {
     *   "content": "See attached document for details",
     *   "parent_comment_id": 789,
     *   "attachment": "proposal.pdf"
     * }
     */
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
            $createCommentRequest = new CreateCommentRequest(
                module: $module,
                relatedId: $recordId,
                content: $validated['content'],
                userId: $authenticatedUser->getId(),
                parentCommentId: $validated['parent_comment_id'] ?? null,
                isPrivate: $validated['is_private'] ?? null,
                attachment: $validated['attachment'] ?? null,
            );

            //  Execute UseCase
            $comment = $this->createCommentUseCase->execute($createCommentRequest);

            //  Return response using mapper for API format
            return response()->json([
                'data' => CommentMapper::toApi($comment),
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

        } catch (\Exception $e) {
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
    public function show(int $commentId): JsonResponse
    {
        try {
            // TODO: Implement show method with repository or use case
            // $comment = $this->commentRepository->findById($commentId);
            // if (!$comment) {
            //     return response()->json(['error' => 'Comment not found'], 404);
            // }
            // return response()->json(['data' => CommentDto::fromEntity($comment)->toArray()]);

            return response()->json(['error' => 'Not implemented'], 501);
        } catch (InvalidArgumentException $e) {
            //  Invalid comment ID (400 Bad Request)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            //  Unexpected error (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to retrieve comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing comment
     * 
     * PATCH /api/comments/{commentId}
     * 
     * Updates comment content and metadata. Only the original author
     * can update their own comments (enforced by business rules).
     * 
     * @param Request $request HTTP request with update data
     * @param int $commentId Unique identifier of the comment to update
     * 
     * @return JsonResponse JSON response with update result
     * 
     * @throws ValidationException If request data fails validation (422)
     * @throws DomainException If user is not authorized to update (403)
     * @throws RuntimeException If update operation fails (500)
     * 
     * @response 200 { "message": "Comment updated successfully" }
     * @response 403 { "error": "Not authorized to update this comment" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 501 { "error": "Update not implemented" }
     * 
     * @example
     * // Update comment content
     * PATCH /api/comments/456
     * {
     *   "content": "Updated comment text",
     *   "reason_to_edit": "Fixed typo"
     * }
     */
    public function update(Request $request, int $commentId): JsonResponse
    {
        try {
            //  Check if update functionality is implemented
            if (!$this->updateCommentUseCase) {
                return response()->json(['error' => 'Update not implemented'], 501);
            }

            //  Verify user authentication
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            //  Validate incoming update data
            $validated = $request->validate([
                'content' => 'nullable|string|max:65000',
                'reason_to_edit' => 'nullable|string|max:255',
                'is_private' => 'nullable|boolean',
                'attachment' => 'nullable|string|max:255',
            ]);

            // TODO: Create UpdateCommentRequest DTO and execute use case
            // $updateRequest = new UpdateCommentRequest(...);
            // $success = $this->updateCommentUseCase->execute($commentId, $updateRequest, $user->getId());

            return response()->json(['error' => 'Not implemented'], 501);

        } catch (ValidationException $e) {
            //  Handle validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        } catch (DomainException $e) {
            //  Handle authorization errors (403 Forbidden)
            return response()->json(['error' => $e->getMessage()], 403);

        } catch (\Exception $e) {
            //  Handle unexpected errors (500 Internal Server Error)
            return response()->json([
                'error' => 'Failed to update comment: ' . $e->getMessage()
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
}