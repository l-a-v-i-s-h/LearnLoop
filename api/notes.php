<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'You must be logged in to use notes API.'
	]);
	exit;
}

$notesCollection = db()->selectCollection('notes');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
	create_note($notesCollection);
	exit;
}

if ($requestMethod === 'GET') {
	if (isset($_GET['action']) && $_GET['action'] === 'download') {
		download_note($notesCollection);
	} else {
		get_notes($notesCollection);
	}
	exit;
}

if ($requestMethod === 'DELETE') {
	delete_note($notesCollection);
	exit;
}

http_response_code(405);
echo json_encode([
	'success' => false,
	'message' => 'Method not allowed. Use GET, POST, or DELETE.'
]);
exit;

function create_note($notesCollection): void
{
	$body = get_request_body();

	$title = trim((string) ($body['title'] ?? ''));
	$content = trim((string) ($body['content'] ?? ''));

	if ($title === '' || $content === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'Title and content are required.'
		]);
		return;
	}

	if (strlen($title) > 120) {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'Title must be 120 characters or less.'
		]);
		return;
	}

	if (strlen($content) > 5000) {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'Content must be 5000 characters or less.'
		]);
		return;
	}

	$noteId = bin2hex(random_bytes(8));
	$userId = $_SESSION['user']['user_id'];
	$decodedContent = json_decode($content, true);
	$groupId = '';
	if (is_array($decodedContent) && !empty($decodedContent['group'])) {
		$groupId = trim((string) $decodedContent['group']);
	}
	if ($groupId === '') {
		$groupId = 'general';
	}

	// Placeholder path string for now since actual binary file upload is not implemented yet.
	$fileUrl = 'notes/' . $userId . '/' . $noteId . '-' . preg_replace('/\s+/', '-', strtolower($title));
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$notesCollection->insertOne([
			'note_id' => $noteId,
			'user_id' => $userId,
			'group_id' => $groupId,
			'file_url' => $fileUrl,
			'uploaded_at' => $now,
			'title' => $title,
			'content' => $content,
			'created_at' => $now,
			'updated_at' => $now
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to add note. Please check note data and try again.'
		]);
		return;
	}

	http_response_code(201);
	echo json_encode([
		'success' => true,
		'message' => 'Note added successfully.',
		'data' => [
			'note_id' => $noteId,
			'title' => $title,
			'content' => $content
		]
	]);
}

function get_notes($notesCollection): void
{
	$userId = $_SESSION['user']['user_id'];

	try {
		$cursor = $notesCollection->find(
			['user_id' => $userId],
			['sort' => ['uploaded_at' => -1, 'created_at' => -1]]
		);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to fetch notes.'
		]);
		return;
	}

	$notes = [];
	foreach ($cursor as $doc) {
		$notes[] = [
			'note_id' => $doc['note_id'] ?? '',
			'title' => $doc['title'] ?? '',
			'content' => $doc['content'] ?? '',
			'created_at' => format_mongo_date($doc['created_at'] ?? ($doc['uploaded_at'] ?? null)),
			'updated_at' => format_mongo_date($doc['updated_at'] ?? null)
		];
	}

	echo json_encode([
		'success' => true,
		'message' => 'Notes fetched successfully.',
		'data' => $notes
	]);
}

function delete_note($notesCollection): void
{
	$body = get_request_body();
	$noteId = trim((string) ($body['note_id'] ?? ($_GET['note_id'] ?? '')));

	if ($noteId === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'note_id is required.'
		]);
		return;
	}

	$userId = $_SESSION['user']['user_id'];

	try {
		$result = $notesCollection->deleteOne([
			'note_id' => $noteId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to delete note.'
		]);
		return;
	}

	if ($result->getDeletedCount() === 0) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'message' => 'Note not found.'
		]);
		return;
	}

	echo json_encode([
		'success' => true,
		'message' => 'Note deleted successfully.'
	]);
}

function download_note($notesCollection): void
{
	$noteId = trim((string) ($_GET['note_id'] ?? ''));

	if ($noteId === '') {
		http_response_code(422);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'success' => false,
			'message' => 'note_id is required.'
		]);
		return;
	}

	$userId = $_SESSION['user']['user_id'];

	try {
		$note = $notesCollection->findOne([
			'note_id' => $noteId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		http_response_code(500);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'success' => false,
			'message' => 'Failed to download note.'
		]);
		return;
	}

	if (!$note) {
		http_response_code(404);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'success' => false,
			'message' => 'Note not found.'
		]);
		return;
	}

	// Generate file content
	$title = $note['title'] ?? 'document';
	$content = $note['content'] ?? '';

	// Parse content to get file info
	$contentData = json_decode($content, true);
	$fileType = $contentData['fileType'] ?? 'txt';

	// Create simple text file for download
	$fileContent = "Title: " . $title . "\n";
	$fileContent .= "Group: " . ($contentData['group'] ?? 'N/A') . "\n";
	$fileContent .= "Date: " . (isset($note['created_at']) ? format_mongo_date($note['created_at']) : date('Y-m-d H:i:s')) . "\n";
	$fileContent .= "---\n\n";
	$fileContent .= "Content: " . $content . "\n";

	// Set headers for download
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . basename($title) . '.' . $fileType . '"');
	header('Content-Length: ' . strlen($fileContent));
	header('Cache-Control: no-cache, no-store, must-revalidate');

	echo $fileContent;
}

function get_request_body(): array
{
	if (!empty($_POST)) {
		return $_POST;
	}

	$rawInput = file_get_contents('php://input');
	if ($rawInput === false || trim($rawInput) === '') {
		return [];
	}

	$jsonBody = json_decode($rawInput, true);
	if (is_array($jsonBody)) {
		return $jsonBody;
	}

	parse_str($rawInput, $formBody);
	if (is_array($formBody)) {
		return $formBody;
	}

	return [];
}

function format_mongo_date($value): string
{
	if ($value instanceof MongoDB\BSON\UTCDateTime) {
		$value = $value->toDateTime();
		return $value->format('Y-m-d H:i:s');
	}

	return '';
}
