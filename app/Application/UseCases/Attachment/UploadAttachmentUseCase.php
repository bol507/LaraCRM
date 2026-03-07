<?php

namespace App\Application\UseCases\Attachment;

use App\Application\Repositories\AttachmentRepositoryInterface;
use App\Domain\Entities\Attachment;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Upload Attachment Use Case
 * 
 * Orchestrates the validation and upload of file attachments to CRM entities.
 * 
 * Responsibilities:
 * - Validate that the target module is permitted to receive attachments
 * - Validate uploaded file against size and MIME type constraints
 * - Delegate persistence and storage operations to the repository layer
 * - Return the created Attachment entity on success
 * 
 * This use case is part of the Application layer and should not contain:
 * - HTTP-specific logic (request handling, response formatting)
 * - Storage implementation details (local filesystem, Google Drive, S3, etc.)
 * - Database queries or entity mapping logic
 * 
 * Business rules enforced:
 * - Only whitelisted modules can receive attachments (configurable)
 * - Maximum file size: 10MB (configurable)
 * - Allowed MIME types: images, PDFs, and Office documents (configurable)
 * - All validation failures throw InvalidArgumentException with descriptive messages
 * 
 * @package App\Application\UseCases\Attachment
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\AttachmentRepositoryInterface
 * @see \App\Domain\Entities\Attachment
 * @see \App\Http\Controllers\Api\AttachmentController
 */
class UploadAttachmentUseCase
{
    /**
     * List of module names that are permitted to have attachments
     * 
     * Modules correspond to Vtiger CRM entity types. This list should be
     * kept in sync with the modules that have been tested and verified
     * to support the attachment workflow.
     * 
     * @var array<string>
     */
    private const ALLOWED_MODULES = [
        'Project',
        'Quote',
        'Opportunity',
        'Account',
        'Contact',
        'Calendar',
        'Task',
    ];

    /**
     * Maximum allowed file size in bytes (10MB)
     * 
     * This limit protects against denial-of-service attacks via large file uploads
     * and ensures reasonable storage consumption. Adjust based on infrastructure
     * capacity and business requirements.
     * 
     * @var int
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * List of permitted MIME types for uploaded files
     * 
     * Only files with MIME types in this whitelist will be accepted.
     * This list balances security (preventing executable uploads) with
     * practical business needs (documents, images, spreadsheets).
     * 
     * @var array<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    /**
     * Attachment repository for persistence operations
     * 
     * @var AttachmentRepositoryInterface
     */
    private readonly AttachmentRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param AttachmentRepositoryInterface $repository Repository implementation for attachment persistence
     */
    public function __construct(AttachmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the upload attachment use case
     * 
     * Validates the uploaded file and module, then delegates to the repository
     * for storage and metadata persistence.
     * 
     * Validation steps:
     * 1. Verify module is in the allowed list
     * 2. Verify file size does not exceed maximum
     * 3. Verify file MIME type is in the allowed list
     * 4. Delegate to repository for storage and entity creation
     * 
     * @param UploadedFile $file The uploaded file from HTTP request
     * @param string $module The module type receiving the attachment (e.g., 'Calendar', 'Project')
     * @param int $recordId The ID of the record in the target module
     * @param int $authenticatedUserId The ID of the user performing the upload
     * @param string|null $description Optional description for the attachment
     * 
     * @return Attachment The created attachment entity with assigned ID and metadata
     * 
     * @throws InvalidArgumentException If module is not allowed, file exceeds size limit, or MIME type is not permitted
     * @throws \RuntimeException If repository operation fails (storage error, database error, etc.)
     * 
     * @example
     * // Upload a PDF to a task
     * $useCase = new UploadAttachmentUseCase($repository);
     * $attachment = $useCase->execute(
     *     file: $uploadedFile,
     *     module: 'Calendar',
     *     recordId: 123,
     *     authenticatedUserId: 456,
     *     description: 'Project proposal document'
     * );
     * 
     * @example
     * // Upload an image to a project
     * $attachment = $useCase->execute(
     *     file: $imageFile,
     *     module: 'Project',
     *     recordId: 789,
     *     authenticatedUserId: 456
     * );
     */
    public function execute(
        UploadedFile $file,
        string $module,
        int $recordId,
        int $authenticatedUserId,
        ?string $description = null
    ): Attachment {
        // Validate module is permitted
        $this->validateModule($module);

        // Validate file size
        $this->validateFileSize($file);

        // Validate MIME type
        $this->validateMimeType($file);

        // Delegate to repository for storage and persistence
        return $this->repository->upload(
            $file,
            $module,
            $recordId,
            $authenticatedUserId,
            $description
        );
    }

    /**
     * Validate that the target module is permitted to receive attachments
     * 
     * @param string $module Module name to validate
     * @return void
     * @throws InvalidArgumentException If module is not in the allowed list
     */
    private function validateModule(string $module): void
    {
        if (!in_array($module, self::ALLOWED_MODULES, true)) {
            throw new InvalidArgumentException(
                'Module not allowed. Valid modules: ' . implode(', ', self::ALLOWED_MODULES)
            );
        }
    }

    /**
     * Validate that the uploaded file does not exceed the maximum size limit
     * 
     * @param UploadedFile $file File to validate
     * @return void
     * @throws InvalidArgumentException If file size exceeds maximum
     */
    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'File size exceeds maximum limit of 10MB'
            );
        }
    }

    /**
     * Validate that the uploaded file has an allowed MIME type
     * 
     * @param UploadedFile $file File to validate
     * @return void
     * @throws InvalidArgumentException If MIME type is not in the allowed list
     */
    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException(
                'File type not allowed. Allowed types: JPEG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, TXT'
            );
        }
    }
}