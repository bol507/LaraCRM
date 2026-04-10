<?php

namespace App\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use DomainException;

/**
 * Comment Entity
 * 
 * Represents a comment in Vtiger CRM (vtiger_comments table).
 * Encapsulates business rules and provides meaningful behavior for comment management.
 * 
 * Comments can be associated with tasks, support threading via parent_comments,
 * and include visibility controls (private/public) and attachment references.
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @property-read int $commentid Unique identifier for the comment
 * @property-read string $commentcontent Main text content of the comment
 * @property-read int $related_to ID of the related record (e.g., task ID)
 * @property-read int|null $parent_comments ID of parent comment for threading
 * @property-read int|null $customer ID of customer who created the comment (if applicable)
 * @property-read int|null $userid ID of user who created the comment
 * @property-read string|null $reasontoedit Reason provided when editing the comment
 * @property-read int|null $is_private Visibility flag (1 = private, 0 = public)
 * @property-read string|null $filename Name of attached file (if any)
 * @property-read int|null $related_email_id ID of related email (if comment came from email)
 * @property-read string|null $createdtime Creation timestamp (YYYY-MM-DD HH:MM:SS)
 * @property-read string|null $modifiedtime Last modification timestamp (YYYY-MM-DD HH:MM:SS)
 * @property-read string|null $assigned_user_name Name of user assigned to related record
 * @property-read string|null $assigned_user_email Email of user assigned to related record
 * 
 * @see \App\Application\UseCases\Comment\CreateCommentUseCase
 * @see \App\Application\UseCases\Comment\GetTaskCommentsUseCase
 * @see \App\Infrastructure\Repositories\VtigerCommentRepository
 */
class Comment
{


    /**
     * Comment constructor.
     *
     * Creates an immutable Comment entity instance with all comment data.
     * All properties are marked as private readonly to ensure immutability
     * after instantiation, promoting safer data handling and preventing
     * accidental modifications.
     *
     * @param  int  $commentid  The unique identifier of the comment.
     * @param  string  $commentcontent  The content/text of the comment.
     * @param  int  $related_to  The ID of the related record (task, project, etc.).
     * @param  int|null  $parent_comments  The ID of the parent comment if this is a reply.
     * @param  int|null  $customer  The customer ID associated with the comment.
     * @param  int|null  $userid  The ID of the user who created the comment.
     * @param  string|null  $reasontoedit  Reason for editing the comment (if applicable).
     * @param  int|null  $is_private  Flag indicating if the comment is private (1) or public (0).
     * @param  string|null  $filename  Attachment filename if the comment has an attachment.
     * @param  int|null  $related_email_id  Related email ID if associated with an email.
     * @param  string|null  $createdtime  Timestamp when the comment was created.
     * @param  string|null  $modifiedtime  Timestamp when the comment was last modified.
     * @param  string|null  $assigned_user_name  Name of the user who created the comment.
     * @param  string|null  $assigned_user_email  Email of the user who created the comment.
     * @param  string|null  $relatedModule  The module type of the related record (e.g., 'Tasks', 'Project').
     * @param  string|null  $userName  Alternative user name field (for compatibility).
     *
     * @throws InvalidArgumentException If commentid, content, or related_to are invalid
     */
    public function __construct(
        private readonly int $commentid,
        private readonly string $commentcontent,
        private readonly int $related_to,
        private readonly ?int $parent_comments = null,
        private readonly ?int $customer = null,
        private readonly ?int $userid = null,
        private readonly ?string $reasontoedit = null,
        private readonly ?int $is_private = null,
        private readonly ?string $filename = null,
        private readonly ?int $related_email_id = null,
        private readonly ?string $createdtime = null,
        private readonly ?string $modifiedtime = null,
        private readonly ?string $assigned_user_name = null,
        private readonly ?string $assigned_user_email = null,
        private readonly ?string $relatedModule = null,
        private readonly ?string $userName = null,
        private readonly ?string $userEmail = null,
    ) {

        $this->validateCommentId($commentid);
        $this->validateContent($commentcontent);
        $this->validateRelatedTo($related_to);
    }


    // ========================================================================
    // GETTERS (Read-only access to entity state)
    // ========================================================================


    public function getId(): int
    {
        return $this->commentid;
    }


    public function getContent(): string
    {
        return $this->commentcontent;
    }

    public function getRelatedTo(): int
    {
        return $this->related_to;
    }

    public function getCreatedTime(): ?string
    {
        return $this->createdtime;
    }

    public function getModifiedTime(): ?string
    {
        return $this->modifiedtime;
    }

    public function getParentComments(): ?int
    {
        return $this->parent_comments;
    }


    public function getTaskId(): int
    {
        return $this->related_to;
    }


