const groupsGrid = document.getElementById("groupsGrid");
const form = document.getElementById("createForm");
const toggleBtn = document.getElementById("toggleFormBtn");

let groups = [
  { name: "Modern Web Arch", members: 12, active: 4 },
  { name: "Data Structures", members: 8, active: 2 },
  { name: "Math Analysis", members: 12, active: 3 }
];

function toggleForm() {
  if (form.style.display === "none" || form.style.display === "") {
    form.style.display = "flex";
  } else {
    form.style.display = "none";
  }
}

toggleBtn.addEventListener("click", toggleForm);

function createGroup() {
  const name = document.getElementById("groupName").value;
  const subject = document.getElementById("groupSubject").value;

  if (!name || !subject) {
    alert("Fill all fields");
    return;
  }

  groups.push({
    name,
    members: 1,
    active: 1
  });

  document.getElementById("groupName").value = "";
  document.getElementById("groupSubject").value = "";

  renderGroups();
  toggleForm();
}

function renderGroups() {
  groupsGrid.innerHTML = groups.map(g => `
    <div class="group-card" onclick="openGroup('${g.name}')">
      <h3>${g.name}</h3>
      <p>${g.members} members • ${g.active} active</p>
      <span>Open Room →</span>
    </div>
  `).join("");
}

function openGroup(name) {
  window.location.href = "chatroom.php?group=" + encodeURIComponent(name);
}

renderGroups();