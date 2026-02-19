<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use App\Application\UseCases\CreateQuoteUseCase;
use App\Application\UseCases\UpdateQuoteUseCase;
use App\Application\UseCases\GetQuoteUseCase;
use App\Application\UseCases\DeleteQuoteUseCase;
use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Http\Controllers\Controller;

class QuoteController extends Controller
{
    public function __construct(
        private readonly CreateQuoteUseCase $createQuoteUseCase,
        private readonly UpdateQuoteUseCase $updateQuoteUseCase,
        private readonly GetQuoteUseCase $getQuoteUseCase,
        private readonly DeleteQuoteUseCase $deleteQuoteUseCase
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $paginator = $this->getQuoteUseCase->execute($page, $perPage, $search);

        $data = $paginator->items();

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

    public function store(Request $request): JsonResponse
    {
        $validator = ValidatorFacade::make($request->all(), [
            'subject' => 'required|string|max:255',
            'accountid' => 'required|integer',
            'assigned_user_id' => 'required|integer',
            'validtill' => 'nullable|date',
            'closingdate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.productname' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.listprice' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.sequence_no' => 'required|integer',
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
        unset($requestData['quote_no']);

        $createRequest = new CreateQuoteRequest(
            subject: $requestData['subject'],
            potentialid: $requestData['potentialid'] ?? null,
            accountid: $requestData['accountid'],
            assigned_user_id: $requestData['assigned_user_id'],
            validtill: $requestData['validtill'] ?? null,
            description: $requestData['description'] ?? null,
            items: $this->prepareItems($requestData['items'])
        );

        try {
            $quoteId = $this->createQuoteUseCase->execute($createRequest, $authenticatedUser->id);
            $quote = $this->getQuoteUseCase->executeById($quoteId);
            return response()->json([
                'message' => 'Cotización creada exitosamente',
                'quoteid' => $quoteId,
                'quoteno' => $quote?->quoteno
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $quote = $this->getQuoteUseCase->executeById($id);
            if (!$quote) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }
            return response()->json($quote);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = ValidatorFacade::make($request->all(), [
            'subject' => 'required|string|max:255',
            'accountid' => 'required|integer',
            'assigned_user_id' => 'required|integer',
            'quote_stage' => 'required|string|in:Draft,Sent,Accepted,Rejected',
            'validtill' => 'nullable|date',
            'closingdate' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.productname' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.listprice' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.sequence_no' => 'required|integer',
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

        $updateRequest = new UpdateQuoteRequest(
            quoteid: $id,
            subject: $requestData['subject'],
            potentialid: $requestData['potentialid'] ?? null,
            accountid: $requestData['accountid'],
            assigned_user_id: $requestData['assigned_user_id'],
            quote_stage: $requestData['quote_stage'],
            validtill: $requestData['validtill'] ?? null,
            closingdate: $requestData['closingdate'] ?? null,
            description: $requestData['description'] ?? null,
            items: $this->prepareItems($requestData['items'])
        );

        try {
            $success = $this->updateQuoteUseCase->execute($updateRequest, $authenticatedUser->id);
            if ($success) {
                return response()->json(['message' => 'Cotización actualizada exitosamente']);
            }
            return response()->json(['error' => 'Cotización no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            $success = $this->deleteQuoteUseCase->execute($id, $authenticatedUser->id);
            if ($success) {
                return response()->json(['message' => 'Cotización eliminada exitosamente']);
            }
            return response()->json(['error' => 'Cotización no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepara los ítems para el DTO, asegurando valores por defecto
     */
    private function prepareItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'productid' => $item['productid'] ?? null,
                'sequence_no' => $item['sequence_no'],
                'productname' => $item['productname'],
                'quantity' => (float) $item['quantity'],
                'listprice' => (float) $item['listprice'],
                'discount_percent' => isset($item['discount_percent']) ? (float) $item['discount_percent'] : 0.0,
                'description' => $item['description'] ?? null,
            ];
        }, $items);
    }
}
