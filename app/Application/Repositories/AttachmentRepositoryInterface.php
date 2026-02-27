<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Attachment;
use Illuminate\Http\UploadedFile;

interface AttachmentRepositoryInterface
{
    public function upload(
        UploadedFile $file,
        string $module,
        int $recordId,
        int $authenticatedUserId, 
        ?string $description = null
    ): Attachment;

   
    public function findByModuleAndRecord(string $module, int $recordId): array;

    public function delete(int $attachmentId, int $authenticatedUserId): bool;
}