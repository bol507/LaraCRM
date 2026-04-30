<?php
// app/Http/Controllers/Api/RoleProfileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Role\AssignProfileToRoleUseCase;
use App\Application\DTOs\Role\AssignProfileRequest;
use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoleProfileController extends Controller
{
    public function __construct(
        private readonly AssignProfileToRoleUseCase $assignUseCase,
        private readonly RoleProfileAssignmentRepositoryInterface $assignmentRepository,
    ) {}

    /**
     * PUT /api/settings/roles/{roleId}/profile
     * Assign a permission profile to a hierarchical role.
     */
    public function assign(Request $request, string $roleId): JsonResponse
    {
        try {
            // HTTP validation
            $validated = $request->validate([
                'profile_id' => 'required|integer|exists:vtiger.vtiger_profile,profileid',
            ]);

            // DTO with domain validation
            $dto = AssignProfileRequest::fromArray($validated, $roleId);
            
            // Execute use case
            $this->assignUseCase->execute($dto);
            
            return response()->json([
                'message' => 'Profile assigned to role successfully',
                'data' => [
                    'role_id' => $roleId,
                    'profile_id' => $dto->profileId,
                ],
            ], 200);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/settings/roles/{roleId}/profile
     * Get the profile currently assigned to a role.
     */
    public function show(string $roleId): JsonResponse
    {
        $assignment = $this->assignmentRepository->findByRoleId($roleId);
        
        if (!$assignment) {
            return response()->json([
                'message' => 'No profile assigned to this role',
                'data' => null,
            ], 404);
        }
        
        return response()->json([
            'data' => [
                'profileid' => (string) $assignment['profileid'],
                'name' => $assignment['profilename'],
            ],
        ], 200);
    }
}