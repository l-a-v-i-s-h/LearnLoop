<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

$notifications = db()->selectCollection('notifications');
$users = db()->selectCollection('users');
$groups = db()->selectCollection('groups');
$userId = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = read_body();

// CSRF check for POST/PUT/DELETE
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $token = '';
    if (!empty($_POST['_csrf_token'])) {
        $token = clean_text($_POST['_csrf_token']);
    } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = clean_text($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    if (!csrf_check($token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

// GET notifications
if ($method === 'GET') {
    $action = clean_text($_GET['action'] ?? '');
    
    if ($action === 'count') {
        try {
            $count = $notifications->countDocuments([
                'recipient_id' => $userId,
                'status' => 'pending'
            ]);
            respond(200, true, 'Notification count fetched.', ['count' => $count]);
        } catch (Exception $e) {
            respond(500, false, 'Failed to fetch notification count.');
        }
        exit;
    }
    
    if ($action === 'pending_invitations') {
        // Get pending invitations for groups owned by user
        try {
            $groupIds = [];
            $ownedGroups = $groups->find(['user_id' => $userId], ['projection' => ['group_id' => 1]]);
            foreach ($ownedGroups as $group) {
                $groupIds[] = $group['group_id'];
            }
            
            if (empty($groupIds)) {
                respond(200, true, 'No pending invitations.', []);
                exit;
            }
            
            $cursor = $notifications->find([
                'group_id' => ['$in' => $groupIds],
                'type' => 'group_invite',
                'status' => 'pending'
            ], ['sort' => ['created_at' => -1]]);
            
            $list = [];
            foreach ($cursor as $doc) {
                $created = '';
                if (isset($doc['created_at']) && $doc['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $created = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
                }
                
                $list[] = [
                    'notification_id' => $doc['notification_id'] ?? '',
                    'recipient_email' => $doc['recipient_email'] ?? '',
                    'group_id' => $doc['group_id'] ?? '',
                    'group_name' => $doc['group_name'] ?? '',
                    'message' => $doc['message'] ?? '',
                    'status' => $doc['status'] ?? 'pending',
                    'created_at' => $created
                ];
            }
            
            respond(200, true, 'Pending invitations fetched.', $list);
        } catch (Exception $e) {
            respond(500, false, 'Failed to fetch pending invitations.');
        }
        exit;
    }

    // Get all notifications for the user
    try {
        $cursor = $notifications->find(
            ['recipient_id' => $userId],
            ['sort' => ['created_at' => -1], 'limit' => 50]
        );
        
        $list = [];
        foreach ($cursor as $doc) {
            $created = '';
            if (isset($doc['created_at']) && $doc['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                $created = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            
            $list[] = [
                'notification_id' => $doc['notification_id'] ?? '',
                'sender_id' => $doc['sender_id'] ?? '',
                'sender_name' => $doc['sender_name'] ?? '',
                'sender_email' => $doc['sender_email'] ?? '',
                'group_id' => $doc['group_id'] ?? '',
                'group_name' => $doc['group_name'] ?? '',
                'message' => $doc['message'] ?? '',
                'status' => $doc['status'] ?? 'pending',
                'type' => $doc['type'] ?? 'group_invite',
                'created_at' => $created
            ];
        }
        
        respond(200, true, 'Notifications fetched successfully.', $list);
    } catch (Exception $e) {
        respond(500, false, 'Failed to fetch notifications.');
    }
    exit;
}

// POST - Send invitation or perform action
if ($method === 'POST') {
    $action = clean_text($body['action'] ?? '');
    
    // Send invitation
    if ($action === 'send_invite') {
        $recipientEmail = clean_email($body['recipient_email'] ?? '');
        $groupId = clean_text($body['group_id'] ?? '');
        
        if ($recipientEmail === '' || $groupId === '') {
            respond(422, false, 'recipient_email and group_id are required.');
            exit;
        }
        
        // Get current user info
        $currentUser = $users->findOne(['user_id' => $userId]);
        if (!$currentUser) {
            respond(404, false, 'User not found.');
            exit;
        }
        
        // Check if email is registered
        $recipientUser = $users->findOne(['email' => $recipientEmail]);
        if (!$recipientUser) {
            respond(404, false, 'User with this email is not registered in LearnLoop.');
            exit;
        }
        
        $recipientId = $recipientUser['user_id'];
        
        // Prevent self-invite
        if ($recipientId === $userId) {
            respond(422, false, 'You cannot invite yourself.');
            exit;
        }
        
        // Get group info
        $group = $groups->findOne(['group_id' => $groupId]);
        if (!$group) {
            respond(404, false, 'Group not found.');
            exit;
        }
        
        // Check if already a member
        $groupMembers = db()->selectCollection('group_members');
        $existingMember = $groupMembers->findOne([
            'group_id' => $groupId,
            'user_id' => $recipientId
        ]);
        
        if ($existingMember) {
            respond(409, false, 'User is already a member of this group.');
            exit;
        }
        
        // Check if invitation already exists
        $existingInvite = $notifications->findOne([
            'sender_id' => $userId,
            'recipient_id' => $recipientId,
            'group_id' => $groupId,
            'type' => 'group_invite',
            'status' => 'pending'
        ]);
        
        if ($existingInvite) {
            respond(409, false, 'Invitation already sent to this user.');
            exit;
        }
        
        // Create notification
        $notificationId = bin2hex(random_bytes(8));
        $now = new MongoDB\BSON\UTCDateTime();
        
        try {
            $notifications->insertOne([
                'notification_id' => $notificationId,
                'type' => 'group_invite',
                'sender_id' => $userId,
                'sender_name' => $currentUser['full_name'] ?? 'User',
                'sender_email' => $currentUser['email'] ?? '',
                'recipient_id' => $recipientId,
                'recipient_email' => $recipientEmail,
                'group_id' => $groupId,
                'group_name' => $group['group_name'] ?? '',
                'message' => ($currentUser['full_name'] ?? 'A user') . ' invited you to join ' . ($group['group_name'] ?? 'a group'),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            // Send email notification
            send_group_invite_email(
                $recipientEmail,
                $recipientUser['full_name'] ?? 'User',
                $currentUser['full_name'] ?? 'User',
                $group['group_name'] ?? 'a study group',
                $notificationId
            );
            
            respond(201, true, 'Invitation sent successfully.', ['notification_id' => $notificationId]);
        } catch (Exception $e) {
            respond(500, false, 'Failed to send invitation.');
        }
        exit;
    }
    
    // Group owner approve/decline pending invitation
    if ($action === 'admin_respond') {
        $notificationId = clean_text($body['notification_id'] ?? '');
        $response = clean_text($body['response'] ?? ''); // 'approve' or 'decline' or 'withdraw'
        
        if ($notificationId === '' || !in_array($response, ['approve', 'decline', 'withdraw'])) {
            respond(422, false, 'notification_id and response (approve/decline/withdraw) are required.');
            exit;
        }
        
        // Find notification
        $notification = $notifications->findOne([
            'notification_id' => $notificationId,
            'type' => 'group_invite',
            'status' => 'pending'
        ]);
        
        if (!$notification) {
            respond(404, false, 'Notification not found.');
            exit;
        }
        
        // Verify user is group owner
        $group = $groups->findOne(['group_id' => $notification['group_id']]);
        if (!$group || $group['user_id'] !== $userId) {
            respond(403, false, 'You do not have permission to manage this group.');
            exit;
        }
        
        $now = new MongoDB\BSON\UTCDateTime();
        $groupMembers = db()->selectCollection('group_members');
        
        if ($response === 'approve') {
            // Add user to group
            $groupMemberId = bin2hex(random_bytes(8));
            
            try {
                $groupMembers->insertOne([
                    'member_id' => $groupMemberId,
                    'group_id' => $notification['group_id'],
                    'user_id' => $notification['recipient_id'],
                    'role' => 'member',
                    'joined_at' => $now
                ]);
                
                // Update notification status
                $notifications->updateOne(
                    ['notification_id' => $notificationId],
                    ['$set' => ['status' => 'approved', 'updated_at' => $now]]
                );
                
                respond(200, true, 'Member approved successfully.', ['group_id' => $notification['group_id']]);
            } catch (Exception $e) {
                respond(500, false, 'Failed to approve member.');
            }
        } elseif ($response === 'decline') {
            // Decline invitation
            try {
                $notifications->updateOne(
                    ['notification_id' => $notificationId],
                    ['$set' => ['status' => 'declined', 'updated_at' => $now]]
                );
                
                respond(200, true, 'Invitation declined.');
            } catch (Exception $e) {
                respond(500, false, 'Failed to decline invitation.');
            }
        } else {
            // Withdraw invitation (owner cancels before recipient acts)
            try {
                $notifications->updateOne(
                    ['notification_id' => $notificationId],
                    ['$set' => ['status' => 'withdrawn', 'updated_at' => $now]]
                );
                
                respond(200, true, 'Invitation withdrawn.');
            } catch (Exception $e) {
                respond(500, false, 'Failed to withdraw invitation.');
            }
        }
        exit;
    }
    
    // Accept or reject invitation (from recipient side)
    if ($action === 'respond') {
        $notificationId = clean_text($body['notification_id'] ?? '');
        $response = clean_text($body['response'] ?? ''); // 'accept' or 'reject'
        
        if ($notificationId === '' || !in_array($response, ['accept', 'reject'])) {
            respond(422, false, 'notification_id and response (accept/reject) are required.');
            exit;
        }
        
        // Find notification
        $notification = $notifications->findOne([
            'notification_id' => $notificationId,
            'recipient_id' => $userId,
            'type' => 'group_invite',
            'status' => 'pending'
        ]);
        
        if (!$notification) {
            respond(404, false, 'Notification not found.');
            exit;
        }
        
        $now = new MongoDB\BSON\UTCDateTime();
        
        if ($response === 'accept') {
            // Add user to group
            $groupMembers = db()->selectCollection('group_members');
            $groupMemberId = bin2hex(random_bytes(8));
            
            try {
                $groupMembers->insertOne([
                    'member_id' => $groupMemberId,
                    'group_id' => $notification['group_id'],
                    'user_id' => $userId,
                    'role' => 'member',
                    'joined_at' => $now
                ]);
                
                // Update notification status
                $notifications->updateOne(
                    ['notification_id' => $notificationId],
                    ['$set' => ['status' => 'accepted', 'updated_at' => $now]]
                );
                
                respond(200, true, 'Successfully joined the group.', ['group_id' => $notification['group_id']]);
            } catch (Exception $e) {
                respond(500, false, 'Failed to join group.');
            }
        } else {
            // Reject invitation
            try {
                $notifications->updateOne(
                    ['notification_id' => $notificationId],
                    ['$set' => ['status' => 'rejected', 'updated_at' => $now]]
                );
                
                respond(200, true, 'Invitation rejected.');
            } catch (Exception $e) {
                respond(500, false, 'Failed to reject invitation.');
            }
        }
        exit;
    }

    if ($action === 'delete') {
        $notificationId = clean_text($body['notification_id'] ?? '');

        if ($notificationId === '') {
            respond(422, false, 'notification_id is required.');
            exit;
        }

        try {
            $result = $notifications->deleteOne([
                'notification_id' => $notificationId,
                'recipient_id' => $userId
            ]);

            if ($result->getDeletedCount() === 0) {
                respond(404, false, 'Notification not found.');
                exit;
            }

            respond(200, true, 'Notification deleted permanently.');
        } catch (Exception $e) {
            respond(500, false, 'Failed to delete notification.');
        }
        exit;
    }
    
    respond(422, false, 'Invalid action.');
    exit;
}

respond(405, false, 'Method not allowed. Use GET or POST.');

function read_body(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    if (!$raw || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    parse_str($raw, $data);
    return is_array($data) ? $data : [];
}

function respond(int $code, bool $ok, string $message, mixed $data = null): void
{
    http_response_code($code);
    $out = ['success' => $ok, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    echo json_encode($out);
}

function send_group_invite_email(string $recipientEmail, string $recipientName, string $senderName, string $groupName, string $notificationId): bool
{
    try {
        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/../PHPMailer/src/Exception.php';

        $subject = 'Group Invitation - LearnLoop';
        
        $message = '<html><body style="margin:0;padding:0;background:#f4f7fb;">';
        $message .= '<div style="font-family:Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#eef5ff 100%);padding:28px 16px;">';
        $message .= '<div style="max-width:640px;margin:0 auto;border:1px solid #d9e3f2;border-radius:16px;overflow:hidden;background:#ffffff;box-shadow:0 10px 30px rgba(15,23,42,0.08);">';
        $message .= '<div style="padding:28px 24px 18px;text-align:center;background:linear-gradient(180deg,#f7fbff 0%,#ffffff 100%);border-bottom:1px solid #e5ecf5;">';
        $message .= '<div style="font-size:34px;font-weight:700;line-height:1;color:#1d2f57;letter-spacing:0.2px;">LearnL<span style="color:#d6ff2f;text-shadow:0 0 10px rgba(214,255,47,0.85);">∞</span>op</div>';
        $message .= '</div>';
        $message .= '<div style="padding:28px 32px 30px;color:#20304a;">';
        $message .= '<div style="font-size:16px;line-height:1.55;color:#25364d;margin:0 0 18px;">';
        $message .= '<p style="margin:0 0 10px;">Hi ' . esc($recipientName) . ',</p>';
        $message .= '<p style="margin:0;"><strong>' . esc($senderName) . '</strong> has invited you to join a study group: <strong>' . esc($groupName) . '</strong></p>';
        $message .= '</div>';
        $message .= '<div style="background:linear-gradient(180deg,#f4f9ff 0%,#eaf3ff 100%);border:1px solid #cfdff2;border-radius:14px;padding:22px 24px;text-align:center;box-shadow:inset 0 1px 0 rgba(255,255,255,0.8);">';
        $message .= '<p style="margin:0 0 16px;color:#334155;font-size:15px;">Accept the invitation to start collaborating:</p>';
        $message .= '<a href="http://localhost/LearnLoop/pages/dashboard.php" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#4CAF50 0%,#45a049 100%);color:white;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;box-shadow:0 4px 12px rgba(76,175,80,0.3);">Check Your Notifications</a>';
        $message .= '</div>';
        $message .= '<p style="margin:20px 0 10px;font-size:15px;line-height:1.6;color:#334155;text-align:left;">Log in to LearnLoop and check your notifications panel to accept or decline the invitation.</p>';
        $message .= '<div style="border-top:1px solid #e3e8f0;padding-top:14px;text-align:center;color:#6b7280;font-size:12px;">&copy; 2026 LearnLoop. Empowering collaboration.</div>';
        $message .= '</div></div></div></body></html>';

        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bimalkandel468@gmail.com';
        $mail->Password   = 'aubh sawb jqwi anqm';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('bimalkandel468@gmail.com', 'LearnLoop');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $message;
        $mail->AltBody = $senderName . ' invited you to join ' . $groupName . '. Log in to LearnLoop to accept the invitation.';

        if ($mail->send()) {
            return true;
        }

        error_log('Email not sent. Please check your email settings.');
        return false;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

?>
