<?php
// app/Application/UseCases/Procurement/GeneratePOFromQuoteUseCase.php

namespace App\Application\UseCases\Procurement;

use App\Application\DTOs\Procurement\GeneratePOFromQuoteDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use App\Domain\Events\PurchaseOrderCreated;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class GeneratePOFromQuoteUseCase
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly VendorQuoteRepositoryInterface $quoteRepo,
        private readonly MaterialRequestRepositoryInterface $requestRepo,
    ) {}

    /**
     * Ejecuta la generación de una Purchase Order desde una Vendor Quote aceptada.
     * 
     * @return int ID de la PO creada
     * @throws DomainException Si la cotización no está aceptada o hay inconsistencias
     */
    public function execute(GeneratePOFromQuoteDto $dto): int
    {
        return DB::connection('vtiger')->transaction(function () use ($dto) {
            
            // 1. Validar que la cotización existe y está ACEPTADA
            $quote = $this->quoteRepo->findById($dto->vendorQuoteId);
            if (!$quote) {
                throw new DomainException('Vendor quote not found');
            }
            if ($quote['status'] !== 'accepted') {
                throw new DomainException('Only accepted quotes can be converted to a Purchase Order');
            }

            // 2. Generar número de PO único
            $poNumber = $dto->poNumberOverride ?? $this->generatePONumber($dto->projectId);

            // 3. Calcular totales desde los ítems (precios inmutables del quote)
            $subtotal = array_sum(array_column($dto->items, 'line_total'));
            $taxAmount = 0; // Fase 3: implementar cálculo de impuestos por proyecto/proveedor
            $totalAmount = $subtotal + $taxAmount;

            // 4. Crear cabecera de la PO
            $poId = $this->poRepo->create([
                'project_id' => $dto->projectId,
                'po_number' => $poNumber,
                'vendor_quote_id' => $dto->vendorQuoteId,
                'material_request_id' => $quote['material_request_id'] ?? null,
                'vendor_id' => $quote['vendor_id'],
                'status' => 'draft', // Inicia en draft para revisión antes de enviar al proveedor
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'expected_delivery_date' => $this->getEarliestDeliveryDate($dto->items),
                'terms' => $quote['terms'] ?? null,
                'notes' => $quote['notes'] ?? null,
                'internal_notes' => $dto->internalNotes,
                'created_by' => $dto->createdBy,
            ]);

            // 5. Crear ítems de la PO (copia inmutable de precios y condiciones)
            foreach ($dto->items as $item) {
                $this->poRepo->createItem($poId, [
                    'vendor_quote_item_id' => $item['vendor_quote_item_id'],
                    'material_request_item_id' => $item['material_request_item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'catalog_item_type' => $item['catalog_item_type'] ?? null,
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],      // 🔒 Precio real del quote
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $item['line_total'],       // 🔒 Total real del quote
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                    'terms' => $item['terms'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'received_quantity' => 0,
                    'receipt_status' => 'pending',
                ]);
            }

            // 6. Bloquear la cotización para evitar POs duplicadas
            $this->quoteRepo->updateStatus($dto->vendorQuoteId, 'processed');

            // 7. Actualizar estado de la solicitud de material origen
            if (!empty($quote['material_request_id'])) {
                $this->requestRepo->checkAndUpdateToFullyProcured($quote['material_request_id']);
            }

            // 8. Disparar evento para notificaciones y auditoría
            Event::dispatch(new PurchaseOrderCreated(
                purchaseOrderId: $poId,
                projectId: $dto->projectId,
                createdBy: $dto->createdBy,
                quoteId: $dto->vendorQuoteId,
                requestData: [
                    'po_number' => $poNumber,
                    'vendor_name' => $quote['vendor_name'] ?? null,
                    'total_amount' => $totalAmount,
                    'items_count' => count($dto->items),
                    'material_request_id' => $quote['material_request_id'] ?? null,
                ]
            ));

            Log::info('Purchase Order generated successfully', [
                'po_id' => $poId,
                'po_number' => $poNumber,
                'quote_id' => $dto->vendorQuoteId,
                'project_id' => $dto->projectId,
            ]);

            return $poId;
        });
    }

    /**
     * Genera un número de PO único y legible.
     * Formato: PO-{AÑO}-{PROYECTO}-{SECUENCIA}
     * Ej: PO-2026-9123-001
     */
    private function generatePONumber(int $projectId): string
    {
        $year = date('Y');
        $prefix = "PO-{$year}-{$projectId}-";
        
        $lastSequence = DB::connection('vtiger')
            ->table('nova_purchase_orders')
            ->where('po_number', 'like', "{$prefix}%")
            ->max('id'); // Usamos ID como proxy de secuencia dentro de la transacción
            
        $sequence = str_pad((int)($lastSequence ?? 0) + 1, 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}{$sequence}";
    }

    /**
     * Obtiene la fecha de entrega más temprana entre todos los ítems.
     * Útil para planificación logística y alertas.
     */
    private function getEarliestDeliveryDate(array $items): ?string
    {
        $dates = array_filter(array_column($items, 'expected_delivery_date'));
        return !empty($dates) ? min($dates) : null;
    }
}