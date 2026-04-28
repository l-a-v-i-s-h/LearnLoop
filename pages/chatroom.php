<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'chat';
$user = $_SESSION['user'];
$groupName = clean_text($_GET['group'] ?? 'General');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LearnLoop | Study Room</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/groups.css">
    <link rel="stylesheet" href="../assets/css/chat.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">
    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="study-room-header">
                <button class="back-button-figma" onclick="window.history.back()">← Back</button>
                <h1 class="study-title-figma"><?php echo esc($groupName); ?> Study Room</h1>
            </div>

            <div class="study-layout-flex">
                <div
                    class="chat-box-figma"
                    id="chatBox"
                    data-group="<?php echo esc($groupName); ?>"
                    data-user-id="<?php echo esc($user['user_id'] ?? ''); ?>"
                    data-user-name="<?php echo esc($user['full_name'] ?? 'Student'); ?>"
                >
                    <div class="messages-container" id="chatMessages">
                        <div class="chat-empty">No messages yet.</div>
                    </div>
                    <div class="input-wrapper-figma">
                        <button class="icon-btn" id="attachBtn" type="button"><i class="fas fa-link"></i></button>
                        <input id="fileInput" type="file" hidden>
                        <input type="text" placeholder="Type something..." id="msgInput">
                        <button class="icon-btn" id="sendBtn" type="button"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>

                <aside class="members-sidebar-figma">
                    <div class="members-header-inline">
                        <div>
                            <h3>Members</h3>
                            <small>1 member</small>
                        </div>
                        <button id="addMemberBtn" class="add-mini-btn">+ Add</button>
                    </div>

                    <div class="invite-card-figma" id="inviteCard">
                        <input type="email" id="inviteEmail" placeholder="Email address">
                        <div class="invite-actions">
                            <button id="confirmInvite" class="btn-invite">Invite</button>
                            <button id="cancelInvite" class="btn-cancel">Cancel</button>
                        </div>
                    </div>

                    <div class="member-list-figma">
                        <div class="member-item"><span class="user-icon"></span> <div><strong>You</strong><p>Member</p></div></div>
                    </div>
                    
                    <div class="toast-sent" id="inviteToast">Invite has been sent!</div>
                </aside>
            </div>
        </main>
    </div>

    <script src="../assets/js/chat.js"></script>
</body>
</html>