<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\DTOs\UpdateOpportunityRequest;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class UpdateOpportunityUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    /**
     * Execute the use case to update an opportunity
     * 
     * @param UpdateOpportunityRequest $request The request with update data
     * @param int|null $modifiedByUserId ID of the user performing the update
     * @return bool True if updated successfully
     * @throws \InvalidArgumentException If the opportunity ID is missing
     * @throws \Exception If the update fails
     */
    public function execute(UpdateOpportunityRequest $request, ?int $modifiedByUserId = null): bool
    {
        $opportunityId = $request->id;
        
        if (!$opportunityId) {
            throw new \InvalidArgumentException('Opportunity ID is required');
        }
        
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

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);
        
        // Update vtiger_crmentity.label if the name changed
        if (!empty($request->potentialname)) {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $opportunityId)
                ->where('setype', 'Potentials')
                ->update([
                    'label' => $request->potentialname,
                    'modifiedtime' => now(),
                ]);
        }

        // Update opportunity
        $updated = $this->opportunityRepository->update($opportunityId, $data, $userId);

        if (!$updated) {
            throw new \Exception('Failed to update opportunity');
        }

        // Register activity
        VtigerActivityTracker::updated(
            module: 'Potentials',
            crmid: $opportunityId,
            userId: $userId
        );

        return $updated;
    }
}
