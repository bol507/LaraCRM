<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveService
{
    private $client;
    private $driveService;
    private $folderId;
    private $config;

    public function __construct()
    {
        // ✅ Cargar configuración (SOLO refresh_token y folder_id)
        $configPath = storage_path('app/google-drive-config.json');
        
        if (!file_exists($configPath)) {
            throw new \Exception(
                "Configuración de Google Drive no encontrada. " .
                "Ejecuta: php artisan drive:auth"
            );
        }

        $config = json_decode(file_get_contents($configPath), true);
        
        if (!$config || !isset($config['refresh_token'])) {
            throw new \Exception(
                "Configuración inválida. Falta 'refresh_token' en google-drive-config.json. " .
                "Ejecuta: php artisan drive:auth"
            );
        }

        $this->folderId = $config['folder_id'] ?? null;

        if (!$this->folderId) {
            throw new \Exception(
                "Folder ID no configurado. Edita google-drive-config.json y agrega 'folder_id'"
            );
        }

        // ✅ Cargar credenciales OAuth (client_id y client_secret)
        $credentialsPath = storage_path('app/google-oauth-credentials.json');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception(
                "Credenciales OAuth no encontradas. " .
                "Descarga el JSON de Google Cloud Console y guárdalo como: google-oauth-credentials.json"
            );
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        // ✅ Extraer client_id y client_secret (formato "installed" o "web")
        $clientId = $credentials['installed']['client_id'] ?? $credentials['web']['client_id'] ?? null;
        $clientSecret = $credentials['installed']['client_secret'] ?? $credentials['web']['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            throw new \Exception(
                "Credenciales OAuth inválidas. El archivo debe contener 'client_id' y 'client_secret'"
            );
        }

        // ✅ Configurar cliente OAuth
        $this->client = new Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->addScope([Drive::DRIVE_FILE, Drive::DRIVE_METADATA_READONLY]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        // ✅ RENOVAR access token usando refresh token (¡SIEMPRE!)
        try {
            $this->client->fetchAccessTokenWithRefreshToken($config['refresh_token']);
            
            if ($this->client->isAccessTokenExpired()) {
                throw new \Exception('No se pudo renovar el access token con el refresh token');
            }
        } catch (\Exception $e) {
            Log::error('Error al renovar access token: ' . $e->getMessage());
            throw new \Exception(
                'Autenticación con Google Drive fallida. ' .
                'Ejecuta: php artisan drive:auth para renovar credenciales'
            );
        }

        // ✅ Inicializar servicio Drive
        $this->driveService = new Drive($this->client);
    }

    /**
     * Renovar access token usando refresh token
     */
    private function refreshAccessToken(): void
    {
        try {
            // ✅ Forzar renovación del token
            $this->client->fetchAccessTokenWithRefreshToken($this->config['refresh_token']);
            
            // ✅ Verificar que el token sea válido
            if ($this->client->isAccessTokenExpired()) {
                throw new \Exception('Access token expirado y no se pudo renovar');
            }
        } catch (\Exception $e) {
            Log::error('Error al renovar access token de Google Drive: ' . $e->getMessage());
            throw new \Exception(
                'No se pudo autenticar con Google Drive. ' .
                'Verifica tu configuración o ejecuta: php artisan drive:auth'
            );
        }
    }

    /**
     * Subir archivo a Google Drive
     */
    public function uploadFile(UploadedFile $file, string $module, int $recordId): array
    {
        try {
            // ✅ Validar tamaño (10MB máximo)
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw new \Exception('El archivo no puede superar los 10MB');
            }

            // ✅ Validar extensión
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception('Tipo de archivo no permitido');
            }

            // ✅ Crear carpetas
            $parentFolderId = $this->getOrCreateModuleFolder($module);
            $recordFolderId = $this->getOrCreateRecordFolder($parentFolderId, $recordId);

            // ✅ Subir archivo
            $fileMetadata = new DriveFile([
                'name' => $file->getClientOriginalName(),
                'parents' => [$recordFolderId],
                'description' => "Archivo del módulo $module (ID: $recordId)"
            ]);

            $content = file_get_contents($file->getRealPath());
            $createdFile = $this->driveService->files->create(
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => $file->getMimeType(),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webContentLink, webViewLink, size, mimeType'
                ]
            );

            // ✅ Establecer permisos públicos
            $this->setPublicPermission($createdFile->id);

            return [
                'id' => $createdFile->id,
                'name' => $file->getClientOriginalName(),
                'url' => $createdFile->webContentLink,
                'view_url' => $createdFile->webViewLink,
                'size' => (int) $createdFile->size,
                'mime_type' => $createdFile->mimeType,
            ];

        } catch (\Exception $e) {
            Log::error('Error al subir a Google Drive: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener o crear carpeta para el módulo
     */
    private function getOrCreateModuleFolder(string $module): string
    {
        $folderName = ucfirst(Str::singular($module));

        // ✅ Buscar carpeta existente
        $query = "name = '$folderName' and mimeType = 'application/vnd.google-apps.folder' and '{$this->folderId}' in parents and trashed = false";
        $params = [
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive'
        ];

        $files = $this->driveService->files->listFiles($params);
        $folder = $files->getFiles()[0] ?? null;

        if (!$folder) {
            // ✅ Crear nueva carpeta
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$this->folderId]
            ]);

            $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id, name']);
        }

        return $folder->id;
    }

    /**
     * Obtener o crear carpeta para el registro
     */
    private function getOrCreateRecordFolder(string $parentId, int $recordId): string
    {
        $folderName = "ID-$recordId";

        // ✅ Buscar carpeta existente
        $query = "name = '$folderName' and mimeType = 'application/vnd.google-apps.folder' and '$parentId' in parents and trashed = false";
        $params = [
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive'
        ];

        $files = $this->driveService->files->listFiles($params);
        $folder = $files->getFiles()[0] ?? null;

        if (!$folder) {
            // ✅ Crear nueva carpeta
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId]
            ]);

            $folder = $this->driveService->files->create($fileMetadata, ['fields' => 'id, name']);
        }

        return $folder->id;
    }

    /**
     * Establecer permisos públicos para visualización
     */
    private function setPublicPermission(string $fileId): void
    {
        $permission = new Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false
        ]);

        $this->driveService->permissions->create(
            $fileId,
            $permission,
            ['fields' => 'id']
        );
    }

    /**
     * Listar archivos para un registro
     */
    public function listFiles(string $module, int $recordId): array
    {
        try {
            $parentId = $this->getOrCreateModuleFolder($module);
            $recordFolderId = $this->getOrCreateRecordFolder($parentId, $recordId);

            $query = "'$recordFolderId' in parents and trashed = false";
            $params = [
                'q' => $query,
                'fields' => 'files(id, name, webViewLink, webContentLink, size, mimeType, createdTime)',
                'pageSize' => 100,
                'spaces' => 'drive'
            ];

            $files = $this->driveService->files->listFiles($params);
            return $files->getFiles();

        } catch (\Exception $e) {
            // ✅ Intentar renovar token si hay error de autenticación
            if (strpos($e->getMessage(), 'Invalid Credentials') !== false) {
                $this->refreshAccessToken();
                return $this->listFiles($module, $recordId);
            }

            Log::error('Error al listar archivos de Google Drive: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Eliminar archivo
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            // ✅ Intentar renovar token si hay error de autenticación
            if (strpos($e->getMessage(), 'Invalid Credentials') !== false) {
                $this->refreshAccessToken();
                return $this->deleteFile($fileId);
            }

            Log::error('Error al eliminar archivo de Google Drive: ' . $e->getMessage());
            return false;
        }
    }
}