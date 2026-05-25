<?php
require_once __DIR__ . '/../config/db.php';

// Capture error message and clear it from session
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Capture old form input data so the user doesn't retype everything
$old_name = $_SESSION['old_input']['fullname'] ?? '';
$old_email = $_SESSION['old_input']['email'] ?? '';
unset($_SESSION['old_input']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Register</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="logo">
            LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
            <p class="tagline">Create your account</p>
        </div>

        <?php if ($error !== ''): ?>
            <div style="background:#fee; color:#900; padding:10px; border-radius:6px; margin-bottom:10px; font-size:14px; text-align:center;">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="../api/auth.php?action=register" method="POST">
            <?php echo csrf_input(); ?>

            <div class="input-group">
                <label>Full Name</label>
                <input 
                    type="text" 
                    name="fullname" 
                    placeholder="John Doe" 
                    value="<?php echo htmlspecialchars($old_name, ENT_QUOTES, 'UTF-8'); ?>" 
                    required
                >
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    placeholder="email@example.com" 
                    value="<?php echo htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8'); ?>" 
                    required
                >
            </div>
            
            <div class="input-group">
                <label>Password</label>

                <div class="password-field">
                    <input type="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" aria-label="Show password" data-password-toggle data-password-label="password">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" aria-label="Show confirm password" data-password-toggle data-password-label="confirm password">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="auth-btn" style="margin-top: 10px;">Create Account</button>
        </form>

        <p class="switch-auth">Already have an account? <a href="login.php" style="font-weight: 700; text-decoration: underline;">Sign In</a></p>
    </div>
    <script src="../assets/js/auth-password.js"></script>
</body>
</html>