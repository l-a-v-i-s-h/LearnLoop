const groupsGrid = document.getElementById("groupsGrid");
const emptyState = document.getElementById("emptyState");

const createModal = document.getElementById("createModal");
const openBtn = document.getElementById("openCreateModal");
const closeBtn = document.getElementById("closeCreateModal");
const form = document.getElementById("createForm");

let groups = [
  { id: 1, name: "Modern Web Arch", subject: "CS", members: 12, active: 4 },
  { id: 2, name: "Data Structures", subject: "CS", members: 8, active: 2 },
  { id: 3, name: "Math Analysis", subject: "Math", members: 12, active: 3 },
];

// Render
function renderGroups() {
  if (!groups.length) {
    groupsGrid.style.display = "none";
    emptyState.style.display = "block";
    return;
  }

  emptyState.style.display = "none";
  groupsGrid.style.display = "grid";

  groupsGrid.innerHTML = groups.map(g => `
    <div class="group-card" onclick="openGroup('${g.name}')">
      <div class="group-title">${g.name}</div>
      <div class="group-meta">${g.members} members · ${g.active} active</div>
      <div class="open-room">Open Room →</div>
    </div>
  `).join("");
}

// Modal
openBtn.onclick = () => createModal.classList.add("active");
closeBtn.onclick = () => createModal.classList.remove("active");

createModal.onclick = (e) => {
  if (e.target === createModal) closeBtn.click();
};

// Create group
form.onsubmit = (e) => {
  e.preventDefault();

  const name = document.getElementById("groupName").value;
  const subject = document.getElementById("groupSubject").value;

  groups.push({
    id: Date.now(),
    name,
    subject,
    members: 1,
    active: 1
  });

  form.reset();
  closeBtn.click();
  renderGroups();
};

// Open group
function openGroup(name) {
  alert("Opening " + name);
}

// Init
renderGroups();