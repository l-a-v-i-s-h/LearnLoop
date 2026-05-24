<?php
require_once __DIR__ . '/../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	header('Location: login.php');
	exit;
}

$token = clean_text($_POST['_csrf_token'] ?? '');
if (!csrf_check($token)) {
	http_response_code(419);
	header('Location: login.php');
	exit;
}

session_unset();
session_destroy();

remember_forget_cookie_token();

header('Location: login.php');
exit;
?>
