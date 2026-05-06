<?php
// app/Http/Controllers/Api/Procurement/PurchaseOrderController.php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Procurement\GeneratePurchaseOrderDto;
use App\Application\DTOs\Procurement\GetPurchaseOrderRequestDto;
use App\Application\DTOs\Procurement\ListPurchaseOrdersRequestDto;
use App\Http\Controllers\Controller;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use App\Application\UseCases\Procurement\GeneratePurchaseOrderUseCase;
use App\Application\UseCases\Procurement\GetPurchaseOrderUseCase;
use App\Application\UseCases\Procurement\ListPurchaseOrdersUseCase;
use App\Services\CurrentUserService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly ListPurchaseOrdersUseCase $listUseCase,
        private readonly GetPurchaseOrderUseCase $getUseCase,
        private readonly GeneratePurchaseOrderUseCase $generateUseCase,
        private readonly PurchaseOrderRepositoryInterface $repo
    ) {}

    /**
     * GET /api/projects/{projectId}/purchase-orders
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'status' => 'sometimes|string',
            'vendor_id' => 'sometimes|integer'
        ]);

        $dto = new ListPurchaseOrdersRequestDto(
            projectId: $projectId,
            page: $request->integer('page', 1),
            limit: $request->integer('limit', 20),
            status: $request->input('status'),
            vendorId: $request->integer('vendor_id')
        );

        $result = $this->listUseCase->execute($dto);

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta']
        ]);
    }

    public function show(Request $request, int $projectId, int $poId): JsonResponse
    {
        
        $request->validate([
            'projectId' => 'required|integer',
            'poId' => 'required|integer'
        ]);

        $dto = new GetPurchaseOrderRequestDto(
            projectId: $projectId,
            poId: $poId
        );

        try {
            $po = $this->getUseCase->execute($dto);

            return response()->json([
                'data' => $po,
                'message' => 'Purchase order retrieved successfully'
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/purchase-orders
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vendor_id' => 'nullable|integer|exists:vtiger_vendor,vendorid',
                'approved_item_ids' => 'required|array|min:1',
                'approved_item_ids.*' => 'required|integer|exists:material_request_items,id',
                'po_notes' => 'nullable|string|max:500',
                'expected_delivery' => 'nullable|date',
            ]);

            $creatorId = $request->integer('created_by_id') ?? $request->user()?->id;
            if (!$creatorId) {
                return response()->json(['error' => 'created_by_id or authenticated user required'], 400);
            }

            $dto = new GeneratePurchaseOrderDto(
                projectId: $validated['project_id'],
                vendorId: isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
                createdById: $creatorId,
                approvedItemIds: array_map('intval', $validated['approved_item_ids']),
                poNotes: $validated['po_notes'] ?? null,
                expectedDelivery: $validated['expected_delivery'] ?? null,
            );

            $poId = $this->generateUseCase->execute($dto);

            return response()->json([
                'data' => ['id' => $poId],
                'message' => 'Purchase order generated successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate purchase order'], 500);
        }
    }



    /**
     * PATCH /api/purchase-orders/items/{itemId}/receive
     */
    public function receive(Request $request, int $itemId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'received_qty' => 'required|numeric|min:0.01',
            ]);

            $this->repo->recordReception($itemId, (float) $validated['received_qty']);

            // Opcional: fetch updated PO status to trigger auto-closure logic here or in a job

            return response()->json(['message' => 'Reception recorded successfully']);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (InvalidArgumentException | DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to record reception'], 500);
        }
    }

    /**
     * POST /api/projects/{projectId}/purchase-orders/generate
     * 
     * Generate one or more Purchase Orders from approved material request items.
     * Auto-splits items by vendor_id.
     */
    public function generate(Request $request, int $projectId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_ids' => 'required|array|min:1',
                'item_ids.*' => 'required|integer|exists:vtiger.material_request_items,id',
                'vendor_id' => 'required|integer|exists:vtiger.vtiger_vendor,vendorid',
                'expected_delivery' => 'nullable|date|after:today',
                'notes' => 'nullable|string|max:1000',
            ]);

            $creatorId = CurrentUserService::idOr(1);

            $dto = GeneratePurchaseOrderDto::fromValidatedData([
                'vendor_id' => $validated['vendor_id'] ?? null,
                'created_by_id' => $creatorId,
                'approved_item_ids' => $validated['approved_item_ids'],
                'po_notes' => $validated['po_notes'] ?? null,
                'expected_delivery' => $validated['expected_delivery'] ?? null,
            ]);

            $poId = $this->generateUseCase->execute($dto);

            return response()->json([
                'message' => 'Purchase order generated successfully',
                ['id' => $poId, 'po_number' => $dto->poNumber ?? null]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('GeneratePO failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to generate purchase order'], 500);
        }
    }
}
