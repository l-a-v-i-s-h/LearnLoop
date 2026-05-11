<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function generate_verification_code(): string
{
    $code = rand(100000, 999999);
    return (string) $code;
}

function ensure_ttl_index(): void
{
    static $isCreated = false;

    if ($isCreated) {
        return;
    }

    db()->selectCollection('email_verifications')->createIndex(
        ['expires_at' => 1],
        ['expireAfterSeconds' => 0, 'name' => 'email_verifications_expires_at_ttl']
    );

    $isCreated = true;
}

function send_verification_email(string $email, string $fullname): bool
{
    $verificationCode = generate_verification_code();
    $currentTime = time();
    $expiryTime = $currentTime + (15 * 60);
    
    $verifications = db()->selectCollection('email_verifications');
    
    try {
        ensure_ttl_index();

        $verifications->deleteMany(['email' => $email]);
        $verifications->insertOne([
            'email' => $email,
            'code' => $verificationCode,
            'created_at' => new MongoDB\BSON\UTCDateTime($currentTime * 1000),
            'expires_at' => new MongoDB\BSON\UTCDateTime($expiryTime * 1000),
        ]);
    } catch (Exception $e) {
        $_SESSION['error'] = 'Could not save verification code. Please try again.';
        return false;
    }
    
    $subject = 'LearnLoop - Email Verification Code';

    $message = '<html><body style="margin:0;padding:0;background:#f4f7fb;">';
    $message .= '<div style="font-family:Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);padding:28px 16px;">';
    $message .= '<div style="max-width:640px;margin:0 auto;border:1px solid #d9e3f2;border-radius:16px;overflow:hidden;background:#ffffff;box-shadow:0 10px 30px rgba(15,23,42,0.08);">';
    $message .= '<div style="padding:28px 24px 18px;text-align:center;background:linear-gradient(180deg,#f7fbff 0%,#ffffff 100%);border-bottom:1px solid #e5ecf5;">';
    $message .= '<div style="font-size:34px;font-weight:700;line-height:1;color:#1d2f57;letter-spacing:0.2px;">LearnL<span style="color:#d6ff2f;text-shadow:0 0 10px rgba(214,255,47,0.85);">∞</span>op</div>';
    $message .= '</div>';
    $message .= '<div style="padding:28px 32px 30px;color:#20304a;">';
    $message .= '<div style="font-size:16px;line-height:1.55;color:#25364d;margin:0 0 18px;">';
    $message .= '<p style="margin:0 0 10px;">Hello ' . esc($fullname) . ',</p>';
    $message .= '<p style="margin:0;">We received a request to verify your LearnLoop account. Use the verification code below to secure your workspace.</p>';
    $message .= '</div>';
    $message .= '<div style="background:linear-gradient(180deg,#f4f9ff 0%,#eaf3ff 100%);border:1px solid #cfdff2;border-radius:14px;padding:22px 18px 18px;text-align:center;box-shadow:inset 0 1px 0 rgba(255,255,255,0.8);">';
    $message .= '<div style="font-size:18px;font-weight:700;color:#7a97b7;margin-bottom:10px;">Verification Code</div>';
    $message .= '<div style="font-size:48px;font-weight:800;letter-spacing:6px;line-height:1;color:#2e4f92;">' . $verificationCode . '</div>';
    $message .= '<div style="margin-top:16px;">';
    $message .= '<span style="display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid #c8d5ea;background:#fff;color:#334155;font-size:14px;font-weight:600;">Copy Code</span>';
    $message .= '</div>';
    $message .= '</div>';
    $message .= '<p style="margin:18px 0 10px;text-align:center;font-size:18px;font-weight:700;color:#1f2937;">Expires in 15 minutes</p>';
    $message .= '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;text-align:left;">If you did not request this, you can safely ignore this email. Your workspace remains secure.</p>';
    $message .= '<div style="border-top:1px solid #e3e8f0;padding-top:14px;text-align:center;color:#6b7280;font-size:12px;">&copy; 2026 LearnLoop. Empowering collaboration.</div>';
    $message .= '</div></div></div></body></html>';
    
    // Send via PHPMailer
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bimalkandel468@gmail.com';
        $mail->Password   = 'aubh sawb jqwi anqm';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom('bimalkandel468@gmail.com', 'LearnLoop');
        $mail->addAddress($email, $fullname);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $message;
        $mail->AltBody = 'Your verification code is: ' . $verificationCode . ' (expires in 15 minutes)';
        
        if ($mail->send()) {
            return true;
        }

        $_SESSION['error'] = 'Email not sent. Please check your email settings.';
        return false;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Email send failed. Please try again.';
        return false;
    }
}

