<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use App\Infrastructure\Mappers\CommentMapper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_modcomments - Comment data table
 *
 * Handles CRUD operations for comment data in Vtiger CRM.
 * This repository implements CommentRepositoryInterface, providing both
 * simple table operations (for Create/Update UseCases) and complex
 * queries with joins (for Get/List UseCases).
 */
class CommentRepository implements CommentRepositoryInterface
{
    private const TABLE = 'vtiger_modcomments';

    private const CONNECTION = 'vtiger';

    // =========================================================================
    // Simple CRUD Methods (used by Create/Update UseCases)
    // =========================================================================

    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        $commentId = $data['modcommentsid'] ?? null;
        if (! $commentId) {
            throw new InvalidArgumentException('modcommentsid is required for insert');
        }

        DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $commentId;
    }

    public function updateComment(int $commentId, array $data): bool
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('modcommentsid must be positive');
        }

        if (empty($data)) {
            return true;
        }

        $sanitized = array_diff_key($data, [
            'modcommentsid' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('modcommentsid', $commentId)
            ->update($sanitized);

        return $affected > 0;
    }

    public function findCommentById(int $commentId): ?array
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('modcommentsid must be positive');
        }

        $row = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('modcommentsid', $commentId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function exists(int $commentId): bool
    {
        if ($commentId <= 0) {
            return false;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('modcommentsid', $commentId)
            ->exists();
    }

    public function getNextCommentId(): int
    {
        $maxId = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->max('modcommentsid');

        return $maxId ? $maxId + 1 : 1;
    }

    // =========================================================================
    // Interface Implementation: CommentRepositoryInterface
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getByRelatedId(
        int $relatedId,
        string $module,
        int $page = 1,
        int $perPage = 50
    ): LengthAwarePaginator {
        $mapModuleToSetype = [
            'Project' => 'Project',
            'Quotes' => 'Quotes',
            'Calendar' => 'Calendar',
            'Accounts' => 'Accounts',
            'Contacts' => 'Contacts',
            'HelpDesk' => 'HelpDesk',
        ];

        $setype = $mapModuleToSetype[$module] ?? $module;

        $query = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_modcomments.userid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_modcomments.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_crmentity.smcreatorid',
                'vtiger_crmentity.smownerid',
                'vtiger_crmentity.deleted as crm_deleted',
                'vtiger_users.user_name as user_name',
                'vtiger_users.email1 as user_email',
            )
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0);

        $total = $query->count();
        $items = $query->forPage($page, $perPage)
            ->orderBy('vtiger_crmentity.createdtime', 'desc')
            ->get();

        $comments = $items->map(function ($row) {
            return new Comment(
                commentid: (int) $row->modcommentsid,
                commentcontent: (string) $row->commentcontent,
                related_to: (int) $row->related_to,
                parent_comments: $row->parent_comments ? (int) $row->parent_comments : null,
                userid: (int) $row->userid,
                createdtime: $row->createdtime,
                modifiedtime: $row->modifiedtime,
                is_private: $row->is_private === '1' ? 1 : 0,
                assigned_user_name: $row->user_name ?? null,
                assigned_user_email: $row->user_email ?? null,
                userName: $row->user_name ?? null,
            );
        });

        return new LengthAwarePaginator(
            $comments,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $commentId): ?Comment
    {
        if ($commentId <= 0) {
            throw new \InvalidArgumentException('commentId must be positive');
        }

        $row = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_modcomments.userid', '=', 'vtiger_users.id')
            ->leftJoin('vtiger_crmentity as related_entity', 'vtiger_modcomments.related_to', '=', 'related_entity.crmid')
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
                'related_entity.setype as related_module',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name"),
                DB::raw('vtiger_users.email1 as assigned_user_email')
            )
            ->first();
        if (!$row) {
            return null;
        }

        $rowData = (array) $row;
        $rowData['relatedModule'] = $row->related_module ?? null;

        // Map to entity if found, otherwise return null
        return CommentMapper::fromDatabase($rowData);
    }

    /**
     * {@inheritDoc}
     */
    public function create(
        string $module,
        int $relatedId,
        string $content,
        int $authenticatedUserId,
        ?int $parentId = null,
        ?bool $isPrivate = false
    ): Comment {
        throw new RuntimeException('Use CreateCommentUseCase for comment creation');
    }

    /**
     * {@inheritDoc}
     */
    public function update(
        int $commentId,
        int $authenticatedUserId,
        string $content,
        ?string $reasonToEdit = null
    ): bool {
        throw new RuntimeException('Use UpdateCommentUseCase for comment updates');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $commentId): bool
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException('Comment ID must be positive');
        }

        $updated = DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->where('crmid', $commentId)
            ->where('setype', 'ModComments')
            ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function countByRelatedId(int $relatedId, string $module): int
    {
        $mapModuleToSetype = [
            'Project' => 'Project',
            'Quotes' => 'Quotes',
            'Calendar' => 'Calendar',
            'Accounts' => 'Accounts',
            'Contacts' => 'Contacts',
            'HelpDesk' => 'HelpDesk',
        ];

        $setype = $mapModuleToSetype[$module] ?? $module;

        return DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function canAccess(int $commentId, int $userId): bool
    {
        $comment = $this->findById($commentId);

        if (! $comment) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateRequiredFields(array $data): void
    {
        $required = ['modcommentsid', 'commentcontent'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'modcommentsid' => $data['modcommentsid'],
            'commentcontent' => $data['commentcontent'],
            'related_to' => $data['related_to'] ?? null,
            'parent_comments' => $data['parent_comments'] ?? null,
            'customer' => $data['customer'] ?? null,
            'userid' => $data['userid'] ?? null,
            'reasontoedit' => $data['reasontoedit'] ?? null,
            'is_private' => $data['is_private'] ?? '0',
            'filename' => $data['filename'] ?? null,
            'related_email_id' => $data['related_email_id'] ?? null,
        ];
    }
}
