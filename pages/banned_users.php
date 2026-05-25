<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/moderation.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');
$bannedSnapshot = moderation_get_dashboard_snapshot();
$summaryCards = [
    ['label' => 'Total Moderated', 'value' => (string) (($bannedSnapshot['summary']['users_warned'] ?? 0) + ($bannedSnapshot['summary']['users_suspended'] ?? 0) + ($bannedSnapshot['summary']['users_banned'] ?? 0)), 'icon' => 'fa-solid fa-user-group', 'class' => 'banned-total'],
    ['label' => 'Active', 'value' => (string) ($bannedSnapshot['summary']['users_active'] ?? 0), 'icon' => 'fa-solid fa-check', 'class' => 'banned-active'],
    ['label' => 'Warned', 'value' => (string) ($bannedSnapshot['summary']['users_warned'] ?? 0), 'icon' => 'fa-solid fa-triangle-exclamation', 'class' => 'banned-warned'],
    ['label' => 'Suspended', 'value' => (string) ($bannedSnapshot['summary']['users_suspended'] ?? 0), 'icon' => 'fa-solid fa-user-lock', 'class' => 'banned-warned'],
    ['label' => 'Banned', 'value' => (string) ($bannedSnapshot['summary']['users_banned'] ?? 0), 'icon' => 'fa-solid fa-ban', 'class' => 'banned-blocked'],
];

$bannedUsers = moderation_get_banned_users();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Banned Users</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page banned-users-page">
    <!-- user moderation modal will be appended here via server-rendered block at end of body -->
    <div class="admin-shell">
        <header class="admin-header">
            <a href="admin_dashboard.php" class="admin-logo" aria-label="LearnLoop home">
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
                    <a class="admin-nav-item" href="admin_dashboard.php">
                        <i class="fa-solid fa-house"></i>
                        <span>Dashboard</span>
                    </a>

                    <a class="admin-nav-item" href="all_students.php">
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

                    <a class="admin-nav-item is-active" href="banned_users.php">
                        <i class="fa-solid fa-ban"></i>
                        <span>Banned Users</span>
                    </a>

                    <a class="admin-nav-item" href="profile.php">
                        <i class="fa-regular fa-user"></i>
                        <span>Account</span>
                    </a>
                </nav>
            </aside>

            <main class="admin-main banned-main">
                <h1>Banned Users</h1>

                <section class="banned-summary" aria-label="Banned users summary">
                    <?php foreach ($summaryCards as $card): ?>
                        <article class="banned-summary-card <?php echo esc($card['class']); ?>">
                            <span class="banned-summary-icon">
                                <i class="<?php echo esc($card['icon']); ?>"></i>
                            </span>
                            <div>
                                <p><?php echo esc($card['label']); ?></p>
                                <strong><?php echo esc($card['value']); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="banned-table-panel" aria-label="Banned students list">
                    <div class="banned-toolbar">
                        <label class="banned-list-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" placeholder="Search banned students by name/username..." aria-label="Search banned students">
                        </label>

                        <div class="banned-filters" aria-label="Banned user filters">
                            <button class="filter-btn is-active" type="button">All</button>
                            <button class="filter-btn" type="button">Active</button>
                            <button class="filter-btn" type="button">Warned</button>
                            <button class="filter-btn" type="button">Banned</button>
                        </div>
                    </div>

                    <div class="banned-user-list">
                        <?php if (empty($bannedUsers)): ?>
                            <article class="banned-user-row">
                                <span class="banned-user-avatar">--</span>
                                <div class="banned-user-copy">
                                    <strong>No warned, suspended, or banned users yet</strong>
                                    <span>New moderation actions will appear here in real time.</span>
                                </div>
                                <span class="banned-user-course">All clear</span>
                                <span class="banned-user-date">No moderation history</span>
                                <div class="banned-row-actions">
                                    <button class="row-action view" type="button" disabled>View</button>
                                    <button class="row-action ban" type="button" disabled>Ban</button>
                                    <button class="row-action unban" type="button" disabled>Unban</button>
                                </div>
                            </article>
                        <?php else: ?>
                            <?php foreach ($bannedUsers as $user): ?>
                            <article class="banned-user-row" data-user-id="<?php echo esc($user['user_id']); ?>" data-user-status="<?php echo esc($user['moderation_status']); ?>">
                                <span class="banned-user-avatar"><?php echo esc($user['initials']); ?></span>
                                <div class="banned-user-copy">
                                    <strong><?php echo esc($user['full_name']); ?></strong>
                                    <span><?php echo esc($user['username'] ?: $user['email']); ?></span>
                                </div>
                                <span class="banned-user-course"><?php echo esc(ucfirst($user['moderation_status'])); ?></span>
                                <span class="banned-user-date"><?php echo esc($user['updated_at'] ? 'Updated: ' . $user['updated_at'] : 'Recently updated'); ?></span>
                                <div class="banned-row-actions">
                                    <a class="row-action view" href="chat_monitor.php?reported_user_id=<?php echo esc($user['user_id']); ?>">View</a>
                                    <button class="row-action ban" type="button" data-user-moderate="ban" data-user-id="<?php echo esc($user['user_id']); ?>">Ban</button>
                                    <button class="row-action unban" type="button" data-user-moderate="unban" data-user-id="<?php echo esc($user['user_id']); ?>">Unban</button>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="../assets/js/moderation.js"></script>
    <script src="../assets/js/banned_users.js"></script>
    <div class="report-modal-overlay" id="userModerationModal" hidden data-action="">
        <div class="report-modal-card" role="dialog" aria-modal="true" aria-labelledby="userModerationTitle">
            <div class="report-modal-header">
                <div>
                    <p class="report-modal-kicker" id="userModerationKicker">Confirm action</p>
                    <h2 id="userModerationTitle">User moderation</h2>
                </div>
                <button type="button" class="report-modal-close" id="userModerationClose" aria-label="Close">&times;</button>
            </div>

            <div class="report-modal-form">
                <p id="userModerationMessage">Confirm the admin action for this user.</p>
                <p class="report-modal-target"><strong>User ID:</strong> <span id="userModerationUserId" style="word-break:break-all;"></span></p>

                <label class="report-field" id="userModerationReasonWrap">
                    <span id="userModerationReasonLabel">Reason</span>
                    <textarea id="userModerationReason" rows="4" placeholder="Type the reason here..."></textarea>
                </label>

                <div class="report-modal-actions">
                    <button type="button" class="report-modal-cancel" id="userModerationCancel">Cancel</button>
                    <button type="button" class="report-modal-submit" id="userModerationConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
