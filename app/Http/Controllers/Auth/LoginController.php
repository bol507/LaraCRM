<?php

namespace App\Http\Controllers\Auth;

use App\Application\Repositories\RoleRepositoryInterface;
use App\Application\UseCases\Role\GetUserHierarchicalRoleUseCase;
use App\Http\Controllers\Controller;
use App\Models\VtigerUser;
use App\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JwtService;
use Illuminate\Support\Facades\Log;

/**
 * Authentication controller for user login/logout operations.
 *
 * Handles user authentication using Vtiger CRM user database.
 * Uses Eloquent models directly for auth (acceptable for presentation layer).
 *
 * @package App\Http\Controllers\Auth
 * @see \App\Http\Middleware\JwtMiddleware
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly GetUserHierarchicalRoleUseCase $getHierarchicalRoleUseCase
    ) {}
    /**
     * Authenticate user and return JWT token.
     *
     * POST /api/auth/login
     *
     * @param Request $request HTTP request with credentials
     * @param JwtService $jwtService JWT token service
     * @return JsonResponse Authentication result with token
     */
    public function login(Request $request, JwtService $jwtService): JsonResponse
    {
        $request->validate([
            'user_name' => 'required|string|max:50',
            'password' => 'required|string|min:1',
        ]);


        $vtigerUser = VtigerUser::where('user_name', $request->user_name)
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->first();


        if (!$vtigerUser) {
            Log::warning('Login attempt for non-existent user', [
                'user_name' => $request->user_name
            ]);
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }


        if (!$this->verifyPassword($request->password, $vtigerUser)) {
            Log::warning('Login attempt with invalid password', [
                'user_name' => $request->user_name,
                'crypt_type' => $vtigerUser->crypt_type ?? 'unknown'
            ]);
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }


        $token = $jwtService->generateToken($vtigerUser->id);


        


        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600, // 1 hour (should match JWT config)
            'user' => [
                'id' => $vtigerUser->id,
                'user_name' => $vtigerUser->user_name,
                'first_name' => $vtigerUser->first_name ?? '',
                'last_name' => $vtigerUser->last_name ?? '',
                'email' => $vtigerUser->email1 ?? '',  //
                'role' => $vtigerUser->is_admin === '1' ? 'Admin' : 'Usuario',
                'status' => $vtigerUser->status,
            ]
        ], 200);
    }

    /**
     * Logout user (invalidate token on client side).
     *
     * POST /api/auth/logout
     *
     * @param Request $request HTTP request
     * @return JsonResponse Logout confirmation
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();


        

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }

    /**
     * Get current authenticated user profile.
     *
     * GET /api/auth/me
     *
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse Current user profile data
     */
    public function me(Request $request): JsonResponse
    {

        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->attributes->get('auth_user');

        if (!$authenticatedUser) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        $hierarchicalRole = $this->getHierarchicalRoleUseCase->execute($authenticatedUser->getId());

        return response()->json([
            'data' => [
                'id' => $authenticatedUser->getId(),
                'user_name' => $authenticatedUser->getUserName(),
                'first_name' => $authenticatedUser->getFirstName(),
                'last_name' => $authenticatedUser->getLastName(),
                'email' => $authenticatedUser->getEmail(),

                'is_admin' => $authenticatedUser->isAdmin(),
                'role_id' => $hierarchicalRole['role_id'] ?? null,
                'rolename' => $hierarchicalRole['rolename'] ?? null,

                'status' => $authenticatedUser->getStatus(),
                'department' => $authenticatedUser->getDepartment(),
                'phone' => $authenticatedUser->getPhoneCrm(),
                'is_admin' => $authenticatedUser->isAdmin(),
                'is_active' => $authenticatedUser->isActive(),
            ]
        ], 200);
    }

    /**
     * Verify password with support for multiple Vtiger crypt types.
     *
     * Vtiger supports: PHASH (PHP password_hash), MD5, CRYPT
     *
     * @param string $inputPassword Plain text password from login form
     * @param VtigerUser $user Vtiger user model with stored password
     * @return bool True if password matches, false otherwise
     */
    private function verifyPassword(string $inputPassword, VtigerUser $user): bool
    {
        $cryptType = strtoupper($user->crypt_type ?? '');

        try {
            if ($cryptType === 'PHASH') {
                //  Modern PHP password_hash() format (recommended)
                return password_verify($inputPassword, $user->user_password);
            } elseif ($cryptType === 'MD5' || empty($cryptType)) {
                //  Legacy MD5 with salt
                $salt = $user->salt ?? '';
                return hash_equals($user->user_password, md5($inputPassword . $salt));
            } elseif ($cryptType === 'CRYPT') {
                //  PHP crypt() format
                return hash_equals($user->user_password, crypt($inputPassword, $user->salt ?? ''));
            } else {
                //  Unknown crypt type - log for investigation
                Log::warning('Unknown password crypt type', [
                    'user_id' => $user->id,
                    'crypt_type' => $cryptType
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Password verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
