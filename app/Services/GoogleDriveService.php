<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for interacting with Google Drive API.
 * 
 * Handles file uploads, folder management, and permission settings
 * using OAuth 2.0 authentication with refresh token support.
 * 
 * @package App\Services
 * @see https://developers.google.com/drive/api/guides/about-sdk
 */
class GoogleDriveService
{
    /**
     * Google API client instance.
     *
     * @var Client
     */
    private $client;

    /**
     * Google Drive service instance.
     *
     * @var Drive
     */
    private $driveService;

    /**
     * Root folder ID in Google Drive where files will be stored.
     *
     * @var string|null
     */
    private $folderId;

    /**
     * Configuration array containing refresh token and folder ID.
     *
     * @var array|null
     */
    private $config;

    /**
     * Constructor.
     * 
     * Initializes the Google Drive client with OAuth 2.0 credentials
     * and refreshes the access token using the stored refresh token.
     * 
     * @throws \Exception If configuration files are missing or invalid.
     * @throws \Exception If token refresh fails.
     */
    public function __construct()
    {
        // Load configuration from secure storage
        $configPath = storage_path('app/google-drive-config.json');

        if (!file_exists($configPath)) {
            throw new \Exception(
                'Google Drive configuration file not found. ' .
                    'Run: php artisan drive:auth'
            );
        }

        $config = json_decode(file_get_contents($configPath), true);

        if (!$config || !isset($config['refresh_token'])) {
            throw new \Exception(
                'Invalid configuration. Missing "refresh_token" in google-drive-config.json. ' .
                    'Run: php artisan drive:auth'
            );
        }

        // Assign config to class property for later use
        $this->config = $config;

        $this->folderId = $config['folder_id'] ?? null;

        if (!$this->folderId) {
            throw new \Exception(
                'Folder ID not configured. Edit google-drive-config.json and add "folder_id"'
            );
        }

        // Load OAuth credentials
        $credentialsPath = storage_path('app/google-oauth-credentials.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception(
                'OAuth credentials not found. ' .
                    'Download the JSON from Google Cloud Console and save as: google-oauth-credentials.json'
            );
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $clientId = null;
        $clientSecret = null;

        // Extract client_id and client_secret (support both "installed" and "web" formats)
        if (isset($credentials['installed']) && is_array($credentials['installed'])) {
            $clientId = $credentials['installed']['client_id'] ?? null;
            $clientSecret = $credentials['installed']['client_secret'] ?? null;
        } elseif (isset($credentials['web']) && is_array($credentials['web'])) {
            $clientId = $credentials['web']['client_id'] ?? null;
            $clientSecret = $credentials['web']['client_secret'] ?? null;
        }

        if (!$clientId) {
            Log::error('Google OAuth: client_id not found in credentials file', [
                'credentials_keys' => array_keys($credentials),
                'file_path' => $credentialsPath,
            ]);
            throw new \Exception(
                'OAuth client_id not found. Ensure google-oauth-credentials.json ' .
                    'contains "installed.client_id" or "web.client_id"'
            );
        }

        if (!$clientSecret) {
            Log::error('Google OAuth: client_secret not found in credentials file', [
                'credentials_keys' => array_keys($credentials),
                'file_path' => $credentialsPath,
            ]);
            throw new \Exception(
                'OAuth client_secret not found. Ensure google-oauth-credentials.json ' .
                    'contains "installed.client_secret" or "web.client_secret"'
            );
        }

        Log::info('Google OAuth credentials loaded successfully', [
            'client_id_prefix' => substr($clientId, 0, 20) . '...',
        ]);

        // Configure OAuth client
        $this->client = new Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $this->client->addScope([Drive::DRIVE_FILE, Drive::DRIVE_METADATA_READONLY]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        // Refresh access token using refresh token
        try {
            $this->client->fetchAccessTokenWithRefreshToken($config['refresh_token']);

            if ($this->client->isAccessTokenExpired()) {
                throw new \Exception('Could not renew access token with refresh token');
            }
        } catch (\Google\Exception $e) {
            Log::error('Google API exception during token refresh: ' . $e->getMessage(), [
                'error_code' => $e->getCode(),
                'error_details' => $e->getTraceAsString(),
            ]);
            throw new \Exception(
                'Google Drive authentication failed. ' .
                    'Run: php artisan drive:auth to renew credentials'
            );
        } catch (\Exception $e) {
            Log::error('Generic exception during token refresh: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception(
                'Google Drive authentication failed: ' . $e->getMessage()
            );
        }

        // Initialize Drive service
        $this->driveService = new Drive($this->client);
    }

    /**
     * Refresh access token using the stored refresh token.
     * 
     * This method should be called when an API request fails due to
     * expired or invalid credentials.
     * 
     * @return void
     * @throws \Exception If token refresh fails.
     */
    private function refreshAccessToken(): void
    {
        if (!$this->config || !isset($this->config['refresh_token'])) {
            throw new \Exception('Cannot refresh token: refresh_token not available in config');
        }

        try {
            $this->client->fetchAccessTokenWithRefreshToken($this->config['refresh_token']);

            if ($this->client->isAccessTokenExpired()) {
                throw new \Exception('Access token expired and could not be renewed');
            }
        } catch (\Google\Exception $e) {
            Log::error('Google API exception during token refresh: ' . $e->getMessage(), [
                'error_code' => $e->getCode(),
            ]);
            throw new \Exception(
                'Could not authenticate with Google Drive. ' .
                    'Verify configuration or run: php artisan drive:auth'
            );
        }
    }

    /**
     * Upload a file to Google Drive.
     * 
     * Creates necessary folder structure and sets public read permissions
     * for the uploaded file.
     * 
     * @param UploadedFile $file The file to upload.
     * @param string $module The module name (e.g., "Project", "Quote").
     * @param int $recordId The ID of the record this file belongs to.
     * @return array File metadata including ID, URLs, and size.
     * @throws \Exception If file validation fails or upload encounters an error.
     */
    public function uploadFile(UploadedFile $file, string $module, int $recordId): array
    {
        try {
            // Validate file size (10MB maximum)
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw new \Exception('File size cannot exceed 10MB');
            }

            // Validate file extension
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \Exception(
                    'File type not allowed. Allowed extensions: ' . implode(', ', $allowedExtensions)
                );
            }

            // Create or retrieve folder structure
            $parentFolderId = $this->getOrCreateModuleFolder($module);
            $recordFolderId = $this->getOrCreateRecordFolder($parentFolderId, $recordId);

            // Prepare file metadata
            $fileMetadata = new DriveFile([
                'name' => $file->getClientOriginalName(),
                'parents' => [$recordFolderId],
                'description' => sprintf('File for module %s (ID: %d)', $module, $recordId),
            ]);

            $content = file_get_contents($file->getRealPath());
            $createdFile = $this->driveService->files->create(
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => $file->getMimeType(),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webContentLink, webViewLink, size, mimeType',
                ]
            );

