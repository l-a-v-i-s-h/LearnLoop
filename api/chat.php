<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'You must be logged in.'
	]);
	exit;
}

$chatCollection = db()->selectCollection('chat_messages');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
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

if ($requestMethod === 'GET') {
	get_messages($chatCollection);
	exit;
}

if ($requestMethod === 'POST') {
	post_message($chatCollection);
	exit;
}

if ($requestMethod === 'PATCH') {
	edit_message($chatCollection);
	exit;
}

if ($requestMethod === 'DELETE') {
	delete_message($chatCollection);
	exit;
}

http_response_code(405);
echo json_encode([
	'success' => false,
	'message' => 'Method not allowed. Use GET, POST, PATCH, or DELETE.'
]);
exit;

function get_messages($chatCollection): void
{
	$group = clean_text($_GET['group'] ?? 'General');

	try {
		$cursor = $chatCollection->find(
			['group' => $group],
			['sort' => ['created_at' => 1]]
		);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to fetch messages.'
		]);
		return;
	}

	$messages = [];
	foreach ($cursor as $doc) {
		$messages[] = [
			'message_id' => $doc['message_id'] ?? '',
			'group' => $doc['group'] ?? '',
			'user_id' => $doc['user_id'] ?? '',
			'sender_name' => $doc['sender_name'] ?? '',
			'type' => $doc['type'] ?? 'text',
			'message' => $doc['message'] ?? '',
			'file_name' => $doc['file_name'] ?? '',
			'file_path' => $doc['file_path'] ?? '',
			'file_size' => (int) ($doc['file_size'] ?? 0),
			'edited' => (bool) ($doc['edited'] ?? false),
			'created_at' => format_mongo_date($doc['created_at'] ?? null)
		];
	}

	echo json_encode([
		'success' => true,
		'message' => 'Messages fetched successfully.',
		'data' => $messages
	]);
}

function post_message($chatCollection): void
{
	$user = $_SESSION['user'];
	$userId = $user['user_id'] ?? '';
	$senderName = clean_text($user['full_name'] ?? 'User');

	$group = clean_text($_POST['group'] ?? '');
	$messageText = clean_text($_POST['message'] ?? '');

	if ($group === '' || ($messageText === '' && empty($_FILES['file']))) {
		$body = read_json_body();
		if ($group === '') {
			$group = clean_text($body['group'] ?? 'General');
		}
		if ($messageText === '') {
			$messageText = clean_text($body['message'] ?? '');
		}
	}

	if ($group === '') {
		$group = 'General';
	}

	$type = 'text';
	$fileName = '';
	$filePath = '';
	$fileSize = 0;

	if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		$upload = $_FILES['file'];
		if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			http_response_code(422);
			echo json_encode([
				'success' => false,
				'message' => 'File upload failed.'
			]);
			return;
		}

		if (!is_allowed_chat_upload((string) ($upload['name'] ?? ''))) {
			http_response_code(422);
			echo json_encode([
				'success' => false,
				'message' => 'Unsupported file type.'
			]);
			return;
		}

		if ((int) ($upload['size'] ?? 0) <= 0 || (int) ($upload['size'] ?? 0) > 26214400) {
			http_response_code(422);
			echo json_encode([
				'success' => false,
				'message' => 'File size must be 25 MB or less.'
			]);
			return;
		}

		$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($upload['name']));
		$storedName = time() . '-' . bin2hex(random_bytes(4)) . '-' . $safeName;

		$uploadDir = __DIR__ . '/../uploads/chat/' . $userId;
		if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'message' => 'Failed to create upload folder.'
			]);
			return;
		}

		$targetPath = $uploadDir . '/' . $storedName;
		if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'message' => 'Failed to upload file.'
			]);
			return;
		}

		$type = 'file';
		$fileName = $upload['name'];
		$filePath = 'uploads/chat/' . $userId . '/' . $storedName;
		$fileSize = (int) ($upload['size'] ?? 0);
		$messageText = '';
	}

	if ($type === 'text' && $messageText === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'Message is required.'
		]);
		return;
	}

	$messageId = bin2hex(random_bytes(8));
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$chatCollection->insertOne([
			'message_id' => $messageId,
			'group' => $group,
			'user_id' => $userId,
			'sender_name' => $senderName,
			'type' => $type,
			'message' => $messageText,
			'file_name' => $fileName,
			'file_path' => $filePath,
			'file_size' => $fileSize,
			'edited' => false,
			'created_at' => $now
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to save message.'
		]);
		return;
	}

	echo json_encode([
		'success' => true,
		'message' => 'Message sent successfully.',
		'data' => [
			'message_id' => $messageId,
			'group' => $group,
			'user_id' => $userId,
			'sender_name' => $senderName,
			'type' => $type,
			'message' => $messageText,
			'file_name' => $fileName,
			'file_path' => $filePath,
			'file_size' => $fileSize,
			'edited' => false,
			'created_at' => format_mongo_date($now)
		]
	]);
}

function edit_message($chatCollection): void
{
	$body = read_json_body();
	$messageId = clean_text($body['message_id'] ?? '');
	$messageText = clean_text($body['message'] ?? '');
	$userId = $_SESSION['user']['user_id'] ?? '';

	if ($messageId === '' || $messageText === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'message_id and message are required.'
		]);
		return;
	}

	try {
		$result = $chatCollection->updateOne(
			[
				'message_id' => $messageId,
				'user_id' => $userId,
				'type' => 'text'
			],
			[
				'$set' => [
					'message' => $messageText,
					'edited' => true
				]
			]
		);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to edit message.'
		]);
		return;
	}

	if ($result->getMatchedCount() === 0) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'message' => 'Message not found.'
		]);
		return;
	}

	echo json_encode([
		'success' => true,
		'message' => 'Message updated.'
	]);
}

function delete_message($chatCollection): void
{
	$body = read_json_body();
	$messageId = clean_text($body['message_id'] ?? '');
	$userId = $_SESSION['user']['user_id'] ?? '';

	if ($messageId === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'message_id is required.'
		]);
		return;
	}

	try {
		$message = $chatCollection->findOne([
			'message_id' => $messageId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to delete message.'
		]);
		return;
	}

	if (!$message) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'message' => 'Message not found.'
		]);
		return;
	}

	if (!empty($message['file_path'])) {
		$path = __DIR__ . '/../' . ltrim((string) $message['file_path'], '/\\');
		if (is_file($path)) {
			@unlink($path);
		}
	}

	try {
		$result = $chatCollection->deleteOne([
			'message_id' => $messageId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to delete message.'
		]);
		return;
	}

	if ($result->getDeletedCount() === 0) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'message' => 'Message not found.'
		]);
		return;
	}

	echo json_encode([
		'success' => true,
		'message' => 'Message deleted.'
	]);
}

function read_json_body(): array
{
	$raw = file_get_contents('php://input');
	if ($raw === false || trim($raw) === '') {
		return [];
	}

	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : [];
}

function format_mongo_date($value): string
{
	if ($value instanceof MongoDB\BSON\UTCDateTime) {
		return $value->toDateTime()->format('Y-m-d H:i:s');
	}
	return '';
}

function is_allowed_chat_upload(string $fileName): bool
{
	$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	return in_array($extension, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg'], true);
}


