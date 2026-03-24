<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use App\Infrastructure\Mappers\CommentMapper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vtiger Comment Repository
 * 
 * Implements the CommentRepositoryInterface for Vtiger CRM database.
 * 
 * This repository handles persistence operations for comments stored in
 * Vtiger's vtiger_modcomments and vtiger_crmentity tables. It follows
 * the Repository pattern to abstract database access from the domain layer.
 * 
 * Key responsibilities:
 * - Map database rows to Comment domain entities via CommentMapper
 * - Handle pagination for comment lists with filtering by related record
 * - Manage transactions for atomic comment creation (crmentity + modcomments)
 * - Implement soft delete via vtiger_crmentity.deleted flag
 * - Enforce access control rules for comment visibility
 * 
 * Database tables used:
 * - vtiger_crmentity: Base entity table for all Vtiger records
 * - vtiger_modcomments: Comment-specific data and metadata
 * - vtiger_users: User information for comment authors
 * 
 * @package App\Infrastructure\Repositories
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @implements CommentRepositoryInterface
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Infrastructure\Mappers\CommentMapper
 */
class VtigerCommentRepository implements CommentRepositoryInterface
{
    /**
     * {@inheritDoc}
     * 
     * Retrieves a paginated list of comments associated with a specific
     * record (e.g., project, quote, task), ordered by creation date.
     * 
     * This method performs a JOIN across vtiger_modcomments, vtiger_crmentity,
     * and vtiger_users tables to fetch complete comment data including
     * author information.
     * 
     * @param int $relatedId ID of the related record (e.g., project ID)
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $page Current page number (1-based, default: 1)
     * @param int $perPage Number of items per page (default: 50)
     * 
     * @return LengthAwarePaginator<Comment> Paginated collection of Comment entities
     * 
     * @throws \InvalidArgumentException If relatedId is not positive
     * @throws \RuntimeException If database query fails
     */
    /**
     * {@inheritDoc}
     * 
     * Retrieves a paginated list of comments associated with a specific
     * record (e.g., project, quote, task), ordered by creation date
     * with most recent comments first.
     * 
     * This method performs a JOIN across vtiger_modcomments, vtiger_crmentity,
     * and vtiger_users tables to fetch complete comment data including
     * author information.
     * 
     * @param int $relatedId ID of the related record (e.g., project ID)
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $page Current page number (1-based, default: 1)
     * @param int $perPage Number of items per page (default: 50)
     * 
     * @return LengthAwarePaginator<Comment> Paginated collection of Comment entities
     * 
     * @throws \InvalidArgumentException If relatedId is not positive
     * @throws \RuntimeException If database query fails
     */
    public function getByRelatedId(
        int $relatedId,
        string $module,
        int $page = 1,
        int $perPage = 50
    ): LengthAwarePaginator {
        // Validate that relatedId is positive
        if ($relatedId <= 0) {
            throw new \InvalidArgumentException('relatedId must be positive');
        }

        // Calculate offset for pagination
        $offset = ($page - 1) * $perPage;

        // Count total comments for pagination metadata
        $total = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();

        // Return empty paginator if no comments found
        if ($total === 0) {
            return new LengthAwarePaginator([], $total, $perPage, $page);
        }

        // Fetch comments with user data via JOINs
        $rows = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_modcomments.userid', '=', 'vtiger_users.id')
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0)
            ->select(
                'vtiger_modcomments.modcommentsid',
                'vtiger_modcomments.related_to',
                'vtiger_modcomments.commentcontent',
                'vtiger_modcomments.userid',
                'vtiger_modcomments.parent_comments',
                'vtiger_modcomments.customer',
                'vtiger_modcomments.reasontoedit',
                'vtiger_modcomments.is_private',
                'vtiger_modcomments.filename',
                'vtiger_modcomments.related_email_id',
                'vtiger_crmentity.createdtime',      // date created
                'vtiger_crmentity.modifiedtime',     //date modified
                'vtiger_crmentity.label',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                DB::raw('vtiger_users.email1 as assigned_user_email')
            )
            
            ->orderBy('vtiger_crmentity.createdtime', 'DESC')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Map database rows to Comment entities using CommentMapper
        $comments = $rows->map(fn($row) => CommentMapper::fromDatabase((array) $row))->toArray();