function ensure_password_reset_ttl_index(): void
{
    static $isCreated = false;

    if ($isCreated) {
        return;
    }

    db()->selectCollection('password_resets')->createIndex(
        ['expires_at' => 1],
        ['expireAfterSeconds' => 0, 'name' => 'password_resets_expires_at_ttl']
    );

    $isCreated = true;
}

function send_password_reset_email(string $email, string $fullname): bool
{
    $resetCode = generate_verification_code();
    $currentTime = time();
    $expiryTime = $currentTime + (15 * 60);

    $resets = db()->selectCollection('password_resets');

    try {
        ensure_password_reset_ttl_index();

        $resets->deleteMany(['email' => $email]);
        $resets->insertOne([
            'email' => $email,
            'code' => $resetCode,
            'created_at' => new MongoDB\BSON\UTCDateTime($currentTime * 1000),
            'expires_at' => new MongoDB\BSON\UTCDateTime($expiryTime * 1000),
        ]);
    } catch (Exception $e) {
        $_SESSION['error'] = 'Could not save reset code. Please try again.';
        return false;
    }

    $subject = 'LearnLoop - Password Reset Code';

    $message = '<html><body style="margin:0;padding:0;background:#f4f7fb;">';
    $message .= '<div style="font-family:Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);padding:28px 16px;">';
    $message .= '<div style="max-width:640px;margin:0 auto;border:1px solid #d9e3f2;border-radius:16px;overflow:hidden;background:#ffffff;box-shadow:0 10px 30px rgba(15,23,42,0.08);">';
    $message .= '<div style="padding:28px 24px 18px;text-align:center;background:linear-gradient(180deg,#f7fbff 0%,#ffffff 100%);border-bottom:1px solid #e5ecf5;">';
    $message .= '<div style="font-size:34px;font-weight:700;line-height:1;color:#1d2f57;letter-spacing:0.2px;">LearnL<span style="color:#d6ff2f;text-shadow:0 0 10px rgba(214,255,47,0.85);">∞</span>op</div>';
    $message .= '</div>';
    $message .= '<div style="padding:28px 32px 30px;color:#20304a;">';
    $message .= '<div style="font-size:16px;line-height:1.55;color:#25364d;margin:0 0 18px;">';
    $message .= '<p style="margin:0 0 10px;">Hello ' . esc($fullname) . ',</p>';
    $message .= '<p style="margin:0;">We received a request to reset your LearnLoop account password. Use the verification code below to secure your workspace.</p>';
    $message .= '</div>';
    $message .= '<div style="background:linear-gradient(180deg,#f4f9ff 0%,#eaf3ff 100%);border:1px solid #cfdff2;border-radius:14px;padding:22px 18px 18px;text-align:center;box-shadow:inset 0 1px 0 rgba(255,255,255,0.8);">';
    $message .= '<div style="font-size:18px;font-weight:700;color:#7a97b7;margin-bottom:10px;">Verification Code</div>';
    $message .= '<div style="font-size:48px;font-weight:800;letter-spacing:6px;line-height:1;color:#2e4f92;">' . $resetCode . '</div>';
    $message .= '<div style="margin-top:16px;">';
    $message .= '<span style="display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid #c8d5ea;background:#fff;color:#334155;font-size:14px;font-weight:600;">Copy Code</span>';
    $message .= '</div>';
    $message .= '</div>';
    $message .= '<p style="margin:18px 0 10px;text-align:center;font-size:18px;font-weight:700;color:#1f2937;">Expires in 15 minutes</p>';
    $message .= '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;text-align:left;">If you did not request this, you can safely ignore this email. Your workspace remains secure.</p>';
    $message .= '<div style="border-top:1px solid #e3e8f0;padding-top:14px;text-align:center;color:#6b7280;font-size:12px;">&copy; 2026 LearnLoop. Empowering collaboration.</div>';
    $message .= '</div></div></div></body></html>';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bimalkandel468@gmail.com';
        $mail->Password   = 'aubh sawb jqwi anqm';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('bimalkandel468@gmail.com', 'LearnLoop');
        $mail->addAddress($email, $fullname);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $message;
        $mail->AltBody = 'Your password reset code is: ' . $resetCode . ' (expires in 15 minutes)';

        if ($mail->send()) {
            return true;
        }

        $_SESSION['error'] = 'Email not sent. Please check your email settings.';
        return false;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Email send failed. Please try again.';
        return false;
    }
}

