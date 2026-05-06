<?php
// app/Http/Controllers/Api/Procurement/MaterialRequestController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Procurement\CreateMaterialRequestUseCase;
use App\Application\UseCases\Procurement\ApproveMaterialRequestUseCase;
use App\Application\DTOs\Procurement\CreateMaterialRequestDto;
use App\Application\DTOs\Procurement\ApproveMaterialRequestDto;
use App\Application\Repositories\MaterialRequestRepositoryInterface;
use App\Services\CurrentUserService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MaterialRequestController extends Controller
{
    public function __construct(
        private readonly CreateMaterialRequestUseCase $createUseCase,
        private readonly ApproveMaterialRequestUseCase $approveUseCase,
        private readonly MaterialRequestRepositoryInterface $repo
    ) {}

    /**
     * GET /api/projects/{projectId}/material-requests
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $limit = min(100, max(1, $request->integer('limit', 20)));
        $offset = ($page - 1) * $limit;

        $filters = $request->only(['status', 'requested_by']);
        $result = $this->repo->findByProjectId($projectId, $filters, $limit, $offset);

        return response()->json([
            'data' => $result['data'],
            'meta' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
            ]
        ]);
    }

    public function show(int $projectId, int $requestId): JsonResponse
    {
        $request = $this->repo->findByIdWithItems($requestId);

        if (!$request) {
            return response()->json(['error' => 'Material request not found'], 404);
        }

        // Seguridad: verificar que pertenece al proyecto solicitado
        if ($request['project_id'] !== $projectId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $request,
            'message' => 'Material request retrieved successfully'
        ]);
    }

    /**
     * POST /api/projects/{projectId}/material-requests
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.name' => 'required|string|max:255',
                'items.*.type' => 'required|string|in:material,tool,consumable,service',
                'items.*.reason' => 'required|string',
                'items.*.qty' => 'required|numeric|min:0.01',
                'items.*.unit' => 'required|string|max:20',
                'items.*.priority' => 'nullable|string|in:low,medium,high,urgent',
                'items.*.est_cost' => 'nullable|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:1000',

                'items.*.reason_other' => 'nullable|string|max:500|required_if:items.*.reason,other',
            ]);

            $userId = CurrentUserService::idOr(1);
            if (!$userId) {
                return response()->json(
                    ['error' => 'User not authenticated'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $dto = new CreateMaterialRequestDto(
                projectId: $projectId,
                requestedBy: $userId,
                items: array_map(fn($i) => [
                    'name' => $i['name'],
                    'type' => $i['type'],
                    'reason' => $i['reason'],
                    'qty' => (float) $i['qty'],
                    'unit' => $i['unit'],
                    'priority' => $i['priority'] ?? 'medium',
                    'estCost' => isset($i['est_cost']) ? (float) $i['est_cost'] : null,
                    'notes' => $i['notes'] ?? null,

                    'reasonOther' => $i['reason_other'] ?? null,
                ], $validated['items'])
            );

            $requestId = $this->createUseCase->execute($dto);

            return response()->json([
                'data' => ['id' => $requestId],
                'message' => 'Material request created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (InvalidArgumentException | DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create request'], 500);
        }
    }



    /**
     * PATCH /api/material-requests/{requestId}/approve
     */
    public function approve(Request $request, int $projectId, int $requestId): JsonResponse
    {
        try {
            $validated = $request->validate([

                'items' => 'required|array|min:1',
                'items.*.itemId' => 'required|integer|exists:vtiger.material_request_items,id',
                'items.*.decision' => 'required|string|in:approve,reject,partial',
                'items.*.approvedQuantity' => 'nullable|numeric|min:0',

                'notes' => 'nullable|string|max:500',
            ]);

            $approverId = CurrentUserService::idOr(1); 

            if (!$approverId) {
                return response()->json(
                    ['error' => 'User not authenticated'],
                    Response::HTTP_UNAUTHORIZED
                );
            }


            $dto = new ApproveMaterialRequestDto(
                requestId: $requestId,
                approverId: $approverId,
                itemDecisions: array_map(fn($d) => [
                    'itemId' => $d['itemId'],
                    'action' => $d['decision'],
                    'quantity' => isset($d['approvedQuantity']) ? (float) $d['approvedQuantity'] : null,
                ], $validated['items']),
                notes: $validated['notes'] ?? null
            );

            $this->approveUseCase->execute($dto);

            return response()->json(['message' => 'Request processed successfully']);
        } catch (ValidationException $e) {

            Log::warning('Approval validation failed', [
                'request_id' => $requestId,
                'payload' => $request->all(),
                'errors' => $e->errors()
            ]);
            return response()->json(['error' => $e->errors()], 422);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Approval failed', ['error' => $e->getMessage(), 'request_id' => $requestId]);
            return response()->json(['error' => 'Failed to process approval'], 500);
        }
    }
}
