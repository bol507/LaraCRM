<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class VtigerCommentRepository implements CommentRepositoryInterface
{
    public function getByRelatedId(int $relatedId, string $module): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
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
            ->where('vtiger_modcomments.related_to', $relatedId)
            ->where('vtiger_crmentity.deleted', 0)
            ->orderBy('vtiger_crmentity.createdtime', 'desc');

        $total = $query->count();
        $comments = $query->get()->map(function($row) {
            return new Comment(
                commentid: $row->commentid,
                commentcontent: $row->commentcontent,
                related_to: $row->related_to,
                parent_comments: $row->parent_comments,
                customer: $row->customer,
                userid: $row->userid,
                reasontoedit: $row->reasontoedit,
                is_private: $row->is_private,
                filename: $row->filename,
                related_email_id: $row->related_email_id,
                createdtime: $row->createdtime,
                modifiedtime: $row->modifiedtime,
                assigned_user_name: $row->assigned_user_name,
                assigned_user_email: $row->assigned_user_email
            );
        });

        return new LengthAwarePaginator($comments, $total, 20, 1, [
            'path' => request()->url(),
        ]);
    }

    public function findById(int $commentId): ?Comment
    {
        $comment = DB::connection('vtiger')
            ->table('vtiger_modcomments')
            ->join('vtiger_crmentity', 'vtiger_modcomments.modcommentsid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
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
            ->where('vtiger_modcomments.modcommentsid', $commentId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (!$comment) {
            return null;
        }

        return new Comment(
            commentid: $comment->commentid,
            commentcontent: $comment->commentcontent,
            related_to: $comment->related_to,
            parent_comments: $comment->parent_comments,
            customer: $comment->customer,
            userid: $comment->userid,
            reasontoedit: $comment->reasontoedit,
            is_private: $comment->is_private,
            filename: $comment->filename,
            related_email_id: $comment->related_email_id,
            createdtime: $comment->createdtime,
            modifiedtime: $comment->modifiedtime,
            assigned_user_name: $comment->assigned_user_name,
            assigned_user_email: $comment->assigned_user_email
        );
    }

    public function create(array $data): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            // Insertar en vtiger_crmentity
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $crmid,
                    'smownerid' => $data['userid'],
                    'smcreatorid' => $data['userid'],
                    'setype' => 'ModComments',
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'deleted' => 0,
                ]);

            // Insertar en vtiger_modcomments
            DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->insert([
                    'modcommentsid' => $crmid,
                    'commentcontent' => $data['commentcontent'],
                    'related_to' => $data['related_to'],
                    'parent_comments' => $data['parent_comments'] ?? null, 
                    'customer' => $data['customer'] ?? null,               
                    'userid' => $data['userid'] ?? null,                  
                    'reasontoedit' => null,
                    'is_private' => $data['is_private'] ?? 0,
                    'filename' => $data['filename'] ?? null,
                    'related_email_id' => $data['related_email_id'] ?? null,
                ]);

            DB::connection('vtiger')->commit();
            return $crmid;

        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function update(int $commentId, array $data): bool
    {
        try {
            DB::connection('vtiger')
                ->table('vtiger_modcomments')
                ->where('modcommentsid', $commentId)
                ->update([
                    'commentcontent' => $data['commentcontent'] ?? DB::raw('commentcontent'),
                    'reasontoedit' => $data['reasontoedit'] ?? null,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $commentId)
                ->update([
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function delete(int $commentId): bool
    {
        try {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $commentId)
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}