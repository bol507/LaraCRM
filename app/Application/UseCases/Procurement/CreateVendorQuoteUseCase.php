<?php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\CreateVendorQuoteDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// app/Application/UseCases/Procurement/CreateVendorQuoteUseCase.php
class CreateVendorQuoteUseCase
{
    public function __construct(
        private readonly VendorQuoteRepositoryInterface $repo,
        private readonly MaterialRequestRepositoryInterface $requestRepo,
    ) {}

    public function execute(CreateVendorQuoteDto $dto): int
    {
        return DB::connection('vtiger')->transaction(function () use ($dto) {

            // 1. Crear cabecera de la cotización
            $quoteId = $this->repo->create([
                'project_id' => $dto->projectId,
                'material_request_id' => $dto->materialRequestId,
                'vendor_id' => $dto->vendorId,
                'quote_number' => $this->generateQuoteNumber($dto->projectId),
                'status' => 'draft',
                'valid_until' => $dto->validUntil,
                'terms' => $dto->terms,
                'notes' => $dto->notes,
                'created_by' => $dto->createdById,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            
            $sourceItemIds = array_column($dto->items, 'material_request_item_id');
            $sourceItems = DB::connection('vtiger')
                ->table('nova_material_request_items')
                ->whereIn('id', $sourceItemIds)
                ->get()
                ->keyBy('id'); 

            
            foreach ($dto->items as $item) {
                $sourceItem = $sourceItems[$item['material_request_item_id']] ?? null;

                if (!$sourceItem) {
                    Log::warning('Source item not found for RFQ', [
                        'material_request_item_id' => $item['material_request_item_id'],
                        'quote_id' => $quoteId,
                    ]);
                    continue; 
                }

                $this->repo->createItem($quoteId, [
                    'material_request_item_id' => $item['material_request_item_id'],
                    'item_name' => $sourceItem->item_name ?? 'Ítem sin nombre',
                    'catalog_item_type' => $sourceItem->catalog_item_type ?? null,
                    'unit' => $item['unit'] ?? $sourceItem->unit ?? 'unidad',
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount_percent' => (float) ($item['discount_percent'] ?? 0),

                    
                    'line_total' => (float) (
                        $item['quantity'] * $item['unit_price'] * (1 - (($item['discount_percent'] ?? 0) / 100))
                    ),

                    'delivery_date' => $item['delivery_date'] ?? null,
                    'terms' => $item['terms'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // 4. Actualizar estados
            $this->repo->updateStatus($quoteId, 'sent');
            $this->requestRepo->updateStatus(
                id: $dto->materialRequestId,
                status: 'procurement_in_progress'
            );

            
            Log::info('Vendor Quote created successfully', [
                'quote_id' => $quoteId,
                'items_count' => count($dto->items),
                'project_id' => $dto->projectId,
            ]);

            return $quoteId;
        });
    }

    private function generateQuoteNumber(int $projectId): string
    {
        $year = date('Y');
        $prefix = "CF-{$year}-";
        $last = DB::connection('vtiger')->table('nova_vendor_quotes')
            ->where('quote_number', 'like', "{$prefix}%")->max('id');
        return $prefix . str_pad(($last ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    }
}
