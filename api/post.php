<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

$isForumReader = isset($_SESSION['user']['user_id']) || isset($_SESSION['admin']['admin_id']);
$isForumWriter = isset($_SESSION['user']['user_id']);
$isForumAdmin = isset($_SESSION['admin']['admin_id']);

if (!$isForumReader) {
	http_response_code(401);
	echo json_encode([
		'success' => false
	]);
	exit;
}

$postsCollection = db()->selectCollection('forum_posts');
$commentsCollection = db()->selectCollection('comments');
$usersCollection = db()->selectCollection('users');
$pusher = build_pusher();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$apiAction = trim((string) ($_GET['action'] ?? ''));

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
	if (!$isForumWriter) {
		respond_error(401);
		exit;
	}

	if ($apiAction === 'comment') {
		create_comment($postsCollection, $commentsCollection);
	} else {
		create_post($postsCollection, $usersCollection);
	}
	exit;
}

if ($requestMethod === 'GET') {
	if ($apiAction === 'recent') {
		get_recent_posts($postsCollection, $commentsCollection, $usersCollection);
	} else {
		get_posts($postsCollection, $commentsCollection, $usersCollection);
	}
	exit;
}

if ($requestMethod === 'PUT' || $requestMethod === 'PATCH') {
	if (!$isForumWriter) {
		respond_error(401);
		exit;
	}

	if ($apiAction === 'comment') {
		update_comment($commentsCollection);
	} else {
		update_post($postsCollection);
	}
	exit;
}

if ($requestMethod === 'DELETE') {
	if (!$isForumWriter && !$isForumAdmin) {
		respond_error(401);
		exit;
	}

	if ($apiAction === 'comment') {
		delete_comment($commentsCollection);
	} else {
		delete_post($postsCollection, $commentsCollection);
	}
	exit;
}

respond_error(405);
exit;

function create_post(mixed $postsCollection, mixed $usersCollection): void
{
	$body = get_request_body();

	$title = body_value($body, 'title');
	$content = first_body_value($body, ['description', 'content']);
	$groupId = body_value($body, 'group_id', 'general');

	if (!require_value($title) || !require_value($content) || !require_max_len($title, 150)) {
		return;
	}

	if ($groupId === '') {
		$groupId = 'general';
	}

	if (!require_max_len($groupId, 60)) {
		return;
	}

	$postId = bin2hex(random_bytes(8));
	$userId = current_user_id();
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

	$authorName = get_post_author_name($usersCollection, $userId);

	trigger_forum_event('post-created', [
		'post_id' => $postId,
		'group_id' => $groupId,
		'user_id' => $userId,
		'title' => $title,
		'content' => $content,
		'created_at' => format_mongo_date($now)
	]);

	if (!wants_json_response()) {
		header('Location: ../pages/forums.php', true, 303);
		return;
	}

	respond_success([
		'post_id' => $postId,
		'group_id' => $groupId,
		'user_id' => $userId,
		'user_name' => $authorName,
		'title' => $title,
		'content' => $content,
		'description' => $content,
		'replies' => [],
		'reply_count' => 0,
		'created_at' => date('Y-m-d H:i:s')
	], 201);
}

function create_comment(mixed $postsCollection, mixed $commentsCollection): void
{
	$body = get_request_body();

	$postId = body_value($body, 'post_id');
	$content = body_value($body, 'content');

	if (!require_value($postId) || !require_value($content) || !require_max_len($content, 1000)) {
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
	$userId = current_user_id();
	$now = new MongoDB\BSON\UTCDateTime();
	$displayName = trim((string) ($_SESSION['user']['full_name'] ?? $_SESSION['user']['username'] ?? 'User'));

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

	trigger_forum_event('reply-created', [
		'post_id' => $postId,
		'comment_id' => $commentId,
		'user_id' => $userId,
		'content' => $content,
		'created_at' => format_mongo_date($now)
	]);

	respond_success([
		'comment_id' => $commentId,
		'post_id' => $postId,
		'user_id' => $userId,
		'user_name' => $displayName,
		'content' => $content,
		'text' => $content,
		'created_at' => date('Y-m-d H:i:s')
	], 201);
}

function update_comment(mixed $commentsCollection): void
{
	$body = get_request_body();

	$commentId = input_value($body, 'comment_id');
	$content = body_value($body, 'content');

	if (!require_value($commentId) || !require_value($content) || !require_max_len($content, 1000)) {
		return;
	}

	$userId = current_user_id();

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

	trigger_forum_event('reply-updated', [
		'comment_id' => $commentId,
		'user_id' => $userId,
		'content' => $content
	]);

	respond_success([
		'comment_id' => $commentId,
		'content' => $content,
		'text' => $content
	]);
}

function delete_comment(mixed $commentsCollection): void
{
	$body = get_request_body();
	$commentId = input_value($body, 'comment_id');

	if (!require_value($commentId)) {
		return;
	}

	$userId = current_user_id();
	$isAdmin = is_admin_session();

	try {
		$filter = ['comment_id' => $commentId];
		if (!$isAdmin) {
			$filter['user_id'] = $userId;
		}

		$result = $commentsCollection->deleteOne($filter);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	if ($result->getDeletedCount() === 0) {
		respond_error(404);
		return;
	}

	trigger_forum_event('reply-deleted', [
		'comment_id' => $commentId,
		'user_id' => $userId
	]);

	respond_success();
}

function get_posts(mixed $postsCollection, mixed $commentsCollection, mixed $usersCollection): void
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

		$replies = get_post_replies($commentsCollection, $usersCollection, $postId);
		$authorName = get_post_author_name($usersCollection, (string) ($doc['user_id'] ?? ''));
		respond_success(map_post_document($doc, $replies, $authorName));
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
		$replies = get_post_replies($commentsCollection, $usersCollection, $currentPostId);
		$authorName = get_post_author_name($usersCollection, (string) ($doc['user_id'] ?? ''));
		$posts[] = map_post_document($doc, $replies, $authorName);
	}

	respond_success($posts);
}

