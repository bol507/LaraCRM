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
 * - List attachments for a specific record (project, quote, etc.)
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
     * Create a new AttachmentController instance
     * 
     * @param UploadAttachmentUseCase $uploadAttachmentUseCase Use case for uploading attachments
     * @param ListAttachmentsUseCase $listAttachmentsUseCase Use case for listing attachments
     * @param DeleteAttachmentUseCase $deleteAttachmentUseCase Use case for deleting attachments
     */
    public function __construct(
        private readonly UploadAttachmentUseCase $uploadAttachmentUseCase,
        private readonly ListAttachmentsUseCase $listAttachmentsUseCase,
        private readonly DeleteAttachmentUseCase $deleteAttachmentUseCase
    ) {}

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

    /**
     * Upload a new attachment
     * 
     * Handles file upload to Google Drive and persists metadata to the database.
     * 
     * @param Request $request HTTP request containing file and optional description
     * @param string $module Module type (e.g., 'Project', 'Quotes', 'Calendar')
     * @param int $recordId ID of the related record
     * 
     * @return JsonResponse
     * 
     * @response 201 {
     *   "message": "File uploaded successfully",
     *   "data": { AttachmentDto }
     * }
     * @response 401 { "error": "User not authenticated" }
     * @response 422 { "error": "Validation failed", "messages": { field: [errors] } }
     * @response 500 { "error": "Internal server error processing file" }
     * 
     * @throws ValidationException If request validation fails
     * @throws \InvalidArgumentException If business validation fails
     * @throws \Exception If upload operation fails
     */
    public function upload(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->getId();

            //  Validate incoming request data
            $request->validate([
                'file' => 'required|file|max:10240',        // Max 10MB
                'description' => 'nullable|string|max:500',  // Optional, max 500 chars
            ]);

            //  Execute upload use case
            $attachment = $this->uploadAttachmentUseCase->execute(
                $request->file('file'),
                $module,
                $recordId,
                $authenticatedUserId,
                $request->description
            );

            //  Always use explicit integer literals for HTTP status codes
            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => AttachmentDto::fromEntity($attachment)
            ], 201); //  201 = Created

        } catch (ValidationException $e) {
            //  Handle Laravel validation errors
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422); //  422 = Unprocessable Entity

        } catch (\InvalidArgumentException $e) {
            //  Handle business rule validation errors
            return response()->json([
                'error' => 'Validation error: ' . $e->getMessage()
            ], 422); //  422 = Unprocessable Entity

        } catch (\Exception $e) {
            //  Log unexpected errors with context for debugging
            Log::error('Error uploading file: ' . $e->getMessage(), [
                'module' => $module,
                'recordId' => $recordId,
                'userId' => $authenticatedUser->getId() ?? 'unknown'
            ]);
            
            //  Always return 500 for unexpected server errors (explicit integer literal)
            return response()->json([
                'error' => 'Internal server error processing file'
            ], 500); //  500 = Internal Server Error
        }
    }

    /**
     * List attachments for a specific record
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
     * 
     * @throws \Exception If listing operation fails
     */
    public function index(Request $request, string $module, int $recordId): JsonResponse
    {
        try {
            //  Verify user authentication
            $this->getAuthenticatedUser($request);
            
            //  Execute list use case
            $attachments = $this->listAttachmentsUseCase->execute($module, $recordId);
            
            //  Map entities to DTOs for API response
            $data = array_map(fn($att) => AttachmentDto::fromEntity($att), $attachments);

            return response()->json(['data' => $data], 200); //  200 = OK

        } catch (\Exception $e) {
            //  Log error and return generic error message
            Log::error('Error listing attachments: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error retrieving attachment list'
            ], 500); //  500 = Internal Server Error
        }
    }

    /**
     * Delete an attachment
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
     * 
     * @throws \Exception If delete operation fails
     */
    public function destroy(Request $request, int $attachmentId): JsonResponse
    {
        try {
            $authenticatedUser = $this->getAuthenticatedUser($request);
            $authenticatedUserId = $authenticatedUser->getId();

            //  Execute delete use case
            $success = $this->deleteAttachmentUseCase->execute(
                $attachmentId,
                $authenticatedUserId
            );

            if (!$success) {
                //  Return 404 if attachment was not found
                return response()->json([
                    'error' => 'File not found'
                ], 404); //  404 = Not Found
            }

            return response()->json([
                'message' => 'File deleted successfully'
            ], 200); //  200 = OK

        } catch (\Exception $e) {
            //  Log error and return generic error message
            Log::error('Error deleting file: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error deleting file'
            ], 500); //  500 = Internal Server Error
        }
    }
}