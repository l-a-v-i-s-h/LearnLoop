<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/moderation.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    header('Location: admin_login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');
$dashboardSnapshot = moderation_get_dashboard_snapshot();
$dashboardSummary = $dashboardSnapshot['summary'];
$dashboardReports = $dashboardSnapshot['reports'];
$dashboardActivities = $dashboardSnapshot['recent_activities'];
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
                        <i class="fa-solid fa-headset"></i>
                        <span>Chat Monitor</span>
                    </a>

                    <a class="admin-nav-item" href="banned_users.php">
                        <i class="fa-solid fa-ban"></i>
                        <span>Banned Users</span>
                    </a>
                    
                    <a class="admin-nav-item" href="profile.php">
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
                            <strong><?php echo esc((string) ($dashboardSummary['users_total'] ?? 0)); ?></strong>
                        </div>
                    </article>

                    <article class="admin-stat-card stat-reports">
                        <span class="admin-stat-icon"><i class="fa-regular fa-note-sticky"></i></span>
                        <div>
                            <p>Pending Reports</p>
                            <strong><?php echo esc((string) ($dashboardSummary['reports_pending'] ?? 0)); ?></strong>
                        </div>
                    </article>

                    <article class="admin-stat-card stat-banned">
                        <span class="admin-stat-icon"><i class="fa-solid fa-ban"></i></span>
                        <div>
                            <p>Banned Users</p>
                            <strong><?php echo esc((string) ($dashboardSummary['users_banned'] ?? 0)); ?></strong>
                        </div>
                    </article>
                </section>

                <section class="admin-panels">
                    <article class="admin-panel report-panel">
                        <div class="panel-heading">
                            <h2>Report Queue</h2>
                            <a href="chat_monitor.php">View All <i class="fa-regular fa-circle-right"></i></a>
                        </div>

                        <div class="queue-list">
                            <?php if (empty($dashboardReports)): ?>
                                <div class="queue-item">
                                    <span class="mini-avatar">--</span>
                                    <div class="queue-copy">
                                        <strong>No reports yet</strong>
                                        <span>New reports will appear here in real time.</span>
                                    </div>
                                    <em class="pill pill-medium">Idle</em>
                                </div>
                            <?php else: ?>
                                <?php foreach ($dashboardReports as $report): ?>
                                    <a class="queue-item" href="chat_monitor.php?report_id=<?php echo esc($report['report_id']); ?>" style="text-decoration:none; color:inherit;">
                                        <span class="mini-avatar"><?php echo esc($report['reporter_initials'] ?? 'U'); ?></span>
                                        <div class="queue-copy">
                                            <strong><?php echo esc($report['reporter_name'] ?: 'Unknown reporter'); ?></strong>
                                            <span><?php echo esc($report['reason'] ?: $report['category']); ?></span>
                                        </div>
                                        <em class="pill pill-<?php echo esc($report['priority'] === 'high' ? 'high' : ($report['priority'] === 'medium' ? 'medium' : 'medium')); ?>"><?php echo esc(ucfirst($report['priority'])); ?></em>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="admin-panel banned-panel">
                        <div class="panel-heading">
                            <h2>Recent Activities</h2>
                            <a href="banned_users.php">Manage <i class="fa-regular fa-circle-right"></i></a>
                        </div>

                        <div class="banned-list">
                            <?php if (empty($dashboardActivities)): ?>
                                <div class="banned-item">
                                    <span class="mini-avatar">--</span>
                                    <div>
                                        <strong>No recent activities</strong>
                                    </div>
                                    <div class="banned-meta">
                                        <em>Idle</em>
                                        <span>Nothing to review</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($dashboardActivities as $act): ?>
                                    <?php if (($act['type'] ?? '') === 'user'): ?>
                                        <a class="banned-item" href="banned_users.php?user_id=<?php echo esc($act['id'] ?? ''); ?>" style="text-decoration:none; color:inherit;">
                                            <span class="mini-avatar"><?php echo esc($act['raw']['initials'] ?? '--'); ?></span>
                                            <div>
                                                <strong><?php echo esc($act['title'] ?? 'User'); ?></strong>
                                            </div>
                                            <div class="banned-meta">
                                                <em><?php echo esc(ucfirst($act['subtitle'] ?? '')); ?></em>
                                                <span><?php echo esc($act['raw']['updated_at'] ?? 'Just updated'); ?></span>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <a class="banned-item" href="chat_monitor.php?report_id=<?php echo esc($act['id'] ?? ''); ?>" style="text-decoration:none; color:inherit;">
                                            <span class="mini-avatar"><?php echo esc($act['raw']['reporter_initials'] ?? '--'); ?></span>
                                            <div>
                                                <strong><?php echo esc($act['title'] ?? 'Report'); ?></strong>
                                                <span><?php echo esc($act['subtitle'] ?? ''); ?></span>
                                            </div>
                                            <div class="banned-meta">
                                                <em><?php echo esc(ucfirst($act['meta'] ?? '')); ?></em>
                                                <span><?php echo esc($act['raw']['updated_at'] ?? $act['raw']['created_at'] ?? ''); ?></span>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="../assets/js/moderation.js"></script>
</body>
</html>
