<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Vtiger Implementation of Comment Repository
 * 
 * Persists Comment entities to Vtiger CRM database tables:
 * - vtiger_modcomments: Main comment data
 * - vtiger_crmentity: Metadata (permissions, soft delete, timestamps)
 * - vtiger_users: Author information (via join)
 * 
 * This implementation follows Vtiger's data model conventions:
 * - Auto-increment IDs via MAX(id) + 1 (Vtiger legacy pattern)
 * - Soft delete via vtiger_crmentity.deleted flag
 * - Module type 'ModComments' for comment entities
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @implements CommentRepositoryInterface
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Application\DTOs\CreateCommentRequest
 */
class VtigerCommentRepository implements CommentRepositoryInterface
{
    /**
     * Default pagination settings
     */
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 100;

    /**
     * Vtiger module type for comments
     */
    private const MODULE_TYPE = 'ModComments';

    /**
     * {@inheritDoc}
     */
    public function getByRelatedId(
        int $relatedId,
        string $module,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE
    ): LengthAwarePaginator {
        // Validate input parameters
        if ($relatedId <= 0) {
            throw new InvalidArgumentException('Related record ID must be positive');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Page number must be at least 1');
        }
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);

        try {
            $offset = ($page - 1) * $perPage;

            // Build base query with joins for user information
            $query = DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_modcomments.related_to', $relatedId)
                ->where('vtiger_crmentity.deleted', 0)
                ->select(
                    'vtiger_modcomments.modcommentsid as commentid',
                    'vtiger_modcomments.commentcontent',
                    'vtiger_modcomments.related_to',
                    'vtiger_modcomments.parent_comments',
                    'vtiger_modcomments.customer',
                    'vtiger_modcomments.userid',
                    'vtiger_modcomments.reasontoedit',
                    'vtiger_modcomments.is_private',
                    'vtiger_modcomments.filename',
                    'vtiger_modcomments.related_email_id',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                    'vtiger_users.email1 as assigned_user_email'
                );

            // Get total count for pagination metadata
            $total = (clone $query)->count();

            // Execute paginated query ordered by creation date (newest first)
            $results = $query
                ->orderBy('vtiger_crmentity.createdtime', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Map database rows to Comment entities
            $comments = $results->map(fn($row) => $this->mapToEntity($row));

            // Return Laravel paginator with entity collection
            return new LengthAwarePaginator(
                $comments,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve comments for related ID {$relatedId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $commentId): ?Comment
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_modcomments.modcommentsid', $commentId)
                ->where('vtiger_crmentity.deleted', 0)
                ->select(
                    'vtiger_modcomments.modcommentsid as commentid',
                    'vtiger_modcomments.commentcontent',
                    'vtiger_modcomments.related_to',
                    'vtiger_modcomments.parent_comments',
                    'vtiger_modcomments.customer',
                    'vtiger_modcomments.userid',
                    'vtiger_modcomments.reasontoedit',
                    'vtiger_modcomments.is_private',
                    'vtiger_modcomments.filename',
                    'vtiger_modcomments.related_email_id',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                    'vtiger_users.email1 as assigned_user_email'
                )
                ->first();

            if (!$row) {
                return null;
            }

            return $this->mapToEntity($row);

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve comment {$commentId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateCommentRequest $request): int
    {
        try {
            return DB::connection('vtiger')->transaction(function () use ($request) {
                // Generate new comment ID using Vtiger legacy pattern: MAX(id) + 1
                $commentId = (int) (DB::connection('vtiger')
                    ->table('vtiger_modcomments')
                    ->max('modcommentsid') ?? 0) + 1;

                $now = now()->format('Y-m-d H:i:s');

                // Insert main comment data into vtiger_modcomments
                DB::connection('vtiger')->table('vtiger_modcomments')->insert([
                    'modcommentsid' => $commentId,
                    'related_to' => $request->taskId,
                    'commentcontent' => $request->content,
                    'userid' => $request->userId,
                    'parent_comments' => $request->parentCommentId,
                    'customer' => null, // Reserved for customer portal users
                    'filename' => $request->attachment,
                    'is_private' => 0, // Default to public visibility
                    'related_email_id' => null,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                ]);

                // Insert metadata into vtiger_crmentity (required for Vtiger permissions/soft-delete)
                DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $commentId,
                    'smcreatorid' => $request->userId,
                    'smownerid' => $request->userId,
                    'modifiedby' => $request->userId,
                    'setype' => self::MODULE_TYPE,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'deleted' => 0,
                    'version' => 0,
                    'presence' => 1,
                ]);

                return $commentId;
            });

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create comment: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $commentId, array $data): bool
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        try {
            $now = now()->format('Y-m-d H:i:s');
            $updated = false;

            // Update comment content and audit fields in vtiger_modcomments
            if (!empty($data['content']) || isset($data['reason_to_edit']) || isset($data['is_private'])) {
                $updateData = [];
                
                if (isset($data['content'])) {
                    $updateData['commentcontent'] = $data['content'];
                }
                if (isset($data['reason_to_edit'])) {
                    $updateData['reasontoedit'] = $data['reason_to_edit'];
                }
                if (isset($data['is_private'])) {
                    $updateData['is_private'] = $data['is_private'] ? 1 : 0;
                }
                if (isset($data['attachment'])) {
                    $updateData['filename'] = $data['attachment'];
                }
                
                $updateData['modifiedtime'] = $now;

                $updated = DB::connection('vtiger')
                    ->table('vtiger_modcomments')
                    ->where('modcommentsid', $commentId)
                    ->update($updateData) > 0;
            }

            // Always update modifiedtime in vtiger_crmentity for consistency
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $commentId)
                ->update(['modifiedtime' => $now]);

            return $updated;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to update comment {$commentId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $commentId): bool
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        try {
            // Soft delete: mark as deleted in vtiger_crmentity
            $updated = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $commentId)
                ->where('deleted', 0) // Only delete if not already deleted
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return $updated > 0;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to delete comment {$commentId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deletePermanently(int $olderThanDays): int
    {
        if ($olderThanDays <= 0) {
            throw new InvalidArgumentException('Retention days must be positive');
        }

        try {
            $cutoffDate = now()->subDays($olderThanDays)->format('Y-m-d H:i:s');

            // Get IDs of comments to delete (for audit logging)
            $commentIds = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('setype', 'ModComments')
                ->where('deleted', 1)
                ->where('modifiedtime', '<', $cutoffDate)
                ->pluck('crmid');

            if ($commentIds->isEmpty()) {
                return 0;
            }

            // Delete from vtiger_modcomments first (foreign key constraint)
            DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->whereIn('modcommentsid', $commentIds)
                ->delete();

            // Then delete from vtiger_crmentity
            $deleted = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->whereIn('crmid', $commentIds)
                ->delete();

            return $deleted;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to permanently delete old comments: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function restore(int $commentId): bool
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        try {
            // Restore: mark as not deleted in vtiger_crmentity
            $updated = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $commentId)
                ->where('deleted', 1) // Only restore if currently deleted
                ->update([
                    'deleted' => 0,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return $updated > 0;

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to restore comment {$commentId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdIncludingDeleted(int $commentId): ?Comment
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_modcomments.modcommentsid', $commentId)
                // Note: NOT filtering by deleted = 0 to include soft-deleted comments
                ->select(
                    'vtiger_modcomments.modcommentsid as commentid',
                    'vtiger_modcomments.commentcontent',
                    'vtiger_modcomments.related_to',
                    'vtiger_modcomments.parent_comments',
                    'vtiger_modcomments.customer',
                    'vtiger_modcomments.userid',
                    'vtiger_modcomments.reasontoedit',
                    'vtiger_modcomments.is_private',
                    'vtiger_modcomments.filename',
                    'vtiger_modcomments.related_email_id',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    'vtiger_crmentity.deleted',
                    DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                    'vtiger_users.email1 as assigned_user_email'
                )
                ->first();

            if (!$row) {
                return null;
            }

            return $this->mapToEntity($row);

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve comment {$commentId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function countByRelatedId(int $relatedId, string $module): int
    {
        if ($relatedId <= 0) {
            throw new InvalidArgumentException('Related record ID must be positive');
        }

        try {
            return DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_modcomments.related_to', $relatedId)
                ->where('vtiger_crmentity.deleted', 0)
                ->count();

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to count comments for related ID {$relatedId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserId(int $userId, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Page number must be at least 1');
        }
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);

        try {
            $offset = ($page - 1) * $perPage;

            $query = DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
                ->where('vtiger_modcomments.userid', $userId)
                ->where('vtiger_crmentity.deleted', 0)
                ->select(
                    'vtiger_modcomments.modcommentsid as commentid',
                    'vtiger_modcomments.commentcontent',
                    'vtiger_modcomments.related_to',
                    'vtiger_modcomments.parent_comments',
                    'vtiger_modcomments.customer',
                    'vtiger_modcomments.userid',
                    'vtiger_modcomments.reasontoedit',
                    'vtiger_modcomments.is_private',
                    'vtiger_modcomments.filename',
                    'vtiger_modcomments.related_email_id',
                    'vtiger_crmentity.createdtime',
                    'vtiger_crmentity.modifiedtime',
                    DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                    'vtiger_users.email1 as assigned_user_email'
                );

            $total = (clone $query)->count();

            $results = $query
                ->orderBy('vtiger_crmentity.createdtime', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $comments = $results->map(fn($row) => $this->mapToEntity($row));

            return new LengthAwarePaginator(
                $comments,
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'pageName' => 'page']
            );

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve comments for user {$userId}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function canAccess(int $commentId, int $userId): bool
    {
        if ($commentId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $comment = DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_modcomments.modcommentsid', $commentId)
                ->where('vtiger_crmentity.deleted', 0)
                ->select('vtiger_modcomments.is_private', 'vtiger_modcomments.userid', 'vtiger_crmentity.smownerid')
                ->first();

            if (!$comment) {
                return false;
            }

            // Public comments are visible to all authenticated users
            if ((int) $comment->is_private !== 1) {
                return true;
            }

            // Private comments: only visible to author or record owner
            return (int) $comment->userid === $userId || (int) $comment->smownerid === $userId;

        } catch (\Exception) {
            // On error, deny access (fail-safe)
            return false;
        }
    }

    /**
     * Map database row to Comment entity using factory method
     * 
     * Centralizes the mapping logic to ensure consistency across all queries.
     * 
     * @param object $row Database row from joined query
     * @return Comment Mapped domain entity
     * 
     * @internal Only for internal use by this repository
     */
    private function mapToEntity(object $row): Comment
    {
        return Comment::fromDatabaseRow([
            'commentid' => (int) $row->commentid,
            'commentcontent' => (string) $row->commentcontent,
            'related_to' => (int) $row->related_to,
            'parent_comments' => $row->parent_comments ? (int) $row->parent_comments : null,
            'customer' => $row->customer ? (int) $row->customer : null,
            'userid' => $row->userid ? (int) $row->userid : null,
            'reasontoedit' => $row->reasontoedit,
            'is_private' => $row->is_private ? (int) $row->is_private : null,
            'filename' => $row->filename,
            'related_email_id' => $row->related_email_id ? (int) $row->related_email_id : null,
            'createdtime' => $row->createdtime,
            'modifiedtime' => $row->modifiedtime,
            'assigned_user_name' => $row->assigned_user_name,
            'assigned_user_email' => $row->assigned_user_email,
        ]);
    }
}