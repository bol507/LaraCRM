<?php

namespace App\Application\UseCases\Client;

use App\Application\DTOs\UpdateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\AccountRepository;
use App\Infrastructure\Repositories\BillingAddressRepository;
use App\Infrastructure\Repositories\ShippingAddressRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateClientUseCase
{
    private const ENTITY_SETYPE = 'Accounts';

    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly AccountRepository $account,
        private readonly BillingAddressRepository $billing,
        private readonly ShippingAddressRepository $shipping,
    ) {}

    /**
     * Execute the use case to update a client
     *
     * Orquestación de DML:
     * 1. Validar que el cliente existe
     * 2. Actualizar vtiger_account (datos del cliente)
     * 3. Actualizar vtiger_crmentity (label + modifiedtime)
     * 4. Actualizar vtiger_accountbillads (dirección de facturación)
     * 5. Actualizar vtiger_accountshipads (dirección de envío)
     *
     * @param  UpdateClientRequest  $request  The request with client data
     * @param  int|null  $userId  ID of the user performing the update
     * @return bool True if updated successfully
     *
     * @throws InvalidArgumentException If the client ID is missing
     * @throws RuntimeException If the client update fails
     */
    public function execute(UpdateClientRequest $request, ?int $userId = null): bool
    {
        $clientId = $request->id;

        if (! $clientId) {
            throw new InvalidArgumentException('Client ID is required');
        }

        $userId = $userId ?? CurrentUserService::idOr(1);

        // Validar que el cliente existe
        if (! $this->clientRepository->findById($clientId)) {
            throw new InvalidArgumentException('Client not found');
        }

        // Actualizar en transacción
        DB::connection('vtiger')->transaction(function () use ($request, $clientId, $userId) {
            // 1. Actualizar vtiger_account
            $this->account->updateAccount($clientId, [
                'accountname' => $request->accountname,
                'account_no' => $request->account_no,
                'account_type' => $request->account_type,
                'industry' => $request->industry,
                'annualrevenue' => $request->annualrevenue,
                'rating' => $request->rating,
                'ownership' => $request->ownership,
                'siccode' => $request->siccode,
                'tickersymbol' => $request->tickersymbol,
                'phone' => $request->phone,
                'otherphone' => $request->otherphone,
                'email1' => $request->email1,
                'email2' => $request->email2,
                'website' => $request->website,
                'fax' => $request->fax,
                'employees' => $request->employees,
                'emailoptout' => $request->emailoptout,
                'notify_owner' => $request->notify_owner,
                'isconvertedfromlead' => $request->isconvertedfromlead,
                'tags' => $request->tags,
            ]);

            // 2. Actualizar vtiger_crmentity (label + description)
            $crmentityData = [];
            if ($request->accountname) {
                $crmentityData['label'] = trim($request->accountname);
            }
            if ($request->description !== null) {
                $crmentityData['description'] = $request->description;
            }
            if (! empty($crmentityData)) {
                $this->updateEntity->execute(
                    crmId: $clientId,
                    data: $crmentityData,
                    userId: $userId
                );
            }

            // 3. Actualizar dirección de facturación
            $this->billing->upsert($clientId, [
                'bill_street' => $request->bill_street,
                'bill_city' => $request->bill_city,
                'bill_state' => $request->bill_state,
                'bill_code' => $request->bill_code,
                'bill_country' => $request->bill_country,
                'bill_pobox' => $request->bill_pobox,
            ]);

            // 4. Actualizar dirección de envío
            $this->shipping->upsert($clientId, [
                'ship_street' => $request->ship_street,
                'ship_city' => $request->ship_city,
                'ship_state' => $request->ship_state,
                'ship_code' => $request->ship_code,
                'ship_country' => $request->ship_country,
                'ship_pobox' => $request->ship_pobox,
            ]);
        });

        // Registrar actividad
        $this->logActivity($clientId, $userId);

        return true;
    }

    private function logActivity(int $clientId, int $userId): void
    {
        try {
            VtigerActivityTracker::updated(
                module: 'Accounts',
                crmid: $clientId,
                userId: $userId
            );
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'clientId' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
