<?php
require_once __DIR__ . '/../config/db.php';

function csrf_fail(string $redirect): void
{
    $_SESSION['error'] = 'Invalid request token. Please try again.';
    header('Location: ' . $redirect);
    exit;
}

function handle_login_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/login.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/login.php');
    }

    $email = clean_email($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $_SESSION['error'] = 'Please enter email and password.';
        header('Location: ../pages/login.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $user = $users->findOne(['email' => $email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['error'] = 'Invalid email or password.';
        header('Location: ../pages/login.php');
        exit;
    }

    $_SESSION['user'] = [
        'user_id' => $user['user_id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'email' => $user['email'],
    ];

    header('Location: ../pages/dashboard.php');
    exit;
}

function handle_register_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/register.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/register.php');
    }

    $fullname = safe_input($_POST['fullname'] ?? '', 100);
    $email = clean_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || $email === '' || $password === '') {
        $_SESSION['error'] = 'Please fill in all fields.';
        header('Location: ../pages/register.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password should be at least 6 characters.';
        header('Location: ../pages/register.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $existing = $users->findOne(['email' => $email]);
    if ($existing) {
        $_SESSION['error'] = 'Email already registered.';
        header('Location: ../pages/register.php');
        exit;
    }

    $username = explode('@', $email)[0];
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($username));
    if ($username === '') {
        $username = 'user';
    }

    if ($users->findOne(['username' => $username])) {
        $username = $username . bin2hex(random_bytes(2));
    }

    $userId = bin2hex(random_bytes(8));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $users->insertOne([
            'user_id' => $userId,
            'full_name' => $fullname,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        header('Location: ../pages/register.php');
        exit;
    }

    $_SESSION['success'] = 'Account created. Please sign in.';
    header('Location: ../pages/login.php');
    exit;
}

function handle_profile_update_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/profile.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/profile.php');
    }

    if (!isset($_SESSION['user']['user_id'])) {
        header('Location: ../pages/login.php');
        exit;
    }

    $userId = $_SESSION['user']['user_id'];
    $currentEmail = (string) ($_SESSION['user']['email'] ?? '');
    $fullName = safe_input($_POST['full_name'] ?? '', 100);
    $email = safe_input($_POST['email'] ?? '', 150);

    if ($fullName === '') {
        $_SESSION['error'] = 'Please enter your full name.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($email !== '' && $email !== $currentEmail) {
        $_SESSION['error'] = 'Email cannot be changed.';
        header('Location: ../pages/profile.php');
        exit;
    }

    $users = db()->selectCollection('users');

    try {
        $result = $users->updateOne(
            ['user_id' => $userId],
            ['$set' => ['full_name' => $fullName]]
        );
    } catch (MongoDB\Driver\Exception\Exception $e) {
        $_SESSION['error'] = 'Profile update failed.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($result->getMatchedCount() === 0) {
        $_SESSION['error'] = 'User not found.';
        header('Location: ../pages/profile.php');
        exit;
    }

    $_SESSION['user']['full_name'] = $fullName;
    $_SESSION['success'] = 'Profile updated successfully.';

    header('Location: ../pages/profile.php');
    exit;
}
