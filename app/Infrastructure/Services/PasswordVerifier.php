<?php

namespace App\Infrastructure\Services;

use Illuminate\Support\Facades\Log;

class PasswordVerifier
{
    /**
     * Verify password with support for multiple Vtiger crypt types.
     *
     * Vtiger supports: PHASH (PHP password_hash), MD5, CRYPT
     *
     * @param string $plain Plain text password
     * @param array $user Array with account data
     * @return bool True if password matches, false otherwise
     */
    public function verify(string $plain, array $user): bool
    {
        $cryptType = strtoupper($user['crypt_type'] ?? '');
        
        if ($cryptType === 'PHASH') {
            // Modern PHP password_hash() format (recommended)
            $result = password_verify($plain, $user['user_password']);
            
            return $result;
        }
        
        if ($cryptType === 'MD5' || empty($cryptType)) {
            $salt = $user['salt'] ?? '';
            return hash_equals($user['user_password'], md5($plain . $salt));
        }
        
        if ($cryptType === 'CRYPT') {
            // PHP crypt() format
            return hash_equals($user['user_password'], crypt($plain, $user['salt'] ?? ''));
        }

        return false;
    }
}