<?php
/**
 * includes/file_uploader.php
 *
 * A secure helper script for handling file uploads (Strictly NO FRAMEWORKS).
 * Validates MIME types, enforces size limits, and generates unique filenames.
 */

class FileUploader {
    private $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private $maxFileSize = 2097152; // 2MB in bytes
    private $baseUploadDir;

    public function __construct() {
        // Base directory for all uploads, relative to this file
        $this->baseUploadDir = realpath(__DIR__ . '/../uploads/');

        // Ensure the base directory exists
        if (!$this->baseUploadDir) {
            $dir = __DIR__ . '/../uploads/';
            if (!mkdir($dir, 0755, true)) {
                error_log("FileUploader: Failed to create base upload directory: $dir");
            }
            $this->baseUploadDir = realpath($dir);
        }
    }

    /**
     * Upload a file to a specific subdirectory
     *
     * @param array $file The $_FILES array element (e.g., $_FILES['logo'])
     * @param string $subDir The subdirectory name (e.g., 'logos' or 'campaigns')
     * @return array ['success' => bool, 'path' => string, 'error' => string]
     */
    public function upload($file, $subDir) {
        // Check for basic upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file sent.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'Exceeded filesize limit.'];
            default:
                return ['success' => false, 'error' => 'Unknown errors.'];
        }

        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => 'Exceeded filesize limit (Max: 2MB).'];
        }

        // Validate MIME type securely using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            return ['success' => false, 'error' => 'Invalid file format. Only JPG, PNG, and WebP are allowed.'];
        }

        // Map MIME type to extension
        $ext = '';
        switch ($mime) {
            case 'image/jpeg': $ext = 'jpg'; break;
            case 'image/png': $ext = 'png'; break;
            case 'image/webp': $ext = 'webp'; break;
        }

        // Generate a secure unique filename to prevent overwriting and traversal attacks
        $newFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $ext);

        // Ensure the target subdirectory exists
        $targetDir = $this->baseUploadDir . '/' . trim($subDir, '/');
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create upload directory.'];
            }
        }

        $targetPath = $targetDir . '/' . $newFilename;

        // Move the file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file.'];
        }

        // Return the relative path for database storage (e.g., '/uploads/logos/xyz.jpg')
        $relativePath = '/uploads/' . trim($subDir, '/') . '/' . $newFilename;

        return [
            'success' => true,
            'path' => $relativePath,
            'full_path' => $targetPath
        ];
    }

    /**
     * Delete an uploaded file securely given its relative path.
     *
     * @param string $relativePath
     * @return bool
     */
    public function deleteFile($relativePath) {
        if (empty($relativePath)) {
            return false;
        }

        // Sanitize path to prevent directory traversal
        $cleanPath = basename($relativePath);

        // Determine subdir based on the path
        $subDir = '';
        if (strpos($relativePath, '/uploads/logos/') !== false) {
            $subDir = 'logos';
        } else if (strpos($relativePath, '/uploads/campaigns/') !== false) {
            $subDir = 'campaigns';
        } else {
            return false;
        }

        $fullPath = $this->baseUploadDir . '/' . $subDir . '/' . $cleanPath;

        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
