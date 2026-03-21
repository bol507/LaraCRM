<?php

namespace App\Domain\Entities;

/**
 * Class QuoteItem
 * 
 * Domain entity representing a line item within a sales quote in Vtiger CRM.
 * 
 * This entity encapsulates all data related to a single product or service line
 * item in a quote, including pricing calculations, quantities, discounts, and
 * descriptive information. It maps to the vtiger_inventoryproductrel table which
 * stores the relationship between quotes and their line items.
 * 
 * The entity follows the immutability pattern using readonly properties with
 * constructor property promotion, ensuring that once created, quote item data
 * cannot be modified. This promotes safer data handling, predictable behavior,
 * and easier reasoning about pricing calculations throughout the application.
 * 
 * Key characteristics:
 * - Immutable: All properties are readonly after instantiation via constructor promotion
 * - Pricing calculations: Includes pre-calculated netprice and total for display efficiency
 * - Flexible descriptions: productname supports long, customizable product descriptions
 * - Discount handling: Supports percentage-based discounts at the line item level
 * - Sequence ordering: sequence_no field maintains item order as displayed in quotes
 * 
 * Database mapping:
 * - Primary table: vtiger_inventoryproductrel (composite key: id + sequence_no)
 * - Parent reference: id field references vtiger_quotes.quoteid
 * - Product reference: productid references vtiger_products.productid (optional)
 * 
 * Pricing calculation logic:
 * - netprice = listprice * (1 - discount_percent / 100)
 * - total = quantity * netprice
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Domain\Entities\Quote For the parent quote entity
 * @see \App\Infrastructure\Mappers\QuoteMapper For database row to entity mapping
 * @see \App\Application\DTOs\Quote\QuoteResponse For the API response DTO that includes items
 */
class QuoteItem
{
    
    /**
     * QuoteItem constructor.
     * 
     * Creates an immutable QuoteItem entity instance with all line item data
     * from the Vtiger CRM database. All properties are declared and initialized
     * via constructor property promotion with readonly modifier, ensuring
     * immutability after instantiation without requiring separate property
     * declarations.
     * 
     * The entity is designed to be created by the mapper layer after fetching
     * data from the vtiger_inventoryproductrel table. Pricing fields (netprice,
     * total) are expected to be pre-calculated by the repository or mapper to
     * ensure consistency with database-stored values.
     * 
     * @param int $quoteid The unique identifier of the parent quote.
     * @param int|null $productid The unique identifier of the related product, if applicable.
     * @param int $sequence_no The sequence number that determines the display order of this item.
     * @param string $productname The name or description of the product or service.
     * @param float $quantity The quantity of the product or service being quoted.
     * @param float $listprice The base list price per unit before discounts.
     * @param float $discount_percent The discount percentage applied to this line item.
     * @param float $netprice The net price per unit after discount application.
     * @param float $total The total amount for this line item (quantity * netprice).
     * @param string|null $description Additional description or notes specific to this line item.
     * 
     * @return void
     */
    public function __construct(
        public readonly int $quoteid,
        public readonly ?int $productid,
        public readonly int $sequence_no,
        public readonly string $productname,
        public readonly float $quantity,
        public readonly float $listprice,
        public readonly float $discount_percent,
        public readonly float $netprice,
        public readonly float $total,
        public readonly ?string $description
    ) {}

    /**
     * Check if this line item references a catalog product.
     * 
     * Determines whether this quote item is linked to a specific product record
     * in the catalog by checking if the productid property is populated. Useful
     * for conditional display of product details, inventory checks, or product-
     * specific workflows.
     * 
     * @return bool True if the item references a catalog product, false if it
     *              is a custom or ad-hoc line item.
     * 
     * @example
     * // Conditionally display product details
     * if ($item->isCatalogProduct()) {
     *     echo "Product ID: {$item->productid}";
     *     // Fetch and display product image, specifications, etc.
     * }
     * 
     * @example
     * // Filter catalog vs custom items
     * $catalogItems = array_filter($items, fn($i) => $i->isCatalogProduct());
     * $customItems = array_filter($items, fn($i) => !$i->isCatalogProduct());
     */
    public function isCatalogProduct(): bool
    {
        return $this->productid !== null;
    }

    /**
     * Check if a discount is applied to this line item.
     * 
     * Determines whether this quote item has a discount applied by checking
     * if the discount_percent property is greater than zero. Useful for
     * conditional display of discount information, highlighting discounted
     * items, or calculating total discount amounts.
     * 
     * @return bool True if a discount is applied (discount_percent > 0),
     *              false if the item is priced at list price.
     * 
     * @example
     * // Display discount badge in UI
     * if ($item->hasDiscount()) {
     *     echo '<span class="badge badge-warning">-' . $item->discount_percent . '%</span>';
     * }
     * 
     * @example
     * // Calculate total discount amount across items
     * $totalDiscount = array_sum(
     *     array_map(
     *         fn($i) => $i->hasDiscount() ? ($i->listprice - $i->netprice) * $i->quantity : 0,
     *         $items
     *     )
     * );
     */
    public function hasDiscount(): bool
    {
        return $this->discount_percent > 0;
    }

