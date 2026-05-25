<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/moderation.php';

if (!isset($_SESSION['admin']['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = (string) ($_SESSION['admin']['full_name'] ?? 'Admin');

// --- APP DATA STATE SNAPSHOT (Matches Target Design Layout Values) ---
try {
    $usersCursor = db()->selectCollection('users')->find([], ['sort' => ['created_at' => -1]]);
} catch (Exception $e) {
    $usersCursor = [];
}

try {
    $reportsCursor = db()->selectCollection('reports')->find([], ['projection' => ['reported_user_id' => 1]]);
} catch (Exception $e) {
    $reportsCursor = [];
}

$reportCounts = [];
foreach ($reportsCursor as $reportDoc) {
    $reportedUserId = (string) ($reportDoc['reported_user_id'] ?? '');
    if ($reportedUserId === '') {
        continue;
    }
    $reportCounts[$reportedUserId] = ($reportCounts[$reportedUserId] ?? 0) + 1;
}

$allStudents = [];
$statusCounts = [
    'active' => 0,
    'pending' => 0,
];

foreach ($usersCursor as $doc) {
    $fullName = trim((string) ($doc['full_name'] ?? 'Unknown user'));
    $isVerified = (bool) ($doc['is_verified'] ?? false);
    $status = $isVerified ? 'active' : 'pending';

    $allStudents[] = [
        'user_id' => (string) ($doc['user_id'] ?? ''),
        'full_name' => $fullName !== '' ? $fullName : 'Unknown user',
        'email' => (string) ($doc['email'] ?? ''),
        'initials' => moderation_initials($fullName),
        'status' => $status,
        'joined_date' => moderation_format_date($doc['created_at'] ?? null),
        'reports' => (string) ($reportCounts[(string) ($doc['user_id'] ?? '')] ?? 0),
    ];

    $statusCounts[$status] += 1;
}

$summarySnapshot = moderation_get_summary();
$summaryCards = [
    ['label' => 'Total Students', 'value' => (string) ($summarySnapshot['users_total'] ?? count($allStudents)), 'icon' => 'fa-solid fa-users', 'class' => 'kpi-blue'],
    ['label' => 'Active', 'value' => (string) $statusCounts['active'], 'icon' => 'fa-solid fa-check', 'class' => 'kpi-green'],
    ['label' => 'Pending', 'value' => (string) $statusCounts['pending'], 'icon' => 'fa-solid fa-hourglass-half', 'class' => 'kpi-orange'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo esc(csrf_token()); ?>">
    <title>LearnLoop | All Students</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/all_students.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-dashboard-page student-registry-target-view">
    
    <div class="admin-shell">
        <header class="admin-header">
            <a href="admin_dashboard.php" class="admin-logo" aria-label="LearnLoop home">
                LearnL<span><i class="fa-solid fa-infinity"></i></span>p
            </a>

            <label class="admin-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" placeholder="Search" aria-label="Global System Search">
            </label>

            <div class="admin-profile">
                <div class="admin-profile-copy">
                    <span class="admin-avatar"><i class="fa-regular fa-user"></i></span>
                    <span><?php echo esc($adminName); ?></span>
                </div>
                <form action="../api/auth.php?action=admin-logout" method="POST" style="margin: 0;">
                    <?php echo csrf_input(); ?>
                    <button class="logout-button" type="submit" aria-label="Log out">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </button>
                </form>
            </div>
        </header>

        <div class="admin-layout">
            <aside class="admin-sidebar">
                <nav class="admin-nav" aria-label="Main system management links">
                    <a class="admin-nav-item" href="admin_dashboard.php">
                        <i class="fa-solid fa-house"></i><span>Dashboard</span>
                    </a>
                    <a class="admin-nav-item is-active" href="all_students.php">
                        <i class="fa-solid fa-user-graduate"></i><span>All Students</span>
                    </a>
                    <a class="admin-nav-item" href="forums.php">
                        <i class="fa-regular fa-comments"></i><span>Academic Forums</span>
                    </a>
                    <a class="admin-nav-item" href="chat_monitor.php">
                        <i class="fa-solid fa-headset"></i><span>Chat Monitor</span>
                    </a>
                    <a class="admin-nav-item" href="banned_users.php">
                        <i class="fa-solid fa-ban"></i><span>Banned Users</span>
                    </a>
                    <a class="admin-nav-item" href="profile.php">
                        <i class="fa-regular fa-user"></i><span>Account</span>
                    </a>
                </nav>
            </aside>

            <main class="admin-main student-registry-main">
                <div class="registry-page-title">
                    <h1>All Students</h1>
                </div>

                <section class="registry-kpi-row" aria-label="Operational high level metrics status cards">
                    <?php foreach ($summaryCards as $card): ?>
                        <article class="registry-kpi-card <?php echo esc($card['class']); ?>">
                            <span class="kpi-icon-badge">
                                <i class="<?php echo esc($card['icon']); ?>"></i>
                            </span>
                            <div class="kpi-data-block">
                                <p class="kpi-label"><?php echo esc($card['label']); ?></p>
                                <strong class="kpi-value"><?php echo esc($card['value']); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="registry-table-container-card" aria-label="Database output matrix grid sheet">
                    <div class="registry-table-toolbar">
                        <label class="registry-search-input-field">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="registryFilterInput" placeholder="Search students by name, email, or ID..." aria-label="Search records dataset input">
                        </label>

                        <div class="registry-tabs-filter-group" aria-label="Dataset state query categorical segment hooks">
                            <button class="filter-tab-btn is-active" type="button">All</button>
                        </div>
                    </div>

                    <div class="registry-data-grid">
                        <div class="registry-grid-header-row">
                            <span>Students</span>
                            <span>ID</span>
                            <span>Email</span>
                            <span>Status</span>
                            <span>Joined</span>
                            <span>Reports</span>
                        </div>

                        <div id="registryDataRowsCollection">
                            <?php foreach ($allStudents as $student): ?>
                            <div class="registry-data-table-row" 
                                 data-user-id="<?php echo esc($student['user_id']); ?>" 
                                 data-user-status="<?php echo esc($student['status']); ?>">
                                
                                <div class="student-profile-identity-cell">
                                    <span class="student-avatar-badge"><?php echo esc($student['initials']); ?></span>
                                    <div class="student-identity-meta">
                                        <strong><?php echo esc($student['full_name']); ?></strong>
                                    </div>
                                </div>
                                
                                <span class="student-text-data-cell id-hash-dim"><?php echo esc($student['user_id']); ?></span>
                                <span class="student-text-data-cell email-cell"><?php echo esc($student['email']); ?></span>
                                
                                <div class="student-text-data-cell">
                                    <span class="status-pill-badge status-pill-<?php echo esc($student['status']); ?>">
                                        <?php echo esc(ucfirst($student['status'])); ?>
                                    </span>
                                </div>
                                
                                <span class="student-text-data-cell light-date-lbl"><?php echo esc($student['joined_date']); ?></span>
                                <span class="student-text-data-cell weight-reports-lbl"><?php echo esc($student['reports']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="report-modal-overlay" id="userModerationModal" hidden data-action="">
        <div class="report-modal-card" role="dialog" aria-modal="true" aria-labelledby="userModerationTitle">
            <div class="report-modal-header">
                <div>
                    <p class="report-modal-kicker" id="userModerationKicker">Administrative Override Protocol</p>
                    <h2 id="userModerationTitle">Execute Account Restriction</h2>
                </div>
                <button type="button" class="report-modal-close" id="userModerationClose" aria-label="Close UI layout">&times;</button>
            </div>

            <div class="report-modal-form">
                <p id="userModerationMessage">Are you sure you want to alter the global data system permissions of this user account profile snapshot reference index?</p>
                <p class="report-modal-target"><strong>Target Student Identifier Token:</strong> <span id="userModerationUserId" style="word-break:break-all;"></span></p>

                <label class="report-field">
                    <span>Administrative Verification Reasoning Audit Log Detail</span>
                    <textarea id="userModerationReason" rows="4" placeholder="Enter standard system operational justification notice details..."></textarea>
                </label>

                <div class="report-modal-actions">
                    <button type="button" class="report-modal-cancel" id="userModerationCancel">Cancel</button>
                    <button type="button" class="report-modal-submit" id="userModerationConfirm">Confirm Pipeline Change</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/all_students.js"></script>
</body>
</html>