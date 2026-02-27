<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\DeleteUserUseCase;
use App\Application\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the DeleteUserUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer with correct parameters
 * - Handling of successful and failed user deletions
 * - Return type consistency (bool)
 * - Error propagation from repository (especially permission errors)
 * - Audit trail parameter passing (deletedByUserId)
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\DeleteUserUseCase
 */
class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private DeleteUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new DeleteUserUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HAPPY PATH: USER DELETION SUCCESS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_true_when_user_deletion_succeeds(): void
    {
        // Arrange: Valid user ID and deleting user ID
        $userIdToDelete = 42;
        $deletedByUserId = 5; // Admin performing the deletion

        // ✅ Expect repository to be called with exact parameters
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userIdToDelete, $deletedByUserId)
            ->willReturn(true);

        // Act
        $result = $this->useCase->execute($userIdToDelete, $deletedByUserId);

        // Assert
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_deletes_user_with_correct_audit_trail(): void
    {
        // Arrange: Different deletion scenarios for audit testing
        $testCases = [
            ['userId' => 42, 'deletedBy' => 1, 'description' => 'Admin deletes user'],
            ['userId' => 100, 'deletedBy' => 5, 'description' => 'Manager deletes subordinate'],
            ['userId' => 999, 'deletedBy' => 42, 'description' => 'System process deletes user'],
        ];

        foreach ($testCases as $case) {
            $this->repositoryMock
                ->expects($this->once())
                ->method('delete')
                ->with($case['userId'], $case['deletedBy'])
                ->willReturn(true);

            // Act
            $result = $this->useCase->execute($case['userId'], $case['deletedBy']);

            // Assert
            $this->assertTrue($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new DeleteUserUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // EDGE CASE: USER DELETION FAILS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_user_not_found(): void
    {
        // Arrange: Repository returns false for non-existent user
        $nonExistentUserId = 999999;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($nonExistentUserId, 1)
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($nonExistentUserId, 1);

        // Assert
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_user_already_deleted(): void
    {
        // Arrange: Repository returns false for already soft-deleted user
        $alreadyDeletedUserId = 42;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($alreadyDeletedUserId, 1)
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($alreadyDeletedUserId, 1);

        // Assert
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_deletion_rejected_by_repository(): void
    {
        // Arrange: Repository rejects deletion (e.g., last admin protection)
        $lastAdminId = 1;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($lastAdminId, 5)
            ->willReturn(false);

        // Act
        $result = $this->useCase->execute($lastAdminId, 5);

        // Assert
        $this->assertFalse($result);
    }

    // ========================================================================
    // PARAMETER VARIATIONS & EDGE CASES
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_small_positive_user_ids(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with(1, 1)
            ->willReturn(true);

        $result = $this->useCase->execute(1, 1);

        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_large_user_ids(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with(2147483647, 1) // Max int
            ->willReturn(true);

        $result = $this->useCase->execute(2147483647, 1);

        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_same_user_id_for_deleted_by(): void
    {
        // Note: Repository should handle self-deletion validation
        $userId = 42;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId, $userId)
            ->willReturn(true);

        $result = $this->useCase->execute($userId, $userId);

        $this->assertTrue($result);
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_permission_denied_exceptions(): void
    {
        // Arrange: Repository throws exception for unauthorized deletion
        $userId = 42;
        $unauthorizedDeleter = 99;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId, $unauthorizedDeleter)
            ->willThrowException(new \DomainException('Only administrators can delete users'));

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only administrators can delete users');

        $this->useCase->execute($userId, $unauthorizedDeleter);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_self_deletion_prevention_exceptions(): void
    {
        // Arrange: Repository throws exception for self-deletion attempt
        $userId = 42;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId, $userId)
            ->willThrowException(new \DomainException('You cannot delete your own account'));

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You cannot delete your own account');

        $this->useCase->execute($userId, $userId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_last_admin_protection_exceptions(): void
    {
        // Arrange: Repository throws exception for deleting last admin
        $lastAdminId = 1;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($lastAdminId, 5)
            ->willThrowException(new \DomainException('Cannot delete the last administrator'));

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete the last administrator');

        $this->useCase->execute($lastAdminId, 5);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_connection_exceptions(): void
    {
        // Arrange: Database error during user deletion
        $userId = 42;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId, 1)
            ->willThrowException(new \RuntimeException('Database connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute($userId, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_without_swallowing(): void
    {
        // Arrange: Repository validates and throws on invalid user ID
        $invalidUserId = 0;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($invalidUserId, 1)
            ->willThrowException(new \InvalidArgumentException('Invalid user ID'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user ID');

        $this->useCase->execute($invalidUserId, 1);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_true_when_repository_succeeds(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->useCase->execute(42, 1);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_false_when_repository_fails(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $result = $this->useCase->execute(42, 1);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_never_returns_null_or_int_only_bool_or_exception(): void
    {
        // Success case: returns bool true
        $this->repositoryMock
            ->method('delete')
            ->willReturn(true);
        
        $result = $this->useCase->execute(42, 1);
        $this->assertIsBool($result);
        $this->assertNotSame(null, $result);
        $this->assertNotSame(1, $result);

        // Error case: throws exception (doesn't return null/int)
        $this->repositoryMock
            ->method('delete')
            ->willThrowException(new \Exception('Error'));
        
        $this->expectException(\Exception::class);
        $this->useCase->execute(42, 1);
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_authorization_check_to_repository(): void
    {
        // Arrange: Use case does NOT check permissions itself, delegates to repository
        $userId = 42;
        $potentialUnauthorizedDeleter = 99;

        // ✅ Repository is responsible for checking if $deletedByUserId can delete $userId
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId, $potentialUnauthorizedDeleter)
            ->willReturn(true); // Repository decides if authorized

        // Act
        $result = $this->useCase->execute($userId, $potentialUnauthorizedDeleter);

        // Assert: Use case just delegates, doesn't make auth decisions
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_preserves_parameter_values_throughout_execution(): void
    {
        // Arrange: Capture original parameter values
        $originalUserId = 42;
        $originalDeletedByUserId = 5;

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($originalUserId, $originalDeletedByUserId)
            ->willReturnCallback(function ($userId, $deletedByUserId) use ($originalUserId, $originalDeletedByUserId) {
                // ✅ Verify parameters are passed exactly as provided
                $this->assertSame($originalUserId, $userId);
                $this->assertSame($originalDeletedByUserId, $deletedByUserId);
                return true;
            });

        // Act
        $result = $this->useCase->execute($originalUserId, $originalDeletedByUserId);

        // Assert
        $this->assertTrue($result);
    }
}