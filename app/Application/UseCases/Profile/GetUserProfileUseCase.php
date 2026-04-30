<?php
// app/Application/UseCases/Profile/GetUserProfileUseCase.php

namespace App\Application\UseCases\Profile;

use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;

class GetUserProfileUseCase
{
    public function __construct(
        private readonly RoleProfileAssignmentRepositoryInterface $repository
    ) {}

    /**
     * Get the profile ID assigned to a user's role.
     * 
     * @param int $userId
     * @return int|null Profile ID if assigned, null if no profile linked
     */
    public function execute(int $userId): ?int
    {
        return $this->repository->findProfileIdByUserId($userId);
    }
}