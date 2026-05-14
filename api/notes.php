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
$groupsCollection = db()->selectCollection('groups');
$groupMembersCollection = db()->selectCollection('group_members');
$chatCollection = db()->selectCollection('chat_messages');
$pusher = build_pusher();
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

if ($requestMethod === 'POST') {
	create_note($notesCollection);
	exit;
}

if ($requestMethod === 'GET') {
	if (isset($_GET['action']) && $_GET['action'] === 'download') {
		download_note($notesCollection);
	} elseif (isset($_GET['action']) && $_GET['action'] === 'count') {
		get_notes_count($notesCollection);
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

function create_note(mixed $notesCollection): void
{
	$body = get_request_body();

	$title = safe_input($body['title'] ?? '', 120);
	$content = safe_input($body['content'] ?? '', 5000);

	if ($title === '' || $content === '') {
		http_response_code(422);
		echo json_encode([
			'success' => false,
			'message' => 'Title and content are required.'
		]);
		return;
	}

	$noteId = bin2hex(random_bytes(8));
	$userId = $_SESSION['user']['user_id'];
	$decodedContent = json_decode($content, true);
	$groupId = '';
	$groupName = 'Public';
	$visibility = 'public';
	$fileType = 'txt';
	if (is_array($decodedContent)) {
		$groupId = trim((string) ($decodedContent['groupId'] ?? $decodedContent['group_id'] ?? ''));
		$groupName = trim((string) ($decodedContent['groupName'] ?? $decodedContent['group'] ?? 'Public'));
		$visibility = trim((string) ($decodedContent['visibility'] ?? ''));
		$fileType = trim((string) ($decodedContent['fileType'] ?? 'txt')) ?: 'txt';
	}

	if ($groupId === '' || $visibility === 'public') {
		$groupId = 'public';
		$groupName = 'Public';
		$visibility = 'public';
	} else {
		$group = $GLOBALS['groupsCollection']->findOne(['group_id' => $groupId]);
		if (!$group) {
			http_response_code(404);
			echo json_encode([
				'success' => false,
				'message' => 'Group not found.'
			]);
			return;
		}

		$isOwner = (($group['user_id'] ?? '') === $userId);
		$isMember = $GLOBALS['groupMembersCollection']->findOne([
			'group_id' => $groupId,
			'user_id' => $userId,
			'role' => 'member'
		]);

		if (!$isOwner && !$isMember) {
			http_response_code(403);
			echo json_encode([
				'success' => false,
				'message' => 'You do not have permission to share to this group.'
			]);
			return;
		}

		$groupName = trim((string) ($group['group_name'] ?? $groupName)) ?: 'Shared group';
		$visibility = 'private_group';
	}

	// Placeholder path string for now since actual binary file upload is not implemented yet.
	$fileUrl = 'notes/' . $userId . '/' . $noteId . '-' . preg_replace('/\s+/', '-', strtolower($title));
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$notesCollection->insertOne([
			'note_id' => $noteId,
			'user_id' => $userId,
			'group_id' => $groupId,
			'group_name' => $groupName,
			'visibility' => $visibility,
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

	trigger_note_event('note-created', [
		'user_id' => $userId,
		'note_id' => $noteId,
		'title' => $title,
		'content' => $content,
		'group_id' => $groupId,
		'group_name' => $groupName,
		'visibility' => $visibility,
		'created_at' => format_mongo_date($now)
	]);

	if ($visibility === 'private_group' && $groupId !== 'public') {
		$postToChat = $GLOBALS['chatCollection'] ?? null;
		if ($postToChat) {
			try {
				$chatMessageId = bin2hex(random_bytes(8));
				$postToChat->insertOne([
					'message_id' => $chatMessageId,
					'group' => $groupName,
					'user_id' => $userId,
					'sender_name' => $_SESSION['user']['full_name'] ?? 'User',
					'type' => 'note',
					'message' => 'Shared a study note',
					'note_id' => $noteId,
					'note_title' => $title,
					'note_group_id' => $groupId,
					'note_group_name' => $groupName,
					'note_visibility' => $visibility,
					'file_name' => '',
					'file_path' => '',
					'file_size' => 0,
					'edited' => false,
					'created_at' => $now
				]);
			} catch (Exception $e) {
				// Do not fail note upload if chat post fails.
			}
		}
	}

	http_response_code(201);
	echo json_encode([
		'success' => true,
		'message' => 'Note added successfully.',
		'data' => [
			'note_id' => $noteId,
			'title' => $title,
			'content' => $content,
			'group_id' => $groupId,
			'group_name' => $groupName,
			'visibility' => $visibility,
			'user_id' => $userId
		]
	]);
}

function get_notes(mixed $notesCollection): void
{
	$userId = $_SESSION['user']['user_id'];

	try {
		$cursor = $notesCollection->find(
			[
				'$or' => [
					['visibility' => 'public'],
					['user_id' => $userId]
				]
			],
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
		$visibility = $doc['visibility'] ?? (($doc['group_id'] ?? 'public') === 'public' ? 'public' : 'private_group');
		$isOwn = (($doc['user_id'] ?? '') === $userId);
		if ($visibility !== 'public' && !$isOwn) {
			continue;
		}

		$notes[] = [
			'note_id' => $doc['note_id'] ?? '',
			'user_id' => $doc['user_id'] ?? '',
			'title' => $doc['title'] ?? '',
			'content' => $doc['content'] ?? '',
			'group_id' => $doc['group_id'] ?? 'public',
			'group_name' => $doc['group_name'] ?? ($visibility === 'public' ? 'Public' : 'Shared group'),
			'visibility' => $visibility,
			'can_delete' => $isOwn,
			'can_download' => ($visibility === 'public' || $isOwn),
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

function delete_note(mixed $notesCollection): void
{
	$body = get_request_body();
	$noteId = safe_input($body['note_id'] ?? ($_GET['note_id'] ?? ''), 80);

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

	trigger_note_event('note-deleted', [
		'user_id' => $userId,
		'note_id' => $noteId
	]);
}

function download_note(mixed $notesCollection): void
{
	$noteId = safe_input($_GET['note_id'] ?? '', 80);

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
	$groupsCollection = db()->selectCollection('groups');
	$groupMembersCollection = db()->selectCollection('group_members');

	try {
		$note = $notesCollection->findOne([
			'note_id' => $noteId
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

	$visibility = $note['visibility'] ?? (($note['group_id'] ?? 'public') === 'public' ? 'public' : 'private_group');
	$isOwner = (($note['user_id'] ?? '') === $userId);

	if ($visibility !== 'public' && !$isOwner) {
		$groupId = $note['group_id'] ?? '';
		$group = $groupId !== '' ? $groupsCollection->findOne(['group_id' => $groupId]) : null;
		if (!$group) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode([
				'success' => false,
				'message' => 'You do not have access to this note.'
			]);
			return;
		}

		$isGroupOwner = (($group['user_id'] ?? '') === $userId);
		$isGroupMember = $groupMembersCollection->findOne([
			'group_id' => $groupId,
			'user_id' => $userId,
			'role' => 'member'
		]);

		if (!$isGroupOwner && !$isGroupMember) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode([
				'success' => false,
				'message' => 'You do not have access to this note.'
			]);
			return;
		}
	}

	// Generate file content
	$title = $note['title'] ?? 'document';
	$content = $note['content'] ?? '';

	// Parse content to get file info
	$contentData = json_decode($content, true);
	$fileType = $contentData['fileType'] ?? 'txt';
	$groupLabel = $note['group_name'] ?? ($contentData['groupName'] ?? ($visibility === 'public' ? 'Public' : 'Shared group'));

	// Create simple text file for download
	$fileContent = "Title: " . $title . "\n";
	$fileContent .= "Group: " . $groupLabel . "\n";
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

function build_pusher(): ?Pusher\Pusher
{
	try {
		$options = [
			'cluster' => 'ap2',
			'useTLS' => true
		];

		return new Pusher\Pusher(
			'14db4509a104fa2c4d52',
			'22eeaeff5739ab77e4cc',
			'2150170',
			$options
		);
	} catch (Exception $e) {
		return null;
	}
}

function trigger_note_event(string $eventName, array $payload): void
{
	global $pusher;

	if (!$pusher) {
		return;
	}

	try {
		$pusher->trigger('notes-channel', $eventName, $payload);
	} catch (Exception $e) {
		// Ignore pusher failures so the API still works.
	}
}

function format_mongo_date(mixed $value): string
{
	if ($value instanceof MongoDB\BSON\UTCDateTime) {
		$value = $value->toDateTime();
		return $value->format('Y-m-d H:i:s');
	}

	return '';

function get_notes_count(mixed $notesCollection): void
{
	$userId = $_SESSION['user']['user_id'];

	try {
		$userNotesCount = $notesCollection->countDocuments(['user_id' => $userId]);
		
		http_response_code(200);
		echo json_encode([
			'success' => true,
			'message' => 'Notes count fetched successfully.',
			'data' => [
				'count' => $userNotesCount
			]
		]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to fetch notes count.'
		]);
	}
}
}

