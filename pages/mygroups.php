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
        <h1 class="page-title">My Study Groups</h1>
        <button class="create-btn" id="toggleFormBtn">
            + Create Group
        </button>
    </div>

    <!-- FORM -->
    <div class="create-form" id="createForm">
        <input type="text" id="groupName" placeholder="Group name...">
        <input type="text" id="groupSubject" placeholder="Subject...">

        <button onclick="createGroup()">Create</button>
        <button onclick="toggleForm()">Cancel</button>
    </div>

    <!-- GROUPS -->
    <div class="groups-grid" id="groupsGrid"></div>

</main>
</div>

<!-- ✅ IMPORTANT: JS MUST BE LAST -->
<script src="../assets/js/group.js"></script>

</body>
</html>