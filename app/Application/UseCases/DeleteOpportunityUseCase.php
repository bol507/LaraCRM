<?php

namespace App\Application\UseCases;

use App\Application\Repositories\OpportunityRepositoryInterface;

class DeleteOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    public function execute(int $id, int $deletedByUserId): bool
    {
        return $this->opportunityRepository->delete($id, $deletedByUserId);
    }
}