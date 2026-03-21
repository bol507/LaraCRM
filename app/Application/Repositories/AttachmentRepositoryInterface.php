<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Attachment;
use Illuminate\Http\UploadedFile;

/**
 * Interface AttachmentRepositoryInterface
 * 
 * Defines the contract for attachment file management operations in the CRM system.
 * 
 * This interface abstracts the data persistence and file storage layer for attachment
 * entities, allowing for interchangeable implementations (e.g., local filesystem,
 * cloud storage like S3, database BLOB storage) without affecting the business logic
 * layer that depends on this contract.
 * 
 * The repository pattern provides a clean separation between domain logic and
 * file handling logic, promoting testability, maintainability, and adherence to
 * the Dependency Inversion Principle. Implementations should handle file validation,
 * secure storage, metadata persistence, and access control.
 * 
 * @package App\Application\Repositories
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see Attachment For the domain entity this repository manages
 * @see \App\Infrastructure\Repositories\Vtiger\VtigerAttachmentRepository For the Vtiger-specific implementation
 * @see \Illuminate\Http\UploadedFile For the Laravel file upload handling class
 */
interface AttachmentRepositoryInterface
{
    /**
     * Upload and persist a new attachment file.
     * 
     * This method handles the complete file upload workflow including:
     * - Validating the uploaded file (type, size, integrity)
     * - Generating a secure unique filename to prevent conflicts
     * - Storing the file in the configured storage location
     * - Persisting attachment metadata in the database (vtiger_seattachmentsrel,
     *   vtiger_attachments, vtiger_crmentity)
     * - Setting audit information (creator, timestamps)
     * - Creating the relationship between the attachment and the target record
     * 
     * The method ensures that attachments are properly linked to their parent
     * module and record, enabling efficient retrieval and access control based
     * on the parent record's permissions.
     * 
     * @param UploadedFile $file The uploaded file instance from the HTTP request.
     *                           Must be a valid uploaded file with proper MIME type
     *                           and within configured size limits.
     * @param string $module The CRM module name to which the attachment belongs
     *                       (e.g., "Accounts", "Leads", "Potentials", "Quotes").
     *                       This determines the relationship table and access rules.
     * @param int $recordId The unique identifier of the parent record in the
     *                      specified module to which this attachment is related.
     * @param int $authenticatedUserId The ID of the currently authenticated user
     *                                 performing the upload, used for audit tracking
     *                                 (smcreatorid, smownerid fields).
     * @param string|null $description Optional description or notes about the
     *                                 attachment for display purposes in the UI.
     * 
     * @return Attachment The newly created Attachment entity with all persisted
     *                    properties including the generated attachment ID, file
     *                    path, and relationship metadata.
     * 
     * @throws \InvalidArgumentException If the file is invalid, the module name
     *                                   is not recognized, or the record ID does
     *                                   not exist.
     * @throws \Illuminate\Filesystem\FileNotFoundException If the uploaded file
     *                                                      cannot be read or moved.
     * @throws \RuntimeException If the database transaction fails or file storage
     *                           operation encounters an error.
     * @throws \Exception If any unexpected error occurs during the upload process.
     * 
     * @example
     * // Upload a file to an Account record
     * $file = $request->file('attachment');
     * $attachment = $repository->upload(
     *     file: $file,
     *     module: 'Accounts',
     *     recordId: 123,
     *     authenticatedUserId: $request->user()->id,
     *     description: 'Contract signed document'
     * );
     * 
     * @example
     * // Return attachment ID for frontend reference
     * return response()->json([
     *     'attachment_id' => $attachment->getId(),
     *     'filename' => $attachment->getFilename()
     * ]);
     * 
     * @see UploadedFile::isValid() For file validation
     * @see \Illuminate\Support\Facades\Storage For file storage operations
     * @see Attachment::class For the returned entity structure
     */
    public function upload(
        UploadedFile $file,
        string $module,
        int $recordId,
        int $authenticatedUserId,
        ?string $description = null
    ): Attachment;

