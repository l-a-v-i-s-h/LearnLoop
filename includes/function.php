<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email-verification.php';

function csrf_fail(string $redirect): void
{
    $_SESSION['error'] = 'Invalid request token. Please try again.';
    header('Location: ' . $redirect);
    exit;
}

function admin_seed(): void
{
    $admins = db()->selectCollection('admin');
    $email = 'admin@gmail.com';

    try {
        $existing = $admins->findOne(['email' => $email]);
        if ($existing) {
            return;
        }

        $admins->insertOne([
            'admin_id' => 'admin-001',
            'full_name' => 'Admin',
            'email' => $email,
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);
    } catch (MongoDB\Driver\Exception\Exception $e) {
    }
}

function admin_login(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/login.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/login.php');
    }

    admin_seed();

    $email = clean_email($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $_SESSION['error'] = 'Please enter email and password.';
        header('Location: ../pages/login.php');
        exit;
    }

    $admins = db()->selectCollection('admin');
    $admin = $admins->findOne(['email' => $email]);

    if (!$admin || !password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
        $_SESSION['error'] = 'Invalid admin email or password.';
        header('Location: ../pages/login.php');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'admin_id' => (string) ($admin['admin_id'] ?? 'admin-001'),
        'full_name' => (string) ($admin['full_name'] ?? 'Admin'),
        'email' => (string) ($admin['email'] ?? $email),
    ];

    header('Location: ../pages/admin_dashboard.php');
    exit;
}

function admin_logout(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../pages/login.php');
        exit;
    }

    $token = clean_text($_POST['_csrf_token'] ?? '');
    if (!csrf_check($token)) {
        csrf_fail('../pages/login.php');
    }

    unset($_SESSION['admin']);
    session_regenerate_id(true);

    header('Location: ../pages/login.php');
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

    admin_seed();

    $admins = db()->selectCollection('admin');
    $admin = $admins->findOne(['email' => $email]);

    if ($admin && password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'admin_id' => (string) ($admin['admin_id'] ?? 'admin-001'),
            'full_name' => (string) ($admin['full_name'] ?? 'Admin'),
            'email' => (string) ($admin['email'] ?? $email),
        ];

        header('Location: ../pages/admin_dashboard.php');
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

    $_SESSION['temp_email'] = $email;
    $_SESSION['user_id_pending'] = $userId;

    if (!send_verification_email($email, $fullname)) {
        $_SESSION['error'] = $_SESSION['error'] ?? 'Verification email could not be sent.';
        header('Location: ../pages/register.php');
        exit;
    }

    $_SESSION['success'] = 'Please check your email for verification code.';
    header('Location: ../pages/verification.php');
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

function handle_pass_change(): void
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

    $uid = $_SESSION['user']['user_id'];
    $cur = (string) ($_POST['cur_pass'] ?? '');
    $new = (string) ($_POST['new_pass'] ?? '');
    $conf = (string) ($_POST['conf_pass'] ?? '');

    if ($cur === '' || $new === '' || $conf === '') {
        $_SESSION['error'] = 'Please fill all password fields.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($new !== $conf) {
        $_SESSION['error'] = 'New passwords do not match.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if (strlen($new) < 6) {
        $_SESSION['error'] = 'New password should be at least 6 characters.';
        header('Location: ../pages/profile.php');
        exit;
    }

    $users = db()->selectCollection('users');
    $user = $users->findOne(['user_id' => $uid]);

    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if (!password_verify($cur, $user['password_hash'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
        header('Location: ../pages/profile.php');
        exit;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);

    try {
        $res = $users->updateOne(['user_id' => $uid], ['$set' => ['password_hash' => $newHash]]);
    } catch (MongoDB\Driver\Exception\Exception $e) {
        $_SESSION['error'] = 'Password update failed. Try again.';
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($res->getMatchedCount() === 0) {
        $_SESSION['error'] = 'Password update failed.';
        header('Location: ../pages/profile.php');
        exit;
    }

    $_SESSION['success'] = 'Password updated successfully.';
    header('Location: ../pages/profile.php');
    exit;
}
