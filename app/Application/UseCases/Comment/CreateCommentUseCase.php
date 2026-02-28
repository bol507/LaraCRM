<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Domain\Entities\Comment;
use InvalidArgumentException;
use DomainException;

/**
 * Create Comment Use Case
 * 
 * Orchestrates the creation of a new comment in the system.
 * 
 * Responsibilities:
 * - Validate input data against business rules
 * - Ensure user has permission to comment on the related record
 * - Delegate persistence to the repository layer
 * - Return the ID of the newly created comment
 * 
 * This use case is part of the Application layer and should not contain
 * infrastructure-specific logic (SQL, HTTP, file I/O, etc.).
 * 
 * @package App\Application\UseCases\Comment
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\DTOs\CreateCommentRequest
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Http\Controllers\Api\CommentController
 */
class CreateCommentUseCase
{
    /**
     * Comment repository for persistence operations
     * 
     * @var CommentRepositoryInterface
     */
    protected readonly CommentRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param CommentRepositoryInterface $repository Repository for comment persistence
     */
    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the create comment use case
     * 
     * Creates a new comment after validating business rules:
     * - Content must not be empty or exceed maximum length
     * - Related task/record must exist and be accessible
     * - User must have permission to comment on the related record
     * - Attachment filename (if provided) must be valid
     * 
     * @param CreateCommentRequest $request Data transfer object with comment creation data
     * @return int The unique identifier of the newly created comment
     * 
     * @throws InvalidArgumentException If input data fails validation rules
     * @throws DomainException If business rules are violated (e.g., insufficient permissions)
     * @throws \RuntimeException If repository operation fails
     * 
     * @example
     * // In a controller:
     * $request = new CreateCommentRequest(
     *     taskId: 123,
     *     content: "Task completed successfully!",
     *     userId: 456,
     *     parentCommentId: null,
     *     attachment: null
     * );
     * $commentId = $createCommentUseCase->execute($request);
     * 
     * @example
     * // Creating a threaded reply:
     * $request = new CreateCommentRequest(
     *     taskId: 123,
     *     content: "Thanks for the update!",
     *     userId: 456,
     *     parentCommentId: 789, // Reply to comment #789
     *     attachment: null
     * );
     * $replyId = $createCommentUseCase->execute($request);
     */
    public function execute(CreateCommentRequest $request): int
    {
        // ✅ Validate request data (domain-level validation)
        $this->validateRequest($request);

        // ✅ Business rule: Verify user can comment on this task/record
        $this->verifyCommentPermission($request->taskId, $request->userId);

        // ✅ Business rule: If replying, verify parent comment exists and is accessible
        if ($request->parentCommentId !== null) {
            $this->verifyParentComment($request->parentCommentId, $request->userId);
        }

        // ✅ Business rule: Validate attachment filename if provided
        if ($request->attachment !== null) {
            $this->validateAttachment($request->attachment);
        }

        // ✅ Delegate persistence to repository layer
        // The repository will map the DTO to a Comment entity and persist it
        return $this->repository->create($request);
    }

