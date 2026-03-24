<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use App\Application\UseCases\Quote\CreateQuoteUseCase;
use App\Application\UseCases\Quote\UpdateQuoteUseCase;
use App\Application\UseCases\Quote\GetQuoteUseCase;
use App\Application\UseCases\DeleteQuoteUseCase;
use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\UseCases\Quote\GetAllQuotesUseCase;
use App\Http\Controllers\Controller;

/**
 * Quote API Controller
 * 
 * Handles HTTP requests for quote (sales proposal) management operations.
 * 
 * Responsibilities:
 * - Parse and validate HTTP request data for quote CRUD operations
 * - Delegate business logic to Application Use Cases
 * - Transform domain entities to JSON API format
 * - Handle exceptions and return appropriate HTTP status codes
 * - Manage pagination, filtering, and search parameters
 * - Process quote line items with validation and transformation
 * 
 * This controller is part of the Presentation/HTTP layer and should not contain:
 * - Business rules or validation logic (delegated to Use Cases)
 * - Database queries or persistence logic (delegated to Repositories)
 * - UI-specific formatting beyond JSON serialization
 * 
 * @package App\Http\Controllers\Api
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\CreateQuoteUseCase
 * @see \App\Application\UseCases\UpdateQuoteUseCase
 * @see \App\Application\UseCases\GetQuoteUseCase
 * @see \App\Application\UseCases\DeleteQuoteUseCase
 * @see \App\Application\DTOs\CreateQuoteRequest
 * @see \App\Application\DTOs\UpdateQuoteRequest
 */
class QuoteController extends Controller
{
    

    /**
     * Constructor with dependency injection
     * 
     * @param CreateQuoteUseCase $createQuoteUseCase Use case for creating quotes
     * @param UpdateQuoteUseCase $updateQuoteUseCase Use case for updating quotes
     * @param GetQuoteUseCase $getQuoteUseCase Use case for retrieving quotes
     * @param DeleteQuoteUseCase $deleteQuoteUseCase Use case for deleting quotes
     */
    public function __construct(
        private readonly GetAllQuotesUseCase $getAllQuoteUseCase,
        private readonly CreateQuoteUseCase $createQuoteUseCase,
        private readonly UpdateQuoteUseCase $updateQuoteUseCase,
        private readonly GetQuoteUseCase $getQuoteUseCase,
        private readonly DeleteQuoteUseCase $deleteQuoteUseCase
    ) {}

