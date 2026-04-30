<?php

namespace App\Application\UseCases\Comment;

use App\Infrastructure\Repositories\CommentRepository;
use App\Services\VtigerActivityTracker;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UpdateCommentUseCase
{
    public function __construct(
        private readonly CommentRepository $comment,
    ) {}

    /**
     * Execute the update comment use case
     *
     * DML orchestration:
     * 1. Validate that the comment exists and the user has permissions
     * 2. Update vtiger_modcomments (content + reason to edit)
     *
     * @param  int  $commentId  Unique identifier of the comment to update
     * @param  int  $authenticatedUserId  ID of the user attempting the update
     * @param  string  $content  New comment content
     * @param  string|null  $reasonToEdit  Optional reason for editing (audit trail)
     * @param  string|null  $module  Module for activity logging
     * @param  int|null  $relatedId  Related record ID for activity logging
     * @return bool True if update was successful
     *
     * @throws InvalidArgumentException If parameters are invalid
     * @throws DomainException If user is not authorized to edit
     * @throws RuntimeException If persistence operation fails
     */
    public function execute(
        int $commentId,
        int $authenticatedUserId,
        string $content,
        ?string $reasonToEdit = null,
        ?string $module = null,
        ?int $relatedId = null
    ): bool {
        // Validate content
        if (trim($content) === '') {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        if (mb_strlen($content) > 65000) {
            throw new InvalidArgumentException('Comment exceeds maximum allowed length');
        }

        // Validate that the comment exists
        $existingComment = $this->comment->findById($commentId);
        if (! $existingComment) {
            throw new InvalidArgumentException('Comment not found');
        }

        // Validate that the user is the author
        $authorId = (int) ($existingComment->userid ?? 0);
        if ($authorId !== $authenticatedUserId) {
            throw new DomainException('Only the author can edit this comment');
        }

        // Update in transaction
        $success = DB::connection('vtiger')->transaction(function () use ($commentId, $content, $reasonToEdit) {
            // 1. Update vtiger_modcomments
            $succcess =$this->comment->updateComment($commentId, [
                'commentcontent' => $content,
                'reasontoedit' => $reasonToEdit,
            ]);
            return $succcess;
        });

        // Log activity if module and relatedId are provided
        if ($module && $relatedId) {
            $this->logActivity($commentId, $authenticatedUserId, $module, $relatedId, $reasonToEdit);
        }

        return $success;
    }

    private function logActivity(
        int $commentId,
        int $userId,
        string $module,
        int $relatedId,
        ?string $reason
    ): void {
        try {
            VtigerActivityTracker::updated(
                module: $module,
                crmid: $relatedId,
                userId: $userId
            );
        } catch (\Exception $e) {
            // Silently fail - activity logging is non-critical
        }
    }
}
