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
 * @property-read string $authorName Display name for UI (fallback: "Usuario")
 * @property-read string|null $formattedCreatedAt Human-readable date (DD/MM/YYYY HH:mm)
 * 
 * @see \App\Domain\Entities\Comment
 * @see \App\Application\UseCases\Comment\GetTaskCommentsUseCase
 * @see \App\Http\Controllers\Api\CommentController
 */
class CommentDto
{
    /**
     * Unique identifier for the comment
     * 
     * @var int
     */
    public readonly int $id;

    /**
     * ID of the related task or record this comment belongs to
     * 
     * Typically references vtiger_activity.activityid for task comments.
     * 
     * @var int
     */
    public readonly int $taskId;

    /**
     * Main text content of the comment
     * 
     * Plain text, max 65,000 characters. Sanitized and validated
     * at the domain entity level before reaching this DTO.
     * 
     * @var string
     */
    public readonly string $content;

    /**
     * ID of the user who created this comment
     * 
     * References vtiger_users.id for internal users.
     * Defaults to 0 if created by anonymous customer.
     * 
     * @var int
     */
    public readonly int $userId;

    /**
     * Display name of the comment author
     * 
     * Formatted as "FirstName LastName" from vtiger_users.
     * Null if user data is not available or comment is anonymous.
     * 
     * @var string|null
     */
    public readonly ?string $userName;

    /**
     * Email address of the comment author
     * 
     * From vtiger_users.email1. Null if not available or anonymous.
     * 
     * @var string|null
     */
    public readonly ?string $userEmail;

    /**
     * Creation timestamp in database format
     * 
     * Format: YYYY-MM-DD HH:MM:SS
     * Null if timestamp is not available (should not happen in practice).
     * 
     * @var string|null
     */
    public readonly ?string $createdAt;

    /**
     * Last modification timestamp in database format
     * 
     * Format: YYYY-MM-DD HH:MM:SS
     * Null if comment has never been modified.
     * 
     * @var string|null
     */
    public readonly ?string $updatedAt;

    /**
     * ID of parent comment for threaded replies
     * 
     * Null indicates this is a top-level comment.
     * Non-null value indicates this comment is a reply.
     * 
     * @var int|null
     */
    public readonly ?int $parentCommentId;

    /**
     * Whether this comment is marked as private/internal
     * 
     * Private comments are only visible to internal CRM users.
     * Public comments are visible to customers in the portal.
     * 
     * @var bool
     */
    public readonly bool $isPrivate;

    /**
     * Filename of attached file (if any)
     * 
     * References a file in vtiger_attachments.
     * Null if no attachment is associated with this comment.
     * 
     * @var string|null
     */
    public readonly ?string $attachment;

    /**
     * Whether this comment is a reply to another comment
     * 
     * Computed property: true if parentCommentId is not null.
     * Useful for UI rendering of threaded conversations.
     * 
     * @var bool
     */
    public readonly bool $isReply;

    /**
     * Display name for the comment author (UI-friendly)
     * 
     * Returns userName if available, otherwise defaults to "Usuario".
     * Guaranteed to return a non-empty string for safe UI rendering.
     * 
     * @var string
     */
    public readonly string $authorName;

    /**
     * Human-readable formatted creation date
     * 
     * Format: DD/MM/YYYY HH:mm (e.g., "24/02/2026 14:30")
     * Null if original timestamp is not available or parsing fails.
     * 
     * @var string|null
     */
    public readonly ?string $formattedCreatedAt;

    /**
     * Constructor for CommentDto
     * 
     * Creates an immutable DTO instance with all required fields.
     * Use `fromEntity()` factory method for conversion from domain entity.
     * 
     * @param int $id Unique identifier for the comment
     * @param int $taskId ID of the related task/record
     * @param string $content Main text content of the comment
     * @param int $userId ID of user who created the comment
     * @param string|null $userName Display name of the author (optional)
     * @param string|null $userEmail Email of the author (optional)
     * @param string|null $createdAt Creation timestamp (optional)
     * @param string|null $updatedAt Last modification timestamp (optional)
     * @param int|null $parentCommentId ID of parent comment for threading (optional)
     * @param bool $isPrivate Whether comment is private/internal only
     * @param string|null $attachment Filename of attached file (optional)
     * @param bool $isReply Whether this is a threaded reply
     * @param string $authorName Display name for UI (fallback: "Usuario")
     * @param string|null $formattedCreatedAt Human-readable date (optional)
     */
    public function __construct(
        int $id,
        int $taskId,
        string $content,
        int $userId,
        ?string $userName,
        ?string $userEmail,
        ?string $createdAt,
        ?string $updatedAt,
        ?int $parentCommentId,
        bool $isPrivate,
        ?string $attachment,
        bool $isReply,
        string $authorName,
        ?string $formattedCreatedAt,
    ) {
        $this->id = $id;
        $this->taskId = $taskId;
        $this->content = $content;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->parentCommentId = $parentCommentId;
        $this->isPrivate = $isPrivate;
        $this->attachment = $attachment;
        $this->isReply = $isReply;
        $this->authorName = $authorName;
        $this->formattedCreatedAt = $formattedCreatedAt;
    }

    /**
     * Create a CommentDto from a Comment domain entity
     * 
     * Factory method that maps entity properties to DTO fields using
     * entity getters. Handles null safety and computed properties.
     * 
     * This is the preferred way to create DTO instances from domain logic.
     * 
     * @param Comment $comment The domain entity to convert
     * @return self New CommentDto instance with mapped values
     * 
     * @example
     * $comment = $repository->findById(123);
     * $dto = CommentDto::fromEntity($comment);
     * return response()->json($dto->toArray());
     */
    public static function fromEntity(Comment $comment): self
    {
        return new self(
            id: $comment->getId(),
            taskId: $comment->getTaskId(),
            content: $comment->getContent(),
            userId: $comment->getUserId() ?? 0,
            userName: $comment->getAssignedUserName(),
            userEmail: $comment->getAssignedUserEmail(),
            createdAt: $comment->getCreatedAt(),
            updatedAt: $comment->getUpdatedAt(),
            parentCommentId: $comment->getParentCommentId(),
            isPrivate: $comment->isPrivate(),
            attachment: $comment->getFilename(),
            isReply: $comment->isReply(),
            authorName: $comment->getAuthorName(),
            formattedCreatedAt: $comment->getFormattedCreatedAt(),
        );
    }

    /**
     * Convert DTO to associative array for JSON serialization
     * 
     * Returns a clean, frontend-ready representation of the comment.
     * All property names use camelCase for JavaScript compatibility.
     * 
     * This method is idempotent and safe to call multiple times.
     * 
     * @return array<string, mixed> Associative array with comment data
     * 
     * @example
     * // In controller:
     * return response()->json($dto->toArray());
     * 
     * // Output:
     * // {
     * //   "id": 123,
     * //   "taskId": 456,
     * //   "content": "Great progress!",
     * //   "userName": "John Doe",
     * //   "isReply": false,
     * //   ...
     * // }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'taskId' => $this->taskId,
            'content' => $this->content,
            'userId' => $this->userId,
            'userName' => $this->userName,
            'userEmail' => $this->userEmail,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'parentCommentId' => $this->parentCommentId,
            'isPrivate' => $this->isPrivate,
            'attachment' => $this->attachment,
            'isReply' => $this->isReply,
            'authorName' => $this->authorName,
            'formattedCreatedAt' => $this->formattedCreatedAt,
        ];
    }
}