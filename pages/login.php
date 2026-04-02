<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Sign In</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="logo">
            LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
            <p class="tagline">Collaborative Learning Space</p>
        </div>

        <form action="../actions/login_process.php" method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            
            
            <div class="auth-options">
                <div class="remember-me">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                </div>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="auth-btn">Sign In</button>
        </form>

        <p class="switch-auth">New to the Loop? <a href="register.php">Create Account</a></p>
    </div>
</body>
</html>