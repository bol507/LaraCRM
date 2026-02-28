<?php

namespace App\Application\Repositories;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Domain\Entities\Comment;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Comment Repository Interface
 * 
 * Defines the contract for comment persistence operations.
 * 
 * This interface abstracts the data access layer for comments, allowing
 * different implementations (Vtiger, MySQL, PostgreSQL, etc.) while
 * maintaining a consistent API for the application layer.
 * 
 * Key responsibilities:
 * - CRUD operations for comments (Create, Read, Update, Delete)
 * - Pagination support for comment lists
 * - Entity mapping between database rows and domain entities
 * - Transaction management for data consistency
 * 
 * Implementations should:
 * - Return domain entities (Comment) from read operations
 * - Accept DTOs (CreateCommentRequest) for write operations
 * - Handle soft deletes if supported by the data source
 * - Throw RuntimeException for persistence failures
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Comment
 * @see \App\Application\DTOs\CreateCommentRequest
 * @see \App\Infrastructure\Repositories\VtigerCommentRepository
 * @see \App\Application\UseCases\Comment\CreateCommentUseCase
 * @see \App\Application\UseCases\Comment\GetTaskCommentsUseCase
 */
interface CommentRepositoryInterface
{
    /**
     * Get all comments for a related record with pagination
     * 
     * Retrieves comments associated with a specific record (e.g., task, project, quote)
     * ordered by creation date (oldest first for chronological display).
     * 
     * Business rules:
     * - Only returns non-deleted comments (soft delete aware)
     * - Supports threading via parent_comments field
     * - Includes user information (name, email) via join with users table
     * - Respects visibility permissions (private vs public comments)
     * 
     * @param int $relatedId ID of the related record (e.g., task ID from vtiger_activity)
     * @param string $module Module type of the related record (e.g., 'Calendar', 'Project', 'Quotes')
     * @param int $page Current page number (1-based, default: 1)
     * @param int $perPage Number of items per page (default: 50, max: 100)
     * 
     * @return LengthAwarePaginator Paginated collection of Comment entities
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If relatedId is invalid (<= 0)
     * 
     * @example
     * // Get first page of comments for task #123
     * $paginator = $repository->getByRelatedId(123, 'Calendar', page: 1, perPage: 20);
     * 
     * @example
     * // In a use case:
     * $comments = $this->repository->getByRelatedId($taskId, 'Calendar');
     * return [
     *     'comments' => $comments->items(),
     *     'pagination' => [
     *         'current_page' => $comments->currentPage(),
     *         'total' => $comments->total(),
     *         'last_page' => $comments->lastPage(),
     *     ],
     * ];
     */
    public function getByRelatedId(
        int $relatedId,
        string $module,
        int $page = 1,
        int $perPage = 50
    ): LengthAwarePaginator;

    /**
     * Get a single comment by its unique identifier
     * 
     * Retrieves a specific comment with all related data including:
     * - Comment content and metadata
     * - Author information (user name, email)
     * - Parent comment reference (for threaded replies)
     * - Attachment information (if any)
     * 
     * @param int $commentId Unique identifier of the comment (vtiger_comments.commentid)
     * 
     * @return Comment|null Comment entity if found, null if not exists or deleted
     * 
     * @throws \RuntimeException If database query fails
     * @throws \InvalidArgumentException If commentId is invalid (<= 0)
     * 
     * @example
     * // Get comment by ID
     * $comment = $repository->findById(456);
     * if ($comment) {
     *     echo $comment->getContent();
     * }
     * 
     * @example
     * // In a use case with null handling
     * $comment = $this->repository->findById($commentId);
     * if (!$comment) {
     *     throw new NotFoundException('Comment not found');
     * }
     * if (!$comment->canBeEditedBy($userId)) {
     *     throw new AuthorizationException('Cannot edit this comment');
     * }
     */
    public function findById(int $commentId): ?Comment;

    /**
     * Create a new comment from validated request data
     * 
     * Persists a new comment to the database with full transactional support.
     * This method handles:
     * - Insert into vtiger_comments (comment data)
     * - Insert into vtiger_crmentity (metadata for permissions, soft delete, etc.)
     * - Optional: Insert into vtiger_seattachmentsrel (if attachment exists)
     * - Auto-generation of comment ID (max + 1)
     * - Timestamp management (createdtime, modifiedtime)
     * 
     * Prerequisites (validated by UseCase before calling):
     * - Content is not empty and within length limits
     * - Related record exists and is accessible
     * - User has permission to comment on the related record
     * - Parent comment exists (if this is a reply)
     * - Attachment filename is valid (if provided)
     * 
     * @param CreateCommentRequest $request Validated data transfer object with comment creation data
     * 
     * @return int The unique identifier (commentid) of the newly created comment
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If request data is invalid
     * @throws \DomainException If business rules are violated (e.g., duplicate detection)
     * 
     * @example
     * // Create a top-level comment
     * $request = new CreateCommentRequest(
     *     taskId: 123,
     *     content: "Task completed successfully!",
     *     userId: 456,
     *     parentCommentId: null,
     *     attachment: null
     * );
     * $commentId = $repository->create($request);
     * 
     * @example
     * // Create a threaded reply with attachment
     * $request = new CreateCommentRequest(
     *     taskId: 123,
     *     content: "See attached document",
     *     userId: 456,
     *     parentCommentId: 789,
     *     attachment: "proposal.pdf"
     * );
     * $replyId = $repository->create($request);
     */
    public function create(CreateCommentRequest $request): int;

