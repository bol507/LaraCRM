<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\ChangePasswordUseCase;
use App\Application\DTOs\User\ChangePasswordRequest;
use App\Application\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the ChangePasswordUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer with correct parameters
 * - Handling of success and failure scenarios
 * - Return type consistency (bool)
 * - Error propagation from repository
 * - Request object usage and immutability
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\ChangePasswordUseCase
 */
class ChangePasswordUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private ChangePasswordUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new ChangePasswordUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HAPPY PATH: PASSWORD CHANGE SUCCESS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_true_when_password_changed_successfully(): void
    {
        // Arrange: Create valid request and user IDs
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'NewSecureP@ss123'
        );
        $modifiedByUserId = 5; // Admin or the user themselves

        // ✅ Expect repository to be called with exact parameters from request
        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with($request->userId, $request->newPassword, $modifiedByUserId)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, $modifiedByUserId);

        // Assert
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_exact_password_value_without_modification(): void
    {
        // Arrange: Password with special characters that should not be altered
        $complexPassword = 'P@ssw0rd!#$%^&*()_+-=[]{}|;:,.<>?';
        $request = new ChangePasswordRequest(
            userId: 100,
            newPassword: $complexPassword
        );
        $modifiedByUserId = 1;

        // ✅ Verify the exact password string is passed to repository
        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with(100, $complexPassword, 1)
            ->willReturn(true);

        // Act
        $this->useCase->execute($request, $modifiedByUserId);

        // Assert: If we reach here, the mock expectation was met
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_different_user_ids_correctly(): void
    {
        // Arrange: Test various user ID combinations
        $testCases = [
            ['userId' => 1, 'modifiedBy' => 1],    // User changing own password
            ['userId' => 42, 'modifiedBy' => 1],   // Admin changing user's password
            ['userId' => 999, 'modifiedBy' => 5],  // Different admin
        ];

        foreach ($testCases as $case) {
            $request = new ChangePasswordRequest(
                userId: $case['userId'],
                newPassword: 'TestP@ss123'
            );

            $this->repositoryMock
                ->expects($this->once())
                ->method('changePassword')
                ->with($case['userId'], 'TestP@ss123', $case['modifiedBy'])
                ->willReturn(true);

            // Act
            $result = $this->useCase->execute($request, $case['modifiedBy']);

            // Assert
            $this->assertTrue($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new ChangePasswordUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // EDGE CASE: PASSWORD CHANGE FAILS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_user_not_found(): void
    {
        // Arrange: Repository returns false for non-existent user
        $request = new ChangePasswordRequest(
            userId: 999999,
            newPassword: 'NewP@ss123'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with(999999, 'NewP@ss123', 1)
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_password_change_rejected_by_repository(): void
    {
        // Arrange: Repository rejects the change (e.g., policy violation)
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'weak' // Might fail password policy
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertFalse($result);
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_permission_denied_exceptions(): void
    {
        // Arrange: Repository throws exception for unauthorized change
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'NewP@ss123'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->willThrowException(new \DomainException('Cannot change password: insufficient permissions'));

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot change password: insufficient permissions');

        $this->useCase->execute($request, 99); // Different user, not admin
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_from_repository(): void
    {
        // Arrange: Repository validates password and throws on weak password
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: '123' // Too weak
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->willThrowException(new \InvalidArgumentException('Password does not meet security requirements'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password does not meet security requirements');

        $this->useCase->execute($request, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_exceptions_without_swallowing(): void
    {
        // Arrange: Database error during password update
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'ValidP@ss123'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute($request, 1);
    }

    // ========================================================================
    // REQUEST OBJECT TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_uses_request_properties_without_modifying_request(): void
    {
        // Arrange: Create request and capture its values
        $originalUserId = 42;
        $originalPassword = 'OriginalP@ss123';
        $request = new ChangePasswordRequest(
            userId: $originalUserId,
            newPassword: $originalPassword
        );

        // ✅ Verify request properties are read-only (immutable by design)
        $this->assertSame($originalUserId, $request->userId);
        $this->assertSame($originalPassword, $request->newPassword);

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with($originalUserId, $originalPassword, 1)
            ->willReturn(true);

        // Act
        $this->useCase->execute($request, 1);

        // Assert: Request should remain unchanged after use case execution
        $this->assertSame($originalUserId, $request->userId);
        $this->assertSame($originalPassword, $request->newPassword);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_request_with_minimum_required_fields(): void
    {
        // Arrange: Request with only required fields (ChangePasswordRequest has only userId and newPassword)
        $request = new ChangePasswordRequest(
            userId: 1,
            newPassword: 'MinP@ss'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with(1, 'MinP@ss', 1)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($request, 1);

        // Assert
        $this->assertTrue($result);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_always_returns_boolean_never_other_type(): void
    {
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'TestP@ss'
        );

        // Test success case
        $this->repositoryMock
            ->expects($this->exactly(2))
            ->method('changePassword')
            ->willReturnOnConsecutiveCalls(true, false);

        $result1 = $this->useCase->execute($request, 1);
        $this->assertIsBool($result1);
        $this->assertTrue($result1);

        // Segunda llamada: fallo
        $result2 = $this->useCase->execute($request, 1);
        $this->assertIsBool($result2);
        $this->assertFalse($result2);
    }

    // ========================================================================
    // SECURITY-RELATED TESTS (delegation only, auth logic in repository)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_authorization_check_to_repository(): void
    {
        // Arrange: Use case does NOT check permissions itself, delegates to repository
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'NewP@ss123'
        );
        $modifiedByUserId = 99; // Different user, potentially unauthorized

        // ✅ Repository is responsible for checking if $modifiedByUserId can change $request->userId's password
        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with(42, 'NewP@ss123', 99) // All params passed through
            ->willReturn(true); // Repository decides if authorized

        // Act
        $result = $this->useCase->execute($request, $modifiedByUserId);

        // Assert: Use case just delegates, doesn't make auth decisions
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_passes_modified_by_user_id_for_audit_trail(): void
    {
        // Arrange: The modifiedByUserId is crucial for audit logging
        $request = new ChangePasswordRequest(
            userId: 42,
            newPassword: 'NewP@ss123'
        );
        $adminId = 5;

        // ✅ Verify admin ID is passed for audit trail in repository
        $this->repositoryMock
            ->expects($this->once())
            ->method('changePassword')
            ->with(42, 'NewP@ss123', $adminId) // ✅ adminId passed through
            ->willReturn(true);

        // Act
        $this->useCase->execute($request, $adminId);

        // Assert: If mock expectation met, audit ID was passed correctly
        $this->assertTrue(true);
    }
}
