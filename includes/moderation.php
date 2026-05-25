<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/file_store.php';

function moderation_reports_collection(): MongoDB\Collection
{
    return db()->selectCollection('reports');
}

function moderation_users_collection(): MongoDB\Collection
{
    return db()->selectCollection('users');
}

function moderation_notifications_collection(): MongoDB\Collection
{
    return db()->selectCollection('notifications');
}

function moderation_group_members_collection(): MongoDB\Collection
{
    return db()->selectCollection('group_members');
}

function moderation_chat_messages_collection(): MongoDB\Collection
{
    return db()->selectCollection('chat_messages');
}

function moderation_build_pusher(): ?Pusher\Pusher
{
    try {
        return new Pusher\Pusher(
            '14db4509a104fa2c4d52',
            '22eeaeff5739ab77e4cc',
            '2150170',
            [
                'cluster' => 'ap2',
                'useTLS' => true,
            ]
        );
    } catch (Exception $e) {
        return null;
    }
}

function moderation_trigger_event(string $eventName, array $payload): void
{
    static $pusher = null;

    if ($pusher === null) {
        $pusher = moderation_build_pusher();
    }

    if (!$pusher) {
        return;
    }

    try {
        $pusher->trigger('moderation-channel', $eventName, $payload);
    } catch (Exception $e) {
        // Ignore realtime failures so moderation still works.
    }
}

function moderation_normalize_status(mixed $status): string
{
    $value = strtolower(trim((string) $status));

    if ($value === 'warn') {
        return 'warned';
    }

    if ($value === 'suspend') {
        return 'suspended';
    }

    if ($value === 'delete') {
        return 'deleted';
    }

    if (!in_array($value, ['active', 'warned', 'suspended', 'banned', 'deleted'], true)) {
        return 'active';
    }

    return $value;
}

function moderation_format_date(mixed $value): string
{
    if ($value instanceof MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->format('Y-m-d H:i:s');
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }

    return '';
}

function moderation_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= strtoupper(substr($part, 0, 1));
        if (strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : 'U';
}

function moderation_priority_from_reason(string $reason, string $details = ''): string
{
    $text = strtolower($reason . ' ' . $details);

    if (preg_match('/threat|abuse|harass|hate|sexual|violence|scam|cheat/', $text)) {
        return 'high';
    }

    if (preg_match('/spam|spammy|repeat|off-topic|bully/', $text)) {
        return 'medium';
    }

    return 'low';
}

function moderation_normalize_priority(mixed $priority): string
{
    $value = strtolower(trim((string) $priority));

    if (in_array($value, ['low', 'medium', 'high'], true)) {
        return $value;
    }

    return 'medium';
}

