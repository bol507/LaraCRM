<?php

namespace App\Application\UseCases\Calendar;

use App\Application\DTOs\Calendar\GetActivityFiltersRequest;
use App\Application\DTOs\Calendar\GetActivityFiltersResponse;
use App\Application\Repositories\RoleRepositoryInterface;
use App\Application\UseCases\User\IsAdminUseCase;
use DomainException;
use Illuminate\Support\Facades\Log;

class GetActivityFiltersUseCase
{
    public function __construct(
        private readonly IsAdminUseCase $isAdminUseCase,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {}

    public function execute(GetActivityFiltersRequest $request): GetActivityFiltersResponse
    {
        // 1. Admins have full access (separate logic or canViewAllUsers flag)
        $isAdmin = $this->isAdminUseCase->execute($request->currentUserId);
        
        if ($isAdmin) {
            return new GetActivityFiltersResponse(
                requestedUserId: $request->requestedUserId,
                subordinateIds: null, 
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
            
            return new GetActivityFiltersResponse(
                requestedUserId: null,
                subordinateIds: $subordinateIds, // Returns the actual list of IDs
                canViewAllUsers: false,
                canViewOtherUsers: !empty($subordinateIds),
            );
        }

        // 4. View of a specific user
        if (in_array($request->requestedUserId, $subordinateIds, true)) {
            return new GetActivityFiltersResponse(
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