        return new LengthAwarePaginator(
            $comments,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * {@inheritDoc}
     * 
     * Retrieves a single comment by its unique identifier with full
     * author information and metadata.
     * 
     * @param int $commentId Unique identifier of the comment to retrieve
     * 
     * @return Comment|null Comment entity if found and not deleted, null otherwise
     * 
     * @throws \InvalidArgumentException If commentId is not positive
     * @throws \RuntimeException If database query fails
     */
    public function findById(int $commentId): ?Comment
    {
        // Validate that commentId is positive
        if ($commentId <= 0) {
            throw new \InvalidArgumentException('commentId must be positive');
        }

        // Fetch comment with user data via JOINs
        $row = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_modcomments.userid', '=', 'vtiger_users.id')
            ->where('vtiger_modcomments.modcommentsid', $commentId)
            ->where('vtiger_crmentity.deleted', 0)
            ->select(
                'vtiger_modcomments.modcommentsid',
                'vtiger_modcomments.related_to',
                'vtiger_modcomments.commentcontent',
                'vtiger_modcomments.userid',
                'vtiger_modcomments.parent_comments',
                'vtiger_modcomments.customer',
                'vtiger_modcomments.reasontoedit',
                'vtiger_modcomments.is_private',
                'vtiger_modcomments.filename',
                'vtiger_modcomments.related_email_id',
                 'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.label',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                DB::raw('vtiger_users.email1 as assigned_user_email')
            )
            ->first();

        // Map to entity if found, otherwise return null
        return $row ? CommentMapper::fromDatabase((array) $row) : null;
    }

    /**
     * {@inheritDoc}
     * 
     * Creates a new comment with full transactional support.
     * 
     * This method handles the two-table insert pattern required by Vtiger:
     * 1. Insert into vtiger_crmentity (base entity metadata)
     * 2. Insert into vtiger_modcomments (comment-specific data)
     * 
     * Both inserts use the same ID (commentId) to maintain referential
     * integrity via foreign key constraint.
     * 
     * @param string $module Module type of the related record
     * @param int $relatedId ID of the related record
     * @param string $content Comment text content
     * @param int $authenticatedUserId ID of the user creating the comment
     * @param int|null $parentId ID of parent comment for threaded replies, null for top-level
     * @param bool|null $isPrivate Visibility flag (true = private, false = public)
     * 
     * @return Comment The newly created Comment entity with generated ID
     * 
     * @throws \RuntimeException If database transaction fails
     * @throws \InvalidArgumentException If required parameters are invalid
     */
    public function create(
        string $module,
        int $relatedId,
        string $content,
        int $authenticatedUserId,
        ?int $parentId = null,
        ?bool $isPrivate = false
    ): Comment {
        return DB::connection('vtiger')->transaction(function () use (
            $module,
            $relatedId,
            $content,
            $authenticatedUserId,
            $parentId,
            $isPrivate
        ) {
            // Step 1: Generate new unique ID for the comment
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');
            $commentId = $maxCrmid ? $maxCrmid + 1 : 1;

            $now = now()->format('Y-m-d H:i:s');

            // Step 2: Insert into vtiger_crmentity (parent entity table)
            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $commentId,
                'smcreatorid' => $authenticatedUserId,
                'smownerid' => $authenticatedUserId,
                'setype' => 'ModComments',
                'description' => Str::limit($content, 100),
                'label' => Str::limit(trim($content), 100, '...'),
                'createdtime' => $now,
                'modifiedtime' => $now,
                'deleted' => 0,
            ]);

            // Step 3: Insert into vtiger_modcomments (comment-specific table)
            DB::connection('vtiger')->table('vtiger_modcomments')->insert([
                'modcommentsid' => $commentId,
                'commentcontent' => $content,
                'related_to' => $relatedId,
                'parent_comments' => $parentId,
                'customer' => null,
                'userid' => $authenticatedUserId,
                'reasontoedit' => null,
                'is_private' => $isPrivate ? 1 : 0,
                'filename' => null,
                'related_email_id' => null,
                'createdtime' => $now,
                'modifiedtime' => $now,
            ]);

            // Step 4: Fetch user data for entity mapping
            $user = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $authenticatedUserId)
                ->first();

            $userName = $user ? trim("{$user->first_name} {$user->last_name}") : 'Usuario';
            $userEmail = $user->email1 ?? '';

            // Step 5: Build data array with database column names for mapper
            $commentData = [
                'modcommentsid' => $commentId,
                'related_to' => $relatedId,
                'commentcontent' => $content,
                'userid' => $authenticatedUserId,
                'parent_comments' => $parentId,
                'customer' => null,
                'reasontoedit' => null,
                'is_private' => $isPrivate ? 1 : 0,
                'filename' => null,
                'related_email_id' => null,
                'createdtime' => $now,
                'modifiedtime' => $now,
                'assigned_user_name' => $userName,
                'assigned_user_email' => $userEmail,
            ];

            // Use CommentMapper to create domain entity (prevents parameter name errors)
            return CommentMapper::fromDatabase($commentData);
        });
    }

    /**
     * {@inheritDoc}
     * 
     * Updates an existing comment's content or metadata.
     * 
     * Only specified fields can be updated to maintain data integrity.
     * The modifiedtime field is automatically updated on every change.
     * 
     * @param int $commentId Unique identifier of the comment to update
     * @param array<string, mixed> $data Associative array with fields to update.
     *        Supported keys: 'commentcontent', 'reasontoedit', 'is_private'
     * 
     * @return bool True if update was successful, false if no changes were made
     * 
     * @throws \InvalidArgumentException If commentId is not positive
     * @throws \RuntimeException If database update fails
     */
    public function update(int $commentId, array $data): bool
    {
        // Validate that commentId is positive
        if ($commentId <= 0) {
            throw new \InvalidArgumentException('commentId must be positive');
        }

        // Define whitelist of updatable fields
        $updatable = ['commentcontent', 'reasontoedit', 'is_private'];

        // Filter input data to only include allowed fields
        $updates = array_filter(
            $data,
            fn($key) => in_array($key, $updatable, true),
            ARRAY_FILTER_USE_KEY
        );

        // Return false if no valid fields to update
        if (empty($updates)) {
            return false;
        }

        // Auto-update modifiedtime timestamp
        $updates['modifiedtime'] = now()->format('Y-m-d H:i:s');

        // Execute database update
        $affected = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->where('modcommentsid', $commentId)
            ->update($updates);

        return $affected > 0;
    }

    /**
     * {@inheritDoc}
     * 
     * Performs a soft delete by marking the comment as deleted in
     * vtiger_crmentity. The record remains in the database for audit
     * purposes but is excluded from normal queries.
     * 
     * @param int $commentId Unique identifier of the comment to delete
     * 
     * @return bool True if deletion was successful, false if not found or already deleted
     * 
     * @throws \InvalidArgumentException If commentId is not positive
     * @throws \RuntimeException If database update fails
     */
    public function delete(int $commentId): bool
    {
        // Validate that commentId is positive
        if ($commentId <= 0) {
            throw new \InvalidArgumentException('commentId must be positive');
        }

        // Soft delete: mark as deleted in vtiger_crmentity
        $affected = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $commentId)
            ->where('deleted', 0)
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $affected > 0;
    }

    /**
     * {@inheritDoc}
     * 
     * Returns the total count of non-deleted comments for a related record.
     * 
     * This is a lightweight COUNT query without loading full entities,
     * suitable for pagination metadata or dashboard statistics.
     * 
     * @param int $relatedId ID of the related record
     * @param string $module Module type of the related record
     * 
     * @return int Total number of non-deleted comments
     * 
     * @throws \RuntimeException If database query fails
     */
    public function countByRelatedId(int $relatedId, string $module): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    /**
     * {@inheritDoc}
     * 
     * Checks if a user has permission to view a specific comment.
     * 
     * Access rules:
     * - Public comments (is_private = 0): visible to all authenticated users
     * - Private comments (is_private = 1): visible only to author or admins
     * - Deleted comments: never visible (filtered by caller)
     * 
     * @param int $commentId ID of the comment to check
     * @param int $userId ID of the user requesting access
     * 
     * @return bool True if user can view the comment, false otherwise
     * 
     * @throws \RuntimeException If database query fails
     */
    public function canAccess(int $commentId, int $userId): bool
    {
        // Fetch comment privacy settings and author ID
        $comment = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_modcomments.modcommentsid', $commentId)
            ->where('vtiger_crmentity.deleted', 0)
            ->select('vtiger_modcomments.is_private', 'vtiger_modcomments.userid')
            ->first();

        // Comment not found or already deleted
        if (!$comment) {
            return false;
        }

        // Public comments are visible to all authenticated users
        if (!$comment->is_private) {
            return true;
        }

        // Private comments: only author or admins can view
        return $comment->userid === $userId || $this->isAdmin($userId);
    }

    /**
     * Convert Comment entity to API response format
     * 
     * This method delegates to CommentMapper for consistent transformation
     * between domain entities and JSON API format.
     * 
     * @param Comment $comment Domain entity to transform
     * 
     * @return array Serializable data for JSON response
     */
    public function commentToApiResponse(Comment $comment): array
    {
        return CommentMapper::toApi($comment);
    }

    /**
     * Check if a user has administrator role
     * 
     * @param int $userId ID of the user to check
     * 
     * @return bool True if user has Admin role, false otherwise
     */
    private function isAdmin(int $userId): bool
    {
        $role = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $userId)
            ->value('role');

        return $role === 'Admin';
    }
}