            // Set public read permissions for file access
            $this->setPublicPermission($createdFile->getId());

            return [
                'id' => $createdFile->getId(),
                'name' => $file->getClientOriginalName(),
                'url' => $createdFile->getWebContentLink(),
                'view_url' => $createdFile->getWebViewLink(),
                'size' => (int) $createdFile->getSize(),
                'mime_type' => $createdFile->getMimeType(),
            ];
        } catch (\Google\Exception $e) {
            // Handle Google API specific errors
            if ($this->isAuthenticationError($e->getMessage())) {
                $this->refreshAccessToken();
                // Retry upload once after token refresh
                return $this->uploadFile($file, $module, $recordId);
            }
            Log::error('Google API error during file upload: ' . $e->getMessage());
            throw new \Exception('Failed to upload file to Google Drive: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error uploading file to Google Drive: ' . $e->getMessage(), [
                'module' => $module,
                'recordId' => $recordId,
                'fileName' => $file->getClientOriginalName(),
                'fileSize' => $file->getSize(),
            ]);
            throw $e;
        }
    }

    /**
     * Get or create a folder for the specified module.
     * 
     * @param string $module The module name (e.g., "Project").
     * @return string The folder ID.
     * @throws \Exception If folder creation fails.
     */
    private function getOrCreateModuleFolder(string $module): string
    {
        $folderName = ucfirst(Str::singular($module));

        // Search for existing folder
        $query = sprintf(
            "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            $this->escapeQueryValue($folderName),
            $this->escapeQueryValue($this->folderId)
        );

        $params = [
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive',
        ];

        $files = $this->driveService->files->listFiles($params);
        $folder = $files->getFiles()[0] ?? null;

        if (!$folder) {
            // Create new folder
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$this->folderId],
            ]);

            $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id, name']);
        }

        return $folder->getId();
    }

    /**
     * Get or create a folder for a specific record.
     * 
     * @param string $parentId The parent folder ID.
     * @param int $recordId The record ID to use for folder naming.
     * @return string The folder ID.
     * @throws \Exception If folder creation fails.
     */
    private function getOrCreateRecordFolder(string $parentId, int $recordId): string
    {
        $folderName = sprintf('ID-%d', $recordId);

        // Search for existing folder
        $query = sprintf(
            "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            $this->escapeQueryValue($folderName),
            $this->escapeQueryValue($parentId)
        );

        $params = [
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive',
        ];

        $files = $this->driveService->files->listFiles($params);
        $folder = $files->getFiles()[0] ?? null;

        if (!$folder) {
            // Create new folder
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]);

            $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id, name']);
        }

        return $folder->getId();
    }

    /**
     * Set public read permissions for a file.
     * 
     * This allows anyone with the link to view the file.
     * 
     * @param string $fileId The Google Drive file ID.
     * @return void
     * @throws \Exception If permission setting fails.
     */
    private function setPublicPermission(string $fileId): void
    {
        $permission = new Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false,
        ]);

        $this->driveService->permissions->create(
            $fileId,
            $permission,
            ['fields' => 'id']
        );
    }

    /**
     * List files for a specific record.
     * 
     * @param string $module The module name.
     * @param int $recordId The record ID.
     * @return array Array of file objects from Google Drive API.
     */
    public function listFiles(string $module, int $recordId): array
    {
        try {
            $parentId = $this->getOrCreateModuleFolder($module);
            $recordFolderId = $this->getOrCreateRecordFolder($parentId, $recordId);

            $query = sprintf(
                "'%s' in parents and trashed = false",
                $this->escapeQueryValue($recordFolderId)
            );

            $params = [
                'q' => $query,
                'fields' => 'files(id, name, webViewLink, webContentLink, size, mimeType, createdTime)',
                'pageSize' => 100,
                'spaces' => 'drive',
            ];

            $files = $this->driveService->files->listFiles($params);
            return $files->getFiles();
        } catch (\Google\Exception $e) {
            if ($this->isAuthenticationError($e->getMessage())) {
                $this->refreshAccessToken();
                return $this->listFiles($module, $recordId);
            }
            Log::error('Error listing files from Google Drive: ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            Log::error('Error listing files from Google Drive: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a file from Google Drive.
     * 
     * @param string $fileId The Google Drive file ID to delete.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Google\Exception $e) {
            if ($this->isAuthenticationError($e->getMessage())) {
                $this->refreshAccessToken();
                return $this->deleteFile($fileId);
            }
            Log::error('Error deleting file from Google Drive: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Error deleting file from Google Drive: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine if an error message indicates an authentication issue.
     * 
     * @param string $message The error message to check.
     * @return bool True if the message indicates an authentication error.
     */
    private function isAuthenticationError(string $message): bool
    {
        $authErrors = [
            'invalid_grant',
            'Invalid Credentials',
            'Request had invalid authentication credentials',
            'The OAuth token was invalid',
            'Token has been expired or revoked',
        ];

        foreach ($authErrors as $authError) {
            if (strpos($message, $authError) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escape a value for use in Google Drive API query strings.
     * 
     * This helps prevent query injection attacks.
     * 
     * @param string $value The value to escape.
     * @return string The escaped value.
     */
    private function escapeQueryValue(string $value): string
    {
        // Escape single quotes by doubling them (Google Drive API convention)
        return str_replace("'", "''", $value);
    }
}
