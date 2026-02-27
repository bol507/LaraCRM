<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Attachment\UploadAttachmentUseCase;
use App\Application\UseCases\Attachment\ListAttachmentsUseCase;
use App\Application\UseCases\Attachment\DeleteAttachmentUseCase;
use App\Application\DTOs\AttachmentDto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly UploadAttachmentUseCase $uploadAttachmentUseCase,
        private readonly ListAttachmentsUseCase $listAttachmentsUseCase,
        private readonly DeleteAttachmentUseCase $deleteAttachmentUseCase
    ) {}

    private function getAuthenticatedUser(Request $request): object
    {
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            throw new \Exception('Usuario no autenticado', 401);
        }
        return $user;
    }

    /**
     * Subir archivo adjunto
     */
    public function upload(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->id;

            $request->validate([
                'file' => 'required|file|max:10240',
                'description' => 'nullable|string|max:500',
            ]);

            $attachment = $this->uploadAttachmentUseCase->execute(
                $request->file('file'),
                $module,
                $recordId,
                $authenticatedUserId,
                $request->description
            );

            // ✅ SIEMPRE usar enteros explícitos, NUNCA variables
            return response()->json([
                'message' => 'Archivo subido exitosamente',
                'data' => AttachmentDto::fromEntity($attachment)
            ], 201); // ✅ 201 es entero literal

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validación fallida',
                'messages' => $e->errors()
            ], 422); // ✅ 422 es entero literal

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage()
            ], 422); // ✅ 422 es entero literal

        } catch (\Exception $e) {
            Log::error('Error al subir archivo: ' . $e->getMessage(), [
                'module' => $module,
                'recordId' => $recordId,
                'user' => $authenticatedUser->id ?? 'unknown'
            ]);
            
            // ✅ SIEMPRE 500 para errores inesperados (entero literal)
            return response()->json([
                'error' => 'Error interno del servidor al procesar el archivo'
            ], 500); // ✅ 500 es entero literal
        }
    }

    /**
     * Listar archivos adjuntos
     */
    public function index(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            $this->getAuthenticatedUser($request);
            $attachments = $this->listAttachmentsUseCase->execute($module, $recordId);
            
            $data = array_map(fn($att) => AttachmentDto::fromEntity($att), $attachments);

            return response()->json(['data' => $data], 200); // ✅ 200 es entero literal

        } catch (\Exception $e) {
            Log::error('Error al listar archivos: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener la lista de archivos'
            ], 500); // ✅ 500 es entero literal
        }
    }

    /**
     * Eliminar archivo adjunto
     */
    public function destroy(Request $request, int $attachmentId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->id;

            $success = $this->deleteAttachmentUseCase->execute(
                $attachmentId,
                $authenticatedUserId
            );

            if (!$success) {
                return response()->json([
                    'error' => 'Archivo no encontrado'
                ], 404); // ✅ 404 es entero literal
            }

            return response()->json([
                'message' => 'Archivo eliminado exitosamente'
            ], 200); // ✅ 200 es entero literal

        } catch (\Exception $e) {
            Log::error('Error al eliminar archivo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar el archivo'
            ], 500); // ✅ 500 es entero literal
        }
    }
}