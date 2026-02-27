<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\FindUserByFullNameUseCase;
use App\Application\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the FindUserByFullNameUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer with correct parameters
 * - Handling of found vs not-found users
 * - Return type consistency (?array)
 * - Error propagation from repository
 * - Search term parameter validation
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\FindUserByFullNameUseCase
 */
class FindUserByFullNameUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private FindUserByFullNameUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new FindUserByFullNameUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HAPPY PATH: USER FOUND
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_user_array_when_found(): void
    {
        // Arrange: Create expected user data array
        $fullName = 'John Doe';
        $expectedUserData = [
            'id' => 42,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john.doe@example.com',
            'role' => 'Usuario',
        ];

        // ✅ Expect repository to be called with exact search term
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($fullName)
            ->willReturn($expectedUserData);

        // Act
        $result = $this->useCase->execute($fullName);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($expectedUserData, $result);
        $this->assertSame(42, $result['id']);
        $this->assertSame('John Doe', $result['first_name'] . ' ' . $result['last_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_user_array_with_all_expected_fields(): void
    {
        // Arrange: User data with all possible fields
        $fullName = 'Jane Smith';
        $expectedUserData = [
            'id' => 100,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'user_name' => 'janesmith',
            'email' => 'jane.smith@example.com',
            'role' => 'Admin',
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($fullName)
            ->willReturn($expectedUserData);

        // Act
        $result = $this->useCase->execute($fullName);

        // Assert: Verify all expected fields exist
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('user_name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('role', $result);
    }

    // ========================================================================
    // EDGE CASE: USER NOT FOUND
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_when_user_not_found(): void
    {
        // Arrange: Repository returns null for non-existent user
        $nonExistentName = 'Non Existent User';

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($nonExistentName)
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute($nonExistentName);

        // Assert
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_for_empty_search_term(): void
    {
        // Arrange: Empty string search
        $emptySearch = '';

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($emptySearch)
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute($emptySearch);

        // Assert
        $this->assertNull($result);
    }

    // ========================================================================
    // PARAMETER VARIATIONS & EDGE CASES
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_single_name_search(): void
    {
        // Arrange: Search with only first name
        $singleName = 'John';
        $expectedUserData = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'Usuario',
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($singleName)
            ->willReturn($expectedUserData);

        // Act
        $result = $this->useCase->execute($singleName);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_full_name_with_middle_name(): void
    {
        // Arrange: Search with three-part name
        $fullName = 'John Michael Doe';
        $expectedUserData = [
            'id' => 42,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'Usuario',
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($fullName)
            ->willReturn($expectedUserData);

        // Act
        $result = $this->useCase->execute($fullName);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame(42, $result['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_name_with_special_characters(): void
    {
        // Arrange: Names with accents, hyphens, etc.
        $testCases = [
            'José García',
            'María López-Pérez',
            'François Müller',
            '李明 (Chinese name)',
        ];

        foreach ($testCases as $fullName) {
            $expectedUserData = [
                'id' => 1,
                'first_name' => 'Test',
                'last_name' => 'User',
                'user_name' => 'testuser',
                'email' => 'test@example.com',
                'role' => 'Usuario',
            ];

            $this->repositoryMock
                ->expects($this->once())
                ->method('findByFullName')
                ->with($fullName)
                ->willReturn($expectedUserData);

            // Act
            $result = $this->useCase->execute($fullName);

            // Assert
            $this->assertIsArray($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new FindUserByFullNameUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_case_variations_in_search_term(): void
    {
        // Arrange: Different case variations (repository handles case-sensitivity)
        $testCases = [
            'john doe',
            'John Doe',
            'JOHN DOE',
            'JoHn DoE',
        ];

        foreach ($testCases as $searchTerm) {
            $expectedUserData = [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'user_name' => 'johndoe',
                'email' => 'john@example.com',
                'role' => 'Usuario',
            ];

            $this->repositoryMock
                ->expects($this->once())
                ->method('findByFullName')
                ->with($searchTerm)
                ->willReturn($expectedUserData);

            // Act
            $result = $this->useCase->execute($searchTerm);

            // Assert
            $this->assertIsArray($result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new FindUserByFullNameUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_name_with_extra_whitespace(): void
    {
        // Arrange: Search term with extra spaces (repository should handle trimming)
        $searchTerm = '  John   Doe  ';
        $expectedUserData = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'Usuario',
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($searchTerm)
            ->willReturn($expectedUserData);

        // Act
        $result = $this->useCase->execute($searchTerm);

        // Assert
        $this->assertIsArray($result);
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_database_connection_exceptions(): void
    {
        // Arrange: Database error during search
        $fullName = 'John Doe';

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($fullName)
            ->willThrowException(new \RuntimeException('Database connection failed'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute($fullName);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_from_repository(): void
    {
        // Arrange: Repository validates search term and throws
        $invalidSearch = str_repeat('a', 500); // Too long search term

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($invalidSearch)
            ->willThrowException(new \InvalidArgumentException('Search term too long'));

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search term too long');

        $this->useCase->execute($invalidSearch);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_unexpected_exceptions_without_swallowing(): void
    {
        // Arrange: Unexpected error in repository
        $fullName = 'John Doe';

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->willThrowException(new \LogicException('Unexpected state in repository'));

        // Act & Assert
        $this->expectException(\LogicException::class);

        $this->useCase->execute($fullName);
    }

    // ========================================================================
    // RETURN TYPE CONSISTENCY TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_array_when_user_is_found(): void
    {
        $expectedUserData = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'Usuario',
        ];

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with('John Doe')
            ->willReturn($expectedUserData);

        $result = $this->useCase->execute('John Doe');

        $this->assertIsArray($result);
        $this->assertSame($expectedUserData, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_null_when_user_is_not_found(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with('Non Existent')
            ->willReturn(null);

        $result = $this->useCase->execute('Non Existent');

        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_never_returns_user_entity_only_array_or_null(): void
    {
        // Arrange: Repository should return array, not User entity
        $arrayResult = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_name' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'Usuario',
        ];

        $this->repositoryMock
            ->method('findByFullName')
            ->willReturn($arrayResult);

        $result = $this->useCase->execute('John Doe');

        // Assert: Should be array, NOT User entity
        $this->assertIsArray($result);
        $this->assertNotInstanceOf(\App\Domain\Entities\User::class, $result);
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_passes_search_term_unchanged_to_repository(): void
    {
        // Arrange: Capture the exact search term passed
        $originalSearchTerm = 'John Michael Doe';

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($originalSearchTerm)
            ->willReturnCallback(function ($searchTerm) use ($originalSearchTerm) {
                // ✅ Verify search term is passed exactly as provided
                $this->assertSame($originalSearchTerm, $searchTerm);
                return [
                    'id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'user_name' => 'johndoe',
                    'email' => 'john@example.com',
                    'role' => 'Usuario',
                ];
            });

        // Act
        $result = $this->useCase->execute($originalSearchTerm);

        // Assert
        $this->assertIsArray($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_does_not_modify_or_transform_search_term(): void
    {
        // Arrange: Use case should NOT transform the search term
        $searchTerm = '  José García  '; // With spaces and accents

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByFullName')
            ->with($searchTerm) // ✅ Exact term, not trimmed or modified
            ->willReturn(null);

        // Act
        $result = $this->useCase->execute($searchTerm);

        // Assert: Use case delegates without transformation
        $this->assertNull($result);
    }
}
