<?php
// app/Domain/Events/VendorQuoteAccepted.php

namespace App\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorQuoteAccepted
{
    use Dispatchable, SerializesModels;

    /**
     * @param int $quoteId ID of the accepted quote
     * @param int $projectId Project ID
     * @param int $acceptedBy ID of the user who approved the quote
     * @param array $quoteData Contextual data { quote_number, vendor_name, total_amount, material_request_id }
     */
    public function __construct(
        public readonly int $quoteId,
        public readonly int $projectId,
        public readonly int $acceptedBy,
        public readonly array $quoteData,
    ) {}
}