    /**
     * Retrieve all attachments related to a specific module and record.
     * 
     * This method fetches all attachment entities that are linked to the
     * specified module and record ID through the vtiger_seattachmentsrel
     * relationship table. It includes file metadata, creator information,
     * and timestamps for display in attachment lists.
     * 
     * The method respects soft-deletion by excluding attachments where the
     * deleted flag is set in the vtiger_crmentity table. Results are ordered
     * by creation time descending to show recent attachments first.
     * 
     * @param string $module The CRM module name to filter attachments by
     *                       (e.g., "Accounts", "Leads", "Potentials").
     * @param int $recordId The unique identifier of the parent record in the
     *                      specified module whose attachments should be retrieved.
     * 
     * @return array<int, Attachment> An array of Attachment entities related to
     *                                the specified module and record, ordered by
     *                                creation time descending. Returns an empty
     *                                array if no attachments are found.
     * 
     * @throws \RuntimeException If the database query fails or relationship
     *                           tables cannot be accessed.
     * 
     * @example
     * // Get all attachments for an Account
     * $attachments = $repository->findByModuleAndRecord('Accounts', 123);
     * 
     * @example
     * // Display attachments in frontend
     * foreach ($attachments as $attachment) {
     *     echo $attachment->getFilename();
     *     echo $attachment->getCreatedtime();
     * }
     * 
     * @example
     * // Check if record has attachments
     * if (empty($attachments)) {
     *     echo 'No attachments found';
     * }
     * 
     * @see Attachment::class For the entity structure returned
     * @see vtiger_seattachmentsrel For the relationship table used in the query
     */
    public function findByModuleAndRecord(string $module, int $recordId): array;

    /**
     * Delete an attachment and its associated file.
     * 
     * This method performs a complete attachment deletion workflow including:
     * - Verifying that the attachment exists and is not already deleted
     * - Checking that the authenticated user has permission to delete the
     *   attachment (based on ownership or administrative privileges)
     * - Removing the physical file from the storage location
     * - Updating the deleted flag in vtiger_crmentity (soft delete)
     * - Removing relationship records from vtiger_seattachmentsrel
     * - Logging the deletion action for audit purposes
     * 
     * The method uses soft deletion to preserve audit history and allow for
     * potential restoration. Hard deletion (permanent removal) should be
     * handled by a separate maintenance process or administrative function.
     * 
     * @param int $attachmentId The unique identifier (attachmentsid) of the
     *                          attachment to delete.
     * @param int $authenticatedUserId The ID of the currently authenticated user
     *                                 requesting the deletion, used for permission
     *                                 verification and audit tracking.
     * 
     * @return bool True if the deletion was successful, false if the attachment
     *              was not found, already deleted, or the user lacks permission.
     * 
     * @throws \InvalidArgumentException If the attachment ID is invalid or
     *                                   the user ID is not provided.
     * @throws \Illuminate\Auth\Access\AuthorizationException If the user does
     *                                                        not have permission
     *                                                        to delete the attachment.
     * @throws \RuntimeException If the database update fails or file deletion
     *                           encounters an error.
     * @throws \Exception If any unexpected error occurs during the deletion process.
     * 
     * @example
     * // Delete an attachment
     * $success = $repository->delete(456, $request->user()->id);
     * 
     * @example
     * // Handle deletion result
     * if ($success) {
     *     toast()->success('Attachment deleted successfully');
     *     return redirect()->back();
     * } else {
     *     toast()->error('Failed to delete attachment');
     * }
     * 
     * @example
     * // Confirm before deletion in frontend
     * if (confirm('Are you sure you want to delete this attachment?')) {
     *     // Call delete endpoint
     * }
     * 
     * @see Attachment::isActive For checking if an attachment is soft-deleted
     * @see \Illuminate\Support\Facades\Storage For file deletion operations
     */
    public function delete(int $attachmentId, int $authenticatedUserId): bool;
}