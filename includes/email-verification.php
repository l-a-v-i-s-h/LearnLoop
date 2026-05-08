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
    
    // Build email template
    $message = '<html><body>';
    $message .= '<div style="font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;">';
    $message .= '<div style="background-color: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto;">';
    $message .= '<h2 style="color: #333;">Hello ' . esc($fullname) . ',</h2>';
    $message .= '<p style="color: #666; font-size: 16px;">Welcome to <strong>LearnLoop</strong>!</p>';
    $message .= '<p style="color: #666; font-size: 14px;">Please verify your email address to complete your registration.</p>';
    
    // Show verification code
    $message .= '<div style="background-color: #580000; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center;">';
    $message .= '<p style="margin: 0; color: #37ff00; font-size: 15px; font-weight: bold;">Your Verification Code:</p>';
    $message .= '<p style="margin: 10px 0 0 0; color: #ffffff; font-size: 34px; font-weight: bold; letter-spacing: 4px;">';
    $message .= $verificationCode;
    $message .= '</p>';
    $message .= '</div>';
    
    $message .= '<p style="color: #666; font-size: 14px;">This code will expire in <strong>15 minutes</strong>.</p>';
    $message .= '<p style="color: #999; font-size: 12px;">If you did not create this account, please ignore this email.</p>';
    $message .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">';
    $message .= '<p style="color: #999; font-size: 11px; text-align: center;">&copy; LearnLoop. All rights reserved.</p>';
    $message .= '</div></div></body></html>';
    
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
