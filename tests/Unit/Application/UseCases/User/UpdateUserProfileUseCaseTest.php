<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\UpdateUserProfileUseCase;
use App\Application\DTOs\User\UpdateUserProfileRequest;
use App\Application\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the UpdateUserProfileUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer with correct parameters
 * - Handling of successful and failed profile updates
 * - Return type consistency (bool)
 * - Error propagation from repository
 * - Request object usage and immutability
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\UpdateUserProfileUseCase
 */
class UpdateUserProfileUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private UpdateUserProfileUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new UpdateUserProfileUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HELPER METHOD
    // ========================================================================

    /**
     * Helper to create valid UpdateUserProfileRequest with snake_case parameters
     */
    private function createValidUpdateRequest(array $overrides = []): UpdateUserProfileRequest
    {
        $defaults = [
            'id' => 42,
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => 'Usuario',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new UpdateUserProfileRequest(
            id: $data['id'],
            user_name: $data['user_name'],
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            email: $data['email'],
            role: $data['role'],
            phone_crm: $data['phone_crm'],
            department: $data['department'],
            reports_to_id: $data['reports_to_id'],
        );
    }

    // ========================================================================
    // HAPPY PATH: PROFILE UPDATE SUCCESS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_true_when_profile_update_succeeds(): void
    {
        // Arrange: Create valid request and expected response
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'updated_user',
            'email' => 'updated@example.com',
        ]);
        $modifiedByUserId = 5; // Admin or the user themselves

        // ✅ Expect repository to be called with exact parameters
        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, $modifiedByUserId)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, $modifiedByUserId);

        // Assert
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_request_object_without_modification(): void
    {
        // Arrange: Create request and capture original values
        $originalUserName = 'original_user';
        $originalEmail = 'original@example.com';

        $request = $this->createValidUpdateRequest([
            'id' => 100,
            'user_name' => $originalUserName,
            'email' => $originalEmail,
        ]);

        // ✅ Verify request properties are accessible (public readonly)
        $this->assertSame($originalUserName, $request->user_name);
        $this->assertSame($originalEmail, $request->email);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, 1)
            ->willReturn(true);

        // Act
        $this->useCase->execute($request, 1);

        // Assert: Request should remain unchanged after use case execution
        $this->assertSame($originalUserName, $request->user_name);
        $this->assertSame($originalEmail, $request->email);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_modified_by_user_id_for_audit_trail(): void
    {
        // Arrange: Different users modifying profiles (audit scenario)
        $testCases = [
            ['modifiedBy' => 1, 'description' => 'Admin updating user'],
            ['modifiedBy' => 42, 'description' => 'User updating own profile'],
            ['modifiedBy' => 99, 'description' => 'Manager updating subordinate'],
        ];

        foreach ($testCases as $case) {
            $request = $this->createValidUpdateRequest([
                'id' => 50,
                'user_name' => 'audit_test',
                'email' => 'audit@test.com',
            ]);

            $this->repositoryMock
                ->expects($this->once())
                ->method('updateProfile')
                ->with($request, $case['modifiedBy'])
                ->willReturn(true);

            // Act
            $result = $this->useCase->execute($request, $case['modifiedBy']);

            // Assert
            $this->assertTrue($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new UpdateUserProfileUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // EDGE CASE: PROFILE UPDATE FAILS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_user_not_found(): void
    {
        // Arrange: Repository returns false for non-existent user
        $request = $this->createValidUpdateRequest([
            'id' => 999999, // Non-existent ID
            'user_name' => 'not_found',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, 1)
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_update_rejected_by_repository(): void
    {
        // Arrange: Repository rejects the update (e.g., validation failed)
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'email' => 'conflict@example.com', // Might conflict with existing user
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertFalse($result);
    }

    // ========================================================================
    // PARAMETER VARIATIONS & EDGE CASES
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_request_with_optional_fields_null(): void
    {
        // Arrange: Request with only required fields (optional fields as null)
        $request = $this->createValidUpdateRequest([
            'id' => 1,
            'user_name' => 'minimal_update',
            'email' => 'minimal@example.com',
            'role' => 'Cliente',
            'phone_crm' => null,
            'department' => null,
            'reports_to_id' => null,
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, 1)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_request_with_all_roles(): void
    {
        $validRoles = ['Admin', 'Usuario', 'Cliente'];

        foreach ($validRoles as $role) {
            $request = $this->createValidUpdateRequest([
                'id' => 100,
                'user_name' => "user_{$role}",
                'email' => "test_{$role}@example.com",
                'role' => $role,
            ]);

            $this->repositoryMock
                ->expects($this->once())
                ->method('updateProfile')
                ->with($request, 1)
                ->willReturn(true);

            // Act
            $result = $this->useCase->execute($request, 1);

            // Assert
            $this->assertTrue($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new UpdateUserProfileUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_partial_updates_with_only_some_fields(): void
    {
        // Arrange: Update only email, keep other fields unchanged
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'email' => 'newemail@example.com',
            // Other fields remain as defaults
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, 1)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertTrue($result);
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_email_already_exists_exception(): void
    {
        // Arrange: Repository throws exception for duplicate email
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'email' => 'duplicate@example.com',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willThrowException(new \InvalidArgumentException('Email already registered'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already registered');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_username_already_exists_exception(): void
    {
        // Arrange: Repository throws exception for duplicate username
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'existing_username',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willThrowException(new \InvalidArgumentException('Username already exists'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username already exists');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_permission_denied_exceptions(): void
    {
        // Arrange: Repository throws exception for unauthorized update
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'protected_user',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willThrowException(new \DomainException('Permission denied: cannot modify this user'));

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Permission denied: cannot modify this user');

        $this->useCase->execute($request, 99); // Different user, not authorized
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_connection_exceptions(): void
    {
        // Arrange: Database error during profile update
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'db_error_user',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
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
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'email' => 'invalid-email-format', // Might fail email validation
            'role' => 'InvalidRole', // Might fail role validation
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willThrowException(new \InvalidArgumentException('Invalid email format'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->useCase->execute($request, 1);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_true_when_repository_succeeds(): void
    {
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'type_test',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willReturn(true);

        $result = $this->useCase->execute($request, 1);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_repository_fails(): void
    {
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'type_test',
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->willReturn(false);

        $result = $this->useCase->execute($request, 1);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_never_returns_null_or_int_only_bool_or_exception(): void
    {
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'return_type_test',
        ]);

        // Success case: returns bool true
        $this->repositoryMock
            ->method('updateProfile')
            ->willReturn(true);

        $result = $this->useCase->execute($request, 1);
        $this->assertIsBool($result);
        $this->assertNotSame(null, $result);
        $this->assertNotSame(1, $result);

        // Error case: throws exception (doesn't return null/int)
        $this->repositoryMock
            ->method('updateProfile')
            ->willThrowException(new \Exception('Error'));

        $this->expectException(\Exception::class);
        $this->useCase->execute($request, 1);
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_preserves_request_immutability_throughout_execution(): void
    {
        // Arrange: Create request with sensitive data
        $request = $this->createValidUpdateRequest([
            'id' => 50,
            'user_name' => 'immutability_test',
            'email' => 'immutability@test.com',
            'role' => 'Admin',
            'phone_crm' => '+507 1234-5678',
            'department' => 'Security',
            'reports_to_id' => 1,
        ]);

        // Capture original state
        $originalState = [
            'id' => $request->id,
            'user_name' => $request->user_name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, 1)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert: Request should be completely unchanged
        $this->assertSame($originalState['id'], $request->id);
        $this->assertSame($originalState['user_name'], $request->user_name);
        $this->assertSame($originalState['email'], $request->email);
        $this->assertSame($originalState['role'], $request->role);
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_authorization_check_to_repository(): void
    {
        // Arrange: Use case does NOT check permissions itself, delegates to repository
        $request = $this->createValidUpdateRequest([
            'id' => 42,
            'user_name' => 'protected_user',
        ]);
        $modifiedByUserId = 99; // Different user, potentially unauthorized

        // ✅ Repository is responsible for checking if $modifiedByUserId can update $request->id
        $this->repositoryMock
            ->expects($this->once())
            ->method('updateProfile')
            ->with($request, $modifiedByUserId) // All params passed through
            ->willReturn(true); // Repository decides if authorized

        // Act
        $result = $this->useCase->execute($request, $modifiedByUserId);

        // Assert: Use case just delegates, doesn't make auth decisions
        $this->assertTrue($result);
    }
}