    public function getParentCommentId(): ?int
    {
        return $this->parent_comments;
    }


    public function getCustomerId(): ?int
    {
        return $this->customer;
    }


    public function getUserId(): ?int
    {
        return $this->userid;
    }


    public function getReasonToEdit(): ?string
    {
        return $this->reasontoedit;
    }


    public function getRelatedModule(): ?string
    {
        return $this->relatedModule;
    }

    public function getUserName(): ?string
    {
        return $this->userName ?? $this->assigned_user_name;
    }


    public function getRelatedEntityType(): string
    {
        return match ($this->relatedModule) {
            'Project' => 'Proyecto',
            'Calendar', 'Tasks' => 'Tarea',
            'Quotes' => 'Cotización',
            'Accounts' => 'Cliente',
            'Contacts' => 'Contacto',
            'Potentials' => 'Oportunidad',
            'HelpDesk' => 'Ticket',
            default => 'Entidad',
        };
    }


    public function getRelatedEntityIcon(): string
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


    public function isPrivate(): bool
    {
        return $this->is_private === 1;
    }


    public function getFilename(): ?string
    {
        return $this->filename;
    }


    public function getRelatedEmailId(): ?int
    {
        return $this->related_email_id;
    }


    public function getCreatedAt(): ?string
    {
        return $this->createdtime;
    }


    public function getUpdatedAt(): ?string
    {
        return $this->modifiedtime;
    }


    public function getAssignedUserName(): ?string
    {
        return $this->assigned_user_name ?? $this->userName ?? 'Usuario';
    }


    public function getAssignedUserEmail(): ?string
    {
        return $this->assigned_user_email ?? $this->userName ?? $this->userEmail;
    }

    // ========================================================================
    // DOMAIN METHODS (Business behavior)
    // ========================================================================

    /**
     * Check if this comment is a reply to another comment
     * 
     * @return bool True if this is a threaded reply, false if top-level
     */
    public function isReply(): bool
    {
        return $this->parent_comments !== null;
    }

    /**
     * Check if this comment has an attached file
     * 
     * @return bool True if attachment exists, false otherwise
     */
    public function hasAttachment(): bool
    {
        return !empty($this->filename);
    }

    /**
     * Check if this comment can be edited by a specific user
     * 
     * Business rule: Only the original author can edit their own comments.
     * 
     * @param int $userId ID of the user attempting to edit
     * @return bool True if user is authorized to edit, false otherwise
     */
    public function canBeEditedBy(int $userId): bool
    {
        return $this->userid === $userId;
    }

    /**
     * Check if this comment is visible to a specific user
     * 
     * Business rule: Private comments are only visible to internal users.
     * Public comments are visible to all authenticated users.
     * 
     * @param int $userId ID of the user requesting access
     * @param bool $isInternalUser Whether the user is an internal CRM user
     * @return bool True if comment is visible, false otherwise
     */
    public function isVisibleTo(int $userId, bool $isInternalUser): bool
    {
        if (!$this->isPrivate()) {
            return true;
        }
        return $isInternalUser;
    }

