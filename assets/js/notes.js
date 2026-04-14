(function () {
  "use strict";

  const API_URL = "../api/notes.php";
  const GROUP_API_URL = "../api/group.php";

  // Empty by default so empty state is shown first.
  let notes = [];

  let pendingFile = null;
  let pendingDeleteId = null;
  let groups = [];

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

  function formatApiDate(dateText) {
    if (!dateText) return "";

    const parsed = new Date(String(dateText).replace(" ", "T"));
    if (Number.isNaN(parsed.getTime())) {
      return String(dateText);
    }

    return formatDate(parsed);
  }

  function getExt(name) {
    const i = name.lastIndexOf(".");
    return i >= 0 ? name.slice(i + 1).toLowerCase() : "";
  }

  async function apiRequest(method, bodyObj) {
    const options = {
      method,
      headers: {}
    };

    if (bodyObj) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(bodyObj);
    }

    const response = await fetch(API_URL, options);

    let payload = null;
    try {
      payload = await response.json();
    } catch (err) {
      payload = null;
    }

    if (!response.ok || !payload || payload.success === false) {
      const message = payload && payload.message ? payload.message : "Request failed.";
      throw new Error(message);
    }

    return payload;
  }

  function parseNoteContent(rawContent) {
    if (!rawContent || typeof rawContent !== "string") {
      return { group: "-", sizeBytes: 0 };
    }

    try {
      const obj = JSON.parse(rawContent);
      return {
        group: obj.group ? String(obj.group) : "-",
        sizeBytes: Number(obj.sizeBytes) || 0
      };
    } catch (err) {
      return {
        group: rawContent,
        sizeBytes: 0
      };
    }
  }

  function mapApiNoteToRow(note) {
    const content = parseNoteContent(note.content || "");

    return {
      id: String(note.note_id || ""),
      name: note.title || "Untitled",
      group: content.group,
      sizeBytes: content.sizeBytes,
      date: formatApiDate(note.created_at)
    };
  }

  async function loadNotes() {
    try {
      const payload = await apiRequest("GET");
      notes = Array.isArray(payload.data) ? payload.data.map(mapApiNoteToRow) : [];
      render();
    } catch (err) {
      alert(err.message || "Could not load notes.");
      notes = [];
      render();
    }
  }

  async function loadGroups() {
    try {
      const response = await fetch(GROUP_API_URL);
      const payload = await response.json();

      groups = payload && payload.success && Array.isArray(payload.data)
        ? payload.data
        : [];

      renderGroupOptions();
    } catch (err) {
      groups = [];
      renderGroupOptions();
    }
  }

  function renderGroupOptions() {
    const currentValue = targetGroup.value;

    const options = ['<option value="">Select group</option>'];
    groups.forEach((g) => {
      const name = String(g.group_name || "").trim();
      if (!name) return;
      options.push('<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>');
    });

    targetGroup.innerHTML = options.join("");

    if (currentValue && groups.some(g => String(g.group_name || "").trim() === currentValue)) {
      targetGroup.value = currentValue;
      targetGroup.classList.add("selected");
    } else {
      targetGroup.value = "";
      targetGroup.classList.remove("selected");
    }

    if (groups.length === 0) {
      targetGroup.disabled = true;
      targetGroup.innerHTML = '<option value="">No groups available</option>';
      targetGroup.classList.add("selected");
    } else {
      targetGroup.disabled = false;
    }
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
          <span class="fname" title="${n.name}">${n.name}</span>
        </div>
        <div class="col-group">${n.group}</div>
        <div class="col-size">${formatSize(n.sizeBytes)}</div>
        <div class="col-date">${n.date}</div>
        <div class="col-actions">
          <button class="row-download" data-action="download" data-id="${n.id}" aria-label="Download">
            <i class="fa-solid fa-download"></i>
          </button>
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

  uploadForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (groups.length === 0) { alert("Please create a group first."); return; }
    if (!targetGroup.value) { targetGroup.focus(); return; }
    if (!pendingFile) { fileChooserBtn.focus(); return; }

    const contentJson = JSON.stringify({
      group: targetGroup.value,
      sizeBytes: pendingFile.size,
      fileType: getExt(pendingFile.name)
    });

    try {
      const payload = await apiRequest("POST", {
        title: pendingFile.name,
        content: contentJson
      });

      const created = payload.data || null;
      if (created && created.note_id) {
        notes.unshift(mapApiNoteToRow({
          note_id: created.note_id,
          title: created.title,
          content: created.content,
          created_at: new Date().toISOString().slice(0, 19).replace("T", " ")
        }));
        render();
      } else {
        await loadNotes();
      }

      closeUploadPanel();
    } catch (err) {
      alert(err.message || "Could not upload note.");
    }
  });

  uploadCancel.addEventListener("click", closeUploadPanel);

  //  Download flow //
  tableBody.addEventListener("click", async (e) => {
    const btn = e.target.closest('button[data-action="download"]');
    if (!btn) return;
    const id = String(btn.dataset.id || "");
    const note = notes.find(n => n.id === id);
    if (!note) return;

    try {
      const response = await fetch("../api/notes.php?action=download&note_id=" + encodeURIComponent(id));
      if (!response.ok) throw new Error("Download failed");
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = note.name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err.message || "Could not download file.");
    }
  });

  //  Delete flow // 
  tableBody.addEventListener("click", (e) => {
    const btn = e.target.closest('button[data-action="delete"]');
    if (!btn) return;
    const id = String(btn.dataset.id || "");
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
  deleteConfirm.addEventListener("click", async () => {
    if (pendingDeleteId == null) return;

    try {
      await apiRequest("DELETE", { note_id: pendingDeleteId });
      notes = notes.filter(n => n.id !== pendingDeleteId);
      pendingDeleteId = null;
      deleteModal.hidden = true;
      render();
    } catch (err) {
      alert(err.message || "Could not delete note.");
    }
  });

  deleteModal.addEventListener("click", (e) => {
    if (e.target === deleteModal) cancelDelete();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !deleteModal.hidden) cancelDelete();
  });

  // Init
  loadGroups();
  loadNotes();
})();
