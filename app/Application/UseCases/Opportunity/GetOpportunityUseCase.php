<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Domain\Entities\Opportunity;

/**
 * Get a single Opportunity by ID
 */
class GetOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    /**
     * Execute the use case to retrieve a single opportunity by ID.
     *
     * @param int $id The opportunity ID (potentialid)
     * @return Opportunity|null The Opportunity entity if found, null otherwise
     */
    public function execute(int $id): ?Opportunity
    {
        return $this->opportunityRepository->findById($id);
    }
}
