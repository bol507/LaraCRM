<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UpdateQuoteUseCase
{
    public function __construct(
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly QuoteRepository $quote,
    ) {}

    public function execute(UpdateQuoteRequest $request, ?int $modifiedByUserId = null): bool
    {
        $quoteId = $request->quoteid;

        if (! $quoteId) {
            throw new InvalidArgumentException('Quote ID is required');
        }

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        if (! $this->quote->exists($quoteId)) {
            throw new InvalidArgumentException('Quote not found');
        }

        
        return DB::connection('vtiger')->transaction(function () use ($request, $quoteId, $userId) {
            
           
            $this->quote->update($request, $userId);

            $crmentityData = [];

            if ($request->subject !== null) {
                $crmentityData['label'] = $request->subject;
            }
            if ($request->description !== null) {
                $crmentityData['description'] = $request->description;
            }
            if (isset($request->assigned_user_id)) {
                $crmentityData['smownerid'] = $request->assigned_user_id;
            }

           
            if (! empty($crmentityData)) {
                $this->updateEntity->execute(
                    crmId: $quoteId,
                    data: $crmentityData,
                    userId: $userId
                );
            }

            
            $this->logActivity($quoteId, $userId);

            return true;
        });
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