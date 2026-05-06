<?php
require_once __DIR__ . '/../config/db.php';
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
        <p class="verification-subtitle">We've sent a code to your email</p>

        <form class="verification-form" action="#" method="POST">
            <div class="verification-code" aria-label="Verification code">
                <input type="text" name="code_1" inputmode="numeric" maxlength="1" aria-label="Digit 1">
                <input type="text" name="code_2" inputmode="numeric" maxlength="1" aria-label="Digit 2">
                <input type="text" name="code_3" inputmode="numeric" maxlength="1" aria-label="Digit 3">
                <input type="text" name="code_4" inputmode="numeric" maxlength="1" aria-label="Digit 4">
                <input type="text" name="code_5" inputmode="numeric" maxlength="1" aria-label="Digit 5">
                <input type="text" name="code_6" inputmode="numeric" maxlength="1" aria-label="Digit 6">
            </div>

            <button class="verification-button" type="submit">Verify Code</button>
        </form>

        <p class="verification-resend">Didn't receive the code? <a href="#">Resend Code</a></p>
        <p class="verification-expiry">Code expires in 15 minutes</p>
    </main>
</body>

</html>