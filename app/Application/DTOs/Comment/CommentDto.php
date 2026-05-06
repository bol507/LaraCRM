<?php

namespace App\Application\DTOs\Comment;

use App\Domain\Entities\Comment;

/**
 * Comment Data Transfer Object (DTO)
 *
 * Transforms Comment domain entity into a format suitable for API responses
 * and presentation layer consumption. Provides a stable, versioned contract
 * between backend and frontend, decoupled from database schema changes.
 *
 * This DTO includes both raw data fields and computed properties for convenience:
 * - Raw fields: id, content, createdAt, etc. (direct from entity)
 * - Computed: isReply, hasAttachment, formattedCreatedAt (business logic)
 *
 * @package App\Application\DTOs
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 *
 * @property-read int $id Unique identifier for the comment
 * @property-read int $taskId ID of the related task/record
 * @property-read string $content Main text content of the comment
 * @property-read int $userId ID of user who created the comment
 * @property-read string|null $userName Display name of the author
 * @property-read string|null $userEmail Email of the author
 * @property-read string|null $createdAt Creation timestamp (YYYY-MM-DD HH:MM:SS)
 * @property-read string|null $updatedAt Last modification timestamp
 * @property-read int|null $parentCommentId ID of parent comment (for threading)
 * @property-read bool $isPrivate Whether comment is private/internal only
 * @property-read string|null $attachment Filename of attached file (if any)
 * @property-read bool $isReply Whether this is a threaded reply
 * @property-read string $authorName Display name for UI (fallback: "User")
 * @property-read string|null $formattedCreatedAt Human-readable date (DD/MM/YYYY HH:mm)
 *
 * @see \App\Domain\Entities\Comment
 * @see \App\Application\UseCases\Comment\GetTaskCommentsUseCase
 * @see \App\Http\Controllers\Api\CommentController
 */
class CommentDto
{
    /**
     * Constructor for CommentDto
     *
     * Creates an immutable DTO instance with all required fields.
     * Use `fromEntity()` factory method for conversion from domain entity.
     *
     * @param int $id Unique identifier for the comment
     * @param string $content Main text content of the comment
     * @param int $relatedTo ID of the related task/record
     * @param int|null $parentCommentId ID of parent comment for threading (optional)
     * @param int|null $customerId Customer ID associated with the comment (optional)
     * @param int|null $userId ID of user who created the comment (optional)
     * @param string|null $reasonToEdit Reason for editing (optional)
     * @param bool $isPrivate Whether comment is private/internal only
     * @param string|null $filename Filename of attached file (optional)
     * @param int|null $relatedEmailId Related email ID (optional)
     * @param string|null $userName Display name of the author (optional)
     * @param string|null $userEmail Email of the author (optional)
     * @param string|null $createdAt Creation timestamp (optional)
     * @param string|null $updatedAt Last modification timestamp (optional)
     * @param int|null $createdBy ID of user who created the entity (optional)
     * @param int|null $ownerId ID of user who owns the entity (optional)
     */
    public function __construct(
        // Comment fields
        public readonly int $id,
        public readonly string $content,
        public readonly int $relatedTo,
        public readonly ?int $parentCommentId,
        public readonly ?int $customerId,
        public readonly ?int $userId,
        public readonly ?string $reasonToEdit,
        public readonly bool $isPrivate,
        public readonly ?string $filename,
        public readonly ?int $relatedEmailId,

        // Enriched fields from vtiger_crmentity + vtiger_users
        public readonly ?string $userName = null,
        public readonly ?string $userEmail = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?int $createdBy = null,
        public readonly ?int $ownerId = null,
        public readonly ?string $relatedModule = null,
    ) {}

