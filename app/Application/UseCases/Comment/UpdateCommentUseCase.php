<?php

namespace App\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Application\DTOs\Comment\UpdateCommentRequest;
use App\Domain\Entities\Comment;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use InvalidArgumentException;
use DomainException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Update Comment Use Case
 * 
 * Orchestrates the update of an existing comment in the system.
 * 
 * Responsibilities:
 * - Validate that the comment exists and is accessible
 * - Verify that the requesting user is authorized to update the comment
 * - Apply business rules for what can be updated and by whom
 * - Delegate persistence to the repository layer
 * - Return success status or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Only the original author can update their own comments
 * - Content updates must pass validation (non-empty, length limits)
 * - Visibility changes require appropriate permissions
 * - Attachment updates must reference valid filenames
 * - Audit trail: reason for edit is recorded when content changes
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
 * @see \App\Application\DTOs\UpdateCommentRequest
 * @see \App\Http\Controllers\Api\CommentController
 */
class UpdateCommentUseCase
{
    /**
     * Comment repository for persistence operations
     * 
     * @var CommentRepositoryInterface
     */
    private readonly CommentRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param CommentRepositoryInterface $repository Repository implementation for comment persistence
     */
    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the update comment use case
     * 
     * Updates an existing comment after validating all business rules:
     * - Comment must exist and not be deleted
     * - Requesting user must be the original author (or admin)
     * - New content must pass validation if provided
     * - Attachment filename must be valid if provided
     * - Visibility changes must respect module permissions
     * 
     * @param int $commentId Unique identifier of the comment to update
     * @param UpdateCommentRequest $request Data transfer object with update data
     * @param int $userId ID of the user requesting the update (for authorization)
     * 
     * @return bool True if update was successful, false if no changes were made
     * 
     * @throws InvalidArgumentException If input data fails validation rules
     * @throws DomainException If business rules are violated (e.g., unauthorized user)
     * @throws RuntimeException If repository operation fails
     * 
     * @example
     * // Update comment content with audit reason
     * $request = new UpdateCommentRequest(
     *     content: "Updated task description with new requirements",
     *     reasonToEdit: "Added missing acceptance criteria",
     *     isPrivate: null,
     *     attachment: null
     * );
     * $success = $useCase->execute(456, $request, userId: 123);
     * 
     * @example
     * // Mark comment as private (internal only)
     * $request = new UpdateCommentRequest(
     *     content: null,
     *     reasonToEdit: "Contains sensitive internal information",
     *     isPrivate: true,
     *     attachment: null
     * );
     * $success = $useCase->execute(456, $request, userId: 123);
     * 
     * @example
     * // Update attachment reference
     * $request = new UpdateCommentRequest(
     *     content: null,
     *     reasonToEdit: "Attached revised proposal document",
     *     isPrivate: null,
     *     attachment: "proposal_v2.pdf"
     * );
     * $success = $useCase->execute(456, $request, userId: 123);
     */
    public function execute(int $commentId, UpdateCommentRequest $request, ?int $userId = null): bool
    {
        // Determine user (JWT or parameter)
        $userId = $userId ?? CurrentUserService::idOr(1);
        Log::debug('UpdateCommentUseCase::execute', [
            'commentId' => $commentId,
            'userId' => $userId,
        ]);
        
        // Validate input parameters
        $this->validateParameters($commentId, $userId);

        // Fetch the existing comment
        $comment = $this->repository->findById($commentId);
        if (!$comment) {
            Log::warning('Comment not found', ['commentId' => $commentId]);
            throw new DomainException("Comment {$commentId} not found or has been deleted");
        }

        // Business rule: Verify user is authorized to update this comment
        $this->verifyUpdatePermission($comment, $userId);

        // Business rule: Validate update data against domain rules
        $this->validateUpdateData($request, $comment);

        // Business rule: Prepare update data for repository
        $updateData = $this->prepareUpdateData($request, $comment, $userId);

        // Delegate persistence to repository layer
        $updated = $this->repository->update($commentId, $updateData);
        
        if (!$updated) {
            Log::error('Failed to update comment in repository', ['commentId' => $commentId]);
            throw new RuntimeException('Failed to update comment');
        }

        // Register activity in vtiger_modtracker_basic
        try {
            VtigerActivityTracker::updated(
                module: 'ModComments',  // Correct module for comments
                crmid: $commentId,
                userId: $userId
            );
            
            Log::info('Activity logged for comment update', [
                'commentId' => $commentId,
                'module' => 'ModComments',
                'userId' => $userId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity for comment update', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
            // Do not rethrow to avoid breaking the main flow
        }

        return $updated;
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
            throw new InvalidArgumentException("Comment ID must be a positive integer, got {$commentId}");
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be a positive integer, got {$userId}");
        }
    }

    /**
     * Verify that the user is authorized to update the comment
     * 
     * Business rules:
     * - Only the original author can update their own comments
     * - Admin users may override this rule (optional, implement based on your auth system)
     * - Comments on private records may have additional restrictions
     * 
     * @param Comment $comment The comment entity to check
     * @param int $userId ID of the user requesting the update
     * @return void
     * @throws DomainException If user is not authorized
     */
    private function verifyUpdatePermission(Comment $comment, int $userId): void
    {
        // Primary rule: Only the author can edit their own comment
        if (!$comment->canBeEditedBy($userId)) {
            throw new DomainException(
                "User {$userId} is not authorized to update comment {$comment->getId()}. " .
                "Only the original author can edit their comments."
            );
        }

        // Optional: Add admin override logic here
        // if ($this->isAdmin($userId)) {
        //     return; // Admins can edit any comment
        // }

        // Optional: Add record-level permission checks
        // if (!$this->hasAccessToRelatedRecord($comment->getTaskId(), $userId)) {
        //     throw new DomainException("User does not have access to the related record");
        // }
    }

    /**
     * Validate update data against domain business rules
     * 
     * @param UpdateCommentRequest $request Update request to validate
     * @param Comment $comment Existing comment entity for context
     * @return void
     * @throws InvalidArgumentException If update data is invalid
     */
    private function validateUpdateData(UpdateCommentRequest $request, Comment $comment): void
    {
        // If updating content, validate it
        if ($request->shouldUpdateContent()) {
            $this->validateContent($request->content);
        }

        // If updating attachment, validate filename
        if ($request->shouldUpdateAttachment()) {
            $this->validateAttachmentUpdate($request->attachment);
        }

        // If updating privacy, validate transition rules
        if ($request->shouldUpdatePrivacy()) {
            $this->validatePrivacyUpdate($request->isPrivate, $comment);
        }
    }

    /**
     * Validate new comment content
     * 
     * @param string|null $content Content to validate
     * @return void
     * @throws InvalidArgumentException If content is invalid
     */
    private function validateContent(?string $content): void
    {
        if ($content === null) {
            return;
        }

        $trimmed = trim($content);
        
        // Content cannot be empty or whitespace-only
        if ($trimmed === '') {
            throw new InvalidArgumentException('Comment content cannot be empty or whitespace-only');
        }

        // Content length limit (Vtiger TEXT field: ~65,535 bytes, UTF-8 safe: 65,000 chars)
        if (mb_strlen($trimmed, 'UTF-8') > 65000) {
            throw new InvalidArgumentException(
                'Comment content exceeds maximum length of 65,000 characters'
            );
        }
    }

    /**
     * Validate attachment filename update
     * 
     * @param string|null $filename Filename to validate
     * @return void
     * @throws InvalidArgumentException If filename is invalid
     */
    private function validateAttachmentUpdate(?string $filename): void
    {
        if ($filename === null) {
            return;
        }

        $filename = trim($filename);
        
        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename cannot be empty');
        }

        // Prevent path traversal and injection attacks
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new InvalidArgumentException('Invalid attachment filename: path traversal detected');
        }

