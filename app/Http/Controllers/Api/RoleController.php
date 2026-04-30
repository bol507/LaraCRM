<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Role\AssignRoleRequest;
use App\Application\DTOs\Role\CreateRoleRequest;
use App\Application\DTOs\Role\UpdateRoleRequest;
use App\Application\UseCases\Role\AssignUserRoleUseCase;
use App\Application\UseCases\Role\CreateRoleUseCase;
use App\Application\UseCases\Role\DeleteRoleUseCase;
use App\Application\UseCases\Role\GetAllRolesUseCase;
use App\Application\UseCases\Role\GetAvailableRolesUseCase;
use App\Application\UseCases\Role\UpdateRoleUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RoleController extends Controller
{
    public function __construct(
        private readonly GetAllRolesUseCase $getAll,
        private readonly CreateRoleUseCase $create,
        private readonly UpdateRoleUseCase $update,
        private readonly DeleteRoleUseCase $delete,
        private readonly GetAvailableRolesUseCase $getRoles,
        private readonly AssignUserRoleUseCase $assignRole
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->getAll->execute()]);
    }
    /**
     * GET /api/roles
     * Return all available roles
     */
    public function available(): JsonResponse
    {
        $roles = $this->getRoles->execute();
        return response()->json(['data' => array_map(fn($r) => $r->toArray(), $roles)]);
    }

    public function store(Request $request): JsonResponse
    {
        $dto = CreateRoleRequest::fromArray($request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|string|exists:vtiger.vtiger_role,roleid',
        ]));
        $role = $this->create->execute($dto);
        return response()->json(['data' => $role, 'message' => 'Role created'], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $dto = UpdateRoleRequest::fromArray($request->validate([
            'name' => 'nullable|string|max:100',
            'parent_id' => 'nullable|string|exists:vtiger.vtiger_role,roleid',
            'sharing_rule' => 'nullable|integer|in:0,1,2,3',
        ]));
        $role = $this->update->execute($id, $dto);
        return response()->json(['data' => $role, 'message' => 'Role updated']);
    }

    /**
     * DELETE /api/settings/roles/{id}
     * Delete a hierarchical role with cascade cleanup.
     * 
     * Query params:
     * - force=true: Auto-reassign users to parent role (default: false = error if users exist)
     */
    public function destroy(Request $request, string $id, DeleteRoleUseCase $useCase): JsonResponse
    {
        // Validar formato de roleid
        if (!preg_match('/^H\d+$/', $id)) {
            return response()->json(['error' => 'Invalid role ID format'], 400);
        }

        $authenticatedUser = $request->attributes->get('auth_user');
        if (!$authenticatedUser) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Leer parámetro force de la query string
            $force = $request->boolean('force', false);

            $useCase->execute($id, $authenticatedUser->getId(), $force);

            return response()->json([
                'message' => 'Role deleted successfully',
                'data' => ['role_id' => $id, 'force' => $force],
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Si es por asignaciones existentes, sugerir uso de force
            if (str_contains($e->getMessage(), 'user(s) assigned')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'hint' => 'Use ?force=true to auto-reassign users to parent role',
                ], 409); // Conflict
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

 

    //TODO create use case  
    public function checkName(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $excludeId = $request->query('exclude_id');

        if (!$name) {
            return response()->json(['error' => 'Name parameter required'], 400);
        }

        $exists = DB::connection('vtiger')
            ->table('vtiger_role')
            ->join('vtiger_crmentity', function ($join) {
                $join->on('vtiger_role.roleid', '=', 'vtiger_crmentity.crmid')
                    ->where('vtiger_crmentity.setype', '=', 'Roles');
            })
            ->where('vtiger_role.rolename', $name)
            ->where('vtiger_crmentity.deleted', 0)
            ->when($excludeId, fn($q) => $q->where('vtiger_role.roleid', '!=', $excludeId))
            ->exists();

        return response()->json(['available' => !$exists]);
    }
}
