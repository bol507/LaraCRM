<?php

namespace App\Application\Repositories;

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
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Domain\Entities\Comment
 * @see \App\Infrastructure\Repositories\VtigerCommentRepository
 */
interface CommentRepositoryInterface
{
    /**
     * Get all comments for a related record with pagination
     * 
     * @param int $relatedId ID of the related record (e.g., project ID)
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $page Current page number (1-based)
     * @param int $perPage Number of items per page
     * 
     * @return LengthAwarePaginator<Comment> Paginated collection of Comment entities
     * 
     * @throws \RuntimeException If database query fails
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
     * @param int $commentId Unique identifier of the comment
     * 
     * @return object|null Comment entity if found, null if not exists or deleted
     * 
     * @throws \RuntimeException If database query fails
     */
    public function findById(int $commentId): ?object;

    /**
     * Create a new comment
     * 
     * Persists a new comment to the database with full transactional support.
     * Handles insertion into vtiger_crmentity and vtiger_modcomments tables.
     * 
     * @param string $module Module type of the related record
     * @param int $relatedId ID of the related record
     * @param string $content Comment content/text
     * @param int $authenticatedUserId ID of the user creating the comment
     * @param int|null $parentId ID of parent comment (for threaded replies), null for top-level
     * @param bool|null $isPrivate Visibility flag (true = private, false = public)
     * 
     * @return Comment The newly created Comment entity with generated ID
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If required parameters are invalid
     */
    public function create(
        string $module,
        int $relatedId,
        string $content,
        int $authenticatedUserId,
        ?int $parentId = null,
        ?bool $isPrivate = false
    ): Comment;

    /**
     * Update an existing comment
     * 
     * @param int $commentId Unique identifier of the comment to update
     * @param int $authenticatedUserId ID of the user attempting the update
     * @param string $content New comment content
     * @param string|null $reasonToEdit Optional reason for editing (audit trail)
     * 
     * @return bool True if update was successful, false if not found or unauthorized
     * 
     * @throws \InvalidArgumentException If parameters are invalid
     * @throws \DomainException If user is not authorized to edit this comment
     */
    public function update(
        int $commentId,
        int $authenticatedUserId,
        string $content,
        ?string $reasonToEdit = null
    ): bool;

    /**
     * Delete a comment (soft delete via vtiger_crmentity.deleted flag)
     * 
     * @param int $commentId Unique identifier of the comment to delete
     * 
     * @return bool True if deletion was successful, false if not found or already deleted
     * 
     * @throws \RuntimeException If database operation fails
     */
    public function delete(int $commentId): bool;

    /**
     * Count total non-deleted comments for a related record
     * 
     * @param int $relatedId ID of the related record
     * @param string $module Module type of the related record
     * 
     * @return int Total number of comments (excluding deleted)
     */
    public function countByRelatedId(int $relatedId, string $module): int;

    /**
     * Check if a user can access a specific comment (visibility permissions)
     * 
     * @param int $commentId ID of the comment to check
     * @param int $userId ID of the user requesting access
     * 
     * @return bool True if user can view the comment, false otherwise
     */
    public function canAccess(int $commentId, int $userId): bool;

    /**
     * Find a comment including soft-deleted ones
     * 
     * @param int $commentId The comment ID to find
     * @return Comment|null The comment entity or null if not found
     */
    public function findByIdIncludingDeleted(int $commentId): ?Comment;
    
    /**
     * Restore a soft-deleted comment
     * 
     * @param int $commentId The comment ID to restore
     * @return bool True if restore was successful
     */
    public function restore(int $commentId): bool;
    
    /**
     * Permanently delete soft-deleted comments older than a retention period
     * 
     * @param int $olderThanDays Only delete comments soft-deleted more than this many days ago
     * @return int Number of comments permanently deleted
     */
    public function deletePermanently(int $olderThanDays): int;
}
