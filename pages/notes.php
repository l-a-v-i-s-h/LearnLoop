<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'notes';
$user = $_SESSION['user'];
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LearnLoop | Shared Notes</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/notes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-layout">

    <?php include '../includes/header.php'; ?>

    <div class="app-container">
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <div class="notes-header">
                <h1 class="page-title">Shared Notes</h1>
                <button class="upload-btn" id="openUploadModal">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Note
                </button>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState" style="display:none;">
                <div class="empty-icon">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h2>No Notes Yet</h2>
                <p>Upload your first note to share with your study groups.</p>
                <button class="upload-btn" onclick="document.getElementById('openUploadModal').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Note
                </button>
            </div>

            <!-- Notes Grid -->
            <div class="notes-grid" id="notesGrid"></div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Upload Note</h2>
                <button class="modal-close" id="closeUploadModal">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo esc($csrfToken); ?>">
                <div class="form-group">
                    <label for="noteTitle">Title</label>
                    <input type="text" id="noteTitle" name="title" placeholder="e.g. Chapter 5 — Data Structures" required>
                </div>
                <div class="form-group">
                    <label for="noteSubject">Subject</label>
                    <input type="text" id="noteSubject" name="subject" placeholder="e.g. Computer Science" required>
                </div>
                <div class="form-group">
                    <label>File</label>
                    <div class="file-drop" id="fileDrop">
                        <i class="fa-solid fa-file-arrow-up"></i>
                        <p>Drag & drop or <span class="file-browse">browse</span></p>
                        <small>PDF, DOC, DOCX, PPT, PPTX, TXT, PNG, JPG — max 10 MB</small>
                        <input type="file" id="noteFile" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.png,.jpg,.jpeg" required>
                    </div>
                    <div class="file-selected" id="fileSelected" style="display:none;">
                        <i class="fa-solid fa-file-check"></i>
                        <span id="fileName"></span>
                        <button type="button" class="file-remove" id="fileRemove">&times;</button>
                    </div>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-card modal-view">
            <div class="modal-header">
                <h2 id="viewTitle"></h2>
                <button class="modal-close" id="closeViewModal">&times;</button>
            </div>
            <div class="view-details">
                <div class="detail-row">
                    <span class="detail-label">Subject</span>
                    <span class="detail-value" id="viewSubject"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Uploaded by</span>
                    <span class="detail-value" id="viewUploader"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">File</span>
                    <span class="detail-value" id="viewFile"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value" id="viewDate"></span>
                </div>
            </div>
            <a class="submit-btn download-link" id="viewDownload" href="#" target="_blank">
                <i class="fa-solid fa-download"></i> Download File
            </a>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-card modal-delete">
            <div class="delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2>Delete Note?</h2>
            <p>This action cannot be undone. The note and its file will be permanently removed.</p>
            <div class="delete-actions">
                <button class="cancel-btn" id="cancelDelete">Cancel</button>
                <button class="confirm-delete-btn" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

<script>
const CSRF = '<?php echo esc($csrfToken); ?>';
const CURRENT_USER = '<?php echo esc($user['user_id']); ?>';

const notesGrid    = document.getElementById('notesGrid');
const emptyState   = document.getElementById('emptyState');
const uploadModal  = document.getElementById('uploadModal');
const viewModal    = document.getElementById('viewModal');
const deleteModal  = document.getElementById('deleteModal');
const uploadForm   = document.getElementById('uploadForm');
const fileInput    = document.getElementById('noteFile');
const fileDrop     = document.getElementById('fileDrop');
const fileSelected = document.getElementById('fileSelected');
const fileNameEl   = document.getElementById('fileName');

let deleteTargetId = null;

// ── File type icon helper ──
function fileIcon(ext) {
    const map = {
        pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word',
        ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint',
        txt: 'fa-file-lines', png: 'fa-file-image', jpg: 'fa-file-image', jpeg: 'fa-file-image'
    };
    return map[ext] || 'fa-file';
}

function fileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// ── Render notes ──
function renderNotes(notes) {
    if (!notes.length) {
        notesGrid.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }
    emptyState.style.display = 'none';
    notesGrid.style.display = 'grid';

    notesGrid.innerHTML = notes.map(function(n) {
        const isOwner = n.uploaded_by === CURRENT_USER;
        return '<div class="note-card" data-id="' + n.note_id + '">' +
            '<div class="note-icon"><i class="fa-solid ' + fileIcon(n.file_ext) + '"></i></div>' +
            '<div class="note-info">' +
                '<h3 class="note-title">' + escHtml(n.title) + '</h3>' +
                '<span class="note-subject">' + escHtml(n.subject) + '</span>' +
                '<div class="note-meta">' +
                    '<span><i class="fa-solid fa-user"></i> ' + escHtml(n.uploader_name) + '</span>' +
                    '<span><i class="fa-solid fa-hard-drive"></i> ' + fileSize(n.file_size) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="note-actions">' +
                '<button class="action-btn view-btn" title="View" onclick="viewNote(\'' + n.note_id + '\')"><i class="fa-solid fa-eye"></i></button>' +
                (isOwner ? '<button class="action-btn delete-btn" title="Delete" onclick="deleteNote(\'' + n.note_id + '\')"><i class="fa-solid fa-trash"></i></button>' : '') +
            '</div>' +
        '</div>';
    }).join('');
}

function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ── Load notes ──
let allNotes = [];
function loadNotes() {
    fetch('../api/notes.php?action=list')
        .then(function(r) { return r.json(); })
        .then(function(data) { allNotes = data; renderNotes(data); });
}

// ── Upload modal ──
document.getElementById('openUploadModal').addEventListener('click', function() {
    uploadModal.classList.add('active');
});
document.getElementById('closeUploadModal').addEventListener('click', function() {
    uploadModal.classList.remove('active');
    uploadForm.reset();
    fileSelected.style.display = 'none';
    fileDrop.style.display = 'flex';
});
uploadModal.addEventListener('click', function(e) {
    if (e.target === uploadModal) document.getElementById('closeUploadModal').click();
});

// ── File picker ──
fileDrop.addEventListener('click', function() { fileInput.click(); });
fileDrop.addEventListener('dragover', function(e) { e.preventDefault(); fileDrop.classList.add('dragover'); });
fileDrop.addEventListener('dragleave', function() { fileDrop.classList.remove('dragover'); });
fileDrop.addEventListener('drop', function(e) {
    e.preventDefault();
    fileDrop.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showSelectedFile(e.dataTransfer.files[0].name);
    }
});
fileInput.addEventListener('change', function() {
    if (fileInput.files.length) showSelectedFile(fileInput.files[0].name);
});
function showSelectedFile(name) {
    fileNameEl.textContent = name;
    fileSelected.style.display = 'flex';
    fileDrop.style.display = 'none';
}
document.getElementById('fileRemove').addEventListener('click', function() {
    fileInput.value = '';
    fileSelected.style.display = 'none';
    fileDrop.style.display = 'flex';
});

// ── Upload submit ──
uploadForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';

    var fd = new FormData(uploadForm);
    fetch('../api/notes.php?action=upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-upload"></i> Upload';
            if (res.ok) {
                document.getElementById('closeUploadModal').click();
                loadNotes();
            } else {
                alert(res.data.error || 'Upload failed.');
            }
        });
});

// ── View note ──
function viewNote(id) {
    var n = allNotes.find(function(x) { return x.note_id === id; });
    if (!n) return;
    document.getElementById('viewTitle').textContent = n.title;
    document.getElementById('viewSubject').textContent = n.subject;
    document.getElementById('viewUploader').textContent = n.uploader_name;
    document.getElementById('viewFile').textContent = n.file_name;
    document.getElementById('viewDate').textContent = n.created_at;
    document.getElementById('viewDownload').href = '../uploads/notes/' + id + '.' + n.file_ext;
    viewModal.classList.add('active');
}
document.getElementById('closeViewModal').addEventListener('click', function() {
    viewModal.classList.remove('active');
});
viewModal.addEventListener('click', function(e) {
    if (e.target === viewModal) viewModal.classList.remove('active');
});

// ── Delete note ──
function deleteNote(id) {
    deleteTargetId = id;
    deleteModal.classList.add('active');
}
document.getElementById('cancelDelete').addEventListener('click', function() {
    deleteModal.classList.remove('active');
    deleteTargetId = null;
});
deleteModal.addEventListener('click', function(e) {
    if (e.target === deleteModal) document.getElementById('cancelDelete').click();
});
document.getElementById('confirmDelete').addEventListener('click', function() {
    if (!deleteTargetId) return;
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('note_id', deleteTargetId);

    fetch('../api/notes.php?action=delete', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            deleteModal.classList.remove('active');
            deleteTargetId = null;
            if (res.success) loadNotes();
            else alert(res.error || 'Delete failed.');
        });
});

// ── Init ──
loadNotes();
</script>
</body>
</html>