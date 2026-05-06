<?php
// app/Application/UseCases/Auth/LoginUseCase.php

namespace App\Application\UseCases\Auth;

use App\Application\DTOs\Auth\LoginRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Infrastructure\Services\PasswordVerifier;
use App\Services\JwtService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly JwtService $jwtService,
        private readonly PasswordVerifier $passwordVerifier,
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
        $user = $this->userRepository->findForAuthentication($dto->user_name);
        if (!$user|| (string) $user['status'] !== 'Active') {
            throw new InvalidArgumentException('Invalid credentials');
        }

        $success = $this->passwordVerifier->verify($dto->password, $user);

        if (!$success) {
            throw new InvalidArgumentException('Invalid credentials');
        }



        $dbUser = $this->userRepository->findById((int) $user['id']);
        if (!$dbUser) {
            throw new RuntimeException('User data corrupted');
        }
        $userData = [
            'role_id' => $dbUser->getRoleId(),
            'rolename' => $dbUser->getRoleName(),
            'is_admin' => $dbUser->isAdmin(),
        ];
        // 5. Generate JWT token
        $token = $this->jwtService->generateToken($dbUser->getId(), $userData);

        return [
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 3600),
            'user' => $dbUser->toArray(),
        ];
    }




}
