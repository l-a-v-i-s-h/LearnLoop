<?php
require_once __DIR__ . '/../config/db.php';

$isAdminProfile = isset($_SESSION['admin']['admin_id']);
$isUserProfile = isset($_SESSION['user']['user_id']);

if (!$isAdminProfile && !$isUserProfile) {
    header('Location: login.php');
    exit;
}

$account = $isAdminProfile ? $_SESSION['admin'] : $_SESSION['user'];
$fullName = $account['full_name'] ?? '';
$email = $account['email'] ?? '';
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if (!$isAdminProfile) {
    $current_page = 'profile';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Account Settings</title>
    <?php if ($isAdminProfile): ?>
        <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <?php else: ?>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/profile.css">
    <?php if (!$isAdminProfile): ?>
        <link rel="stylesheet" href="../assets/css/notifications.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="<?php echo $isAdminProfile ? 'admin-dashboard-page' : 'dashboard-layout'; ?>">

    <?php if ($isAdminProfile): ?>
        <div class="admin-shell">
            <header class="admin-header">
                <a href="admin_dashboard.php" class="admin-logo" aria-label="LearnLoop home">
                    LearnL<span><i class="fa-solid fa-infinity"></i></span>p
                </a>

                <label class="admin-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" placeholder="Search" aria-label="Search">
                </label>

                <div class="admin-profile">
                    <a href="profile.php" class="admin-profile-copy" aria-label="Open account page">
                        <span class="admin-avatar"><i class="fa-regular fa-user"></i></span>
                        <span><?php echo esc($fullName); ?></span>
                    </a>

                    <form action="../api/auth.php?action=admin-logout" method="POST" style="margin: 0;">
                        <?php echo csrf_input(); ?>
                        <button class="logout-button" type="submit" aria-label="Log out">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </header>

            <div class="admin-layout">
                <aside class="admin-sidebar">
                    <nav class="admin-nav" aria-label="Admin navigation">
                        <a class="admin-nav-item" href="admin_dashboard.php">
                            <i class="fa-solid fa-house"></i>
                            <span>Dashboard</span>
                        </a>

                        <a class="admin-nav-item" href="all_students.php">
                            <i class="fa-solid fa-user-graduate"></i>
                            <span>All Students</span>
                        </a>

                        <a class="admin-nav-item" href="forums.php">
                            <i class="fa-regular fa-comments"></i>
                            <span>Academic Forums</span>
                        </a>

                        <a class="admin-nav-item" href="chat_monitor.php">
                            <i class="fa-solid fa-headset"></i>
                            <span>Chat Monitor</span>
                        </a>

                        <a class="admin-nav-item" href="banned_users.php">
                            <i class="fa-solid fa-ban"></i>
                            <span>Banned Users</span>
                        </a>

                        <a class="admin-nav-item is-active" href="profile.php">
                            <i class="fa-regular fa-user"></i>
                            <span>Account</span>
                        </a>
                    </nav>
                </aside>

                <main class="admin-main">
    <?php else: ?>
        <?php include '../includes/header.php'; ?>

        <div class="app-container">
            <?php include '../includes/navbar.php'; ?>

            <main class="main-content">
    <?php endif; ?>
            <h1 class="settings-title">Account Settings</h1>

            <?php if ($successMessage !== ''): ?>
                <div class="profile-message success-message">
                    <?php echo esc($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="profile-message error-message">
                    <?php echo esc($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <section class="settings-card">
                    <h2>Profile Information</h2>
                    <form id="profileForm" action="../api/auth.php?action=profile-update" method="POST">
                        <?php echo csrf_input(); ?>
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" name="full_name" value="<?php echo esc($fullName); ?>" placeholder="Enter your name" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo esc($email); ?>" readonly>
                        </div>
                        <button type="submit" class="btn-settings">Save Changes</button>
                    </form>
                </section>

                <section class="settings-card">
                    <h2>Change Password</h2>
                    <form id="passwordForm" action="../api/auth.php?action=change-pass" method="POST">
                        <?php echo csrf_input(); ?>
                        <div class="form-group">
                            <label>Current password</label>
                            <input type="password" name="cur_pass" required placeholder="Current password">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_pass" required placeholder="New password (min 6)"> 
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="conf_pass" required placeholder="Confirm new password">
                        </div>
                        <button type="submit" class="btn-settings">Update Password</button>
                    </form>
                </section>
            </div>
        </main>
    <?php if ($isAdminProfile): ?>
            </div>
        </div>
    <?php else: ?>
        </div>
    <?php endif; ?>

    <script src="../assets/js/profile.js"></script>
</body>
</html>