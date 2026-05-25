(function () {
  "use strict";

  const API_URL = "../api/notes.php";
  const GROUP_API_URL = "../api/group.php";
  const bodyEl = document.body;
  const currentUserId = bodyEl ? String(bodyEl.getAttribute("data-user-id") || "") : "";
  const PUSHER_KEY = "14db4509a104fa2c4d52";
  const PUSHER_CLUSTER = "ap2";
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfInput = document.querySelector('input[name="_csrf_token"]');
  const csrfToken = csrfTokenMeta
    ? csrfTokenMeta.getAttribute("content") || ""
    : (csrfInput ? csrfInput.value || "" : "");

  // Empty by default so empty state is shown first.
  let notes = [];

  let pendingFiles = [];
  let pendingDeleteId = null;
  let groups = [];
  let isRefreshing = false;

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

  function escapeHtml(value) {
    return String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  }

  async function apiRequest(method, bodyObj) {
    const options = {
      method,
      headers: {}
    };

    if (bodyObj) {
      options.headers["Content-Type"] = "application/json";
      options.headers["X-CSRF-Token"] = csrfToken;
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
      return { groupId: "public", groupName: "Public", visibility: "public", sizeBytes: 0, fileType: "txt" };
    }

    try {
      const obj = JSON.parse(rawContent);
      return {
        groupId: obj.groupId ? String(obj.groupId) : (obj.group_id ? String(obj.group_id) : "public"),
        groupName: obj.groupName ? String(obj.groupName) : (obj.group ? String(obj.group) : "Public"),
        visibility: obj.visibility ? String(obj.visibility) : ((obj.groupId || obj.group_id) ? "private_group" : "public"),
        sizeBytes: Number(obj.sizeBytes) || 0,
        fileType: obj.fileType ? String(obj.fileType) : "txt"
      };
    } catch (err) {
      return {
        groupId: "public",
        groupName: String(rawContent || "Public"),
        visibility: "public",
        sizeBytes: 0,
        fileType: "txt"
      };
    }
  }

  function mapApiNoteToRow(note) {
    const content = parseNoteContent(note.content || "");
    const visibility = String(note.visibility || content.visibility || (String(note.group_id || "") === "public" ? "public" : "private_group"));
    const groupName = String(note.group_name || content.groupName || (visibility === "public" ? "Public" : "Shared group"));
    const ownerId = String(note.user_id || "");
    const currentUserOwns = ownerId && ownerId === currentUserId;
    const fileName = String(note.file_name || note.title || "Untitled");
    const fileSize = Number(note.file_size || content.sizeBytes || 0);

    return {
      id: String(note.note_id || ""),
      name: fileName,
      group: groupName,
      sizeBytes: fileSize,
      date: formatApiDate(note.created_at),
      visibility,
      ownerId,
      ownerName: String(note.owner_name || note.user_name || note.user_full_name || note.owner || ""),
      canDelete: currentUserOwns,
      canDownload: visibility === "public" || currentUserOwns || visibility === "private_group"
    };
  }

  async function loadNotes() {
    try {
      const payload = await apiRequest("GET");
      notes = Array.isArray(payload.data) ? payload.data.map(mapApiNoteToRow) : [];
      render();
    } catch (err) {
      notify('error', err.message || "Could not load notes.");
      notes = [];
      render();
    }
  }

  function refreshNotesSilently() {
    if (isRefreshing) return;
    isRefreshing = true;

    // Simple AJAX with XMLHttpRequest so beginners can follow.
    const xhr = new XMLHttpRequest();
    xhr.open("GET", API_URL, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      isRefreshing = false;

      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const payload = JSON.parse(xhr.responseText || "{}");
          notes = Array.isArray(payload.data) ? payload.data.map(mapApiNoteToRow) : [];
          render();
        } catch (err) {
          // Ignore bad JSON.
        }
      }
    };
    xhr.send();
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

    const options = ['<option value="">Share publicly</option>'];
    groups.forEach((g) => {
      const name = String(g.group_name || "").trim();
      const groupId = String(g.group_id || "").trim();
      if (!name || !groupId) return;
      options.push('<option value="' + escapeHtml(groupId) + '">' + escapeHtml(name) + '</option>');
    });

    targetGroup.innerHTML = options.join("");

    if (currentValue && groups.some(g => String(g.group_id || "").trim() === currentValue)) {
      targetGroup.value = currentValue;
      targetGroup.classList.add("selected");
    } else {
      targetGroup.value = "";
      targetGroup.classList.remove("selected");
    }

    targetGroup.disabled = false;
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
          <button class="row-download" data-action="download" data-id="${n.id}" aria-label="Download">
            <i class="fa-solid fa-download"></i>
          </button>
          ${n.canDelete ? `
          <button class="row-delete" data-action="delete" data-id="${n.id}" aria-label="Delete">
            <i class="fa-solid fa-trash"></i>
          </button>
          ` : ''}
        </div>
      </div>
    `).join("");
  }

  // Upload panel toggle //
  function resetUploadForm() {
    pendingFiles = [];
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
    const list = e.target.files ? Array.from(e.target.files) : [];
    if (!list || list.length === 0) return;
    if (list.length > 5) { notify('error','You can upload up to 5 files.'); fileInput.value = ''; return; }

    // simple client-side checks
    for (const f of list) {
      if (f.size <= 0) { notify('error','One of the selected files is empty.'); fileInput.value = ''; return; }
      if (f.size > 25 * 1024 * 1024) { notify('error','Each file must be 25 MB or less.'); fileInput.value = ''; return; }
      const ext = getExt(f.name);
      if (!['pdf','doc','docx','ppt','pptx','txt','png','jpg','jpeg','zip'].includes(ext)) { notify('error','Unsupported file type: .' + ext); fileInput.value = ''; return; }
    }

    pendingFiles = list;
    fileChooserLabel.textContent = pendingFiles.map(f => f.name).join(', ');
    fileChooserBtn.classList.add("selected");
  });

  targetGroup.addEventListener("change", () => {
    if (targetGroup.value) targetGroup.classList.add("selected");
    else targetGroup.classList.remove("selected");
  });

  uploadForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!pendingFiles || pendingFiles.length === 0) { fileChooserBtn.focus(); return; }

    try {
      const selectedGroup = groups.find((g) => String(g.group_id || "") === String(targetGroup.value || ""));
      const visibility = targetGroup.value ? "private_group" : "public";
      const groupId = targetGroup.value ? String(targetGroup.value) : "public";
      const groupName = targetGroup.value ? String(selectedGroup && selectedGroup.group_name ? selectedGroup.group_name : targetGroup.options[targetGroup.selectedIndex]?.text || "") : "Public";

      for (const f of pendingFiles) {
        const formData = new FormData();
        formData.append("title", f.name);
        formData.append("group_id", groupId);
        formData.append("group_name", groupName);
        formData.append("visibility", visibility);
        formData.append("file", f, f.name);
        formData.append("_csrf_token", csrfToken);

        const response = await fetch(API_URL, {
          method: "POST",
          headers: {
            "X-CSRF-Token": csrfToken
          },
          body: formData
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Could not upload note.");
        }

        const created = payload.data || null;
        if (created && created.note_id) {
          notes.unshift(mapApiNoteToRow({
            note_id: created.note_id,
            title: created.title,
            content: created.content,
            group_id: created.group_id,
            group_name: created.group_name,
            visibility: created.visibility,
            user_id: created.user_id,
            file_name: created.file_name,
            file_size: created.file_size,
            file_type: created.file_type,
            created_at: new Date().toISOString().slice(0, 19).replace("T", " ")
          }));
        }
      }

      render();
      closeUploadPanel();
    } catch (err) {
      notify('error', err.message || "Could not upload note.");
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
      const response = await fetch("../api/notes.php?action=download&note_id=" + encodeURIComponent(id), {
        headers: {
          "X-CSRF-Token": csrfToken
        }
      });
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
      notify('error', err.message || "Could not download file.");
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
      notify('error', err.message || "Could not delete note.");
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

  // Simple fallback refresh to avoid manual reloads.
  setInterval(refreshNotesSilently, 6000);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      refreshNotesSilently();
    }
  });

  // Real-time updates (beginner friendly)
  if (window.Pusher && PUSHER_KEY) {
    const pusher = new window.Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER });
    const channel = pusher.subscribe("notes-channel");

    channel.bind("note-created", (data) => {
      if (!data) return;

      const incomingId = String(data.note_id || "");
      if (!incomingId) return;

      const visibility = String(data.visibility || "private_group");
      const isOwn = String(data.user_id || "") === currentUserId;
      if (visibility !== "public" && !isOwn) return;

      const exists = notes.some(n => n.id === incomingId);
      if (exists) return;

      notes.unshift(mapApiNoteToRow({
        note_id: data.note_id,
        title: data.title,
        content: data.content,
        group_id: data.group_id,
        group_name: data.group_name,
        visibility: data.visibility,
        user_id: data.user_id,
        created_at: data.created_at
      }));
      render();
    });

    channel.bind("note-deleted", (data) => {
      if (!data) return;

      const deleteId = String(data.note_id || "");
      if (!deleteId) return;

      const visibility = String(data.visibility || "private_group");
      const isOwn = String(data.user_id || "") === currentUserId;
      if (visibility !== "public" && !isOwn) return;

      notes = notes.filter(n => n.id !== deleteId);
      render();
    });
    const moderationChannel = pusher.subscribe('moderation-channel');
    moderationChannel.bind('force-logout', (data) => {
      try {
        if (!data || String(data.user_id || '') !== currentUserId) return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const action = 'logout.php';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_csrf_token';
        input.value = csrf;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      } catch (err) {
        console.error('force-logout handler failed', err);
        window.location.href = '/';
      }
    });
  }
})();
