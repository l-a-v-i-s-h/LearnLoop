<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'notes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LearnLoop | Shared Notes</title>
    <?php $v = time(); ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../assets/css/notes.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">

    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="notes-header">
                <div>
                    <h1 class="notes-title">My Shared Notes</h1>
                    <p class="notes-subtitle">Track the study materials you have shared</p>
                </div>
                <button type="button" class="upload-btn" id="uploadBtn">
                    + Upload Note
                </button>
            </div>

            <!-- Inline Upload Form (shown on click) -->
            <div class="upload-panel" id="uploadPanel" hidden>
                <div class="upload-panel-label">UPLOAD FORM</div>
                <form id="uploadForm" class="upload-panel-row">
                    <select id="targetGroup" class="inline-field" required>
                        <option value="">Select group</option>
                        <option value="Modern Web Arch">Modern Web Arch</option>
                        <option value="Data Structures">Data Structures</option>
                        <option value="Algorithms">Algorithms</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Database Systems">Database Systems</option>
                    </select>
                    <button type="button" class="inline-field file-chooser" id="fileChooserBtn">
                        <span id="fileChooserLabel">Choose file ...</span>
                    </button>
                    <input type="file" id="noteFileInput" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.png,.jpg,.jpeg" hidden>
                    <button type="submit" class="btn-upload-inline">Upload</button>
                    <button type="button" class="btn-cancel-inline" id="uploadCancel">Cancel</button>
                </form>
            </div>

            <!-- Notes Table (shown when notes exist) -->
            <div class="notes-table-wrapper" id="notesTableWrapper" hidden>
                <div class="notes-table-head">
                    <div class="col-name">DOCUMENT NAME</div>
                    <div class="col-group">TARGET GROUP</div>
                    <div class="col-size">SIZE</div>
                    <div class="col-date">DATE</div>
                    <div class="col-actions"></div>
                </div>
                <div class="notes-table-body" id="notesTableBody"></div>
            </div>

            <!-- Empty State (shown when no notes exist) -->
            <div class="notes-empty" id="notesEmpty">
                <div class="empty-icon">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h2 class="empty-title">No Notes Yet</h2>
                <p class="empty-text">Upload your first note to share with your study groups</p>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal" hidden>
        <div class="modal-card modal-sm">
            <div class="modal-head">
                <h3>Delete Note?</h3>
                <button class="modal-close" id="deleteModalClose" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="confirm-text">Are you sure you want to delete <strong id="deleteFileName">this note</strong>? This action cannot be undone.</p>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="deleteCancel">Cancel</button>
                    <button type="button" class="btn-danger" id="deleteConfirm">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/notes.js?v=<?php echo $v; ?>"></script>
</body>
</html>
