<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VtigerUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = VtigerUser::select(
            'id',
            'user_name',
            'first_name',
            'last_name',
            'email1',
            'user_password',
            'crypt_type',
            'status'
        )->where('user_name', $request->user_name)->first();

        if (!$user || $user->status !== 'Active') {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $inputPassword = $request->password;
        $cryptType = strtoupper($user->crypt_type ?? '');

        // ✅ Caso 1: PHASH → usar password_verify (bcrypt)
        if ($cryptType === 'PHASH') {
            if (password_verify($inputPassword, $user->user_password)) {
                Session::put('vtiger_user_id', $user->id);
                Session::regenerate();
                return $this->successResponse($user);
            }
        }
        // ✅ Caso 2: MD5 → md5(password . salt)
        elseif ($cryptType === 'MD5' || empty($cryptType)) {
            $salt = $user->salt ?? '';
            $computed = md5($inputPassword . $salt);
            if (hash_equals($user->user_password, $computed)) {
                Session::put('vtiger_user_id', $user->id);
                Session::regenerate();
                return $this->successResponse($user);
            }
        }
        // ✅ Caso 3: CRYPT → crypt()
        elseif ($cryptType === 'CRYPT') {
            $computed = crypt($inputPassword, $user->salt ?? '');
            if (hash_equals($user->user_password, $computed)) {
                Session::put('vtiger_user_id', $user->id);
                Session::regenerate();
                return $this->successResponse($user);
            }
        }

        return response()->json(['error' => 'Credenciales inválidas'], 401);
    }

    public function logout(Request $request)
    {
        Session::forget('vtiger_user_id');
        Session::invalidate();
        Session::regenerateToken();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        $userId = Session::get('vtiger_user_id');

        if (!$userId) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $user = VtigerUser::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'user_name' => $user->user_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email1,
        ]);
    }

    private function successResponse($user)
    {
        return response()->json([
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user->id,
                'user_name' => $user->user_name,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email1 ?? '',
            ]
        ]);
    }
}
