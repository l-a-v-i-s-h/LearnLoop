<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    header('Location: admin_login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page admin-home-page">
    <div class="admin-shell">
        <header class="admin-header">
            <a href="#" class="admin-logo" aria-label="LearnLoop home">
                LearnL<span><i class="fa-solid fa-infinity"></i></span>p
            </a>

            <label class="admin-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" placeholder="Search" aria-label="Search">
            </label>

            <div class="admin-profile">
                <div class="admin-profile-copy">
                    <span class="admin-avatar"><i class="fa-regular fa-user"></i></span>
                    <span><?php echo esc($adminName); ?></span>
                </div>

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
                    <a class="admin-nav-item is-active" href="admin_dashboard.php">
                        <i class="fa-solid fa-house"></i>
                        <span>Dashboard</span>
                    </a>

                    <a class="admin-nav-item" href="#">
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>All Students</span>
                    </a>

                    <a class="admin-nav-item" href="forums.php">
                        <i class="fa-regular fa-comments"></i>
                        <span>Academic Forums</span>
                    </a>
                    <a class="admin-nav-item" href="chat_monitor.php">
                    <a class="admin-nav-item" href="#">
                        <i class="fa-solid fa-headset"></i>
                        <span>Chat Monitor</span>
                    </a>

                    <a class="admin-nav-item" href="banned_users.php">
                    <a class="admin-nav-item" href="#">
                        <i class="fa-solid fa-ban"></i>
                        <span>Banned Users</span>
                    </a>
                    
                    <a class="admin-nav-item" href="#">
                        <i class="fa-regular fa-user"></i>
                        <span>Account</span>
                    </a>
                </nav>


            </aside>

            <main class="admin-main">
                <div class="admin-title-row">
                    <h1>Welcome, Admin</h1>
                    <a class="admin-floating-bell" href="#" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                    </a>
                </div>

                <section class="admin-stats" aria-label="Dashboard summary">
                    <article class="admin-stat-card stat-students">
                        <span class="admin-stat-icon"><i class="fa-solid fa-users"></i></span>
                        <div>
                            <p>Total Students</p>
                            <strong>255</strong>
                        </div>
                    </article>

                    <article class="admin-stat-card stat-reports">
                        <span class="admin-stat-icon"><i class="fa-regular fa-note-sticky"></i></span>
                        <div>
                            <p>Pending Reports</p>
                            <strong>15</strong>
                        </div>
                    </article>

                    <article class="admin-stat-card stat-banned">
                        <span class="admin-stat-icon"><i class="fa-solid fa-ban"></i></span>
                        <div>
                            <p>Banned Users</p>
                            <strong>7</strong>
                        </div>
                    </article>
                </section>

                <section class="admin-panels">
                    <article class="admin-panel report-panel">
                        <div class="panel-heading">
                            <h2>Report Queue</h2>
                            <a href="#">View All <i class="fa-regular fa-circle-right"></i></a>
                        </div>

                        <div class="queue-list">
                            <div class="queue-item">
                                <span class="mini-avatar">AZ</span>
                                <div class="queue-copy">
                                    <strong>ALIE ZHANG CS</strong>
                                    <span>Verbal Harassment in CS group</span>
                                </div>
                                <em class="pill pill-high">High</em>
                            </div>

                            <div class="queue-item">
                                <span class="mini-avatar">BC</span>
                                <div class="queue-copy">
                                    <strong>BOB LEE CY</strong>
                                    <span>Spam in Shared notes</span>
                                </div>
                                <em class="pill pill-medium">Medium</em>
                            </div>

                            <div class="queue-item">
                                <span class="mini-avatar">SL</span>
                                <div class="queue-copy">
                                    <strong>SARA LEE</strong>
                                    <span>Inappropriate forum post</span>
                                </div>
                                <em class="pill pill-high">High</em>
                            </div>

                            <div class="queue-item">
                                <span class="mini-avatar">SL</span>
                                <div class="queue-copy">
                                    <strong>SARA LEE</strong>
                                    <span>Inappropriate forum post</span>
                                </div>
                                <em class="pill pill-high">High</em>
                            </div>

                            <div class="queue-item">
                                <span class="mini-avatar">SL</span>
                                <div class="queue-copy">
                                    <strong>SARA LEE</strong>
                                    <span>Inappropriate forum post</span>
                                </div>
                                <em class="pill pill-high">High</em>
                            </div>
                        </div>
                    </article>

                    <article class="admin-panel banned-panel">
                        <div class="panel-heading">
                            <h2>Recently Banned</h2>
                            <a href="#">Manage <i class="fa-regular fa-circle-right"></i></a>
                        </div>

                        <div class="banned-list">
                            <div class="banned-item">
                                <span class="mini-avatar">MK</span>
                                <div>
                                    <strong>MIKE<br>KWAN</strong>
                                </div>
                                <div class="banned-meta">
                                    <em>Banned</em>
                                    <span>3 days ago</span>
                                </div>
                            </div>

                            <div class="banned-item">
                                <span class="mini-avatar">JP</span>
                                <div>
                                    <strong>JAKE<br>PARK</strong>
                                </div>
                                <div class="banned-meta">
                                    <em>Banned</em>
                                    <span>3 days ago</span>
                                </div>
                            </div>

                            <div class="banned-item">
                                <span class="mini-avatar">RK</span>
                                <div>
                                    <strong>RAJ<br>KUMAR</strong>
                                </div>
                                <div class="banned-meta">
                                    <em>Banned</em>
                                    <span>5 days ago</span>
                                </div>
                            </div>

                            <div class="banned-item">
                                <span class="mini-avatar">RK</span>
                                <div>
                                    <strong>RAJ<br>KUMAR</strong>
                                </div>
                                <div class="banned-meta">
                                    <em>Banned</em>
                                    <span>7 days ago</span>
                                </div>
                            </div>

                            <div class="banned-item">
                                <span class="mini-avatar">RK</span>
                                <div>
                                    <strong>RAJ<br>KUMAR</strong>
                                </div>
                                <div class="banned-meta">
                                    <em>Banned</em>
                                    <span>14 days ago</span>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
