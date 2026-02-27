<?php

namespace App\Application\UseCases\Attachment;

use App\Application\Repositories\AttachmentRepositoryInterface;
use App\Domain\Entities\Attachment;
use Illuminate\Http\UploadedFile;

class UploadAttachmentUseCase
{
    public function __construct(
        private readonly AttachmentRepositoryInterface $repository
    ) {}

    public function execute(
        UploadedFile $file,
        string $module,
        int $recordId,
        int $authenticatedUserId, 
        ?string $description = null
    ): Attachment {
      
        $allowedModules = ['Project', 'Quote', 'Opportunity', 'Account', 'Contact'];
        if (!in_array($module, $allowedModules)) {
            throw new \InvalidArgumentException(
                'Módulo no permitido. Módulos válidos: ' . implode(', ', $allowedModules)
            );
        }

        
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('El archivo no puede superar los 10MB');
        }

       
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException(
                'Tipo de archivo no permitido. Solo se permiten JPG, PNG, GIF y PDF.'
            );
        }

        return $this->repository->upload($file, $module, $recordId, $authenticatedUserId, $description);
    }
}