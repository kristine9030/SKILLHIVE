<?php
/**
 * Purpose: Shared notifications API for topbar bell dropdown.
 * Handles: list notifications, unread count, mark one as read, mark all as read.
 */

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('notifications_api_respond')) {
    function notifications_api_respond(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('notifications_api_time_label')) {
    function notifications_api_time_label(?string $datetime): string
    {
        if (!$datetime) {
            return 'Just now';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'Just now';
        }

        $delta = time() - $timestamp;
        if ($delta < 60) {
            return 'Just now';
        }

        if ($delta < 3600) {
            $minutes = max(1, (int)floor($delta / 60));
            return $minutes . ' min ago';
        }

        if ($delta < 86400) {
            $hours = max(1, (int)floor($delta / 3600));
            return $hours . ' hr ago';
        }

        if ($delta < 604800) {
            $days = max(1, (int)floor($delta / 86400));
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        return date('M j, Y g:i A', $timestamp);
    }
}

require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/../../backend/functions/notifications.php';

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$userId = (int)($_SESSION['user_id'] ?? 0);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

if ($role === '' || $userId <= 0) {
    notifications_api_respond(['ok' => false, 'error' => 'Unauthorized'], 401);
}

try {
    if ($action === 'list') {
        $rows = skillhive_notifications_get_latest($pdo, $role, $userId, 12);
        $unreadCount = skillhive_notifications_get_unread_count($pdo, $role, $userId);

        $notifications = array_map(static function (array $row): array {
            return [
                'notification_id' => (int)($row['notification_id'] ?? 0),
                'type' => (string)($row['type'] ?? 'general'),
                'title' => (string)($row['title'] ?? 'Notification'),
                'message' => (string)($row['message'] ?? ''),
                'target_url' => (string)($row['target_url'] ?? ''),
                'is_read' => (int)($row['is_read'] ?? 0) === 1,
                'created_at' => (string)($row['created_at'] ?? ''),
                'time_label' => notifications_api_time_label((string)($row['created_at'] ?? '')),
            ];
        }, $rows);

        notifications_api_respond([
            'ok' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    if ($action === 'unread_count') {
        notifications_api_respond([
            'ok' => true,
            'unread_count' => skillhive_notifications_get_unread_count($pdo, $role, $userId),
        ]);
    }

    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
        if ($notificationId <= 0) {
            notifications_api_respond(['ok' => false, 'error' => 'Invalid notification id'], 400);
        }

        skillhive_notifications_mark_read($pdo, $role, $userId, $notificationId);
        notifications_api_respond([
            'ok' => true,
            'unread_count' => skillhive_notifications_get_unread_count($pdo, $role, $userId),
        ]);
    }

    if ($action === 'mark_all_read') {
        skillhive_notifications_mark_all_read($pdo, $role, $userId);
        notifications_api_respond([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    notifications_api_respond(['ok' => false, 'error' => 'Invalid action'], 400);
} catch (Throwable $e) {
    notifications_api_respond(['ok' => false, 'error' => 'Unable to process notifications right now.'], 500);
}
