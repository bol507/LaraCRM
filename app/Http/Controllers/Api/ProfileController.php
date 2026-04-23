<?php
// app/Http/Controllers/Api/ProfileController.php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Profile\CreateProfileRequest;
use App\Http\Controllers\Controller;
use App\Application\UseCases\Profile\UpdateProfileUseCase;
use App\Application\DTOs\Profile\UpdateProfilePermissionsRequest;
use App\Application\Repositories\ProfileRepositoryInterface;
use App\Application\UseCases\Profile\CreateProfileUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileRepositoryInterface $repository,
        private readonly UpdateProfileUseCase $updateUseCase,
        private readonly CreateProfileUseCase $createUseCase,
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
    public function store(Request $request ): JsonResponse
    {
        try {
            // HTTP validation (input layer)
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'description' => 'nullable|string|max:255',
                'modules' => 'nullable|array',
                'modules.*.tabid' => 'required|integer|min:1',
                'modules.*.permissions' => 'required|array',
                'modules.*.permissions.*' => 'in:read,write,create,delete',
            ], [
                'modules.*.permissions.*.in' => 'Invalid permission. Allowed: read, write, create, delete',
            ]);

            // DTO with domain validation
            $dto = CreateProfileRequest::fromArray($validated);

            // Execute use case
            $created = $this->createUseCase->execute($dto);

            return response()->json([
                'message' => 'Profile created successfully',
                'data' => $created,
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
}