function handle_verify_email_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/verification.php');
        exit;
    }
    
    $email = $_SESSION['temp_email'] ?? '';
    $userId = $_SESSION['user_id_pending'] ?? '';
    
    if ($email === '' || $userId === '') {
        $_SESSION['error'] = 'Session expired. Please register again.';
        header('Location: ../pages/register.php');
        exit;
    }
    
    // Combine 6 digits into one code
    $code = '';
    for ($i = 1; $i <= 6; $i++) {
        $digit = $_POST['code_' . $i] ?? '';
        $digit = clean_text($digit);
        
        if (!is_numeric($digit) || strlen($digit) !== 1) {
            $_SESSION['error'] = 'Please enter a valid 6-digit code.';
            header('Location: ../pages/verification.php');
            exit;
        }
        
        $code .= $digit;
    }
    
    $verifications = db()->selectCollection('email_verifications');
    $verificationRecord = $verifications->findOne(['email' => $email]);
    
    if (!$verificationRecord) {
        $_SESSION['error'] = 'No verification code found. Please register again.';
        header('Location: ../pages/register.php');
        exit;
    }
    
    // Check if code expired
    $currentTime = time() * 1000;
    $expiresAt = $verificationRecord['expires_at']->toDateTime()->getTimestamp() * 1000;
    
    if ($currentTime > $expiresAt) {
        $_SESSION['error'] = 'Verification code expired. Please register again.';
        $verifications->deleteOne(['email' => $email]);
        unset($_SESSION['temp_email']);
        unset($_SESSION['user_id_pending']);
        header('Location: ../pages/register.php');
        exit;
    }
    
    // Verify code matches
    if (!hash_equals($verificationRecord['code'], $code)) {
        $_SESSION['error'] = 'Invalid verification code. Please try again.';
        header('Location: ../pages/verification.php');
        exit;
    }
    
    // Mark user as verified
    $users = db()->selectCollection('users');
    
    try {
        $users->updateOne(
            ['user_id' => $userId],
            ['$set' => ['is_verified' => true]]
        );
    } catch (Exception $e) {
        $_SESSION['error'] = 'Verification failed. Please try again.';
        header('Location: ../pages/verification.php');
        exit;
    }
    
    $verifications->deleteOne(['email' => $email]);
    unset($_SESSION['temp_email']);
    unset($_SESSION['user_id_pending']);
    
    // Log user in automatically
    $user = $users->findOne(['user_id' => $userId]);
    
    $_SESSION['user'] = [
        'user_id' => $user['user_id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'email' => $user['email'],
    ];
    
    $_SESSION['success'] = 'Email verified successfully! Welcome to LearnLoop.';
    header('Location: ../pages/dashboard.php');
    exit;
}

function handle_resend_verification_code_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/verification.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/verification.php');
    }

    $email = (string) ($_SESSION['temp_email'] ?? '');
    if ($email === '') {
        $_SESSION['error'] = 'Session expired. Please register again.';
        header('Location: ../pages/register.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $user = $users->findOne(['email' => $email]);

    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: ../pages/register.php');
        exit;
    }

    if (!send_verification_email($email, (string) $user['full_name'])) {
        header('Location: ../pages/verification.php');
        exit;
    }

    $_SESSION['success'] = 'A new verification code has been sent.';
    header('Location: ../pages/verification.php');
    exit;
}

