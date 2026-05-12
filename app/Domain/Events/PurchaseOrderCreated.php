<?php
// app/Domain/Events/Procurement/PurchaseOrderCreated.php

namespace App\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param int $purchaseOrderId ID of the created PO
     * @param int $projectId Project ID
     * @param int $createdBy ID of the user who generated the PO
     * @param int $quoteId ID of the source quote (for traceability)
     * @param array $requestData Contextual data { po_number, vendor_name, total_amount, items_count }
     */
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly int $projectId,
        public readonly int $createdBy,
        public readonly int $quoteId,
        public readonly array $requestData,
    ) {}
}