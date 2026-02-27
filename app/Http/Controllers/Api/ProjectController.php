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

class ProjectController extends Controller {
    protected $getAllProjectsUseCase;
    protected $getProjectByIdUseCase;
    protected $createProjectUseCase;
    protected $updateProjectUseCase;
    protected $deleteProjectUseCase;

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
     * Get all projects with pagination and search term filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', null);
            $status = $request->get('status', null);

            $projects = $this->getAllProjectsUseCase->execute($page, $limit, $search, $status);

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
            return response()->json([
                'error' => 'Error al obtener proyectos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a project by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $project = $this->getProjectByIdUseCase->execute($id);

            if (!$project) {
                return response()->json([
                    'error' => 'Proyecto no encontrado'
                ], 404);
            }

            return response()->json($project);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener proyecto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new project
     */
    public function store(Request $request): JsonResponse
    {
        try {
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
            $authenticatedUser = $request->attributes->get('auth_user');
            $projectId = $this->createProjectUseCase->execute($validated, $authenticatedUser->id);

            return response()->json([
                'message' => 'Proyecto creado exitosamente',
                'projectid' => $projectId
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear proyecto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a project
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
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

            $success = $this->updateProjectUseCase->execute($id, $validated);

            if (!$success) {
                return response()->json([
                    'error' => 'Proyecto no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Proyecto actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar proyecto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a project
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $success = $this->deleteProjectUseCase->execute($id);

            if (!$success) {
                return response()->json([
                    'error' => 'Proyecto no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Proyecto eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar proyecto: ' . $e->getMessage()
            ], 500);
        }
    }
}