<?php

namespace App\Application\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Contract for Google Drive operations.
 * Allows swapping between Service Account and OAuth implementations.
 */
interface GoogleDriveServiceInterface
{
    /**
     * Upload a file to Google Drive
     * 
     * @param UploadedFile $file File to upload
     * @param string $module Module name (Project, Quote, etc.)
     * @param int $recordId Record ID for folder organization
     * @return array File metadata: id, name, url, view_url, size, mime_type
     */
    public function uploadFile(UploadedFile $file, string $module, int $recordId): array;

    /**
     * List files for a specific record
     * 
     * @param string $module Module name
     * @param int $recordId Record ID
     * @return array Array of file objects from Google Drive API
     */
    public function listFiles(string $module, int $recordId): array;

    /**
     * Delete a file from Google Drive
     * 
     * @param string $fileId Google Drive file ID
     * @return bool True if deletion was successful
     */
    public function deleteFile(string $fileId): bool;

    /**
     * Get the current authentication type (for debugging/logging)
     * 
     * @return string 'service_account' or 'oauth'
     */
    public function getAuthType(): string;
}