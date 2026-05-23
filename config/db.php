<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

const REMEMBER_COOKIE_NAME = 'learnloop_remember';
const REMEMBER_COOKIE_DAYS = 30;

function db(): MongoDB\Database
{
	static $client = null;

	if ($client === null) {
		$client = new MongoDB\Client();
	}

	return $client->selectDatabase('learnloop');
}

function remember_cookie_options(int $expiresAt): array
{
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	return [
		'expires' => $expiresAt,
		'path' => '/',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	];
}

function remember_set_cookie(string $value, int $expiresAt): void
{
	setcookie(REMEMBER_COOKIE_NAME, $value, remember_cookie_options($expiresAt));
}

function remember_clear_cookie(): void
{
	setcookie(REMEMBER_COOKIE_NAME, '', remember_cookie_options(time() - 3600));
}

function remember_issue_token(string $accountType, string $accountId): void
{
	$selector = bin2hex(random_bytes(9));
	$validator = bin2hex(random_bytes(32));
	$validatorHash = hash('sha256', $validator);
	$expiresAt = time() + (REMEMBER_COOKIE_DAYS * 86400);

	$tokens = db()->selectCollection('remember_tokens');
	$tokens->insertOne([
		'selector' => $selector,
		'validator_hash' => $validatorHash,
		'account_type' => $accountType,
		'account_id' => $accountId,
		'expires_at' => new MongoDB\BSON\UTCDateTime($expiresAt * 1000),
		'created_at' => new MongoDB\BSON\UTCDateTime(),
		'last_used_at' => new MongoDB\BSON\UTCDateTime(),
	]);

	remember_set_cookie($selector . ':' . $validator, $expiresAt);
}

function remember_forget_cookie_token(): void
{
	$cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
	if ($cookie === '') {
		return;
	}

	$parts = explode(':', $cookie, 2);
	$selector = $parts[0] ?? '';
	if ($selector !== '') {
		db()->selectCollection('remember_tokens')->deleteOne(['selector' => $selector]);
	}

	remember_clear_cookie();
}

function remember_auto_login(): void
{
	if (!empty($_SESSION['user']) || !empty($_SESSION['admin'])) {
		return;
	}

	$cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
	if ($cookie === '') {
		return;
	}

	$parts = explode(':', $cookie, 2);
	if (count($parts) !== 2) {
		remember_clear_cookie();
		return;
	}

	[$selector, $validator] = $parts;
	if ($selector === '' || $validator === '' || !ctype_xdigit($selector) || !ctype_xdigit($validator)) {
		remember_clear_cookie();
		return;
	}

	$tokens = db()->selectCollection('remember_tokens');
	$token = $tokens->findOne(['selector' => $selector]);
	if (!$token) {
		remember_clear_cookie();
		return;
	}

	$expiresAt = $token['expires_at'] ?? null;
	if (!$expiresAt instanceof MongoDB\BSON\UTCDateTime) {
		$tokens->deleteOne(['selector' => $selector]);
		remember_clear_cookie();
		return;
	}

	$expiresTs = $expiresAt->toDateTime()->getTimestamp();
	if (time() > $expiresTs) {
		$tokens->deleteOne(['selector' => $selector]);
		remember_clear_cookie();
		return;
	}

	$validatorHash = hash('sha256', $validator);
	if (!hash_equals((string) ($token['validator_hash'] ?? ''), $validatorHash)) {
		$tokens->deleteOne(['selector' => $selector]);
		remember_clear_cookie();
		return;
	}

	$accountType = (string) ($token['account_type'] ?? '');
	$accountId = (string) ($token['account_id'] ?? '');
	if ($accountType === '' || $accountId === '') {
		$tokens->deleteOne(['selector' => $selector]);
		remember_clear_cookie();
		return;
	}

	if ($accountType === 'admin') {
		$admins = db()->selectCollection('admin');
		$admin = $admins->findOne(['admin_id' => $accountId]);
		if (!$admin) {
			$tokens->deleteOne(['selector' => $selector]);
			remember_clear_cookie();
			return;
		}

		session_regenerate_id(true);
		$_SESSION['admin'] = [
			'admin_id' => (string) ($admin['admin_id'] ?? $accountId),
			'full_name' => (string) ($admin['full_name'] ?? 'Admin'),
			'email' => (string) ($admin['email'] ?? ''),
		];
	} else {
		$users = db()->selectCollection('users');
		$user = $users->findOne(['user_id' => $accountId]);
		if (!$user) {
			$tokens->deleteOne(['selector' => $selector]);
			remember_clear_cookie();
			return;
		}

		session_regenerate_id(true);
		$_SESSION['user'] = [
			'user_id' => $user['user_id'],
			'full_name' => $user['full_name'],
			'username' => $user['username'],
			'email' => $user['email'],
		];
	}

	$newValidator = bin2hex(random_bytes(32));
	$newHash = hash('sha256', $newValidator);
	$newExpires = time() + (REMEMBER_COOKIE_DAYS * 86400);
	$tokens->updateOne(
		['selector' => $selector],
		[
			'$set' => [
				'validator_hash' => $newHash,
				'expires_at' => new MongoDB\BSON\UTCDateTime($newExpires * 1000),
				'last_used_at' => new MongoDB\BSON\UTCDateTime(),
			],
		]
	);

	remember_set_cookie($selector . ':' . $newValidator, $newExpires);
}

function csrf_token(): string
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function csrf_check(string $token): bool
{
	if (!isset($_SESSION['csrf_token'])) {
		return false;
	}

	return hash_equals($_SESSION['csrf_token'], $token);
}

function clean_text(mixed $value): string
{
	$value = trim($value ?? '');
	$value = str_replace("\0", '', $value);

	return $value;
}

function clean_email(mixed $value): string
{
	$value = clean_text($value);

	return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function esc(mixed $value): string
{
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_input(): string
{
	$token = esc(csrf_token());
	return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

function json_header(): void
{
	header('Content-Type: application/json; charset=UTF-8');
}

function safe_input(mixed $value, int $max = 5000): string
{
	if (is_array($value) || is_object($value)) {
		return '';
	}

	$text = clean_text((string) $value);

	if ($text !== '' && $text[0] === '$') {
		$text = '';
	}

	if (strlen($text) > (int) $max) {
		$text = substr($text, 0, (int) $max);
	}

	return $text;
}

remember_auto_login();

?>