    /**
     * Validate the create comment request data
     * 
     * Performs domain-level validation that is independent of framework
     * validation rules. Repository-level validation may add additional checks.
     * 
     * @param CreateCommentRequest $request Request to validate
     * @return void
     * 
     * @throws InvalidArgumentException If any validation rule fails
     */
    protected function validateRequest(CreateCommentRequest $request): void
    {
        // Content cannot be empty or whitespace-only
        if (trim($request->content) === '') {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        // Content length limit (Vtiger TEXT field: ~65,535 bytes, UTF-8 safe: 65,000 chars)
        if (mb_strlen($request->content, 'UTF-8') > 65000) {
            throw new InvalidArgumentException(
                'Comment content exceeds maximum length of 65,000 characters'
            );
        }

        // Task/record ID must be positive
        if ($request->taskId <= 0) {
            throw new InvalidArgumentException('Related task ID must be a positive integer');
        }

        // User ID must be positive (0 is reserved for anonymous, handled separately)
        if ($request->userId < 0) {
            throw new InvalidArgumentException('User ID cannot be negative');
        }

        // Parent comment ID, if provided, must be positive
        if ($request->parentCommentId !== null && $request->parentCommentId <= 0) {
            throw new InvalidArgumentException('Parent comment ID must be a positive integer');
        }
    }

    /**
     * Verify that the user has permission to comment on the related record
     * 
     * Business rules:
     * - Internal users can comment on any record they have access to
     * - Customer portal users can only comment on records they own or are assigned to
     * - Deleted or archived records cannot receive new comments
     * 
     * @param int $taskId ID of the related task/record
     * @param int $userId ID of the user attempting to create the comment
     * @return void
     * 
     * @throws DomainException If user lacks permission to comment
     * @throws \RuntimeException If related record cannot be verified
     */
    protected function verifyCommentPermission(int $taskId, int $userId): void
    {
        // TODO: Implement permission check via repository or authorization service
        // Example implementation:
        // $task = $this->taskRepository->findById($taskId);
        // if (!$task) {
        //     throw new DomainException('Cannot comment on non-existent task');
        // }
        // if (!$task->canBeCommentedBy($userId)) {
        //     throw new DomainException('User does not have permission to comment on this task');
        // }
        
        // For now, assume permission is granted (implement based on your auth system)
    }

    /**
     * Verify that the parent comment exists and is accessible for threading
     * 
     * Business rules:
     * - Parent comment must exist and not be deleted
     * - User must have visibility permission for the parent comment
     * - Threading depth limit (optional): prevent excessively nested replies
     * 
     * @param int $parentCommentId ID of the parent comment being replied to
     * @param int $userId ID of the user attempting to create the reply
     * @return void
     * 
     * @throws DomainException If parent comment is invalid or inaccessible
     */
    protected function verifyParentComment(int $parentCommentId, int $userId): void
    {
        // TODO: Implement parent comment verification
        // Example:
        // $parent = $this->repository->findById($parentCommentId);
        // if (!$parent) {
        //     throw new DomainException('Cannot reply to non-existent comment');
        // }
        // if (!$parent->isVisibleTo($userId, $this->isInternalUser($userId))) {
        //     throw new DomainException('Cannot reply to private comment');
        // }
        // if ($this->getThreadingDepth($parentCommentId) >= 10) {
        //     throw new DomainException('Maximum reply depth exceeded');
        // }
    }

    /**
     * Validate attachment filename if provided
     * 
     * Business rules:
     * - Filename must not be empty
     * - Filename must not contain path traversal characters
     * - File extension must be in allowed list (if extension-based validation is used)
     * 
     * @param string $filename Filename to validate
     * @return void
     * 
     * @throws InvalidArgumentException If filename fails validation
     */
    protected function validateAttachment(string $filename): void
    {
        $filename = trim($filename);
        
        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename cannot be empty');
        }

        // Prevent path traversal attacks
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            throw new InvalidArgumentException('Invalid attachment filename');
        }

        // Optional: Validate file extension against allowed list
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!empty($extension) && !in_array(strtolower($extension), $allowedExtensions, true)) {
            throw new InvalidArgumentException(
                "File extension '.{$extension}' is not allowed for attachments"
            );
        }
    }

    /**
     * Check if a user is an internal CRM user (vs. customer portal user)
     * 
     * @param int $userId User ID to check
     * @return bool True if internal user, false if customer portal user
     * 
     * @internal Used for permission checks; implementation depends on auth system
     */
    protected function isInternalUser(int $userId): bool
    {
        // TODO: Implement based on your authentication/authorization system
        // Example: Check if user exists in vtiger_users table
        // return $this->userRepository->isInternalUser($userId);
        
        // Default assumption: all authenticated users are internal
        return true;
    }
}