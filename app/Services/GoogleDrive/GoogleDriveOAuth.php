<?php

namespace App\Services\GoogleDrive;

use App\Application\Contracts\GoogleDriveServiceInterface;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google Drive service implementation using OAuth 2.0 authentication.
 * 
 * Uses a user's authorized account (via refresh token) to access Google Drive.
 * Files are stored in the user's "My Drive" or shared folders.
 * 
 * @implements GoogleDriveServiceInterface
 */
class GoogleDriveOAuth implements GoogleDriveServiceInterface
{
    private Client $client;
    private Drive $driveService;
    private string $folderId;
    private array $tokenConfig;
    private string $credentialsPath;
    private string $configPath;

    /**
     * Constructor
     * 
     * @param array|null $config Optional config array. If null, loads from file.
     * @throws \Exception If configuration or credentials are missing
     */
    public function __construct(?array $config = null)
    {

        if ($config !== null) {
            $this->tokenConfig = $config;
        } else {
            $this->configPath = storage_path('app/google-drive-config.json');
            if (!file_exists($this->configPath)) {
                throw new \Exception(
                    'Google Drive config not found. Run: php artisan drive:auth'
                );
            }
            $this->tokenConfig = json_decode(file_get_contents($this->configPath), true);
        }


        $this->folderId = $this->tokenConfig['folder_id'] ?? null;
        if (!$this->folderId) {
            throw new \Exception('Folder ID not configured in google-drive-config.json');
        }


        $this->credentialsPath = storage_path('app/google-oauth-credentials.json');
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception(
                'OAuth credentials not found. Download from Google Cloud Console ' .
                    'and save as: google-oauth-credentials.json'
            );
        }


        $this->client = new Client();
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->addScope([
            Drive::DRIVE_FILE,              // Create/edit files
            Drive::DRIVE_METADATA_READONLY, // List/read metadata
        ]);
        $this->client->setAccessType('offline');  // Get refresh token
        $this->client->setPrompt('consent');      // Force consent for refresh token


        $this->refreshTokenIfNeeded();


