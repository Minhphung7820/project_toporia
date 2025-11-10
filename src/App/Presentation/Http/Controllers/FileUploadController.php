<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Storage\UploadedFile;

/**
 * File Upload Controller
 *
 * Demonstrates file upload and storage functionality.
 *
 * Features:
 * - Local file upload
 * - S3 cloud upload
 * - File validation
 * - Hash-based filenames
 *
 * Performance:
 * - Stream-based uploads (memory efficient)
 * - Hash caching
 */
final class FileUploadController extends BaseController
{
    /**
     * Display file upload form.
     */
    public function showForm(): void
    {
        $html = $this->view('upload/form', [
            'title' => 'File Upload Demo'
        ]);

        $this->response()->html($html);
    }

    /**
     * Handle file upload to local storage.
     */
    public function uploadLocal(): void
    {
        // Validate file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->response()->json([
                'error' => 'No file uploaded or upload error'
            ], 400);
            return;
        }

        // Create UploadedFile instance
        $file = UploadedFile::createFromArray($_FILES['file']);

        // Validate file
        if (!$file->isValid()) {
            $this->response()->json([
                'error' => 'Invalid file upload'
            ], 400);
            return;
        }

        // Validate file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->response()->json([
                'error' => 'File too large. Maximum size is 10MB.'
            ], 400);
            return;
        }

        // Store file in 'uploads' directory with hash-based name
        $path = $file->store('uploads', null, 'local');

        if ($path === false) {
            $this->response()->json([
                'error' => 'Failed to store file'
            ], 500);
            return;
        }

        // Get file URL
        $url = storage('local')->url($path);

        $this->response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'path' => $path,
                'url' => $url,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'hash' => $file->hash()
            ]
        ], 201);
    }

    /**
     * Handle file upload to S3.
     */
    public function uploadToS3(): void
    {
        // Validate file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->response()->json([
                'error' => 'No file uploaded or upload error'
            ], 400);
            return;
        }

        // Create UploadedFile instance
        $file = UploadedFile::createFromArray($_FILES['file']);

        if (!$file->isValid()) {
            $this->response()->json([
                'error' => 'Invalid file upload'
            ], 400);
            return;
        }

        // Store file publicly on S3
        $path = $file->storePublicly('uploads', null, 's3');

        if ($path === false) {
            $this->response()->json([
                'error' => 'Failed to upload to S3'
            ], 500);
            return;
        }

        // Get public URL
        $url = storage('s3')->url($path);

        // Generate temporary signed URL (valid for 1 hour)
        $tempUrl = storage('s3')->temporaryUrl($path, 3600);

        $this->response()->json([
            'success' => true,
            'message' => 'File uploaded to S3 successfully',
            'data' => [
                'path' => $path,
                'url' => $url,
                'temporary_url' => $tempUrl,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType()
            ]
        ], 201);
    }

    /**
     * List uploaded files.
     */
    public function listFiles(): void
    {
        $disk = $this->request()->query('disk', 'local');

        // Get all files in uploads directory
        $files = storage($disk)->files('uploads');

        $filesData = [];
        foreach ($files as $file) {
            $filesData[] = [
                'path' => $file,
                'url' => storage($disk)->url($file),
                'size' => storage($disk)->size($file),
                'last_modified' => storage($disk)->lastModified($file),
                'mime_type' => storage($disk)->mimeType($file)
            ];
        }

        $this->response()->json([
            'disk' => $disk,
            'count' => count($filesData),
            'files' => $filesData
        ]);
    }

    /**
     * Download a file.
     */
    public function download(string $filename): void
    {
        $disk = $this->request()->query('disk', 'local');
        $path = 'uploads/' . $filename;

        // Check if file exists
        if (!storage($disk)->exists($path)) {
            $this->response()->json([
                'error' => 'File not found'
            ], 404);
            return;
        }

        // Get file content
        $content = storage($disk)->get($path);
        $mimeType = storage($disk)->mimeType($path);

        // Set response headers for download
        $response = $this->response();
        $response->header('Content-Type', $mimeType ?? 'application/octet-stream');
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->header('Content-Length', (string) strlen($content));

        $response->html($content);
    }

    /**
     * Delete a file.
     */
    public function delete(string $filename): void
    {
        $disk = $this->request()->query('disk', 'local');
        $path = 'uploads/' . $filename;

        // Check if file exists
        if (!storage($disk)->exists($path)) {
            $this->response()->json([
                'error' => 'File not found'
            ], 404);
            return;
        }

        // Delete file
        $deleted = storage($disk)->delete($path);

        if (!$deleted) {
            $this->response()->json([
                'error' => 'Failed to delete file'
            ], 500);
            return;
        }

        $this->response()->json([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    }
}
