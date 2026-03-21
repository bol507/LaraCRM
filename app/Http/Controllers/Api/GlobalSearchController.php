<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\GlobalSearchUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class GlobalSearchController
 * 
 * Handles global search requests across multiple entity types in the CRM system.
 * This controller serves as the HTTP entry point for search functionality,
 * validating incoming requests and delegating business logic to the use case layer.
 * 
 * The global search feature allows users to search across multiple entity types
 * (clients, opportunities, quotes, projects, etc.) with a single query, returning
 * relevant results from all matching entities in a unified response format.
 * 
 * @package App\Http\Controllers\Api
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @version 1.0.0
 * 
 * @see GlobalSearchUseCase For the business logic implementation
 * @see Controller For the base controller class
 */
class GlobalSearchController extends Controller
{
    /**
     * The global search use case instance.
     * 
     * This use case orchestrates the search operation across multiple
     * entity repositories and aggregates results into a unified response.
     * 
     * @var GlobalSearchUseCase
     */
    private GlobalSearchUseCase $globalSearchUseCase;

    /**
     * GlobalSearchController constructor.
     * 
     * Injects the GlobalSearchUseCase dependency via constructor injection.
     * This follows the dependency inversion principle, allowing for easier
     * testing and maintenance of the search functionality.
     * 
     * @param GlobalSearchUseCase $globalSearchUseCase The global search use case instance
     * 
     * @return void
     */
    public function __construct(
        GlobalSearchUseCase $globalSearchUseCase
    ) { 
        $this->globalSearchUseCase = $globalSearchUseCase;
    }

    /**
     * Execute a global search across all entity types.
     * 
     * This endpoint performs a search query across multiple entity types in the CRM system,
     * including clients, opportunities, quotes, projects, contacts, and tasks. Results are
     * aggregated and returned in a unified format with entity type information for each match.
     * 
     * The search is case-insensitive and supports partial matching. Results are ranked by
     * relevance and limited to the specified maximum number of results per entity type.
     * 
     * @param Request $request The HTTP request containing search parameters.
     * 
     * @return JsonResponse A JSON response containing the search results aggregated by entity type.
     * 
     * @throws ValidationException If the request validation fails.
     * 
     * @api
     * 
     * @endpoint GET /api/search
     * 
     * @request-param string $query The search query string (required, min 3 characters, max 100 characters)
     * @request-param int|null $limit Maximum number of results to return per entity type (optional, default 10, max 50)
     * 
     * @response 200 {
     *   "clients": array,
     *   "opportunities": array,
     *   "quotes": array,
     *   "projects": array,
     *   "contacts": array,
     *   "tasks": array,
     *   "total": int
     * }
     * 
     * @response 422 {
     *   "message": "Validation failed",
     *   "errors": {
     *     "query": ["The query field is required.", "The query must be at least 3 characters."]
     *   }
     * }
     * 
     * @example Request
     * GET /api/search?query=acme&limit=20
     * 
     * @example Response 200
     * {
     *   "clients": [
     *     {"id": 1, "name": "Acme Corporation", "type": "client"}
     *   ],
     *   "opportunities": [
     *     {"id": 5, "name": "Acme Deal Q1", "type": "opportunity"}
     *   ],
     *   "total": 2
     * }
     * 
     * @see GlobalSearchUseCase::execute() For the business logic implementation
     */
    public function search(Request $request): JsonResponse
    {
        // Validate incoming request parameters
        $validated = $request->validate([
            'query' => 'required|string|min:3|max:100',
            'limit' => 'nullable|integer|max:50',
        ]);

        // Extract validated parameters with default values
        $query = $validated['query'];
        $limit = $validated['limit'] ?? 10;

        // Execute the global search use case
        $results = $this->globalSearchUseCase->execute($query, $limit);

        // Return results as JSON response
        return response()->json($results);
    }
}