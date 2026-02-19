<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\GetAllOpportunitiesUseCase;
use App\Application\UseCases\CreateOpportunityUseCase;
use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\DTOs\OpportunityDto;
use App\Application\DTOs\UpdateOpportunityRequest;
use App\Application\UseCases\DeleteOpportunityUseCase;
use App\Application\UseCases\UpdateOpportunityUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly GetAllOpportunitiesUseCase $getAllOpportunitiesUseCase,
        private readonly CreateOpportunityUseCase $createOpportunityUseCase,
        private readonly UpdateOpportunityUseCase $updateOpportunityUseCase,
        private readonly DeleteOpportunityUseCase $deleteOpportunityUseCase
    ) {}

    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $paginator = $this->getAllOpportunitiesUseCase->execute($page, $perPage, $search);

        $data = array_map(function ($opportunity) {
            return OpportunityDto::fromEntity($opportunity);
        }, $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'potentialname' => 'required|string|max:255',
            'sales_stage' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'closingdate' => 'nullable|date',
            'probability' => 'nullable|integer|min:0|max:100',
            'related_to' => 'nullable|integer',
            'assigned_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $requestData = $request->all();

        $createRequest = new CreateOpportunityRequest(
            potentialname: $requestData['potentialname'],
            amount: $requestData['amount'] ?? null,
            closingdate: $requestData['closingdate'] ?? null,
            sales_stage: $requestData['sales_stage'],
            probability: $requestData['probability'] ?? null,
            related_to: $requestData['related_to'] ?? null,
            assigned_user_id: $requestData['assigned_user_id'] ?? null,
            description: $requestData['description'] ?? null,
        );

        try {
            $opportunityId = $this->createOpportunityUseCase->execute($createRequest, $authenticatedUser->id);

            return response()->json([
                'message' => 'Oportunidad creada exitosamente',
                'opportunity_id' => $opportunityId
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la oportunidad: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'potentialname' => 'required|string|max:255',
            'sales_stage' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'closingdate' => 'nullable|date',
            'probability' => 'nullable|integer|min:0|max:100',
            'related_to' => 'nullable|integer',
            'assigned_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $validator->errors()
            ], 422);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $requestData = $request->all();

        $updateRequest = new UpdateOpportunityRequest(
            id: $id,
            potentialname: $requestData['potentialname'],
            amount: $requestData['amount'] ?? null,
            closingdate: $requestData['closingdate'] ?? null,
            sales_stage: $requestData['sales_stage'],
            probability: $requestData['probability'] ?? null,
            related_to: $requestData['related_to'] ?? null,
            assigned_user_id: $requestData['assigned_user_id'] ?? null,
            description: $requestData['description'] ?? null,
        );

        try {
            $success = $this->updateOpportunityUseCase->execute($updateRequest, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Oportunidad actualizada exitosamente']);
            }

            return response()->json(['error' => 'Oportunidad no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la oportunidad: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            $success = $this->deleteOpportunityUseCase->execute($id, $authenticatedUser->id);

            if ($success) {
                return response()->json(['message' => 'Oportunidad eliminada exitosamente']);
            }

            return response()->json(['error' => 'Oportunidad no encontrada o ya eliminada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar la oportunidad: ' . $e->getMessage()
            ], 500);
        }
    }
}
