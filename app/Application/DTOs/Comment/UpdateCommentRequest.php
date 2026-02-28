<?php

namespace App\Application\DTOs\Comment;

/**
 * Update Comment Request DTO
 * 
 * Data transfer object for updating an existing comment.
 * Contains only the fields that can be modified, all optional
 * to support partial updates (PATCH semantics).
 * 
 * Validation rules:
 * - At least one field must be provided for update
 * - Content must not exceed 65,000 characters if provided
 * - Reason for edit is optional but recommended for audit trail
 * - Attachment filename must be valid if provided
 * 
 * @package App\Application\DTOs
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Comment\UpdateCommentUseCase
 * @see \App\Domain\Entities\Comment
 */
class UpdateCommentRequest
{
    /**
     * New comment content (optional)
     * 
     * If provided, must be non-empty and within length limits.
     * Maximum: 65,000 characters (Vtiger TEXT field limit).
     * 
     * @var string|null
     */
    public readonly ?string $content;

    /**
     * Reason for editing the comment (optional)
     * 
     * Stored in reasontoedit field for audit trail.
     * Recommended to provide when content is modified.
     * 
     * @var string|null
     */
    public readonly ?string $reasonToEdit;

    /**
     * New visibility setting (optional)
     * 
     * true = private (internal only), false = public (visible to customers).
     * Only affects comments in modules with customer portal support.
     * 
     * @var bool|null
     */
    public readonly ?bool $isPrivate;

    /**
     * New attachment filename (optional)
     * 
     * References a file in vtiger_attachments.
     * Must be a valid filename without path traversal characters.
     * 
     * @var string|null
     */
    public readonly ?string $attachment;

    /**
     * Constructor for UpdateCommentRequest
     * 
     * @param string|null $content New comment content (optional)
     * @param string|null $reasonToEdit Reason for editing (optional, for audit)
     * @param bool|null $isPrivate New visibility setting (optional)
     * @param string|null $attachment New attachment filename (optional)
     * 
     * @throws \InvalidArgumentException If no fields are provided for update
     */
    public function __construct(
        ?string $content = null,
        ?string $reasonToEdit = null,
        ?bool $isPrivate = null,
        ?string $attachment = null,
    ) {
        // Validate that at least one field is provided for update
        if ($content === null && $reasonToEdit === null && $isPrivate === null && $attachment === null) {
            throw new \InvalidArgumentException('At least one field must be provided for comment update');
        }

        // Validate content length if provided
        if ($content !== null && mb_strlen($content, 'UTF-8') > 65000) {
            throw new \InvalidArgumentException('Comment content exceeds maximum length of 65,000 characters');
        }

        // Validate attachment filename if provided
        if ($attachment !== null) {
            $this->validateAttachment($attachment);
        }

        $this->content = $content;
        $this->reasonToEdit = $reasonToEdit;
        $this->isPrivate = $isPrivate;
        $this->attachment = $attachment;
    }

    /**
     * Validate attachment filename
     * 
     * @param string $filename Filename to validate
     * @return void
     * @throws \InvalidArgumentException If filename is invalid
     */
    private function validateAttachment(string $filename): void
    {
        $filename = trim($filename);
        
        if ($filename === '') {
            throw new \InvalidArgumentException('Attachment filename cannot be empty');
        }

        // Prevent path traversal and injection attacks
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new \InvalidArgumentException('Invalid attachment filename');
        }

        // Optional: Validate file extension against allowed list
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!empty($extension) && !in_array(strtolower($extension), $allowedExtensions, true)) {
            throw new \InvalidArgumentException(
                "File extension '.{$extension}' is not allowed for attachments"
            );
        }
    }

    /**
     * Check if content field should be updated
     */
    public function shouldUpdateContent(): bool
    {
        return $this->content !== null;
    }

    /**
     * Check if visibility should be updated
     */
    public function shouldUpdatePrivacy(): bool
    {
        return $this->isPrivate !== null;
    }

    /**
     * Check if attachment should be updated
     */
    public function shouldUpdateAttachment(): bool
    {
        return $this->attachment !== null;
    }

    /**
     * Convert to array for repository layer
     * 
     * @return array<string, mixed> Associative array with fields to update
     */
    public function toUpdateArray(): array
    {
        $data = [];
        
        if ($this->shouldUpdateContent()) {
            $data['content'] = $this->content;
        }
        if ($this->reasonToEdit !== null) {
            $data['reason_to_edit'] = $this->reasonToEdit;
        }
        if ($this->shouldUpdatePrivacy()) {
            $data['is_private'] = $this->isPrivate;
        }
        if ($this->shouldUpdateAttachment()) {
            $data['attachment'] = $this->attachment;
        }
        
        return $data;
    }
}