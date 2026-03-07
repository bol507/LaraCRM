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

/**
 * Attachment Controller
 * 
 * Handles HTTP requests for attachment management operations.
 * 
 * Responsibilities:
 * - Upload new attachments to Google Drive and persist metadata
 * - List attachments for a specific record (project, quote, task, etc.)
 * - Delete attachments with proper authorization checks
 * 
 * All responses follow JSON:API convention with standardized error handling.
 * 
 * @package App\Http\Controllers\Api
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\UseCases\Attachment\UploadAttachmentUseCase
 * @see \App\Application\UseCases\Attachment\ListAttachmentsUseCase
 * @see \App\Application\UseCases\Attachment\DeleteAttachmentUseCase
 */
class AttachmentController extends Controller
{
    /**
     * Use case for uploading attachments
     * 
     * @var UploadAttachmentUseCase
     */
    private readonly UploadAttachmentUseCase $uploadAttachmentUseCase;

    /**
     * Use case for listing attachments
     * 
     * @var ListAttachmentsUseCase
     */
    private readonly ListAttachmentsUseCase $listAttachmentsUseCase;

    /**
     * Use case for deleting attachments
     * 
     * @var DeleteAttachmentUseCase
     */
    private readonly DeleteAttachmentUseCase $deleteAttachmentUseCase;

    /**
     * Create a new AttachmentController instance
     * 
     * @param UploadAttachmentUseCase $uploadAttachmentUseCase Use case for uploading attachments
     * @param ListAttachmentsUseCase $listAttachmentsUseCase Use case for listing attachments
     * @param DeleteAttachmentUseCase $deleteAttachmentUseCase Use case for deleting attachments
     */
    public function __construct(
        UploadAttachmentUseCase $uploadAttachmentUseCase,
        ListAttachmentsUseCase $listAttachmentsUseCase,
        DeleteAttachmentUseCase $deleteAttachmentUseCase
    ) {
        $this->uploadAttachmentUseCase = $uploadAttachmentUseCase;
        $this->listAttachmentsUseCase = $listAttachmentsUseCase;
        $this->deleteAttachmentUseCase = $deleteAttachmentUseCase;
    }

    /**
     * Get authenticated user from request attributes
     * 
     * @param Request $request HTTP request containing auth_user attribute
     * @return object Authenticated user entity
     * 
     * @throws \Exception If user is not authenticated (HTTP 401)
     */
    private function getAuthenticatedUser(Request $request): object
    {
        $user = $request->attributes->get('auth_user');
        
        if (!$user) {
            throw new \Exception('User not authenticated', 401);
        }
        
        return $user;
    }

    // ========================================================================
    // GENERIC ATTACHMENT METHODS (for any module: /api/attachments/{module}/{recordId})
    // ========================================================================

    /**
     * Upload a new attachment (generic endpoint)
     * 
     * POST /api/attachments/{module}/{recordId}
     * 
     * Handles file upload to Google Drive and persists metadata to the database.
     * 
     * @param Request $request HTTP request containing file and optional description
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $recordId ID of the related record
     * 
     * @return JsonResponse
     * 
     * @response 201 { "message": "File uploaded successfully", "data": { AttachmentDto } }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": {...} }
     * @response 500 { "error": "Internal server error processing file" }
     */
    public function upload(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->getId();

            // Validate incoming request data
            $request->validate([
                'file' => 'required|file|max:10240',        // Max 10MB
                'description' => 'nullable|string|max:500',  // Optional, max 500 chars
            ]);

            // Execute upload use case
            $attachment = $this->uploadAttachmentUseCase->execute(
                $request->file('file'),
                $module,
                $recordId,
                $authenticatedUserId,
                $request->input('description')
            );

            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => AttachmentDto::fromEntity($attachment)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Validation error: ' . $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error uploading file: ' . $e->getMessage(), [
                'module' => $module,
                'recordId' => $recordId,
                'userId' => $authenticatedUser->getId() ?? 'unknown'
            ]);
            
