(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // --- Interface Core Hook References Selection Matrix ---
    const searchInput = document.getElementById('registryFilterInput');
    const rows = Array.from(document.querySelectorAll('#registryDataRowsCollection .registry-data-table-row'));

    // --- Modal Interface Handlers ---
    const modal = document.getElementById('userModerationModal');
    const modalUserId = document.getElementById('userModerationUserId');
    const modalReason = document.getElementById('userModerationReason');
    const modalClose = document.getElementById('userModerationClose');
    const modalCancel = document.getElementById('userModerationCancel');
    const modalConfirm = document.getElementById('userModerationConfirm');

    /**
     * Dual Combined Processing Matrix Filtering Execution
     */
    function performCombinedDataQuery() {
      const queryValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

      rows.forEach((row) => {
        // Read text elements inside the grid layout row
        const studentName = (row.querySelector('.student-identity-meta strong')?.textContent || '').toLowerCase();
        const studentId = (row.querySelector('.id-hash-dim')?.textContent || '').toLowerCase();
        const studentEmail = (row.querySelector('.student-text-data-cell')?.textContent || '').toLowerCase();
        const textTargetString = `${studentName} ${studentEmail} ${studentId}`;

        const matchesQuery = textTargetString.includes(queryValue);

        if (matchesQuery) {
          row.style.display = 'grid'; // Retain table row structure pattern alignment
        } else {
          row.style.display = 'none';
        }
      });
    }

    // Input Key Up Listener Driver Execution
    if (searchInput) {
      searchInput.addEventListener('input', performCombinedDataQuery);
    }

    /**
     * Overlay Layer Controller Actions Definitions
     */
    function openModerationView(targetUserId) {
      if (!modal) return;
      modal.removeAttribute('hidden');
      if (modalUserId) modalUserId.textContent = targetUserId;
    }

    function closeModerationView() {
      if (modal) {
        modal.setAttribute('hidden', 'true');
        if (modalReason) modalReason.value = '';
      }
    }

    // Delegated Content Event Catch Handlers within Container Wrapper
    const collectionHub = document.getElementById('registryDataRowsCollection');
    if (collectionHub) {
      collectionHub.addEventListener('click', (e) => {
        const banBtn = e.target.closest('[data-user-moderate]');
        if (!banBtn) return;

        const targetId = banBtn.getAttribute('data-user-id');
        openModerationView(targetId);
      });
    }

    if (modalClose) modalClose.addEventListener('click', closeModerationView);
    if (modalCancel) modalCancel.addEventListener('click', closeModerationView);

    if (modalConfirm) {
      modalConfirm.addEventListener('click', () => {
        const logReasonText = modalReason ? modalReason.value.trim() : '';
        if (!logReasonText) {
          alert('Please insert a valid data override tracking explanation notice log before submitting.');
          return;
        }
        alert(`Account target data token entry ${modalUserId.textContent} status flagged cleanly inside interface mockup preview layout context.`);
        closeModerationView();
      });
    }

    // Initialize display framework engine status
    performCombinedDataQuery();
  });
})();