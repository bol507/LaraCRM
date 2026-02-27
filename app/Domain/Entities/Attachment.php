<?php

namespace App\Domain\Entities;

class Attachment
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
        public readonly \DateTimeImmutable $createdAt
    ) {}
}