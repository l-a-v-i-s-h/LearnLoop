<?php
require_once __DIR__ . '/../includes/function.php';

$action = $_GET['action'] ?? '';

if ($action === 'register') {
	handle_register_process();
}

if ($action === 'login') {
	handle_login_process();
}

if ($action === 'admin-logout') {
	admin_logout();
}

if ($action === 'profile-update') {
	handle_profile_update_process();
}

if ($action === 'verify-email') {
	handle_verify_email_process();
}

if ($action === 'resend-code') {
	handle_resend_verification_code_process();
}

if ($action === 'recover') {
	handle_recover_password_process();
}

if ($action === 'resend-reset-code') {
	handle_resend_reset_code_process();
}

if ($action === 'reset-password') {
	handle_reset_password_process();
}

if ($action === 'change-pass') {
    handle_pass_change();
}

http_response_code(400);
echo 'Invalid action.';
