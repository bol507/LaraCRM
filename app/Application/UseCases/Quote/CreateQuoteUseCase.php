<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreateQuoteUseCase
{
    private const ID_LOCK_NAME = 'quote_id_generation';

    private const ENTITY_SETYPE = 'Quotes';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly QuoteRepository $quote,
    ) {}

    /**
     * Execute the quote creation use case
     *
     * Orquestación de DML:
     * 1. Generar ID único
     * 2. Insertar vtiger_crmentity (metadata)
     * 3. Insertar vtiger_quotes (datos de cotización)
     *
     * Nota: Los items (vtiger_inventoryproductrel) se manejan por separado
     *
     * @param  CreateQuoteRequest  $request  The quote creation request
     * @param  int|null  $createdByUserId  User creating the quote
     * @return int The ID of the created quote
     *
     * @throws InvalidArgumentException When the quote has no items
     * @throws RuntimeException When quote creation fails
     */
    public function execute(CreateQuoteRequest $request, ?int $createdByUserId = null): int
    {
        if (empty($request->subject)) {
            throw new InvalidArgumentException('Quote subject is required');
        }

        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        return DB::connection('vtiger')->transaction(function () use ($request, $userId) {
            // 1. Generar ID único
            $quoteId = $this->idGenerator->generateNextId(
                table: 'vtiger_quotes',
                column: 'quoteid',
                lockName: self::ID_LOCK_NAME
            );

            // 2. Insertar vtiger_crmentity using generic use case
            $this->createEntity->execute(
                data: [
                    'label' => trim($request->subject),
                    'description' => $request->description ?? '',
                    'smownerid' => $request->assigned_user_id ?? $userId,
                    'smcreatorid' => $userId,
                ],
                setype: self::ENTITY_SETYPE,
                table: 'vtiger_crmentity',
                userId: $userId,
                crmId: $quoteId
            );

            // 3. Insertar vtiger_quotes
            $this->quote->insert([
                'quoteid' => $quoteId,
                'quote_no' => $this->quote->getNextQuoteNumber(),
                'subject' => $request->subject,
                'potentialid' => $request->potentialid ?? null,
                'quotestage' => $request->quotestage ?? 'Draft',
                'validtill' => $request->validtill ?? null,
                'contactid' => $request->contactid ?? null,
                'accountid' => $request->accountid ?? null,
                'subtotal' => $request->subtotal ?? null,
                'carrier' => $request->carrier ?? null,
                'shipping' => $request->shipping ?? null,
                'inventorymanager' => $request->inventorymanager ?? null,
                'type' => $request->type ?? null,
                'adjustment' => $request->adjustment ?? null,
                'total' => $request->total ?? null,
                'taxtype' => $request->taxtype ?? null,
                'discount_percent' => $request->discount_percent ?? null,
                'discount_amount' => $request->discount_amount ?? null,
                's_h_amount' => $request->s_h_amount ?? null,
                'terms_conditions' => $request->terms_conditions ?? null,
                'currency_id' => $request->currency_id ?? 1,
                'conversion_rate' => $request->conversion_rate ?? 1,
                
            ], $request->items ?? []);

            // Registrar actividad
            $this->logActivity($quoteId, $userId);

            return $quoteId;
        });
    }

    private function logActivity(int $quoteId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'Quotes',
                crmid: $quoteId,
                userId: $userId
            );
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'quoteId' => $quoteId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
