<?php

use App\Application\DTOs\Auth\LoginRequest;
use App\Application\DTOs\Auth\RequestPasswordResetRequest;
use App\Application\DTOs\Auth\ResetPasswordRequest;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\RequestPasswordResetUseCase;
use App\Application\UseCases\Auth\ResetPasswordUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly RequestPasswordResetUseCase $requestResetUseCase,
        private readonly ResetPasswordUseCase $resetPasswordUseCase,
    ) {}

    /**
     * POST /api/auth/login
     * Authenticate user with rate limiting protection.
     */
    public function login(Request $request): JsonResponse
    {
        // === RATE LIMITING: 5 intentos por minuto por usuario+IP ===
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
            // Validación HTTP
            $validated = $request->validate([
                'user_name' => 'required|string',
                'password' => 'required|string',
            ]);

            // DTO con validación de dominio
            $dto = LoginRequest::fromArray($validated);

            // Ejecutar caso de uso
            $result = $this->loginUseCase->execute($dto);

            // ✅ Login exitoso: limpiar contador de rate limit
            RateLimiter::clear($throttleKey);

            // ✅ Log de éxito para auditoría (sin password)
            Log::info('User logged in successfully', [
                'user_id' => $result['user_id'],
                'user_name' => $result['user_name'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $result['access_token'],
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'user' => $result['user'],
            ], 200);
        } catch (InvalidArgumentException $e) {
            // ✅ Credenciales inválidas: incrementar contador
            RateLimiter::hit($throttleKey, 60);

            Log::warning('Login failed: invalid credentials', [
                'user_name' => $request->input('user_name'),
                'ip' => $request->ip(),
                'reason' => $e->getMessage(),
            ]);

            // ⚠️ Mensaje genérico para no revelar si el usuario existe
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'The provided username or password is incorrect',
            ], 401);
        } catch (RuntimeException $e) {
            // ✅ Error de sistema: no incrementar rate limit (no es culpa del usuario)
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
}
