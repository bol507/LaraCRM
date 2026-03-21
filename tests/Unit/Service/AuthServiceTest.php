<?php

namespace Tests\Unit\Services;

use App\Services\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private JwtService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new JwtService();
    }

    /**
     * Test que el token se genera correctamente
     */
    public function test_generate_token_returns_valid_jwt(): void
    {
        $userId = 123;
        $token = $this->authService->generateToken($userId);

        // Verificar que es string no vacío
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    /**
     * Test que el token contiene los claims correctos
     */
    public function test_generate_token_contains_correct_claims(): void
    {
        $userId = 123;
        $token = $this->authService->generateToken($userId);

        // Decodificar token
        $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));

        // Verificar claims
        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals('cw-crm-api', $decoded->iss);
        $this->assertEquals('cw-crm-users', $decoded->aud);
        $this->assertGreaterThan(time(), $decoded->exp);
        $this->assertNotEmpty($decoded->jti);
    }

    /**
     * Test que el token expira después de 7 días
     */
    public function test_generate_token_expires_in_7_days(): void
    {
        $userId = 123;
        $token = $this->authService->generateToken($userId);

        $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));

        // La expiración debe ser aproximadamente 7 días desde ahora
        $expectedExpiration = time() + (10080 * 60); // 7 días en segundos
        $this->assertEqualsWithDelta($expectedExpiration, $decoded->exp, 5);
    }

    /**
     * Test que lanza excepción con userId inválido
     */
    public function test_generate_token_throws_exception_for_invalid_user_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user ID for token generation');

        $this->authService->generateToken(0);
    }

    /**
     * Test que el token se puede validar correctamente
     */
    public function test_validate_token_returns_user_data(): void
    {
        $userId = 123;
        $token = $this->authService->generateToken($userId);

        $userData = $this->authService->validateToken($token);

        $this->assertIsArray($userData);
        $this->assertEquals($userId, $userData['user_id']);
    }

    /**
     * Test que token expirado retorna null
     */
    public function test_validate_token_returns_null_for_expired_token(): void
    {
        // Crear token manualmente con expiración en el pasado
        $payload = [
            'iss' => 'cw-crm-api',
            'sub' => 123,
            'aud' => 'cw-crm-users',
            'iat' => time() - (8 * 24 * 60 * 60), // 8 días atrás
            'exp' => time() - (7 * 24 * 60 * 60),  // 7 días atrás (expirado)
            'jti' => bin2hex(random_bytes(16)),
        ];

        $token = JWT::encode($payload, config('jwt.secret'), 'HS256');

        $userData = $this->authService->validateToken($token);

        $this->assertNull($userData);
    }
}