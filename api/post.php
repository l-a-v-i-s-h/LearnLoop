<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
	http_response_code(401);
	echo json_encode([
		'success' => false
	]);
	exit;
}

$postsCollection = db()->selectCollection('forum_posts');
$commentsCollection = db()->selectCollection('comments');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$apiAction = trim((string) ($_GET['action'] ?? ''));

switch ($requestMethod) {
	case 'POST':
		if ($apiAction === 'comment') {
			create_comment($postsCollection, $commentsCollection);
		} else {
			create_post($postsCollection);
		}
		exit;

	case 'GET':
		get_posts($postsCollection, $commentsCollection);
		exit;

	case 'PUT':
	case 'PATCH':
		if ($apiAction === 'comment') {
			update_comment($commentsCollection);
		} else {
			update_post($postsCollection);
		}
		exit;

	case 'DELETE':
		if ($apiAction === 'comment') {
			delete_comment($commentsCollection);
		} else {
			delete_post($postsCollection, $commentsCollection);
		}
		exit;

	default:
		respond_error(405);
		exit;
}

function create_post($postsCollection): void
{
	$body = get_request_body();

	$title = trim((string) ($body['title'] ?? ''));
	$content = trim((string) ($body['description'] ?? ($body['content'] ?? '')));
	$groupId = trim((string) ($body['group_id'] ?? 'general'));

	if ($title === '' || $content === '') {
		respond_error(422);
		return;
	}

	if (strlen($title) > 150) {
		respond_error(422);
		return;
	}

	if (strlen($content) > 5000) {
		respond_error(422);
		return;
	}

	if ($groupId === '') {
		$groupId = 'general';
	}

	if (strlen($groupId) > 60) {
		respond_error(422);
		return;
	}

	$postId = bin2hex(random_bytes(8));
	$userId = $_SESSION['user']['user_id'];
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$postsCollection->insertOne([
			'post_id' => $postId,
			'group_id' => $groupId,
			'user_id' => $userId,
			'title' => $title,
			'content' => $content,
			'created_at' => $now,
			'updated_at' => $now
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if (!wants_json_response()) {
		header('Location: ../pages/forums.php', true, 303);
		return;
	}

	respond_success([
		'post_id' => $postId,
		'group_id' => $groupId,
		'user_id' => $userId,
		'title' => $title,
		'content' => $content,
		'description' => $content,
		'replies' => [],
		'reply_count' => 0,
		'created_at' => date('Y-m-d H:i:s')
	], 201);
}

function create_comment($postsCollection, $commentsCollection): void
{
	$body = get_request_body();

	$postId = trim((string) ($body['post_id'] ?? ''));
	$content = trim((string) ($body['content'] ?? ''));

	if ($postId === '' || $content === '') {
		respond_error(422);
		return;
	}

	if (strlen($content) > 1000) {
		respond_error(422);
		return;
	}

	try {
		$post = $postsCollection->findOne(['post_id' => $postId]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if (!$post) {
		respond_error(404);
		return;
	}

	$commentId = bin2hex(random_bytes(8));
	$userId = $_SESSION['user']['user_id'];
	$now = new MongoDB\BSON\UTCDateTime();

	try {
		$commentsCollection->insertOne([
			'comment_id' => $commentId,
			'post_id' => $postId,
			'user_id' => $userId,
			'content' => $content,
			'created_at' => $now
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	respond_success([
		'comment_id' => $commentId,
		'post_id' => $postId,
		'user_id' => $userId,
		'content' => $content,
		'text' => $content,
		'created_at' => date('Y-m-d H:i:s')
	], 201);
}

function update_comment($commentsCollection): void
{
	$body = get_request_body();

	$commentId = trim((string) ($body['comment_id'] ?? ($_GET['comment_id'] ?? '')));
	$content = trim((string) ($body['content'] ?? ''));

	if ($commentId === '' || $content === '') {
		respond_error(422);
		return;
	}

	if (strlen($content) > 1000) {
		respond_error(422);
		return;
	}

	$userId = $_SESSION['user']['user_id'];

	try {
		$result = $commentsCollection->updateOne(
			[
				'comment_id' => $commentId,
				'user_id' => $userId
			],
			[
				'$set' => [
					'content' => $content
				]
			]
		);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if ($result->getMatchedCount() === 0) {
		respond_error(404);
		return;
	}

	respond_success([
		'comment_id' => $commentId,
		'content' => $content,
		'text' => $content
	]);
}

function delete_comment($commentsCollection): void
{
	$body = get_request_body();
	$commentId = trim((string) ($body['comment_id'] ?? ($_GET['comment_id'] ?? '')));

	if ($commentId === '') {
		respond_error(422);
		return;
	}

	$userId = $_SESSION['user']['user_id'];

	try {
		$result = $commentsCollection->deleteOne([
			'comment_id' => $commentId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if ($result->getDeletedCount() === 0) {
		respond_error(404);
		return;
	}

	respond_success();
}

function get_posts($postsCollection, $commentsCollection): void
{
	$postId = trim((string) ($_GET['post_id'] ?? ''));

	if ($postId !== '') {
		try {
			$doc = $postsCollection->findOne(['post_id' => $postId]);
		} catch (Exception $e) {
			respond_error(500);
			return;
		}

		if (!$doc) {
			respond_error(404);
			return;
		}

		$replies = get_post_replies($commentsCollection, $postId);
		respond_success(map_post_document($doc, $replies));
		return;
	}

	$groupId = trim((string) ($_GET['group_id'] ?? ''));
	$filter = [];
	if ($groupId !== '') {
		$filter['group_id'] = $groupId;
	}

	try {
		$cursor = $postsCollection->find($filter, [
			'sort' => ['created_at' => -1],
			'limit' => 200
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	$posts = [];
	foreach ($cursor as $doc) {
		$currentPostId = $doc['post_id'] ?? '';
		$replies = get_post_replies($commentsCollection, $currentPostId);
		$posts[] = map_post_document($doc, $replies);
	}

	respond_success($posts);
}

function update_post($postsCollection): void
{
	$body = get_request_body();

	$postId = trim((string) ($body['post_id'] ?? ($_GET['post_id'] ?? '')));
	if ($postId === '') {
		respond_error(422);
		return;
	}

	$titleProvided = array_key_exists('title', $body);
	$contentProvided = array_key_exists('content', $body) || array_key_exists('description', $body);
	$groupProvided = array_key_exists('group_id', $body);

	if (!$titleProvided && !$contentProvided && !$groupProvided) {
		respond_error(422);
		return;
	}

	$updateData = [];

	if ($titleProvided) {
		$title = trim((string) ($body['title'] ?? ''));
		if ($title === '') {
			respond_error(422);
			return;
		}
		if (strlen($title) > 150) {
			respond_error(422);
			return;
		}
		$updateData['title'] = $title;
	}

	if ($contentProvided) {
		$content = trim((string) ($body['description'] ?? ($body['content'] ?? '')));
		if ($content === '') {
			respond_error(422);
			return;
		}
		if (strlen($content) > 5000) {
			respond_error(422);
			return;
		}
		$updateData['content'] = $content;
	}

	if ($groupProvided) {
		$groupId = trim((string) ($body['group_id'] ?? ''));
		if ($groupId === '') {
			$groupId = 'general';
		}
		if (strlen($groupId) > 60) {
			respond_error(422);
			return;
		}
		$updateData['group_id'] = $groupId;
	}

	$updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
	$userId = $_SESSION['user']['user_id'];

	try {
		$result = $postsCollection->updateOne(
			[
				'post_id' => $postId,
				'user_id' => $userId
			],
			[
				'$set' => $updateData
			]
		);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if ($result->getMatchedCount() === 0) {
		respond_error(404);
		return;
	}

	respond_success();
}

function delete_post($postsCollection, $commentsCollection): void
{
	$body = get_request_body();
	$postId = trim((string) ($body['post_id'] ?? ($_GET['post_id'] ?? '')));

	if ($postId === '') {
		respond_error(422);
		return;
	}

	$userId = $_SESSION['user']['user_id'];

	try {
		$post = $postsCollection->findOne([
			'post_id' => $postId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if (!$post) {
		respond_error(404);
		return;
	}

	try {
		$commentsCollection->deleteMany([
			'post_id' => $postId
		]);

		$result = $postsCollection->deleteOne([
			'post_id' => $postId,
			'user_id' => $userId
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if ($result->getDeletedCount() === 0) {
		respond_error(404);
		return;
	}

	respond_success();
}

function map_post_document($doc, array $replies): array
{
	return [
		'post_id' => $doc['post_id'] ?? '',
		'group_id' => $doc['group_id'] ?? '',
		'user_id' => $doc['user_id'] ?? '',
		'title' => $doc['title'] ?? '',
		'content' => $doc['content'] ?? '',
		'description' => $doc['content'] ?? '',
		'replies' => $replies,
		'reply_count' => count($replies),
		'created_at' => format_mongo_date($doc['created_at'] ?? null),
		'updated_at' => format_mongo_date($doc['updated_at'] ?? null)
	];
}

function respond_error(int $statusCode): void
{
	http_response_code($statusCode);
	echo json_encode([
		'success' => false
	]);
}

function respond_success($data = null, int $statusCode = 200): void
{
	http_response_code($statusCode);
	$response = [
		'success' => true
	];

	if ($data !== null) {
		$response['data'] = $data;
	}

	echo json_encode($response);
}

function get_post_replies($commentsCollection, string $postId): array
{
	if ($postId === '') {
		return [];
	}

	try {
		$cursor = $commentsCollection->find(
			['post_id' => $postId],
			['sort' => ['created_at' => 1], 'limit' => 300]
		);
	} catch (Exception $e) {
		return [];
	}

	$replies = [];
	foreach ($cursor as $comment) {
		$text = $comment['content'] ?? '';
		$replies[] = [
			'comment_id' => $comment['comment_id'] ?? '',
			'post_id' => $comment['post_id'] ?? '',
			'user_id' => $comment['user_id'] ?? '',
			'content' => $text,
			'text' => $text,
			'created_at' => format_mongo_date($comment['created_at'] ?? null)
		];
	}

	return $replies;
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

function wants_json_response(): bool
{
	$accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
	if (strpos($accept, 'application/json') !== false) {
		return true;
	}

	$requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
	return $requestedWith === 'xmlhttprequest';
}
?>
