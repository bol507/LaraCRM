<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

/**
 * Delete Comment Use Case
 * 
 * Orchestrates the soft deletion of an existing comment in the system.
 * 
 * Responsibilities:
 * - Validate that the comment exists and is accessible
 * - Verify that the requesting user is authorized to delete the comment
 * - Apply business rules for deletion permissions and constraints
 * - Delegate persistence (soft delete) to the repository layer
 * - Return success status or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Only the original author can delete their own comments (by default)
 * - Admin users may override author restriction (configurable)
 * - Comments on locked/closed records may have additional restrictions
 * - Deletion is soft (vtiger_crmentity.deleted = 1) for audit trail
 * - Deleted comments are excluded from all query results
 * - Deletion is permanent after configurable retention period (optional)
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
class DeleteCommentUseCase
{
    /**
     * Comment repository for persistence operations
     * 
     * @var CommentRepositoryInterface
     */
    private readonly CommentRepositoryInterface $repository;

    /**
     * Configuration options for deletion behavior
     * 
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * Constructor with dependency injection
     * 
     * @param CommentRepositoryInterface $repository Repository implementation for comment persistence
     * @param array<string, mixed> $config Optional configuration overrides
     * 
     * @example
     * // Default configuration
     * $useCase = new DeleteCommentUseCase($repository);
     * 
     * @example
     * // Custom configuration with admin override and retention
     * $useCase = new DeleteCommentUseCase($repository, [
     *     'allow_admin_override' => true,
     *     'retention_days' => 30,
     *     'require_reason' => false,
     * ]);
     */
    public function __construct(
        CommentRepositoryInterface $repository,
        array $config = []
    ) {
        $this->repository = $repository;
        $this->config = array_merge([
            // Allow admin users to delete any comment (not just their own)
            'allow_admin_override' => false,
            // Number of days to retain soft-deleted comments before permanent deletion
            'retention_days' => null,
            // Require a reason for deletion (for audit trail)
            'require_reason' => false,
            // Prevent deletion of comments on closed/locked records
            'prevent_on_locked_records' => true,
        ], $config);
    }

    /**
     * Execute the delete comment use case
     * 
     * Soft-deletes an existing comment after validating all business rules:
     * - Comment must exist and not already be deleted
     * - Requesting user must be authorized (author or admin)
     * - Record-level permissions must allow deletion
     * - Optional: Deletion reason must be provided if configured
     * 
     * @param int $commentId Unique identifier of the comment to delete
     * @param int $userId ID of the user requesting the deletion (for authorization)
     * @param string|null $reason Optional reason for deletion (for audit trail)
     * 
     * @return bool True if deletion was successful, false if comment not found or already deleted
     * 
     * @throws InvalidArgumentException If input parameters are invalid
     * @throws DomainException If business rules are violated (e.g., unauthorized user)
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Author deletes their own comment
     * $success = $useCase->execute(commentId: 456, userId: 123);
     * 
     * @example
     * // Admin deletes any comment (if allow_admin_override is enabled)
     * $success = $useCase->execute(
     *     commentId: 456,
     *     userId: 1, // Admin user ID
     *     reason: "Spam content removed by moderator"
     * );
     * 
     * @example
     * // Handle deletion result in controller
     * try {
     *     $success = $useCase->execute($commentId, $userId);
     *     if ($success) {
     *         return response()->json(['message' => 'Comment deleted'], 200);
     *     } else {
     *         return response()->json(['error' => 'Comment not found'], 404);
     *     }
     * } catch (DomainException $e) {
     *     return response()->json(['error' => $e->getMessage()], 403);
     * }
     */
    public function execute(int $commentId, int $userId, ?string $reason = null): bool
    {
        //  Validate input parameters
        $this->validateParameters($commentId, $userId, $reason);

        //  Fetch the existing comment
        $comment = $this->repository->findById($commentId);
        if (!$comment) {
            // Return false instead of throwing for "not found" to allow idempotent deletes
            return false;
        }

        //  Business rule: Verify user is authorized to delete this comment
        $this->verifyDeletePermission($comment, $userId);

        //  Business rule: Check record-level constraints (e.g., locked records)
        $this->verifyRecordConstraints($comment);

        //  Business rule: Validate deletion reason if required by config
        if ($this->config['require_reason'] && empty(trim($reason ?? ''))) {
            throw new DomainException('A reason for deletion is required');
        }

        //  Delegate soft delete to repository layer
        return $this->repository->delete($commentId);
    }

    /**
     * Validate input parameters for the use case
     * 
     * @param int $commentId Comment ID to validate
     * @param int $userId User ID to validate
     * @param string|null $reason Deletion reason to validate (if required)
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $commentId, int $userId, ?string $reason): void
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
        if ($this->config['require_reason'] && $reason !== null && strlen(trim($reason)) > 255) {
            throw new InvalidArgumentException(
                'Deletion reason cannot exceed 255 characters'
            );
        }
    }

    /**
     * Verify that the user is authorized to delete the comment
     * 
     * Business rules:
     * - Primary: Only the original author can delete their own comments
     * - Optional: Admin users may override if allow_admin_override is enabled
     * - Optional: Record owners may delete comments on their records (configurable)
     * 
     * @param Comment $comment The comment entity to check
     * @param int $userId ID of the user requesting the deletion
     * @return void
     * @throws DomainException If user is not authorized
     */
    private function verifyDeletePermission(Comment $comment, int $userId): void
    {
        // Primary rule: Author can always delete their own comment
        if ($comment->getUserId() === $userId) {
            return;
        }

        // Optional: Admin override
        if ($this->config['allow_admin_override'] && $this->isAdmin($userId)) {
            return;
        }

        // Optional: Record owner permission (if enabled in config)
        // if ($this->config['allow_record_owner_delete'] && $this->isRecordOwner($comment, $userId)) {
        //     return;
        // }

        // If no rule matched, deny deletion
        throw new DomainException(
            "User {$userId} is not authorized to delete comment {$comment->getId()}. " .
            "Only the original author can delete their comments."
        );
    }

    /**
     * Check record-level constraints that may prevent deletion
     * 
     * Business rules:
     * - Comments on closed/locked records may be protected from deletion
     * - Comments with replies may require cascade delete or blocking
     * - Comments referenced by other systems may be protected
     * 
     * @param Comment $comment The comment entity to check
     * @return void
     * @throws DomainException If record constraints prevent deletion
     */
    private function verifyRecordConstraints(Comment $comment): void
    {
        if (!$this->config['prevent_on_locked_records']) {
            return;
        }

        // Optional: Check if related record is locked/closed
        // This would require a repository or service to check record status
        // Example implementation:
        // if ($this->isRelatedRecordLocked($comment->getTaskId())) {
        //     throw new DomainException(
        //         "Cannot delete comments on locked or closed records. " .
        //         "Please reopen the record or contact an administrator."
        //     );
        // }

        // Optional: Check for child replies (prevent orphaning)
        // if ($this->hasReplies($comment->getId())) {
        //     throw new DomainException(
        //         "Cannot delete a comment that has replies. " .
        //         "Please delete replies first or use cascade delete."
        //     );
        // }
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
        
        // Default: no admin override (only authors can delete)
        return false;
    }

    /**
     * Check if user is the owner of the related record
     * 
     * @param Comment $comment Comment to check
     * @param int $userId User ID to check
     * @return bool True if user owns the related record
     * 
     * @internal Implementation depends on your permission system
     */
    private function isRecordOwner(Comment $comment, int $userId): bool
    {
        // TODO: Implement based on your permission system
        // Example: Check if user is assigned to the related task/project
        // return $this->taskRepository->isOwnedBy($comment->getTaskId(), $userId);
        
        // Default: deny record owner delete (only authors can delete)
        return false;
    }

    /**
     * Check if a related record is locked or closed
     * 
     * @param int $relatedId ID of the related record
     * @return bool True if record is locked/closed
     * 
     * @internal Implementation depends on your business logic
     */
    private function isRelatedRecordLocked(int $relatedId): bool
    {
        // TODO: Implement based on your business logic
        // Example: Check project status, task completion, etc.
        // $task = $this->taskRepository->findById($relatedId);
        // return $task && in_array($task->getStatus(), ['Closed', 'Locked', 'Archived']);
        
        // Default: assume record is not locked
        return false;
    }

    /**
     * Check if a comment has child replies
     * 
     * @param int $commentId ID of the parent comment
     * @return bool True if comment has replies
     * 
     * @internal Implementation depends on your repository
     */
    private function hasReplies(int $commentId): bool
    {
        // TODO: Implement based on your repository
        // Example: Count comments with parent_comments = $commentId
        // return $this->repository->countReplies($commentId) > 0;
        
        // Default: assume no replies (allow delete)
        return false;
    }

    /**
     * Schedule permanent deletion of soft-deleted comments
     * 
     * This method can be called by a scheduled job to permanently remove
     * comments that have been soft-deleted beyond the retention period.
     * 
     * @param int|null $olderThanDays Only delete comments older than this many days
     * @return int Number of comments permanently deleted
     * 
     * @throws RuntimeException If permanent deletion operation fails
     * 
     * @example
     * // In a scheduled job (e.g., daily cleanup)
     * $deletedCount = $useCase->cleanupPermanently(olderThanDays: 30);
     * Log::info("Permanently deleted {$deletedCount} old comments");
     */
    public function cleanupPermanently(?int $olderThanDays = null): int
    {
        $retentionDays = $olderThanDays ?? $this->config['retention_days'];
        
        if ($retentionDays === null || $retentionDays <= 0) {
            // Retention not configured; skip permanent deletion
            return 0;
        }

        try {
            // Delegate to repository for bulk permanent deletion
            return $this->repository->deletePermanently(olderThanDays: $retentionDays);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to permanently delete old comments: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Restore a soft-deleted comment
     * 
     * Reverses a soft delete operation, making the comment visible again.
     * Only the original author or an admin can restore their deleted comments.
     * 
     * @param int $commentId ID of the comment to restore
     * @param int $userId ID of the user requesting the restore
     * @return bool True if restore was successful, false if comment not found or not deleted
     * 
     * @throws DomainException If user is not authorized to restore
     * @throws RuntimeException If restore operation fails
     * 
     * @example
     * // Author restores their accidentally deleted comment
     * $success = $useCase->restore(commentId: 456, userId: 123);
     * if ($success) {
     *     // Comment is now visible again
     * }
     */
    public function restore(int $commentId, int $userId): bool
    {
        // Validate parameters
        if ($commentId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Comment ID and User ID must be positive integers');
        }

        // Fetch comment (including soft-deleted ones)
        // Note: Repository needs a method that ignores the deleted flag
        $comment = $this->repository->findByIdIncludingDeleted($commentId);
        if (!$comment) {
            return false;
        }

        // Verify authorization (same rules as delete)
        if ($comment->getUserId() !== $userId && 
            !($this->config['allow_admin_override'] && $this->isAdmin($userId))) {
            throw new DomainException(
                "User {$userId} is not authorized to restore comment {$commentId}"
            );
        }

        // Delegate restore to repository
        return $this->repository->restore($commentId);
    }
}