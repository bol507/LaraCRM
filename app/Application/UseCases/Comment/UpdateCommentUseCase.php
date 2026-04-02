<?php

namespace App\Application\UseCases\Comment;

use App\Infrastructure\Repositories\CommentRepository;
use App\Services\VtigerActivityTracker;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateCommentUseCase
{
    public function __construct(
        private readonly CommentRepository $comment,
    ) {}

    /**
     * Execute the update comment use case
     *
     * Orquestación de DML:
     * 1. Validar que el comentario existe y el usuario tiene permisos
     * 2. Actualizar vtiger_modcomments (content + reasontoedit)
     *
     * @param  int  $commentId  Unique identifier of the comment to update
     * @param  int  $authenticatedUserId  ID of the user attempting the update
     * @param  string  $content  New comment content
     * @param  string|null  $reasonToEdit  Optional reason for editing (audit trail)
     * @param  string|null  $module  Module for activity logging
     * @param  int|null  $relatedId  Related record ID for activity logging
     * @return bool True if update was successful
     *
     * @throws InvalidArgumentException If parameters are invalid
     * @throws DomainException If user is not authorized to edit
     * @throws RuntimeException If persistence operation fails
     */
    public function execute(
        int $commentId,
        int $authenticatedUserId,
        string $content,
        ?string $reasonToEdit = null,
        ?string $module = null,
        ?int $relatedId = null
    ): bool {
        // Validar contenido
        if (trim($content) === '') {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }

        if (mb_strlen($content) > 65000) {
            throw new InvalidArgumentException('Comment exceeds maximum allowed length');
        }

        // Validar que el comentario existe
        $existingComment = $this->comment->findById($commentId);
        if (! $existingComment) {
            throw new InvalidArgumentException('Comment not found');
        }

        // Validar que el usuario es el autor
        if ((int) $existingComment['userid'] !== $authenticatedUserId) {
            throw new DomainException('Only the author can edit this comment');
        }

        // Actualizar en transacción
        DB::connection('vtiger')->transaction(function () use ($commentId, $content, $reasonToEdit) {
            // 1. Actualizar vtiger_modcomments
            $this->comment->updateComment($commentId, [
                'commentcontent' => $content,
                'reasontoedit' => $reasonToEdit,
            ]);
        });

        // Log actividad si se proporcionó
        if ($module && $relatedId) {
            $this->logActivity($commentId, $authenticatedUserId, $module, $relatedId, $reasonToEdit);
        }

        return true;
    }

    private function logActivity(
        int $commentId,
        int $userId,
        string $module,
        int $relatedId,
        ?string $reason
    ): void {
        try {
            VtigerActivityTracker::updated(
                module: $module,
                crmid: $relatedId,
                userId: $userId
            );

            Log::info('Comment edited', [
                'comment_id' => $commentId,
                'module' => $module,
                'related_id' => $relatedId,
                'user_id' => $userId,
                'reason' => $reason,
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log comment edit activity: '.$e->getMessage(), [
                'comment_id' => $commentId,
            ]);
        }
    }
}
