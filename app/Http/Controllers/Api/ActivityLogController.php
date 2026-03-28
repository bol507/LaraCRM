<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\ActivityLog\GetRecentActivitiesUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for activity log management.
 * 
 * Responsible for:
 * - Receiving and validating HTTP requests
 * - Invoking the appropriate use case
 * - Returning appropriate HTTP responses
 * 
 * Does not contain business logic.
 */
class ActivityLogController extends Controller
{
    public function __construct(
        private GetRecentActivitiesUseCase $getRecentActivitiesUseCase
    ) {}

    /**
     * Get recent activities.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Validate input parameters
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
            'entity_type' => 'nullable|string',
            'action' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:100',
        ]);

        // Execute use case
        $result = $this->getRecentActivitiesUseCase->execute(
            limit: $validated['limit'] ?? null,
            offset: $validated['offset'] ?? null, // ✅ Pasar offset
            entityType: $validated['entity_type'] ?? null,
            action: $validated['action'] ?? null,
            userId: $validated['user_id'] ?? null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            search: $validated['search'] ?? null
        );

        // Return response
        return response()->json($result->toArray());
    }
}
