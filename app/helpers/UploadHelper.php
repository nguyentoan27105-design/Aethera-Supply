<?php

class UploadHelper
{
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const CERTIFICATE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public static function saveUploadedFile(
        array $file,
        string $uploadDir,
        array $allowedTypes,
        string $prefix,
        int $maxBytes = 5242880
    ): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid upload source.');
        }

        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
            throw new RuntimeException('File size is not allowed.');
        }

        $mime = self::detectMime($tmpName);
        if (!isset($allowedTypes[$mime])) {
            throw new RuntimeException('File type is not allowed.');
        }

        $originalExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExt = array_unique(array_values($allowedTypes));
        if (!in_array($originalExt, $allowedExt, true)) {
            throw new RuntimeException('File extension is not allowed.');
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Cannot create upload directory.');
        }

        $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'upload';
        $fileName = $safePrefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$mime];
        $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Cannot save uploaded file.');
        }

        return $fileName;
    }

    private static function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Cannot inspect uploaded file.');
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (!is_string($mime) || $mime === '') {
            throw new RuntimeException('Cannot inspect uploaded file.');
        }

        return $mime;
    }
}
