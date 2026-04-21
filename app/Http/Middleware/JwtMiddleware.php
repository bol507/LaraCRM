<?php

namespace App\Http\Middleware;

use App\Domain\Entities\User;
use App\Infrastructure\Mappers\UserMapper;
use App\Models\VtigerUser;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


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

        $jwtService = app(JwtService::class);

       
        $decoded = $jwtService->validateToken($token);

        if (!$decoded || !isset($decoded->sub)) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

       
        $vtigerUser = VtigerUser::where('id', $decoded->sub)
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->first();

        if (!$vtigerUser) {
            return response()->json(['error' => 'Usuario no encontrado o inactivo'], 401);
        }

        $domainUser = UserMapper::toDomain($vtigerUser);


        
        $request->attributes->set('auth_user', $domainUser);

        return $next($request);
    }
}
