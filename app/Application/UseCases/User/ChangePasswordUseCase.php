<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\ChangePasswordRequest;
use App\Application\Repositories\UserRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ChangePasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    )
    {}
    
    public function execute(ChangePasswordRequest $request, int $modifiedByUserId): bool
    {
        $targetUser = $this->userRepository->findById($request->userId);
        if (!$targetUser) {
            throw new RuntimeException("User with ID {$request->userId} not found");
        }

        $authUser = $this->userRepository->findById($modifiedByUserId);
        if (!$authUser || (!$authUser->isAdmin() && $authUser->getId() !== $targetUser->getId())) {
            throw new InvalidArgumentException('Not authorized to change this password');
        }

        if ($request->currentPassword !== null) {
            $this->verifyCurrentPassword($targetUser, $request->currentPassword);
        }
    
        return $this->userRepository->changePassword($request->userId, $request->newPassword, $modifiedByUserId);
    }

    /**
     * Verify the user's current password against stored hash.
     *
     * @throws InvalidArgumentException If password does not match
     */
    private function verifyCurrentPassword(object $user, string $currentPassword): void
    {
        
        $storedHash = $user->user_password ?? '';
        $cryptType = $user->crypt_type ?? 'PHASH';

        $isValid = match ($cryptType) {
            'PHASH' => password_verify($currentPassword, $storedHash),
            'MD5' => md5($currentPassword) === $storedHash,  // Legacy, migrate to PHASH
            default => false,
        };

        if (!$isValid) {
            throw new InvalidArgumentException('Current password is incorrect');
        }
    }
}