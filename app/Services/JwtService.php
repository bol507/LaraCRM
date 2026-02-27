<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Illuminate\Support\Facades\Log;

/**
 * JWT Token Service for authentication.
 * 
 * Handles generation, verification, and validation of JWT tokens.
 * This service is intentionally decoupled from Eloquent models
 * to maintain clean architecture separation.
 * 
 * @package App\Services
 * @see \App\Http\Middleware\JwtMiddleware
 * @see \App\Http\Controllers\Auth\LoginController
 */
class JwtService
{
    /**
     * Generate JWT token for authenticated user.
     * 
     * @param int $userId The unique user identifier (NOT the user object)
     * @return string Encoded JWT token
     * @throws \Exception If token generation fails
     */
    public function generateToken(int $userId): string  
    {
        $payload = [
            'iss' => config('app.url'),      // Issuer
            'sub' => $userId,                 // Subject (user ID)
            'iat' => time(),                  // Issued at
            'exp' => time() + (60 * 60),      // Expiration (1 hour)
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    /**
     * Validate and decode JWT token.
     * 
     * @param string $token JWT token to validate
     * @return object|null Decoded token payload with user ID (sub), or null if invalid
     * 
     * @throws ExpiredException If token has expired
     * @throws SignatureInvalidException If token signature is invalid
     * @throws BeforeValidException If token is not yet valid
     */
    public function validateToken(string $token): ?object 
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            
            
            return $decoded;
            
        } catch (ExpiredException $e) {
            Log::warning('JWT token expired', ['error' => $e->getMessage()]);
            return null;
            
        } catch (SignatureInvalidException $e) {
            Log::warning('JWT token signature invalid', ['error' => $e->getMessage()]);
            return null;
            
        } catch (BeforeValidException $e) {
            Log::warning('JWT token not yet valid', ['error' => $e->getMessage()]);
            return null;
            
        } catch (\Exception $e) {
            Log::error('JWT token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify token and return user ID if valid.
     * 
     * Convenience method for getting just the user ID from a token.
     * 
     * @param string $token JWT token to verify
     * @return int|null User ID if token is valid, null otherwise
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $decoded = $this->validateToken($token);
        return $decoded->sub ?? null;
    }

    /**
     * Refresh JWT token with new expiration time.
     * 
     * @param string $token Current JWT token
     * @return string|null New JWT token with extended expiration, or null if current token is invalid
     */
    public function refreshToken(string $token): ?string
    {
        $decoded = $this->validateToken($token);
        
        if (!$decoded || !isset($decoded->sub)) {
            return null;
        }
        
        // Generate new token with same user ID
        return $this->generateToken($decoded->sub);
    }

    /**
     * Check if token is about to expire.
     * 
     * @param string $token JWT token to check
     * @param int $thresholdSeconds Threshold in seconds to consider "about to expire" (default: 5 minutes)
     * @return bool True if token is expiring soon or already expired
     */
    public function isTokenExpiringSoon(string $token, int $thresholdSeconds = 300): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            $remainingTime = $decoded->exp - time();
            return $remainingTime <= $thresholdSeconds;
        } catch (\Exception $e) {
            return true; // Consider expired if we can't decode
        }
    }

    /**
     * Get JWT secret key from environment.
     * 
     * @return string Secret key for JWT signing/verification
     */
    private function getSecret(): string
    {
        return env('JWT_SECRET', config('app.key', 'your-default-secret-key-change-in-production'));
    }
}