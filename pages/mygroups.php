<?php
session_start();
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

        
        <div class="groups-header">
            <h1 class="page-title">My Study Groups</h1>
            <button class="create-btn" id="openCreateModal">
                <i class="fa-solid fa-plus"></i> Create Group
            </button>
        </div>

        
        <div class="empty-state" id="emptyState" style="display:none;">
            <i class="fa-solid fa-users"></i>
            <h2>No Groups Yet</h2>
            <p>Create or join a group to start collaborating.</p>
        </div>

        
        <div class="groups-grid" id="groupsGrid"></div>

    </main>
</div>


<div class="modal-overlay" id="createModal">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Create Group</h2>
            <button class="modal-close" id="closeCreateModal">&times;</button>
        </div>

        <form id="createForm">
            <div class="form-group">
                <label>Group Name</label>
                <input type="text" id="groupName" placeholder="Enter group name" required>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" id="groupSubject" placeholder="Enter subject" required>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fa-solid fa-plus"></i> Create
            </button>
        </form>
    </div>
</div>

<script src="../assets/js/group.js"></script>
</body>
</html>