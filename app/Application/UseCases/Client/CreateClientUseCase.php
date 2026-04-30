<?php

namespace App\Application\UseCases\Client;

use App\Application\DTOs\CreateClientRequest;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Infrastructure\Repositories\AccountRepository;
use App\Infrastructure\Repositories\BillingAddressRepository;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\ShippingAddressRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreateClientUseCase
{
    private const ID_LOCK_NAME = 'client_id_generation';

    private const ENTITY_SETYPE = 'Accounts';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly AccountRepository $account,
        private readonly BillingAddressRepository $billing,
        private readonly ShippingAddressRepository $shipping,
    ) {}

    /**
     * Execute the use case to create a client
     *
     * Orquestación de DML:
     * 1. Generar ID único
     * 2. Insertar vtiger_crmentity (metadata)
     * 3. Insertar vtiger_account (datos del cliente)
     * 4. Insertar vtiger_accountbillads (dirección de facturación)
     * 5. Insertar vtiger_accountshipads (dirección de envío)
     *
     * @param  CreateClientRequest  $request  The request with client data
     * @param  int|null  $userId  ID of the user creating the client
     * @return int ID of the created client
     *
     * @throws InvalidArgumentException If the client name is missing
     * @throws RuntimeException If the client creation fails
     */
    public function execute(CreateClientRequest $request, ?int $userId = null): int
    {
        if (empty(trim($request->accountname))) {
            throw new InvalidArgumentException('Client name is required');
        }

        $userId = $userId ?? CurrentUserService::idOr(1);

        return DB::connection('vtiger')->transaction(function () use ($request, $userId) {
            // 1. Generar ID único
            $accountId = $this->idGenerator->generateNextId(
                table: 'vtiger_account',
                column: 'accountid',
                lockName: self::ID_LOCK_NAME
            );

            $accountNo = $request->account_no ?? 'ACC-'.$accountId;

            // 2. Insertar vtiger_crmentity using generic use case
            $this->createEntity->execute(
                data: [
                    'label' => trim($request->accountname),
                    'description' => $request->description ?? '',
                    'smownerid' => $userId,
                    'smcreatorid' => $userId,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $accountId
            );

            // 3. Insertar vtiger_account
            $this->account->insert([
                'accountid' => $accountId,
                'account_no' => $accountNo,
                'accountname' => $request->accountname,
                'parentid' => $request->parentid ?? null,
                'account_type' => $request->account_type ?? 'Customer',
                'industry' => $request->industry ?? null,
                'annualrevenue' => $request->annualrevenue ?? null,
                'rating' => $request->rating ?? null,
                'ownership' => $request->ownership ?? null,
                'siccode' => $request->siccode ?? null,
                'tickersymbol' => $request->tickersymbol ?? null,
                'phone' => $request->phone ?? null,
                'otherphone' => $request->otherphone ?? null,
                'email1' => $request->email1 ?? null,
                'email2' => $request->email2 ?? null,
                'website' => $request->website ?? null,
                'fax' => $request->fax ?? null,
                'employees' => $request->employees ?? null,
                'emailoptout' => $request->emailoptout ?? '0',
                'notify_owner' => $request->notify_owner ?? '0',
                'isconvertedfromlead' => $request->isconvertedfromlead ?? '0',
                'tags' => $request->tags ?? null,
            ]);

            // 4. Insertar dirección de facturación
            $this->billing->upsert($accountId, [
                'bill_street' => $request->bill_street ?? null,
                'bill_city' => $request->bill_city ?? null,
                'bill_state' => $request->bill_state ?? null,
                'bill_code' => $request->bill_code ?? null,
                'bill_country' => $request->bill_country ?? null,
                'bill_pobox' => $request->bill_pobox ?? null,
            ]);

            // 5. Insertar dirección de envío
            $this->shipping->upsert($accountId, [
                'ship_street' => $request->ship_street ?? null,
                'ship_city' => $request->ship_city ?? null,
                'ship_state' => $request->ship_state ?? null,
                'ship_code' => $request->ship_code ?? null,
                'ship_country' => $request->ship_country ?? null,
                'ship_pobox' => $request->ship_pobox ?? null,
            ]);

            // Registrar actividad
            $this->logActivity($accountId, $userId);

            return $accountId;
        });
    }

    private function logActivity(int $clientId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'Accounts',
                crmid: $clientId,
                userId: $userId
            );
        } catch (\Exception $e) {
            //
        }
    }
}