function handle_recover_password_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/forgot_password.php');
    }

    $email = clean_email($_POST['email'] ?? '');
    if ($email === '') {
        $_SESSION['error'] = 'Please enter a valid email address.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $user = $users->findOne(['email' => $email]);

    if (!$user) {
        $_SESSION['error'] = 'Email not registered.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    if (!send_password_reset_email($email, (string) $user['full_name'])) {
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $_SESSION['temp_reset_email'] = $email;
    $_SESSION['reset_user_id_pending'] = (string) $user['user_id'];
    $_SESSION['success'] = 'We sent a reset code to your email. Enter it below to set a new password.';
    header('Location: ../pages/reset_password.php');
    exit;
}

function handle_resend_reset_code_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/reset_password.php');
    }

    $email = (string) ($_SESSION['temp_reset_email'] ?? '');
    $userId = (string) ($_SESSION['reset_user_id_pending'] ?? '');

    if ($email === '' || $userId === '') {
        $_SESSION['error'] = 'Session expired. Please request a new reset code.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $user = $users->findOne(['user_id' => $userId, 'email' => $email]);

    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    if (!send_password_reset_email($email, (string) $user['full_name'])) {
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $_SESSION['success'] = 'A new reset code has been sent.';
    header('Location: ../pages/reset_password.php');
    exit;
}

function handle_reset_password_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/reset_password.php');
    }

    $email = (string) ($_SESSION['temp_reset_email'] ?? '');
    $userId = (string) ($_SESSION['reset_user_id_pending'] ?? '');

    if ($email === '' || $userId === '') {
        $_SESSION['error'] = 'Session expired. Please request a new reset code.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $code = '';
    for ($i = 1; $i <= 6; $i++) {
        $digit = clean_text($_POST['code_' . $i] ?? '');

        if (!is_numeric($digit) || strlen($digit) !== 1) {
            $_SESSION['error'] = 'Please enter a valid 6-digit code.';
            header('Location: ../pages/reset_password.php');
            exit;
        }

        $code .= $digit;
    }

    $newPassword = (string) ($_POST['new_pass'] ?? '');
    $confirmPassword = (string) ($_POST['conf_pass'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        $_SESSION['error'] = 'Please fill all password fields.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    if (strlen($newPassword) < 6) {
        $_SESSION['error'] = 'Password should be at least 6 characters.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $resets = db()->selectCollection('password_resets');
    $resetRecord = $resets->findOne(['email' => $email]);

    if (!$resetRecord) {
        $_SESSION['error'] = 'No reset code found. Please request a new one.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    $currentTime = time() * 1000;
    $expiresAt = $resetRecord['expires_at']->toDateTime()->getTimestamp() * 1000;

    if ($currentTime > $expiresAt) {
        $resets->deleteOne(['email' => $email]);
        unset($_SESSION['temp_reset_email'], $_SESSION['reset_user_id_pending']);
        $_SESSION['error'] = 'Reset code expired. Please request a new one.';
        header('Location: ../pages/forgot_password.php');
        exit;
    }

    if (!hash_equals((string) $resetRecord['code'], $code)) {
        $_SESSION['error'] = 'Invalid reset code. Please try again.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $users = db()->selectCollection('users');

    try {
        $result = $users->updateOne(
            ['user_id' => $userId, 'email' => $email],
            ['$set' => ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]]
        );
    } catch (Exception $e) {
        $_SESSION['error'] = 'Password update failed. Try again.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    if ($result->getMatchedCount() === 0) {
        $_SESSION['error'] = 'Password update failed.';
        header('Location: ../pages/reset_password.php');
        exit;
    }

    $resets->deleteOne(['email' => $email]);
    unset($_SESSION['temp_reset_email'], $_SESSION['reset_user_id_pending']);

    $_SESSION['success'] = 'Password updated successfully. Please sign in.';
    header('Location: ../pages/login.php');
    exit;
}
