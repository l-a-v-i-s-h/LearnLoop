(function () {
  "use strict";

  // Empty by default so empty state is shown first.
  let notes = [];

  let pendingFile = null;
  let pendingDeleteId = null;

  // ===== DOM =====
  const tableWrapper = document.getElementById("notesTableWrapper");
  const tableBody = document.getElementById("notesTableBody");
  const emptyState = document.getElementById("notesEmpty");
  const uploadBtn = document.getElementById("uploadBtn");
  const fileInput = document.getElementById("noteFileInput");

  const uploadPanel = document.getElementById("uploadPanel");
  const uploadForm = document.getElementById("uploadForm");
  const targetGroup = document.getElementById("targetGroup");
  const fileChooserBtn = document.getElementById("fileChooserBtn");
  const fileChooserLabel = document.getElementById("fileChooserLabel");
  const uploadCancel = document.getElementById("uploadCancel");

  const deleteModal = document.getElementById("deleteModal");
  const deleteFileName = document.getElementById("deleteFileName");
  const deleteCancel = document.getElementById("deleteCancel");
  const deleteConfirm = document.getElementById("deleteConfirm");
  const deleteModalClose = document.getElementById("deleteModalClose");

  // ===== Helpers =====
  function formatSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  function formatDate(d) {
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    return months[d.getMonth()] + " " + d.getDate();
  }

  function getExt(name) {
    const i = name.lastIndexOf(".");
    return i >= 0 ? name.slice(i + 1).toLowerCase() : "";
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  // ===== Render =====
  function render() {
    if (notes.length === 0) {
      tableWrapper.hidden = true;
      emptyState.hidden = false;
      return;
    }
    tableWrapper.hidden = false;
    emptyState.hidden = true;

    tableBody.innerHTML = notes.map(n => `
      <div class="notes-row" data-id="${n.id}">
        <div class="col-name">
          <span class="fname" title="${escapeHtml(n.name)}">${escapeHtml(n.name)}</span>
        </div>
        <div class="col-group">${escapeHtml(n.group)}</div>
        <div class="col-size">${formatSize(n.sizeBytes)}</div>
        <div class="col-date">${escapeHtml(n.date)}</div>
        <div class="col-actions">
          <button class="row-delete" data-action="delete" data-id="${n.id}" aria-label="Delete">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>
    `).join("");
  }

  // Upload panel toggle //
  function resetUploadForm() {
    pendingFile = null;
    fileInput.value = "";
    fileChooserLabel.textContent = "Choose file ...";
    fileChooserBtn.classList.remove("selected");
    targetGroup.value = "";
    targetGroup.classList.remove("selected");
  }

  function openUploadPanel() {
    resetUploadForm();
    uploadPanel.hidden = false;
  }
  function closeUploadPanel() {
    resetUploadForm();
    uploadPanel.hidden = true;
  }

  uploadBtn.addEventListener("click", () => {
    if (uploadPanel.hidden) openUploadPanel();
    else closeUploadPanel();
  });

  fileChooserBtn.addEventListener("click", () => fileInput.click());

  fileInput.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (!file) return;
    pendingFile = file;
    fileChooserLabel.textContent = file.name + " (" + formatSize(file.size) + ")";
    fileChooserBtn.classList.add("selected");
  });

  targetGroup.addEventListener("change", () => {
    if (targetGroup.value) targetGroup.classList.add("selected");
    else targetGroup.classList.remove("selected");
  });

  uploadForm.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!targetGroup.value) { targetGroup.focus(); return; }
    if (!pendingFile) { fileChooserBtn.focus(); return; }

    const newNote = {
      id: Date.now(),
      name: pendingFile.name,
      group: targetGroup.value,
      sizeBytes: pendingFile.size,
      date: formatDate(new Date()),
      type: getExt(pendingFile.name),
    };
    notes.unshift(newNote);
    render();
    closeUploadPanel();
  });

  uploadCancel.addEventListener("click", closeUploadPanel);

  //  Delete flow // 
  tableBody.addEventListener("click", (e) => {
    const btn = e.target.closest('button[data-action="delete"]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    const note = notes.find(n => n.id === id);
    if (!note) return;
    pendingDeleteId = id;
    deleteFileName.textContent = note.name;
    deleteModal.hidden = false;
  });

  function cancelDelete() {
    pendingDeleteId = null;
    deleteModal.hidden = true;
  }
  deleteCancel.addEventListener("click", cancelDelete);
  deleteModalClose.addEventListener("click", cancelDelete);
  deleteConfirm.addEventListener("click", () => {
    if (pendingDeleteId == null) return;
    notes = notes.filter(n => n.id !== pendingDeleteId);
    pendingDeleteId = null;
    deleteModal.hidden = true;
    render();
  });

  deleteModal.addEventListener("click", (e) => {
    if (e.target === deleteModal) cancelDelete();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !deleteModal.hidden) cancelDelete();
  });

  // Init
  render();
})();
