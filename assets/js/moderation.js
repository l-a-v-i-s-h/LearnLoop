(function () {
  'use strict';

  const API_URL = '../api/moderation.php';
  // read CSRF token on demand (in case it's injected or regenerated after script load)
  // do not read once at top-level to avoid stale/empty tokens
  const PUSHER_KEY = '14db4509a104fa2c4d52';
  const PUSHER_CLUSTER = 'ap2';
  const isAdminPage = document.body.classList.contains('admin-dashboard-page');
  const isChatMonitorPage = document.body.classList.contains('chat-monitor-page');
  const isBannedPage = document.body.classList.contains('banned-users-page');

  function notify(message) {
    if (window.alert) {
      window.alert(message);
    }
  }

  async function postAction(payload) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    return response.json();
  }

  function parseStatus(value) {
    return String(value || 'pending').toLowerCase().replace(/\s+/g, '_');
  }

  function currentReportId() {
    return document.querySelector('.chat-report-detail')?.getAttribute('data-report-id') || '';
  }

  function currentReportStatus() {
    return parseStatus(document.querySelector('.chat-report-detail')?.getAttribute('data-report-status') || 'pending');
  }

  function currentAdminNote() {
    return document.querySelector('[data-admin-note]')?.value || '';
  }

  const ACTION_MODAL_CONFIG = {
    delete_report: {
      kicker: 'Confirm deletion',
      title: 'Reject & Delete report',
      message: 'Reject the report, notify the reporter, and erase the report record.',
      reasonLabel: 'Reason for rejection',
      reasonPlaceholder: 'Explain why this report is being rejected.',
      confirmText: 'Reject & Delete',
      reasonRequired: true
    },
    warn: {
      kicker: 'Confirm warning',
      title: 'Warn reported user',
      message: 'Send a warning notification to the reported user.',
      reasonLabel: 'Warning reason',
      reasonPlaceholder: 'Explain what happened and why the user is being warned.',
      confirmText: 'Warn user',
      reasonRequired: true
    },
    suspend: {
      kicker: 'Confirm suspension',
      title: 'Suspend reported user',
      message: 'Suspend the user and remove them from the chat group.',
      reasonLabel: 'Suspension reason',
      reasonPlaceholder: 'Explain why the user is being suspended.',
      confirmText: 'Suspend user',
      reasonRequired: true
    },
    ban: {
      kicker: 'Confirm ban',
      title: 'Ban reported user',
      message: 'Ban the reported user from LearnLoop. No reason is required.',
      reasonLabel: 'Reason',
      reasonPlaceholder: 'Optional note for this action.',
      confirmText: 'Ban user',
      reasonRequired: false
    }
  };

  const USER_MODAL_CONFIG = {
    ban: {
      kicker: 'Confirm ban',
      title: 'Ban user',
      message: 'Ban this user and permanently remove their account from LearnLoop.',
      reasonLabel: 'Reason',
      reasonPlaceholder: 'Optional note for this action.',
      confirmText: 'Ban user',
      reasonRequired: false
    },
    warn: {
      kicker: 'Confirm warning',
      title: 'Warn user',
      message: 'Send a warning to this user.',
      reasonLabel: 'Warning reason',
      reasonPlaceholder: 'Explain the reason for the warning.',
      confirmText: 'Warn user',
      reasonRequired: true
    },
    unban: {
      kicker: 'Confirm unban',
      title: 'Unban user',
      message: 'Restore this user to active status.',
      reasonLabel: 'Note',
      reasonPlaceholder: 'Optional note for this action.',
      confirmText: 'Unban user',
      reasonRequired: false
    }
  };

  let actionModal = null;
  let actionModalClose = null;
  let actionModalCancel = null;
  let actionModalConfirm = null;
  let actionModalKicker = null;
  let actionModalTitle = null;
  let actionModalMessage = null;
  let actionModalReportId = null;
  let actionModalReasonWrap = null;
  let actionModalReasonLabel = null;
  let actionModalReason = null;
  let pendingAction = null;
  // user moderation modal elements
  let userModal = null;
  let userModalClose = null;
  let userModalCancel = null;
  let userModalConfirm = null;
  let userModalKicker = null;
  let userModalTitle = null;
  let userModalMessage = null;
  let userModalUserId = null;
  let userModalReasonWrap = null;
  let userModalReasonLabel = null;
  let userModalReason = null;
  let pendingUserAction = null;

  function openActionModal(action, reportId) {
    const config = ACTION_MODAL_CONFIG[action];
    if (!actionModal || !config) {
      return;
    }

    pendingAction = { action, reportId, ...config };
    actionModal.dataset.reportId = reportId;
    actionModal.dataset.action = action;
    actionModalKicker.textContent = config.kicker;
    actionModalTitle.textContent = config.title;
    actionModalMessage.textContent = config.message;
    actionModalReportId.textContent = reportId;
    actionModalReasonWrap.hidden = !config.reasonRequired;
    actionModalReasonLabel.textContent = config.reasonLabel;
    actionModalReason.placeholder = config.reasonPlaceholder;
    actionModalReason.value = '';
    actionModalConfirm.textContent = config.confirmText;
    actionModal.hidden = false;
    document.body.classList.add('report-modal-open');

    if (config.reasonRequired) {
      actionModalReason.focus();
    } else {
      actionModalConfirm.focus();
    }
  }

  function closeActionModal() {
    if (!actionModal) {
      return;
    }

    actionModal.hidden = true;
    actionModal.dataset.reportId = '';
    actionModal.dataset.action = '';
    pendingAction = null;
    document.body.classList.remove('report-modal-open');
  }

  function openUserModal(action, userId) {
    const config = USER_MODAL_CONFIG[action];
    if (!userModal || !config) return;

    pendingUserAction = { action, userId, ...config };
    userModal.dataset.userId = userId;
    userModal.dataset.action = action;
    userModal.setAttribute('data-action', action);
    userModalKicker.textContent = config.kicker;
    userModalTitle.textContent = config.title;
    userModalMessage.textContent = config.message;
    userModalUserId.textContent = userId;
    userModalReasonWrap.hidden = !config.reasonRequired;
    userModalReasonLabel.textContent = config.reasonLabel;
    userModalReason.placeholder = config.reasonPlaceholder;
    userModalReason.value = '';
    userModalConfirm.textContent = config.confirmText;
    // ensure overlay and card layout even if CSS is missing or overridden
    try {
      userModal.style.position = 'fixed';
      userModal.style.inset = '0';
      userModal.style.display = 'flex';
      userModal.style.alignItems = 'center';
      userModal.style.justifyContent = 'center';
      userModal.style.padding = '24px';
      userModal.style.background = 'rgba(7,24,42,0.6)';
      userModal.style.zIndex = '99999';

      const card = userModal.querySelector('.report-modal-card');
      if (card) {
        card.style.width = 'min(620px,100%)';
        card.style.borderRadius = '12px';
        card.style.background = '#fff';
        card.style.boxShadow = '0 18px 40px rgba(10,38,64,0.18)';
        card.style.padding = '20px';
        card.style.borderLeft = (action === 'ban' ? '6px solid #e55e57' : (action === 'warn' ? '6px solid #ffbd67' : (action === 'unban' ? '6px solid #7bd389' : 'none')));
      }
    } catch (e) {
      // ignore inline style failures
    }

    userModal.hidden = false;
    document.body.classList.add('report-modal-open');

    if (config.reasonRequired) {
      userModalReason.focus();
    } else {
      userModalConfirm.focus();
    }
  }

  function closeUserModal() {
    if (!userModal) return;
    userModal.hidden = true;
    userModal.dataset.userId = '';
    userModal.dataset.action = '';
    pendingUserAction = null;
    document.body.classList.remove('report-modal-open');
  }

  async function handleReportAction(button) {
    const reportId = button?.getAttribute('data-report-id') || currentReportId();
    const action = button?.getAttribute('data-report-action') || '';

    if (!reportId) {
      notify('No report selected.');
      return;
    }

    let status = currentReportStatus();
    // Prevent actions on already resolved reports
    if (status === 'resolved') {
      notify('Report already resolved — no further actions allowed.');
      return;
    }
    let moderationAction = '';
    let reason = '';

    if (['delete_report', 'warn', 'suspend', 'ban'].includes(action)) {
      openActionModal(action, reportId);
      return;
    }

    const payload = {
      action: 'review_report',
      report_id: reportId,
      status: status,
      admin_note: currentAdminNote(),
      moderation_action: moderationAction,
      reason: reason
    };

    const data = await postAction(payload);
    if (!data.success) {
      notify(data.message || 'Failed to update the report.');
      return;
    }

    window.location.reload();
  }

  async function handleUserModeration(button) {
    const userId = button?.getAttribute('data-user-id') || '';
    const action = button?.getAttribute('data-user-moderate') || '';

    if (!userId) {
      notify('No user selected.');
      return;
    }

    // Open modal-driven confirmation for user actions
    if (['ban', 'warn', 'unban'].includes(action)) {
      openUserModal(action, userId);
      return;
    }
  }

  function bindAdminEvents() {
    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      const reportAction = target.closest('[data-report-action]');
      if (reportAction) {
        event.preventDefault();
        handleReportAction(reportAction);
        return;
      }

      const userModerate = target.closest('[data-user-moderate]');
      if (userModerate) {
        event.preventDefault();
        handleUserModeration(userModerate);
      }
    });
  }

  function bindRealtime() {
    if (!window.Pusher || !PUSHER_KEY) {
      return;
    }

    const pusher = new window.Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
    const channel = pusher.subscribe('moderation-channel');

    const refresh = () => {
      window.location.reload();
    };

    channel.bind('report-created', refresh);
    channel.bind('report-updated', refresh);
    channel.bind('report-deleted', refresh);
    channel.bind('user-deleted', refresh);
    channel.bind('user-moderation-updated', refresh);
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (isAdminPage || isChatMonitorPage || isBannedPage) {
      bindAdminEvents();
      bindRealtime();
    }

    actionModal = document.getElementById('moderationActionModal');
    actionModalClose = document.getElementById('moderationActionClose');
    actionModalCancel = document.getElementById('moderationActionCancel');
    actionModalConfirm = document.getElementById('moderationActionConfirm');
    actionModalKicker = document.getElementById('moderationActionKicker');
    actionModalTitle = document.getElementById('moderationActionTitle');
    actionModalMessage = document.getElementById('moderationActionMessage');
    actionModalReportId = document.getElementById('moderationActionReportId');
    actionModalReasonWrap = document.getElementById('moderationActionReasonWrap');
    actionModalReasonLabel = document.getElementById('moderationActionReasonLabel');
    actionModalReason = document.getElementById('moderationActionReason');

    userModal = document.getElementById('userModerationModal');
    userModalClose = document.getElementById('userModerationClose');
    userModalCancel = document.getElementById('userModerationCancel');
    userModalConfirm = document.getElementById('userModerationConfirm');
    userModalKicker = document.getElementById('userModerationKicker');
    userModalTitle = document.getElementById('userModerationTitle');
    userModalMessage = document.getElementById('userModerationMessage');
    userModalUserId = document.getElementById('userModerationUserId');
    userModalReasonWrap = document.getElementById('userModerationReasonWrap');
    userModalReasonLabel = document.getElementById('userModerationReasonLabel');
    userModalReason = document.getElementById('userModerationReason');

    if (actionModal) {
      actionModal.addEventListener('click', (event) => {
        if (event.target === actionModal) {
          closeActionModal();
        }
      });
    }

    // Delegated click handling so buttons work even if DOM changes
    document.addEventListener('click', async (e) => {
      const tgt = e.target instanceof Element ? e.target : null;
      if (!tgt) return;

      // close X
      if (tgt.id === 'moderationActionClose') {
        e.preventDefault();
        closeActionModal();
        return;
      }

      // cancel
      if (tgt.id === 'moderationActionCancel') {
        e.preventDefault();
        closeActionModal();
        return;
      }

      // confirm
      if (tgt.id === 'moderationActionConfirm') {
        e.preventDefault();
        const expected = actionModal ? (actionModal.dataset.reportId || '') : '';
        const action = actionModal ? (actionModal.dataset.action || '') : '';
        const config = ACTION_MODAL_CONFIG[action];

        if (!expected) {
          notify('No report selected.');
          return;
        }

        const reasonValue = actionModalReason ? actionModalReason.value.trim() : '';
        if (config && config.reasonRequired && !reasonValue) {
          notify('Please enter a reason before continuing.');
          actionModalReason.focus();
          return;
        }

        let payload;
        if (action === 'delete_report') {
          payload = {
            action: 'delete_report',
            report_id: expected,
            reason: reasonValue
          };
        } else {
          payload = {
            action: 'review_report',
            report_id: expected,
            status: 'resolved',
            admin_note: currentAdminNote(),
            moderation_action: action,
            reason: reasonValue
          };
        }

        const data = await postAction(payload);
        if (!data.success) {
          notify(data.message || 'Failed to apply the moderation action.');
          return;
        }

        closeActionModal();
        window.location.reload();
        return;
      }

      // user modal handlers
      if (tgt.id === 'userModerationClose') {
        e.preventDefault();
        closeUserModal();
        return;
      }

      if (tgt.id === 'userModerationCancel') {
        e.preventDefault();
        closeUserModal();
        return;
      }

      if (tgt.id === 'userModerationConfirm') {
        e.preventDefault();
        const expectedId = userModal ? (userModal.dataset.userId || '') : '';
        const action = userModal ? (userModal.dataset.action || '') : '';
        const config = USER_MODAL_CONFIG[action];

        if (!expectedId) {
          notify('No user selected.');
          return;
        }

        const reasonValue = userModalReason ? userModalReason.value.trim() : '';
        if (config && config.reasonRequired && !reasonValue) {
          notify('Please enter a reason before continuing.');
          userModalReason.focus();
          return;
        }

        let status = 'active';
        if (action === 'ban') status = 'banned';
        if (action === 'warn') status = 'warned';
        if (action === 'unban') status = 'active';

        const data = await postAction({
          action: 'moderate_user',
          user_id: expectedId,
          status: status,
          reason: reasonValue
        });

        if (!data.success) {
          notify(data.message || 'Failed to update user moderation.');
          return;
        }

        closeUserModal();
        window.location.reload();
        return;
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && actionModal && !actionModal.hidden) {
        closeActionModal();
      }
    });
  });
})();
