<?php


namespace App\Http\Middleware;

use App\Domain\Entities\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    private const AUTH_USER_ATTRIBUTE = 'auth_user';

    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user (set by JwtMiddleware) has Admin role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {

        $user = $request->attributes->get(self::AUTH_USER_ATTRIBUTE);


        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized: Authentication required'], 401);
        }


        if (!$user->isAdmin()) {
            return response()->json([
                'error' => 'Forbidden: Admin access required',
                'message' => 'Your role "' . $user->getRole() . '" does not have permission to access this resource'
            ], 403);
        }

        return $next($request);
    }
}
