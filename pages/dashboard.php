<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LearnLoop | Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">

    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <h1 class="welcome-title" style="font-size: 34px; color: #1e3a5f; margin-bottom: 40px;">Welcome, <?php echo $_SESSION['user']['full_name']; ?></h1>

            <div class="stats-row">
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">My Groups</p>
                        <span class="stat-value">4</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">My Notes</p>
                        <span class="stat-value">2</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">Status</p>
                        <span class="stat-value" style="font-size:24px;">Active</span>
                    </div>
                </div>
            </div>

            <div class="forums-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                    <h2 style="color:#1e3a5f;">Recent Forums</h2>
                    <a href="forums.php" style="font-weight:600; color:#1e3a5f;">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                
                <div class="forum-card">
                    <span style="font-size:18px; color:#1e3a5f; font-weight:500;">Questions</span>
                    <span style="background:#60a5fa; color:white; padding:8px 20px; border-radius:25px; font-size:13px;">2 Replies</span>
                </div>

                <div class="forum-card">
                    <span style="font-size:18px; color:#1e3a5f; font-weight:500;">Project Discussion</span>
                    <span style="background:#94a3b8; color:white; padding:8px 20px; border-radius:25px; font-size:13px;">0 Replies</span>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
