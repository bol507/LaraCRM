<?php

namespace Tests\Unit\Application\UseCases\User;

use App\Application\UseCases\User\GetAllUsersUseCase;
use App\Application\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the GetAllUsersUseCase.
 * 
 * Tests focus on:
 * - Delegation to repository layer (no business logic in this use case)
 * - Parameter passing and default values
 * - Return type consistency
 * - Error propagation from repository
 * 
 * @package Tests\Unit\Application\UseCases\User
 * @covers \App\Application\UseCases\User\GetAllUsersUseCase
 */
class GetAllUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private GetAllUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Create mock repository for isolation testing
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new GetAllUsersUseCase($this->repositoryMock);
    }

    // ========================================================================
    // HAPPY PATH TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_to_repository_with_default_parameters(): void
    {
        // Arrange: Create mock paginator response
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);
        $mockPaginator->method('currentPage')->willReturn(1);
        $mockPaginator->method('lastPage')->willReturn(1);
        $mockPaginator->method('perPage')->willReturn(20);
        $mockPaginator->method('total')->willReturn(0);

        // ✅ Expect repository to be called with default parameters
        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 20, null)
            ->willReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame($mockPaginator, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_to_repository_with_custom_parameters(): void
    {
        // Arrange
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $page = 3;
        $perPage = 50;
        $search = 'john';

        // ✅ Expect repository to receive the exact parameters passed
        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with($page, $perPage, $search)
            ->willReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute($page, $perPage, $search);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_delegates_empty_search_string_correctly(): void
    {
        // Arrange
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        // ✅ Empty string should be passed as-is (not converted to null)
        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 20, '')
            ->willReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute(1, 20, '');

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    // ========================================================================
    // PARAMETER EDGE CASES
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_page_one_as_minimum(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 20, null)
            ->willReturn($mockPaginator);

        $result = $this->useCase->execute(1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_large_page_numbers(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with(999, 20, null)
            ->willReturn($mockPaginator);

        $result = $this->useCase->execute(999);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_various_per_page_values(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $testValues = [1, 10, 20, 50, 100];

        foreach ($testValues as $perPage) {
            $this->repositoryMock
                ->expects($this->once())
                ->method('getAll')
                ->with(1, $perPage, null)
                ->willReturn($mockPaginator);

            $result = $this->useCase->execute(1, $perPage);
            $this->assertInstanceOf(LengthAwarePaginator::class, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new GetAllUsersUseCase($this->repositoryMock);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_null_search_parameter(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->with(1, 20, null)
            ->willReturn($mockPaginator);

        $result = $this->useCase->execute(1, 20, null);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_accepts_search_with_special_characters(): void
    {
        $mockPaginator = $this->createMock(LengthAwarePaginator::class);
        $mockPaginator->method('items')->willReturn([]);

        $searchTerms = [
            'john doe',
            'user@example.com',
            'admin+tag',
            'café',
            '用户',
        ];

        foreach ($searchTerms as $search) {
            $this->repositoryMock
                ->expects($this->once())
                ->method('getAll')
                ->with(1, 20, $search)
                ->willReturn($mockPaginator);

            $result = $this->useCase->execute(1, 20, $search);
            $this->assertInstanceOf(LengthAwarePaginator::class, $result);

            // Reset mock for next iteration
            $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
            $this->useCase = new GetAllUsersUseCase($this->repositoryMock);
        }
    }

    // ========================================================================
    // RETURN VALUE TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_paginator_with_users(): void
    {
        // Arrange: Create real User entities for more realistic test
        $users = [
            new User(
                id: 1,
                userName: 'user1',
                firstName: 'John',
                lastName: 'Doe',
                email: 'john@example.com',
                role: 'Usuario',
                status: 'Active'
            ),
            new User(
                id: 2,
                userName: 'user2',
                firstName: 'Jane',
                lastName: 'Smith',
                email: 'jane@example.com',
                role: 'Admin',
                status: 'Active'
            ),
        ];

        // Create a real paginator with the users
        $paginator = new LengthAwarePaginator(
            new Collection($users),
            2,  // total
            20, // per_page
            1,  // current_page
            ['path' => '/api/users']
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($paginator);

        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertSame(2, $result->total());
        $this->assertSame(1, $result->currentPage());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_returns_empty_paginator_when_no_users(): void
    {
        $emptyPaginator = new LengthAwarePaginator(
            new Collection([]),
            0,  // total
            20, // per_page
            1,  // current_page
            ['path' => '/api/users']
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($emptyPaginator);

        $result = $this->useCase->execute();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEmpty($result->items());
        $this->assertSame(0, $result->total());
    }

    // ========================================================================
    // ERROR PROPAGATION TESTS
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_repository_exceptions(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->useCase->execute();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_propagates_validation_exceptions_from_repository(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new \InvalidArgumentException('Invalid search parameter'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid search parameter');

        $this->useCase->execute(1, 20, 'invalid@search');
    }

    // ========================================================================
    // INTEGRATION-STYLE TEST (optional, for confidence)
    // ========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function execute_preserves_paginator_metadata(): void
    {
        // Arrange: Create paginator with specific metadata
        $users = [
            new User(
                id: 1,
                userName: 'test',
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
                role: 'Usuario',
                status: 'Active'
            ),
        ];

        $originalPaginator = new LengthAwarePaginator(
            new Collection($users),
            150, // total: 150 users in DB
            25,  // per_page: 25 items per page
            6,   // current_page: page 6 (LAST PAGE: ceil(150/25) = 6)
            ['path' => 'https://api.example.com/users']
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($originalPaginator);

        // Act
        $result = $this->useCase->execute(6, 25);

        // Assert: Metadata should be preserved exactly
        $this->assertSame(6, $result->currentPage());
        $this->assertSame(25, $result->perPage());
        $this->assertSame(150, $result->total());
        $this->assertSame(6, $result->lastPage()); // ceil(150/25) = 6

        
        $this->assertNull($result->nextPageUrl()); 
        $this->assertNotNull($result->previousPageUrl()); // ✅ Page 5 exists
    }
}
