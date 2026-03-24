<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class CreateOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    /**
     * Executes the opportunity creation use case.
     *
     * Creates a new opportunity with the provided data, validates required fields,
     * synchronizes the CRM entity record with the opportunity name, and logs the
     * creation activity to Vtiger.
     *
     * @param CreateOpportunityRequest $request The opportunity creation request containing opportunity data
     * @param int|null $createdByUserId The ID of the user creating the opportunity (defaults to current user)
     * @return int The ID of the newly created opportunity
     *
     * @throws \InvalidArgumentException When the opportunity name is missing
     * @throws \Exception When opportunity creation fails
     */
    public function execute(CreateOpportunityRequest $request, ?int $createdByUserId = null): int
    {
        if (empty($request->potentialname)) {
            throw new \InvalidArgumentException('Opportunity name is required');
        }

        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        $opportunityId = $this->opportunityRepository->create($request, $userId);
        
        if (!$opportunityId || !is_numeric($opportunityId)) {
            throw new \Exception('Failed to create opportunity');
        }

        if (!empty($request->potentialname)) {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->updateOrInsert(
                    ['crmid' => $opportunityId, 'setype' => 'Potentials'],
                    [
                        'label' => $request->potentialname,
                        'createdtime' => now(),
                        'modifiedtime' => now(),
                        'deleted' => 0,
                    ]
                );
        }

        VtigerActivityTracker::created(
            module: 'Potentials',
            crmid: (int) $opportunityId,
            userId: $userId
        );
        
        return $opportunityId;
    }
}
