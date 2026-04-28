<?php

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

function db()
{
	static $client = null;

	if ($client === null) {
		$client = new MongoDB\Client();
	}

	return $client->selectDatabase('learnloop');
}

function csrf_token()
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function csrf_check($token)
{
	if (!isset($_SESSION['csrf_token'])) {
		return false;
	}

	return hash_equals($_SESSION['csrf_token'], $token);
}

function clean_text($value)
{
	$value = trim($value ?? '');
	$value = str_replace("\0", '', $value);

	return $value;
}

function clean_email($value)
{
	$value = clean_text($value);

	return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function esc($value)
{
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_input()
{
	$token = esc(csrf_token());
	return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

function json_header(): void
{
	header('Content-Type: application/json; charset=UTF-8');
}

function safe_input($value, $max = 5000)
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

?>
