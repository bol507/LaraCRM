<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\DTOs\CreateOpportunityRequest;
use App\Application\DTOs\OpportunityDto;
use App\Application\DTOs\UpdateOpportunityRequest;
use App\Application\UseCases\Opportunity\DeleteOpportunityUseCase;
use App\Application\UseCases\Opportunity\CreateOpportunityUseCase;
use App\Application\UseCases\Opportunity\GetAllOpportunitiesUseCase;
use App\Application\UseCases\Opportunity\GetOpportunityUseCase;
use App\Application\UseCases\Opportunity\UpdateOpportunityUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Opportunity API Controller
 * 
 * Handles HTTP requests for opportunity (sales pipeline) management operations.
 * 
 * Responsibilities:
 * - Parse and validate HTTP request data for opportunity CRUD operations
 * - Delegate business logic to Application Use Cases
 * - Transform domain entities to JSON API format via DTOs
 * - Handle exceptions and return appropriate HTTP status codes
 * - Manage pagination, filtering, and sorting parameters
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
 * @see \App\Application\UseCases\GetAllOpportunitiesUseCase
 * @see \App\Application\UseCases\CreateOpportunityUseCase
 * @see \App\Application\UseCases\UpdateOpportunityUseCase
 * @see \App\Application\UseCases\DeleteOpportunityUseCase
 * @see \App\Application\DTOs\OpportunityDto
 */
class OpportunityController extends Controller
{
    /**
     * Constructor with dependency injection
     * 
     * @param GetAllOpportunitiesUseCase $getAllOpportunitiesUseCase Use case for listing opportunities
     * @param CreateOpportunityUseCase $createOpportunityUseCase Use case for creating opportunities
     * @param UpdateOpportunityUseCase $updateOpportunityUseCase Use case for updating opportunities
     * @param DeleteOpportunityUseCase $deleteOpportunityUseCase Use case for deleting opportunities
     */
    public function __construct(
        private readonly GetAllOpportunitiesUseCase $getAllOpportunitiesUseCase,
        private readonly GetOpportunityUseCase $getOpportunityUseCase,
        private readonly CreateOpportunityUseCase $createOpportunityUseCase,
        private readonly UpdateOpportunityUseCase $updateOpportunityUseCase,
        private readonly DeleteOpportunityUseCase $deleteOpportunityUseCase
    ) {}

