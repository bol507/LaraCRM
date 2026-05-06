<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Procurement\AcceptVendorQuoteDto;
use App\Application\DTOs\Procurement\CreateVendorQuoteDto;
use App\Application\UseCases\Procurement\AcceptVendorQuoteUseCase;
use App\Application\UseCases\Procurement\CreateVendorQuoteUseCase;
use App\Http\Controllers\Controller;
use App\Services\CurrentUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorQuoteController extends Controller
{
    public function __construct(
        private readonly CreateVendorQuoteUseCase $createUseCase,
        private readonly AcceptVendorQuoteUseCase $acceptUseCase,
    ) {}

    public function store(Request $req, int $projectId): JsonResponse {
        $validated = $req->validate([
            'material_request_id' => 'required|integer',
            'vendor_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.material_request_item_id' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|min:0',
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

    public function accept(Request $req, int $quoteId): JsonResponse {
        $dto = new AcceptVendorQuoteDto(
            quoteId: $quoteId,
            approvedByUserId: CurrentUserService::idOr(1),
            notes: $req->input('notes')
        );
        $this->acceptUseCase->execute($dto);
        return response()->json(['message' => 'Quote accepted successfully']);
    }
}