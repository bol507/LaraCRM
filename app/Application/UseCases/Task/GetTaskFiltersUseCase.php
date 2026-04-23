<?php

namespace App\Application\UseCases\Task;

use App\Application\DTOs\Task\GetTaskFiltersRequest;
use App\Application\DTOs\Task\GetTaskFiltersResponse;
use App\Application\Repositories\RoleRepositoryInterface;
use App\Application\UseCases\User\IsAdminUseCase;
use DomainException;

class GetTaskFiltersUseCase
{
    public function __construct(
        private readonly IsAdminUseCase $isAdminUseCase,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {}

    /**
     * Get task filters for a user
     * 
     * @param GetTaskFiltersRequest $request
     * @return GetTaskFiltersResponse
     * 
     * @throws DomainException If user is not authorized to view tasks
     */
    public function execute(GetTaskFiltersRequest $request): GetTaskFiltersResponse
    {
        $isAdmin = $this->isAdminUseCase->execute($request->currentUserId);
        
       
        if ($isAdmin) {
            return new GetTaskFiltersResponse(
                requestedUserId: $request->requestedUserId,
                subordinateIds: [],
                canViewAllUsers: true,
                canViewOtherUsers: true,
            );
        }

       
        if ($request->requestedUserId === null || 
            $request->requestedUserId === $request->currentUserId) {
            return new GetTaskFiltersResponse(
                requestedUserId: null,
                subordinateIds: [],
                canViewAllUsers: false,
                canViewOtherUsers: false,
            );
        }

       
        $subordinateIds = $this->roleRepository->findSubordinateUserIds(
            $request->currentUserRoleId
        );

       
        if (in_array($request->requestedUserId, $subordinateIds)) {
            return new GetTaskFiltersResponse(
                requestedUserId: $request->requestedUserId,
                subordinateIds: $subordinateIds,
                canViewAllUsers: false,
                canViewOtherUsers: true,
            );
        }

       
        throw new DomainException(
            'Do not have permission to view tasks for this user'
        );
    }
}