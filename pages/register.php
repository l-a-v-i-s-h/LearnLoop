
<?php
require_once __DIR__ . '/../config/db.php';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <div style="background:#fee; color:#900; padding:10px; border-radius:6px; margin-bottom:10px;">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="../api/auth.php?action=register" method="POST">
        <form action="../actions/register_process.php" method="POST">

            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="fullname" placeholder="John Doe" required>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>
            
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="auth-btn" style="margin-top: 10px;">Create Account</button>
        </form>

        <p class="switch-auth">Already have an account? <a href="login.php" style="font-weight: 700; text-decoration: underline;">Sign In</a></p>
    </div>
</body>
</html>