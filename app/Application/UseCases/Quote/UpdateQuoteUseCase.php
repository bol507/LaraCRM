<?php

namespace App\Application\UseCases\Quote;

use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;

class UpdateQuoteUseCase
{
    public function __construct(
        private readonly QuoteRepositoryInterface $quoteRepository
    ) {}

    public function execute(UpdateQuoteRequest $request, int $modifiedByUserId): bool
    {
        
        if (empty($request->items)) {
            throw new \InvalidArgumentException('La cotización debe tener al menos un ítem');
        }

        
        $quoteId = $request->quoteid;
        
        if (!$quoteId) {
            throw new \InvalidArgumentException('El ID de la cotización es requerido');
        }

        $userId = $modifiedByUserId ?? CurrentUserService::idOr(1);

        if (!empty($request->subject)) {
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $quoteId)
                ->where('setype', 'Quotes')
                ->update([
                    'label' => $request->subject,
                    'modifiedtime' => now(),
                ]);
        }
        
        $updatedQuote = $this->quoteRepository->update($request, $userId);
        
        if (!$updatedQuote) {
            throw new \Exception('No se pudo actualizar la cotización');
        }

        
        VtigerActivityTracker::updated(
            module: 'Quotes',
            crmid: $quoteId,  
            userId: $userId  
        );
        
        return $updatedQuote;
    }
}