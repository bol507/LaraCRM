<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\AttachmentRepositoryInterface;
use App\Domain\Entities\Attachment;
use App\Services\GoogleDriveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class VtigerAttachmentRepository implements AttachmentRepositoryInterface
{
    public function __construct(
        private readonly GoogleDriveService $driveService
    ) {}

    public function upload(
        UploadedFile $file,
        string $module,
        int $recordId,
        int $authenticatedUserId, // ✅ Usuario autenticado desde middleware
        ?string $description = null
    ): Attachment {
        return DB::connection('vtiger')->transaction(function () use ($file, $module, $recordId, $authenticatedUserId, $description) {
            // ✅ Subir a Google Drive
            $driveResult = $this->driveService->uploadFile($file, $module, $recordId);

            // ✅ Insertar en vtiger_crmentity
            $maxCrmid = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->max('crmid');

            $crmid = $maxCrmid ? $maxCrmid + 1 : 1;

            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $crmid,
                'smcreatorid' => $authenticatedUserId, // ✅ Usar ID del usuario autenticado
                'smownerid' => $authenticatedUserId,   // ✅ Usar ID del usuario autenticado
                'setype' => 'Documents',
                'description' => $description,
                'createdtime' => now()->format('Y-m-d H:i:s'),
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);

            // ✅ Insertar en vtiger_attachments
            DB::connection('vtiger')->table('vtiger_attachments')->insert([
                'attachmentsid' => $crmid,
                'name' => $driveResult['name'],
                'description' => $description,
                'type' => $driveResult['mime_type'],
                'path' => $driveResult['id'], // ✅ ID de Google Drive
                'filesize' => $driveResult['size'] ?? 0,
            ]);

            // ✅ Relacionar con el módulo
            DB::connection('vtiger')->table('vtiger_seattachmentsrel')->insert([
                'crmid' => $recordId,
                'attachmentsid' => $crmid,
            ]);

            // ✅ Retornar entidad
            return new Attachment(
                id: $crmid,
                name: $driveResult['name'],
                description: $description,
                mimeType: $driveResult['mime_type'],
                size: $driveResult['size'] ?? 0,
                googleDriveId: $driveResult['id'],
                url: $driveResult['url'],
                viewUrl: $driveResult['view_url'],
                relatedRecordId: $recordId,
                module: $module,
                createdAt: new \DateTimeImmutable()
            );
        });
    }

    public function findByModuleAndRecord(string $module, int $recordId): array
    {
        // ✅ Obtener IDs de attachments relacionados
        $attachmentIds = DB::connection('vtiger')
            ->table('vtiger_seattachmentsrel')
            ->where('crmid', $recordId)
            ->pluck('attachmentsid')
            ->toArray();

        if (empty($attachmentIds)) {
            return [];
        }

        // ✅ Obtener detalles de los attachments
        $attachments = DB::connection('vtiger')
            ->table('vtiger_attachments')
            ->join('vtiger_crmentity', 'vtiger_attachments.attachmentsid', '=', 'vtiger_crmentity.crmid')
            ->whereIn('vtiger_attachments.attachmentsid', $attachmentIds)
            ->where('vtiger_crmentity.deleted', 0)
            ->select(
                'vtiger_attachments.attachmentsid',
                'vtiger_attachments.name',
                'vtiger_attachments.description',
                'vtiger_attachments.type as mime_type',
                'vtiger_attachments.path as google_drive_id',
                'vtiger_attachments.filesize as size',
                'vtiger_crmentity.createdtime'
            )
            ->get();

        // ✅ Obtener URLs de Google Drive para cada archivo
        $result = [];
        foreach ($attachments as $att) {
            try {
                $files = $this->driveService->listFiles($module, $recordId);
                $file = collect($files)->first(fn($f) => $f->id === $att->google_drive_id);
                
                if ($file) {
                    $result[] = new Attachment(
                        id: $att->attachmentsid,
                        name: $att->name,
                        description: $att->description,
                        mimeType: $att->mime_type,
                        size: $att->size,
                        googleDriveId: $att->google_drive_id,
                        url: $file->webContentLink ?? $file->webViewLink,
                        viewUrl: $file->webViewLink,
                        relatedRecordId: $recordId,
                        module: $module,
                        createdAt: new \DateTimeImmutable($att->createdtime)
                    );
                }
            } catch (\Exception $e) {
                // ✅ Ignorar archivos que no se pueden encontrar en Drive
                continue;
            }
        }

        return $result;
    }

    public function delete(int $attachmentId, int $authenticatedUserId): bool
    {
        return DB::connection('vtiger')->transaction(function () use ($attachmentId, $authenticatedUserId) {
            // ✅ Verificar que el usuario tenga permisos para eliminar
            $attachment = DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $attachmentId)
                ->where('deleted', 0)
                ->first();

            if (!$attachment) {
                throw new \Exception('Attachment no encontrado o ya eliminado');
            }

            // ✅ Obtener ID de Google Drive antes de eliminar
            $driveAttachment = DB::connection('vtiger')
                ->table('vtiger_attachments')
                ->where('attachmentsid', $attachmentId)
                ->first();

            if ($driveAttachment) {
                // ✅ Eliminar de Google Drive
                $this->driveService->deleteFile($driveAttachment->path);
            }

            // ✅ Marcar como eliminado en vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $attachmentId)
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                ]);

            return true;
        });
    }
}