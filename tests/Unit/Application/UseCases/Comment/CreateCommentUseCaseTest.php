<?php

namespace Tests\Unit\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CreateCommentRequest;
use App\Application\UseCases\Comment\CreateCommentUseCase;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Domain\Entities\Comment;
use App\Domain\Exceptions\Comment\CommentTargetNotFoundException;
use App\Infrastructure\Repositories\CommentRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the CreateCommentUseCase.
 *
 * Focuses on the domain validation rules:
 * - Module support (Potentials, Accounts, Quotes, etc.)
 * - Related record existence (CommentTargetNotFoundException)
 * - Setype/module mismatch
 * - Validation of comment content and IDs
 *
 * The happy path is exercised with a mocked vtiger connection so no
 * real database is required.
 *
 * @package Tests\Unit\Application\UseCases\Comment
 */
#[PHPUnit\Framework\Attributes\CoversClass(CreateCommentUseCase::class)]
class CreateCommentUseCaseTest extends TestCase
{
    private CommentRepository|MockObject $commentRepoMock;
    private CreateEntityUseCase|MockObject $createEntityMock;
    private CreateCommentUseCase $useCase;

    /**
     * Boot the application so facades (DB, now()) work, but WITHOUT
     * running migrations against the real vtiger database.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->commentRepoMock = $this->createMock(CommentRepository::class);
        $this->createEntityMock = $this->createMock(CreateEntityUseCase::class);
        $this->useCase = new CreateCommentUseCase($this->createEntityMock, $this->commentRepoMock);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_unsupported_module_before_touching_database(): void
    {
        $request = new CreateCommentRequest(
            module: 'Leads',
            relatedId: 55,
            content: 'Hello',
            userId: 7,
        );

        $this->commentRepoMock->expects($this->never())->method('relatedRecordSetype');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute($request);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_empty_content(): void
    {
        $request = new CreateCommentRequest(
            module: 'Potentials',
            relatedId: 55,
            content: '   ',
            userId: 7,
        );

        $this->expectException(ValidationException::class);
        $this->useCase->execute($request);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_invalid_related_id(): void
    {
        $request = new CreateCommentRequest(
            module: 'Potentials',
            relatedId: 0,
            content: 'Hello',
            userId: 7,
        );

        $this->commentRepoMock->expects($this->never())->method('relatedRecordSetype');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute($request);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_target_not_found_when_record_is_missing(): void
    {
        $request = new CreateCommentRequest(
            module: 'Potentials',
            relatedId: 999,
            content: 'Hello',
            userId: 7,
        );

        $this->commentRepoMock
            ->expects($this->once())
            ->method('relatedRecordSetype')
            ->with(999)
            ->willReturn(null);

        try {
            $this->useCase->execute($request);
            $this->fail('Expected CommentTargetNotFoundException was not thrown.');
        } catch (CommentTargetNotFoundException $e) {
            $this->assertStringContainsString('Potentials', $e->getMessage());
            $this->assertStringContainsString('999', $e->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_when_record_belongs_to_another_module(): void
    {
        $request = new CreateCommentRequest(
            module: 'Potentials',
            relatedId: 55,
            content: 'Hello',
            userId: 7,
        );

        $this->commentRepoMock
            ->method('relatedRecordSetype')
            ->with(55)
            ->willReturn('Accounts');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute($request);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creates_comment_when_target_is_valid(): void
    {
        $request = new CreateCommentRequest(
            module: 'Potentials',
            relatedId: 55,
            content: 'Comment for the opportunity',
            userId: 7,
            parentCommentId: null,
            isPrivate: false,
        );

        $expectedId = 5001;

        // Mock the target record existence check
        $this->commentRepoMock
            ->expects($this->once())
            ->method('relatedRecordSetype')
            ->with(55)
            ->willReturn('Potentials');

        // Mock the generic CRUD entity creation (returns crmid)
        $this->createEntityMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($expectedId);

        // Expect the comment insert to be executed
        $this->commentRepoMock
            ->expects($this->once())
            ->method('insert');

        // Mock the vtiger database connection so the transaction closure
        // and the activity tracker do not hit a real database.
        $tableMock = $this->createMock(Builder::class);
        $tableMock->method('insertGetId')->willReturn(1);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('transaction')->willReturnCallback(
            fn (callable $callback) => $callback()
        );
        $connectionMock->method('table')->willReturn($tableMock);

        DB::shouldReceive('connection')->with('vtiger')->andReturn($connectionMock);

        // Act
        $comment = $this->useCase->execute($request);

        // Assert
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertSame($expectedId, $comment->getId());
        $this->assertSame(55, $comment->getRelatedTo());
        $this->assertSame('Comment for the opportunity', $comment->getContent());
        $this->assertSame(7, $comment->getUserId());
    }
}