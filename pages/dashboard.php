<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout" data-user-id="<?php echo esc($_SESSION['user']['user_id'] ?? ''); ?>">

    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <h1 class="welcome-title" style="font-size: 34px; color: #1e3a5f; margin-bottom: 40px;">Welcome, <?php echo esc($_SESSION['user']['full_name'] ?? 'Student'); ?></h1>

            <div class="stats-row">
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">My Groups</p>
                        <span class="stat-value" id="groups-count">0</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">My Notes</p>
                        <span class="stat-value" id="notes-count">0</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div style="background:white; width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#1e3a5f; font-size:22px;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <p style="font-size:14px; color:#555;">Status</p>
                        <span class="stat-value" style="font-size:24px;">Active</span>
                    </div>
                </div>
            </div>

            <div class="forums-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                    <h2 style="color:#1e3a5f;">Recent Forums</h2>
                    <a href="forums.php" style="font-weight:600; color:#1e3a5f;">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div id="recent-forums-container">
                    <div style="text-align:center; padding:20px; color:#999;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading forums...
                    </div>
                </div>
            </div>
        </main>

        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script>
            const PUSHER_KEY = '14db4509a104fa2c4d52';
            const PUSHER_CLUSTER = 'ap2';
            const currentUserId = String(document.body?.getAttribute('data-user-id') || '');

            document.addEventListener('DOMContentLoaded', function() {
                fetchGroupCount();
                fetchNotesCount();
                fetchRecentForums();
                initNotesUpdates();
            });

            function fetchGroupCount() {
                fetch('../api/group.php?action=count')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            document.getElementById('groups-count').textContent = data.data.total || 0;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching group count:', error);
                        document.getElementById('groups-count').textContent = '0';
                    });
            }

            function fetchNotesCount() {
                fetch('../api/notes.php?action=count')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            document.getElementById('notes-count').textContent = data.data.count || 0;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching notes count:', error);
                        document.getElementById('notes-count').textContent = '0';
                    });
            }

            function initNotesUpdates() {
                if (!window.Pusher || !PUSHER_KEY) {
                    return;
                }

                const pusher = new window.Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
                const channel = pusher.subscribe('notes-channel');

                const refreshIfOwnNote = (data) => {
                    if (!data) return;

                    if (String(data.user_id || '') !== currentUserId) {
                        return;
                    }

                    fetchNotesCount();
                };

                channel.bind('note-created', refreshIfOwnNote);
                channel.bind('note-deleted', refreshIfOwnNote);
            }

            setInterval(fetchNotesCount, 6000);

            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    fetchNotesCount();
                }
            });

            function fetchRecentForums() {
                fetch('../api/post.php?action=recent&limit=2')
                    .then(response => response.json())
                    .then(data => {
                        const container = document.getElementById('recent-forums-container');
                    
                        if (!data.success || !Array.isArray(data.data)) {
                            showForumsEmpty(container);
                            return;
                        }

                        if (data.data.length === 0) {
                            showForumsEmpty(container);
                            return;
                        }

                        let html = '';
                        data.data.forEach(post => {
                            const replyCount = post.reply_count || 0;
                            const replyText = replyCount === 1 ? 'Reply' : 'Replies';
                            const badgeColor = replyCount > 0 ? '#60a5fa' : '#94a3b8';
                        
                            html += `
                                <div class="forum-card">
                                    <span style="font-size:18px; color:#1e3a5f; font-weight:500;">${escapeHtml(post.title || 'Untitled')}</span>
                                    <span style="background:${badgeColor}; color:white; padding:8px 20px; border-radius:25px; font-size:13px;">${replyCount} ${replyText}</span>
                                </div>
                            `;
                        });

                        container.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error fetching recent forums:', error);
                        const container = document.getElementById('recent-forums-container');
                        showForumsEmpty(container);
                    });
            }

            function showForumsEmpty(container) {
                container.innerHTML = `
                    <div style="background:linear-gradient(135deg, #f0f4f8 0%, #d9e8f5 100%); border:1px solid #cbd5e1; border-radius:12px; padding:40px; text-align:center;">
                        <div style="background:#e0f2fe; width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#0369a1; font-size:36px; margin:0 auto 20px;">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <h3 style="color:#1e3a5f; font-size:20px; margin:0 0 10px 0;">No Recent Forums</h3>
                        <p style="color:#64748b; font-size:14px; margin:0;">Join the discussion and explore academic forums</p>
                    </div>
                `;
            }

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            }
        </script>
    </div>
</body>
</html>
