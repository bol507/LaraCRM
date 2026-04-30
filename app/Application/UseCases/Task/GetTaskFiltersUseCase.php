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

    public function execute(GetTaskFiltersRequest $request): GetTaskFiltersResponse
    {
        // 1. Admins have full access (separate logic or canViewAllUsers flag)
        $isAdmin = $this->isAdminUseCase->execute($request->currentUserId);
        if ($isAdmin) {
            return new GetTaskFiltersResponse(
                requestedUserId: $request->requestedUserId,
                subordinateIds: [],
                canViewAllUsers: true,
                canViewOtherUsers: true,
            );
        }

        // 2. Calculate subordinates for non-admins (Always, not only when filtering)
        $subordinateIds = $this->roleRepository->findSubordinateUserIds(
            $request->currentUserRoleId
        );

        // 3. Default view (Own Dashboard)
        if ($request->requestedUserId === null || 
            $request->requestedUserId === $request->currentUserId) {
            
            return new GetTaskFiltersResponse(
                requestedUserId: null,
                subordinateIds: $subordinateIds, // Returns the actual list of IDs
                canViewAllUsers: false,
                canViewOtherUsers: !empty($subordinateIds),
            );
        }

        // 4. View of a specific user
        if (in_array($request->requestedUserId, $subordinateIds, true)) {
            return new GetTaskFiltersResponse(
                requestedUserId: $request->requestedUserId,
                subordinateIds: [$request->requestedUserId], // Filter only by that user
                canViewAllUsers: false,
                canViewOtherUsers: true,
            );
        }

        // 5. Attempt to view an unauthorized user
        throw new DomainException('Do not have permission to view tasks for this user');
    }
}