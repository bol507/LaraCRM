<?php
// app/Http/Controllers/Api/ProfileController.php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Profile\CreateProfileRequest;
use App\Http\Controllers\Controller;
use App\Application\UseCases\Profile\UpdateProfileUseCase;
use App\Application\DTOs\Profile\UpdateProfilePermissionsRequest;
use App\Application\Repositories\ModuleRepositoryInterface;
use App\Application\Repositories\ProfileRepositoryInterface;
use App\Application\UseCases\Profile\CreateProfileUseCase;
use App\Infrastructure\Services\ProfilePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileRepositoryInterface $repository,
        private readonly UpdateProfileUseCase $updateUseCase,
        private readonly CreateProfileUseCase $createUseCase,
        private readonly ProfilePermissionService $permService,
        private readonly ModuleRepositoryInterface $moduleRepo
    ) {}

    /**
     * GET /api/settings/profiles
     * List all permission profiles with module permissions.
     */
    public function index(): JsonResponse
    {
        $profiles = $this->repository->findAll();

        $data = array_map(function ($profile) {
            return [
                'profileid' => $profile['profileid'],
                'name' => $profile['profilename'],
                'modules' => $profile['modules'],
            ];
        }, $profiles);

        return response()->json(['data' => $data], 200);
    }

    /**
     * POST /api/settings/profiles
     * Create a new permission profile.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'    => 'required|string|max:100|unique:vtiger.vtiger_profile,profilename',
                'role_id' => 'nullable|string|exists:vtiger.vtiger_role,roleid',
            ]);

            $profile = $this->repository->create($validated['name'], $validated['role_id'] ?? null);
            Cache::forget('settings:profiles_list');

            return response()->json([
                'data' => $profile,
                'message' => 'Profile created successfully'
            ], 201);
        } catch (InvalidArgumentException $e) {
            // Domain validation failed → 400
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            // Persistence error → 500
            return response()->json(['error' => 'Failed to create profile: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/settings/profiles/{id}
     * Update profile name and/or module permissions.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return response()->json(['error' => 'Invalid profile ID'], 400);
        }

        $profileId = (int) $id;

        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:100',
                'modules' => 'required|array',
                'modules.*.tabid' => 'required|integer|min:1',
                'modules.*.permissions' => 'required|array',
                'modules.*.permissions.*' => 'in:read,write,create,delete',
            ]);

            $dto = UpdateProfilePermissionsRequest::fromArray($validated);

            $updated = $this->updateUseCase->execute($profileId, $dto);

            return response()->json([
                'message' => 'Profile permissions updated successfully',
                'data' => [
                    'profileid' => $updated['profileid'],
                    'name' => $updated['profilename'],
                    'modules' => $updated['modules'],
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    // TODO: Create use case
    public function checkName(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $excludeId = $request->query('exclude_id');


        if (!$name || trim($name) === '') {
            return response()->json(['error' => 'Name parameter is required'], 400);
        }

        if (strlen($name) > 100) {
            return response()->json(['error' => 'Name too long (max 100 characters)'], 400);
        }


        $query = DB::connection('vtiger')
            ->table('vtiger_profile')
            ->join('vtiger_crmentity', function ($join) {
                $join->on('vtiger_profile.profileid', '=', 'vtiger_crmentity.crmid')
                    ->where('vtiger_crmentity.setype', '=', 'Profiles');
            })
            ->where('vtiger_profile.profilename', $name)
            ->where('vtiger_crmentity.deleted', 0);

        // exclude current profile if provided
        if ($excludeId && is_numeric($excludeId)) {
            $query->where('vtiger_profile.profileid', '!=', (int) $excludeId);
        }

        $exists = $query->exists();

        return response()->json(['available' => !$exists]);
    }

    public function getPermissions(string $profileId): JsonResponse
    {
        $modules = $this->moduleRepo->getAllActive();
        $rawPerms = $this->permService->getRawPermissionsForProfile((int) $profileId);

        $result = array_map(function ($module) use ($rawPerms) {
            $tabid = $module['tabid'] ?? null;
            if ($tabid === null) {
                return null;
            }

            $perms = $rawPerms[$tabid] ?? [];
            $actions = array_filter(
                ['read', 'write', 'create', 'delete'],
                fn($a) => $perms[$a] ?? false
            );

            return [
                'tabid' => $tabid,
                'name' => $module['name'] ?? 'Unknown',
                'permissions' => array_values($actions),
            ];
        }, $modules); 

        
        $result = array_filter($result, fn($r) => $r !== null);

        return response()->json(['data' => ['modules' => array_values($result)]]);
    }
}
