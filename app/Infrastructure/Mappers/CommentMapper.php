<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Comment;
use Illuminate\Support\Collection;

/**
 * Comment Mapper
 * 
 * Centralizes the mapping between database rows and domain entities.
 * This prevents duplication and ensures consistency across the application.
 * 
 * @package App\Infrastructure\Mappers
 */
class CommentMapper
{
    /**
     * Map database row to Comment entity
     * 
     * Array keys must match either:
     * - Database column names (for direct queries)
     * - Or SELECT alias names (e.g., 'assigned_user_name')
     * 
     * @param array $row Database row with specific keys
     * @return Comment Mapped domain entity
     */
    public static function fromDatabaseRow(object $row): Comment
    {
        return new Comment(
            commentid: (int) ($row['modcommentsid'] ?? $row['commentid'] ?? $row['id'] ?? 0),
            commentcontent: (string) ($row['commentcontent'] ?? $row['content'] ?? ''),
            related_to: (int) ($row['related_to'] ?? $row['taskId'] ?? 0),
            parent_comments: isset($row['parent_comments']) ? (int) $row['parent_comments'] : null,
            customer: isset($row['customer']) ? (int) $row['customer'] : null,
            userid: isset($row['userid']) ? (int) $row['userid'] : null,
            reasontoedit: $row['reasontoedit'] ?? null,
            is_private: isset($row['is_private']) ? (int) $row['is_private'] : 0,
            filename: $row['filename'] ?? $row['attachment'] ?? null,
            related_email_id: isset($row['related_email_id']) ? (int) $row['related_email_id'] : null,
        );
    }

    public static function fromDatabaseRows(Collection|array $rows): array
    {
         $items = $rows instanceof Collection ? $rows->all() : $rows;
        return array_map(fn(object $row) => self::fromDatabaseRow($row), $items);
    }

    public static function toDatabaseRow(Comment $comment): array 
    {
        return [
            'modcommentsid' => $comment->getId(),
            'commentcontent' => $comment->getContent(),
            'related_to' => $comment->getRelatedTo(),
            'parent_comments' => $comment->getParentCommentId(),
            'customer' => $comment->getCustomerId(),
            'userid' => $comment->getUserId(),
            'reasontoedit' => $comment->getReasonToEdit(),
            'is_private' => $comment->isPrivate() ? 1 : 0,
            'filename' => $comment->getFilename(),
            'related_email_id' => $comment->getRelatedEmailId(),
        ];
    }

    public static function toDatabaseRows(array $comments): array
    {
        return array_map(fn(Comment $comment) => self::toDatabaseRow($comment), $comments);
    }

    public static function fromArray(array $data): Comment
    {
        return self::fromDatabaseRow((object) $data);
    }

    public static function toArray(Comment $comment): array
    {
        return [
            'commentsid' => $comment->getId(),
            'commentcontent' => $comment->getContent(),
            'related_to' => $comment->getRelatedTo(),
            'parent_comments' => $comment->getParentCommentId(),
            'customer' => $comment->getCustomerId(),
            'userid' => $comment->getUserId(),
            'reasontoedit' => $comment->getReasonToEdit(),
            'is_private' => $comment->isPrivate() ? 1 : 0,
            'filename' => $comment->getFilename(),
            'related_email_id' => $comment->getRelatedEmailId(),
        ];
    }
    

   

}
