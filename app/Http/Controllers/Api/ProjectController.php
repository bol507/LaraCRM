<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Project\GetAllProjectsUseCase;
use App\Application\UseCases\Project\GetProjectByIdUseCase;
use App\Application\UseCases\Project\CreateProjectUseCase;
use App\Application\UseCases\Project\UpdateProjectUseCase;
use App\Application\UseCases\Project\DeleteProjectUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Project API Controller
 * 
 * Handles HTTP requests for project management operations in the CRM system.
 * 
 * Responsibilities:
 * - Parse and validate HTTP request data for project CRUD operations
 * - Delegate business logic to Application Use Cases
 * - Transform domain entities to JSON API format
 * - Handle exceptions and return appropriate HTTP status codes
 * - Manage pagination, filtering, and search parameters
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
 * @see \App\Application\UseCases\Project\GetAllProjectsUseCase
 * @see \App\Application\UseCases\Project\GetProjectByIdUseCase
 * @see \App\Application\UseCases\Project\CreateProjectUseCase
 * @see \App\Application\UseCases\Project\UpdateProjectUseCase
 * @see \App\Application\UseCases\Project\DeleteProjectUseCase
 */
class ProjectController extends Controller
{
    /**
     * Use case for retrieving all projects with pagination
     * 
     * @var GetAllProjectsUseCase
     */
    protected GetAllProjectsUseCase $getAllProjectsUseCase;

    /**
     * Use case for retrieving a single project by ID
     * 
     * @var GetProjectByIdUseCase
     */
    protected GetProjectByIdUseCase $getProjectByIdUseCase;

    /**
     * Use case for creating new projects
     * 
     * @var CreateProjectUseCase
     */
    protected CreateProjectUseCase $createProjectUseCase;

    /**
     * Use case for updating existing projects
     * 
     * @var UpdateProjectUseCase
     */
    protected UpdateProjectUseCase $updateProjectUseCase;

    /**
     * Use case for deleting projects
     * 
     * @var DeleteProjectUseCase
     */
    protected DeleteProjectUseCase $deleteProjectUseCase;

    /**
     * Constructor with dependency injection
     * 
     * @param GetAllProjectsUseCase $getAllProjectsUseCase Use case for listing projects
     * @param GetProjectByIdUseCase $getProjectByIdUseCase Use case for fetching single project
     * @param CreateProjectUseCase $createProjectUseCase Use case for creating projects
     * @param UpdateProjectUseCase $updateProjectUseCase Use case for updating projects
     * @param DeleteProjectUseCase $deleteProjectUseCase Use case for deleting projects
     */
    public function __construct(
        GetAllProjectsUseCase $getAllProjectsUseCase,
        GetProjectByIdUseCase $getProjectByIdUseCase,
        CreateProjectUseCase $createProjectUseCase,
        UpdateProjectUseCase $updateProjectUseCase,
        DeleteProjectUseCase $deleteProjectUseCase
    ) {
        $this->getAllProjectsUseCase = $getAllProjectsUseCase;
        $this->getProjectByIdUseCase = $getProjectByIdUseCase;
        $this->createProjectUseCase = $createProjectUseCase;
        $this->updateProjectUseCase = $updateProjectUseCase;
        $this->deleteProjectUseCase = $deleteProjectUseCase;
    }

