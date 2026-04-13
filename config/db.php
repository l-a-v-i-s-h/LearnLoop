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
?>
