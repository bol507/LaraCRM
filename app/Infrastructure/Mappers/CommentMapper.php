<?php

namespace App\Infrastructure\Mappers;

use App\Domain\Entities\Comment;

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
    public static function fromDatabase(array $row): Comment
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
            createdtime: $row['createdtime'] ??  null,
            modifiedtime: $row['modifiedtime'] ??  null,
            assigned_user_name: $row['assigned_user_name'] ?? $row['userName'] ?? null,
            assigned_user_email: $row['assigned_user_email'] ?? $row['userEmail'] ?? null,
        );
    }

    /**
     * Map Comment entity to array for API response
     * 
     * Converts domain names (Comment) to frontend names (API JSON)
     * 
     * @param Comment $comment Domain entity
     * @return array Serializable data for JSON response
     */
    public static function toApi(Comment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'taskId' => $comment->getRelatedTo(),
            'content' => $comment->getContent(),
            'userName' => $comment->getAssignedUserName(),
            'userEmail' => $comment->getAssignedUserEmail(),
            'userId' => $comment->getUserId(),
            'createdAt' => $comment->getCreatedTime(),
            'updatedAt' => $comment->getModifiedTime(),
            'formattedCreatedAt' => $comment->getFormattedCreatedAt(),
            'isPrivate' => $comment->isPrivate(),
            'isReply' => $comment->isReply(),
            'parentCommentId' => $comment->getParentComments(),
            'attachment' => $comment->getFilename(),
            'reasonToEdit' => $comment->getReasonToEdit(),
        ];
    }

    /**
     * Map array of Comment entities to array of API responses
     * 
     * @param Comment[] $comments
     * @return array[]
     */
    public static function collectionToApi(array $comments): array
    {
        return array_map(fn(Comment $c) => self::toApi($c), $comments);
    }
}