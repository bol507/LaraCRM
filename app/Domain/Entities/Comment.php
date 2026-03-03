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
     * Unique identifier for the comment
     * 
     * @var int
     */
    private int $commentid;

    /**
     * Main text content of the comment
     * 
     * Supports plain text. Maximum length: 65,000 characters
     * (Vtiger uses TEXT field with ~65,535 bytes limit).
     * 
     * @var string
     */
    private string $commentcontent;

    /**
     * ID of the related record this comment belongs to
     * 
     * Typically references a task ID (vtiger_activity.activityid),
     * but can reference any CRM entity via vtiger_crmentity.
     * 
     * @var int
     */
    private int $related_to;

    /**
     * ID of parent comment for hierarchical threading
     * 
     * Used to create comment replies. Null indicates a top-level comment.
     * 
     * @var int|null
     */
    private ?int $parent_comments;

    /**
     * ID of customer who created the comment
     * 
     * Only populated when comments are created through customer portal.
     * Null for internal user comments.
     * 
     * @var int|null
     */
    private ?int $customer;

    /**
     * ID of user who created the comment
     * 
     * References vtiger_users.id for internal users.
     * Null only when comment is created by anonymous customer.
     * 
     * @var int|null
     */
    private ?int $userid;

    /**
     * Reason provided when editing the comment
     * 
     * Populated when a user modifies an existing comment.
     * Used for audit trail and change tracking.
     * 
     * @var string|null
     */
    private ?string $reasontoedit;

    /**
     * Visibility flag for comment privacy
     * 
     * Values:
     * - 1: Private comment (visible only to internal users)
     * - 0: Public comment (visible to customers in portal)
     * - null: Default visibility based on module settings
     * 
     * @var int|null
     */
    private ?int $is_private;

    /**
     * Name of attached file (if any)
     * 
     * Stores filename when a file is attached to the comment.
     * File itself is stored in vtiger_attachments table.
     * 
     * @var string|null
     */
    private ?string $filename;

    /**
     * ID of related email (if comment came from email)
     * 
     * Populated when comments are created automatically from email replies.
     * References vtiger_ossmailview.mailviewid.
     * 
     * @var int|null
     */
    private ?int $related_email_id;

    /**
     * Creation timestamp
     * 
     * Format: YYYY-MM-DD HH:MM:SS
     * Set automatically by database on insert.
     * 
     * @var string|null
     */
    private ?string $createdtime;

    /**
     * Last modification timestamp
     * 
     * Format: YYYY-MM-DD HH:MM:SS
     * Updated automatically when comment is edited.
     * 
     * @var string|null
     */
    private ?string $modifiedtime;

    /**
     * Name of user assigned to the related record
     * 
     * Cached value for performance (avoid joins when displaying comments).
     * Populated from vtiger_users.first_name + vtiger_users.last_name.
     * 
     * @var string|null
     */
    private ?string $assigned_user_name;

    /**
     * Email of user assigned to the related record
     * 
     * Cached value for performance and notifications.
     * Populated from vtiger_users.email1.
     * 
     * @var string|null
     */
    private ?string $assigned_user_email;

    /**
     * Constructor for Comment entity
     * 
     * Validates input data and initializes the entity state.
     * 
     * @param int $commentid Unique identifier for the comment
     * @param string $commentcontent Main text content of the comment
     * @param int $related_to ID of the related record this comment belongs to
     * @param int|null $parent_comments ID of parent comment for threading (optional)
     * @param int|null $customer ID of customer who created the comment (optional)
     * @param int|null $userid ID of user who created the comment (optional)
     * @param string|null $reasontoedit Reason for editing the comment (optional)
     * @param int|null $is_private Visibility flag (1=private, 0=public) (optional)
     * @param string|null $filename Attached file name (optional)
     * @param int|null $related_email_id ID of related email (optional)
     * @param string|null $createdtime Creation timestamp (optional)
     * @param string|null $modifiedtime Last modification timestamp (optional)
     * @param string|null $assigned_user_name Name of assigned user (optional)
     * @param string|null $assigned_user_email Email of assigned user (optional)
     * 
     * @throws InvalidArgumentException If validation fails
     */
    public function __construct(
        int $commentid,
        string $commentcontent,
        int $related_to,
        ?int $parent_comments = null,
        ?int $customer = null,
        ?int $userid = null,
        ?string $reasontoedit = null,
        ?int $is_private = null,
        ?string $filename = null,
        ?int $related_email_id = null,
        ?string $createdtime = null,
        ?string $modifiedtime = null,
        ?string $assigned_user_name = null,
        ?string $assigned_user_email = null
    ) {
        $this->validateCommentId($commentid);
        $this->validateContent($commentcontent);
        $this->validateRelatedTo($related_to);

        $this->commentid = $commentid;
        $this->commentcontent = $commentcontent;
        $this->related_to = $related_to;
        $this->parent_comments = $parent_comments;
        $this->customer = $customer;
        $this->userid = $userid;
        $this->reasontoedit = $reasontoedit;
        $this->is_private = $is_private;
        $this->filename = $filename;
        $this->related_email_id = $related_email_id;
        $this->createdtime = $createdtime;
        $this->modifiedtime = $modifiedtime;
        $this->assigned_user_name = $assigned_user_name;
        $this->assigned_user_email = $assigned_user_email;
    }

    // ========================================================================
    // GETTERS (Read-only access to entity state)
    // ========================================================================

    /**
     * Get the unique identifier for this comment
     * 
     * @return int Comment ID
     */
    public function getId(): int
    {
        return $this->commentid;
    }

    /**
     * Get the main text content of the comment
     * 
     * @return string Comment content
     */
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

    /**
     * Get the ID of the related record (e.g., task ID)
     * 
     * @return int Related record ID
     */
    public function getTaskId(): int
    {
        return $this->related_to;
    }

    /**
     * Get the ID of the parent comment (for threaded replies)
     * 
     * @return int|null Parent comment ID, or null if top-level
     */
    public function getParentCommentId(): ?int
    {
        return $this->parent_comments;
    }

    /**
     * Get the ID of the customer who created this comment
     * 
     * @return int|null Customer ID, or null if created by internal user
     */
    public function getCustomerId(): ?int
    {
        return $this->customer;
    }

    /**
     * Get the ID of the user who created this comment
     * 
     * @return int|null User ID, or null if created by anonymous customer
     */
    public function getUserId(): ?int
    {
        return $this->userid;
    }

    /**
     * Get the reason provided when this comment was edited
     * 
     * @return string|null Edit reason, or null if never edited
     */
    public function getReasonToEdit(): ?string
    {
        return $this->reasontoedit;
    }

    /**
     * Check if this comment is marked as private
     * 
     * @return bool True if private, false if public
     */
    public function isPrivate(): bool
    {
        return $this->is_private === 1;
    }

    /**
     * Get the name of the attached file (if any)
     * 
     * @return string|null Filename, or null if no attachment
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the ID of the related email (if comment came from email)
     * 
     * @return int|null Email ID, or null if not email-related
     */
    public function getRelatedEmailId(): ?int
    {
        return $this->related_email_id;
    }

    /**
     * Get the creation timestamp
     * 
     * @return string|null Timestamp in YYYY-MM-DD HH:MM:SS format, or null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdtime;
    }

    /**
     * Get the last modification timestamp
     * 
     * @return string|null Timestamp in YYYY-MM-DD HH:MM:SS format, or null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->modifiedtime;
    }

    /**
     * Get the name of the user assigned to the related record
     * 
     * @return string|null User name, or null if not available
     */
    public function getAssignedUserName(): ?string
    {
        return $this->assigned_user_name;
    }

    /**
     * Get the email of the user assigned to the related record
     * 
     * @return string|null User email, or null if not available
     */
    public function getAssignedUserEmail(): ?string
    {
        return $this->assigned_user_email;
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
        return $this->assigned_user_name ?? 'Usuario';
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
            'content' => $this->commentcontent,
            'userId' => $this->userid,
            'userName' => $this->assigned_user_name,
            'userEmail' => $this->assigned_user_email,
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