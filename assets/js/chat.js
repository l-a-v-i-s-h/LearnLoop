document.addEventListener('DOMContentLoaded', () => {
    const chatBox = document.getElementById('chatBox');
    const messagesEl = document.getElementById('chatMessages');
    const msgInput = document.getElementById('msgInput');
    const sendBtn = document.getElementById('sendBtn');
    const attachBtn = document.getElementById('attachBtn');
    const fileInput = document.getElementById('fileInput');

    const groupName = chatBox ? (chatBox.dataset.group || 'General') : 'General';
    const currentUserId = chatBox ? (chatBox.dataset.userId || '') : '';
    let activeInlineEdit = null;
    let skipOutsideCloseOnce = false;
    let pendingFile = null;

    function resetPendingFile() {
        pendingFile = null;

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

        if (item.type === 'file') {
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
            + '<div class="msg-meta' + (isSent ? ' sent' : '') + '">'
            + (!isSent ? '<span class="msg-sender">' + esc(sender) + '</span>' : '')
            + (timeText ? '<span class="msg-time">' + esc(timeText) + '</span>' : '')
            + '</div>'
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
                alert('Message cannot be empty.');
                return;
            }

            saveBtn.disabled = true;
            cancelBtn.disabled = true;

            const response = await fetch('../api/chat.php', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message_id: messageId,
                    message: message
                })
            });
            const data = await response.json();

            saveBtn.disabled = false;
            cancelBtn.disabled = false;

            if (!data.success) {
                alert(data.message || 'Failed to edit message.');
                return;
            }

            await loadMessages();
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId })
            });
            const data = await response.json();

            deleteBtn.disabled = false;
            cancelBtn.disabled = false;

            if (!data.success) {
                alert(data.message || 'Failed to unsend message.');
                return;
            }

            await loadMessages();
        });
    }

    async function unsendMessage(messageId) {
        // kept for compatibility; unsend now uses inline confirm
        if (messageId) {
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
            renderMessages([]);
            return;
        }
        renderMessages(data.data || []);
    }

    async function sendCurrentMessage() {
        if (!msgInput || !sendBtn) {
            return;
        }

        if (pendingFile) {
            await sendFileMessage(pendingFile);
            return;
        }

        const message = msgInput.value.trim();
        if (!message) {
            return;
        }

        sendBtn.disabled = true;

        const response = await fetch('../api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                group: groupName,
                message: message
            })
        });

        const data = await response.json();
        sendBtn.disabled = false;

        if (!data.success) {
            alert(data.message || 'Failed to send message.');
            return;
        }

        msgInput.value = '';
        await loadMessages();
    }

    async function sendFileMessage(file) {
        if (!file || !attachBtn) {
            return;
        }

        const formData = new FormData();
        formData.append('group', groupName);
        formData.append('file', file);

        attachBtn.disabled = true;

        const response = await fetch('../api/chat.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        attachBtn.disabled = false;

        if (!data.success) {
            alert(data.message || 'Failed to send file.');
            return;
        }

        resetPendingFile();

        await loadMessages();
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', sendCurrentMessage);
    }

    if (msgInput) {
        msgInput.addEventListener('keydown', (event) => {
            if (pendingFile && (event.key === 'Backspace' || event.key === 'Delete' || event.key === 'Escape')) {
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
            const selected = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (selected) {
                pendingFile = selected;
                if (msgInput) {
                    msgInput.value = selected.name;
                    msgInput.readOnly = true;
                    msgInput.title = 'Press Backspace to cancel selected file';
                }
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

    loadMessages();

    const addBtn = document.getElementById('addMemberBtn');
    const inviteCard = document.getElementById('inviteCard');
    const cancelBtn = document.getElementById('cancelInvite');
    const confirmBtn = document.getElementById('confirmInvite');
    const toast = document.getElementById('inviteToast');
    const emailInput = document.getElementById('inviteEmail');

    if (!addBtn || !inviteCard || !cancelBtn || !confirmBtn || !toast || !emailInput) {
        return;
    }

    addBtn.addEventListener('click', () => {
        inviteCard.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        inviteCard.style.display = 'none';
        emailInput.value = '';
    });

    confirmBtn.addEventListener('click', () => {
        if (emailInput.value.trim() !== "") {
            inviteCard.style.display = 'none';
            emailInput.value = '';
            
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        } else {
            alert("Please enter an email address.");
        }
    });
});