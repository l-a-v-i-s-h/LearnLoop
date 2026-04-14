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

if (!function_exists('esc')) {
	function esc($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('clean_text')) {
	function clean_text($value): string
	{
		$text = trim((string) $value);
		$text = strip_tags($text);
		return preg_replace('/\s+/', ' ', $text) ?? '';
	}
}
?>