    /**
     * List quotes with pagination and search
     * 
     * GET /api/quotes?page=1&per_page=20&search=keyword
     * 
     * Retrieves a paginated list of quotes with optional search filtering.
     * Results include metadata for pagination navigation and HATEOAS links.
     * 
     * @param Request $request HTTP request with optional pagination and search parameters
     * 
     * @return JsonResponse JSON response with paginated quotes and metadata
     * 
     * @throws \RuntimeException If repository operation fails
     * 
     * @response 200 {
     *   "data": [ {Quote}, ... ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 20,
     *     "total": 95
     *   },
     *   "links": {
     *     "first": "https://api.example.com/quotes?page=1",
     *     "last": "https://api.example.com/quotes?page=5",
     *     "prev": null,
     *     "next": "https://api.example.com/quotes?page=2"
     *   }
     * }
     * @response 500 { "error": "Error retrieving quotes: <message>" }
     * 
     * @example
     * // Get first page with default limit
     * GET /api/quotes?page=1
     * 
     * @example
     * // Search quotes by subject
     * GET /api/quotes?search=enterprise+proposal&per_page=50
     */
    public function index(Request $request): JsonResponse
    {
        // Extract pagination and search parameters with defaults
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $accountId = $request->get('account_id') ? (int) $request->get('account_id') : null;

        // Execute use case with validated parameters
        $paginator = $this->getAllQuoteUseCase->execute($page, $perPage, $search, $accountId);

        // Extract items from paginator for response
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

    /**
     * Create a new quote with line items
     * 
     * POST /api/quotes
     * 
     * Creates a new sales quote with one or more line items. The authenticated
     * user becomes the creator/owner of the quote.
     * 
     * @param Request $request HTTP request with quote creation data including items array
     * 
     * @return JsonResponse JSON response with created quote ID, quote number, or error
     * 
     * @throws \InvalidArgumentException If request validation fails (422)
     * @throws \RuntimeException If persistence operation fails (500)
     * 
     * @response 201 {
     *   "message": "Quote created successfully",
     *   "quoteid": 12345,
     *   "quoteno": "QT-2026-00123"
     * }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": { field: [errors] } }
     * @response 500 { "error": "Error creating quote: <message>" }
     * 
     * @example
     * // Create a new quote with line items
     * POST /api/quotes
     * {
     *   "subject": "Enterprise Software License",
     *   "accountid": 5001,
     *   "assigned_user_id": 5,
     *   "validtill": "2026-06-30",
     *   "items": [
     *     {
     *       "productname": "Professional License",
     *       "quantity": 10,
     *       "listprice": 500.00,
     *       "discount_percent": 10,
     *       "sequence_no": 1
     *     }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        // Validate incoming request data including nested items array
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
            // Return validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Extract request data and remove auto-generated fields
        $requestData = $request->all();
        unset($requestData['quote_no']);

        // Create DTO from validated data with prepared items
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
            // Execute use case to create quote
            $quote = $this->createQuoteUseCase->execute($createRequest, $authenticatedUser->getId());
            
            
            
            return response()->json([
                'message' => 'Quote created successfully',
                'quoteid' => $quote->quoteid,
                'quoteno' => $quote->quoteno
            ], 201);

        } catch (\Exception $e) {
            // Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error creating quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a quote by its unique identifier
     * 
     * GET /api/quotes/{id}
     * 
     * Retrieves a single quote with all its details including line items,
     * totals, and related entity information.
     * 
     * @param int $id Unique identifier of the quote to retrieve
     * 
     * @return JsonResponse JSON response with quote data or error
     * 
     * @throws \RuntimeException If repository operation fails
     * 
     * @response 200 { Quote }
     * @response 404 { "error": "Quote not found" }
     * @response 500 { "error": "Error retrieving quote: <message>" }
     * 
     * @example
     * // Get quote #12345
     * GET /api/quotes/12345
     * 
     * Response:
     * {
     *   "quoteid": 12345,
     *   "quoteno": "QT-2026-00123",
     *   "subject": "Enterprise Software License",
     *   "quote_stage": "Draft",
     *   "validtill": "2026-06-30",
     *   "items": [ ... ],
     *   "total": 4500.00,
     *   ...
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Execute use case to fetch quote by ID
            $quote = $this->getQuoteUseCase->executeById($id);
            
            if (!$quote) {
                // Quote not found (404 Not Found)
                return response()->json(['error' => 'Quote not found'], 404);
            }
            
            return response()->json($quote);
        } catch (\Exception $e) {
            // Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error retrieving quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing quote with line items
     * 
     * PUT|PATCH /api/quotes/{id}
     * 
     * Updates an existing quote's details and line items. All fields are
     * optional for partial updates. Quote stage transitions are validated.
     * 
     * @param Request $request HTTP request with update data (all fields optional)
     * @param int $id Unique identifier of the quote to update
     * 
     * @return JsonResponse JSON response with update result or error
     * 
     * @throws \InvalidArgumentException If request validation fails (422)
     * @throws \RuntimeException If update operation fails (500)
     * 
     * @response 200 { "message": "Quote updated successfully" }
     * @response 404 { "error": "Quote not found" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 500 { "error": "Error updating quote: <message>" }
     * 
     * @example
     * // Update quote stage and add discount
     * PATCH /api/quotes/12345
     * {
     *   "quote_stage": "Sent",
     *   "validtill": "2026-07-31",
     *   "items": [
     *     {
     *       "productname": "Professional License",
     *       "quantity": 10,
     *       "listprice": 500.00,
     *       "discount_percent": 15,
     *       "sequence_no": 1
     *     }
     *   ]
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Validate incoming update data including nested items array
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
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Extract request data
        $requestData = $request->all();

        // Create DTO from validated data with prepared items
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
            // Execute use case to update quote
            $success = $this->updateQuoteUseCase->execute($updateRequest, $authenticatedUser->getId());
            
            if ($success) {
                return response()->json(['message' => 'Quote updated successfully']);
            }
            
            // Quote not found (404 Not Found)
            return response()->json(['error' => 'Quote not found'], 404);

        } catch (\Exception $e) {
            // Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error updating quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft delete) a quote
     * 
     * DELETE /api/quotes/{id}
     * 
     * Marks a quote as deleted. Only authorized users (owner or admin)
     * can delete a quote (enforced by business rules in the Use Case).
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param int $id Unique identifier of the quote to delete
     * 
     * @return JsonResponse JSON response with deletion result or error
     * 
     * @throws \RuntimeException If deletion operation fails (500)
     * 
     * @response 200 { "message": "Quote deleted successfully" }
     * @response 401 { "error": "User not authenticated" }
     * @response 404 { "error": "Quote not found" }
     * @response 500 { "error": "Error deleting quote: <message>" }
     * 
     * @example
     * // Delete quote #12345
     * DELETE /api/quotes/12345
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Execute use case to delete quote
            $success = $this->deleteQuoteUseCase->execute($id, $authenticatedUser->getId());
            
            if ($success) {
                return response()->json(['message' => 'Quote deleted successfully']);
            }
            
            // Quote not found (404 Not Found)
            return response()->json(['error' => 'Quote not found'], 404);

        } catch (\Exception $e) {
            // Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error deleting quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare quote line items for DTO conversion
     * 
     * Transforms incoming item data from request format to the structure
     * expected by CreateQuoteRequest and UpdateQuoteRequest DTOs.
     * Ensures numeric values are properly cast and defaults are applied.
     * 
     * @param array $items Array of line item data from HTTP request
     * 
     * @return array Prepared items array with consistent structure and types
     * 
     * @example
     * // Input:
     * [
     *   { "productname": "License", "quantity": "10", "listprice": "500" }
     * ]
     * // Output:
     * [
     *   {
     *     "productid": null,
     *     "sequence_no": 1,
     *     "productname": "License",
     *     "quantity": 10.0,
     *     "listprice": 500.0,
     *     "discount_percent": 0.0,
     *     "description": null
     *   }
     * ]
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