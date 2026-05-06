<?php
// app/Application/DTOs/Procurement/CreateVendorQuoteDto.php
namespace App\Application\DTOs\Procurement;

class CreateVendorQuoteDto {
    public function __construct(
        public readonly int $projectId,
        public readonly int $materialRequestId,
        public readonly int $vendorId,
        public readonly int $createdById,
        public readonly array $items, // [{ material_request_item_id, unit_price, delivery_date, discount, terms, notes }]
        public readonly ?string $validUntil = null,
        public readonly ?string $terms = null,
        public readonly ?string $notes = null,
    ) {}
}