<?php

namespace App\Application\UseCases\Attachment;

use App\Application\Repositories\AttachmentRepositoryInterface;

class DeleteAttachmentUseCase
{
    public function __construct(
        private readonly AttachmentRepositoryInterface $repository
    ) {}

    public function execute(int $attachmentId, int $authenticatedUserId): bool
    {
        return $this->repository->delete($attachmentId, $authenticatedUserId);
    }
}