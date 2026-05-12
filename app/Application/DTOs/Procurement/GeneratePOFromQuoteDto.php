<?php
// app/Application/DTOs/Procurement/GeneratePOFromQuoteDto.php

namespace App\Application\DTOs\Procurement;

/**
 * DTO for generating a Purchase Order from an accepted Vendor Quote.
 * 
 * Prices, quantities, and terms are copied immutably from the quote
 * to preserve commercial history even if the quote changes later.
 */
class GeneratePOFromQuoteDto
{
    /**
     * @param int $projectId Project ID
     * @param int $vendorQuoteId ID of the accepted quote (source)
     * @param int $createdBy ID of the user generating the PO
     * @param array<int, array{
     *   vendor_quote_item_id: int,
     *   material_request_item_id: int,
     *   item_name: string,
     *   unit: string,
     *   quantity: float,
     *   unit_price: float,
     *   discount_percent: float,
     *   line_total: float,
     *   expected_delivery_date?: string,
     *   terms?: string,
     *   notes?: string
     * }> $items Items copied from the quote (actual prices)
     * @param string|null $poNumberOverride Custom PO number (optional)
     * @param string|null $internalNotes Internal notes for the purchasing team
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $vendorQuoteId,
        public readonly int $createdBy,
        public readonly array $items,
        public readonly ?string $poNumberOverride = null,
        public readonly ?string $internalNotes = null,
    ) {
        // Validate that all items have positive prices
        foreach ($this->items as $idx => $item) {
            if (($item['unit_price'] ?? 0) <= 0) {
                throw new \InvalidArgumentException("Item #{$idx} must have a positive unit_price");
            }
            if (($item['quantity'] ?? 0) <= 0) {
                throw new \InvalidArgumentException("Item #{$idx} must have a positive quantity");
            }
        }
    }
}