function update_post(mixed $postsCollection): void
{
	$body = get_request_body();

	$postId = input_value($body, 'post_id');
	if (!require_value($postId)) {
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
		$title = body_value($body, 'title');
		if (!require_value($title) || !require_max_len($title, 150)) {
			return;
		}
		$updateData['title'] = $title;
	}

	if ($contentProvided) {
		$content = first_body_value($body, ['description', 'content']);
		if (!require_value($content)) {
			return;
		}
		$updateData['content'] = $content;
	}

	if ($groupProvided) {
		$groupId = body_value($body, 'group_id');
		if ($groupId === '') {
			$groupId = 'general';
		}
		if (!require_max_len($groupId, 60)) {
			return;
		}
		$updateData['group_id'] = $groupId;
	}

	$updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
	$userId = current_user_id();

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

	$updateData['post_id'] = $postId;
	$updateData['user_id'] = $userId;
	trigger_forum_event('post-updated', $updateData);

	respond_success();
}

function delete_post(mixed $postsCollection, mixed $commentsCollection): void
{
	$body = get_request_body();
	$postId = input_value($body, 'post_id');

	if (!require_value($postId)) {
		return;
	}

	$userId = current_user_id();

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

	trigger_forum_event('post-deleted', [
		'post_id' => $postId,
		'user_id' => $userId
	]);

	respond_success();
}

function map_post_document(mixed $doc, array $replies, string $authorName = ''): array
{
	return [
		'post_id' => $doc['post_id'] ?? '',
		'group_id' => $doc['group_id'] ?? '',
		'user_id' => $doc['user_id'] ?? '',
		'user_name' => $authorName,
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

function respond_success(mixed $data = null, int $statusCode = 200): void
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

function get_post_author_name(mixed $usersCollection, string $userId): string
{
	if ($userId === '') {
		return '';
	}

	try {
		$user = $usersCollection->findOne(['user_id' => $userId]);
		if (!$user) {
			return '';
		}

		return trim((string) ($user['full_name'] ?? $user['username'] ?? ''));
	} catch (Exception $e) {
		return '';
	}
}

function get_post_replies(mixed $commentsCollection, mixed $usersCollection, string $postId): array
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
		$replyUserName = get_post_author_name($usersCollection, (string) ($comment['user_id'] ?? ''));

		$replies[] = [
			'comment_id' => $comment['comment_id'] ?? '',
			'post_id' => $comment['post_id'] ?? '',
			'user_id' => $comment['user_id'] ?? '',
			'user_name' => $replyUserName,
			'content' => $text,
			'text' => $text,
			'created_at' => format_mongo_date($comment['created_at'] ?? null)
		];
	}

	return $replies;
}

function get_recent_posts(mixed $postsCollection, mixed $commentsCollection, mixed $usersCollection, int $limit = 3): void
{
	$requestedLimit = (int) ($_GET['limit'] ?? $limit);
	if ($requestedLimit < 1) {
		$requestedLimit = $limit;
	}
	if ($requestedLimit > 100) {
		$requestedLimit = 100;
	}

	try {
		$cursor = $postsCollection->find([], [
			'sort' => ['created_at' => -1],
			'limit' => $requestedLimit
		]);
	} catch (Exception $e) {
		respond_error(500);
		return;
	}

	$posts = [];
	foreach ($cursor as $doc) {
		$currentPostId = $doc['post_id'] ?? '';
		$replies = get_post_replies($commentsCollection, $usersCollection, $currentPostId);
		$authorName = get_post_author_name($usersCollection, (string) ($doc['user_id'] ?? ''));
		$posts[] = map_post_document($doc, $replies, $authorName);
	}

	respond_success($posts);
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

function trigger_forum_event(string $eventName, array $payload): void
{
	global $pusher;

	if (!$pusher) {
		return;
	}

	try {
		$pusher->trigger('forum-channel', $eventName, $payload);
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

function current_user_id(): string
{
	if (isset($_SESSION['user']['user_id'])) {
		return (string) $_SESSION['user']['user_id'];
	}

	return (string) ($_SESSION['admin']['admin_id'] ?? '');
}

function is_admin_session(): bool
{
	return isset($_SESSION['admin']['admin_id']);
}

function body_value(array $body, string $key, string $default = ''): string
{
	return safe_input($body[$key] ?? $default);
}

function first_body_value(array $body, array $keys, string $default = ''): string
{
	foreach ($keys as $key) {
		if (array_key_exists($key, $body)) {
			return safe_input($body[$key]);
		}
	}

	return safe_input($default);
}

function input_value(array $body, string $key, string $default = ''): string
{
	if (array_key_exists($key, $body)) {
		return safe_input($body[$key], 150);
	}

	return safe_input($_GET[$key] ?? $default, 150);
}

function require_value(string $value): bool
{
	if ($value !== '') {
		return true;
	}

	respond_error(422);
	return false;
}

function require_max_len(string $value, int $max): bool
{
	if (strlen($value) <= $max) {
		return true;
	}

	respond_error(422);
	return false;
}


?>
