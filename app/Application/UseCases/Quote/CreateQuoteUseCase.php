<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\Quote\QuoteResponse;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class CreateQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    /**
     * Executes the quote creation use case.
     *
     * Creates a new quote with the provided data, ensures the quote has at least one item,
     * creates a CRM entity record in Vtiger, and logs the creation activity.
     *
     * @param CreateQuoteRequest $request The quote creation request containing quote data and items
     * @param int $createdByUserId The ID of the user creating the quote
     * @return QuoteResponse|bool The created quote data or false if the creation fails
     *
     * @throws \InvalidArgumentException When the quote has no items
     * @throws \Exception When quote creation fails
     */
    public function execute(CreateQuoteRequest $request, int $createdByUserId = null): QuoteResponse|bool
    {
        
        
        if (empty($request->items)) {
            throw new \InvalidArgumentException('Quote must have at least one item');
        }

        $userId = $createdByUserId ?? CurrentUserService::idOr(1);


        $quoteId = $this->quoteRepository->create($request, $userId);

        if (!$quoteId || !is_numeric($quoteId)) {
            throw new \Exception('Failed to create quote');
        }


        $createdQuote = $this->quoteRepository->findById((int) $quoteId);

        if (!$createdQuote) {
            throw new \Exception('Failed to fetch created quote');
        }


        DB::connection('vtiger')
            ->table('vtiger_crmentity')
            ->updateOrInsert(
                ['crmid' => $quoteId, 'setype' => 'Quotes'],
                [
                    'label' => $request->subject ?? 'Quote #' . $quoteId,
                    'createdtime' => now(),
                    'modifiedtime' => now(),
                    'deleted' => 0,
                ]
            );


        VtigerActivityTracker::created(
            module: 'Quotes',
            crmid: (int) $quoteId,
            userId: $userId
        );


        return $createdQuote;
    }
}