    /**
     * Create DTO from database row
     */
    public static function fromDatabaseRow(object $row): self
    {
        // Date helper
        $parseDateTime = fn(?string $val): ?string =>
        $val && $val !== '0000-00-00 00:00:00'
            ? (new \DateTimeImmutable($val))->format('Y-m-d H:i:s')
            : null;

        return new self(
            // Fields from vtiger_modcomments
            id: (int) $row->modcommentsid,
            content: (string) $row->commentcontent,
            relatedTo: (int) $row->related_to,
            parentCommentId: $row->parent_comments ? (int) $row->parent_comments : null,
            customerId: $row->customer ? (int) $row->customer : null,
            userId: $row->userid ? (int) $row->userid : null,
            reasonToEdit: $row->reasontoedit ?? null,
            isPrivate: (int) ($row->is_private ?? 0) === 1,
            filename: $row->filename ?? null,
            relatedEmailId: $row->related_email_id ? (int) $row->related_email_id : null,

            // Enriched fields from JOINs (for frontend)
            userName: $row->user_name ?? null,              // from vtiger_users.user_name
            userEmail: $row->user_email ?? null,            // from vtiger_users.email1
            createdAt: $parseDateTime($row->createdtime),   // from vtiger_crmentity.createdtime
            updatedAt: $parseDateTime($row->modifiedtime),  // from vtiger_crmentity.modifiedtime
            createdBy: $row->smcreatorid ? (int) $row->smcreatorid : null,
            ownerId: $row->smownerid ? (int) $row->smownerid : null,
            relatedModule: $row->related_module ?? null,
        );
    }

    /**
     * Create from Entity (when you don't need enriched data)
     */
    public static function fromEntity(Comment $comment): self
    {
        return new self(
            id: $comment->getId(),
            content: $comment->getContent(),
            relatedTo: $comment->getRelatedTo(),
            parentCommentId: $comment->getParentCommentId(),
            customerId: $comment->getCustomerId(),
            userId: $comment->getUserid(),
            reasonToEdit: $comment->getReasonToEdit(),
            isPrivate: $comment->isPrivate(),
            filename: $comment->getFilename(),
            relatedEmailId: $comment->getRelatedEmailId(),
            // Enriched fields = null when coming from Entity only
            userName: null,
            userEmail: null,
            createdAt: null,
            updatedAt: null,
            createdBy: null,
            ownerId: null,
            relatedModule: null,
        );
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,

            'relatedToId' => $this->relatedTo,
            'relatedModule' => $this->relatedModule ?? null,
            'relatedEntityType' => $this->relatedModule ?? null,

            'userId' => $this->userId,
            'userName' => $this->userName,
            'userEmail' => $this->userEmail,

            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'formattedCreatedAt' => $this->getFormattedCreatedAt(),

            'isPrivate' => $this->isPrivate,
            'isReply' => $this->parentCommentId !== null,
            'parentCommentId' => $this->parentCommentId,

            'attachment' => $this->filename,              // ← filename → attachment
            'hasAttachment' => !empty($this->filename),

            'customerId' => $this->customerId,
            'reasonToEdit' => $this->reasonToEdit,

            'filename' => $this->filename,
            'relatedEmailId' => $this->relatedEmailId,

            // Enriched fields for frontend (camelCase)


            'createdBy' => $this->createdBy,
            'ownerId' => $this->ownerId,

            'relatedEntityIcon' => $this->getRelatedEntityIcon(),

        ];
    }

    /**
     * Get icon for related entity type (matches frontend expectations)
     */
    private function getRelatedEntityIcon(): string
    {
        return match ($this->relatedModule) {
            'Project' => '📋',
            'Calendar', 'Tasks' => '✓',
            'Quotes' => '📄',
            'Accounts' => '🏢',
            'Contacts' => '👤',
            'Potentials' => '💰',
            'HelpDesk' => '🎫',
            default => '🔗',
        };
    }

    /**
     * Format creation date for UI display
     * Format: DD/MM/YYYY HH:mm
     */
    private function getFormattedCreatedAt(): ?string
    {
        if (!$this->createdAt) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($this->createdAt);
            return $date->format('d/m/Y H:i');
        } catch (\Exception) {
            return $this->createdAt;
        }
    }
}
