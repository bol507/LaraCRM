<?php
// app/Http/Controllers/Api/VendorController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Vendor\CreateVendorUseCase;
use App\Application\UseCases\Vendor\GetVendorUseCase;
use App\Application\UseCases\Vendor\ListVendorsUseCase;
use App\Application\UseCases\Vendor\UpdateVendorUseCase;
use App\Application\UseCases\Vendor\DeleteVendorUseCase;
use App\Application\UseCases\Vendor\SearchVendorsUseCase;
use App\Application\DTOs\Vendor\CreateVendorRequest;
use App\Application\DTOs\Vendor\UpdateVendorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class VendorController extends Controller
{
    public function __construct(
        private CreateVendorUseCase $create,
        private GetVendorUseCase $get,
        private ListVendorsUseCase $list,
        private UpdateVendorUseCase $update,
        private DeleteVendorUseCase $delete,
        private SearchVendorsUseCase $search,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $vendors = $this->list->execute(
                page: $request->get('page', 1),
                limit: $request->get('limit', 10),
                search: $request->get('search'),
                category: $request->get('category'),
                sortBy: $request->get('sort_by', 'createdtime'),
                sortOrder: $request->get('sort_order', 'DESC'),
            );

            return response()->json([
                'data' => $vendors->items(),
                'meta' => [
                    'current_page' => $vendors->currentPage(),
                    'last_page' => $vendors->lastPage(),
                    'per_page' => $vendors->perPage(),
                    'total' => $vendors->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json($this->get->execute((int) $id)->toArray());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vendorname' => 'required|string|max:100',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:30',
                'category' => 'nullable|string|max:50',
                'website' => 'nullable|url',
                'street' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'postalcode' => 'nullable|string',
                'country' => 'nullable|string',
                'description' => 'nullable|string',
                'assigned_user_id' => 'nullable|integer',
            ]);

            $dto = CreateVendorRequest::fromArray($validated);
            $vendor = $this->create->execute($dto);

            return response()->json(['message' => 'Vendor created', 'data' => $vendor->toArray()], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vendorname' => 'nullable|string|max:100',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:30',
                'category' => 'nullable|string|max:50',
                'website' => 'nullable|url',
                'street' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'postalcode' => 'nullable|string',
                'country' => 'nullable|string',
                'description' => 'nullable|string',
                'assigned_user_id' => 'nullable|integer',
            ]);

            $dto = UpdateVendorRequest::fromArray($validated);
            $vendor = $this->update->execute((int) $id, $dto);

            return response()->json(['message' => 'Vendor updated', 'data' => $vendor->toArray()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->delete->execute((int) $id);
            return response()->json(['message' => 'Vendor deleted']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $term = $request->get('search', '');
            $vendors = $this->search->execute($term);
            return response()->json(['data' => $vendors]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}