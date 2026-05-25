<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/upload.php';

function learnloop_gridfs_bucket(): MongoDB\GridFS\Bucket
{
    return db()->selectGridFSBucket([
        'bucketName' => 'learnloop_files'
    ]);
}

function learnloop_object_id(mixed $value): ?MongoDB\BSON\ObjectId
{
    if ($value instanceof MongoDB\BSON\ObjectId) {
        return $value;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        return new MongoDB\BSON\ObjectId($value);
    } catch (Exception $e) {
        return null;
    }
}

function learnloop_store_single_file(array $file, array $metadata = []): array
{
    $name = (string) ($file['name'] ?? '');
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($name === '' || $tmp === '' || $size <= 0 || $error !== UPLOAD_ERR_OK || !is_file($tmp)) {
        return [false, 'Invalid file upload.', null];
    }

    $mimeType = detect_mime($tmp) ?: (string) ($file['type'] ?? 'application/octet-stream');
    $bucket = learnloop_gridfs_bucket();
    $source = fopen($tmp, 'rb');
    if ($source === false) {
        return [false, 'Failed to read uploaded file.', null];
    }

    $payload = array_merge([
        'original_name' => $name,
        'mime_type' => $mimeType,
        'size' => $size,
    ], $metadata);

    try {
        $fileId = $bucket->uploadFromStream($name, $source, [
            'metadata' => $payload,
        ]);
    } catch (Exception $e) {
        fclose($source);
        return [false, 'Failed to store file in database.', null];
    }

    fclose($source);

    return [true, 'File stored.', [
        'file_id' => (string) $fileId,
        'file_name' => $name,
        'file_size' => $size,
        'mime_type' => $mimeType,
        'metadata' => $payload,
    ]];
}

function learnloop_fetch_stored_file(string $fileId): ?array
{
    $objectId = learnloop_object_id($fileId);
    if (!$objectId) {
        return null;
    }

    try {
        $file = learnloop_gridfs_bucket()->findOne(['_id' => $objectId]);
    } catch (Exception $e) {
        return null;
    }

    if (!$file) {
        return null;
    }

    $metadata = (array) ($file['metadata'] ?? []);

    return [
        'file_id' => (string) ($file['_id'] ?? $fileId),
        'file_name' => (string) ($file['filename'] ?? ($metadata['original_name'] ?? 'file')),
        'file_size' => (int) ($file['length'] ?? ($metadata['size'] ?? 0)),
        'mime_type' => (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
        'metadata' => $metadata,
        'download_name' => (string) ($metadata['original_name'] ?? ($file['filename'] ?? 'file')),
    ];
}

function learnloop_delete_stored_file(string $fileId): bool
{
    $objectId = learnloop_object_id($fileId);
    if (!$objectId) {
        return false;
    }

    try {
        learnloop_gridfs_bucket()->delete($objectId);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function learnloop_stream_stored_file(string $fileId): bool
{
    $file = learnloop_fetch_stored_file($fileId);
    if (!$file) {
        return false;
    }

    $objectId = learnloop_object_id($fileId);
    if (!$objectId) {
        return false;
    }

    try {
        $stream = learnloop_gridfs_bucket()->openDownloadStream($objectId);
    } catch (Exception $e) {
        return false;
    }

    if (!is_resource($stream)) {
        return false;
    }

    $downloadName = $file['download_name'] !== '' ? $file['download_name'] : 'download';
    header('Content-Type: ' . ($file['mime_type'] !== '' ? $file['mime_type'] : 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Content-Length: ' . (int) ($file['file_size'] ?? 0));
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        return false;
    }

    stream_copy_to_stream($stream, $output);
    fclose($stream);
    fclose($output);

    return true;
}