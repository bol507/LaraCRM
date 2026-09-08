<?php

namespace Tests\Unit\Application\UseCases\Comment;

use App\Application\Repositories\CommentRepositoryInterface;
use App\Application\UseCases\Comment\GetCommentsByRelatedIdUseCase;
use App\Domain\Exceptions\Comment\CommentTargetNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the GetCommentsByRelatedIdUseCase.
 *
 * Focuses on:
 * - Parameter validation (relatedId, page, perPage)
 * - Module support validation
 * - Related record existence (CommentTargetNotFoundException)
 * - Setype/module mismatch
 * - Delegation to the repository on the happy path
 *
 * @package Tests\Unit\Application\UseCases\Comment
 */
#[PHPUnit\Framework\Attributes\CoversClass(GetCommentsByRelatedIdUseCase::class)]
class GetCommentsByRelatedIdUseCaseTest extends TestCase
{
    private CommentRepositoryInterface|MockObject $repositoryMock;
    private GetCommentsByRelatedIdUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(CommentRepositoryInterface::class);
        $this->useCase = new GetCommentsByRelatedIdUseCase($this->repositoryMock);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_non_positive_related_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute(0, 'Potentials');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_invalid_pagination_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(5, 'Potentials', page: 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_unsupported_module_before_database_call(): void
    {
        $this->repositoryMock->expects($this->never())->method('relatedRecordSetype');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(5, 'Invoice');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_target_not_found_when_record_is_missing(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('relatedRecordSetype')
            ->with(999)
            ->willReturn(null);

        try {
            $this->useCase->execute(999, 'Quotes');
            $this->fail('Expected CommentTargetNotFoundException was not thrown.');
        } catch (CommentTargetNotFoundException $e) {
            $this->assertStringContainsString('Quotes', $e->getMessage());
            $this->assertStringContainsString('999', $e->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_when_record_belongs_to_another_module(): void
    {
        $this->repositoryMock
            ->method('relatedRecordSetype')
            ->with(55)
            ->willReturn('Accounts');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(55, 'Potentials');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_comments_when_target_is_valid(): void
    {
        $expectedPaginator = new LengthAwarePaginator(
            items: collect([]),
            total: 0,
            perPage: 50,
            currentPage: 1,
            options: ['path' => '/comments/Potentials/55'],
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('relatedRecordSetype')
            ->with(55)
            ->willReturn('Potentials');

        $this->repositoryMock
            ->expects($this->once())
            ->method('getByRelatedId')
            ->with(55, 'Potentials', 1, 50)
            ->willReturn($expectedPaginator);

        $result = $this->useCase->execute(55, 'Potentials');

        $this->assertSame($expectedPaginator, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function passes_pagination_args_through(): void
    {
        $expectedPaginator = new LengthAwarePaginator(
            items: collect([]),
            total: 0,
            perPage: 20,
            currentPage: 3,
            options: ['path' => '/comments/Contacts/12'],
        );

        $this->repositoryMock->method('relatedRecordSetype')->willReturn('Contacts');
        $this->repositoryMock
            ->expects($this->once())
            ->method('getByRelatedId')
            ->with(12, 'Contacts', 3, 20)
            ->willReturn($expectedPaginator);

        $result = $this->useCase->execute(12, 'Contacts', page: 3, perPage: 20);

        $this->assertSame(3, $result->currentPage());
        $this->assertSame(20, $result->perPage());
    }
}