    /**
     * Get the discount amount per unit for this line item.
     * 
     * Calculates the monetary discount applied per unit by subtracting the
     * net price from the list price. Returns the absolute discount value,
     * useful for displaying savings information or calculating total discount
     * amounts across the quote.
     * 
     * @return float The discount amount per unit (listprice - netprice).
     *               Always non-negative.
     * 
     * @example
     * // Display per-unit savings in UI
     * $savings = $item->getDiscountAmount();
     * if ($savings > 0) {
     *     echo "Save $" . number_format($savings, 2) . " per unit";
     * }
     * 
     * @example
     * // Calculate total savings for this line
     * $lineSavings = $item->getDiscountAmount() * $item->quantity;
     */
    public function getDiscountAmount(): float
    {
        return $this->listprice - $this->netprice;
    }

    /**
     * Get the total discount amount for this line item.
     * 
     * Calculates the total monetary discount applied to this line item by
     * multiplying the per-unit discount by the quantity. Useful for displaying
     * total savings, generating discount reports, or calculating quote-level
     * discount summaries.
     * 
     * @return float The total discount amount for this line (discount per unit * quantity).
     *               Always non-negative.
     * 
     * @example
     * // Display total line discount in quote summary
     * echo "Line discount: $" . number_format($item->getTotalDiscount(), 2);
     * 
     * @example
     * // Calculate total quote discount
     * $quoteDiscount = array_sum(
     *     array_map(fn($i) => $i->getTotalDiscount(), $quoteItems)
     * );
     */
    public function getTotalDiscount(): float
    {
        return $this->getDiscountAmount() * $this->quantity;
    }

    /**
     * Format the total amount for display with currency formatting.
     * 
     * Returns the line item total formatted as a currency string for display
     * in the user interface or PDF generation. Uses standard two-decimal
     * formatting suitable for most currency displays.
     * 
     * @param string $currencySymbol The currency symbol to prepend (default: '$').
     * @return string The formatted total amount (e.g., "$1,234.56").
     * 
     * @example
     * // Display formatted total in UI
     * echo $item->getFormattedTotal(); // Outputs: "$1,234.56"
     * 
     * @example
     * // Use with different currency
     * echo $item->getFormattedTotal('€'); // Outputs: "€1.234,56"
     */
    public function getFormattedTotal(string $currencySymbol = '$'): string
    {
        return $currencySymbol . number_format($this->total, 2);
    }

    /**
     * Format the net price for display with currency formatting.
     * 
     * Returns the net price per unit formatted as a currency string for display
     * in the user interface or PDF generation. Useful for showing the discounted
     * unit price separately from the list price.
     * 
     * @param string $currencySymbol The currency symbol to prepend (default: '$').
     * @return string The formatted net price (e.g., "$99.99").
     * 
     * @example
     * // Display discounted unit price
     * if ($item->hasDiscount()) {
     *     echo '<span class="text-muted"><s>$' . number_format($item->listprice, 2) . '</s></span>';
     *     echo ' <span class="text-primary">' . $item->getFormattedNetPrice() . '</span>';
     * }
     */
    public function getFormattedNetPrice(string $currencySymbol = '$'): string
    {
        return $currencySymbol . number_format($this->netprice, 2);
    }

    /**
     * Convert the entity to an array for JSON serialization.
     * 
     * Prepares the quote item data for transmission in API responses. The
     * returned array uses snake_case keys to conform to common JSON API
     * conventions and includes all public properties of the entity. Calculated
     * fields like discount_amount and formatted values are included for
     * frontend convenience.
     * 
     * @return array<string, mixed> An associative array containing all entity
     *                              properties formatted for JSON serialization.
     * 
     * @example
     * // Convert to array for API response
     * $responseData = $item->toArray();
     * 
     * @example
     * // Use in quote response DTO
     * $quoteResponse->items = array_map(fn($i) => $i->toArray(), $quoteItems);
     * 
     * @example
     * // Response structure
     * // Returns:
     * // [
     * //     'quoteid' => 456,
     * //     'productid' => 789,
     * //     'sequence_no' => 1,
     * //     'productname' => 'Enterprise License - Annual',
     * //     'quantity' => 10,
     * //     'listprice' => 500.00,
     * //     'discount_percent' => 10.0,
     * //     'netprice' => 450.00,
     * //     'total' => 4500.00,
     * //     'description' => 'Includes premium support and SLA',
     * //     'is_catalog_product' => true,
     * //     'has_discount' => true,
     * //     'discount_amount' => 50.00,
     * //     'total_discount' => 500.00,
     * //     'formatted_total' => '$4,500.00',
     * //     'formatted_net_price' => '$450.00'
     * // ]
     */
    public function toArray(): array
    {
        return [
            'quoteid' => $this->quoteid,
            'productid' => $this->productid,
            'sequence_no' => $this->sequence_no,
            'productname' => $this->productname,
            'quantity' => $this->quantity,
            'listprice' => $this->listprice,
            'discount_percent' => $this->discount_percent,
            'netprice' => $this->netprice,
            'total' => $this->total,
            'description' => $this->description,
            'is_catalog_product' => $this->isCatalogProduct(),
            'has_discount' => $this->hasDiscount(),
            'discount_amount' => $this->getDiscountAmount(),
            'total_discount' => $this->getTotalDiscount(),
            'formatted_total' => $this->getFormattedTotal(),
            'formatted_net_price' => $this->getFormattedNetPrice(),
        ];
    }
}