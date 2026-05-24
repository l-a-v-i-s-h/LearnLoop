<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/moderation.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');
$summary = moderation_get_summary();
$summaryCards = [
    ['label' => 'Total Reports', 'value' => (string) ($summary['reports_total'] ?? 0), 'icon' => 'fa-regular fa-flag', 'class' => 'chat-total'],
    ['label' => 'Pending', 'value' => (string) ($summary['reports_pending'] ?? 0), 'icon' => 'fa-regular fa-hourglass-half', 'class' => 'chat-pending'],
    ['label' => 'Under Review', 'value' => (string) ($summary['reports_under_review'] ?? 0), 'icon' => 'fa-solid fa-magnifying-glass', 'class' => 'chat-review'],
    ['label' => 'Resolved', 'value' => (string) ($summary['reports_resolved'] ?? 0), 'icon' => 'fa-solid fa-check', 'class' => 'chat-resolved'],
    ['label' => 'Dismissed', 'value' => (string) ($summary['reports_dismissed'] ?? 0), 'icon' => 'fa-solid fa-xmark', 'class' => 'chat-dismissed'],
];

$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = moderation_normalize_status($_GET['status']);
}
if (!empty($_GET['search'])) {
    $filters['search'] = clean_text($_GET['search']);
}
if (!empty($_GET['reported_user_id'])) {
    $filters['reported_user_id'] = clean_text($_GET['reported_user_id']);
}
if (!empty($_GET['reporter_id'])) {
    $filters['reporter_id'] = clean_text($_GET['reporter_id']);
}

$reports = moderation_get_reports($filters);
$selectedReportId = clean_text($_GET['report_id'] ?? '');
$selectedReport = null;

if ($selectedReportId !== '') {
    $selectedReport = moderation_get_report_by_id($selectedReportId);
}

if (!$selectedReport && !empty($_GET['reported_user_id'])) {
    foreach ($reports as $report) {
        if (($report['reported_user_id'] ?? '') === clean_text($_GET['reported_user_id'])) {
            $selectedReport = $report;
            break;
        }
    }
}

if (!$selectedReport) {
    $selectedReport = $reports[0] ?? null;
}

