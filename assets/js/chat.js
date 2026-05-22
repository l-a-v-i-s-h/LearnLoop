document.addEventListener('DOMContentLoaded', () => {
    const chatBox = document.getElementById('chatBox');
    const messagesEl = document.getElementById('chatMessages');
    const msgInput = document.getElementById('msgInput');
    const sendBtn = document.getElementById('sendBtn');
    const attachBtn = document.getElementById('attachBtn');
    const fileInput = document.getElementById('fileInput');

    const groupName = chatBox ? (chatBox.dataset.group || 'General') : 'General';
    const currentUserId = chatBox ? (chatBox.dataset.userId || '') : '';
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
    const PUSHER_KEY = '14db4509a104fa2c4d52';
    const PUSHER_CLUSTER = 'ap2';
    let activeInlineEdit = null;
    let skipOutsideCloseOnce = false;
    let pendingFiles = [];
    let messages = [];
    let realtimeEnabled = false;
    const MAX_UPLOAD_COUNT = 5;
    const MAX_UPLOAD_BYTES = 25 * 1024 * 1024; // 25 MB
    const ALLOWED_EXT = ['pdf','doc','docx','ppt','pptx','txt','png','jpg','jpeg','zip'];

    function resetPendingFile() {
        pendingFiles = [];

        if (fileInput) {
            fileInput.value = '';
        }

        if (msgInput) {
            msgInput.value = '';
            msgInput.placeholder = 'Type something...';
            msgInput.readOnly = false;
            msgInput.title = '';
        }
    }

    function esc(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function firstName(fullName) {
        const value = String(fullName || '').trim();
        if (!value) {
            return 'User';
        }
        return value.split(/\s+/)[0];
    }

    function formatFileSize(size) {
        const num = Number(size || 0);
        if (num < 1024) {
            return num + ' B';
        }
        if (num < 1024 * 1024) {
            return Math.round(num / 1024) + ' KB';
        }
        return (num / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function formatTime(value) {
        if (!value) {
            return '';
        }

        const raw = String(value).trim();
        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
        const hasZone = /[zZ]|[+\-]\d{2}:?\d{2}$/.test(normalized);
        const date = new Date(hasZone ? normalized : (normalized + 'Z'));
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Asia/Kathmandu'
        });
    }

    function renderMessage(item) {
        const isSent = item.user_id === currentUserId;
        const sender = firstName(item.sender_name);
        const timeText = formatTime(item.created_at);
        let bubbleHtml = esc(item.message || '');
        let actionsHtml = '';

        if (item.type === 'note') {
            const noteTitle = esc(item.note_title || item.message || 'Shared note');
            const noteGroup = esc(item.note_group_name || 'Shared note');
            const noteUrl = '../api/notes.php?action=download&note_id=' + encodeURIComponent(String(item.note_id || ''));
            bubbleHtml = '<div class="shared-note-card">'
                + '<div class="shared-note-chip">Study Note</div>'
                + '<h4 class="shared-note-title">' + noteTitle + '</h4>'
                + '<p class="shared-note-meta">' + noteGroup + '</p>'
                + '<a class="shared-note-link" href="' + noteUrl + '" target="_blank" rel="noopener">Open note</a>'
                + '</div>';
        } else if (item.type === 'file') {
            bubbleHtml = '<a class="file-link" href="../' + esc(item.file_path || '') + '" target="_blank" rel="noopener">'
                + esc(item.file_name || 'File')
                + '</a><small class="file-meta">' + formatFileSize(item.file_size) + '</small>';
        } else if (item.edited) {
            bubbleHtml += ' <small class="edited-tag">(edited)</small>';
        }

        if (isSent) {
            actionsHtml = '<div class="msg-actions">'
                + '<button class="msg-menu-btn" type="button" data-menu-toggle="' + esc(item.message_id) + '" aria-label="Message options"><i class="fa-solid fa-ellipsis"></i></button>'
                + '<div class="msg-menu" data-menu="' + esc(item.message_id) + '">'
                + '<button type="button" class="msg-menu-item" data-action="unsend" data-message-id="' + esc(item.message_id) + '">Unsend</button>'
                + (item.type === 'text'
                    ? '<button type="button" class="msg-menu-item" data-action="edit" data-message-id="' + esc(item.message_id) + '" data-message-text="' + esc(item.message || '') + '">Edit message</button>'
                    : '')
                + '</div>'
                + '</div>';
        }

        const contentHtml = '<div class="message-content">'
            + '<div class="bubble-wrap"><p class="bubble">' + bubbleHtml + '</p>' + actionsHtml + '</div>'
            + '</div>';

        return '<div class="message-row' + (isSent ? ' sent' : '') + '">'
            + (isSent ? '' : '<span class="avatar">' + esc(sender) + '</span>')
            + contentHtml
            + (isSent ? '<span class="avatar">' + esc(sender) + '</span>' : '')
            + '</div>';
    }

    function renderMessages(items) {
        activeInlineEdit = null;

        if (!messagesEl) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            messagesEl.innerHTML = '<div class="chat-empty">No messages yet.</div>';
            return;
        }

        messagesEl.innerHTML = items.map(renderMessage).join('');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setMessages(items) {
        messages = Array.isArray(items) ? items : [];
        renderMessages(messages);
    }

    function sortMessages() {
        messages.sort((a, b) => String(a.created_at || '').localeCompare(String(b.created_at || '')));
    }

    function upsertMessage(item) {
        if (!item || !item.message_id) {
            return;
        }

        const existingIndex = messages.findIndex(m => m.message_id === item.message_id);
        if (existingIndex >= 0) {
            messages[existingIndex] = { ...messages[existingIndex], ...item };
        } else {
            messages.push(item);
        }

        sortMessages();
        renderMessages(messages);
    }

    function removeMessage(messageId) {
        if (!messageId) {
            return;
        }

        messages = messages.filter(m => m.message_id !== messageId);
        renderMessages(messages);
    }

    function closeAllMenus() {
        if (!messagesEl) {
            return;
        }
        const openMenus = messagesEl.querySelectorAll('.msg-menu.open');
        openMenus.forEach((menu) => menu.classList.remove('open'));
    }

    function closeInlineEditor() {
        if (!activeInlineEdit) {
            return;
        }

        activeInlineEdit.bubble.innerHTML = activeInlineEdit.originalHtml;
        activeInlineEdit.row.classList.remove('editing');
        activeInlineEdit = null;
    }

    function holdOutsideCloseOnce() {
        skipOutsideCloseOnce = true;
        setTimeout(() => {
            skipOutsideCloseOnce = false;
        }, 0);
    }

    function openInlineEditor(triggerEl, messageId, oldText) {
        if (!triggerEl || !messageId) {
            return;
        }

        const row = triggerEl.closest('.message-row');
        if (!row) {
            return;
        }

        const bubble = row.querySelector('.bubble');
        if (!bubble) {
            return;
        }

        if (activeInlineEdit && activeInlineEdit.bubble === bubble) {
            const currentInput = bubble.querySelector('.inline-edit-input');
            if (currentInput) {
                currentInput.focus();
            }
            return;
        }

        closeInlineEditor();

        const originalHtml = bubble.innerHTML;
        row.classList.add('editing');
        bubble.innerHTML = '';

        const wrap = document.createElement('div');
        wrap.className = 'inline-edit-wrap';

        const input = document.createElement('textarea');
        input.className = 'inline-edit-input';
        input.value = String(oldText || '');

        const actions = document.createElement('div');
        actions.className = 'inline-edit-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'inline-edit-btn cancel';
        cancelBtn.textContent = 'Cancel';

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'inline-edit-btn save';
        saveBtn.textContent = 'Save';

        actions.appendChild(cancelBtn);
        actions.appendChild(saveBtn);
        wrap.appendChild(input);
        wrap.appendChild(actions);
        bubble.appendChild(wrap);

        activeInlineEdit = { row, bubble, originalHtml };
        holdOutsideCloseOnce();

        const submitInlineEdit = async () => {
            const message = input.value.trim();
            if (!message) {
                notify('error', 'Message cannot be empty.');
                return;
            }

            saveBtn.disabled = true;
            cancelBtn.disabled = true;

            const response = await fetch('../api/chat.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    message_id: messageId,
                    message: message
                })
            });
            const data = await response.json();

            saveBtn.disabled = false;
            cancelBtn.disabled = false;

            if (!data.success) {
                notify('error', data.message || 'Failed to edit message.');
                return;
            }

            if (!realtimeEnabled) {
                await loadMessages();
            }
        };

        saveBtn.addEventListener('click', submitInlineEdit);
        cancelBtn.addEventListener('click', closeInlineEditor);

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeInlineEditor();
                return;
            }

            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitInlineEdit();
            }
        });

        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }

    function openInlineUnsendConfirm(triggerEl, messageId) {
        if (!triggerEl || !messageId) {
            return;
        }

        const row = triggerEl.closest('.message-row');
        if (!row) {
            return;
        }

        const bubble = row.querySelector('.bubble');
        if (!bubble) {
            return;
        }

        if (activeInlineEdit && activeInlineEdit.bubble === bubble) {
            return;
        }

        closeInlineEditor();

        const originalHtml = bubble.innerHTML;
        row.classList.add('editing');
        bubble.innerHTML = '';

        const wrap = document.createElement('div');
        wrap.className = 'inline-edit-wrap';

        const text = document.createElement('div');
        text.className = 'inline-warning-text';
        text.textContent = 'Delete this message permanently?';

        const actions = document.createElement('div');
        actions.className = 'inline-edit-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'inline-edit-btn cancel';
        cancelBtn.textContent = 'Cancel';

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'inline-edit-btn danger';
        deleteBtn.textContent = 'Unsend';

        actions.appendChild(cancelBtn);
        actions.appendChild(deleteBtn);
        wrap.appendChild(text);
        wrap.appendChild(actions);
        bubble.appendChild(wrap);

        activeInlineEdit = { row, bubble, originalHtml };
        holdOutsideCloseOnce();

        cancelBtn.addEventListener('click', closeInlineEditor);
        deleteBtn.addEventListener('click', async () => {
            deleteBtn.disabled = true;
            cancelBtn.disabled = true;

            const response = await fetch('../api/chat.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ message_id: messageId })
            });
            const data = await response.json();

            deleteBtn.disabled = false;
            cancelBtn.disabled = false;

            if (!data.success) {
                notify('error', data.message || 'Failed to unsend message.');
                return;
            }

            if (!realtimeEnabled) {
                await loadMessages();
            }
        });
    }

    async function unsendMessage(messageId) {
        // kept for compatibility; unsend now uses inline confirm
        if (messageId && !realtimeEnabled) {
            await loadMessages();
        }
    }

    async function loadMessages() {
        if (!chatBox) {
            return;
        }

        const response = await fetch('../api/chat.php?group=' + encodeURIComponent(groupName));
        const data = await response.json();
        if (!data.success) {
            setMessages([]);
            return;
        }
        setMessages(data.data || []);
    }

    async function sendCurrentMessage() {
        if (!msgInput || !sendBtn) {
            return;
        }

        if (pendingFiles && pendingFiles.length > 0) {
            await sendFileMessage(pendingFiles);
            return;
        }

        const message = msgInput.value.trim();
        if (!message) {
            return;
        }

        sendBtn.disabled = true;

        const response = await fetch('../api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                group: groupName,
                message: message
            })
        });

        const data = await response.json();
        sendBtn.disabled = false;

            if (!data.success) {
            notify('error', data.message || 'Failed to send message.');
            return;
        }

        msgInput.value = '';
        if (!realtimeEnabled) {
            await loadMessages();
        }
    }

    async function sendFileMessage(files) {
        if (!files || !attachBtn) return;

        const formData = new FormData();
        formData.append('group', groupName);

        if (!Array.isArray(files)) {
            files = [files];
        }

        for (let i = 0; i < files.length; i++) {
            formData.append('file[]', files[i], files[i].name);
        }

        attachBtn.disabled = true;

        const response = await fetch('../api/chat.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        });

        const data = await response.json();
        attachBtn.disabled = false;

            if (!data.success) {
            let msg = data.message || 'Failed to send file.';
            if (data.errors && Array.isArray(data.errors)) {
                msg += '\n' + data.errors.map(e => (e.file ? e.file + ': ' : '') + (e.message || e.code)).join('\n');
            }
            notify('error', msg);
            return;
        }

        resetPendingFile();
        if (!realtimeEnabled) {
            await loadMessages();
        }
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', sendCurrentMessage);
    }

    if (msgInput) {
        msgInput.addEventListener('keydown', (event) => {
            if (pendingFiles && pendingFiles.length > 0 && (event.key === 'Backspace' || event.key === 'Delete' || event.key === 'Escape')) {
                event.preventDefault();
                resetPendingFile();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                sendCurrentMessage();
            }
        });
    }

    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            const list = fileInput.files ? Array.from(fileInput.files) : [];
            if (!list || list.length === 0) return;

            if (list.length > MAX_UPLOAD_COUNT) {
                notify('error', 'You can upload up to ' + MAX_UPLOAD_COUNT + ' files only.');
                fileInput.value = '';
                return;
            }

            // simple client-side validation
            for (const f of list) {
                if (f.size <= 0) { notify('error','One of the selected files is empty.'); fileInput.value = ''; return; }
                if (f.size > MAX_UPLOAD_BYTES) { notify('error','Each file must be ' + (MAX_UPLOAD_BYTES / (1024*1024)) + ' MB or less.'); fileInput.value = ''; return; }
                const name = f.name || '';
                const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
                if (!ALLOWED_EXT.includes(ext)) { notify('error','Unsupported file type: .' + ext); fileInput.value = ''; return; }
            }

            pendingFiles = list;
            if (msgInput) {
                msgInput.value = pendingFiles.map(f => f.name).join(', ');
                msgInput.readOnly = true;
                msgInput.title = 'Press Backspace to cancel selected files';
            }
        });
    }

    if (messagesEl) {
        messagesEl.addEventListener('click', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            const toggle = target.closest('[data-menu-toggle]');
            if (toggle) {
                event.stopPropagation();
                const id = toggle.getAttribute('data-menu-toggle') || '';
                const menu = messagesEl.querySelector('[data-menu="' + id + '"]');
                if (menu) {
                    const willOpen = !menu.classList.contains('open');
                    closeAllMenus();
                    if (willOpen) {
                        menu.classList.add('open');
                    }
                }
                return;
            }

            const item = target.closest('[data-action]');
            if (item) {
                event.stopPropagation();
                const action = item.getAttribute('data-action') || '';
                const messageId = item.getAttribute('data-message-id') || '';
                const oldText = item.getAttribute('data-message-text') || '';

                closeAllMenus();

                if (action === 'unsend') {
                    openInlineUnsendConfirm(item, messageId);
                }

                if (action === 'edit') {
                    openInlineEditor(item, messageId, oldText);
                }
                return;
            }

            if (target.closest('.inline-edit-wrap')) {
                return;
            }

            closeAllMenus();
        });

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                closeAllMenus();
                return;
            }

            if (!target.closest('.msg-actions')) {
                closeAllMenus();
            }

            if (skipOutsideCloseOnce) {
                return;
            }

            if (!target.closest('.inline-edit-wrap')) {
                closeInlineEditor();
            }
        });
    }

    function initChatRealtime() {
        if (!window.Pusher || !PUSHER_KEY) {
            return;
        }

        const pusher = new window.Pusher(PUSHER_KEY, {
            cluster: PUSHER_CLUSTER
        });

        const channel = pusher.subscribe('chat-channel');
        realtimeEnabled = true;

        const mapPayload = (data) => ({
            message_id: String(data.message_id || ''),
            group: String(data.group || ''),
            user_id: String(data.user_id || ''),
            sender_name: String(data.sender_name || ''),
            type: String(data.type || 'text'),
            message: String(data.message || ''),
            note_id: String(data.note_id || ''),
            note_title: String(data.note_title || ''),
            note_group_id: String(data.note_group_id || ''),
            note_group_name: String(data.note_group_name || ''),
            note_visibility: String(data.note_visibility || ''),
            file_name: String(data.file_name || ''),
            file_path: String(data.file_path || ''),
            file_size: Number(data.file_size || 0),
            edited: Boolean(data.edited),
            created_at: String(data.created_at || '')
        });

        channel.bind('message-created', (data) => {
            if (!data || String(data.group || '') !== groupName) {
                return;
            }

            const message = mapPayload(data);
            if (!message.message_id) {
                return;
            }

            upsertMessage(message);
        });

        channel.bind('message-updated', (data) => {
            if (!data || String(data.group || '') !== groupName) {
                return;
            }

            const message = mapPayload(data);
            if (!message.message_id) {
                return;
            }

            upsertMessage(message);
        });

        channel.bind('message-deleted', (data) => {
            if (!data || String(data.group || '') !== groupName) {
                return;
            }

            const messageId = String(data.message_id || '');
            removeMessage(messageId);
        });
    }

    initChatRealtime();
    loadMessages();

    // Load and display group members
    function loadGroupMembers() {
        const memberListEl = document.querySelector('.member-list-figma');
        if (!memberListEl) return;

        try {
                const membersJson = chatBox ? (chatBox.dataset.members || '[]') : '[]';
                let members = [];
                try {
                    members = JSON.parse(membersJson);
                } catch (err) {
                    console.error('Failed to parse members JSON:', err, membersJson);
                    members = [];
                }
            
            if (!Array.isArray(members) || members.length === 0) {
                memberListEl.innerHTML = '<div class="member-item"><span class="user-icon"></span> <div><strong>No members</strong></div></div>';
                return;
            }

            // Update member count
            const memberHeader = document.querySelector('.members-header-inline small');
            if (memberHeader) {
                const count = members.length;
                memberHeader.textContent = count + ' member' + (count !== 1 ? 's' : '');
            }

            // Render members
            memberListEl.innerHTML = members.map(member => {
                const isYou = member.user_id === currentUserId;
                const displayName = isYou ? 'You' : member.full_name;
                const roleText = member.role === 'owner' ? 'Owner' : 'Member';
                
                return `<div class="member-item">
                    <span class="user-icon"></span>
                    <div>
                        <strong>${esc(displayName)}</strong>
                        <p>${roleText}</p>
                    </div>
                </div>`;
            }).join('');
        } catch (error) {
            console.error('Error parsing members:', error);
            memberListEl.innerHTML = '<div class="member-item"><span class="user-icon"></span> <div><strong>Error loading members</strong></div></div>';
        }
    }

    loadGroupMembers();

    const addBtn = document.getElementById('addMemberBtn');
    const inviteCard = document.getElementById('inviteCard');
    const cancelBtn = document.getElementById('cancelInvite');
    const confirmBtn = document.getElementById('confirmInvite');
    const toast = document.getElementById('inviteToast');
    const emailInput = document.getElementById('inviteEmail');
    const groupId = chatBox ? (chatBox.dataset.groupId || '') : '';

    // Add event listeners only for elements that exist
    if (addBtn && inviteCard && emailInput) {
        addBtn.addEventListener('click', () => {
            inviteCard.style.display = 'block';
            emailInput.focus();
        });
    }

    if (cancelBtn && inviteCard && emailInput) {
        cancelBtn.addEventListener('click', () => {
            inviteCard.style.display = 'none';
            emailInput.value = '';
        });
    }

    if (confirmBtn && emailInput && inviteCard && toast) {
        confirmBtn.addEventListener('click', async () => {
        const email = emailInput.value.trim();
        
        if (email === "") {
            notify('error', "Please enter an email address.");
            return;
        }

        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            notify('error', "Please enter a valid email address.");
            return;
        }

        if (!groupId) {
            notify('error', "Group information not found.");
            return;
        }

        confirmBtn.disabled = true;

        try {
            const response = await fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'send_invite',
                    recipient_email: email,
                    group_id: groupId
                })
            });

            const data = await response.json();
            confirmBtn.disabled = false;

            if (data.success) {
                inviteCard.style.display = 'none';
                emailInput.value = '';
                
                toast.style.display = 'block';
                toast.textContent = 'Invitation sent successfully!';
                
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 3000);
            } else {
                notify('error', data.message || 'Failed to send invitation');
            }
        } catch (error) {
            confirmBtn.disabled = false;
            console.error('Error sending invitation:', error);
            notify('error', 'An error occurred while sending the invitation.');
        }
        });
    }

    // Allow pressing Enter to send invite
    if (emailInput && confirmBtn) {
        emailInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirmBtn.click();
            }
        });
    }

    // Load pending invitations for group owner
    const isOwner = chatBox ? (chatBox.dataset.isOwner === 'true') : false;
    const groupIdForPending = chatBox ? (chatBox.dataset.groupId || '') : '';
    
    if (isOwner && groupIdForPending) {
        loadPendingInvitations();
        // Refresh pending invitations every 15 seconds
        setInterval(loadPendingInvitations, 15000);
    }

    async function loadPendingInvitations() {
        try {
            const response = await fetch('../api/notifications.php?action=pending_invitations');
            const data = await response.json();
            
            if (data.success && Array.isArray(data.data)) {
                const groupPending = data.data.filter(inv => inv.group_id === groupIdForPending);
                renderPendingInvitations(groupPending);
            }
        } catch (error) {
            console.error('Failed to load pending invitations:', error);
        }
    }

    function renderPendingInvitations(pending) {
        const section = document.getElementById('pendingInvitationsSection');
        const list = document.getElementById('pendingList');
        
        if (!section || !list) return;
        
        if (pending.length === 0) {
            section.style.display = 'none';
            return;
        }
        
        section.style.display = 'block';
        list.innerHTML = pending.map(inv => `
            <div style="padding: 10px; background: #f9f9f9; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <div style="flex: 1; min-width: 0;">
                    <p style="margin: 0 0 4px; font-size: 13px; color: #333; word-break: break-all;">${esc(inv.recipient_email)}</p>
                    <small style="color: #999; font-size: 12px;">${formatTime(inv.created_at)}</small>
                </div>
                <div style="display: flex; gap: 4px; flex-shrink: 0;">
                    <button
                        class="pending-btn-withdraw"
                        onclick="handleAdminResponse('${esc(inv.notification_id)}', 'withdraw')"
                        style="padding: 6px 10px; background: #f97316; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;"
                        title="Withdraw invitation"
                    >
                        Withdraw
                    </button>
                    <button 
                        class="pending-btn-decline" 
                        onclick="handleAdminResponse('${esc(inv.notification_id)}', 'decline')"
                        style="padding: 4px 8px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;"
                    >
                        ✕
                    </button>
                </div>
            </div>
        `).join('');
    }

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

    window.handleAdminResponse = async (notificationId, response) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        try {
            const fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'admin_respond',
                    notification_id: notificationId,
                    response: response
                })
            };

            const apiResponse = await fetch('../api/notifications.php', fetchOptions);
            const data = await apiResponse.json();

            if (data.success) {
                if (response === 'withdraw') {
                    notify('info', 'Invitation withdrawn.');
                } else if (response === 'approve') {
                    notify('success', 'Member approved!');
                } else {
                    notify('info', 'Invitation declined');
                }
                // Reload pending invitations
                await loadPendingInvitations();
            } else {
                notify('error', data.message || 'Failed to process request');
            }
        } catch (error) {
            console.error('Error handling admin response:', error);
            notify('error', 'An error occurred');
        }
    };
});