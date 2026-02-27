<?php

namespace App\Http\Middleware;

use App\Domain\Entities\User;
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

        
        $domainUser = User::fromArray([
            'id' => $vtigerUser->id,
            'user_name' => $vtigerUser->user_name,
            'first_name' => $vtigerUser->first_name,
            'last_name' => $vtigerUser->last_name,
            'email' => $vtigerUser->email1,
            'role' => $vtigerUser->is_admin === '1' ? 'Admin' : 'Usuario',
            'status' => $vtigerUser->status,
            'phone_crm' => $vtigerUser->phone_crm_extension,
            'department' => $vtigerUser->department,
            'reports_to_id' => $vtigerUser->reports_to_id,
            'is_active' => $vtigerUser->status === 'Active',
        ]);

        
        $request->attributes->set('auth_user', $domainUser);

        return $next($request);
    }
}
