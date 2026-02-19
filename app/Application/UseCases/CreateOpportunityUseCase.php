<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;

class CreateOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    public function execute(CreateOpportunityRequest $request, int $createdByUserId): int
{
  
    $opportunityData = new CreateOpportunityRequest(
        potentialname: $request->potentialname,
        amount: $request->amount,
        closingdate: $request->closingdate,
        sales_stage: $request->sales_stage,
        probability: $request->probability,
        related_to: $request->related_to, 
        assigned_user_id: $request->assigned_user_id ?? $createdByUserId, 
        description: $request->description,
    );

    return $this->opportunityRepository->create($opportunityData, $createdByUserId);
}
}
