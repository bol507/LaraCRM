<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateQuoteUseCase
{
    public function __construct(
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly QuoteRepository $quote,
    ) {}

    /**
     * Execute the quote update use case
     *
     * Orquestación de DML:
     * 1. Validar que la cotización existe
     * 2. Actualizar vtiger_quotes (datos de cotización)
     * 3. Actualizar vtiger_crmentity (label + modifiedby)
     *
     * @param  UpdateQuoteRequest  $request  The request with update data
     * @param  int|null  $modifiedByUserId  User performing the update
     * @return bool True if updated successfully
     *
     * @throws InvalidArgumentException If the quote ID is missing
     * @throws RuntimeException If the update fails
     */
    public function execute(UpdateQuoteRequest $request, ?int $modifiedByUserId = null): bool
    {
        $quoteId = $request->quoteid;

        if (! $quoteId) {
            throw new InvalidArgumentException('Quote ID is required');
        }

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        // Validar que la cotización existe
        if (! $this->quote->exists($quoteId)) {
            throw new InvalidArgumentException('Quote not found');
        }

        // Preparar datos para actualizar usando los campos del DTO
        $data = [
            'subject' => $request->subject,
            'potentialid' => $request->potentialid,
            'quotestage' => $request->quote_stage ?? null,
            'validtill' => $request->validtill ?? null,
            'accountid' => $request->accountid ?? null,
        ];

        // Filtrar valores null
        $data = array_filter($data, fn ($v) => $v !== null);

        $success = $this->quote->update($request, $userId);

        // Registrar actividad
        $this->logActivity($quoteId, $userId);

        return $success;
    }

    private function logActivity(int $quoteId, int $userId): void
    {
        try {
            VtigerActivityTracker::updated(
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
