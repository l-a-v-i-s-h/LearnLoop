<?php
require_once __DIR__ . '/../includes/moderation.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/file_store.php';

header('Content-Type: application/json; charset=UTF-8');

$hasUser = isset($_SESSION['user']['user_id']);
$hasAdmin = isset($_SESSION['admin']['admin_id']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = clean_text($_GET['action'] ?? '');
$body = moderation_read_body();

if (!$hasUser && !$hasAdmin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $token = '';
    if (!empty($_POST['_csrf_token'])) {
        $token = clean_text($_POST['_csrf_token']);
    } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = clean_text($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    if (!csrf_check($token)) {
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token.'
        ]);
        exit;
    }
}

if ($method === 'GET') {
    if ($action === 'self_status') {
        $userId = $hasUser ? (string) ($_SESSION['user']['user_id'] ?? '') : '';
        respond_json(200, true, 'Moderation status fetched.', moderation_get_user_state($userId));
        exit;
    }

    if ($action === 'download_report_evidence') {
        if (!$hasAdmin) {
            respond_json(403, false, 'Admin access required.');
            exit;
        }

        $fileId = clean_text($_GET['file_id'] ?? '');
        if ($fileId === '' || !learnloop_stream_stored_file($fileId)) {
            respond_json(404, false, 'File not found.');
        }
        exit;
    }

    if (!$hasAdmin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required.'
        ]);
        exit;
    }

    if ($action === 'dashboard') {
        respond_json(200, true, 'Dashboard snapshot fetched.', moderation_get_dashboard_snapshot());
        exit;
    }

    if ($action === 'reports') {
        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = moderation_normalize_status($_GET['status']);
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = clean_text($_GET['search']);
        }
        if (!empty($_GET['reporter_id'])) {
            $filters['reporter_id'] = clean_text($_GET['reporter_id']);
        }
        if (!empty($_GET['reported_user_id'])) {
            $filters['reported_user_id'] = clean_text($_GET['reported_user_id']);
        }
        respond_json(200, true, 'Reports fetched.', moderation_get_reports($filters));
        exit;
    }

    if ($action === 'report') {
        $reportId = clean_text($_GET['report_id'] ?? '');
        $report = moderation_get_report_by_id($reportId);
        if (!$report) {
            respond_json(404, false, 'Report not found.');
            exit;
        }
        respond_json(200, true, 'Report fetched.', $report);
        exit;
    }

    if ($action === 'banned') {
        respond_json(200, true, 'Banned users fetched.', moderation_get_banned_users());
        exit;
    }

    respond_json(400, false, 'Invalid action.');
    exit;
}

