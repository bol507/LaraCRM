<?php
namespace App\Http\Controllers\Api;

use App\Application\DTOs\Role\AssignRoleRequest;
use App\Application\UseCases\Role\AssignUserRoleUseCase;
use App\Application\UseCases\Role\GetAvailableRolesUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly GetAvailableRolesUseCase $getRoles,
        private readonly AssignUserRoleUseCase $assignRole
    ) {}

    /**
     * GET /api/roles
     * Return all available roles
     */
    public function index(): JsonResponse
    {
        $roles = $this->getRoles->execute();
        return response()->json(['data' => array_map(fn($r) => $r->toArray(), $roles)]);
    }

    /**
     * PUT /api/users/{userId}/role
     * Assign a role to a user
     */
    public function assign(Request $request, string $userId): JsonResponse
    {
        if (!is_numeric($userId) || (int) $userId <= 0) {
            return response()->json(['error' => 'Invalid user ID'], 400);
        }

        $validated = $request->validate([
            'role_id' => 'required|string|exists:vtiger.vtiger_role,roleid',
        ]);

        $dto = new AssignRoleRequest(
            userId: (int) $userId,
            roleId: $validated['role_id']
        );

        try {
            $authId = $request->attributes->get('auth_user')->getId();
            $this->assignRole->execute($dto, $authId);
            
            return response()->json(['message' => 'Role assigned successfully']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}