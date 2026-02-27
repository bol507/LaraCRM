<?php

namespace App\Application\UseCases\Attachment;

use App\Application\Repositories\AttachmentRepositoryInterface;
use App\Domain\Entities\Attachment;

class ListAttachmentsUseCase
{
    public function __construct(
        private readonly AttachmentRepositoryInterface $repository
    ) {}

    public function execute(string $module, int $recordId): array
    {
        return $this->repository->findByModuleAndRecord($module, $recordId);
    }
}