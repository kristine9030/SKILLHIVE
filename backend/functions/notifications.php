<?php
/**
 * Purpose: Shared notification storage and retrieval helpers.
 * Tables/columns used: user_notification(notification_id, recipient_role, recipient_id, type, title, message, target_url, reference_table, reference_id, is_read, created_at, read_at).
 */

if (!function_exists('skillhive_notifications_ensure_table')) {
    function skillhive_notifications_ensure_table(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_notification (
                notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_role VARCHAR(32) NOT NULL,
                recipient_id INT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                target_url VARCHAR(255) DEFAULT NULL,
                reference_table VARCHAR(64) DEFAULT NULL,
                reference_id INT UNSIGNED DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME DEFAULT NULL,
                PRIMARY KEY (notification_id),
                INDEX idx_user_notification_recipient (recipient_role, recipient_id, is_read, created_at),
                INDEX idx_user_notification_reference (reference_table, reference_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ensured = true;
    }
}

if (!function_exists('skillhive_notifications_create')) {
    function skillhive_notifications_create(PDO $pdo, array $payload): bool
    {
        $recipientRole = strtolower(trim((string)($payload['recipient_role'] ?? '')));
        $recipientId = (int)($payload['recipient_id'] ?? 0);
        $type = trim((string)($payload['type'] ?? 'general'));
        $title = trim((string)($payload['title'] ?? 'Notification'));
        $message = trim((string)($payload['message'] ?? ''));
        $targetUrl = trim((string)($payload['target_url'] ?? ''));
        $referenceTable = trim((string)($payload['reference_table'] ?? ''));
        $referenceId = (int)($payload['reference_id'] ?? 0);

        if ($recipientRole === '' || $recipientId <= 0 || $message === '') {
            return false;
        }

        skillhive_notifications_ensure_table($pdo);

        if ($referenceTable !== '' && $referenceId > 0) {
            $existsStmt = $pdo->prepare(
                'SELECT notification_id
                 FROM user_notification
                 WHERE recipient_role = :recipient_role
                   AND recipient_id = :recipient_id
                   AND type = :type
                                     AND COALESCE(reference_table, \'\') = :reference_table
                   AND COALESCE(reference_id, 0) = :reference_id
                 LIMIT 1'
            );
            $existsStmt->execute([
                ':recipient_role' => $recipientRole,
                ':recipient_id' => $recipientId,
                ':type' => $type,
                ':reference_table' => $referenceTable,
                ':reference_id' => $referenceId,
            ]);

            if ((int)$existsStmt->fetchColumn() > 0) {
                return true;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO user_notification (
                recipient_role,
                recipient_id,
                type,
                title,
                message,
                target_url,
                reference_table,
                reference_id,
                is_read,
                created_at
            ) VALUES (
                :recipient_role,
                :recipient_id,
                :type,
                :title,
                :message,
                :target_url,
                :reference_table,
                :reference_id,
                0,
                NOW()
            )'
        );

        return $stmt->execute([
            ':recipient_role' => $recipientRole,
            ':recipient_id' => $recipientId,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':target_url' => $targetUrl !== '' ? $targetUrl : null,
            ':reference_table' => $referenceTable !== '' ? $referenceTable : null,
            ':reference_id' => $referenceId > 0 ? $referenceId : null,
        ]);
    }
}

if (!function_exists('skillhive_notifications_get_unread_count')) {
    function skillhive_notifications_get_unread_count(PDO $pdo, string $role, int $userId): int
    {
        if ($userId <= 0 || trim($role) === '') {
            return 0;
        }

        skillhive_notifications_ensure_table($pdo);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM user_notification
             WHERE recipient_role = :recipient_role
               AND recipient_id = :recipient_id
               AND is_read = 0'
        );
        $stmt->execute([
            ':recipient_role' => strtolower(trim($role)),
            ':recipient_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('skillhive_notifications_get_latest')) {
    function skillhive_notifications_get_latest(PDO $pdo, string $role, int $userId, int $limit = 10): array
    {
        if ($userId <= 0 || trim($role) === '') {
            return [];
        }

        skillhive_notifications_ensure_table($pdo);

        $safeLimit = max(1, min(30, $limit));
        $sql = sprintf(
            'SELECT notification_id, type, title, message, target_url, is_read, created_at
             FROM user_notification
             WHERE recipient_role = :recipient_role
               AND recipient_id = :recipient_id
             ORDER BY created_at DESC, notification_id DESC
             LIMIT %d',
            $safeLimit
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':recipient_role' => strtolower(trim($role)),
            ':recipient_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('skillhive_notifications_mark_read')) {
    function skillhive_notifications_mark_read(PDO $pdo, string $role, int $userId, int $notificationId): bool
    {
        if ($userId <= 0 || trim($role) === '' || $notificationId <= 0) {
            return false;
        }

        skillhive_notifications_ensure_table($pdo);

        $stmt = $pdo->prepare(
            'UPDATE user_notification
             SET is_read = 1,
                 read_at = NOW()
             WHERE notification_id = :notification_id
               AND recipient_role = :recipient_role
               AND recipient_id = :recipient_id'
        );

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':recipient_role' => strtolower(trim($role)),
            ':recipient_id' => $userId,
        ]);
    }
}

if (!function_exists('skillhive_notifications_mark_all_read')) {
    function skillhive_notifications_mark_all_read(PDO $pdo, string $role, int $userId): bool
    {
        if ($userId <= 0 || trim($role) === '') {
            return false;
        }

        skillhive_notifications_ensure_table($pdo);

        $stmt = $pdo->prepare(
            'UPDATE user_notification
             SET is_read = 1,
                 read_at = NOW()
             WHERE recipient_role = :recipient_role
               AND recipient_id = :recipient_id
               AND is_read = 0'
        );

        return $stmt->execute([
            ':recipient_role' => strtolower(trim($role)),
            ':recipient_id' => $userId,
        ]);
    }
}