function moderation_user_snapshot(string $userId): array
{
    if ($userId === '') {
        return [
            'user_id' => '',
            'full_name' => '',
            'username' => '',
            'email' => '',
            'initials' => 'U',
            'moderation_status' => 'active',
            'moderation_reason' => '',
            'moderation_updated_at' => '',
            'warning_count' => 0,
        ];
    }

    try {
        $user = moderation_users_collection()->findOne(['user_id' => $userId]);
    } catch (Exception $e) {
        $user = null;
    }

    if (!$user) {
        return [
            'user_id' => $userId,
            'full_name' => 'Deleted user',
            'username' => '',
            'email' => '',
            'initials' => 'X',
            'moderation_status' => 'deleted',
            'moderation_reason' => 'Account removed by admin.',
            'moderation_updated_at' => '',
            'warning_count' => 0,
            'moderation_admin_id' => '',
            'moderation_admin_name' => '',
        ];
    }

    $fullName = trim((string) ($user['full_name'] ?? 'Unknown user'));

    return [
        'user_id' => (string) ($user['user_id'] ?? $userId),
        'full_name' => $fullName !== '' ? $fullName : 'Unknown user',
        'username' => (string) ($user['username'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'initials' => moderation_initials($fullName),
        'moderation_status' => moderation_normalize_status($user['moderation_status'] ?? 'active'),
        'moderation_reason' => (string) ($user['moderation_reason'] ?? ''),
        'moderation_updated_at' => moderation_format_date($user['moderation_updated_at'] ?? null),
        'warning_count' => (int) ($user['warning_count'] ?? 0),
        'moderation_admin_id' => (string) ($user['moderation_admin_id'] ?? ''),
        'moderation_admin_name' => (string) ($user['moderation_admin_name'] ?? ''),
    ];
}

function moderation_report_snapshot(mixed $doc): array
{
    $reporterId = (string) ($doc['reporter_id'] ?? '');
    $reportedUserId = (string) ($doc['reported_user_id'] ?? '');
    $reporter = moderation_user_snapshot($reporterId);
    $reportedUser = moderation_user_snapshot($reportedUserId);
    $reportedUserName = $reportedUser['full_name'];
    if ($reportedUserName === 'Deleted user' || $reportedUserName === 'Unknown user') {
        $storedReportedUserName = trim((string) ($doc['reported_user_name'] ?? ''));
        if ($storedReportedUserName !== '') {
            $reportedUserName = $storedReportedUserName;
        }
    }

    $reporterName = $reporter['full_name'];
    if ($reporterName === 'Deleted user' || $reporterName === 'Unknown user') {
        $storedReporterName = trim((string) ($doc['reporter_name'] ?? ''));
        if ($storedReporterName !== '') {
            $reporterName = $storedReporterName;
        }
    }

    return [
        'report_id' => (string) ($doc['report_id'] ?? ''),
        'report_type' => (string) ($doc['report_type'] ?? 'chat_message'),
        'message_id' => (string) ($doc['message_id'] ?? ''),
        'group_id' => (string) ($doc['group_id'] ?? ''),
        'group_name' => (string) ($doc['group_name'] ?? ''),
        'category' => (string) ($doc['category'] ?? 'Chat Report'),
        'priority' => (string) ($doc['priority'] ?? 'low'),
        'status' => (string) ($doc['status'] ?? 'pending'),
        'reason' => (string) ($doc['reason'] ?? ''),
        'details' => (string) ($doc['details'] ?? ''),
        'admin_note' => (string) ($doc['admin_note'] ?? ''),
        'reporter_id' => $reporterId,
        'reported_user_id' => $reportedUserId,
        'reporter_name' => $reporterName,
        'reporter_initials' => $reporter['initials'],
        'reported_user_name' => $reportedUserName,
        'reported_user_initials' => $reportedUser['initials'],
        'reported_user_status' => $reportedUser['moderation_status'],
        'reported_user_reason' => $reportedUser['moderation_reason'],
        'reporter_course' => (string) ($doc['reporter_course'] ?? ''),
        'reported_user_course' => (string) ($doc['reported_user_course'] ?? ''),
        'message_excerpt' => (string) ($doc['message_excerpt'] ?? ''),
        'evidence_file_id' => (string) ($doc['evidence_file_id'] ?? ''),
        'evidence_file_name' => (string) ($doc['evidence_file_name'] ?? ''),
        'evidence_file_path' => (string) ($doc['evidence_file_path'] ?? ''),
        'evidence_file_type' => (string) ($doc['evidence_file_type'] ?? ''),
        'admin_id' => (string) ($doc['admin_id'] ?? ''),
        'admin_name' => (string) ($doc['admin_name'] ?? ''),
        'created_at' => moderation_format_date($doc['created_at'] ?? null),
        'updated_at' => moderation_format_date($doc['updated_at'] ?? null),
        'reviewed_at' => moderation_format_date($doc['reviewed_at'] ?? null),
    ];
}

function moderation_get_report_by_id(string $reportId): ?array
{
    if ($reportId === '') {
        return null;
    }

    try {
        $doc = moderation_reports_collection()->findOne(['report_id' => $reportId]);
    } catch (Exception $e) {
        return null;
    }

    if (!$doc) {
        return null;
    }

    return moderation_report_snapshot($doc);
}

function moderation_get_reports(array $filters = []): array
{
    try {
        $cursor = moderation_reports_collection()->find([], ['sort' => ['created_at' => -1], 'limit' => 500]);
    } catch (Exception $e) {
        return [];
    }

    $reports = [];
    foreach ($cursor as $doc) {
        $report = moderation_report_snapshot($doc);

        if (!empty($filters['status']) && $report['status'] !== $filters['status']) {
            continue;
        }

        if (!empty($filters['reporter_id']) && $report['reporter_id'] !== $filters['reporter_id']) {
            continue;
        }

        if (!empty($filters['reported_user_id']) && $report['reported_user_id'] !== $filters['reported_user_id']) {
            continue;
        }

        if (!empty($filters['search'])) {
            $haystack = strtolower(implode(' ', [
                $report['report_id'],
                $report['reporter_name'],
                $report['reported_user_name'],
                $report['group_name'],
                $report['reason'],
                $report['details'],
                $report['message_excerpt'],
            ]));

            if (strpos($haystack, strtolower($filters['search'])) === false) {
                continue;
            }
        }

        $reports[] = $report;
    }

    return $reports;
}

function moderation_get_banned_users(): array
{
    try {
        $cursor = moderation_users_collection()->find([], ['sort' => ['moderation_updated_at' => -1]]);
    } catch (Exception $e) {
        return [];
    }

    $users = [];
    foreach ($cursor as $doc) {
        $status = moderation_normalize_status($doc['moderation_status'] ?? 'active');
        if (!in_array($status, ['warned', 'suspended', 'banned'], true)) {
            continue;
        }

        $fullName = trim((string) ($doc['full_name'] ?? 'Unknown user'));
        $username = (string) ($doc['username'] ?? '');
        $reason = (string) ($doc['moderation_reason'] ?? '');

        $users[] = [
            'user_id' => (string) ($doc['user_id'] ?? ''),
            'initials' => moderation_initials($fullName),
            'full_name' => $fullName !== '' ? $fullName : 'Unknown user',
            'username' => $username,
            'email' => (string) ($doc['email'] ?? ''),
            'moderation_status' => $status,
            'warning_count' => (int) ($doc['warning_count'] ?? 0),
            'reason' => $reason,
            'updated_at' => moderation_format_date($doc['moderation_updated_at'] ?? null),
            'admin_name' => (string) ($doc['moderation_admin_name'] ?? ''),
        ];
    }
    return $users;
}

function moderation_send_notification(string $recipientId, string $message, string $type, string $senderId = '', string $senderName = 'Admin', string $groupId = '', string $groupName = ''): bool
{
    if ($recipientId === '' || $message === '' || $type === '') {
        return false;
    }

    try {
        $recipient = moderation_user_snapshot($recipientId);
        moderation_notifications_collection()->insertOne([
            'notification_id' => bin2hex(random_bytes(8)),
            'type' => $type,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_email' => '',
            'recipient_id' => $recipientId,
            'recipient_email' => $recipient['email'] ?? '',
            'group_id' => $groupId,
            'group_name' => $groupName,
            'message' => $message,
            'status' => 'pending',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

function moderation_remove_user_from_groups(string $userId): int
{
    if ($userId === '') {
        return 0;
    }

    try {
        $result = moderation_group_members_collection()->deleteMany(['user_id' => $userId]);
        return (int) $result->getDeletedCount();
    } catch (Exception $e) {
        return 0;
    }
}

function moderation_remove_user_from_group(string $userId, string $groupId): int
{
    $userId = trim($userId);
    $groupId = trim($groupId);

    if ($userId === '' || $groupId === '') {
        return 0;
    }

    try {
        $result = moderation_group_members_collection()->deleteMany([
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);

        return (int) $result->getDeletedCount();
    } catch (Exception $e) {
        return 0;
    }
}

function moderation_remove_user_messages(string $userId): int
{
    if ($userId === '') {
        return 0;
    }

    try {
        $result = moderation_chat_messages_collection()->deleteMany(['user_id' => $userId]);
        return (int) $result->getDeletedCount();
    } catch (Exception $e) {
        return 0;
    }
}

function moderation_delete_user_account(string $userId, string $adminId = '', string $adminName = ''): array
{
    $userId = trim($userId);
    if ($userId === '') {
        return [false, 'user_id is required.', null];
    }

    $user = moderation_users_collection()->findOne(['user_id' => $userId]);
    if (!$user) {
        return [false, 'User not found.', null];
    }

    $removedGroups = moderation_remove_user_from_groups($userId);
    $removedMessages = moderation_remove_user_messages($userId);

    try {
        moderation_users_collection()->deleteOne(['user_id' => $userId]);
    } catch (Exception $e) {
        return [false, 'Failed to delete user account.', null];
    }

    moderation_trigger_event('user-deleted', [
        'user_id' => $userId,
        'full_name' => (string) ($user['full_name'] ?? 'User'),
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'removed_groups' => $removedGroups,
        'removed_messages' => $removedMessages,
        'deleted_at' => moderation_format_date(new MongoDB\BSON\UTCDateTime()),
    ]);

    // notify user's clients to force a logout (if connected)
    moderation_trigger_event('force-logout', [
        'user_id' => $userId,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'reason' => 'banned',
        'deleted_at' => moderation_format_date(new MongoDB\BSON\UTCDateTime()),
    ]);

    return [true, 'User account deleted successfully.', [
        'user_id' => $userId,
        'full_name' => (string) ($user['full_name'] ?? 'User'),
        'removed_groups' => $removedGroups,
        'removed_messages' => $removedMessages,
    ]];
}

function moderation_delete_report(string $reportId, string $adminId = '', string $adminName = '', string $reason = ''): array
{
    if ($reportId === '') {
        return [false, 'Report not found.', null];
    }

    $report = moderation_get_report_by_id($reportId);
    if (!$report) {
        return [false, 'Report not found.', null];
    }

    $reporterId = (string) ($report['reporter_id'] ?? '');
    $message = 'Your report has been rejected.';
    if (trim($reason) !== '') {
        $message .= ' Reason: ' . trim($reason);
    }

    moderation_send_notification(
        $reporterId,
        $message,
        'report_rejected',
        $adminId,
        $adminName ?: 'Admin',
        (string) ($report['group_id'] ?? ''),
        (string) ($report['group_name'] ?? '')
    );

    $evidenceFileId = (string) ($report['evidence_file_id'] ?? '');
    if ($evidenceFileId !== '') {
        learnloop_delete_stored_file($evidenceFileId);
    }

    $evidencePath = (string) ($report['evidence_file_path'] ?? '');
    if ($evidencePath !== '' && str_starts_with($evidencePath, 'uploads/reports/')) {
        $fullPath = __DIR__ . '/../' . $evidencePath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    try {
        moderation_reports_collection()->deleteOne(['report_id' => $reportId]);
    } catch (Exception $e) {
        return [false, 'Failed to delete report.', null];
    }

    moderation_trigger_event('report-deleted', [
        'report_id' => $reportId,
        'reported_user_id' => $report['reported_user_id'] ?? '',
        'reported_user_name' => $report['reported_user_name'] ?? '',
        'deleted_at' => moderation_format_date(new MongoDB\BSON\UTCDateTime()),
    ]);

    return [true, 'Report deleted successfully.', $report];
}

function moderation_get_summary(): array
{
    $reports = moderation_get_reports();
    $users = moderation_get_banned_users();

    $pending = 0;
    $underReview = 0;
    $resolved = 0;
    $dismissed = 0;

    foreach ($reports as $report) {
        if ($report['status'] === 'pending') {
            $pending++;
        } elseif ($report['status'] === 'under_review') {
            $underReview++;
        } elseif ($report['status'] === 'resolved') {
            $resolved++;
        } elseif ($report['status'] === 'dismissed') {
            $dismissed++;
        }
    }

    $warned = 0;
    $suspended = 0;
    $banned = 0;
    foreach ($users as $user) {
        if ($user['moderation_status'] === 'warned') {
            $warned++;
        } elseif ($user['moderation_status'] === 'suspended') {
            $suspended++;
        } elseif ($user['moderation_status'] === 'banned') {
            $banned++;
        }
    }

    $totalUsers = 0;
    try {
        $totalUsers = moderation_users_collection()->countDocuments([]);
    } catch (Exception $e) {
        $totalUsers = 0;
    }

    $active = max(0, $totalUsers - $warned - $suspended - $banned);

    return [
        'reports_total' => count($reports),
        'reports_pending' => $pending,
        'reports_under_review' => $underReview,
        'reports_resolved' => $resolved,
        'reports_dismissed' => $dismissed,
        'users_total' => $totalUsers,
        'users_active' => $active,
        'users_warned' => $warned,
        'users_suspended' => $suspended,
        'users_banned' => $banned,
    ];
}

function moderation_get_dashboard_snapshot(): array
{
    $reports = moderation_get_reports();
    $bannedUsers = moderation_get_banned_users();
    $summary = moderation_get_summary();

    // Build a unified recent activities list combining recent resolved reports and recently moderated users
    $recentActivities = moderation_get_recent_activities();

    // Exclude resolved and dismissed reports from the live Report Queue
    $reportsQueue = array_filter($reports, function ($r) {
        return !in_array(($r['status'] ?? ''), ['resolved', 'dismissed'], true);
    });

    return [
        'summary' => $summary,
        'reports' => array_slice(array_values($reportsQueue), 0, 5),
        'recent_activities' => array_slice($recentActivities, 0, 5),
    ];
}

function moderation_get_recent_activities(): array
{
    $activities = [];

    // Recent resolved reports
    try {
        $cursor = moderation_reports_collection()->find(['status' => 'resolved'], ['sort' => ['updated_at' => -1], 'limit' => 10]);
    } catch (Exception $e) {
        $cursor = [];
    }

    foreach ($cursor as $doc) {
        $report = moderation_report_snapshot($doc);
        $ts = 0;
        if (isset($doc['updated_at']) && $doc['updated_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $ts = $doc['updated_at']->toDateTime()->getTimestamp();
        } elseif (isset($doc['created_at']) && $doc['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $ts = $doc['created_at']->toDateTime()->getTimestamp();
        }
        $activities[] = [
            'type' => 'report',
            'id' => (string) ($doc['report_id'] ?? ''),
            'title' => (string) ($report['reporter_name'] ?? 'Report'),
            'subtitle' => 'Resolved report: ' . ($report['reported_user_name'] ?? ''),
            'meta' => ($report['priority'] ?? ''),
            'ts' => $ts,
            'raw' => $report,
        ];
    }

    // Recent moderated users (warned/suspended/banned)
    try {
        $usersCursor = moderation_users_collection()->find(['moderation_status' => ['$in' => ['warned', 'suspended', 'banned']]], ['sort' => ['moderation_updated_at' => -1], 'limit' => 10]);
    } catch (Exception $e) {
        $usersCursor = [];
    }

    foreach ($usersCursor as $doc) {
        $fullName = trim((string) ($doc['full_name'] ?? 'Unknown user'));
        $status = moderation_normalize_status($doc['moderation_status'] ?? 'active');
        $ts = 0;
        if (isset($doc['moderation_updated_at']) && $doc['moderation_updated_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $ts = $doc['moderation_updated_at']->toDateTime()->getTimestamp();
        }
        $activities[] = [
            'type' => 'user',
            'id' => (string) ($doc['user_id'] ?? ''),
            'title' => $fullName !== '' ? $fullName : 'Unknown user',
            'subtitle' => ucfirst($status),
            'meta' => $status,
            'ts' => $ts,
            'raw' => [
                'user_id' => (string) ($doc['user_id'] ?? ''),
                'initials' => moderation_initials($fullName),
                'full_name' => $fullName,
                'moderation_status' => $status,
                'updated_at' => moderation_format_date($doc['moderation_updated_at'] ?? null),
            ],
        ];
    }

    // Sort by timestamp desc
    usort($activities, function ($a, $b) {
        return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
    });

    return $activities;
}

function moderation_get_user_state(string $userId): array
{
    return moderation_user_snapshot($userId);
}

function moderation_set_user_status(string $userId, string $status, string $reason = '', string $adminId = '', string $adminName = ''): bool
{
    $userId = trim($userId);
    if ($userId === '') {
        return false;
    }

    $status = moderation_normalize_status($status);
    $warningCount = $status === 'warned' ? 1 : 0;
    $now = new MongoDB\BSON\UTCDateTime();

    try {
        $result = moderation_users_collection()->updateOne(
            ['user_id' => $userId],
            ['$set' => [
                'moderation_status' => $status,
                'moderation_reason' => $reason,
                'moderation_updated_at' => $now,
                'moderation_admin_id' => $adminId,
                'moderation_admin_name' => $adminName,
                'warning_count' => $warningCount,
            ]]
        );
    } catch (Exception $e) {
        return false;
    }

    if ($result->getMatchedCount() === 0) {
        return false;
    }

    moderation_trigger_event('user-moderation-updated', [
        'user_id' => $userId,
        'status' => $status,
        'reason' => $reason,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'updated_at' => moderation_format_date($now),
    ]);

    return true;
}

function moderation_create_report(array $payload, array $reporter): array
{
    $messageId = trim((string) ($payload['message_id'] ?? ''));
    $groupId = trim((string) ($payload['group_id'] ?? ''));
    $groupName = trim((string) ($payload['group_name'] ?? ''));
    $reportedUserId = trim((string) ($payload['reported_user_id'] ?? ''));
    $reportedUserName = trim((string) ($payload['reported_user_name'] ?? ''));
    $reason = trim((string) ($payload['reason'] ?? ''));
    $details = trim((string) ($payload['details'] ?? ''));
    $messageExcerpt = trim((string) ($payload['message_excerpt'] ?? ''));
    $evidenceFileId = trim((string) ($payload['evidence_file_id'] ?? ''));
    $evidenceFileName = trim((string) ($payload['evidence_file_name'] ?? ''));
    $evidenceFilePath = trim((string) ($payload['evidence_file_path'] ?? ''));
    $evidenceFileType = trim((string) ($payload['evidence_file_type'] ?? ''));
    $priority = moderation_normalize_priority($payload['priority'] ?? 'medium');

    if ($groupName === '' || $reportedUserId === '' || $reason === '') {
        return [false, 'group_name, reported_user_id, and reason are required.', null];
    }

    $reportId = bin2hex(random_bytes(8));
    $now = new MongoDB\BSON\UTCDateTime();

    if ($priority === '') {
        $priority = moderation_priority_from_reason($reason, $details . ' ' . $messageExcerpt);
    }

    try {
        moderation_reports_collection()->insertOne([
            'report_id' => $reportId,
            'report_type' => 'chat_message',
            'message_id' => $messageId,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'category' => $payload['category'] ?? 'Chat Report',
            'priority' => $priority,
            'status' => 'pending',
            'reason' => $reason,
            'details' => $details,
            'message_excerpt' => $messageExcerpt,
            'reporter_id' => $reporter['user_id'] ?? '',
            'reporter_name' => $reporter['full_name'] ?? 'User',
            'reporter_course' => $reporter['course'] ?? '',
            'reported_user_id' => $reportedUserId,
            'reported_user_name' => $reportedUserName,
            'reported_user_course' => $payload['reported_user_course'] ?? '',
            'evidence_file_id' => $evidenceFileId,
            'evidence_file_name' => $evidenceFileName,
            'evidence_file_path' => $evidenceFilePath,
            'evidence_file_type' => $evidenceFileType,
            'admin_note' => '',
            'admin_id' => '',
            'admin_name' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
    } catch (Exception $e) {
        return [false, 'Failed to create report.', null];
    }

    $report = moderation_get_report_by_id($reportId);

    moderation_trigger_event('report-created', [
        'report_id' => $reportId,
        'status' => 'pending',
        'priority' => $priority,
        'group_name' => $groupName,
        'reported_user_id' => $reportedUserId,
        'reported_user_name' => $reportedUserName,
        'created_at' => moderation_format_date($now),
    ]);

    return [true, 'Report submitted successfully.', $report];
}

function moderation_review_report(string $reportId, string $status, string $adminId, string $adminName, string $adminNote = '', string $moderationAction = '', string $reason = ''): array
{
    $status = trim(strtolower($status));
    if (!in_array($status, ['pending', 'under_review', 'resolved', 'dismissed'], true)) {
        return [false, 'Invalid report status.', null];
    }

    $moderationAction = trim(strtolower($moderationAction));
    $allowedActions = ['', 'warn', 'suspend', 'ban', 'unban'];
    if (!in_array($moderationAction, $allowedActions, true)) {
        return [false, 'Invalid moderation action.', null];
    }

    if (in_array($moderationAction, ['warn', 'suspend'], true) && trim($reason) === '') {
        return [false, 'A reason is required for this moderation action.', null];
    }

    $report = moderation_get_report_by_id($reportId);
    if (!$report) {
        return [false, 'Report not found.', null];
    }

    $now = new MongoDB\BSON\UTCDateTime();

    try {
        moderation_reports_collection()->updateOne(
            ['report_id' => $reportId],
            ['$set' => [
                'status' => $status,
                'admin_note' => $adminNote,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'updated_at' => $now,
                'reviewed_at' => $now,
                'moderation_action' => $moderationAction,
                'moderated_by' => $adminName,
                'moderated_at' => $now,
            ]]
        );
    } catch (Exception $e) {
        return [false, 'Failed to update report.', null];
    }

    if ($moderationAction !== '') {
        $targetReason = $reason !== '' ? $reason : ($adminNote !== '' ? $adminNote : $report['reason']);
        $reportedUserId = (string) ($report['reported_user_id'] ?? '');

        if ($moderationAction === 'warn') {
            if (!moderation_set_user_status($reportedUserId, 'warned', $targetReason, $adminId, $adminName)) {
                return [false, 'Report updated, but warning the user failed.', null];
            }
            moderation_send_notification(
                $reportedUserId,
                'Your account has been warned. Reason: ' . $targetReason,
                'user_warned',
                $adminId,
                $adminName,
                (string) ($report['group_id'] ?? ''),
                (string) ($report['group_name'] ?? '')
            );
        } elseif ($moderationAction === 'suspend') {
            if (!moderation_set_user_status($reportedUserId, 'suspended', $targetReason, $adminId, $adminName)) {
                return [false, 'Report updated, but suspending the user failed.', null];
            }

            moderation_send_notification(
                $reportedUserId,
                'Your account has been suspended. Reason: ' . $targetReason,
                'user_suspended',
                $adminId,
                $adminName,
                (string) ($report['group_id'] ?? ''),
                (string) ($report['group_name'] ?? '')
            );
        } elseif ($moderationAction === 'ban') {
            if (!moderation_set_user_status($reportedUserId, 'banned', $targetReason, $adminId, $adminName)) {
                return [false, 'Report updated, but banning the user failed.', null];
            }

            $groupId = (string) ($report['group_id'] ?? '');
            if ($groupId !== '') {
                moderation_remove_user_from_group($reportedUserId, $groupId);
            }

            moderation_send_notification(
                $reportedUserId,
                'Your account has been banned. Reason: ' . $targetReason,
                'user_banned',
                $adminId,
                $adminName,
                $groupId,
                (string) ($report['group_name'] ?? '')
            );
        } elseif ($moderationAction === 'unban') {
            if (!moderation_set_user_status($reportedUserId, 'active', $targetReason, $adminId, $adminName)) {
                return [false, 'Report updated, but unbanning the user failed.', null];
            }
        }
    }

    $updated = moderation_get_report_by_id($reportId);

    moderation_trigger_event('report-updated', [
        'report_id' => $reportId,
        'status' => $status,
        'admin_note' => $adminNote,
        'moderation_action' => $moderationAction,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'updated_at' => moderation_format_date($now),
    ]);

    return [true, 'Report updated successfully.', $updated];
}

function moderation_can_send_message(string $userId): array
{
    $state = moderation_get_user_state($userId);
    $status = moderation_normalize_status($state['moderation_status'] ?? 'active');

    if (in_array($status, ['banned', 'suspended', 'deleted'], true)) {
        return [false, 'Your account is blocked and cannot send messages.', $state];
    }

    return [true, '', $state];
}

function moderation_apply_user_action(string $userId, string $status, string $reason, string $adminId, string $adminName): array
{
    $status = moderation_normalize_status($status);
    $allowed = in_array($status, ['active', 'warned', 'suspended', 'banned'], true);

    if (!$allowed) {
        return [false, 'Invalid moderation status.', null];
    }

    if ($status === 'banned') {
        if (!moderation_set_user_status($userId, $status, $reason, $adminId, $adminName)) {
            return [false, 'Failed to update user moderation status.', null];
        }

        moderation_send_notification(
            $userId,
            'Your account has been banned. Reason: ' . ($reason !== '' ? $reason : 'Admin decision'),
            'user_banned',
            $adminId,
            $adminName
        );

        return [true, 'User moderation updated successfully.', moderation_get_user_state($userId)];
    }

    if ($status === 'suspended') {
        if (!moderation_set_user_status($userId, $status, $reason, $adminId, $adminName)) {
            return [false, 'Failed to update user moderation status.', null];
        }
        moderation_send_notification(
            $userId,
            'Your account has been suspended. Reason: ' . ($reason !== '' ? $reason : 'Admin decision'),
            'user_suspended',
            $adminId,
            $adminName
        );

        return [true, 'User moderation updated successfully.', moderation_get_user_state($userId)];
    }

    if (!moderation_set_user_status($userId, $status, $reason, $adminId, $adminName)) {
        return [false, 'Failed to update user moderation status.', null];
    }

    return [true, 'User moderation updated successfully.', moderation_get_user_state($userId)];
}
