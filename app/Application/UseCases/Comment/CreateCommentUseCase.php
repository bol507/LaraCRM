<?php

namespace App\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Domain\Entities\Comment;
use App\Infrastructure\Repositories\CommentRepository;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateCommentUseCase
{
    private const ID_LOCK_NAME = 'comment_id_generation';

    private const ENTITY_SETYPE = 'ModComments';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly CommentRepository $comment,
    ) {}

    /**
     * Execute the create comment use case
     *
     * Orquestación de DML:
     * 1. Generar ID único
     * 2. Insertar vtiger_crmentity (metadata)
     * 3. Insertar vtiger_modcomments (datos del comentario)
     *
     * @param  CreateCommentRequest  $request  Validated request data
     * @return Comment The newly created comment entity
     *
     * @throws ValidationException If validation fails
     * @throws InvalidArgumentException If business rules are violated
     * @throws \RuntimeException If persistence fails
     */
    public function execute(CreateCommentRequest $request): Comment
    {
        $this->validateBusinessRules($request);

        $userId = $request->userId ?? CurrentUserService::idOr(1);
        $now = now()->format('Y-m-d H:i:s');

        // Generar ID y crear comentario en transacción
        $commentId = $this->idGenerator->generateNextId(
            table: 'vtiger_modcomments',
            column: 'modcommentsid',
            lockName: self::ID_LOCK_NAME
        );

        // Insertar en transacción
        $comment = DB::connection('vtiger')->transaction(function () use ($request, $userId, $commentId, $now) {
            // 1. Insertar vtiger_crmentity using generic use case
            $this->createEntity->execute(
                data: [
                    'label' => substr(trim($request->content), 0, 100),
                    'description' => substr($request->content, 0, 100),
                    'smownerid' => $userId,
                    'smcreatorid' => $userId,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $commentId
            );

            // 2. Insertar vtiger_modcomments
            $this->comment->insert([
                'modcommentsid' => $commentId,
                'commentcontent' => $request->content,
                'related_to' => $request->relatedId,
                'parent_comments' => $request->parentCommentId,
                'userid' => $userId,
                'is_private' => $request->isPrivate ? '1' : '0',
            ]);

            // 3. Buscar datos del usuario para crear la entidad
            $user = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $userId)
                ->first();

            $userName = $user ? trim("{$user->first_name} {$user->last_name}") : 'Usuario';
            $userEmail = $user->email1 ?? '';

            // Retornar datos para crear entidad
            return [
                'modcommentsid' => $commentId,
                'related_to' => $request->relatedId,
                'commentcontent' => $request->content,
                'userid' => $userId,
                'parent_comments' => $request->parentCommentId,
                'is_private' => $request->isPrivate ? '1' : '0',
                'assigned_user_name' => $userName,
                'assigned_user_email' => $userEmail,
            ];
        });

        // Crear entidad del dominio
        $entity = new Comment(
            commentid: $commentId,
            commentcontent: $request->content,
            related_to: $request->relatedId,
            parent_comments: $request->parentCommentId,
            userid: $userId,
            is_private: $request->isPrivate ? 1 : 0,
            createdtime: $now,
        );

        // Registrar actividad
        $this->logActivity($commentId, $userId);

        return $entity;
    }

    /**
     * Validate domain-specific business rules
     *
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    private function validateBusinessRules(CreateCommentRequest $request): void
    {
        if (trim($request->content) === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment content cannot be empty'],
            ]);
        }

        if (mb_strlen($request->content) > 65000) {
            throw ValidationException::withMessages([
                'content' => ['Comment exceeds maximum allowed length'],
            ]);
        }

        $allowedModules = ['Project', 'Quotes', 'Calendar', 'Accounts', 'Contacts', 'HelpDesk'];
        if (! in_array($request->module, $allowedModules, true)) {
            throw new InvalidArgumentException(
                "Module '{$request->module}' not allowed for comments. ".
                'Valid modules: '.implode(', ', $allowedModules)
            );
        }

        if ($request->relatedId <= 0) {
            throw new InvalidArgumentException('Related record ID must be positive');
        }

        if ($request->userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        if ($request->parentCommentId !== null && $request->parentCommentId <= 0) {
            throw new InvalidArgumentException('Parent comment ID must be positive');
        }
    }

    private function logActivity(int $commentId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'ModComments',
                crmid: $commentId,
                userId: $userId
            );
        } catch (\Exception $e) {
            Log::error('Failed to log activity for comment creation', [
                'commentId' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
