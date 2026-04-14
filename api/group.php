<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
	respond(401, false, 'You must be logged in to use groups API.');
	exit;
}

$groups = db()->selectCollection('groups');
$userId = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = read_body();

if ($method === 'POST') {
	$name = clean_text($body['group_name'] ?? ($body['name'] ?? ''));
	$subject = clean_text($body['subject'] ?? '');
	$description = clean_text($body['description'] ?? '');

	if ($name === '' || $subject === '') {
		respond(422, false, 'group_name and subject are required.');
		exit;
	}

	if (strlen($name) > 100 || strlen($subject) > 100 || strlen($description) > 500) {
		respond(422, false, 'Max length: group_name 100, subject 100, description 500.');
		exit;
	}

	if ($groups->findOne(['user_id' => $userId, 'group_name' => $name])) {
		respond(409, false, 'You already created a group with this name.');
		exit;
	}

	$groupId = bin2hex(random_bytes(8));
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$groups->insertOne([
			'group_id' => $groupId,
			'user_id' => $userId,
			'owner_name' => $_SESSION['user']['full_name'] ?? '',
			'group_name' => $name,
			'subject' => $subject,
			'description' => $description,
			'created_at' => $now
		]);
	} catch (Exception $e) {
		respond(500, false, 'Failed to create group.');
		exit;
	}

	respond(201, true, 'Group created successfully.', [
		'group_id' => $groupId,
		'group_name' => $name,
		'subject' => $subject,
		'description' => $description
	]);
	exit;
}

if ($method === 'GET') {
	$list = [];
	try {
		$cursor = $groups->find(['user_id' => $userId], ['sort' => ['created_at' => -1]]);
		foreach ($cursor as $doc) {
			$created = '';
			if (isset($doc['created_at']) && $doc['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
				$created = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
			}
			$list[] = [
				'group_id' => $doc['group_id'] ?? '',
				'group_name' => $doc['group_name'] ?? '',
				'subject' => $doc['subject'] ?? '',
				'description' => $doc['description'] ?? '',
				'owner_name' => $doc['owner_name'] ?? '',
				'created_at' => $created
			];
		}
	} catch (Exception $e) {
		respond(500, false, 'Failed to fetch groups.');
		exit;
	}

	respond(200, true, 'Groups fetched successfully.', $list);
	exit;
}

if ($method === 'DELETE') {
	$groupId = clean_text($body['group_id'] ?? ($_GET['group_id'] ?? ''));
	if ($groupId === '') {
		respond(422, false, 'group_id is required.');
		exit;
	}

	try {
		$result = $groups->deleteOne(['group_id' => $groupId, 'user_id' => $userId]);
	} catch (Exception $e) {
		respond(500, false, 'Failed to delete group.');
		exit;
	}

	if ($result->getDeletedCount() === 0) {
		respond(404, false, 'Group not found.');
		exit;
	}

	respond(200, true, 'Group deleted successfully.');
	exit;
}

respond(405, false, 'Method not allowed. Use GET, POST, or DELETE.');

function read_body(): array
{
	if (!empty($_POST)) {
		return $_POST;
	}
	$raw = file_get_contents('php://input');
	if (!$raw || trim($raw) === '') {
		return [];
	}
	$data = json_decode($raw, true);
	if (is_array($data)) {
		return $data;
	}
	parse_str($raw, $data);
	return is_array($data) ? $data : [];
}

function respond(int $code, bool $ok, string $message, $data = null): void
{
	http_response_code($code);
	$out = ['success' => $ok, 'message' => $message];
	if ($data !== null) {
		$out['data'] = $data;
	}
	echo json_encode($out);
}
?>
