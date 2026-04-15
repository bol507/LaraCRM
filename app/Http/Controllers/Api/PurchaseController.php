<?php
// app/Http/Controllers/Api/PurchaseController.php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Purchase\CreatePurchaseRequest;
use App\Http\Controllers\Controller;
use App\Application\UseCases\Purchase\CreatePurchaseUseCase;
use App\Application\UseCases\Purchase\GetProjectPurchasesUseCase;
use App\Application\UseCases\Purchase\GetPurchaseUseCase;
use App\Application\UseCases\Purchase\ListPurchasesUseCase;
use App\Application\UseCases\Purchase\UpdatePurchaseUseCase;
use App\Application\UseCases\Purchase\DeletePurchaseUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class PurchaseController extends Controller
{
    public function __construct(
        private CreatePurchaseUseCase $createPurchaseUseCase,
        private GetPurchaseUseCase $getPurchaseUseCase,
        private ListPurchasesUseCase $listPurchasesUseCase,
        private GetProjectPurchasesUseCase $getProjectPurchasesUseCase,
        private UpdatePurchaseUseCase $updatePurchaseUseCase,//TODO add repository
        private DeletePurchaseUseCase $deletePurchaseUseCase,//TODO: add repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = $this->listPurchasesUseCase->execute(
            page: $request->get('page', 1),
            limit: $request->get('limit', 10),
            search: $request->get('search'),
            status: $request->get('status'),
            projectId: $request->get('project_id'),
        );

        return response()->json([
            'data' => $purchases->items(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ]
        ]);
    }

    public function show(string $id): JsonResponse
    {
        try {
           
            $purchase = $this->getPurchaseUseCase->execute((int) $id);
            return response()->json($purchase->toArray());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'projectid' => 'required|integer',
            'vendorid' => 'required|integer',
            'postatus' => 'required|in:Draft,Pending Approval,Approved,Received',
            'podate' => 'nullable|date',
            'validtill' => 'nullable|date',
            'description' => 'nullable|string',
            'assigned_user_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.productname' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.listprice' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $dto = CreatePurchaseRequest::fromArray($validated);
        $purchase = $this->createPurchaseUseCase->execute($dto);

        return response()->json([
            'message' => 'Purchase order created successfully',
            'data' => $purchase->toArray(),
        ], 201);
    }

    /**
     * Get purchases for a specific project.
     */
    public function byProject(string $projectId, Request $request): JsonResponse
    {
        try {
            $purchases = $this->getProjectPurchasesUseCase->execute(
                projectId: (int) $projectId,
                page: $request->get('page', 1),
                limit: $request->get('limit', 50),
                status: $request->get('status'),
            );

            return response()->json([
                'data' => $purchases->items(),
                'meta' => [
                    'current_page' => $purchases->currentPage(),
                    'last_page' => $purchases->lastPage(),
                    'per_page' => $purchases->perPage(),
                    'total' => $purchases->total(),
                ]
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error retrieving project purchases: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing purchase order.
     */
    public function update(string $id, Request $request): JsonResponse  // ← CAMBIAR A string
    {
        try {
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'projectid' => 'required|integer',
                'vendorid' => 'required|integer',
                'postatus' => 'required|in:Draft,Pending Approval,Approved,Received',
                'podate' => 'nullable|date',
                'validtill' => 'nullable|date',
                'description' => 'nullable|string',
                'assigned_user_id' => 'nullable|integer',
                'items' => 'required|array|min:1',
                'items.*.productname' => 'required|string',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.listprice' => 'required|numeric|min:0',
                'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            ]);

            $dto = CreatePurchaseRequest::fromArray($validated);
            $purchase = $this->updatePurchaseUseCase->execute((int) $id, $dto);  // ← Cast aquí

            return response()->json([
                'message' => 'Purchase order updated successfully',
                'data' => $purchase->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a purchase order.
     */
    public function destroy(string $id): JsonResponse  // ← CAMBIAR A string
    {
        try {
            $this->deletePurchaseUseCase->execute((int) $id);  // ← Cast aquí

            return response()->json([
                'message' => 'Purchase order deleted successfully',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error deleting purchase: ' . $e->getMessage()
            ], 500);
        }
    }
}