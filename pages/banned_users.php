<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

$summaryCards = [
    ['label' => 'Total Banned', 'value' => '7', 'icon' => 'fa-solid fa-user-group', 'class' => 'banned-total'],
    ['label' => 'Active', 'value' => '5', 'icon' => 'fa-solid fa-check', 'class' => 'banned-active'],
    ['label' => 'Warned', 'value' => '2', 'icon' => 'fa-solid fa-triangle-exclamation', 'class' => 'banned-warned'],
    ['label' => 'Banned', 'value' => '7', 'icon' => 'fa-solid fa-ban', 'class' => 'banned-blocked'],
];

$bannedUsers = [
    ['initials' => 'AZ', 'name' => 'ALICE ZHANG CS', 'group' => 'Group1', 'course' => 'BCS', 'date' => 'March 9, 2025'],
    ['initials' => 'BC', 'name' => 'BRUCE CHOI', 'group' => 'Group2', 'course' => 'BIBM', 'date' => 'March 15, 2025'],
    ['initials' => 'BC', 'name' => 'BOB LEE CY', 'group' => 'Group3', 'course' => 'BCY', 'date' => 'March 16, 2025'],
    ['initials' => 'SL', 'name' => 'SARA LEE', 'group' => 'Group4', 'course' => 'BCY', 'date' => 'March 25, 2025'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Banned Users</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page banned-users-page">
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
                            <button class="add-student-btn" type="button"><i class="fa-solid fa-plus"></i>Add Students</button>
                        </div>
                    </div>

                    <div class="banned-user-list">
                        <?php foreach ($bannedUsers as $user): ?>
                            <article class="banned-user-row">
                                <span class="banned-user-avatar"><?php echo esc($user['initials']); ?></span>
                                <div class="banned-user-copy">
                                    <strong><?php echo esc($user['name']); ?></strong>
                                    <span><?php echo esc($user['group']); ?></span>
                                </div>
                                <span class="banned-user-course"><?php echo esc($user['course']); ?></span>
                                <span class="banned-user-date">Banned: <?php echo esc($user['date']); ?></span>
                                <div class="banned-row-actions">
                                    <button class="row-action view" type="button">View</button>
                                    <button class="row-action ban" type="button">Ban</button>
                                    <button class="row-action unban" type="button">Unban</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