    /**
     * Get formatted creation date for display in UI
     * 
     * Format: DD/MM/YYYY HH:mm (e.g., "24/02/2026 14:30")
     * 
     * @return string|null Formatted date string, or null if not available
     */
    public function getFormattedCreatedAt(): ?string
    {
        if (!$this->createdtime) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($this->createdtime);
            return $date->format('d/m/Y H:i');
        } catch (\Exception) {
            // Fallback to raw value if parsing fails
            return $this->createdtime;
        }
    }

    /**
     * Get the display name of the comment author
     * 
     * Returns assigned user name if available, otherwise defaults to "Usuario".
     * 
     * @return string Author display name
     */
    public function getAuthorName(): string
    {
        return $this->assigned_user_name ?? $this->userName ?? 'Usuario';
    }

    /**
     * Update the comment content with audit trail
     * 
     * Business rules:
     * - Only the original author can edit their comment
     * - Content must pass validation rules
     * - Edit reason is recorded for audit purposes
     * - Modification timestamp is updated automatically
     * 
     * @param string $newContent New comment content
     * @param int $editedByUserId ID of user performing the edit
     * @param string|null $reason Optional reason for the edit (for audit)
     * 
     * @throws InvalidArgumentException If new content fails validation
     * @throws DomainException If user is not authorized to edit this comment
     */
    public function updateContent(string $newContent, int $editedByUserId, ?string $reason = null): void
    {
        if (!$this->canBeEditedBy($editedByUserId)) {
            throw new DomainException('Only the author can edit this comment');
        }

        $this->validateContent($newContent);

        $this->commentcontent = $newContent;
        $this->reasontoedit = $reason;
        $this->modifiedtime = date('Y-m-d H:i:s');
    }

    /**
     * Mark this comment as private (internal only)
     * 
     * Updates visibility flag and modification timestamp.
     */
    public function markAsPrivate(): void
    {
        $this->is_private = 1;
        $this->modifiedtime = date('Y-m-d H:i:s');
    }

    /**
     * Mark this comment as public (visible to customers)
     * 
     * Updates visibility flag and modification timestamp.
     */
    public function markAsPublic(): void
    {
        $this->is_private = 0;
        $this->modifiedtime = date('Y-m-d H:i:s');
    }

    // ========================================================================
    // INFRASTRUCTURE METHODS (For repository/persistence layer)
    // ========================================================================

    /**
     * Create a Comment entity from a database row
     * 
     * Maps raw database values to entity properties with type casting.
     * 
     * @internal Only for use by infrastructure layer (repositories)
     * 
     * @param array<string, mixed> $row Database row from vtiger_comments join query
     * @return self New Comment entity instance
     * 
     * @example
     * $row = $db->table('vtiger_comments')->first();
     * $comment = Comment::fromDatabaseRow($row);
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            commentid: (int) ($row['commentid'] ?? 0),
            commentcontent: $row['commentcontent'] ?? '',
            related_to: (int) ($row['related_to'] ?? 0),
            parent_comments: isset($row['parent_comments']) ? (int) $row['parent_comments'] : null,
            customer: isset($row['customer']) ? (int) $row['customer'] : null,
            userid: isset($row['userid']) ? (int) $row['userid'] : null,
            reasontoedit: $row['reasontoedit'] ?? null,
            is_private: isset($row['is_private']) ? (int) $row['is_private'] : null,
            filename: $row['filename'] ?? null,
            related_email_id: isset($row['related_email_id']) ? (int) $row['related_email_id'] : null,
            createdtime: $row['createdtime'] ?? null,
            modifiedtime: $row['modifiedtime'] ?? null,
            assigned_user_name: $row['assigned_user_name'] ?? null,
            assigned_user_email: $row['assigned_user_email'] ?? null,
        );
    }

    /**
     * Convert entity to array for API response or persistence
     * 
     * Includes computed properties (isReply, hasAttachment, etc.) for convenience.
     * 
     * @internal Only for use by presentation/infrastructure layers
     * 
     * @return array<string, mixed> Associative array representation
     * 
     * @example
     * return response()->json($comment->toArray());
     */
    public function toArray(): array
    {
        return [
            'id' => $this->commentid,
            'taskId' => $this->related_to,
            'relatedToId' => $this->related_to,
            'relatedModule' => $this->relatedModule,
            'relatedEntityType' => $this->getRelatedEntityType(),
            'relatedEntityIcon' => $this->getRelatedEntityIcon(),
            'content' => $this->commentcontent,
            'userId' => $this->userid,
            'userName' => $this->getAssignedUserName(),
            'userEmail' => $this->getAssignedUserEmail(),
            'createdAt' => $this->createdtime,
            'updatedAt' => $this->modifiedtime,
            'parentCommentId' => $this->parent_comments,
            'isPrivate' => $this->is_private === 1,
            'attachment' => $this->filename,
            'hasAttachment' => $this->hasAttachment(),
            'isReply' => $this->isReply(),
            'authorName' => $this->getAuthorName(),
            'formattedCreatedAt' => $this->getFormattedCreatedAt(),
        ];
    }

    // ========================================================================
    // PRIVATE VALIDATION METHODS (Encapsulated business rules)
    // ========================================================================

    /**
     * Validate comment ID
     * 
     * @param int $commentid ID to validate
     * @throws InvalidArgumentException If ID is not positive
     */
    private function validateCommentId(int $commentid): void
    {
        if ($commentid <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }
    }

    /**
     * Validate comment content
     * 
     * Rules:
     * - Cannot be empty or whitespace-only
     * - Maximum length: 65,000 characters (Vtiger TEXT field limit)
     * 
     * @param string $content Content to validate
     * @throws InvalidArgumentException If content fails validation
     */
    private function validateContent(string $content): void
    {
        if (trim($content) === '') {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }
        // Vtiger uses TEXT field: max ~65,535 bytes (UTF-8 safe limit: 65,000 chars)
        if (strlen($content) > 65000) {
            throw new InvalidArgumentException('Comment content exceeds maximum length of 65,000 characters');
        }
    }

    /**
     * Validate related record ID
     * 
     * @param int $related_to ID to validate
     * @throws InvalidArgumentException If ID is not positive
     */
    private function validateRelatedTo(int $related_to): void
    {
        if ($related_to <= 0) {
            throw new InvalidArgumentException('Related record ID must be positive');
        }
    }
}
