// Notification Panel Script
document.addEventListener('DOMContentLoaded', () => {
    const notificationBell = document.getElementById('notificationBell');
    const notificationBadge = document.getElementById('notificationBadge');
    let notificationPanel = null;
    let notificationOverlay = null;
    const apiUrl = '../api/notifications.php';

    // Initialize notification system
    function initNotifications() {
        loadNotificationCount();
        // Refresh notifications every 30 seconds
        setInterval(loadNotificationCount, 30000);
    }

    // Load notification count
    async function loadNotificationCount() {
        try {
            const response = await fetch(`${apiUrl}?action=count`);
            const data = await response.json();
            
            if (data.success && data.data) {
                const count = data.data.count || 0;
                updateBadge(count);
            }
        } catch (error) {
            console.error('Failed to load notification count:', error);
        }
    }

    // Update badge
    function updateBadge(count) {
        if (count > 0) {
            notificationBadge.textContent = count > 99 ? '99+' : count;
            notificationBadge.style.display = 'flex';
        } else {
            notificationBadge.style.display = 'none';
        }
    }

    // Toggle notification panel
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        if (notificationPanel && notificationPanel.style.display === 'flex') {
            closeNotificationPanel();
        } else {
            openNotificationPanel();
        }
    });

    // Open notification panel
    async function openNotificationPanel() {
        // Create panel if not exists
        if (!notificationPanel) {
            createNotificationPanel();
        }

        notificationPanel.style.display = 'flex';
        notificationOverlay.classList.add('active');

        // Load notifications
        await loadNotifications();
    }

    // Close notification panel
    function closeNotificationPanel() {
        if (notificationPanel) {
            notificationPanel.style.display = 'none';
        }
        notificationOverlay.classList.remove('active');
    }

    // Create notification panel DOM
    function createNotificationPanel() {
        // Create overlay
        notificationOverlay = document.createElement('div');
        notificationOverlay.className = 'notification-overlay';
        notificationOverlay.addEventListener('click', closeNotificationPanel);
        document.body.appendChild(notificationOverlay);

        // Create panel
        notificationPanel = document.createElement('div');
        notificationPanel.className = 'notification-panel';
        notificationPanel.innerHTML = `
            <div class="notification-panel-header">
                <h3>Notifications</h3>
                <button class="notification-close-btn" aria-label="Close notifications">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="notification-list" id="notificationList">
                <div class="notification-empty">
                    <i class="fa-solid fa-bell-slash"></i>
                    <p class="notification-empty-text">No notifications yet</p>
                </div>
            </div>
        `;

        // Close button handler
        const closeBtn = notificationPanel.querySelector('.notification-close-btn');
        closeBtn.addEventListener('click', closeNotificationPanel);

        document.body.appendChild(notificationPanel);
    }

    // Load notifications from API
    async function loadNotifications() {
        try {
            const response = await fetch(apiUrl);
            const data = await response.json();

            if (data.success && Array.isArray(data.data)) {
                renderNotifications(data.data);
            } else {
                renderNotifications([]);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
            renderNotifications([]);
        }
    }

    // Render notifications
    function renderNotifications(notifications) {
        const notificationList = document.getElementById('notificationList');
        
        if (!notificationList) return;

        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fa-solid fa-bell-slash"></i>
                    <p class="notification-empty-text">No notifications yet</p>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notification => {
            const isPending = notification.status === 'pending';
            const isGroupInvite = notification.type === 'group_invite';
            const titleMap = {
                group_invite: `${esc(notification.sender_name)} invited you`,
                report_rejected: 'Your report was rejected',
                user_warned: 'Your account was warned',
                user_suspended: 'Your account was suspended',
                user_deleted: 'Your account was removed'
            };
            const titleText = titleMap[notification.type] || `${esc(notification.sender_name)} sent you a notification`;

            let actionButtons = '';
            let deleteButton = '';
            if (isPending && isGroupInvite) {
                actionButtons = `
                    <div class="notification-item-actions">
                        <button 
                            class="notification-btn notification-btn-accept"
                            onclick="handleNotificationResponse('${esc(notification.notification_id)}', 'accept')"
                        >
                            Accept
                        </button>
                        <button 
                            class="notification-btn notification-btn-reject"
                            onclick="handleNotificationResponse('${esc(notification.notification_id)}', 'reject')"
                        >
                            Decline
                        </button>
                    </div>
                `;
            }

            deleteButton = `
                <button
                    type="button"
                    class="notification-item-delete"
                    aria-label="Delete notification"
                    onclick="handleNotificationDelete('${esc(notification.notification_id)}')"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;

            return `
                <div class="notification-item ${isPending ? 'unread' : ''}" data-notification-id="${esc(notification.notification_id)}">
                    <div class="notification-item-header">
                        <div class="notification-item-title-wrap">
                            <h4 class="notification-item-title">${titleText}</h4>
                            <span class="notification-item-time">${formatTime(notification.created_at)}</span>
                        </div>
                        ${deleteButton}
                    </div>
                    <p class="notification-item-group">📚 ${esc(notification.group_name)}</p>
                    <p class="notification-item-message">${esc(notification.message)}</p>
                    ${actionButtons}
                </div>
            `;
        }).join('');
    }

    // Handle notification response
    window.handleNotificationResponse = async (notificationId, response) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        try {
            const fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'respond',
                    notification_id: notificationId,
                    response: response
                })
            };

            const apiResponse = await fetch(apiUrl, fetchOptions);
            const data = await apiResponse.json();

            if (data.success) {
                if (response === 'accept') {
                    showNotification('Successfully joined the group!', 'success');
                    // remove the accepted notification from the panel immediately
                    try {
                        const node = document.querySelector(`[data-notification-id="${notificationId}"]`);
                        if (node && node.parentNode) {
                            node.parentNode.removeChild(node);
                        }
                    } catch (err) {
                        console.error('Failed to remove accepted notification from DOM', err);
                    }
                    // update badge count
                    await loadNotificationCount();
                } else {
                    showNotification('Invitation declined', 'info');
                    // reload to reflect declined status
                    await loadNotifications();
                    await loadNotificationCount();
                }
            } else {
                showNotification(data.message || 'Failed to process invitation', 'error');
            }
        } catch (error) {
            console.error('Error handling notification response:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    };

    // Permanently delete a notification
    window.handleNotificationDelete = async (notificationId) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const apiResponse = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'delete',
                    notification_id: notificationId
                })
            });

            const data = await apiResponse.json();

            if (data.success) {
                const node = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (node && node.parentNode) {
                    node.parentNode.removeChild(node);
                }

                const list = document.getElementById('notificationList');
                if (list && !list.children.length) {
                    list.innerHTML = `
                        <div class="notification-empty">
                            <i class="fa-solid fa-bell-slash"></i>
                            <p class="notification-empty-text">No notifications yet</p>
                        </div>
                    `;
                }

                await loadNotificationCount();
                showNotification('Notification deleted', 'success');
            } else {
                showNotification(data.message || 'Failed to delete notification', 'error');
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    };

    // Format time
    function formatTime(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return minutes + ' min ago';
        if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        if (days < 7) return days + ' day' + (days > 1 ? 's' : '') + ' ago';

        return date.toLocaleDateString();
    }

    // Escape HTML
    function esc(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    // Show notification/toast
    function showNotification(message, type = 'info') {
        const toast = document.getElementById('appToast');
        if (toast) {
            // Use existing toast system
            toast.textContent = message;
            toast.className = 'app-toast ' + type;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    }

    // Close panel when clicking outside
    document.addEventListener('click', (e) => {
        if (notificationPanel && 
            notificationPanel.style.display === 'flex' && 
            !notificationPanel.contains(e.target) && 
            !notificationBell.contains(e.target)) {
            closeNotificationPanel();
        }
    });

    // Initialize
    initNotifications();
});
