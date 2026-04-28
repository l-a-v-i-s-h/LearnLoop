<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'forums';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>LearnLoop | Academic Forums</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forum.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="dashboard-layout">

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

    <div class="modal-overlay" id="forumDeleteModal" hidden>
        <div class="modal-card modal-sm">
            <div class="modal-head">
                <h3>Delete Question?</h3>
                <button type="button" class="modal-close" id="forumDeleteModalClose" aria-label="Close delete dialog">&times;</button>
            </div>
            <div class="modal-body">
                <p class="confirm-text">
                    Are you sure you want to delete <strong id="forumDeleteQuestionName">this question</strong>? This action cannot be undone.
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="forumDeleteCancel">Cancel</button>
                    <button type="button" class="btn-danger" id="forumDeleteConfirm">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/forum.js"></script>
</body>

</html>