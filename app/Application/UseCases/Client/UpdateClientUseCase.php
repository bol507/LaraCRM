<?php

namespace App\Application\UseCases\Client;

use App\Application\DTOs\UpdateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class UpdateClientUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    /**
     * Execute the use case to update a client
     * 
     * @param UpdateClientRequest $request The request with client data
     * @param int $userId ID of the user performing the update
     * @return bool True if updated successfully
     * @throws \InvalidArgumentException If the client ID is missing
     * @throws \Exception If the client update fails
     */
    public function execute(UpdateClientRequest $request, int $userId): bool
    {
        $clientId = $request->id;
        
        if (!$clientId) {
            throw new \InvalidArgumentException('Client ID is required');
        }

        // Determine the modifying user (JWT or parameter)
        $userId = $userId ?? CurrentUserService::idOr(1);

        // Update vtiger_crmentity.label if the account name changed
        if (!empty($request->accountname)) {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $clientId)
                ->where('setype', 'Accounts')
                ->update([
                    'label' => $request->accountname,
                    'modifiedtime' => now(),
                ]);
        }

        // Update client in the repository
        $updated = $this->clientRepository->update($request, $userId);
        
        if (!$updated) {
            throw new \Exception('Failed to update client');
        }

        // Register activity in vtiger_modtracker_basic
        VtigerActivityTracker::updated(
            module: 'Accounts', // Correct module for clients
            crmid: $clientId,
            userId: $userId
        );

        return $updated;
    }
}