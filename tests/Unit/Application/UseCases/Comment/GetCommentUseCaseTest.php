<?php

namespace Tests\Unit\Application\UseCases\Comment;

use App\Application\DTOs\Comment\CommentDto;
use App\Application\UseCases\Comment\GetCommentUseCase;
use App\Infrastructure\Repositories\CommentRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

/**
 * Unit tests for the GetCommentUseCase.
 *
 * Focuses on:
 * - Input validation (comment/user IDs)
 * - Comment not found handling
 * - Mapping of the repository row (stdClass) into a domain entity
 *   for the permission check and into an enriched CommentDto for the response.
 *
 * The repository is mocked so no real database is required.
 *
 * @package Tests\Unit\Application\UseCases\Comment
 */
#[PHPUnit\Framework\Attributes\CoversClass(GetCommentUseCase::class)]
class GetCommentUseCaseTest extends TestCase
{
    private CommentRepository|MockObject $commentRepoMock;
    private GetCommentUseCase $useCase;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->commentRepoMock = $this->createMock(CommentRepository::class);
        $this->useCase = new GetCommentUseCase($this->commentRepoMock);
    }

    private function sampleRow(array $overrides = []): \stdClass
    {
        return (object) array_merge([
            'modcommentsid' => 456,
            'commentcontent' => 'Task completed!',
            'related_to' => 55,
            'parent_comments' => 100,
            'customer' => null,
            'userid' => 7,
            'reasontoedit' => null,
            'is_private' => 0,
            'filename' => null,
            'related_email_id' => null,
            'createdtime' => '2026-02-27 14:30:00',
            'modifiedtime' => '2026-02-27 15:00:00',
            'label' => 'Task completed!',
            'smcreatorid' => 7,
            'smownerid' => 7,
            'related_module' => 'Project',
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'user_email' => 'maria@example.com',
            'user_name' => 'Maria Garcia',
        ], $overrides);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_invalid_comment_id(): void
    {
        $this->commentRepoMock->expects($this->never())->method('findById');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(0, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_invalid_user_id(): void
    {
        $this->commentRepoMock->expects($this->never())->method('findById');

        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(456, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_not_found_when_comment_is_missing(): void
    {
        $this->commentRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->useCase->execute(999, 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_enriched_dto_for_existing_comment(): void
    {
        $row = $this->sampleRow();

        $this->commentRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with(456)
            ->willReturn($row);

        $dto = $this->useCase->execute(456, 1);

        $this->assertInstanceOf(CommentDto::class, $dto);
        $this->assertSame(456, $dto->id);
        $this->assertSame('Task completed!', $dto->content);
        $this->assertSame(55, $dto->relatedTo);
        $this->assertSame(100, $dto->parentCommentId);
        $this->assertSame(7, $dto->userId);
        $this->assertSame('Maria Garcia', $dto->userName);
        $this->assertSame('maria@example.com', $dto->userEmail);
        $this->assertSame('Project', $dto->relatedModule);
        $this->assertSame('2026-02-27 14:30:00', $dto->createdAt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_dto_for_private_comment_when_internal_user(): void
    {
        $row = $this->sampleRow(['is_private' => 1, 'parent_comments' => null]);

        $this->commentRepoMock
            ->expects($this->once())
            ->method('findById')
            ->with(456)
            ->willReturn($row);

        $dto = $this->useCase->execute(456, 1);

        $this->assertInstanceOf(CommentDto::class, $dto);
        $this->assertTrue($dto->isPrivate);
        $this->assertNull($dto->parentCommentId);
    }
}