<?php
require_once __DIR__ . '/../config/db.php';
$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Recover Password</title>
    <link rel="stylesheet" href="../assets/css/password.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
</head>
<body>
    <div class="blue-card">
        <div class="logo">
            LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
        </div>
        <p class="tagline">Collaborative Learning Space</p>

        <div class="white-modal">
            <div class="modal-header">
                <span>Recover Password</span>
                <a href="login.php" class="close-x"><i class="fa-solid fa-xmark"></i></a>
            </div>

            <form action="../api/auth.php?action=recover" method="POST">
                <div class="input-box">
                    <i class="fa-regular fa-user"></i>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn-send">Send</button>
                    <a href="login.php" class="btn-cancel">Cancel</a>
                </div>
            </form>

            <?php if ($status === 'success'): ?>
                <div class="success-msg">Link has been successfully sent to your email !</div>
            <?php endif; ?>
        </div>

        <a href="login.php" class="signin-bar">Sign In</a>
        
        <p class="footer-text">New to the Loop? <a href="register.php">Create Account</a></p>
    </div>
</body>
</html>