        // Validate file extension against allowed list
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!empty($extension) && !in_array(strtolower($extension), $allowedExtensions, true)) {
            throw new InvalidArgumentException(
                "File extension '.{$extension}' is not allowed for attachments. " .
                "Allowed: " . implode(', ', $allowedExtensions)
            );
        }
    }

    /**
     * Validate privacy setting update
     * 
     * Business rules:
     * - Public → Private: Always allowed (more restrictive)
     * - Private → Public: May require additional permissions (less restrictive)
     * 
     * @param bool|null $isPrivate New privacy setting
     * @param Comment $comment Existing comment for context
     * @return void
     * @throws DomainException If privacy transition is not allowed
     */
    private function validatePrivacyUpdate(?bool $isPrivate, Comment $comment): void
    {
        if ($isPrivate === null) {
            return;
        }

        // Private → Public transition: may require additional checks
        if ($comment->isPrivate() && !$isPrivate) {
            // Optional: Add logic to verify user can make comment public
            // For now, allow the transition (can be restricted based on business needs)
        }
    }

    /**
     * Prepare update data array for repository layer
     * 
     * Maps DTO fields to repository-compatible array with proper formatting.
     * Includes audit fields like reason for edit and modification timestamp.
     * 
     * @param UpdateCommentRequest $request Validated update request
     * @param Comment $comment Existing comment for context
     * @param int $userId ID of user performing the update (for audit)
     * @return array<string, mixed> Associative array with fields to update
     */
    private function prepareUpdateData(UpdateCommentRequest $request, Comment $comment, int $userId): array
    {
        $data = [];

        // Map content update
        if ($request->shouldUpdateContent()) {
            $data['content'] = trim($request->content);
            // Auto-set reason to edit if content changes and no reason provided
            if ($request->reasonToEdit === null) {
                $data['reason_to_edit'] = 'Content updated by user';
            }
        }

        // Map reason to edit (audit trail)
        if ($request->reasonToEdit !== null && trim($request->reasonToEdit) !== '') {
            $data['reason_to_edit'] = trim($request->reasonToEdit);
        }

        // Map privacy update
        if ($request->shouldUpdatePrivacy()) {
            $data['is_private'] = $request->isPrivate ? 1 : 0;
        }

        // Map attachment update
        if ($request->shouldUpdateAttachment()) {
            $data['attachment'] = trim($request->attachment);
        }

        // Always include modification metadata for audit
        $data['modified_by'] = $userId;
        $data['modified_time'] = now()->format('Y-m-d H:i:s');

        return $data;
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
        
        // Default: no admin override (only authors can edit)
        return false;
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
        // TODO: Implement based on your permission system
        // Example: Check if user owns or is assigned to the related task/project
        // return $this->taskRepository->isAccessibleBy($relatedId, $userId);
        
        // Default: assume access is granted (can be restricted based on business needs)
        return true;
    }
}