<?php

namespace App\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\Repositories\CommentRepositoryInterface;
use App\Domain\Entities\Comment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateCommentUseCase
{
    public function __construct(
        private readonly CommentRepositoryInterface $repository
    ) {}

    /**
     * Execute the create comment use case
     * 
     * @param CreateCommentRequest $request Validated request data
     * @return Comment The newly created comment entity
     * 
     * @throws ValidationException If validation fails
     * @throws \InvalidArgumentException If business rules are violated
     * @throws \RuntimeException If persistence fails
     */
    public function execute(CreateCommentRequest $request): Comment
    {
        //  Additional business rule validation (beyond request validation)
        $this->validateBusinessRules($request);

        //  CORRECTION: Extract parameters from DTO and pass them individually to repository
        return $this->repository->create(
            module: $request->module,              //  string
            relatedId: $request->relatedId,         //  int
            content: $request->content,             //  string
            authenticatedUserId: $request->userId,  //  int
            parentId: $request->parentCommentId,    //  ?int
            isPrivate: $request->isPrivate ?? false //  ?bool
        );
    }

    /**
     * Validate domain-specific business rules
     * 
     * @param CreateCommentRequest $request
     * @throws ValidationException
     * @throws \InvalidArgumentException
     */
    private function validateBusinessRules(CreateCommentRequest $request): void
    {
        //  Validate that content is not empty after trim
        if (trim($request->content) === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment content cannot be empty']
            ]);
        }

        //  Validate maximum length (consistent with DB: TEXT = 65,535 bytes)
        if (mb_strlen($request->content) > 65000) {
            throw ValidationException::withMessages([
                'content' => ['Comment exceeds maximum allowed length']
            ]);
        }

        //  Validate that module is allowed
        $allowedModules = ['Project', 'Quotes', 'Calendar', 'Accounts', 'Contacts', 'HelpDesk'];
        if (!in_array($request->module, $allowedModules, true)) {
            throw new \InvalidArgumentException(
                "Module '{$request->module}' not allowed for comments. " .
                "Valid modules: " . implode(', ', $allowedModules)
            );
        }

        //  Validate that relatedId is positive
        if ($request->relatedId <= 0) {
            throw new \InvalidArgumentException('Related record ID must be positive');
        }

        //  Validate that userId is positive
        if ($request->userId <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }

        //  Validate that parentCommentId, if exists, is positive
        if ($request->parentCommentId !== null && $request->parentCommentId <= 0) {
            throw new \InvalidArgumentException('Parent comment ID must be positive');
        }

        //  Validate that user has permission to comment on this record
        // (This validation might require a repository query or permissions service)
        if (!$this->canUserCommentOnRecord($request->userId, $request->module, $request->relatedId)) {
            throw new \InvalidArgumentException(
                'You do not have permission to comment on this record'
            );
        }
    }

    /**
     * Check if a user can comment on a specific record
     * 
     * @param int $userId
     * @param string $module
     * @param int $recordId
     * @return bool
     */
    private function canUserCommentOnRecord(int $userId, string $module, int $recordId): bool
    {
        //  Basic implementation: allow if user is owner or admin
        // In production, this should consult a more sophisticated permissions service
        
        // For now, allow all authenticated comments
        // (Real validation will depend on your Vtiger business logic)
        return true;
    }
}