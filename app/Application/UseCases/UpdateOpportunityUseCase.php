<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateOpportunityRequest;
use App\Application\Repositories\OpportunityRepositoryInterface;

class UpdateOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    public function execute(UpdateOpportunityRequest $request, int $modifiedByUserId): bool
    {
        $data = [
            'potentialname' => $request->potentialname,
            'amount' => $request->amount,
            'closingdate' => $request->closingdate,
            'sales_stage' => $request->sales_stage,
            'probability' => $request->probability,
            'related_to' => $request->related_to,
            'description' => $request->description,
        ];

        if ($request->assigned_user_id !== null) {
            $data['assigned_user_id'] = $request->assigned_user_id;
        }

        return $this->opportunityRepository->update($request->id, $data, $modifiedByUserId);
    }
}