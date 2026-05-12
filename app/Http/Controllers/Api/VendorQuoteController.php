<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Procurement\AcceptVendorQuoteDto;
use App\Application\DTOs\Procurement\CreateVendorQuoteDto;
use App\Application\Repositories\VendorQuoteRepositoryInterface;
use App\Application\UseCases\Procurement\AcceptVendorQuoteUseCase;
use App\Application\UseCases\Procurement\CreateVendorQuoteUseCase;
use App\Http\Controllers\Controller;
use App\Services\CurrentUserService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorQuoteController extends Controller
{
    public function __construct(
        private readonly CreateVendorQuoteUseCase $createUseCase,
        private readonly AcceptVendorQuoteUseCase $acceptUseCase,
         private readonly VendorQuoteRepositoryInterface $repo,
    ) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $filters = $request->only(['status', 'vendor_id']);
        $page = max(1, $request->integer('page', 1));
        $limit = min(50, max(1, $request->integer('limit', 20)));

        
        $result = $this->repo->findByProject($projectId, $filters, $limit, $page);

        return response()->json($result);
    }

    public function show(int $projectId, int $quoteId): JsonResponse
    {
        $quote = $this->repo->findById($quoteId);
        if (!$quote || $quote['project_id'] !== $projectId) {
            return response()->json(['error' => 'Vendor quote not found'], 404);
        }

        return response()->json(['data' => $quote]);
    }

    public function store(Request $req, int $projectId): JsonResponse {
        $validated = $req->validate([
            'material_request_id' => 'required|integer',
            'vendor_id' => 'required|integer',
            'items' => 'required|array|min:1',

            'items.*.material_request_item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string|max:50',

            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.catalog_item_type' => 'nullable|string|max:50',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'valid_until' => 'nullable|date',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $dto = new CreateVendorQuoteDto(
            projectId: $projectId,
            materialRequestId: $validated['material_request_id'],
            vendorId: $validated['vendor_id'],
            createdById: CurrentUserService::idOr(1),
            items: $validated['items'],
            validUntil: $validated['valid_until'] ?? null,
            terms: $validated['terms'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        $id = $this->createUseCase->execute($dto);
        return response()->json(['data' => ['id' => $id], 'message' => 'Quote created & sent'], 201);
    }

    public function send(int $id): JsonResponse
    {
        $this->repo->updateStatus($id, 'sent');
        return response()->json(['message' => 'Quote sent to vendor successfully']);
    }

    public function accept(Request $req, int $quoteId): JsonResponse {
        $dto = new AcceptVendorQuoteDto(
            quoteId: $quoteId,
            acceptedBy: CurrentUserService::idOr(1),
            notes: $req->input('notes')
        );
        try {
            $this->acceptUseCase->execute($dto);
            return response()->json(['message' => 'Quote accepted successfully']);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function negotiate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'new_terms' => 'nullable|string',
        ]);

        $this->repo->updateStatus($id, 'negotiated');
        
        
        if (!empty($validated['notes']) || !empty($validated['new_terms'])) {
            $this->repo->updateTermsAndNotes(
                id: $id,
                notes: $validated['notes'] ?? null,
                terms: $validated['new_terms'] ?? null
            );
        }

        $updatedQuote = $this->repo->findById($id) ?? ['id' => $id, 'status' => 'negotiated'];

        return response()->json([
            'message' => 'Quote marked as negotiated',
            'data' => $updatedQuote
        ]);
    }
}