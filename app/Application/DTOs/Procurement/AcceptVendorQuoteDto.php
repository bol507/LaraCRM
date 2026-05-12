<?php
// app/Application/DTOs/Procurement/AcceptVendorQuoteDto.php
namespace App\Application\DTOs\Procurement;

class AcceptVendorQuoteDto {
    public function __construct(
        public readonly int $quoteId,
        public readonly int $acceptedBy,      // user who accepted the quote
        public readonly ?string $notes = null // internal notes for the purchasing team
    ) {}
}