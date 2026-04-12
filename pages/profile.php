<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'profile'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

            <div class="settings-grid">
                <section class="settings-card">
                    <h2>Profile Information</h2>
                    <form id="profileForm">
                        <div class="form-group">
                            <label>Display Name</label>
                            <input type="text" placeholder="Enter your name" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" placeholder="Enter your email" required>
                        </div>
                        <button type="submit" class="btn-settings">Save Changes</button>
                    </form>
                </section>

                <section class="settings-card">
                    <h2>Change Password</h2>
                    <form id="passwordForm">
                        <div class="form-group">
                            <label>Current password</label>
                            <input type="password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" required>
                        </div>
                        <button type="submit" class="btn-settings">Update Password</button>
                    </form>
                </section>
            </div>

            <div id="successToast" class="update-toast">
                <p>Profile changes has<br>been updated !</p>
                <div class="check-circle">
                    <i class="fas fa-check"></i>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/profile.js"></script>
</body>
</html>