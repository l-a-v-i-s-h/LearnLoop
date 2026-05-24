(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = Array.from(document.querySelectorAll('.chat-filter-btn'));
    const searchInput = document.querySelector('.chat-report-search input');
    const reportCards = Array.from(document.querySelectorAll('.chat-report-card'));

    function normalizeStatusText(text) {
      return text.toLowerCase().replace(/\s+/g, '_');
    }

    function applyFilter(filterKey) {
      // filterKey can be: 'all', 'pending', 'under_review', 'dismissed', 'high','medium','low'
      reportCards.forEach((card) => {
        const statusEl = card.querySelector('.chat-status');
        const priorityEl = card.querySelector('.chat-priority');

        const statusRaw = (statusEl?.textContent || '').toLowerCase().trim();
        const status = statusRaw.replace(/\s+/g, '_');
        const priority = (priorityEl?.textContent || '').toLowerCase().trim();

        let show = true;

        if (!filterKey || filterKey === 'all') {
          show = true;
        } else if (['high', 'medium', 'low'].includes(filterKey)) {
          show = (priority === filterKey);
        } else {
          show = (status === filterKey);
        }

        card.style.display = show ? '' : 'none';
      });
    }

    function clearActive() {
      filterBtns.forEach(b => b.classList.remove('is-active'));
    }

    if (filterBtns.length) {
      filterBtns.forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          clearActive();
          btn.classList.add('is-active');

          const key = (btn.textContent || '').trim().toLowerCase();
          let mapKey = key;
          // map display labels to internal keys
          if (mapKey === 'under review') mapKey = 'under_review';
          if (mapKey === 'all') mapKey = 'all';
          // 'resolved' label maps to 'resolved' key (no change needed)

          applyFilter(mapKey);
        });
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        const q = (searchInput.value || '').toLowerCase().trim();
        reportCards.forEach((card) => {
          const reporter = (card.querySelector('.chat-report-card-body h2')?.textContent || '').toLowerCase();
          const details = (card.querySelector('.chat-report-card-body p')?.textContent || '').toLowerCase();
          const meta = (card.querySelector('.chat-card-meta')?.textContent || '').toLowerCase();
          const id = (card.querySelector('.chat-report-card-head span')?.textContent || '').toLowerCase();
          const combined = `${reporter} ${details} ${meta} ${id}`;
          card.style.display = combined.includes(q) ? '' : 'none';
        });
      });
    }

    // initial show all
    applyFilter('all');
  });
})();
