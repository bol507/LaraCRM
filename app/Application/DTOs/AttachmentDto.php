<?php

namespace App\Application\DTOs;

use App\Domain\Entities\Attachment;

class AttachmentDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $googleDriveId,
        public readonly string $url,
        public readonly string $viewUrl,
        public readonly int $relatedRecordId,
        public readonly string $module,
        public readonly string $createdAt
    ) {}

    
    public static function fromEntity(Attachment $attachment): self
    {
        return new self(
            id: $attachment->id,
            name: $attachment->name,
            description: $attachment->description,
            mimeType: $attachment->mimeType,
            size: $attachment->size,
            googleDriveId: $attachment->googleDriveId,
            url: $attachment->url,
            viewUrl: $attachment->viewUrl,
            relatedRecordId: $attachment->relatedRecordId,
            module: $attachment->module,
            createdAt: $attachment->createdAt->format('Y-m-d H:i:s')
        );
    }
}