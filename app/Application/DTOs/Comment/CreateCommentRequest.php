<?php

namespace App\Application\DTOs\Comment;

/**
 * Create Comment Request DTO
 * 
 * Data transfer object for creating a new comment.
 * Contains only the data needed for comment creation,
 * validated and sanitized before reaching the use case.
 * 
 * @package App\Application\DTOs
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 */
class CreateCommentRequest
{
    /**
     * Constructor for CreateCommentRequest
     * 
     * @param int $taskId ID of the related task/record
     * @param string $content Main text content of the comment
     * @param int $userId ID of user creating the comment
     * @param int|null $parentCommentId ID of parent comment for threading (optional)
     * @param string|null $attachment Filename of attached file (optional)
     */
    public function __construct(
        public readonly int $taskId,
        public readonly string $content,
        public readonly int $userId,
        public readonly ?int $parentCommentId = null,
        public readonly ?string $attachment = null,
    ) {}
}