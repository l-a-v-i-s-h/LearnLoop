<?php
require_once __DIR__ . '/../includes/function.php';

$action = $_GET['action'] ?? '';

if ($action === 'register') {
	handle_register_process();
}

if ($action === 'login') {
	handle_login_process();
}

if ($action === 'profile-update') {
	handle_profile_update_process();
}

if ($action === 'verify-email') {
	handle_verify_email_process();
}

if ($action === 'change-pass') {
    handle_pass_change();
}

http_response_code(400);
echo 'Invalid action.';
