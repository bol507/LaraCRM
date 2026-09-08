<?php

namespace App\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Application\ValueObjects\Comment\CommentModule;
use App\Domain\Entities\Comment;
use App\Domain\Exceptions\Comment\CommentTargetNotFoundException;
use App\Infrastructure\Repositories\CommentRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Use case for creating a new comment
 */
class CreateCommentUseCase
{
    
    private const ENTITY_SETYPE = 'Calendar';

    public function __construct(
        private readonly CreateEntityUseCase $createEntity,
        private readonly CommentRepository $comment,
    ) {}

    /**
     * Execute the creation comment use case
     *
     * @param CreateCommentRequest $request
     * @return Comment
     * @throws ValidationException
     * @throws \Throwable
     */
    public function execute(CreateCommentRequest $request): Comment
    {
        $this->validateBusinessRules($request);
        $this->validateRelatedRecord($request);

        $userId = $request->userId ?? CurrentUserService::idOr(1);
        $now = now()->format('Y-m-d H:i:s');

        $entity = DB::connection('vtiger')->transaction(function () use ($request, $userId, $now) {
            // 1. Create vtiger_crmentity via generic UseCase → returns crmid
            $commentId = $this->createEntity->execute(
                [
                    'label' => $this->generateLabel($request->content),
                    'description' => $this->generateDescription($request->content),
                    'smownerid' => $userId,
                    'smcreatorid' => $userId,
                    // createdtime/modifiedtime are handled internally by CreateEntityUseCase
                ],
                setype: self::ENTITY_SETYPE,
                userId: $userId
            // crmId: null → let it be generated automatically
            );

            // 2. Create vtiger_modcomments with the generated ID
            $this->comment->insert([
                'modcommentsid' => $commentId,
                'commentcontent' => $request->content,
                'related_to' => $request->relatedId,
                'parent_comments' => $request->parentCommentId,
                'userid' => $userId,
                'is_private' => $request->isPrivate ? '1' : '0',
                'filename' => $request->attachment,
                'createdtime' => $now,      // If your table requires it
                'modifiedtime' => $now,
            ]);

            return new Comment(
                commentid: $commentId,
                commentcontent: $request->content,
                related_to: $request->relatedId,
                parent_comments: $request->parentCommentId,
                customer: null,              // Not used in creation
                userid: $userId,
                reasontoedit: null,
                is_private: $request->isPrivate ? 1 : 0,
                filename: $request->attachment,
                related_email_id: null,
            );
        });

        // Register activity
        $this->logActivity($entity->getId(), $userId);

        return $entity;
    }

    /**
     * Validate domain-specific business rules
     *
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    private function validateBusinessRules(CreateCommentRequest $request): void
    {
        if (trim($request->content) === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment content cannot be empty'],
            ]);
        }

        if (mb_strlen($request->content) > 65000) {
            throw ValidationException::withMessages([
                'content' => ['Comment exceeds maximum allowed length'],
            ]);
        }

        $allowedModules = CommentModule::all();
        if (! CommentModule::isSupported($request->module)) {
            throw new InvalidArgumentException(
                "Module '{$request->module}' not allowed for comments. " .
                'Valid modules: ' . implode(', ', $allowedModules)
            );
        }

        if ($request->relatedId <= 0) {
            throw new InvalidArgumentException('Related record ID must be positive');
        }

        if ($request->userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        if ($request->parentCommentId !== null && $request->parentCommentId <= 0) {
            throw new InvalidArgumentException('Parent comment ID must be positive');
        }
    }

    /**
     * Validate that the related record exists and matches the given module.
     *
     * @throws CommentTargetNotFoundException If the record does not exist or is deleted
     * @throws InvalidArgumentException If the record exists but belongs to another module
     */
    private function validateRelatedRecord(CreateCommentRequest $request): void
    {
        $recordSetype = $this->comment->relatedRecordSetype($request->relatedId);

        if ($recordSetype === null) {
            throw CommentTargetNotFoundException::for($request->module, $request->relatedId);
        }

        $expectedSetype = CommentModule::toSetype($request->module);
        if ($recordSetype !== $expectedSetype) {
            throw new InvalidArgumentException(
                "Related record {$request->relatedId} is a {$recordSetype}, " .
                "not a {$request->module}. Cannot attach a comment to a different module."
            );
        }
    }

    /**
     * Generate a label for the comment entity
     *
     * @param string $content The comment content
     * @return string Truncated label (max 255 characters)
     */
    private function generateLabel(string $content): string
    {
        $label = trim($content);
        if (strlen($label) > 255) {
            $label = substr($label, 0, 252) . '...';
        }
        return $label ?: 'Comment';
    }

    /**
     * Generate a description from the comment content
     *
     * @param string $content The comment content
     * @return string
     */
    private function generateDescription(string $content): string
    {
        return trim($content);
    }

    private function logActivity(int $commentId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'ModComments',
                crmid: $commentId,
                userId: $userId
            );
        } catch (\Exception $e) {
            // Silently fail - activity logging is non-critical
        }
    }
}
