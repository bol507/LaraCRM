<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use InvalidArgumentException;
use RuntimeException;

/**
 * Get Comment Use Case
 * 
 * Orchestrates the retrieval of a single comment by its ID.
 * 
 * Responsibilities:
 * - Validate that the comment ID is valid
 * - Verify that the comment exists and is not deleted
 * - Check that the requesting user has permission to view the comment
 * - Return the comment entity or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Comment must exist and not be deleted
 * - User must have permission to view the comment (public or author)
 * - Private comments are only visible to author or admins
 * 
 * This use case is part of the Application layer and should not contain:
 * - HTTP-specific logic (request/response handling)
 * - Database-specific queries (SQL, joins, etc.)
 * - UI-specific formatting (dates, localization, etc.)
 * 
 * @package App\Application\UseCases\Comment
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Http\Controllers\Api\CommentController
 */
class GetCommentUseCase
{
   
    private readonly CommentRepositoryInterface $repository;

    
    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the get comment use case
     * 
     * Retrieves a single comment by its unique identifier after validating:
     * - Comment ID is valid (positive integer)
     * - Comment exists and is not deleted
     * - Requesting user has permission to view the comment
     * 
     * @param int $commentId Unique identifier of the comment to retrieve
     * @param int $userId ID of the user requesting the comment (for authorization)
     * 
     * @return Comment The comment entity if found and accessible
     * 
     * @throws InvalidArgumentException If comment ID is invalid
     * @throws RuntimeException If comment is not found
     * @throws InvalidArgumentException If user does not have permission to view
     * 
     * @example
     * // Get comment with user authorization
     * $comment = $useCase->execute(commentId: 456, userId: 123);
     * return response()->json($comment->toArray());
     * 
     * @example
     * // Handle exceptions in controller
     * try {
     *     $comment = $useCase->execute(456, 123);
     * } catch (RuntimeException $e) {
     *     return response()->json(['error' => $e->getMessage()], 404);
     * }
     */
    public function execute(int $commentId, int $userId): Comment
    {
        
        $this->validateParameters($commentId, $userId);
        $comment = $this->repository->findById($commentId);
        
        if (!$comment) {
            throw new RuntimeException("Comment {$commentId} not found or has been deleted");
        }

        $this->verifyViewPermission($comment, $userId);

        return $comment;
    }

    /**
     * Validate input parameters for the use case
     * 
     * @param int $commentId Comment ID to validate
     * @param int $userId User ID to validate
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $commentId, int $userId): void
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException(
                "Comment ID must be a positive integer, got {$commentId}"
            );
        }
        
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "User ID must be a positive integer, got {$userId}"
            );
        }
    }

    /**
     * Verify that the user is authorized to view the comment
     * 
     * Business rules:
     * - Public comments (is_private = 0): visible to all authenticated users
     * - Private comments (is_private = 1): visible only to author or admins
     * - Deleted comments: never visible (filtered by repository)
     * 
     * @param Comment $comment The comment entity to check
     * @param int $userId ID of the user requesting access
     * @return void
     * @throws InvalidArgumentException If user is not authorized
     */
    private function verifyViewPermission(Comment $comment, int $userId): void
    {
       
        if (!$comment->isVisibleTo($userId, $this->isInternalUser($userId))) {
            throw new InvalidArgumentException(
                "User {$userId} is not authorized to view comment {$comment->getId()}. " .
                "This comment is private and you are not the author."
            );
        }

    }

    /**
     * Check if a user is an internal CRM user (vs customer portal user)
     * 
     * @param int $userId User ID to check
     * @return bool True if user is internal, false if customer
     * 
     * @internal Implementation depends on your authentication system
     */
    private function isInternalUser(int $userId): bool
    {

        return true;
    }

    /**
     * Check if user has access to the related record
     * 
     * @param int $relatedId ID of the related record
     * @param int $userId User ID to check
     * @return bool True if user has access, false otherwise
     * 
     * @internal Implementation depends on your permission system
     */
    private function hasAccessToRelatedRecord(int $relatedId, int $userId): bool
    {

        return true;
    }
}