    /**
     * List opportunities with pagination and search
     * 
     * GET /api/opportunities?page=1&per_page=20&search=keyword
     * 
     * Retrieves a paginated list of opportunities with optional search filtering.
     * Results include metadata for pagination navigation.
     * 
     * @param Request $request HTTP request with optional pagination and search parameters
     * 
     * @return JsonResponse JSON response with paginated opportunities and metadata
     * 
     * @response 200 {
     *   "data": [ {OpportunityDto}, ... ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 20,
     *     "total": 95
     *   },
     *   "links": {
     *     "first": "https://api.example.com/opportunities?page=1",
     *     "last": "https://api.example.com/opportunities?page=5",
     *     "prev": null,
     *     "next": "https://api.example.com/opportunities?page=2"
     *   }
     * }
     * @response 500 { "error": "Failed to retrieve opportunities" }
     * 
     * @example
     * // Get first page with 20 items per page
     * GET /api/opportunities?page=1&per_page=20
     * 
     * @example
     * // Search opportunities by name
     * GET /api/opportunities?search=enterprise+software
     */
    public function index(Request $request): JsonResponse
    {
        //  Extract pagination and search parameters with defaults
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $search = $request->get('search');

        $accountId = $request->get('account_id') ? (int) $request->get('account_id') : null;

        Log::info('🔍 Opportunities request', [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'account_id' => $accountId,  
        ]);
        //  Execute use case with validated parameters
        $paginator = $this->getAllOpportunitiesUseCase->execute($page, $perPage, $search, $accountId);

        //  Transform Opportunity entities to DTOs for API response
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

    /**
     * Get a single opportunity by ID
     * 
     * GET /api/opportunities/{id}
     */
    public function show(int $id): JsonResponse
    {
        $opportunity = $this->getOpportunityUseCase->execute($id);

        if (!$opportunity) {
            return response()->json(['error' => 'Opportunity not found'], 404);
        }

        return response()->json(OpportunityDto::fromEntity($opportunity));
    }

    /**
     * Create a new opportunity
     * 
     * POST /api/opportunities
     * 
     * Creates a new sales opportunity in the pipeline. The authenticated user
     * becomes the creator/owner of the opportunity.
     * 
     * @param Request $request HTTP request with opportunity creation data
     * 
     * @return JsonResponse JSON response with created opportunity ID or error
     * 
     * @throws ValidationException If request data fails validation (422)
     * @throws \InvalidArgumentException If business rules are violated (400)
     * @throws \RuntimeException If persistence operation fails (500)
     * 
     * @response 201 {
     *   "message": "Opportunity created successfully",
     *   "opportunity_id": 12345
     * }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": { field: [errors] } }
     * @response 500 { "error": "Error creating opportunity: <message>" }
     * 
     * @example
     * // Create a new opportunity
     * POST /api/opportunities
     * {
     *   "potentialname": "Enterprise Software Deal",
     *   "sales_stage": "Prospecting",
     *   "amount": 50000,
     *   "closingdate": "2026-06-30",
     *   "probability": 25,
     *   "related_to": 10018,
     *   "description": "Initial contact made, follow-up scheduled"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        //  Validate incoming request data
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
            //  Return validation errors (422 Unprocessable Entity)
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        //  Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        //  Extract request data
        $requestData = $request->all();

        //  Create DTO from validated data
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
            //  Execute use case to create opportunity
            $opportunityId = $this->createOpportunityUseCase->execute($createRequest, $authenticatedUser->getId());

            return response()->json([
                'message' => 'Opportunity created successfully',
                'opportunity_id' => $opportunityId
            ], 201);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error creating opportunity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing opportunity
     * 
     * PUT|PATCH /api/opportunities/{id}
     * 
     * Updates an existing opportunity's details. Only authorized users
     * (owner or admin) can modify opportunities.
     * 
     * @param Request $request HTTP request with update data
     * @param int $id Unique identifier of the opportunity to update
     * 
     * @return JsonResponse JSON response with update result or error
     * 
     * @throws ValidationException If request data fails validation (422)
     * @throws \InvalidArgumentException If opportunity not found (404)
     * @throws \RuntimeException If update operation fails (500)
     * 
     * @response 200 { "message": "Opportunity updated successfully" }
     * @response 401 { "error": "User not authenticated" }
     * @response 404 { "error": "Opportunity not found" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 500 { "error": "Error updating opportunity: <message>" }
     * 
     * @example
     * // Update opportunity stage and probability
     * PATCH /api/opportunities/12345
     * {
     *   "potentialname": "Enterprise Software Deal",
     *   "sales_stage": "Qualification",
     *   "probability": 50,
     *   "amount": 55000
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        //  Validate incoming update data
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
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // ✅ Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        //  Extract request data
        $requestData = $request->all();

        //  Create DTO from validated data
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
            //  Execute use case to update opportunity
            $success = $this->updateOpportunityUseCase->execute($updateRequest, $authenticatedUser->getId());

            if ($success) {
                return response()->json(['message' => 'Opportunity updated successfully']);
            }

            //  Opportunity not found (404 Not Found)
            return response()->json(['error' => 'Opportunity not found'], 404);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error updating opportunity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft delete) an opportunity
     * 
     * DELETE /api/opportunities/{id}
     * 
     * Marks an opportunity as deleted. Only the owner or an admin
     * can delete an opportunity (enforced by business rules).
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param int $id Unique identifier of the opportunity to delete
     * 
     * @return JsonResponse JSON response with deletion result or error
     * 
     * @throws \InvalidArgumentException If opportunity not found (404)
     * @throws \RuntimeException If deletion operation fails (500)
     * 
     * @response 200 { "message": "Opportunity deleted successfully" }
     * @response 401 { "error": "User not authenticated" }
     * @response 404 { "error": "Opportunity not found or already deleted" }
     * @response 500 { "error": "Error deleting opportunity: <message>" }
     * 
     * @example
     * // Delete opportunity #12345
     * DELETE /api/opportunities/12345
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        //  Get authenticated user from JWT middleware
        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            //  Execute use case to delete opportunity
            $success = $this->deleteOpportunityUseCase->execute($id, $authenticatedUser->getId());

            if ($success) {
                return response()->json(['message' => 'Opportunity deleted successfully']);
            }

            //  Opportunity not found or already deleted (404 Not Found)
            return response()->json(['error' => 'Opportunity not found or already deleted'], 404);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error deleting opportunity: ' . $e->getMessage()
            ], 500);
        }
    }
}