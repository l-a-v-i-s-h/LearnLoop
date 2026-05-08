<?php
require_once __DIR__ . '/../config/db.php';


$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$tempEmail = $_SESSION['temp_email'] ?? '';
if ($tempEmail === '') {

    header('Location: register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Verify Email</title>
    <link rel="stylesheet" href="../assets/css/verification.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="verification-page">
    <main class="verification-card" aria-labelledby="verification-title">
        <div class="verification-logo" aria-label="LearnLoop">
            LearnL<span class="logo-icon"><i class="fa-solid fa-infinity"></i></span>p
        </div>

        <img
            class="verification-lock"
            src="../assets/img/verification-lock.svg"
            alt="Lock and key"
        >

        <h1 class="verification-heading" id="verification-title">Verify Your Email</h1>
        <p class="verification-subtitle">We've sent a code to <?php echo esc($tempEmail); ?></p>

        <?php if ($error !== ''): ?>
            <div style="background:#fee; color:#900; padding:10px; border-radius:6px; margin-bottom:10px; font-size:14px;">
                <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div style="background:#efe; color:#060; padding:10px; border-radius:6px; margin-bottom:10px; font-size:14px;">
                <?php echo esc($success); ?>
            </div>
        <?php endif; ?>

        <form class="verification-form" action="../api/auth.php?action=verify-email" method="POST">
            <?php echo csrf_input(); ?>
            <div class="verification-code" aria-label="Verification code">
                <input type="text" name="code_1" inputmode="numeric" maxlength="1" aria-label="Digit 1" required>
                <input type="text" name="code_2" inputmode="numeric" maxlength="1" aria-label="Digit 2" required>
                <input type="text" name="code_3" inputmode="numeric" maxlength="1" aria-label="Digit 3" required>
                <input type="text" name="code_4" inputmode="numeric" maxlength="1" aria-label="Digit 4" required>
                <input type="text" name="code_5" inputmode="numeric" maxlength="1" aria-label="Digit 5" required>
                <input type="text" name="code_6" inputmode="numeric" maxlength="1" aria-label="Digit 6" required>
            </div>

            <button class="verification-button" type="submit">Verify Code</button>
        </form>

        <form action="../api/auth.php?action=resend-code" method="POST">
            <?php echo csrf_input(); ?>
            <p class="verification-resend">Didn't receive the code? <button type="submit">Resend Code</button></p>
        </form>
        <p class="verification-expiry">Code expires in 15 minutes</p>
    </main>
    <script src="../assets/js/verification.js"></script>
</body>

</html>