    /**
     * Get all projects with pagination and filtering
     * 
     * GET /api/projects?page=1&limit=10&search=keyword&status=Active
     * 
     * Retrieves a paginated list of projects with optional search and status filtering.
     * Results include metadata for pagination navigation.
     * 
     * @param Request $request HTTP request with optional pagination and filter parameters
     * 
     * @return JsonResponse JSON response with paginated projects and metadata
     * 
     * @throws \RuntimeException If repository operation fails
     * 
     * @response 200 {
     *   "data": [ {Project}, ... ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 10,
     *     "total": 45
     *   }
     * }
     * @response 500 { "error": "Error retrieving projects: <message>" }
     * 
     * @example
     * // Get first page with default limit
     * GET /api/projects?page=1
     * 
     * @example
     * // Search active projects by name
     * GET /api/projects?search=website&status=Active&limit=20
     */
    public function index(Request $request): JsonResponse
    {
        try {
            //  Extract pagination and filter parameters with defaults
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', null);
            $status = $request->get('status', null);
            //  Extract account_id parameter
            $accountId = $request->get('account_id') ? (int) $request->get('account_id') : null;
            
            //  Execute use case with validated parameters
            $projects = $this->getAllProjectsUseCase->execute($page, $limit, $search, $status, $accountId);

            return response()->json([
                'data' => $projects->items(),
                'meta' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                ]
            ]);
        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error retrieving projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a project by its unique identifier
     * 
     * GET /api/projects/{id}
     * 
     * Retrieves a single project with all its details by ID.
     * 
     * @param int $id Unique identifier of the project to retrieve
     * 
     * @return JsonResponse JSON response with project data or error
     * 
     * @throws \RuntimeException If repository operation fails
     * 
     * @response 200 { Project }
     * @response 404 { "error": "Project not found" }
     * @response 500 { "error": "Error retrieving project: <message>" }
     * 
     * @example
     * // Get project #10018
     * GET /api/projects/10018
     * 
     * Response:
     * {
     *   "projectid": 10018,
     *   "projectname": "Website Redesign",
     *   "projectstatus": "In Progress",
     *   "targetbudget": "15000.00",
     *   ...
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            //  Execute use case to fetch project by ID
            $project = $this->getProjectByIdUseCase->execute($id);

            if (!$project) {
                //  Project not found (404 Not Found)
                return response()->json([
                    'error' => 'Project not found'
                ], 404);
            }

            return response()->json($project);
        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error retrieving project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new project
     * 
     * POST /api/projects
     * 
     * Creates a new project in the system. The authenticated user becomes
     * the creator/owner of the project.
     * 
     * @param Request $request HTTP request with project creation data
     * 
     * @return JsonResponse JSON response with created project ID or error
     * 
     * @throws \InvalidArgumentException If request validation fails (422)
     * @throws \RuntimeException If persistence operation fails (500)
     * 
     * @response 201 {
     *   "message": "Project created successfully",
     *   "projectid": 10025
     * }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": { field: [errors] } }
     * @response 500 { "error": "Error creating project: <message>" }
     * 
     * @example
     * // Create a new project
     * POST /api/projects
     * {
     *   "projectname": "Mobile App Development",
     *   "accountid": 5001,
     *   "projectstatus": "Planning",
     *   "projectpriority": "High",
     *   "targetbudget": "25000.00",
     *   "startdate": "2026-03-01",
     *   "targetenddate": "2026-09-30",
     *   "description": "Native mobile app for iOS and Android"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            //  Validate incoming request data
            $validated = $request->validate([
                'projectname' => 'required|string|max:255',
                'accountid' => 'required|integer',
                'assigned_user_id' => 'nullable|integer',
                'projectstatus' => 'nullable|string',
                'projectpriority' => 'nullable|string',
                'projecttype' => 'nullable|string',
                'startdate' => 'nullable|date',
                'targetenddate' => 'nullable|date',
                'targetbudget' => 'nullable|string',
                'projecturl' => 'nullable|string',
                'description' => 'nullable|string',
                'potentialid' => 'nullable|integer',
                'quoteid' => 'nullable|integer',
            ]);

            //  Get authenticated user from JWT middleware
            $authenticatedUser = $request->attributes->get('auth_user');

            //  Execute use case to create project
            $projectId = $this->createProjectUseCase->execute($validated, $authenticatedUser->getId());

            return response()->json([
                'message' => 'Project created successfully',
                'projectid' => $projectId
            ], 201);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error creating project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing project
     * 
     * PUT|PATCH /api/projects/{id}
     * 
     * Updates an existing project's details. All fields are optional for partial updates.
     * 
     * @param Request $request HTTP request with update data (all fields optional)
     * @param int $id Unique identifier of the project to update
     * 
     * @return JsonResponse JSON response with update result or error
     * 
     * @throws \InvalidArgumentException If request validation fails (422)
     * @throws \RuntimeException If update operation fails (500)
     * 
     * @response 200 { "message": "Project updated successfully" }
     * @response 404 { "error": "Project not found" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 500 { "error": "Error updating project: <message>" }
     * 
     * @example
     * // Update project status and budget
     * PATCH /api/projects/10018
     * {
     *   "projectstatus": "In Progress",
     *   "targetbudget": "18000.00",
     *   "progress": "45"
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            //  Validate incoming update data (all fields optional for partial updates)
            $validated = $request->validate([
                'projectname' => 'nullable|string|max:255',
                'accountid' => 'nullable|integer',
                'assigned_user_id' => 'nullable|integer',
                'projectstatus' => 'nullable|string',
                'projectpriority' => 'nullable|string',
                'projecttype' => 'nullable|string',
                'startdate' => 'nullable|date',
                'targetenddate' => 'nullable|date',
                'actualenddate' => 'nullable|date',
                'targetbudget' => 'nullable|string',
                'projecturl' => 'nullable|string',
                'progress' => 'nullable|string',
                'description' => 'nullable|string',
                'potentialid' => 'nullable|integer',
            ]);

            //  Execute use case to update project
            $success = $this->updateProjectUseCase->execute($id, $validated);

            if (!$success) {
                //  Project not found (404 Not Found)
                return response()->json([
                    'error' => 'Project not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Project updated successfully'
            ]);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error updating project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete (soft delete) a project
     * 
     * DELETE /api/projects/{id}
     * 
     * Marks a project as deleted. Only authorized users can delete projects
     * (enforced by business rules in the Use Case).
     * 
     * @param int $id Unique identifier of the project to delete
     * 
     * @return JsonResponse JSON response with deletion result or error
     * 
     * @throws \RuntimeException If deletion operation fails (500)
     * 
     * @response 200 { "message": "Project deleted successfully" }
     * @response 404 { "error": "Project not found" }
     * @response 500 { "error": "Error deleting project: <message>" }
     * 
     * @example
     * // Delete project #10018
     * DELETE /api/projects/10018
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            //  Execute use case to delete project
            $success = $this->deleteProjectUseCase->execute($id);

            if (!$success) {
                //  Project not found (404 Not Found)
                return response()->json([
                    'error' => 'Project not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Project deleted successfully'
            ]);

        } catch (\Exception $e) {
            //  Log and return error for unexpected failures (500)
            return response()->json([
                'error' => 'Error deleting project: ' . $e->getMessage()
            ], 500);
        }
    }
}