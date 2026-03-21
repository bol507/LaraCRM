<?php

namespace App\Domain\Entities;

/**
 * Class Attachment
 * 
 * Domain entity representing a file attachment in the CRM system.
 * 
 * This entity encapsulates all metadata and access information for files
 * attached to CRM records (accounts, opportunities, quotes, projects, etc.).
 * Attachments are stored in Google Drive with metadata persisted in the
 * local database for fast retrieval and access control.
 * 
 * The entity follows the immutability pattern using readonly properties,
 * ensuring that once created, attachment data cannot be modified. This
 * promotes safer data handling and predictable behavior throughout the
 * application lifecycle.
 * 
 * Key characteristics:
 * - Immutable: All properties are readonly after instantiation
 * - Google Drive integration: Files are stored externally with local metadata
 * - Access control: Module and record ID enforce permission boundaries
 * - Dual URL support: Separate URLs for viewing and direct access
 * - Audit trail: Created timestamp for tracking attachment history
 * 
 * @package App\Domain\Entities
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see \App\Application\Repositories\AttachmentRepositoryInterface For persistence operations
 * @see \App\Infrastructure\Services\GoogleDriveService For file storage integration
 */
class Attachment
{
    

    /**
     * Attachment constructor.
     * 
     * Creates an immutable Attachment entity instance with all required
     * metadata for file management and access control. All properties
     * are marked as readonly to ensure immutability after instantiation,
     * promoting safer data handling and preventing accidental modifications.
     * 
     * The entity is designed to be created by the repository layer after
     * successful file upload to Google Drive and metadata persistence
     * in the local database. Application code should not instantiate
     * this class directly but should use repository methods instead.
     * 
     * @param int $id The unique identifier of the attachment.
     * @param string $name The original filename of the uploaded attachment.
     * @param string|null $description Optional description or notes about the attachment.
     * @param string $mimeType The MIME type of the uploaded file.
     * @param int $size The size of the attachment in bytes.
     * @param string $googleDriveId The Google Drive file ID for the stored attachment.
     * @param string $url The direct download URL for the attachment.
     * @param string $viewUrl The preview/view URL for the attachment.
     * @param int $relatedRecordId The ID of the CRM record to which this attachment is related.
     * @param string $module The CRM module name to which this attachment belongs.
     * @param \DateTimeImmutable $createdAt The timestamp when the attachment was created.
     * 
     * @return void
     */
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

    /**
     * Get the file size formatted for human-readable display.
     * 
     * Converts the byte size to a formatted string with appropriate
     * units (B, KB, MB, GB) for display in the user interface.
     * 
     * @return string The formatted file size (e.g., "2.5 MB", "150 KB").
     * 
     * @example
     * // Display file size in UI
     * echo $attachment->getFormattedSize(); // Outputs: "2.5 MB"
     * 
     * @example
     * // Use in template
     * <span class="file-size"><?= htmlspecialchars($attachment->getFormattedSize()) ?></span>
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $size, $units[$unitIndex]);
    }

    /**
     * Check if the attachment is a previewable file type.
     * 
     * Determines whether the attachment's MIME type is supported by
     * Google Drive's web viewer for inline preview. This is used to
     * conditionally display preview buttons or embed viewers in the UI.
     * 
     * @return bool True if the file can be previewed in Google Drive,
     *              false otherwise.
     * 
     * @example
     * // Conditionally show preview button
     * if ($attachment->isPreviewable()) {
     *     echo '<a href="' . $attachment->viewUrl . '" target="_blank">Preview</a>';
     * }
     * 
     * @example
     * // Filter attachments by preview capability
     * $previewable = array_filter($attachments, fn($a) => $a->isPreviewable());
     */
    public function isPreviewable(): bool
    {
        $previewableTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        return in_array($this->mimeType, $previewableTypes, true);
    }

    /**
     * Check if the attachment is an image file.
     * 
     * Determines whether the attachment's MIME type indicates an image
     * format. This is used for displaying image thumbnails, applying
     * image-specific UI treatments, or filtering attachments by type.
     * 
     * @return bool True if the file is an image, false otherwise.
     * 
     * @example
     * // Display image thumbnail
     * if ($attachment->isImage()) {
     *     echo '<img src="' . $attachment->url . '" alt="' . htmlspecialchars($attachment->name) . '">';
     * }
     * 
     * @example
     * // Filter for image attachments only
     * $images = array_filter($attachments, fn($a) => $a->isImage());
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Get the file extension from the filename.
     * 
     * Extracts and returns the file extension (including the leading dot)
     * from the attachment name. Returns an empty string if no extension
     * is present. This is useful for displaying file type icons or
     * filtering attachments by extension.
     * 
     * @return string The file extension (e.g., ".pdf", ".xlsx") or empty string.
     * 
     * @example
     * // Display file extension badge
     * echo '<span class="badge">' . strtoupper($attachment->getExtension()) . '</span>';
     * 
     * @example
     * // Filter by extension
     * $pdfs = array_filter($attachments, fn($a) => $a->getExtension() === '.pdf');
     */
    public function getExtension(): string
    {
        $extension = pathinfo($this->name, PATHINFO_EXTENSION);
        return $extension !== '' ? '.' . strtolower($extension) : '';
    }

    /**
     * Convert the entity to an array for JSON serialization.
     * 
     * Prepares the attachment data for transmission in API responses.
     * The returned array uses snake_case keys to conform to common JSON
     * API conventions and includes all public properties of the entity.
     * The createdAt timestamp is formatted as an ISO 8601 string.
     * 
     * @return array<string, mixed> An associative array containing all
     *                              entity properties formatted for JSON.
     * 
     * @example
     * // Convert to array for API response
     * $responseData = $attachment->toArray();
     * 
     * @example
     * // Use in controller
     * return response()->json($attachment->toArray());
     * 
     * @example
     * // Response structure
     * // Returns:
     * // [
     * //     'id' => 123,
     * //     'name' => 'contract.pdf',
     * //     'description' => 'Signed contract',
     * //     'mime_type' => 'application/pdf',
     * //     'size' => 2048576,
     * //     'formatted_size' => '2.0 MB',
     * //     'google_drive_id' => '1ABC...',
     * //     'url' => 'https://drive.google.com/uc?export=download&id=...',
     * //     'view_url' => 'https://drive.google.com/file/d/.../view',
     * //     'related_record_id' => 456,
     * //     'module' => 'Accounts',
     * //     'created_at' => '2026-03-18T10:30:00+00:00',
     * //     'is_previewable' => true,
     * //     'is_image' => false,
     * //     'extension' => '.pdf'
     * // ]
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'formatted_size' => $this->getFormattedSize(),
            'google_drive_id' => $this->googleDriveId,
            'url' => $this->url,
            'view_url' => $this->viewUrl,
            'related_record_id' => $this->relatedRecordId,
            'module' => $this->module,
            'created_at' => $this->createdAt->format(\DateTimeImmutable::ATOM),
            'is_previewable' => $this->isPreviewable(),
            'is_image' => $this->isImage(),
            'extension' => $this->getExtension(),
        ];
    }
}