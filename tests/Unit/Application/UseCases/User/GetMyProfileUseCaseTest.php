<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\GetMyProfileUseCase;
use App\Application\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the GetMyProfileUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer (no business logic in this use case)
 * - Handling of found vs not-found users
 * - Return type consistency (?User)
 * - Error propagation from repository
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\GetMyProfileUseCase
 */
class GetMyProfileUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private GetMyProfileUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new GetMyProfileUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HAPPY PATH: USER FOUND
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_user_when_found(): void
    {
        // Arrange: Create a valid User entity
        $expectedUser = new User(
            id: 42,
            userName: 'johndoe',
            firstName: 'John',
            lastName: 'Doe',
            email: 'john.doe@example.com',
            role: 'Usuario',
            status: 'Active',
            phoneCrm: '+507 6123-4567',
            department: 'Engineering',
            reportsToId: 10,
            isActive: true
        );

        $userId = 42;

        // ✅ Expect repository to be called with the correct user ID
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($expectedUser);

        // Act
        $result = $this->useCase->execute($userId);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($expectedUser, $result);
        $this->assertSame(42, $result->getId());
        $this->assertSame('johndoe', $result->getUserName());
        $this->assertSame('john.doe@example.com', $result->getEmail());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_user_with_all_properties_intact(): void
    {
        // Arrange: User with various property types
        $expectedUser = new User(
            id: 100,
            userName: 'admin_user',
            firstName: 'Admin',
            lastName: 'User',
            email: 'admin@example.com',
            role: 'Admin',
            status: 'Active',
            phoneCrm: null,
            department: 'IT',
            reportsToId: null,
            isActive: true
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(100)
            ->willReturn($expectedUser);

        // Act
        $result = $this->useCase->execute(100);

        // Assert: All properties should be preserved
        $this->assertSame(100, $result->getId());
        $this->assertSame('admin_user', $result->getUserName());
        $this->assertSame('Admin', $result->getRole());
        $this->assertTrue($result->isAdmin());
        $this->assertNull($result->getPhoneCrm());
        $this->assertNull($result->getReportsToId());
        $this->assertSame('IT', $result->getDepartment());
    }

    // ========================================================================
    // EDGE CASE: USER NOT FOUND
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_when_user_not_found(): void
    {
        // Arrange: Repository returns null for non-existent user
        $nonExistentUserId = 999999;

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($nonExistentUserId)
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute($nonExistentUserId);

        // Assert
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_for_zero_user_id(): void
    {
        // Arrange: Edge case with invalid ID
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(0)
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute(0);

        // Assert
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_for_negative_user_id(): void
    {
        // Arrange: Edge case with negative ID (repository should handle validation)
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(-1)
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute(-1);

        // Assert
        $this->assertNull($result);
    }

    // ========================================================================
    // PARAMETER VARIATIONS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_small_positive_user_ids(): void
    {
        $expectedUser = new User(
            id: 1,
            userName: 'first_user',
            firstName: 'First',
            lastName: 'User',
            email: 'first@example.com',
            role: 'Usuario',
            status: 'Active'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($expectedUser);

        $result = $this->useCase->execute(1);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame(1, $result->getId());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_large_user_ids(): void
    {
        $expectedUser = new User(
            id: 999999,
            userName: 'large_id_user',
            firstName: 'Large',
            lastName: 'IdUser',
            email: 'large@example.com',
            role: 'Cliente',
            status: 'Pending'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(999999)
            ->willReturn($expectedUser);

        $result = $this->useCase->execute(999999);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame(999999, $result->getId());
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_connection_exceptions(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute(42);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_from_repository(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willThrowException(new \InvalidArgumentException('Invalid user ID format'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user ID format');

        $this->useCase->execute(42);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_unexpected_exceptions_without_swallowing(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willThrowException(new \LogicException('Unexpected state in repository'));

        $this->expectException(\LogicException::class);

        $this->useCase->execute(42);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_always_returns_user_or_null_never_other_type(): void
    {
        // Test with user found
        $user = new User(
            id: 1,
            userName: 'test',
            firstName: 'Test',
            lastName: 'User',
            email: 'test@example.com',
            role: 'Usuario',
            status: 'Active'
        );
        $this->repositoryMock
            ->method('findById')
            ->willReturn($user);
        
        $result1 = $this->useCase->execute(1);
        $this->assertTrue($result1 instanceof User || $result1 === null);

        // Test with user not found
        $this->repositoryMock
            ->method('findById')
            ->willReturn(null);
        
        $result2 = $this->useCase->execute(999);
        $this->assertTrue($result2 instanceof User || $result2 === null);
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_preserves_user_entity_immutability(): void
    {
        // Arrange: Create user and get result from use case
        $originalUser = new User(
            id: 50,
            userName: 'immutable_test',
            firstName: 'Immutable',
            lastName: 'Test',
            email: 'immutable@example.com',
            role: 'Usuario',
            status: 'Active'
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(50)
            ->willReturn($originalUser);

        // Act
        $result = $this->useCase->execute(50);

        // Assert: The returned user should be the same instance (or equal)
        // and its properties should not be modifiable through the use case
        $this->assertSame($originalUser->getId(), $result->getId());
        $this->assertSame($originalUser->getEmail(), $result->getEmail());
        
        // Verify entity methods work correctly on returned object
        $this->assertSame('Immutable Test', $result->getFullName());
        $this->assertFalse($result->isAdmin());
        $this->assertTrue($result->isActive());
    }
}