<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
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
     * DML orchestration:
     * 1. Validate that the quote exists
     * 2. Update vtiger_quotes (quote data)
     * 3. Update vtiger_crmentity (label + modifiedby)
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

        // Validate that the quote exists
        if (! $this->quote->exists($quoteId)) {
            throw new InvalidArgumentException('Quote not found');
        }

        // Prepare data for update using DTO fields
        $data = [
            'subject' => $request->subject,
            'potentialid' => $request->potentialid,
            'quotestage' => $request->quote_stage ?? null,
            'validtill' => $request->validtill ?? null,
            'accountid' => $request->accountid ?? null,
        ];

        // Filter out null values
        $data = array_filter($data, fn ($v) => $v !== null);

        $success = $this->quote->update($request, $userId);

        // Register activity
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
            // Silently fail - activity logging is non-critical
        }
    }
}