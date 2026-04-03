<?php
require_once __DIR__ . '/../config/db.php';

function handle_login_process(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/login.php');
        exit;
    }

    $token = clean_text($_POST['csrf_token'] ?? '');
    if (!csrf_check($token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: ../pages/login.php');
        exit;
    }

    $email = clean_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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

    $token = clean_text($_POST['csrf_token'] ?? '');
    if (!csrf_check($token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: ../pages/register.php');
        exit;
    }

    $fullname = clean_text($_POST['fullname'] ?? '');
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

    $baseUsername = $username;
    $counter = 0;
    while ($users->findOne(['username' => $username])) {
        $counter++;
        $username = $baseUsername . $counter;
        if ($counter > 20) {
            $username = $baseUsername . bin2hex(random_bytes(2));
            break;
        }
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
        $writeErrors = $e->getWriteResult()->getWriteErrors();
        $detail = $writeErrors ? $writeErrors[0]->getMessage() : $e->getMessage();
        $info = ($writeErrors && method_exists($writeErrors[0], 'getInfo'))
            ? $writeErrors[0]->getInfo()
            : null;
        if ($info) {
            $detail .= ' ' . json_encode($info);
        }

        $_SESSION['error'] = 'Registration failed. ' . $detail;
        header('Location: ../pages/register.php');
        exit;
    }

    $_SESSION['success'] = 'Account created. Please sign in.';
    header('Location: ../pages/login.php');
    exit;
}