        $this->driveService = new Drive($this->client);
    }

    /**
     * Upload a file to Google Drive
     * 
     * @param UploadedFile $file File to upload
     * @param string $module Module name (Project, Quote, etc.)
     * @param int $recordId Record ID for folder organization
     * @return array File metadata: id, name, url, view_url, size, mime_type
     * 
     * @throws \Exception If upload fails
     */
    public function uploadFile(UploadedFile $file, string $module, int $recordId): array
    {
        try {
            // Validate file size (10MB max)
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw new \Exception('File size cannot exceed 10MB');
            }

            // Validate file extension
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \Exception(
                    'File type not allowed. Allowed: ' . implode(', ', $allowedExtensions)
                );
            }

            // Create folder structure
            $parentFolderId = $this->getOrCreateModuleFolder($module);
            $recordFolderId = $this->getOrCreateRecordFolder($parentFolderId, $recordId);

            // Prepare file metadata
            $fileMetadata = new DriveFile([
                'name' => $file->getClientOriginalName(),
                'parents' => [$recordFolderId],
                'description' => sprintf('File for module %s (ID: %d)', $module, $recordId),
            ]);

            $content = file_get_contents($file->getRealPath());

            // Upload file
            $createdFile = $this->driveService->files->create(
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => $file->getMimeType(),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webContentLink, webViewLink, size, mimeType',
                    // ← OAuth para My Drive NO requiere supportsAllDrives
                ]
            );

            // Set public read permissions (optional: make file publicly viewable)
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
            // Handle authentication errors by refreshing token and retrying
            if ($this->isAuthError($e->getMessage())) {
                try {
                    Log::warning('Auth error during upload, attempting token refresh...');
                    $this->refreshTokenIfNeeded();
                    // Retry upload once after refresh
                    return $this->uploadFile($file, $module, $recordId);
                } catch (\Exception $refreshError) {
                    Log::error('Token refresh and retry failed: ' . $refreshError->getMessage());
                    throw new \Exception('Authentication failed. Please re-authorize Google Drive.');
                }
            }

            Log::error('Google API error during file upload: ' . $e->getMessage(), [
                'error_code' => $e->getCode(),
                'module' => $module,
                'recordId' => $recordId,
                'fileName' => $file->getClientOriginalName(),
            ]);
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
     * List files for a specific record
     * 
     * @param string $module Module name
     * @param int $recordId Record ID
     * @return array Array of file objects from Google Drive API
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
                // ← OAuth para My Drive NO requiere supportsAllDrives
            ];

            $files = $this->driveService->files->listFiles($params);
            return $files->getFiles();
        } catch (\Google\Exception $e) {
            if ($this->isAuthError($e->getMessage())) {
                try {
                    $this->refreshTokenIfNeeded();
                    return $this->listFiles($module, $recordId); // Retry
                } catch (\Exception $refreshError) {
                    Log::error('Token refresh failed during listFiles: ' . $refreshError->getMessage());
                }
            }
            Log::error('Error listing files from Google Drive: ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            Log::error('Error listing files from Google Drive: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a file from Google Drive
     * 
     * @param string $fileId Google Drive file ID
     * @return bool True if deletion was successful
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Google\Exception $e) {
            if ($this->isAuthError($e->getMessage())) {
                try {
                    $this->refreshTokenIfNeeded();
                    return $this->deleteFile($fileId); // Retry
                } catch (\Exception $refreshError) {
                    Log::error('Token refresh failed during deleteFile: ' . $refreshError->getMessage());
                }
            }
            Log::error('Error deleting file from Google Drive: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('Error deleting file from Google Drive: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the current authentication type
     * 
     * @return string 'oauth'
     */
    public function getAuthType(): string
    {
        return 'oauth';
    }

    /**
     * Refresh access token if expired or about to expire
     * 
     * @throws \Exception If refresh fails
     */
    private function refreshTokenIfNeeded(): void
    {
        $refreshToken = $this->tokenConfig['refresh_token'] ?? null;

        if (!$refreshToken) {
            throw new \Exception(
                'Refresh token not found. Run: php artisan drive:auth to re-authorize'
            );
        }

        $expiresAt = ($this->tokenConfig['created'] ?? 0) + ($this->tokenConfig['expires_in'] ?? 0);
        $now = time();
        $bufferSeconds = 300;

        if ($expiresAt - $now > $bufferSeconds) {
            Log::debug('Google OAuth: Access token still valid, skipping refresh');
            return;
        }

        Log::info('Google OAuth: Access token expiring soon, refreshing...');

        try {
            // Attempt to refresh the access token
            $token = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($token['error'])) {
                $errorMessage = $token['error'];
                $errorDescription = $token['error_description'] ?? 'No description';

                throw new \Google\Exception(
                    "Token refresh error: {$errorMessage} - {$errorDescription}"
                );
            }

            // Save updated token config (with safe refresh_token handling)
            $this->saveTokenConfig($token);

            Log::info('Google OAuth: Token refreshed successfully', [
                'new_expires_in' => $token['expires_in'] ?? null,
            ]);
        } catch (\Google\Exception $e) {
            Log::error('Google token refresh failed: ' . $e->getMessage(), [
                'error_code' => $e->getCode(),
                'error_details' => $e->getTraceAsString(),
            ]);

            if (strpos($e->getMessage(), 'invalid_grant') !== false) {
                Log::error('Google OAuth: Refresh token may be revoked or invalid. User must re-authorize.');
            }

            throw new \Exception(
                'Authentication failed. Run: php artisan drive:auth to re-authorize Google Drive'
            );
        }
    }

    /**
     * Save updated token configuration to file
     * 
     * @param array $token New token data from Google
     */
    private function saveTokenConfig(array $token): void
    {
        $updates = [
            'access_token' => $token['access_token'] ?? null,
            'expires_in' => $token['expires_in'] ?? null,
            'created' => $token['created'] ?? time(),
            'updated_at' => now()->toIso8601String(),
        ];

        if (isset($token['refresh_token']) && !empty($token['refresh_token'])) {
            $updates['refresh_token'] = $token['refresh_token'];
            Log::info('Google OAuth: New refresh token received and saved');
        }

        $this->tokenConfig = array_merge($this->tokenConfig, $updates);
        // Ensure config directory exists
        $configDir = dirname($this->configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents(
            $this->configPath,
            json_encode($this->tokenConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Log::debug('Google OAuth config saved', [
            'has_refresh_token' => !empty($this->tokenConfig['refresh_token']),
            'access_token_expires_in' => $this->tokenConfig['expires_in'] ?? null,
        ]);
    }

    /**
     * Check if an error message indicates an authentication issue
     * 
     * @param string $message Error message from Google API
     * @return bool True if the error is authentication-related
     */
    private function isAuthError(string $message): bool
    {
        $authErrors = [
            'invalid_grant',
            'Invalid Credentials',
            'Token has been expired or revoked',
            'Request had invalid authentication credentials',
            'The OAuth token was invalid',
            'access_denied',
        ];

        foreach ($authErrors as $authError) {
            if (stripos($message, $authError) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get or create a folder for the specified module
     * 
     * @param string $module Module name (e.g., "Project")
     * @return string The folder ID
     * 
     * @throws \Exception If folder creation fails
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
     * Get or create a folder for a specific record
     * 
     * @param string $parentId Parent folder ID
     * @param int $recordId Record ID to use for folder naming
     * @return string The folder ID
     * 
     * @throws \Exception If folder creation fails
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
     * Set public read permissions for a file
     * 
     * This allows anyone with the link to view the file.
     * 
     * @param string $fileId Google Drive file ID
     * @return void
     * 
     * @throws \Exception If permission setting fails
     */
    private function setPublicPermission(string $fileId): void
    {
        $permission = new Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false,
        ]);

        $this->driveService->permissions->create($fileId, $permission, ['fields' => 'id']);
    }

    /**
     * Escape a value for use in Google Drive API query strings
     * 
     * Prevents query injection by escaping single quotes
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escapeQueryValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
