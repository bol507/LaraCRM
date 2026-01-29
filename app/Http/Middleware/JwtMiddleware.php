<?php

namespace App\Http\Middleware;

use App\Models\VtigerUser;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            
            // Verificar que el usuario exista y esté activo
            $user = VtigerUser::where('id', $decoded->sub)
                ->where('status', 'Active')
                ->first();

            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado o inactivo'], 401);
            }

            // Opcional: Adjuntar el usuario a la petición
            $request->attributes->set('auth_user', $user);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        return $next($request);
    }
}