            return response()->json([
                'error' => 'Internal server error processing file'
            ], 500);
        }
    }

    /**
     * List attachments for a specific record (generic endpoint)
     * 
     * GET /api/attachments/{module}/{recordId}
     * 
     * Retrieves all attachments associated with a module/record combination.
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $recordId ID of the related record
     * 
     * @return JsonResponse
     * 
     * @response 200 { "data": [ AttachmentDto, ... ] }
     * @response 401 { "error": "User not authenticated" }
     * @response 500 { "error": "Error retrieving attachment list" }
     */
    public function index(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            // Verify user authentication
            $this->getAuthenticatedUser($request);
            
            // Execute list use case
            $attachments = $this->listAttachmentsUseCase->execute($module, $recordId);
            
            // Map entities to DTOs for API response
            $data = array_map(fn($att) => AttachmentDto::fromEntity($att), $attachments);

            return response()->json(['data' => $data], 200);

        } catch (\Exception $e) {
            Log::error('Error listing attachments: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error retrieving attachment list'
            ], 500);
        }
    }

    /**
     * Delete an attachment (generic endpoint)
     * 
     * DELETE /api/attachments/{attachmentId}
     * 
     * Removes an attachment from Google Drive and deletes its metadata from the database.
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param int $attachmentId Unique identifier of the attachment to delete
     * 
     * @return JsonResponse
     * 
     * @response 200 { "message": "File deleted successfully" }
     * @response 401 { "error": "User not authenticated" }
     * @response 404 { "error": "File not found" }
     * @response 500 { "error": "Error deleting file" }
     */
    public function destroy(Request $request, int $attachmentId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->getId();

            // Execute delete use case
            $success = $this->deleteAttachmentUseCase->execute(
                $attachmentId,
                $authenticatedUserId
            );

            if (!$success) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            return response()->json([
                'message' => 'File deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting file: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error deleting file'
            ], 500);
        }
    }

    // ========================================================================
    // TASK-SPECIFIC ATTACHMENT METHODS (Nested routes: /api/tasks/{taskId}/attachments)
    // ========================================================================

    /**
     * Upload an attachment to a specific task
     * 
     * POST /api/tasks/{taskId}/attachments
     * 
     * Wrapper around upload() with module hardcoded to 'Calendar'.
     * 
     * @param Request $request HTTP request containing file and optional description
     * @param int $taskId Task ID (injected from nested route)
     * 
     * @return JsonResponse
     * 
     * @see self::upload()
     */
    public function uploadByTask(Request $request, int $taskId): JsonResponse
    {
        // Delegate to generic method with module = 'Calendar'
        return $this->upload($request, module: 'Calendar', recordId: $taskId);
    }

    /**
     * List attachments for a specific task
     * 
     * GET /api/tasks/{taskId}/attachments
     * 
     * Wrapper around index() with module hardcoded to 'Calendar'.
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param int $taskId Task ID (injected from nested route)
     * 
     * @return JsonResponse
     * 
     * @see self::index()
     */
    public function indexByTask(Request $request, int $taskId): JsonResponse
    {
        // Delegate to generic method with module = 'Calendar'
        return $this->index($request, module: 'Calendar', recordId: $taskId);
    }

    /**
     * Delete an attachment from a task
     * 
     * DELETE /api/tasks/{taskId}/attachments/{attachmentId}
     * 
     * Wrapper around destroy() - taskId is used for authorization context only.
     * 
     * @param Request $request HTTP request (used for authentication)
     * @param int $taskId Task ID (for authorization context)
     * @param int $attachmentId Attachment ID to delete
     * 
     * @return JsonResponse
     * 
     * @see self::destroy()
     */
    public function destroyByTask(Request $request, int $taskId, int $attachmentId): JsonResponse
    {
        // Optional: Verify attachment belongs to this task before deleting
        // For now, delegate to generic destroy (authorization handled in UseCase)
        return $this->destroy($request, $attachmentId);
    }
}