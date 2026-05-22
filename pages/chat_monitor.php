<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

$summaryCards = [
    ['label' => 'Total Reports', 'value' => '5', 'icon' => 'fa-regular fa-flag', 'class' => 'chat-total'],
    ['label' => 'Pending', 'value' => '2', 'icon' => 'fa-regular fa-hourglass-half', 'class' => 'chat-pending'],
    ['label' => 'Under Review', 'value' => '1', 'icon' => 'fa-solid fa-magnifying-glass', 'class' => 'chat-review'],
    ['label' => 'Resolved', 'value' => '1', 'icon' => 'fa-solid fa-check', 'class' => 'chat-resolved'],
    ['label' => 'Dismissed', 'value' => '1', 'icon' => 'fa-solid fa-xmark', 'class' => 'chat-dismissed'],
];

$reports = [
    [
        'id' => 'RPT-001',
        'category' => 'Verbal Abuse',
        'priority' => 'Medium',
        'status' => 'Resolved',
        'reporterInitials' => 'OH',
        'reporter' => 'Omar Hassan',
        'reported' => 'Lin Wei',
        'group' => 'ACS Group Chat',
        'date' => 'May 13, 2025',
        'time' => '2:00pm',
        'description' => 'Lin Wei asked me to share the exam paper from last semester. When I refused, they started insulting me in the group. The messages were later deleted but I screenshotted them in time.',
    ],
    [
        'id' => 'RPT-002',
        'category' => 'Spam',
        'priority' => 'Low',
        'status' => 'Pending',
        'reporterInitials' => 'NP',
        'reporter' => 'Nisha Patel',
        'reported' => 'Caleb Morris',
        'group' => 'BIBM Project Room',
        'date' => 'May 14, 2025',
        'time' => '10:35am',
        'description' => 'Caleb kept posting repeated links in the project chat after several students asked him to stop.',
    ],
    [
        'id' => 'RPT-003',
        'category' => 'Harassment',
        'priority' => 'High',
        'status' => 'Under Review',
        'reporterInitials' => 'RS',
        'reporter' => 'Riya Shah',
        'reported' => 'Daniel Kim',
        'group' => 'Cybersecurity Batch',
        'date' => 'May 15, 2025',
        'time' => '4:20pm',
        'description' => 'Daniel made personal comments about another student during the group discussion and continued after being warned.',
    ],
];

$selectedReport = $reports[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LearnLoop | Chat Monitor</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/chat_monitor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page chat-monitor-page">
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

                    <a class="admin-nav-item is-active" href="chat_monitor.php">
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

            <main class="admin-main chat-monitor-main">
                <div class="chat-monitor-title-row">
                    <h1>Chat Monitor</h1>
                    <a class="chat-floating-bell" href="#" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                    </a>
                </div>

                <section class="chat-summary" aria-label="Chat report summary">
                    <?php foreach ($summaryCards as $card): ?>
                        <article class="chat-summary-card <?php echo esc($card['class']); ?>">
                            <span class="chat-summary-icon"><i class="<?php echo esc($card['icon']); ?>"></i></span>
                            <div>
                                <p><?php echo esc($card['label']); ?></p>
                                <strong><?php echo esc($card['value']); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="chat-filter-bar" aria-label="Chat report filters">
                    <label class="chat-report-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" placeholder="Search students by name/ID ..." aria-label="Search chat reports">
                    </label>
                    <button class="chat-filter-btn is-active" type="button">All</button>
                    <button class="chat-filter-btn" type="button">Pending</button>
                    <button class="chat-filter-btn" type="button">Under Review</button>
                    <button class="chat-filter-btn" type="button">Dismissed</button>
                    <button class="chat-filter-btn" type="button">High</button>
                    <button class="chat-filter-btn" type="button">Medium</button>
                    <button class="chat-filter-btn" type="button">Low</button>
                </section>

                <section class="chat-monitor-grid">
                    <div class="chat-report-list" aria-label="Reported chats">
                        <?php foreach ($reports as $index => $report): ?>
                            <article class="chat-report-card <?php echo $index === 0 ? 'is-selected' : ''; ?>">
                                <div class="chat-report-card-head">
                                    <span><?php echo esc($report['id']); ?></span>
                                    <span><?php echo esc($report['category']); ?></span>
                                    <em class="chat-priority priority-<?php echo strtolower(str_replace(' ', '-', $report['priority'])); ?>">
                                        <?php echo esc($report['priority']); ?>
                                    </em>
                                    <em class="chat-status status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                                        <?php echo esc($report['status']); ?>
                                    </em>
                                </div>

                                <div class="chat-report-card-body">
                                    <span class="chat-avatar"><?php echo esc($report['reporterInitials']); ?></span>
                                    <div>
                                        <h2><?php echo esc($report['reporter']); ?> reported <strong><?php echo esc($report['reported']); ?></strong></h2>
                                        <p><?php echo esc($report['description']); ?></p>
                                        <div class="chat-card-meta">
                                            <span><?php echo esc($report['group']); ?></span>
                                            <span><?php echo esc($report['date']); ?></span>
                                            <span><?php echo esc($report['time']); ?></span>
                                            <span><i class="fa-regular fa-comment-dots"></i> 1 screenshot</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <aside class="chat-report-detail" aria-label="Selected report details">
                        <div class="chat-detail-head">
                            <span><?php echo esc($selectedReport['id']); ?></span>
                            <em class="chat-priority priority-medium">Medium</em>
                            <em class="chat-status status-resolved">Resolved</em>
                        </div>

                        <p class="chat-detail-context"><?php echo esc($selectedReport['group']); ?> &middot; <?php echo esc($selectedReport['date']); ?> &middot; <?php echo esc($selectedReport['time']); ?></p>

                        <div class="chat-people">
                            <article>
                                <span class="chat-avatar"><?php echo esc($selectedReport['reporterInitials']); ?></span>
                                <div>
                                    <p>Reporter</p>
                                    <strong><?php echo esc($selectedReport['reporter']); ?></strong>
                                    <small>CS year 3</small>
                                </div>
                            </article>

                            <article class="reported-user">
                                <span class="chat-avatar danger-avatar">LW</span>
                                <div>
                                    <p>Reported User</p>
                                    <strong><?php echo esc($selectedReport['reported']); ?></strong>
                                    <small>CS year 2</small>
                                </div>
                            </article>
                        </div>

                        <div class="chat-detail-section">
                            <h2>Category</h2>
                            <span class="chat-category-pill"><?php echo esc($selectedReport['category']); ?></span>
                        </div>

                        <div class="chat-detail-section">
                            <h2>Student's Description</h2>
                            <p class="chat-description"><?php echo esc($selectedReport['description']); ?></p>
                        </div>

                        <div class="chat-evidence-grid">
                            <div class="chat-detail-section">
                                <div class="chat-section-title">
                                    <h2>Submitted Screenshot</h2>
                                    <a href="#">Download</a>
                                </div>
                                <div class="chat-screenshot">
                                    <div class="message-line message-incoming"></div>
                                    <div class="message-line"></div>
                                    <div class="message-line message-warning"></div>
                                    <div class="message-line message-incoming short"></div>
                                </div>
                                <small>screenshot_linwei.png</small>
                            </div>

                            <div class="chat-detail-section">
                                <h2>Admin Note</h2>
                                <textarea aria-label="Admin note" placeholder="Add an internal note about this..."></textarea>
                                <button class="chat-save-note" type="button">Save</button>
                            </div>
                        </div>
                    </aside>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
