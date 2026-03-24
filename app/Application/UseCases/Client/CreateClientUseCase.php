<?php

namespace App\Application\UseCases\Client;

use App\Application\DTOs\CreateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class CreateClientUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    /**
     * Execute the use case to create a client
     * 
     * @param CreateClientRequest $request The request with client data
     * @param int $userId ID of the user creating the client
     * @return int ID of the created client
     * @throws \InvalidArgumentException If the client name is missing
     * @throws \Exception If the client creation fails
     */
    public function execute(CreateClientRequest $request, int $userId): int
    {
        if (empty($request->accountname)) {
            throw new \InvalidArgumentException('Client name is required');
        }

        $userId = $userId ?? CurrentUserService::idOr(1);

        // Create client in the repository (returns the ID)
        $clientId = $this->clientRepository->create($request, $userId);

        if (!$clientId || !is_numeric($clientId)) {
            throw new \Exception('Failed to create client');
        }

        // Synchronize vtiger_crmentity.label with accountname
        // This is crucial for the activity panel to display the correct name
        DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->updateOrInsert(
                ['crmid' => $clientId, 'setype' => 'Accounts'],
                [
                    'label' => $request->accountname,
                    'createdtime' => now(),
                    'modifiedtime' => now(),
                    'deleted' => 0,
                ]
            );

        // Register activity in vtiger_modtracker_basic
        VtigerActivityTracker::created(
            module: 'Accounts',
            crmid: (int) $clientId,
            userId: $userId
        );

        return $clientId;
    }
}