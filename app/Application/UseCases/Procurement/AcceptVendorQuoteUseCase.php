<?php
// app/Application/UseCases/Procurement/AcceptVendorQuoteUseCase.php
namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\AcceptVendorQuoteDto;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AcceptVendorQuoteUseCase {
    public function __construct(private readonly VendorQuoteRepositoryInterface $repo) {}

    public function execute(AcceptVendorQuoteDto $dto): bool {
        return DB::connection('vtiger')->transaction(function () use ($dto) {
            $quote = $this->repo->findById($dto->quoteId);
            if (!$quote || $quote['status'] !== 'submitted' && $quote['status'] !== 'negotiated') {
                throw new \DomainException('Quote cannot be accepted in current status');
            }

            $this->repo->updateStatus($dto->quoteId, 'accepted');
            // Aquí puedes disparar evento: event(new VendorQuoteAccepted($dto->quoteId));
            return true;
        });
    }
}