    /**
     * Update an existing comment
     * 
     * Updates comment content and metadata with audit trail support.
     * Only the original author can update their own comments (enforced by UseCase).
     * 
     * Updatable fields:
     * - commentcontent: Main text content
     * - reasontoedit: Reason for editing (audit trail)
     * - is_private: Visibility flag
     * - filename: Attachment reference
     * - modifiedtime: Auto-updated on every change
     * 
     * Non-updatable fields (immutable after creation):
     * - commentid, related_to, userid, createdtime, parent_comments
     * 
     * @param int $commentId Unique identifier of the comment to update
     * @param array<string, mixed> $data Associative array with fields to update
     *        Supported keys: 'content', 'reason_to_edit', 'is_private', 'attachment'
     * 
     * @return bool True if update was successful, false if comment not found or no changes made
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If commentId is invalid or data contains invalid keys
     * @throws \DomainException If user is not authorized to update this comment
     * 
     * @example
     * // Update comment content
     * $success = $repository->update(456, [
     *     'content' => 'Updated comment text',
     *     'reason_to_edit' => 'Fixed typo'
     * ]);
     * 
     * @example
     * // Mark comment as private
     * $success = $repository->update(456, [
     *     'is_private' => 1
     * ]);
     * 
     * @example
     * // In a use case with authorization check
     * $comment = $this->repository->findById($commentId);
     * if (!$comment->canBeEditedBy($userId)) {
     *     throw new AuthorizationException('Cannot edit this comment');
     * }
     * $this->repository->update($commentId, ['content' => $newContent]);
     */
    public function update(int $commentId, array $data): bool;

    /**
     * Delete a comment (soft delete)
     * 
     * Marks the comment as deleted in vtiger_crmentity.deleted flag.
     * Soft delete preserves data for audit trail and potential restoration.
     * 
     * @param int $commentId Unique identifier of the comment to delete
     * @return bool True if deletion was successful, false if not found or already deleted
     * 
     * @throws RuntimeException If database operation fails
     */
    public function delete(int $commentId): bool;

    /**
     * Permanently delete soft-deleted comments older than specified days
     * 
     * Used by scheduled jobs to clean up old deleted comments and free storage.
     * This operation is irreversible.
     * 
     * @param int $olderThanDays Only delete comments soft-deleted more than this many days ago
     * @return int Number of comments permanently deleted
     * 
     * @throws RuntimeException If database operation fails
     */
    public function deletePermanently(int $olderThanDays): int;

    /**
     * Restore a soft-deleted comment
     * 
     * Reverses a soft delete, making the comment visible again.
     * 
     * @param int $commentId Unique identifier of the comment to restore
     * @return bool True if restore was successful, false if not found or not deleted
     * 
     * @throws RuntimeException If database operation fails
     */
    public function restore(int $commentId): bool;

    /**
     * Find a comment by ID, including soft-deleted ones
     * 
     * Used for restore operations and admin audits.
     * Bypasses the deleted = 0 filter.
     * 
     * @param int $commentId Unique identifier of the comment
     * @return Comment|null Comment entity if found, null otherwise
     */
    public function findByIdIncludingDeleted(int $commentId): ?Comment;

    /**
     * Count total comments for a related record
     * 
     * Returns the total number of non-deleted comments for pagination purposes.
     * This is a lightweight query (COUNT only) without loading full entities.
     * 
     * @param int $relatedId ID of the related record
     * @param string $module Module type of the related record
     * 
     * @return int Total number of comments (excluding deleted)
     * 
     * @throws \RuntimeException If database query fails
     */
    public function countByRelatedId(int $relatedId, string $module): int;

    /**
     * Get comments by user ID with pagination
     * 
     * Retrieves all comments created by a specific user across all modules.
     * Useful for user activity reports and audit logs.
     * 
     * @param int $userId ID of the user whose comments to retrieve
     * @param int $page Current page number (1-based)
     * @param int $perPage Number of items per page
     * 
     * @return LengthAwarePaginator Paginated collection of Comment entities
     * 
     * @throws \RuntimeException If database query fails
     */
    public function findByUserId(int $userId, int $page = 1, int $perPage = 50): LengthAwarePaginator;

    /**
     * Check if a user can access a specific comment
     * 
     * Validates visibility permissions based on:
     * - Comment privacy setting (is_private flag)
     * - User type (internal vs customer portal)
     * - User relationship to the related record
     * 
     * @param int $commentId ID of the comment to check
     * @param int $userId ID of the user requesting access
     * 
     * @return bool True if user can view the comment, false otherwise
     * 
     * @throws \RuntimeException If database query fails
     */
    public function canAccess(int $commentId, int $userId): bool;
}