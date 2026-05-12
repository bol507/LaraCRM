<?php
// app/Application/UseCases/Procurement/AcceptVendorQuoteUseCase.php
namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\AcceptVendorQuoteDto;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use App\Domain\Events\VendorQuoteAccepted;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class AcceptVendorQuoteUseCase
{
    public function __construct(
        private readonly VendorQuoteRepositoryInterface $quoteRepo
    ) {}

    public function execute(AcceptVendorQuoteDto $dto): void
    {
        DB::connection('vtiger')->transaction(function () use ($dto) {
            
            // 1. Validate that the quote exists and is in an acceptable status
            $quote = $this->quoteRepo->findById($dto->quoteId);
            if (!$quote) {
                throw new DomainException('Vendor quote not found');
            }
            if (!in_array($quote['status'], ['submitted', 'negotiated'])) {
                throw new DomainException('Only submitted or negotiated quotes can be accepted');
            }

            // 2. Mark as accepted (prevents future editing)
            $this->quoteRepo->acceptQuote($dto->quoteId, $dto->acceptedBy, $dto->notes);

            // 3. Dispatch event for notifications and audit
            Event::dispatch(new VendorQuoteAccepted(
                quoteId: $dto->quoteId,
                projectId: $quote['project_id'],
                acceptedBy: $dto->acceptedBy,
                quoteData: [
                    'quote_number' => $quote['quote_number'],
                    'vendor_name' => $quote['vendor_name'] ?? null,
                    'total_amount' => $quote['total_amount'],
                    'material_request_id' => $quote['material_request_id'] ?? null,
                ]
            ));
        });
    }
}