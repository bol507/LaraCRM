<?php
// app/Application/UseCases/Procurement/GeneratePurchaseOrderUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\GeneratePurchaseOrderDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use App\Domain\Events\PurchaseOrderCreatedEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrderUseCase
{
    public function __construct(
        private readonly MaterialRequestRepositoryInterface $requestRepo,
        private readonly PurchaseOrderRepositoryInterface $poRepo
    ) {}

    /**
     * Execute the purchase order generation use case
     *
     * @param GeneratePurchaseOrderDto $dto
     * @return int The ID of the created purchase order
     * @throws DomainException If business rules are violated
     */
    public function execute(GeneratePurchaseOrderDto $dto): int
    {
        return DB::transaction(function () use ($dto) {
            $items = $this->requestRepo->findItemsWithVendor($dto->approvedItemIds);

            foreach ($items as $item) {
                if (!in_array($item->item_status, ['approved', 'partially_approved'])) {
                    throw new DomainException("Item #{$item->id} is not approved for purchase.");
                }
                if ($item->po_id !== null) {
                    throw new DomainException("Item #{$item->id} is already linked to a purchase order.");
                }
            }

            $groupedByVendor = collect($items)->groupBy('vendor_id');
            $createdPoids = [];

            // Create one PO per vendor
            foreach ($groupedByVendor as $vendorId => $vendorItems) {
                // Create PO header
                $poId = $this->poRepo->create([
                    'project_id' => $dto->projectId,
                    'vendor_id' => $vendorId,
                    'status' => 'draft',
                    'created_by' => $dto->createdById,
                    'order_date' => now(),
                    'expected_delivery' => $dto->expectedDelivery,
                    'notes' => $dto->poNotes,
                ]);

                // Create lines and link traceability
                foreach ($vendorItems as $item) {
                    $this->poRepo->addLine($poId, [
                        'source_request_item_id' => $item->id,
                        'item_name' => $item->item_name,
                        'quantity' => $item->approved_quantity ?? $item->quantity,
                        'unit' => $item->unit,
                        'unit_price' => $item->estimated_cost, // Or calculate from catalog
                    ]);

                    // Update requested item status
                    $this->requestRepo->updateItemStatus($item->id, 'partially_procured', $poId);
                }

                $createdPoids[] = $poId;
            }

            return $createdPoids[0] ?? throw new \RuntimeException('No purchase order was created');
        });
    }
}