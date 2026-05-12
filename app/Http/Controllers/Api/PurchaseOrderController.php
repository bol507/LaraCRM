<?php
// app/Http/Controllers/Api/Procurement/PurchaseOrderController.php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Procurement\GeneratePOFromQuoteDto;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use App\Application\UseCases\GetGeneralConditionsUseCase;
use App\Application\UseCases\Procurement\CompanyInfoService;
use App\Application\UseCases\Procurement\GeneratePOFromQuoteUseCase;
use App\Application\UseCases\Procurement\GetPurchaseOrderForPdfUseCase;
use App\Application\UseCases\Procurement\PurchaseOrderCalculationService;
use App\Http\Controllers\Controller;
use App\Services\CurrentUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly GeneratePOFromQuoteUseCase $generatePOUseCase,
        private readonly GetPurchaseOrderForPdfUseCase $getPOForPdfUseCase,
        private readonly PurchaseOrderCalculationService $calculationService,
        private readonly GetGeneralConditionsUseCase $getGeneralConditionsUseCase,
        private readonly CompanyInfoService $companyInfoService,

    ) {}

    /**
     * POST /api/projects/{projectId}/purchase-orders/from-quote
     * 
     * Generar una Purchase Order desde una Vendor Quote aceptada.
     * Los precios y términos se copian inmutables desde el quote.
     */
    public function storeFromQuote(Request $request, int $projectId): JsonResponse
    {
        // ✅ Validación estricta del payload
        $validated = $request->validate([
            'vendor_quote_id' => 'required|integer|exists:vtiger.nova_vendor_quotes,id',
            'items' => 'required|array|min:1',
            'items.*.vendor_quote_item_id' => 'required|integer|exists:vtiger.nova_vendor_quote_items,id',
            'items.*.material_request_item_id' => 'nullable|integer|exists:vtiger.nova_material_request_items,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.line_total' => 'required|numeric|min:0',
            'items.*.expected_delivery_date' => 'nullable|date|after_or_equal:today',
            'items.*.terms' => 'nullable|string|max:500',
            'items.*.notes' => 'nullable|string|max:1000',
            'po_number_override' => 'nullable|string|max:50|unique:nova_purchase_orders,po_number',
            'internal_notes' => 'nullable|string|max:2000',
        ]);

        // ✅ Mapear a DTO
        $dto = new GeneratePOFromQuoteDto(
            projectId: $projectId,
            vendorQuoteId: $validated['vendor_quote_id'],
            createdBy: CurrentUserService::idOr(1),
            items: array_map(function ($item) {
                return [
                    'vendor_quote_item_id' => $item['vendor_quote_item_id'],
                    'material_request_item_id' => $item['material_request_item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'catalog_item_type' => $item['catalog_item_type'] ?? null,
                    'unit' => $item['unit'],
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                    'line_total' => (float) $item['line_total'],
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                    'terms' => $item['terms'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            }, $validated['items']),
            poNumberOverride: $validated['po_number_override'] ?? null,
            internalNotes: $validated['internal_notes'] ?? null,
        );

        try {
            // ✅ Ejecutar UseCase
            $poId = $this->generatePOUseCase->execute($dto);

            // ✅ Obtener número de PO generado para la respuesta
            $po = DB::connection('vtiger')
                ->table('nova_purchase_orders')
                ->where('id', $poId)
                ->first();

            return response()->json([
                'message' => 'Purchase Order created successfully',
                'data' => [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => $po->status,
                    'total_amount' => $po->total_amount,
                    'vendor_id' => $po->vendor_id,
                    'vendor_quote_id' => $po->vendor_quote_id,
                    'created_at' => $po->created_at,
                ]
            ], 201);
        } catch (\DomainException $e) {
            // ✅ Errores de negocio (quote no aceptado, etc.)
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            // ✅ Errores de validación de datos
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            // ✅ Errores inesperados
            Log::error('Error generating PO from quote', [
                'project_id' => $projectId,
                'quote_id' => $validated['vendor_quote_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/projects/{projectId}/purchase-orders
     * Listar POs con filtros opcionales
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        $filters = $request->only(['status', 'vendor_id']);
        $page = max(1, $request->integer('page', 1));
        $limit = min(50, max(1, $request->integer('limit', 20)));

        $result = app(PurchaseOrderRepositoryInterface::class)
            ->findByProject($projectId, $filters, $limit, $page);

        return response()->json($result);
    }

    /**
     * GET /api/projects/{projectId}/purchase-orders/{poId}
     * Detalle de una PO con sus ítems
     */
    public function show(int $projectId, int $poId): JsonResponse
    {
        $po = app(PurchaseOrderRepositoryInterface::class)
            ->findById($poId);

        if (!$po || $po['project_id'] !== $projectId) {
            return response()->json(['error' => 'Purchase order not found'], 404);
        }

        return response()->json(['data' => $po]);
    }

    /**
     * PATCH /api/purchase-orders/{poId}/status
     * Actualizar estado de una PO (draft → submitted → approved, etc.)
     */
    public function updateStatus(int $poId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                'draft',
                'submitted',
                'approved',
                'rejected',
                'partially_received',
                'fully_received',
                'cancelled'
            ])],
            'notes' => 'nullable|string|max:1000',
        ]);

        $updated = app(PurchaseOrderRepositoryInterface::class)
            ->updateStatus($poId, $validated['status']);

        if (!$updated) {
            return response()->json(['message' => 'Failed to update status', 'error' => 'PO not found or invalid transition'], 422);
        }

        return response()->json(['message' => 'Status updated successfully']);
    }

    /**
     * POST /api/purchase-orders/{poId}/items/{itemId}/receipt
     * Registrar recepción de un ítem específico
     */
    public function recordReceipt(int $poId, int $itemId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity_received' => 'required|numeric|min:0.01',
            'received_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'received_by' => 'nullable|integer|exists:vtiger.vtiger_users,id',
        ]);

        try {
            $repo = app(PurchaseOrderRepositoryInterface::class);
            $result = $repo->recordItemReceipt($poId, $itemId, [
                'quantity_received' => (float) $validated['quantity_received'],
                'received_date' => $validated['received_date'],
                'notes' => $validated['notes'] ?? null,
                'received_by' => $validated['received_by'] ?? \App\Services\CurrentUserService::idOr(1),
            ]);

            return response()->json([
                'message' => 'Receipt recorded successfully',
                'data' => (object) $result,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => 'Receipt error',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error recording receipt', [
                'po_id' => $poId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * GET /api/projects/{projectId}/purchase-orders/{poId}/pdf
     * 
     * @return Response BinaryFileResponse en éxito, JsonResponse en error
     */
    public function downloadPdf(int $projectId, int $poId)
    {
        try {
            // ✅ 1. Obtener datos vía UseCase
            $po = $this->getPOForPdfUseCase->execute($poId, $projectId);

            if (!$po) {
                // ✅ Ahora válido: JsonResponse extiende Response
                return response()->json([
                    'message' => 'Purchase Order not found',
                    'error' => "PO #{$poId} does not exist or you don't have access",
                ], 404);
            }

            // ✅ 2-7. Lógica de generación de PDF (igual que antes)
            $calculations = $this->calculationService->calculateForPO($po);
            $company = $this->companyInfoService->getForPDF();
            $poTerms = $this->getGeneralConditionsUseCase->execute('PurchaseOrder');

            // ✅ 2. Fallback a términos genéricos si no hay específicos
            $terms = $poTerms
                ?? $this->getGeneralConditionsUseCase->execute('General')
                ?? config('pdf.default_terms.purchase_order', 'Términos por defecto...');

            $data = [
                'po' => $po,
                'calculations' => $calculations,
                'company' => $company,
                'terms_conditions' => $terms,
                'generated_at' => now()->format('d/m/Y H:i'),
                'footer_note' => 'Documento generado electrónicamente. Válido sin firma.',
            ];

            $pdf = PDF::loadView('pdf.purchase-order', $data)
                ->setPaper(config('pdf.paper_size', 'letter'), config('pdf.orientation', 'portrait'))
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', config('pdf.allow_remote_images', true))
                ->setOption('defaultFont', config('pdf.default_font', 'dejavu sans'));

            $filename = sprintf('PO-%s-%s.pdf', $po->po_number, now()->format('Y-m-d'));

            Log::info('PO PDF generated', [
                'po_id' => $poId,
                'po_number' => $po->po_number,
                'project_id' => $projectId,
                'file_size_kb' => round(strlen($pdf->output()) / 1024, 2),
            ]);

            // ✅ Válido: BinaryFileResponse extiende Response
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating PO PDF', [
                'po_id' => $poId,
                'project_id' => $projectId,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            // ✅ Válido: JsonResponse extiende Response
            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => app()->environment('production')
                    ? 'An unexpected error occurred. Please try again later.'
                    : $e->getMessage(),
            ], 500);
        }
    }
}
