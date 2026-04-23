<?php
// app/Application/UseCases/Auth/LoginUseCase.php

namespace App\Application\UseCases\Auth;

use App\Application\DTOs\Auth\LoginRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Services\JwtService;
use InvalidArgumentException;
use RuntimeException;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
    ) {}

    /**
     * Authenticate user and return JWT token.
     * 
     * @param LoginRequest $dto
     * @return array{token: string, user: array, user_id: int, user_name: string, expires_in?: int}
     * 
     * @throws InvalidArgumentException If credentials are invalid
     * @throws RuntimeException If authentication fails
     */
    public function execute(LoginRequest $dto): array
    {
        // 1. Find user by username (case-sensitive in Vtiger)
        $user = $this->userRepository->findByUserName($dto->user_name);
        
        if (!$user) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // 2. Validate that the user is active
        if (!$user->isActive()) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // 3. Verify password (supports PHASH and legacy MD5 for migration)
        if (!$this->verifyPassword($dto->password, $user)) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // 4. (Optional) Re-hash password if in legacy format
        if ($this->needsRehash($user)) {
            $this->userRepository->upgradePasswordHash($user->getId(), $dto->password);
        }

        // 5. Generate JWT token
        $token = $this->jwtService->generateToken($user->getId());
        
        return [
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 3600),
            'user' => $user->toArray(),
        ];
    }

    /**
     * Verify password with support for multiple hash formats.
     */
    private function verifyPassword(string $password, $user): bool
    {
        $storedHash = $user->getUserPassword(); // Adjust according to your entity
        
        // PHASH (modern)
        if (password_verify($password, $storedHash)) {
            return true;
        }
        
        // MD5 (legacy Vtiger) - for gradual migration
        if (md5($password) === $storedHash) {
            return true;
        }
        
        // Crypt (very legacy)
        if (crypt($password, $storedHash) === $storedHash) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if password needs rehash to modern algorithm.
     */
    private function needsRehash($user): bool
    {
        $storedHash = $user->getUserPassword();
        return !password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }
}