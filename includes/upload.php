<?php
declare(strict_types=1);

/**
 * File upload helper. Validates MIME, extension, and size, then writes
 * to /uploads/<bucket>/. Returns a public path (e.g. /uploads/events/abc.jpg)
 * or null on no-upload, throws RuntimeException on validation failure.
 */

const UPLOAD_BUCKETS = [
    'events'       => ['max' => 5_000_000, 'mimes' => ['image/jpeg','image/png','image/webp']],
    'content'      => ['max' => 50_000_000,'mimes' => ['audio/mpeg','audio/mp4','audio/wav','audio/x-wav','audio/ogg','image/jpeg','image/png','image/webp']],
    'testimonials' => ['max' => 3_000_000, 'mimes' => ['image/jpeg','image/png','image/webp']],
    'avatars'      => ['max' => 2_000_000, 'mimes' => ['image/jpeg','image/png','image/webp']],
];

function handle_upload(string $field, string $bucket): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!isset(UPLOAD_BUCKETS[$bucket])) {
        throw new RuntimeException('Unknown upload bucket.');
    }
    $rules = UPLOAD_BUCKETS[$bucket];
    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int)$file['error'] . ').');
    }
    if ((int)$file['size'] > $rules['max']) {
        throw new RuntimeException('File too large. Max ' . round($rules['max']/1_000_000) . ' MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    if (!in_array($mime, $rules['mimes'], true)) {
        throw new RuntimeException('Unsupported file type (' . e($mime) . ').');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp','mp3','m4a','mp4','wav','ogg'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File extension not allowed.');
    }

    $dir = SH_ROOT . '/uploads/' . $bucket;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload directory not writable.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not store the upload.');
    }
    @chmod($dest, 0644);

    return '/uploads/' . $bucket . '/' . $name;
}

function delete_upload(?string $publicPath): void
{
    if (!$publicPath || !str_starts_with($publicPath, '/uploads/')) {
        return;
    }
    $abs = SH_ROOT . $publicPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}
