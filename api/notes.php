<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$notes = db()->selectCollection('notes');

$method = $_SERVER['REQUEST_METHOD'];
$action = clean_text($_GET['action'] ?? '');

header('Content-Type: application/json');

// ── UPLOAD NOTE ──
if ($method === 'POST' && $action === 'upload') {
    $token = clean_text($_POST['csrf_token'] ?? '');
    if (!csrf_check($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }

    $title = clean_text($_POST['title'] ?? '');
    $subject = clean_text($_POST['subject'] ?? '');

    if ($title === '' || $subject === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Title and subject are required.']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Please select a file to upload.']);
        exit;
    }

    $file = $_FILES['file'];
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'File type not allowed. Allowed: ' . implode(', ', $allowed)]);
        exit;
    }

    $maxSize = 10 * 1024 * 1024; // 10 MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File size must be under 10 MB.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/notes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $noteId = bin2hex(random_bytes(8));
    $storedName = $noteId . '.' . $ext;
    $destination = $uploadDir . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file.']);
        exit;
    }

    $notes->insertOne([
        'note_id'       => $noteId,
        'title'         => $title,
        'subject'       => $subject,
        'file_name'     => $file['name'],
        'stored_name'   => $storedName,
        'file_ext'      => $ext,
        'file_size'     => $file['size'],
        'uploaded_by'   => $user['user_id'],
        'uploader_name' => $user['full_name'],
        'created_at'    => new MongoDB\BSON\UTCDateTime(),
    ]);

    echo json_encode(['success' => true, 'note_id' => $noteId]);
    exit;
}

// ── LIST NOTES ──
if ($method === 'GET' && $action === 'list') {
    $cursor = $notes->find([], ['sort' => ['created_at' => -1]]);
    $result = [];

    foreach ($cursor as $note) {
        $result[] = [
            'note_id'       => $note['note_id'],
            'title'         => $note['title'],
            'subject'       => $note['subject'],
            'file_name'     => $note['file_name'],
            'file_ext'      => $note['file_ext'],
            'file_size'     => $note['file_size'],
            'uploaded_by'   => $note['uploaded_by'],
            'uploader_name' => $note['uploader_name'],
            'created_at'    => $note['created_at']->toDateTime()->format('Y-m-d H:i'),
        ];
    }

    echo json_encode($result);
    exit;
}

// ── DELETE NOTE ──
if ($method === 'POST' && $action === 'delete') {
    $token = clean_text($_POST['csrf_token'] ?? '');
    if (!csrf_check($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }

    $noteId = clean_text($_POST['note_id'] ?? '');
    if ($noteId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Note ID is required.']);
        exit;
    }

    $note = $notes->findOne(['note_id' => $noteId]);
    if (!$note) {
        http_response_code(404);
        echo json_encode(['error' => 'Note not found.']);
        exit;
    }

    if ($note['uploaded_by'] !== $user['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only delete your own notes.']);
        exit;
    }

    $filePath = __DIR__ . '/../uploads/notes/' . $note['stored_name'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    $notes->deleteOne(['note_id' => $noteId]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action.']);