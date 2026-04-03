<?php
require_once __DIR__ . '/../includes/function.php';

$action = $_GET['action'] ?? '';

if ($action === 'register') {
	handle_register_process();
}

if ($action === 'login') {
	handle_login_process();
}

http_response_code(400);
echo 'Invalid action.';