if (!is_array($selectedReport)) {
    $selectedReport = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Chat Monitor</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/chat_monitor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page chat-monitor-page">
        <script src="../assets/js/report_filters.js"></script>
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
                    <button class="chat-filter-btn" type="button">Resolved</button>
                    <button class="chat-filter-btn" type="button">Dismissed</button>
                    <button class="chat-filter-btn" type="button">High</button>
                    <button class="chat-filter-btn" type="button">Medium</button>
                    <button class="chat-filter-btn" type="button">Low</button>
                </section>

                <section class="chat-monitor-grid">
                    <div class="chat-report-list" aria-label="Reported chats">
                        <?php if (empty($reports)): ?>
                            <article class="chat-report-card is-selected">
                                <div class="chat-report-card-head">
                                    <span>--</span>
                                    <span>No reports yet</span>
                                    <em class="chat-priority priority-low">Idle</em>
                                    <em class="chat-status status-pending">Pending</em>
                                </div>

                                <div class="chat-report-card-body">
                                    <span class="chat-avatar">--</span>
                                    <div>
                                        <h2>Reports will appear here when students submit them</h2>
                                        <p>The list updates in real time.</p>
                                    </div>
                                </div>
                            </article>
                        <?php else: ?>
                        <?php foreach ($reports as $index => $report): ?>
                            <a class="chat-report-card <?php echo ($selectedReport && ($selectedReport['report_id'] ?? '') === ($report['report_id'] ?? '')) ? 'is-selected' : ''; ?>" href="chat_monitor.php?report_id=<?php echo esc($report['report_id']); ?><?php echo !empty($_GET['reported_user_id']) ? '&reported_user_id=' . esc(clean_text($_GET['reported_user_id'])) : ''; ?>" style="text-decoration:none; color:inherit;">
                                <div class="chat-report-card-head">
                                    <span><?php echo esc($report['report_id']); ?></span>
                                    <span><?php echo esc($report['category']); ?></span>
                                    <em class="chat-priority priority-<?php echo esc($report['priority']); ?>">
                                        <?php echo esc(ucfirst($report['priority'])); ?>
                                    </em>
                                    <em class="chat-status status-<?php echo esc(str_replace('_', '-', $report['status'])); ?>">
                                        <?php echo esc(ucwords(str_replace('_', ' ', $report['status']))); ?>
                                    </em>
                                </div>

                                <div class="chat-report-card-body">
                                    <span class="chat-avatar"><?php echo esc($report['reporter_initials']); ?></span>
                                    <div>
                                        <h2><?php echo esc($report['reporter_name']); ?> reported <strong><?php echo esc($report['reported_user_name']); ?></strong></h2>
                                        <p><?php echo esc($report['details'] ?: $report['reason']); ?></p>
                                        <div class="chat-card-meta">
                                            <span><?php echo esc($report['group_name']); ?></span>
                                            <span><?php echo esc($report['created_at']); ?></span>
                                            <span><?php echo esc($report['updated_at'] ?: $report['created_at']); ?></span>
                                            <span><i class="fa-regular fa-comment-dots"></i> 1 screenshot</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <aside class="chat-report-detail" aria-label="Selected report details" data-report-id="<?php echo esc($selectedReport['report_id'] ?? ''); ?>" data-report-status="<?php echo esc($selectedReport['status'] ?? 'pending'); ?>">
                        <div class="chat-detail-head">
                            <span><?php echo esc($selectedReport['report_id'] ?? '--'); ?></span>
                            <em class="chat-priority priority-<?php echo esc($selectedReport['priority'] ?? 'low'); ?>"><?php echo esc(ucfirst($selectedReport['priority'] ?? 'low')); ?></em>
                            <em class="chat-status status-<?php echo esc(str_replace('_', '-', $selectedReport['status'] ?? 'pending')); ?>"><?php echo esc(ucwords(str_replace('_', ' ', $selectedReport['status'] ?? 'pending'))); ?></em>
                        </div>

                        <p class="chat-detail-context"><?php echo esc($selectedReport['group_name'] ?? ''); ?> &middot; <?php echo esc($selectedReport['created_at'] ?? ''); ?></p>

                        <div class="chat-people">
                            <article>
                                <span class="chat-avatar"><?php echo esc($selectedReport['reporter_initials'] ?? 'U'); ?></span>
                                <div>
                                    <p>Reporter</p>
                                    <strong><?php echo esc($selectedReport['reporter_name'] ?? 'Unknown'); ?></strong>
                                    <small><?php echo esc($selectedReport['reporter_course'] ?? 'Learner'); ?></small>
                                </div>
                            </article>

                            <article class="reported-user">
                                <span class="chat-avatar danger-avatar"><?php echo esc($selectedReport['reported_user_initials'] ?? 'U'); ?></span>
                                <div>
                                    <p>Reported User</p>
                                    <strong><?php echo esc($selectedReport['reported_user_name'] ?? 'Unknown'); ?></strong>
                                    <small><?php echo esc(($selectedReport['reported_user_status'] ?? 'active') . (!empty($selectedReport['reported_user_reason']) ? ' - ' . $selectedReport['reported_user_reason'] : '')); ?></small>
                                </div>
                            </article>
                        </div>

                        <div class="chat-detail-section">
                            <h2>Category</h2>
                            <span class="chat-category-pill"><?php echo esc($selectedReport['category'] ?? 'Chat Report'); ?></span>
                        </div>

                        <div class="chat-detail-section">
                            <h2>Student's Description</h2>
                            <p class="chat-description"><?php echo esc($selectedReport['details'] ?? $selectedReport['reason'] ?? ''); ?></p>
                        </div>

                        <div class="chat-evidence-grid">
                            <div class="chat-detail-section">
                                <div class="chat-section-title">
                                    <h2>Submitted Screenshot</h2>
                                </div>
                                <?php if (!empty($selectedReport['evidence_file_path'])): ?>
                                    <div class="chat-screenshot chat-screenshot-image">
                                        <img src="../<?php echo esc($selectedReport['evidence_file_path']); ?>" alt="Submitted evidence screenshot">
                                    </div>
                                    <small><?php echo esc($selectedReport['evidence_file_name'] ?: basename((string) $selectedReport['evidence_file_path'])); ?></small>
                                <?php else: ?>
                                    <div class="chat-screenshot chat-screenshot-default">
                                        <img src="../assets/img/default-screenshot.svg" alt="Default screenshot placeholder">
                                    </div>
                                    <small>No screenshot attached</small>
                                <?php endif; ?>
                            </div>

                            <?php $rep_status = $selectedReport['status'] ?? ''; ?>
                            <?php if ($rep_status === 'resolved'): ?>
                                <div class="chat-detail-section">
                                    <h2>Report Resolved</h2>
                                    <p class="report-resolved-note">This report has been resolved. No further moderation actions are available.</p>
                                    <?php if (!empty($selectedReport['moderation_action'])): ?>
                                        <p><strong>Action taken:</strong> <?php echo esc(ucwords(str_replace('_', ' ', $selectedReport['moderation_action']))); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedReport['moderated_by'])): ?>
                                        <p><strong>Moderator:</strong> <?php echo esc($selectedReport['moderated_by']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedReport['moderated_at'])): ?>
                                        <p><strong>When:</strong> <?php echo esc($selectedReport['moderated_at']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="chat-detail-section">
                                    <h2>Actions</h2>
                                    <div class="chat-admin-actions">
                                        <button class="report-action report-action--danger" type="button" data-report-action="delete_report" data-report-id="<?php echo esc($selectedReport['report_id'] ?? ''); ?>">
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                            Reject &amp; Delete
                                        </button>
                                        <button class="report-action report-action--warn" type="button" data-report-action="warn" data-report-id="<?php echo esc($selectedReport['report_id'] ?? ''); ?>">Warn</button>
                                        <button class="report-action report-action--suspend" type="button" data-report-action="suspend" data-report-id="<?php echo esc($selectedReport['report_id'] ?? ''); ?>">Suspend</button>
                                        <button class="report-action report-action--ban" type="button" data-report-action="ban" data-report-id="<?php echo esc($selectedReport['report_id'] ?? ''); ?>">Ban</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </aside>
                </section>
            </main>
        </div>
    </div>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="../assets/js/moderation.js"></script>
    <div class="report-modal-overlay" id="moderationActionModal" hidden>
        <div class="report-modal-card" role="dialog" aria-modal="true" aria-labelledby="moderationActionTitle">
            <div class="report-modal-header">
                <div>
                    <p class="report-modal-kicker" id="moderationActionKicker">Confirm action</p>
                    <h2 id="moderationActionTitle">Moderation action</h2>
                </div>
                <button type="button" class="report-modal-close" id="moderationActionClose" aria-label="Close">&times;</button>
            </div>

            <div class="report-modal-form">
                <p id="moderationActionMessage">Use this popup to confirm an admin moderation action.</p>
                <p class="report-modal-target"><strong>Report ID:</strong> <span id="moderationActionReportId" style="word-break:break-all;"></span></p>

                <label class="report-field" id="moderationActionReasonWrap">
                    <span id="moderationActionReasonLabel">Reason</span>
                    <textarea id="moderationActionReason" rows="4" placeholder="Type the reason here..."></textarea>
                </label>

                <div class="report-modal-actions">
                    <button type="button" class="report-modal-cancel" id="moderationActionCancel">Cancel</button>
                    <button type="button" class="report-modal-submit" id="moderationActionConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
