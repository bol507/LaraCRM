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
     * @param string $plain Texto en texto plano de la contraseña
     * @param array $user Array con los datos de la cuenta
     * @return bool True si la contraseña coincide, false en caso contrario
     */
    public function verify(string $plain, array $user): bool
    {
        Log::debug('Verifying password', [
            'user_id' => $user['id'],
            'plain' => $plain,
            'stored_hash' => $user['user_password'],
            'crypt_type' => $user['crypt_type'] ?? null,
        ]);

        $cryptType = strtoupper($user['crypt_type'] ?? '');
        if ($cryptType === 'PHASH') {
            // Moderno PHP password_hash() formato (recomendado)
            $result = password_verify($plain, $user['user_password']);
            Log::debug(
                'Password verification result for PHASH',
                [
                    'user_id' => $user['id'],
                    'result' => $result,
                ]
            );
            return $result;
        }
        if ($cryptType === 'MD5' || empty($cryptType)) {
            $salt = $user['salt'] ?? '';
            return hash_equals($user['user_password'], md5($plain . $salt));
        }
        if ($cryptType === 'CRYPT') {
            // PHP crypt() formato
            return hash_equals($user['user_password'], crypt($plain, $user['salt'] ?? ''));
        }

        Log::warning('Unknown password crypt type', [
                    'user_id' => $user['id'],
                    'crypt_type' => $cryptType
                ]);

        return false;
    }
}
