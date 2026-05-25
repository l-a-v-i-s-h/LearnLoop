(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const filterContainer = document.querySelector('.banned-filters');
    const filterButtons = filterContainer ? Array.from(filterContainer.querySelectorAll('.filter-btn')) : [];
    const searchInput = document.querySelector('.banned-list-search input');
    const rows = Array.from(document.querySelectorAll('.banned-user-row'));

    function applyFilter(status) {
      // status: '' => all, otherwise status like 'active','warned','banned','suspended'
      rows.forEach((row) => {
        const s = (row.getAttribute('data-user-status') || '').toLowerCase();
        if (!status || status === 'all') {
          row.style.display = '';
        } else if (status === 'warned') {
          row.style.display = (s === 'warned') ? '' : 'none';
        } else if (status === 'banned') {
          row.style.display = (s === 'banned') ? '' : 'none';
        } else if (status === 'suspended') {
          row.style.display = (s === 'suspended') ? '' : 'none';
        } else {
          row.style.display = (s === status) ? '' : 'none';
        }
      });
    }

    function clearActiveButtons() {
      filterButtons.forEach(b => b.classList.remove('is-active'));
    }

    if (filterButtons.length) {
      filterButtons.forEach((btn) => {
        btn.addEventListener('click', (e) => {
          const text = (btn.textContent || '').trim().toLowerCase();
          clearActiveButtons();
          btn.classList.add('is-active');

          const key = text === 'all' ? 'all' : text;
          applyFilter(key);
        });
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        const q = (searchInput.value || '').toLowerCase().trim();
        rows.forEach((row) => {
          const name = (row.querySelector('.banned-user-copy strong')?.textContent || '').toLowerCase();
          const handle = (row.querySelector('.banned-user-copy span')?.textContent || '').toLowerCase();
          const combined = `${name} ${handle}`;
          row.style.display = combined.includes(q) ? '' : 'none';
        });
      });
    }

    // Initial: show all
    applyFilter('all');
  });
})();
