<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'groups';
$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LearnLoop | My Groups</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/groups.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="dashboard-layout">

<?php include '../includes/header.php'; ?>

<div class="app-container">
<?php include '../includes/navbar.php'; ?>

<main class="main-content">

    <!-- Header -->
    <div class="groups-header">
        <div>
            <h1 class="page-title">My Study Groups</h1>
            <p class="page-subtitle">Connect and collaborate with your peers for better learning</p>
        </div>
        <button class="create-btn" id="toggleFormBtn">
            + Create Group
        </button>
    </div>

    <!-- FORM -->
    <div class="create-form" id="createForm">
        <input type="text" id="groupName" placeholder="Group name..." required>
        <input type="text" id="groupSubject" placeholder="Subject..." required>

        <button onclick="createGroup()">Create</button>
        <button onclick="toggleForm()">Cancel</button>
    </div>

    <!-- GROUPS -->
    <div class="groups-grid" id="groupsGrid"></div>

    <!-- Empty State -->
    <div class="groups-empty" id="groupsEmpty" hidden>
        <div class="groups-empty-icon">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <h2>No Groups Yet</h2>
        <p>Create your first group to start learning with your study circle</p>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-card modal-delete">
            <div class="delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2>Delete Group?</h2>
            <p>This action cannot be undone. The group <strong id="deleteGroupName"></strong> will be removed.</p>
            <div class="delete-actions">
                <button class="cancel-btn" id="cancelDelete">Cancel</button>
                <button class="confirm-delete-btn" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

</main>
</div>

<!-- ✅ IMPORTANT: JS MUST BE LAST -->
<script src="../assets/js/group.js"></script>

</body>
</html>