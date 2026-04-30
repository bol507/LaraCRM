<?php

namespace App\Services;



/**
 * Service to get the current authenticated user from JWT token.
 * 
 * Integrates with your custom JwtService using firebase/php-jwt.
 * Works in any context: controllers, services, observers, traits, etc.
 * 
 * @package App\Services
 * @see \App\Services\JwtService
 */
class CurrentUserService
{
    /**
     * Cached user ID for the current request.
     * Prevents multiple token validations per request.
     */
    protected static ?int $cachedUserId = null;

    /**
     * Cached flag to avoid re-validation.
     */
    protected static ?bool $cachedValid = null;

    /**
     * Get the current user ID from JWT token.
     * 
     * @return int|null User ID if token is valid, null otherwise
     */
    public static function id(): ?int
    {
        // Return cached value if already resolved for this request
        if (self::$cachedValid !== null) {
            return self::$cachedValid ? self::$cachedUserId : null;
        }

        try {
            // Get token from request header
            $token = self::getTokenFromRequest();
            
            if (!$token) {
                self::$cachedValid = false;
                return null;
            }

            // Validate token using your JwtService
            $jwtService = app(JwtService::class);
            $userId = $jwtService->getUserIdFromToken($token);

            // Cache the result
            self::$cachedUserId = $userId;
            self::$cachedValid = $userId !== null;

            return $userId;
            
        } catch (\Throwable $e) {
            
            
            self::$cachedValid = false;
            return null;
        }
    }

    /**
     * Get the current user ID or a fallback value.
     * 
     * @param int $fallback Default value if no valid token
     * @return int User ID or fallback
     */
    public static function idOr(int $fallback = 1): int
    {
        return self::id() ?? $fallback;
    }

    /**
     * Check if there's a valid authenticated user.
     * 
     * @return bool True if user is authenticated
     */
    public static function check(): bool
    {
        return self::id() !== null;
    }

    /**
     * Get the full decoded token payload.
     * 
     * @return object|null Decoded payload or null if invalid
     */
    public static function payload(): ?object
    {
        try {
            $token = self::getTokenFromRequest();
            
            if (!$token) {
                return null;
            }

            $jwtService = app(JwtService::class);
            return $jwtService->validateToken($token);
            
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract JWT token from current request.
     * 
     * Supports:
     * - Bearer token in Authorization header
     * - Token in query string (for websockets, etc.)
     * 
     * @return string|null Token string or null if not found
     */
    protected static function getTokenFromRequest(): ?string
    {
        $request = request();
        
        if (!$request) {
            return null;
        }

        // Option 1: Bearer token in Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7); // Remove 'Bearer ' prefix
        }

        // Option 2: Token in query string (for websockets, SSE, etc.)
        $token = $request->query('token') ?? $request->get('token');
        if ($token) {
            return $token;
        }

        // Option 3: Direct bearer token without prefix (less common)
        if ($authHeader && !str_contains($authHeader, ' ')) {
            return $authHeader;
        }

        return null;
    }

    /**
     * Clear the cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$cachedUserId = null;
        self::$cachedValid = null;
    }
}