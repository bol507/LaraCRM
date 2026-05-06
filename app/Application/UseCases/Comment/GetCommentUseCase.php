<?php

namespace App\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CommentDto;
use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use App\Services\CurrentUserService;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Get Comment Use Case
 * 
 * Orchestrates the retrieval of a single comment by its ID.
 * 
 * Responsibilities:
 * - Validate that the comment ID is valid
 * - Verify that the comment exists and is not deleted
 * - Check that the requesting user has permission to view the comment
 * - Return the comment entity or throw appropriate exceptions
 * 
 * Business rules enforced:
 * - Comment must exist and not be deleted
 * - User must have permission to view the comment (public or author)
 * - Private comments are only visible to author or admins
 * 
 * This use case is part of the Application layer and should not contain:
 * - HTTP-specific logic (request/response handling)
 * - Database-specific queries (SQL, joins, etc.)
 * - UI-specific formatting (dates, localization, etc.)
 * 
 * @package App\Application\UseCases\Comment
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\CommentRepositoryInterface
 * @see \App\Domain\Entities\Comment
 * @see \App\Http\Controllers\Api\CommentController
 */
class GetCommentUseCase
{

    private readonly CommentRepositoryInterface $repository;


    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the get comment use case
     * 
     * Retrieves a single comment by its unique identifier after validating:
     * - Comment ID is valid (positive integer)
     * - Comment exists and is not deleted
     * - Requesting user has permission to view the comment
     * 
     * @param int $commentId Unique identifier of the comment to retrieve
     * @param int $userId ID of the user requesting the comment (for authorization)
     * 
     * @return Comment The comment entity if found and accessible
     * 
     * @throws InvalidArgumentException If comment ID is invalid
     * @throws RuntimeException If comment is not found
     * @throws InvalidArgumentException If user does not have permission to view
     * 
     * @example
     * // Get comment with user authorization
     * $comment = $useCase->execute(commentId: 456, userId: 123);
     * return response()->json($comment->toArray());
     * 
     * @example
     * // Handle exceptions in controller
     * try {
     *     $comment = $useCase->execute(456, 123);
     * } catch (RuntimeException $e) {
     *     return response()->json(['error' => $e->getMessage()], 404);
     * }
     */
    public function execute(int $commentId, int $userId): array
    {

        $this->validateParameters($commentId, $userId);
        $comment = $this->repository->findById($commentId);

        if (!$comment) {
            throw new RuntimeException("Comment {$commentId} not found or has been deleted");
        }
        $dto = CommentDto::fromDatabaseRow($comment);
        $userId = $userId ?? CurrentUserService::idOr(1);
        if (!$userId) {
            throw new UnauthorizedException("Authentication required to view comments");
        }
        $this->verifyViewPermission($dto, $userId);
        return $dto->toArray();
    }

    /**
     * Validate input parameters for the use case
     * 
     * @param int $commentId Comment ID to validate
     * @param int $userId User ID to validate
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $commentId, int $userId): void
    {
        if ($commentId <= 0) {
            throw new InvalidArgumentException(
                "Comment ID must be a positive integer, got {$commentId}"
            );
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "User ID must be a positive integer, got {$userId}"
            );
        }
    }

    private function isInternalUser(int $userId): bool
    {
        $payload = CurrentUserService::payload();

        if (!$payload) {
            return false; // Sin payload válido → asumir externo (más seguro)
        }

        // Regla 1: Flag directo de admin
        if (!empty($payload->is_admin) && $payload->is_admin === true) {
            return true;
        }

        // Regla 2: Role ID jerárquico (ajusta a tu estructura de vtiger_role)
        $roleId = $payload->role_id ?? null;
        if ($roleId) {
            // Ejemplo: H1=Organization, H2=CEO, H3=Manager, H5=Producción, H8=Compras
            $internalRoleIds = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7', 'H8'];
            if (in_array($roleId, $internalRoleIds, true)) {
                return true;
            }
        }

        // Regla 3: Nombre de rol legible (fallback)
        $roleName = $payload->rolename ?? null;
        if ($roleName) {
            $internalRoleNames = [
                'Admin',
                'Administrator',
                'CEO',
                'Manager',
                'Supervisor',
                'Producción',
                'Compras',
                'Operaciones',
                'Coordinador'
            ];
            if (in_array($roleName, $internalRoleNames, true)) {
                return true;
            }
        }

        return false; // Por defecto: no es interno
    }

    /**
     * Verify if a user can view a comment.
     * Works with CommentDto (data from database row).
     */
    private function verifyViewPermission(CommentDto $comment, int $userId): void
    {
        // Regla 1: El autor siempre puede ver su comentario
        if ($comment->userId === $userId) {
            return;
        }

        // Regla 2: Comentarios públicos son visibles para todos los autenticados
        if (!$comment->isPrivate) {
            return;
        }

        // Regla 3: Comentarios privados solo para usuarios internos
        if ($this->isInternalUser($userId)) {
            return;
        }

        // Denegar acceso
        throw new InvalidArgumentException(
            "User {$userId} is not authorized to view comment {$comment->id}. " .
                "This comment is private and you are not an internal user."
        );
    }

    /**
     * Check if user has access to the related record
     * 
     * @param int $relatedId ID of the related record
     * @param int $userId User ID to check
     * @return bool True if user has access, false otherwise
     * 
     * @internal Implementation depends on your permission system
     */
    private function hasAccessToRelatedRecord(int $relatedId, int $userId): bool
    {

        return true;
    }
}