if ($method === 'POST') {
    $requestAction = clean_text($body['action'] ?? $action);

    if ($requestAction === 'report_chat') {
        if (!$hasUser) {
            respond_json(401, false, 'User access required.');
            exit;
        }

        $reporter = moderation_user_snapshot((string) ($_SESSION['user']['user_id'] ?? ''));
        $upload = moderation_store_report_evidence($reporter['user_id'] ?? '', $_FILES['proof_photo'] ?? null);
        if (!$upload[0]) {
            respond_json(422, false, $upload[1]);
            exit;
        }

        if (!empty($upload[2])) {
            $body['evidence_file_id'] = $upload[2]['file_id'] ?? '';
            $body['evidence_file_name'] = $upload[2]['file_name'] ?? '';
            $body['evidence_file_path'] = $upload[2]['file_path'] ?? '';
            $body['evidence_file_type'] = $upload[2]['file_type'] ?? '';
        }

        $result = moderation_create_report($body, $reporter);
        respond_json($result[0] ? 201 : 422, $result[0], $result[1], $result[2]);
        exit;
    }

    if ($requestAction === 'review_report') {
        if (!$hasAdmin) {
            respond_json(403, false, 'Admin access required.');
            exit;
        }

        $reportId = clean_text($body['report_id'] ?? '');
        $status = clean_text($body['status'] ?? 'under_review');
        $adminNote = clean_text($body['admin_note'] ?? '');
        $moderationAction = clean_text($body['moderation_action'] ?? '');
        $reason = clean_text($body['reason'] ?? '');
        $adminId = (string) ($_SESSION['admin']['admin_id'] ?? '');
        $adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

        if ($reportId === '') {
            respond_json(422, false, 'report_id is required.');
            exit;
        }

        $result = moderation_review_report($reportId, $status, $adminId, $adminName, $adminNote, $moderationAction, $reason);
        respond_json($result[0] ? 200 : 422, $result[0], $result[1], $result[2]);
        exit;
    }

    if ($requestAction === 'delete_report') {
        if (!$hasAdmin) {
            respond_json(403, false, 'Admin access required.');
            exit;
        }

        $reportId = clean_text($body['report_id'] ?? '');
        $reason = clean_text($body['reason'] ?? '');
        $adminId = (string) ($_SESSION['admin']['admin_id'] ?? '');
        $adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');
        if ($reportId === '') {
            respond_json(422, false, 'report_id is required.');
            exit;
        }
        if ($reason === '') {
            respond_json(422, false, 'reason is required.');
            exit;
        }

        $result = moderation_delete_report($reportId, $adminId, $adminName, $reason);
        respond_json($result[0] ? 200 : 422, $result[0], $result[1], $result[2]);
        exit;
    }

    if ($requestAction === 'moderate_user') {
        if (!$hasAdmin) {
            respond_json(403, false, 'Admin access required.');
            exit;
        }

        $userId = clean_text($body['user_id'] ?? '');
        $status = clean_text($body['status'] ?? 'active');
        $reason = clean_text($body['reason'] ?? '');
        $adminId = (string) ($_SESSION['admin']['admin_id'] ?? '');
        $adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

        if ($userId === '') {
            respond_json(422, false, 'user_id is required.');
            exit;
        }

        $result = moderation_apply_user_action($userId, $status, $reason, $adminId, $adminName);
        respond_json($result[0] ? 200 : 422, $result[0], $result[1], $result[2]);
        exit;
    }

    respond_json(400, false, 'Invalid action.');
    exit;
}

respond_json(405, false, 'Method not allowed.');

function moderation_read_body(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function respond_json(int $statusCode, bool $success, string $message, mixed $data = null): void
{
    http_response_code($statusCode);
    $payload = [
        'success' => $success,
        'message' => $message,
    ];

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload);
}

function moderation_store_report_evidence(string $userId, mixed $fileInput): array
{
    if (empty($fileInput)) {
        return [false, 'Proof photo is required.', null];
    }

    $files = normalize_files_field($fileInput);
    if (count($files) === 0) {
        return [false, 'Proof photo is required.', null];
    }

    $file = $files[0];
    $name = (string) ($file['name'] ?? '');
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)) {
        return [false, 'Proof photo upload failed.', null];
    }

    if ($size <= 0) {
        return [false, 'Proof photo cannot be empty.', null];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return [false, 'Only PNG, JPG, JPEG, or WEBP images are allowed.', null];
    }

    $mime = detect_mime($tmp) ?: '';
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        return [false, 'Invalid proof photo file type.', null];
    }

    $stored = learnloop_store_single_file($file, [
        'context' => 'report_evidence',
        'owner_id' => $userId,
    ]);

    if (!$stored[0]) {
        return [false, $stored[1] ?: 'Failed to save proof photo.', null];
    }

    return [true, 'Proof photo uploaded.', [
        'file_id' => $stored[2]['file_id'],
        'file_name' => $stored[2]['file_name'],
        'file_path' => 'api/moderation.php?action=download_report_evidence&file_id=' . urlencode($stored[2]['file_id']),
        'file_type' => 'image',
        'mime_type' => $stored[2]['mime_type'],
        'file_size' => (int) $stored[2]['file_size'],
    ]];
}
