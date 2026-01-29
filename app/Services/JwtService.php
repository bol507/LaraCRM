<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\VtigerUser;

class JwtService
{
    public function generateToken(VtigerUser $user): string
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * 60), 
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    public function validateToken(string $token): ?VtigerUser
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            return VtigerUser::find($decoded->sub);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getSecret(): string
    {
        return env('JWT_SECRET', 'your-default-secret-key');
    }
}