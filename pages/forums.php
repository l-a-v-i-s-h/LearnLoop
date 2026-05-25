<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) && !isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$isAdminForum = isset($_SESSION['admin']['admin_id']);
$forumUserId = (string) ($_SESSION['user']['user_id'] ?? '');
$forumAdminId = (string) ($_SESSION['admin']['admin_id'] ?? '');
$forumActorId = $forumUserId !== '' ? $forumUserId : $forumAdminId;
$forumAdminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

$current_page = 'forums';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Academic Forums</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if ($isAdminForum): ?>
        <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <?php else: ?>
        <link rel="stylesheet" href="../assets/css/dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../assets/css/forum.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="<?php echo $isAdminForum ? 'admin-dashboard-page' : 'dashboard-layout'; ?>" data-user-id="<?php echo esc($forumActorId); ?>">

    <?php if ($isAdminForum): ?>
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
                        <span><?php echo esc($forumAdminName); ?></span>
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
                            <i class="fa-solid fa-users"></i>
                            <span>All Students</span>
                        </a>

                        <a class="admin-nav-item is-active" href="forums.php">
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
                    <div class="forum-header">
                        <div>
                            <h1 class="forum-title">Academic Forums</h1>
                            <p class="forum-subtitle">Ask questions and share knowledge with your peers</p>
                        </div>
                        <button type="button" class="ask-btn" id="askBtn" hidden>
                            Ask Questions
                        </button>
                    </div>

                    <!-- Inline Ask Question Form -->
                    <div class="ask-panel" id="askPanel" hidden>
                        <div class="ask-panel-label">ASK QUESTION HERE</div>
                        <form id="askForm" class="ask-panel-body" action="../api/post.php" method="POST">
                            <?php echo csrf_input(); ?>
                            <input type="text" id="questionTitle" name="title" class="ask-field" placeholder="Question title" maxlength="150" required>

                            <textarea id="questionDescription" name="description" class="ask-field ask-textarea" placeholder="Describe your question in detail..." rows="3" required></textarea>

                            <div class="ask-actions">
                                <button type="submit" class="btn-post">Post Question</button>
                                <button type="button" class="btn-cancel-ask" id="askCancel">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <!-- Questions List (shown when questions exist) -->
                    <div class="questions-wrapper" id="questionsWrapper" hidden>
                        <div class="questions-list" id="questionsList"></div>
                    </div>

                    <!-- Empty State (shown when no questions exist) -->
                    <div class="forum-empty" id="forumEmpty">
                        <div class="empty-icon">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <h2 class="empty-title">No Questions Yet</h2>
                        <p class="empty-text">Be the first to ask a question and start a discussion</p>
                    </div>
                </main>
            </div>
        </div>
    <?php else: ?>

        <?php include '../includes/header.php'; ?>

        <div class="app-container">
            <?php include '../includes/navbar.php'; ?>

            <main class="main-content">
                <div class="forum-header">
                    <div>
                        <h1 class="forum-title">Academic Forums</h1>
                        <p class="forum-subtitle">Ask questions and share knowledge with your peers</p>
                    </div>
                    <button type="button" class="ask-btn" id="askBtn">
                        Ask Questions
                    </button>
                </div>

                <!-- Inline Ask Question Form -->
                <div class="ask-panel" id="askPanel" hidden>
                    <div class="ask-panel-label">ASK QUESTION HERE</div>
                    <form id="askForm" class="ask-panel-body" action="../api/post.php" method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="text" id="questionTitle" name="title" class="ask-field" placeholder="Question title" maxlength="150" required>

                        <textarea id="questionDescription" name="description" class="ask-field ask-textarea" placeholder="Describe your question in detail..." rows="3" required></textarea>

                        <div class="ask-actions">
                            <button type="submit" class="btn-post">Post Question</button>
                            <button type="button" class="btn-cancel-ask" id="askCancel">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Questions List (shown when questions exist) -->
                <div class="questions-wrapper" id="questionsWrapper" hidden>
                    <div class="questions-list" id="questionsList"></div>
                </div>

                <!-- Empty State (shown when no questions exist) -->
                <div class="forum-empty" id="forumEmpty">
                    <div class="empty-icon">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <h2 class="empty-title">No Questions Yet</h2>
                    <p class="empty-text">Be the first to ask a question and start a discussion</p>
                </div>
            </main>
        </div>
    <?php endif; ?>

    <div class="modal-overlay" id="forumDeleteModal" hidden>
        <div class="modal-card modal-delete">
            <div class="delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2>Delete Question?</h2>
            <p>This action cannot be undone. The question will be removed.</p>
            <div class="delete-actions">
                <button type="button" class="cancel-btn" id="forumDeleteCancel">Cancel</button>
                <button type="button" class="confirm-delete-btn" id="forumDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="forumReplyDeleteModal" hidden>
        <div class="modal-card modal-delete">
            <div class="delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2>Delete Reply?</h2>
            <p>This action cannot be undone. The reply will be removed.</p>
            <div class="delete-actions">
                <button type="button" class="cancel-btn" id="forumReplyDeleteCancel">Cancel</button>
                <button type="button" class="confirm-delete-btn" id="forumReplyDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="../assets/js/forum.js"></script>
</body>

</html>