const groupsGrid = document.getElementById("groupsGrid");
const groupsEmpty = document.getElementById("groupsEmpty");
const form = document.getElementById("createForm");
const nameInput = document.getElementById("groupName");
const subjectInput = document.getElementById("groupSubject");
const toggleBtn = document.getElementById("toggleFormBtn");
const deleteModal = document.getElementById("deleteModal");
const deleteGroupName = document.getElementById("deleteGroupName");
const cancelDeleteBtn = document.getElementById("cancelDelete");
const confirmDeleteBtn = document.getElementById("confirmDelete");
const leaveModal = document.getElementById("leaveModal");
const leaveGroupName = document.getElementById("leaveGroupName");
const cancelLeaveBtn = document.getElementById("cancelLeave");
const confirmLeaveBtn = document.getElementById("confirmLeave");
const apiUrl = "../api/group.php";
const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
const csrfToken = csrfTokenMeta ? (csrfTokenMeta.getAttribute("content") || "") : "";

let groups = [];
let pendingDeleteId = "";
let pendingLeaveId = "";

function toggleForm() {
  if (form.style.display === "none" || form.style.display === "") {
    form.style.display = "flex";
  } else {
    form.style.display = "none";
  }
}

toggleBtn.addEventListener("click", toggleForm);

async function createGroup() {
  const name = nameInput.value.trim();
  const subject = subjectInput.value.trim();

  nameInput.value = name;
  subjectInput.value = subject;

  if (!nameInput.checkValidity()) {
    nameInput.reportValidity();
    return;
  }

  if (!subjectInput.checkValidity()) {
    subjectInput.reportValidity();
    return;
  }

  const res = await fetch(apiUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken
    },
    body: JSON.stringify({ group_name: name, subject: subject })
  });

  const data = await res.json();
  if (!data.success) {
    alert(data.message || "Failed to create group");
    return;
  }

  nameInput.value = "";
  subjectInput.value = "";

  await loadGroups();
  toggleForm();
}

function renderGroups() {
  if (!groups.length) {
    groupsGrid.innerHTML = "";
    groupsGrid.style.display = "none";
    groupsEmpty.hidden = false;
    return;
  }

  groupsGrid.style.display = "grid";
  groupsEmpty.hidden = true;

  groupsGrid.innerHTML = groups.map(g => `
    <div class="group-card">
      <div onclick="openGroup('${esc(g.group_name)}')">
        <div class="group-label">Group Name</div>
        <h3>${esc(g.group_name)}</h3>
        <div class="group-label">Subject</div>
        <p>${esc(g.subject || "No subject")}</p>
      </div>
      <div class="group-actions">
        <button class="action-open-room" onclick="event.stopPropagation();openGroup('${esc(g.group_name)}')">
          Open Room <i class="fa-solid fa-arrow-right"></i>
        </button>
        ${g.role === 'owner' ? `
        <button class="action-delete" title="Delete" onclick="event.stopPropagation();askDeleteGroup('${esc(g.group_id)}','${esc(g.group_name)}')">
          <i class="fa-solid fa-trash"></i>
        </button>
        ` : `
        <!-- member leave button (running exit icon) -->
        <button class="action-leave" title="Leave group" onclick="event.stopPropagation();askLeaveGroup('${esc(g.group_id)}','${esc(g.group_name)}')">
          <i class="fa-solid fa-person-running"></i>
        </button>
        `}
      </div>
    </div>
  `).join("");
}

async function loadGroups() {
  const res = await fetch(apiUrl);
  const data = await res.json();
  groups = data.success && Array.isArray(data.data) ? data.data : [];
  renderGroups();
}

async function deleteGroup(groupId) {
  const res = await fetch(apiUrl, {
    method: "DELETE",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken
    },
    body: JSON.stringify({ group_id: groupId })
  });
  const data = await res.json();
  if (!data.success) {
    alert(data.message || "Failed to delete group");
    return;
  }
  await loadGroups();
}

function askDeleteGroup(groupId, groupName) {
  pendingDeleteId = groupId;
  deleteGroupName.textContent = groupName;
  deleteModal.classList.add("active");
}

// Ask to leave (opens modal)
function askLeaveGroup(groupId, groupName) {
  pendingLeaveId = groupId;
  if (leaveGroupName) leaveGroupName.textContent = groupName;
  if (leaveModal) leaveModal.classList.add("active");
}

function closeLeaveModal() {
  pendingLeaveId = "";
  if (leaveGroupName) leaveGroupName.textContent = "";
  if (leaveModal) leaveModal.classList.remove("active");
}

async function leaveGroup(groupId) {
  const res = await fetch(apiUrl, {
    method: "DELETE",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken
    },
    body: JSON.stringify({ group_id: groupId, action: 'leave' })
  });
  const data = await res.json();
  if (!data.success) {
    alert(data.message || "Failed to leave group");
    return;
  }
  await loadGroups();
}

function closeDeleteModal() {
  pendingDeleteId = "";
  deleteGroupName.textContent = "";
  deleteModal.classList.remove("active");
}

function esc(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function openGroup(name) {
  window.location.href = "chatroom.php?group=" + encodeURIComponent(name);
}

window.toggleForm = toggleForm;
window.createGroup = createGroup;
window.askDeleteGroup = askDeleteGroup;
window.askLeaveGroup = askLeaveGroup;

cancelDeleteBtn.addEventListener("click", closeDeleteModal);

deleteModal.addEventListener("click", (e) => {
  if (e.target === deleteModal) {
    closeDeleteModal();
  }
});

confirmDeleteBtn.addEventListener("click", async () => {
  if (!pendingDeleteId) {
    return;
  }

  await deleteGroup(pendingDeleteId);
  closeDeleteModal();
});

// Leave modal event wiring
if (cancelLeaveBtn) cancelLeaveBtn.addEventListener("click", closeLeaveModal);
if (leaveModal) {
  leaveModal.addEventListener("click", (e) => {
    if (e.target === leaveModal) {
      closeLeaveModal();
    }
  });
}

if (confirmLeaveBtn) confirmLeaveBtn.addEventListener("click", async () => {
  if (!pendingLeaveId) return;
  await leaveGroup(pendingLeaveId);
  closeLeaveModal();
});

loadGroups();