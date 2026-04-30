<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\Role\AssignRoleRequest;
use App\Application\DTOs\User\UpdateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Application\UseCases\Role\AssignUserRoleUseCase;
use App\Domain\Entities\User;
use App\Infrastructure\Services\UserRoleDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateUserUseCase
{
    private const GENERIC_EMAILS = [
        'info@canalwoods.com',
        'noreply@canalwoods.com',
        'admin@canalwoods.com',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AssignUserRoleUseCase $assignUserRoleUseCase,
        private readonly UpdateEntityUseCase $updateEntityUseCase,
    ) {}

    /**
     * Execute user update flow.
     *
     * @param int $userId ID of user to update
     * @param UpdateUserRequest $request DTO with fields to update
     * @param int $authenticatedUserId ID of user performing the action (for audit)
     * @return User Updated user entity
     *
     * @throws InvalidArgumentException If business rules are violated
     * @throws RuntimeException If persistence fails
     */
    public function execute(int $userId, UpdateUserRequest $request, int $authenticatedUserId): User
    {
        $existingUser = $this->userRepository->findById($userId);
        if (!$existingUser) {
            throw new RuntimeException("User with ID {$userId} not found");
        }

        $this->validateBusinessRules($existingUser, $request, $authenticatedUserId);

        // Wrap all operations in a single transaction
        return DB::connection('vtiger')->transaction(function () use (
            $userId,
            $request,
            $existingUser,
            $authenticatedUserId
        ): User {
            $updatable = $request->toUpdatableArray();

            // Update password (if provided) - with error handling
            if (!empty($updatable['password'])) {
                $passwordChanged = $this->userRepository->changePassword(
                    $userId, 
                    $updatable['password'], 
                    $authenticatedUserId
                );
                
                if (!$passwordChanged) {
                    throw new RuntimeException("Failed to update password for user {$userId}");
                }
                unset($updatable['password']);
            }

            // Update other user fields (if any)
            if (!empty($updatable)) {
                $updated = $this->userRepository->update($userId, $updatable, $authenticatedUserId);
                if (!$updated) {
                    throw new RuntimeException("Failed to update user fields for user {$userId}");
                }
            }

            // Update role (if provided) - delegated to specialized use case
            if ($request->role_id !== null) {
                try {
                    $this->assignUserRoleUseCase->execute(
                        AssignRoleRequest::fromArray([
                            'user_id' => $userId,
                            'role_id' => $request->role_id,
                        ])
                    );
                    UserRoleDataService::clearCache($userId);
                } catch (\Exception $e) {
                    throw new RuntimeException(
                        "Failed to assign role {$request->role_id} to user {$userId}: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            // Update crmentity label for global search (if name or email changed)
            if (!empty($updatable['user_name']) || !empty($updatable['email1'])) {
                $label = $updatable['user_name'] ?? $existingUser->getUserName();
                $label = substr($label, 0, 100);
                
                try {
                    $this->updateEntityUseCase->execute($userId, [
                        'label' => $label,
                    ], $authenticatedUserId);
                } catch (\Exception $e) {
                    // Log but don't fail the whole update if crmentity fails (non-critical)
                    Log::warning('Failed to update crmentity label', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $updatedUser = $this->userRepository->findById($userId);
            if (!$updatedUser) {
                throw new RuntimeException("Failed to retrieve updated user {$userId}");
            }

            return $updatedUser;
        });
    }

    private function validateBusinessRules(User $existing, UpdateUserRequest $request, int $authId): void
    {
        // Do not allow deactivating system administrators
        if ($request->status === 'Inactive' && $existing->isAdmin()) {
            throw new InvalidArgumentException('Cannot deactivate a system administrator');
        }

        // Do not allow assigning admin privileges to non-admins
        if ($request->is_admin === true && !$this->isAllowedToAssignAdmin($authId, $existing->getId())) {
            throw new InvalidArgumentException('Not authorized to grant admin privileges');
        }

        // Do not allow changing email to an existing one
        if (
            $request->email !== null
            && $request->email !== $existing->getEmail()
            && !$this->isGenericEmail($request->email)
        ) {
            $available = $this->userRepository->isEmailAvailable($request->email, $existing->getId());
            if (!$available) {
                throw new InvalidArgumentException('Email already in use by another active user');
            }
        }
    }

    /**
     * Check if email is in the generic/placeholder list.
     */
    private function isGenericEmail(?string $email): bool
    {
        if (!$email) return false;
        return in_array(strtolower(trim($email)), self::GENERIC_EMAILS, true);
    }

    /**
     * Check if authenticated user can assign admin privileges.
     */
    private function isAllowedToAssignAdmin(int $authId, int $targetUserId): bool
    {
        // An admin can assign admin to others, or a user can self-assign (if already admin)
        $authUser = $this->userRepository->findById($authId);
        return $authUser?->isAdmin() ?? false;
    }
}