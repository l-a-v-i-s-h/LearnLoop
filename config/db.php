<?php

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

function db_name()
{
	return 'learnloop';
}

function db_client()
{
	static $client = null;

	if ($client === null) {
		$client = new MongoDB\Client();
	}

	return $client;
}

function db()
{
	// Using structured queries avoids SQL injection style string building.
	return db_client()->selectDatabase(db_name());
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
?>
