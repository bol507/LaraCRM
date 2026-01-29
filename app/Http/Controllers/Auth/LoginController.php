<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VtigerUser;
use Illuminate\Http\Request;
use App\Services\JwtService;

class LoginController extends Controller
{
    public function login(Request $request, JwtService $jwtService)
    {
        $request->validate([
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = VtigerUser::where('user_name', $request->user_name)
            ->where('status', 'Active')
            ->first();

        if (!$user || !$this->verifyPassword($request->password, $user)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $token = $jwtService->generateToken($user);

        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => [
                'id' => $user->id,
                'user_name' => $user->user_name,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email1 ?? '',
            ]
        ]);
    }

    public function logout(Request $request)
    {
        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function me(Request $request, JwtService $jwtService)
    {
        $user = $request->attributes->get('auth_user');

        return response()->json([
            'id' => $user->id,
            'user_name' => $user->user_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email1,
        ]);
    }

    private function verifyPassword(string $inputPassword, $user): bool
    {
        $cryptType = strtoupper($user->crypt_type ?? '');

        if ($cryptType === 'PHASH') {
            return password_verify($inputPassword, $user->user_password);
        } elseif ($cryptType === 'MD5' || empty($cryptType)) {
            $salt = $user->salt ?? '';
            return hash_equals($user->user_password, md5($inputPassword . $salt));
        } elseif ($cryptType === 'CRYPT') {
            return hash_equals($user->user_password, crypt($inputPassword, $user->salt ?? ''));
        }

        return false;
    }
}
