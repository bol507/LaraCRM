<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\UpdateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\Repositories\UserRoleAssignmentRepositoryInterface;
use App\Domain\Entities\User;
use Illuminate\Support\Facades\DB;
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
        private readonly UserRoleAssignmentRepositoryInterface $roleAssignmentRepository,
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


        return DB::connection('vtiger')->transaction(function () use (
            $userId, 
            $request, 
            $existingUser, 
            $authenticatedUserId
        ): User {

            $now = now()->format('Y-m-d H:i:s');
            $updatable = $request->toUpdatableArray();

            // update password
            if (!empty($updatable['password'])) {
                $hashedPassword = password_hash($updatable['password'], PASSWORD_DEFAULT);

                DB::connection('vtiger')
                    ->table('vtiger_users')
                    ->where('id', $userId)
                    ->update([
                        'user_password' => $hashedPassword,
                        'confirm_password' => $hashedPassword,
                        'crypt_type' => 'PHASH',
                        'date_modified' => $now,
                        'modified_user_id' => $authenticatedUserId,
                    ]);

                unset($updatable['password']);
            }

            // update other fields  
            if (!empty($updatable)) {
                DB::connection('vtiger')
                    ->table('vtiger_users')
                    ->where('id', $userId)
                    ->update([
                        ...$updatable,
                        'date_modified' => $now,
                        'modified_user_id' => $authenticatedUserId,
                    ]);
            }
            
            // update role
            if ($request->role_id !== null) {
                DB::connection('vtiger')->table('vtiger_user2role')->updateOrInsert(
                    ['userid' => $userId],
                    ['roleid' => $request->role_id]
                );
            }

            // update user name and email
            if (!empty($updatable['user_name']) || !empty($updatable['email1'])) {
                $label = $updatable['user_name'] ?? $existingUser->getUserName();
                DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $userId)
                    ->update([
                        'label' => substr($label, 0, 100),
                        'modifiedtime' => $now,
                    ]);
            }

            

            return $this->userRepository->findById($userId)
                ?? throw new RuntimeException("Failed to retrieve updated user");
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
        // Un admin puede asignar admin a otros, o un usuario puede auto-asignarse (si ya es admin)
        $authUser = $this->userRepository->findById($authId);
        return $authUser?->isAdmin() ?? false;
    }

}
