<?php
// Upload helper utilities
defined('MAX_UPLOAD_BYTES') || define('MAX_UPLOAD_BYTES', 25 * 1024 * 1024); // 25 MB
defined('MAX_UPLOAD_COUNT') || define('MAX_UPLOAD_COUNT', 5);

function normalize_files_field(array $fileField): array
{
    $files = [];
    // Single file
    if (!isset($fileField['name']) || !is_array($fileField['name'])) {
        $files[] = $fileField;
        return $files;
    }

    $count = count($fileField['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $fileField['name'][$i],
            'type' => $fileField['type'][$i],
            'tmp_name' => $fileField['tmp_name'][$i],
            'error' => $fileField['error'][$i],
            'size' => $fileField['size'][$i]
        ];
    }

    return $files;
}

function allowed_extension_list(): array
{
    return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'zip'];
}

function disallowed_extensions(): array
{
    return ['7z', 'tar', 'rar', 'exe', 'sh', 'bat', 'js', 'php'];
}

function allowed_mime_types_for_ext(string $ext): array
{
    $ext = strtolower($ext);
    $map = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream']
    ];

    return $map[$ext] ?? [];
}

function detect_mime(string $tmpPath): ?string
{
    if (!is_file($tmpPath)) {
        return null;
    }

    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = finfo_file($f, $tmpPath);
            finfo_close($f);
            return $m ?: null;
        }
    }

    if (function_exists('mime_content_type')) {
        $m = mime_content_type($tmpPath);
        return $m ?: null;
    }

    return null;
}

function validate_uploaded_files(array $fileField): array
{
    $result = [
        'validFiles' => [],
        'errors' => []
    ];

    $files = normalize_files_field($fileField);

    if (count($files) === 0) {
        return $result;
    }

    if (count($files) > MAX_UPLOAD_COUNT) {
        $result['errors'][] = [
            'code' => 'too_many_files',
            'message' => 'Maximum ' . MAX_UPLOAD_COUNT . ' files are allowed.'
        ];
        return $result;
    }

    foreach ($files as $f) {
        $name = (string) ($f['name'] ?? '');
        $tmp = (string) ($f['tmp_name'] ?? '');
        $size = (int) ($f['size'] ?? 0);
        $error = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            $result['errors'][] = ['file' => $name, 'code' => 'upload_error', 'message' => 'Upload error for file.'];
            continue;
        }

        if ($size <= 0) {
            $result['errors'][] = ['file' => $name, 'code' => 'empty_file', 'message' => 'File is empty.'];
            continue;
        }

        if ($size > MAX_UPLOAD_BYTES) {
            $result['errors'][] = ['file' => $name, 'code' => 'file_too_large', 'message' => 'File exceeds the maximum allowed size of ' . (MAX_UPLOAD_BYTES / (1024 * 1024)) . ' MB.'];
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') {
            $result['errors'][] = ['file' => $name, 'code' => 'no_extension', 'message' => 'File has no extension.'];
            continue;
        }

        if (in_array($ext, disallowed_extensions(), true)) {
            $result['errors'][] = ['file' => $name, 'code' => 'disallowed_extension', 'message' => 'Files with .' . $ext . ' extension are not allowed.'];
            continue;
        }

        if (!in_array($ext, allowed_extension_list(), true)) {
            $result['errors'][] = ['file' => $name, 'code' => 'unsupported_extension', 'message' => 'Unsupported file type: .' . $ext];
            continue;
        }

        $mime = detect_mime($tmp);
        $allowedMimes = allowed_mime_types_for_ext($ext);
        if ($mime === null || !in_array($mime, $allowedMimes, true)) {
            $result['errors'][] = ['file' => $name, 'code' => 'invalid_mime', 'message' => 'Invalid MIME type: ' . ($mime ?: 'unknown')];
            continue;
        }

        $result['validFiles'][] = [
            'name' => $name,
            'tmp_name' => $tmp,
            'size' => $size,
            'type' => $mime
        ];
    }

    return $result;
}
