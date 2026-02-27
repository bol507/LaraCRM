<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\DTOs\User\CreateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the CreateUserUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer with correct parameters
 * - Handling of successful user creation
 * - Return type consistency (int - created user ID)
 * - Error propagation from repository
 * - Request object usage and immutability
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\CreateUserUseCase
 */
class CreateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private CreateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new CreateUserUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HELPER METHOD
    // ========================================================================

    /**
     * Helper to create valid CreateUserRequest with snake_case parameters
     */
    private function createValidCreateRequest(array $overrides = []): CreateUserRequest
    {
        $defaults = [
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            'password' => 'SecureP@ss123',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new CreateUserRequest(
            user_name: $data['user_name'],
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            email: $data['email'],
            role: $data['role'],
            password: $data['password'],
            phone_crm: $data['phone_crm'],
            department: $data['department'],
            reports_to_id: $data['reports_to_id'],
        );
    }

    // ========================================================================
    // HAPPY PATH: USER CREATION SUCCESS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_created_user_id_when_creation_succeeds(): void
    {
        // Arrange: Create valid request using snake_case parameter names
        $request = $this->createValidCreateRequest([
            'user_name' => 'newuser',
            'email' => 'new.user@example.com',
            'phone_crm' => '+507 6123-4567',
            'department' => 'Engineering',
            'reports_to_id' => 10,
        ]);
        $createdByUserId = 5;
        $expectedUserId = 42;

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->with($request, $createdByUserId)
            ->willReturn($expectedUserId);

        // Act
        $result = $this->useCase->execute($request, $createdByUserId);

        // Assert
        $this->assertIsInt($result);
        $this->assertSame($expectedUserId, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_request_object_without_modification(): void
    {
        // Arrange: Create request and capture original values
        $originalUserName = 'testuser';
        $originalEmail = 'test@example.com';
        $originalPassword = 'OriginalP@ss123';
        
        $request = $this->createValidCreateRequest([
            'user_name' => $originalUserName,
            'email' => $originalEmail,
            'password' => $originalPassword,
        ]);

        // ✅ Verify request properties are accessible (public readonly)
        $this->assertSame($originalUserName, $request->user_name);
        $this->assertSame($originalEmail, $request->email);
        $this->assertSame($originalPassword, $request->password);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->with($request, 1)
            ->willReturn(100);

        // Act
        $this->useCase->execute($request, 1);

        // Assert: Request should remain unchanged after use case execution
        $this->assertSame($originalUserName, $request->user_name);
        $this->assertSame($originalEmail, $request->email);
        $this->assertSame($originalPassword, $request->password);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_created_by_user_id_for_audit_trail(): void
    {
        // Arrange: Different users creating accounts (audit scenario)
        $testCases = [
            ['createdBy' => 1, 'description' => 'Admin creating user'],
            ['createdBy' => 5, 'description' => 'Manager creating user'],
            ['createdBy' => 42, 'description' => 'System process creating user'],
        ];

        foreach ($testCases as $case) {
            $request = $this->createValidCreateRequest([
                'user_name' => 'audit_test',
                'email' => 'audit@test.com',
            ]);

            $this->repositoryMock
                ->expects($this->once())
                ->method('create')
                ->with($request, $case['createdBy'])
                ->willReturn(999);

            // Act
            $result = $this->useCase->execute($request, $case['createdBy']);

            // Assert
            $this->assertSame(999, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new CreateUserUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // PARAMETER VARIATIONS & EDGE CASES
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_request_with_optional_fields_null(): void
    {
        // Arrange: Request with only required fields (optional fields as null)
        $request = $this->createValidCreateRequest([
            'user_name' => 'minimal_user',
            'email' => 'minimal@example.com',
            'role' => 'Cliente',
            'password' => 'MinP@ss',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->with($request, 1)
            ->willReturn(50);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertSame(50, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_request_with_all_roles(): void
    {
        $validRoles = ['Admin', 'Usuario', 'Cliente'];

        foreach ($validRoles as $role) {
            $request = $this->createValidCreateRequest([
                'user_name' => "user_{$role}",
                'email' => "test_{$role}@example.com",
                'role' => $role,
            ]);

            $this->repositoryMock
                ->expects($this->once())
                ->method('create')
                ->with($request, 1)
                ->willReturn(100 + array_search($role, $validRoles));

            // Act
            $result = $this->useCase->execute($request, 1);

            // Assert
            $this->assertIsInt($result);
            $this->assertGreaterThan(0, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new CreateUserUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_passwords_with_special_characters(): void
    {
        // Arrange: Passwords that should be passed through unchanged
        $complexPasswords = [
            'P@ssw0rd!#$%',
            'My-S3cur3_P@ss',
            'UnicodeP@ss🔐123',
            'LongP@sswordWith123!@#CharactersThatShouldNotBeTruncated',
        ];

        foreach ($complexPasswords as $password) {
            $request = $this->createValidCreateRequest([
                'user_name' => 'complex_pass_user',
                'email' => 'complex@example.com',
                'password' => $password,
            ]);

            $this->repositoryMock
                ->expects($this->once())
                ->method('create')
                ->with($request, 1)
                ->willReturnCallback(function ($req, $userId) use ($password) {
                    // ✅ Verify password is passed exactly as provided
                    $this->assertSame($password, $req->password);
                    return 200;
                });

            // Act
            $result = $this->useCase->execute($request, 1);

            // Assert
            $this->assertSame(200, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new CreateUserUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_username_already_exists_exception(): void
    {
        // Arrange: Repository throws exception for duplicate username
        $request = $this->createValidCreateRequest([
            'user_name' => 'existing_user',
            'email' => 'existing@example.com',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \InvalidArgumentException('Username already exists'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username already exists');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_email_already_exists_exception(): void
    {
        // Arrange: Repository throws exception for duplicate email
        $request = $this->createValidCreateRequest([
            'user_name' => 'new_user',
            'email' => 'duplicate@example.com',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \InvalidArgumentException('Email already registered'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already registered');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_connection_exceptions(): void
    {
        // Arrange: Database error during user creation
        $request = $this->createValidCreateRequest([
            'user_name' => 'db_error_user',
            'email' => 'dberror@example.com',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_without_swallowing(): void
    {
        // Arrange: Repository validates and throws on invalid data
        $request = $this->createValidCreateRequest([
            'user_name' => 'invalid@user',
            'email' => 'invalid@example.com',
            'role' => 'InvalidRole',
            'password' => 'weak',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new \InvalidArgumentException('Invalid role provided'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role provided');

        $this->useCase->execute($request, 1);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_always_returns_positive_integer_user_id(): void
    {
        $request = $this->createValidCreateRequest([
            'user_name' => 'type_test',
            'email' => 'type@test.com',
        ]);

        // Test various valid user IDs that repositories might return
        $validIds = [1, 42, 999, 10000, 2147483647]; // Including edge cases

        foreach ($validIds as $expectedId) {
            $this->repositoryMock
                ->method('create')
                ->willReturn($expectedId);

            $result = $this->useCase->execute($request, 1);

            $this->assertIsInt($result);
            $this->assertGreaterThan(0, $result);
            $this->assertSame($expectedId, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new CreateUserUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_never_returns_null_or_false_only_int_or_exception(): void
    {
        $request = $this->createValidCreateRequest([
            'user_name' => 'return_type_test',
            'email' => 'return@test.com',
        ]);

        // Success case: returns int
        $this->repositoryMock
            ->method('create')
            ->willReturn(123);
        
        $result = $this->useCase->execute($request, 1);
        $this->assertIsInt($result);
        $this->assertNotSame(null, $result);
        $this->assertNotSame(false, $result);

        // Error case: throws exception (doesn't return null/false)
        $this->repositoryMock
            ->method('create')
            ->willThrowException(new \Exception('Error'));
        
        $this->expectException(\Exception::class);
        $this->useCase->execute($request, 1);
        // If we reach here without exception, test would fail (expected behavior)
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_preserves_request_immutability_throughout_execution(): void
    {
        // Arrange: Create request with sensitive data
        $sensitivePassword = 'SuperSecretP@ss123!';
        $request = $this->createValidCreateRequest([
            'user_name' => 'immutability_test',
            'email' => 'immutability@test.com',
            'role' => 'Admin',
            'password' => $sensitivePassword,
            'phone_crm' => '+507 1234-5678',
            'department' => 'Security',
            'reports_to_id' => 1,
        ]);

        // Capture original state
        $originalState = [
            'user_name' => $request->user_name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->with($request, 1)
            ->willReturn(500);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert: Request should be completely unchanged
        $this->assertSame($originalState['user_name'], $request->user_name);
        $this->assertSame($originalState['email'], $request->email);
        $this->assertSame($originalState['password'], $request->password);
        $this->assertSame($originalState['role'], $request->role);
        $this->assertSame(500, $result);
    }
}