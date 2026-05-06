<?php
// app/Application/DTOs/Procurement/AcceptVendorQuoteDto.php
namespace App\Application\DTOs\Procurement;

class AcceptVendorQuoteDto {
    public function __construct(
        public readonly int $quoteId,
        public readonly int $approvedByUserId,
        public readonly ?string $notes = null,
    ) {}
}