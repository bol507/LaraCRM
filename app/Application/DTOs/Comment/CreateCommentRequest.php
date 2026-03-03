<?php

namespace App\Application\DTOs\Comment;

/**
 * Data Transfer Object for creating a new comment
 * 
 * This DTO carries validated data from the presentation layer
 * to the application layer (UseCase).
 * 
 * @package App\Application\DTOs\Comment
 */
class CreateCommentRequest
{
    /**
     * @param string $module Module type (e.g., 'Project', 'Quotes')
     * @param int $relatedId ID of the related record
     * @param string $content Comment text content
     * @param int $userId ID of the authenticated user creating the comment
     * @param int|null $parentCommentId ID of parent comment (for threaded replies)
     * @param bool|null $isPrivate Visibility flag
     * @param string|null $attachment Optional attachment filename
     */
    public function __construct(
        public readonly string $module,
        public readonly int $relatedId,
        public readonly string $content,
        public readonly int $userId,
        public readonly ?int $parentCommentId = null,
        public readonly ?bool $isPrivate = null,
        public readonly ?string $attachment = null,
    ) {}

    /**
     * Create a CreateCommentRequest from validated request data
     * 
     * @param array<string, mixed> $data Validated data from HTTP request
     * @return self
     */
    public static function fromValidatedData(array $data): self
    {
        return new self(
            module: $data['module'],
            relatedId: (int) $data['related_id'],
            content: $data['content'],
            userId: (int) $data['user_id'],
            parentCommentId: isset($data['parent_comment_id']) 
                ? (int) $data['parent_comment_id'] 
                : null,
            isPrivate: $data['is_private'] ?? null,
            attachment: $data['attachment'] ?? null,
        );
    }
}