<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'chat';
$user = $_SESSION['user'];
$groupName = clean_text($_GET['group'] ?? 'General');

// Get group_id from group name (check if user is owner or member)
$groups = db()->selectCollection('groups');
$groupMembers = db()->selectCollection('group_members');

// Find group by name (then validate access)
$group = $groups->findOne(['group_name' => $groupName]);

if (!$group) {
    // Group not found, redirect back to dashboard
    header('Location: dashboard.php');
    exit;
}

$groupId = $group['group_id'] ?? '';
$isGroupOwner = ($group && ($group['user_id'] ?? '') === $user['user_id']) ? 'true' : 'false';

// If not owner, ensure the user is a member of this specific group
if ($isGroupOwner !== 'true') {
    $memberRecord = $groupMembers->findOne(['group_id' => $groupId, 'user_id' => $user['user_id'], 'role' => 'member']);
    if (!$memberRecord) {
        // Not authorized to view this group's chat
        header('Location: dashboard.php');
        exit;
    }
}

// Get all members of the group
$users = db()->selectCollection('users');
$groupMembers = db()->selectCollection('group_members');

$members = [];

// Add group owner
$owner = $users->findOne(['user_id' => $group['user_id'] ?? null]);
if ($owner) {
    $members[] = [
        'user_id' => $owner['user_id'],
        'full_name' => $owner['full_name'] ?? 'Unknown',
        'role' => 'owner'
    ];
}

// Add other members
$memberRecords = $groupMembers->find(['group_id' => $groupId, 'role' => 'member'])->toArray();
foreach ($memberRecords as $memberRecord) {
    $memberUser = $users->findOne(['user_id' => $memberRecord['user_id']]);
    if ($memberUser) {
        $members[] = [
            'user_id' => $memberUser['user_id'],
            'full_name' => $memberUser['full_name'] ?? 'Unknown',
            'role' => 'member'
        ];
    }
}

$membersJson = json_encode($members);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Study Room</title>
    <?php $v = time(); ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/groups.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/chat.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/notifications.css?v=<?php echo $v; ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">
    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="study-room-header">
                <button class="back-button-figma" onclick="window.history.back()">← Back</button>
                <h1 class="study-title-figma"><?php echo esc($groupName); ?> Room</h1>
            </div>

            <div class="study-layout-flex">
                <div
                    class="chat-box-figma"
                    id="chatBox"
                    data-group="<?php echo esc($groupName); ?>"
                    data-group-id="<?php echo esc($groupId); ?>"
                    data-is-owner="<?php echo $isGroupOwner; ?>"
                    data-user-id="<?php echo esc($user['user_id'] ?? ''); ?>"
                    data-user-name="<?php echo esc($user['full_name'] ?? 'Student'); ?>"
                    data-members="<?php echo htmlspecialchars($membersJson, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <div class="messages-container" id="chatMessages">
                        <div class="chat-empty">No messages yet.</div>
                    </div>
                    <div class="input-wrapper-figma">
                        <button class="icon-btn" id="attachBtn" type="button"><i class="fas fa-link"></i></button>
                        <input id="fileInput" type="file" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.zip" hidden>
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
                    
                    <div class="pending-invitations-section" id="pendingInvitationsSection" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #1e3a5f;">Pending Requests</h4>
                        <div class="pending-list" id="pendingList"></div>
                    </div>
                    
                    <div class="toast-sent" id="inviteToast">Invite has been sent!</div>
                </aside>
            </div>
        </main>
    </div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="../assets/js/chat.js?v=<?php echo $v; ?>"></script>
</body>
</html>