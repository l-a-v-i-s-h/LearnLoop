<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$fullName = $user['full_name'] ?? '';
$email = $user['email'] ?? '';
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);


$current_page = 'profile'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Account Settings</title>
    <link rel="stylesheet" href="../assets/css/style.css">
     <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">

    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
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
                    <form id="passwordForm">
                        <div class="form-group">
                            <label>Current password</label>
                            <input type="password" required readonly>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" required readonly> 
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" required readonly>
                        </div>
                        <button type="button" class="btn-settings" disabled>Update Password</button>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/profile.js"></script>
</body>
</html>