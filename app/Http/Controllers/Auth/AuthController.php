<?php

namespace App\Http\Controllers\Auth;

use App\Application\DTOs\Auth\LoginRequest;
use App\Application\DTOs\Auth\RequestPasswordResetRequest;
use App\Application\DTOs\Auth\ResetPasswordRequest;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\RequestPasswordResetUseCase;
use App\Application\UseCases\Auth\ResetPasswordUseCase;
use App\Application\UseCases\Role\GetUserRoleUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly RequestPasswordResetUseCase $requestResetUseCase,
        private readonly ResetPasswordUseCase $resetPasswordUseCase,
        private readonly GetUserRoleUseCase $getUserRoleUseCase
    ) {}

    /**
     * POST /api/auth/login
     * Authenticate user with rate limiting protection.
     */
    public function login(Request $request): JsonResponse
    {
        // Rate limiting: 5 attempts per 5 seconds
        $throttleKey = 'login:' . strtolower($request->input('user_name') ?? '') . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Login rate limit exceeded', [
                'user_name' => $request->input('user_name'),
                'ip' => $request->ip(),
                'retry_after' => $seconds,
            ]);

            return response()->json([
                'error' => 'Too many login attempts',
                'retry_after' => $seconds,
                'message' => "Please wait {$seconds} seconds before trying again",
            ], 429);
        }

        try {
            // HTTP validation
            $validated = $request->validate([
                'user_name' => 'required|string',
                'password' => 'required|string',
            ]);

            // DTO with domain validation
            $dto = LoginRequest::fromArray($validated);

            // Execute use case
            $result = $this->loginUseCase->execute($dto);

            RateLimiter::clear($throttleKey);

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $result['access_token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'user' => $result['user'],
            ], 200);
        } catch (InvalidArgumentException $e) {
            RateLimiter::hit($throttleKey, 60);

            Log::warning('Login failed: invalid credentials', [
                'user_name' => $request->input('user_name'),
                'ip' => $request->ip(),
                'reason' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'The provided username or password is incorrect',
            ], 401);
        } catch (RuntimeException $e) {
            Log::error('Login failed: system error', [
                'user_name' => $request->input('user_name'),
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'An internal error occurred. Please try again later',
            ], 500);
        }
    }

    /**
     * POST /api/auth/forgot-password
     * Request password reset token.
     * Always returns success to prevent user enumeration.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
            ]);

            $dto = RequestPasswordResetRequest::fromArray($validated);
            $result = $this->requestResetUseCase->execute($dto);

            return response()->json(['message' => $result['message']], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/auth/reset-password
     * Reset password with valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'token' => 'required|string|min:64',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'password.confirmed' => 'Password confirmation does not match',
            ]);

            $dto = ResetPasswordRequest::fromArray($validated);
            $result = $this->resetPasswordUseCase->execute($dto);

            return response()->json(['message' => $result['message']], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getMessage() === 'User not found' ? 404 : 500);
        }
    }

    /**
     * POST /api/auth/logout
     * Log out the authenticated user.
     */
    public function logout(Request $request): JsonResponse
    {
        $authenticatedUser = $request->attributes->get('auth_user');
        $userId = $authenticatedUser?->getId() ?? 'unknown';

        Log::info('User logged out', [
            'user_id' => $userId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json([
            'message' => 'Session closed successfully',
            'data' => ['user_id' => $userId],
        ], 200);
    }

    /**
     * GET /api/auth/me
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->attributes->get('auth_user');

        if (!$authenticatedUser) {
            return response()->json(['error' => 'Unauthenticated user'], 401);
        }

        // Fetch dynamic hierarchical role (no hardcoding)
        $roleData = $this->getUserRoleUseCase->execute($authenticatedUser->getId());

        return response()->json([
            'data' => [
                // Identity
                'id' => $authenticatedUser->getId(),
                'user_name' => $authenticatedUser->getUserName(),
                'first_name' => $authenticatedUser->getFirstName(),
                'last_name' => $authenticatedUser->getLastName(),
                'full_name' => $authenticatedUser->getFullName(),
                'email' => $authenticatedUser->getEmail(),

                // New role architecture (source of truth)
                'is_admin' => $authenticatedUser->getIsAdmin(),
                'role_id' => $roleData['role_id'] ?? null,
                'rolename' => $roleData['rolename'] ?? null,
                'role_depth' => $roleData['depth'] ?? 0,
                'role_parent' => $roleData['parentrole'] ?? null,
                'sharing_rule' => $roleData['sharing_rule'] ?? 1,

                // Profile and status
                'status' => $authenticatedUser->getStatus(),
                'department' => $authenticatedUser->getDepartment(),
                'phone_crm' => $authenticatedUser->getPhoneCrm(),
                'reports_to_id' => $authenticatedUser->getReportsToId(),
                'is_active' => $authenticatedUser->isActive(),

                // Legacy: only for compatibility with old frontend
                'role' => $authenticatedUser->getRole(),
            ]
        ], 200);
    }
}
