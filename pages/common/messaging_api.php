<?php
/**
 * SKILLHIVE DIRECT MESSAGING API
 * ================================
 * Production-ready messaging system for students, employers, and advisers
 * 
 * Features:
 * - One-to-one direct messaging
 * - Conversation history
 * - Read/unread status tracking
 * - Online presence tracking
 * - Contact management
 * - XSS protection via prepared statements
 * - Rate limiting ready
 * 
 * Endpoints:
 * - list_conversations: Get all active conversations
 * - get_conversation: Fetch conversation with another user
 * - get_contacts: Get all available contacts to message
 * - send_message: Send a new message
 * - mark_as_read: Mark message as read
 * - update_presence: Update user's online status
 * - get_unread_count: Get total unread message count
 */

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('messaging_api_respond')) {
    function messaging_api_respond(array $payload, int $statusCode = 200): void
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

if (!function_exists('messaging_format_time')) {
    function messaging_format_time(?string $datetime): string
    {
        if (!$datetime) {
            return date('M j, g:i A');
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return date('M j, g:i A');
        }

        // Always show absolute time in format: "Mar 29, 5:27 PM"
        $today = date('Y-m-d');
        $msgDate = date('Y-m-d', $timestamp);

        if ($msgDate === $today) {
            // Same day: show only time
            return date('g:i A', $timestamp);
        }

        // Different day: show date and time
        return date('M j, g:i A', $timestamp);
    }
}

if (!function_exists('messaging_get_user_name')) {
    function messaging_get_user_name($pdo, string $role, int $userId): ?string
    {
        $role = strtolower(trim($role));
        $userId = (int)$userId;

        if ($userId <= 0) return null;

        try {
            if ($role === 'student') {
                $stmt = $pdo->prepare(
                    "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM student WHERE student_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
            } elseif ($role === 'employer') {
                $stmt = $pdo->prepare(
                    "SELECT company_name as name FROM employer WHERE employer_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
            } elseif ($role === 'adviser') {
                $row = null;
                try {
                    $stmt = $pdo->prepare(
                        "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM internship_adviser WHERE adviser_id = ? LIMIT 1"
                    );
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch();
                } catch (Throwable $e) {
                    $row = null;
                }

                if (!$row) {
                    $stmt = $pdo->prepare(
                        "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM admin WHERE admin_id = ? LIMIT 1"
                    );
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch();
                }

                $name = $row ? trim((string)($row['name'] ?? '')) : null;
                return $name && strlen($name) > 1 ? $name : null;
            } elseif ($role === 'admin') {
                $stmt = $pdo->prepare(
                    "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM admin WHERE admin_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
            } else {
                return null;
            }

            $row = $stmt->fetch();
            $name = $row ? trim((string)($row['name'] ?? '')) : null;
            return $name && strlen($name) > 1 ? $name : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('messaging_get_user_profile_summary')) {
    function messaging_get_user_profile_summary($pdo, string $role, int $userId): array
    {
        $role = strtolower(trim($role));
        $userId = (int)$userId;

        $summary = [
            'headline' => ucfirst($role),
            'email' => '',
            'profile_path' => '',
            'profile_picture' => '',
        ];

        if ($userId <= 0) {
            return $summary;
        }

        try {
            if ($role === 'student') {
                $stmt = $pdo->prepare(
                    "SELECT email, program, year_level, profile_picture FROM student WHERE student_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch() ?: [];

                $program = trim((string)($row['program'] ?? ''));
                $yearLevel = trim((string)($row['year_level'] ?? ''));
                $summary['headline'] = trim($yearLevel . ' • ' . $program, ' •') ?: 'Student';
                $summary['email'] = (string)($row['email'] ?? '');
                $summary['profile_path'] = '/SkillHive/layout.php?page=student/profile';
                
                $profilePic = trim((string)($row['profile_picture'] ?? ''));
                if ($profilePic !== '') {
                    $summary['profile_picture'] = '/SkillHive/assets/backend/uploads/profile/' . rawurlencode($profilePic);
                }
            } elseif ($role === 'employer') {
                $stmt = $pdo->prepare(
                    "SELECT email, industry, company_logo FROM employer WHERE employer_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch() ?: [];

                $summary['headline'] = trim((string)($row['industry'] ?? '')) ?: 'Employer';
                $summary['email'] = (string)($row['email'] ?? '');
                
                $logo = trim((string)($row['company_logo'] ?? ''));
                if ($logo !== '' && strpos($logo, 'http') !== 0) {
                    $summary['profile_picture'] = '/SkillHive/assets/backend/uploads/company/' . rawurlencode($logo);
                } elseif ($logo !== '' && strpos($logo, 'http') === 0) {
                    $summary['profile_picture'] = $logo;
                }
            } elseif ($role === 'adviser') {
                $summary['headline'] = 'Academic Adviser';

                $row = [];
                try {
                    $stmt = $pdo->prepare(
                        "SELECT email, department, profile_picture FROM internship_adviser WHERE adviser_id = ? LIMIT 1"
                    );
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch() ?: [];
                } catch (Throwable $e) {
                    $row = [];
                }

                if (!$row) {
                    $stmt = $pdo->prepare(
                        "SELECT email FROM admin WHERE admin_id = ? LIMIT 1"
                    );
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch() ?: [];
                }

                $department = trim((string)($row['department'] ?? ''));
                if ($department !== '') {
                    $summary['headline'] = $department;
                }
                $summary['email'] = (string)($row['email'] ?? '');
                
                $profilePic = trim((string)($row['profile_picture'] ?? ''));
                if ($profilePic !== '') {
                    $summary['profile_picture'] = '/SkillHive/assets/backend/uploads/profile/' . rawurlencode($profilePic);
                }
            } elseif ($role === 'admin') {
                $stmt = $pdo->prepare(
                    "SELECT email FROM admin WHERE admin_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch() ?: [];

                $summary['headline'] = 'System Administrator';
                $summary['email'] = (string)($row['email'] ?? '');
            }
        } catch (Throwable $e) {
            return $summary;
        }

        return $summary;
    }
}

if (!function_exists('messaging_validate_role')) {
    function messaging_validate_role(string $role): bool
    {
        return in_array(strtolower($role), ['student', 'employer', 'adviser', 'admin'], true);
    }
}

if (!function_exists('messaging_sanitize_message')) {
    function messaging_sanitize_message(string $message): string
    {
        $message = trim($message);
        if (strlen($message) > 5000) {
            $message = substr($message, 0, 5000);
        }
        return $message;
    }
}

if (!function_exists('messaging_has_admin_role_level_column')) {
    function messaging_has_admin_role_level_column($pdo): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'admin'
                   AND COLUMN_NAME = 'role_level'
                 LIMIT 1"
            );
            $stmt->execute();
            $hasColumn = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $hasColumn = false;
        }

        return $hasColumn;
    }
}

if (!function_exists('messaging_has_internship_adviser_table')) {
    function messaging_has_internship_adviser_table($pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'internship_adviser'
                 LIMIT 1"
            );
            $stmt->execute();
            $exists = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('messaging_role_exists')) {
    function messaging_role_exists($pdo, string $role, int $userId): bool
    {
        static $cache = [];

        $role = strtolower(trim($role));
        $userId = (int)$userId;
        if (!messaging_validate_role($role) || $userId <= 0) {
            return false;
        }

        $cacheKey = $role . '_' . $userId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            if ($role === 'student') {
                $stmt = $pdo->prepare('SELECT 1 FROM student WHERE student_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $cache[$cacheKey] = (bool)$stmt->fetchColumn();
                return $cache[$cacheKey];
            }

            if ($role === 'employer') {
                $stmt = $pdo->prepare('SELECT 1 FROM employer WHERE employer_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $cache[$cacheKey] = (bool)$stmt->fetchColumn();
                return $cache[$cacheKey];
            }

            if ($role === 'admin') {
                $stmt = $pdo->prepare('SELECT 1 FROM admin WHERE admin_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $cache[$cacheKey] = (bool)$stmt->fetchColumn();
                return $cache[$cacheKey];
            }

            if ($role === 'adviser') {
                if (messaging_has_internship_adviser_table($pdo)) {
                    $stmt = $pdo->prepare('SELECT 1 FROM internship_adviser WHERE adviser_id = ? LIMIT 1');
                    $stmt->execute([$userId]);
                    if ((bool)$stmt->fetchColumn()) {
                        $cache[$cacheKey] = true;
                        return true;
                    }
                }

                $stmt = $pdo->prepare('SELECT 1 FROM admin WHERE admin_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $cache[$cacheKey] = (bool)$stmt->fetchColumn();
                return $cache[$cacheKey];
            }
        } catch (Throwable $e) {
            $cache[$cacheKey] = false;
            return false;
        }

        $cache[$cacheKey] = false;
        return false;
    }
}

if (!function_exists('messaging_has_student_employer_relationship')) {
    function messaging_has_student_employer_relationship($pdo, int $studentId, int $employerId): bool
    {
        static $cache = [];

        $studentId = (int)$studentId;
        $employerId = (int)$employerId;
        if ($studentId <= 0 || $employerId <= 0) {
            return false;
        }

        $key = $studentId . '_' . $employerId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                 WHERE a.student_id = :student_id
                   AND i.employer_id = :employer_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':student_id' => $studentId,
                ':employer_id' => $employerId,
            ]);
            $cache[$key] = (bool)$stmt->fetchColumn();
            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('messaging_has_student_adviser_relationship')) {
    function messaging_has_student_adviser_relationship($pdo, int $studentId, int $adviserId): bool
    {
        static $cache = [];
        static $studentHasValidMappedAdviser = [];

        $studentId = (int)$studentId;
        $adviserId = (int)$adviserId;
        if ($studentId <= 0 || $adviserId <= 0) {
            return false;
        }

        $key = $studentId . '_' . $adviserId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM adviser_assignment aa
                 WHERE aa.student_id = :student_id
                   AND aa.adviser_id = :adviser_id
                   AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                 LIMIT 1'
            );
            $stmt->execute([
                ':student_id' => $studentId,
                ':adviser_id' => $adviserId,
            ]);

            if ((bool)$stmt->fetchColumn()) {
                $cache[$key] = true;
                return true;
            }

            if (!array_key_exists($studentId, $studentHasValidMappedAdviser)) {
                $mappedStmt = $pdo->prepare(
                    'SELECT 1
                     FROM adviser_assignment aa
                                         LEFT JOIN admin ad ON ad.admin_id = aa.adviser_id
                                         LEFT JOIN internship_adviser ia ON ia.adviser_id = aa.adviser_id
                     WHERE aa.student_id = :student_id
                       AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                                             AND (ad.admin_id IS NOT NULL OR ia.adviser_id IS NOT NULL)
                     LIMIT 1'
                );
                $mappedStmt->execute([':student_id' => $studentId]);
                $studentHasValidMappedAdviser[$studentId] = (bool)$mappedStmt->fetchColumn();
            }

            if ($studentHasValidMappedAdviser[$studentId] === false && messaging_role_exists($pdo, 'adviser', $adviserId)) {
                $cache[$key] = true;
                return true;
            }

            $cache[$key] = false;
            return false;
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('messaging_has_employer_adviser_relationship')) {
    function messaging_has_employer_adviser_relationship($pdo, int $employerId, int $adviserId): bool
    {
        static $cache = [];

        $employerId = (int)$employerId;
        $adviserId = (int)$adviserId;
        if ($employerId <= 0 || $adviserId <= 0) {
            return false;
        }

        $key = $employerId . '_' . $adviserId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM internship i
                 INNER JOIN application a ON a.internship_id = i.internship_id
                 INNER JOIN adviser_assignment aa ON aa.student_id = a.student_id
                 WHERE i.employer_id = :employer_id
                   AND aa.adviser_id = :adviser_id
                   AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                 LIMIT 1'
            );
            $stmt->execute([
                ':employer_id' => $employerId,
                ':adviser_id' => $adviserId,
            ]);
            $cache[$key] = (bool)$stmt->fetchColumn();
            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('messaging_can_message')) {
    function messaging_can_message($pdo, string $fromRole, int $fromId, string $toRole, int $toId): bool
    {
        $fromRole = strtolower(trim($fromRole));
        $toRole = strtolower(trim($toRole));
        $fromId = (int)$fromId;
        $toId = (int)$toId;

        if (!messaging_validate_role($fromRole) || !messaging_validate_role($toRole) || $fromId <= 0 || $toId <= 0) {
            return false;
        }

        if ($fromRole === $toRole) {
            return false;
        }

        $pair = $fromRole . '|' . $toRole;
        $allowedPairs = [
            'student|employer',
            'employer|student',
            'student|adviser',
            'adviser|student',
            'employer|adviser',
            'adviser|employer',
            'adviser|admin',
            'admin|adviser',
            'employer|admin',
            'admin|employer',
        ];

        if (!in_array($pair, $allowedPairs, true)) {
            return false;
        }

        if (!messaging_role_exists($pdo, $fromRole, $fromId) || !messaging_role_exists($pdo, $toRole, $toId)) {
            return false;
        }

        if ($pair === 'student|employer') {
            return messaging_has_student_employer_relationship($pdo, $fromId, $toId);
        }

        if ($pair === 'employer|student') {
            return messaging_has_student_employer_relationship($pdo, $toId, $fromId);
        }

        if ($pair === 'student|adviser') {
            return messaging_has_student_adviser_relationship($pdo, $fromId, $toId);
        }

        if ($pair === 'adviser|student') {
            return messaging_has_student_adviser_relationship($pdo, $toId, $fromId);
        }

        if ($pair === 'employer|adviser') {
            return messaging_has_employer_adviser_relationship($pdo, $fromId, $toId);
        }

        if ($pair === 'adviser|employer') {
            return messaging_has_employer_adviser_relationship($pdo, $toId, $fromId);
        }

        if (in_array($pair, ['adviser|admin', 'admin|adviser', 'employer|admin', 'admin|employer'], true)) {
            return true;
        }

        return false;
    }
}

require_once __DIR__ . '/../../backend/db_connect.php';

// Validate session
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$userId = (int)($_SESSION['user_id'] ?? 0);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list_conversations'));

if (!messaging_validate_role($role) || $userId <= 0) {
    messaging_api_respond(['ok' => false, 'error' => 'Unauthorized'], 401);
}

try {
    // ==================== LIST CONVERSATIONS ====================
    if ($action === 'list_conversations') {
        // Get direct message conversations
        $stmt = $pdo->prepare(
            "SELECT 
                CASE 
                    WHEN sender_id = ? AND sender_role = ? THEN receiver_id
                    ELSE sender_id
                END as other_user_id,
                CASE 
                    WHEN sender_id = ? AND sender_role = ? THEN receiver_role
                    ELSE sender_role
                END as other_user_role,
                MAX(message_id) as last_message_id,
                MAX(created_at) as last_message_at,
                COUNT(CASE WHEN sender_id != ? AND sender_role != ? AND is_read = 0 THEN 1 END) as unread_count
             FROM direct_message
             WHERE (sender_id = ? AND sender_role = ?) OR (receiver_id = ? AND receiver_role = ?)
             GROUP BY other_user_id, other_user_role
             ORDER BY last_message_at DESC
             LIMIT 100"
        );
        $stmt->execute([$userId, $role, $userId, $role, $userId, $role, $userId, $role, $userId, $role]);
        $directConversations = $stmt->fetchAll();

        // Get group chat conversations
        $gcStmt = $pdo->prepare(
            "SELECT g.group_chat_id, g.group_name, g.created_at as last_msg_at
             FROM group_chat g
             INNER JOIN group_chat_members m ON g.group_chat_id = m.group_chat_id
             WHERE m.member_id = ? AND m.member_role = ?
             ORDER BY g.created_at DESC"
        );
        $gcStmt->execute([$userId, $role]);
        $groupChats = $gcStmt->fetchAll();

        $result = [];

        // Process direct conversations
        foreach ($directConversations as $conv) {
            $other_id = (int)($conv['other_user_id'] ?? 0);
            $other_role = (string)($conv['other_user_role'] ?? '');
            $last_msg_at = (string)($conv['last_message_at'] ?? '');
            $unread = (int)($conv['unread_count'] ?? 0);

            if ($other_id <= 0 || !messaging_validate_role($other_role)) continue;
            if (!messaging_can_message($pdo, $role, $userId, $other_role, $other_id)) continue;

            $name = messaging_get_user_name($pdo, $other_role, $other_id);
            if (!$name) continue;

            // Get last message
            $stmt2 = $pdo->prepare(
                "SELECT message_text, is_read FROM direct_message WHERE message_id = ? LIMIT 1"
            );
            $stmt2->execute([(int)($conv['last_message_id'] ?? 0)]);
            $lastMsg = $stmt2->fetch() ?: [];

            // Get profile picture
            $profileSummary = messaging_get_user_profile_summary($pdo, $other_role, $other_id);

            $result[] = [
                'type' => 'direct',
                'conversation_id' => $other_id . '_' . $other_role,
                'other_user_id' => $other_id,
                'other_user_role' => $other_role,
                'other_user_name' => $name,
                'other_user_profile_picture' => (string)($profileSummary['profile_picture'] ?? ''),
                'last_message' => (string)($lastMsg['message_text'] ?? ''),
                'last_message_at' => $last_msg_at,
                'last_message_time' => messaging_format_time($last_msg_at),
                'unread_count' => $unread,
            ];
        }

        // Process group chats
        foreach ($groupChats as $gc) {
            $result[] = [
                'type' => 'group',
                'conversation_id' => 'gc_' . $gc['group_chat_id'],
                'group_chat_id' => (int)$gc['group_chat_id'],
                'group_name' => (string)$gc['group_name'],
                'last_message' => '',
                'last_message_time' => messaging_format_time((string)$gc['last_msg_at']),
                'unread_count' => 0,
            ];
        }

        // Sort by last activity
        usort($result, function($a, $b) {
            $timeA = strtotime($a['last_message_time'] ?? '0');
            $timeB = strtotime($b['last_message_time'] ?? '0');
            return $timeB - $timeA;
        });

        messaging_api_respond([
            'ok' => true,
            'conversations' => $result,
            'total' => count($result),
        ]);
    }

    // ==================== GET UNREAD COUNT ====================
    if ($action === 'get_unread_count') {
        $stmt = $pdo->prepare(
            "SELECT sender_id, sender_role, COUNT(*) as unread_count
             FROM direct_message
             WHERE receiver_id = ? AND receiver_role = ? AND is_read = 0
             GROUP BY sender_id, sender_role"
        );
        $stmt->execute([$userId, $role]);
        $rows = $stmt->fetchAll() ?: [];

        $unread = 0;
        foreach ($rows as $row) {
            $senderId = (int)($row['sender_id'] ?? 0);
            $senderRole = strtolower(trim((string)($row['sender_role'] ?? '')));
            if (!messaging_validate_role($senderRole) || $senderId <= 0) {
                continue;
            }
            if (!messaging_can_message($pdo, $role, $userId, $senderRole, $senderId)) {
                continue;
            }
            $unread += (int)($row['unread_count'] ?? 0);
        }

        messaging_api_respond(['ok' => true, 'unread_count' => $unread]);
    }

    // ==================== GET CONTACTS ====================
    if ($action === 'get_contacts') {
        $contacts = [];

        if ($role === 'student') {
            // Get employers from approved applications
            try {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT e.employer_id as user_id, e.company_name as name, 'employer' as user_role
                     FROM employer e
                     INNER JOIN internship i ON e.employer_id = i.employer_id
                     INNER JOIN application a ON i.internship_id = a.internship_id
                     WHERE a.student_id = ? AND a.status = 'Accepted'
                     ORDER BY e.company_name
                     LIMIT 100"
                );
                $stmt->execute([$userId]);
                $contacts = array_merge($contacts, $stmt->fetchAll());
            } catch (Throwable $e) {
                // Continue if query fails
            }

            // Get assigned advisers
            try {
                $stmt2 = $pdo->prepare(
                                        "SELECT DISTINCT aa.adviser_id as user_id,
                                                        COALESCE(
                                                                NULLIF(TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, ''))), ''),
                                                                NULLIF(TRIM(CONCAT(COALESCE(ia.first_name, ''), ' ', COALESCE(ia.last_name, ''))), ''),
                                                                CONCAT('Adviser #', aa.adviser_id)
                                                        ) as name,
                            'adviser' as user_role
                                         FROM adviser_assignment aa
                                         LEFT JOIN admin ad ON ad.admin_id = aa.adviser_id
                                         LEFT JOIN internship_adviser ia ON ia.adviser_id = aa.adviser_id
                                         WHERE aa.student_id = ?
                                             AND COALESCE(NULLIF(TRIM(aa.status), ''), 'Active') = 'Active'
                                         ORDER BY name
                     LIMIT 100"
                );
                $stmt2->execute([$userId]);
                $contacts = array_merge($contacts, $stmt2->fetchAll());
            } catch (Throwable $e) {
                // Continue if query fails
            }

            // Fallback: show all employers and admins if no mapped contacts exist yet.
            if (count($contacts) === 0) {
                try {
                    $fallbackEmployers = $pdo->query(
                        "SELECT employer_id as user_id, company_name as name, 'employer' as user_role
                         FROM employer
                         ORDER BY company_name
                         LIMIT 100"
                    )->fetchAll();
                    $contacts = array_merge($contacts, $fallbackEmployers);
                } catch (Throwable $e) {
                    // Ignore fallback errors.
                }

                try {
                    $fallbackAdvisers = $pdo->query(
                        "SELECT admin_id as user_id,
                                CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                                'adviser' as user_role
                         FROM admin
                         ORDER BY first_name, last_name
                         LIMIT 100"
                    )->fetchAll();
                    $contacts = array_merge($contacts, $fallbackAdvisers);
                } catch (Throwable $e) {
                    // Ignore fallback errors.
                }

                if (messaging_has_internship_adviser_table($pdo)) {
                    try {
                        $fallbackInternshipAdvisers = $pdo->query(
                            "SELECT adviser_id as user_id,
                                    CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                                    'adviser' as user_role
                             FROM internship_adviser
                             ORDER BY first_name, last_name
                             LIMIT 100"
                        )->fetchAll();
                        $contacts = array_merge($contacts, $fallbackInternshipAdvisers);
                    } catch (Throwable $e) {
                        // Ignore fallback errors.
                    }
                }
            }
        } elseif ($role === 'employer') {
            // Employers can message students they have applications from
            try {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT s.student_id as user_id, 
                            CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) as name, 
                            'student' as user_role
                     FROM student s
                     INNER JOIN application a ON s.student_id = a.student_id
                     INNER JOIN internship i ON a.internship_id = i.internship_id
                     WHERE i.employer_id = ?
                     ORDER BY s.first_name, s.last_name
                     LIMIT 100"
                );
                $stmt->execute([$userId]);
                $contacts = array_merge($contacts, $stmt->fetchAll());
            } catch (Throwable $e) {
                // Continue if query fails
            }
        }

        // Global pool: include all users from all roles (excluding self) so user can always start a chat.
        try {
            $allStudentsStmt = $pdo->prepare(
                "SELECT student_id as user_id,
                        CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                        'student' as user_role
                 FROM student
                 WHERE NOT (? = 'student' AND student_id = ?)
                 ORDER BY first_name, last_name
                 LIMIT 100"
            );
            $allStudentsStmt->execute([$role, $userId]);
            $contacts = array_merge($contacts, $allStudentsStmt->fetchAll());
        } catch (Throwable $e) {
            // Continue if query fails.
        }

        try {
            $allEmployersStmt = $pdo->prepare(
                "SELECT employer_id as user_id,
                        company_name as name,
                        'employer' as user_role
                 FROM employer
                 WHERE NOT (? = 'employer' AND employer_id = ?)
                 ORDER BY company_name
                 LIMIT 100"
            );
            $allEmployersStmt->execute([$role, $userId]);
            $contacts = array_merge($contacts, $allEmployersStmt->fetchAll());
        } catch (Throwable $e) {
            // Continue if query fails.
        }

        try {
            if (messaging_has_admin_role_level_column($pdo)) {
                $allAdvisersStmt = $pdo->prepare(
                    "SELECT admin_id as user_id,
                            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                            CASE
                                WHEN ? = 'student' THEN 'adviser'
                                WHEN role_level >= 2 THEN 'admin'
                                ELSE 'adviser'
                            END as user_role
                     FROM admin
                     WHERE NOT ((? = 'adviser' OR ? = 'admin') AND admin_id = ?)
                     ORDER BY first_name, last_name
                     LIMIT 100"
                );
                $allAdvisersStmt->execute([$role, $role, $role, $userId]);
            } else {
                $allAdvisersStmt = $pdo->prepare(
                    "SELECT admin_id as user_id,
                            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                            CASE
                                WHEN ? = 'student' THEN 'adviser'
                                ELSE 'admin'
                            END as user_role
                     FROM admin
                     WHERE NOT ((? = 'adviser' OR ? = 'admin') AND admin_id = ?)
                     ORDER BY first_name, last_name
                     LIMIT 100"
                );
                $allAdvisersStmt->execute([$role, $role, $role, $userId]);
            }

            $contacts = array_merge($contacts, $allAdvisersStmt->fetchAll());
        } catch (Throwable $e) {
            // Continue if query fails.
        }

        if (messaging_has_internship_adviser_table($pdo)) {
            try {
                $internshipAdvisersStmt = $pdo->prepare(
                    "SELECT adviser_id as user_id,
                            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                            'adviser' as user_role
                     FROM internship_adviser
                     WHERE NOT (? = 'adviser' AND adviser_id = ?)
                     ORDER BY first_name, last_name
                     LIMIT 100"
                );
                $internshipAdvisersStmt->execute([$role, $userId]);
                $contacts = array_merge($contacts, $internshipAdvisersStmt->fetchAll());
            } catch (Throwable $e) {
                // Continue if query fails.
            }
        }

        // Get contacts from previous conversations
        try {
            $stmt3 = $pdo->prepare(
                "SELECT DISTINCT 
                    CASE 
                        WHEN sender_id = ? AND sender_role = ? THEN receiver_id
                        ELSE sender_id
                    END as user_id,
                    CASE 
                        WHEN sender_id = ? AND sender_role = ? THEN receiver_role
                        ELSE sender_role
                    END as user_role
                 FROM direct_message
                 WHERE (sender_id = ? AND sender_role = ?) OR (receiver_id = ? AND receiver_role = ?)
                 LIMIT 100"
            );
            $stmt3->execute([$userId, $role, $userId, $role, $userId, $role, $userId, $role]);
            $contacts = array_merge($contacts, $stmt3->fetchAll());
        } catch (Throwable $e) {
            // Continue if query fails
        }

        // Deduplicate and get names
        $uniqueIds = [];
        $result = [];
        
        foreach ($contacts as $contact) {
            $contact_id = (int)($contact['user_id'] ?? 0);
            $contact_role = strtolower(trim((string)($contact['user_role'] ?? '')));
            $key = $contact_id . '_' . $contact_role;

            if (!messaging_validate_role($contact_role) || $contact_id <= 0) continue;
            if (!messaging_can_message($pdo, $role, $userId, $contact_role, $contact_id)) continue;
            if (isset($uniqueIds[$key])) continue;

            $uniqueIds[$key] = true;
            
            $name = (string)($contact['name'] ?? '');
            if (!$name) {
                $name = messaging_get_user_name($pdo, $contact_role, $contact_id);
            }
            
            if ($name && strlen($name) > 1) {
                $roleLabelMap = ['employer' => 'Employer', 'adviser' => 'Adviser', 'student' => 'Student', 'admin' => 'Admin'];

                $presenceStmt = $pdo->prepare(
                    "SELECT last_seen FROM messaging_presence WHERE user_id = ? AND user_role = ? LIMIT 1"
                );
                $presenceStmt->execute([$contact_id, $contact_role]);
                $presence = $presenceStmt->fetch();

                $isOnline = false;
                $lastSeenLabel = 'Offline';
                if ($presence && !empty($presence['last_seen'])) {
                    $lastSeenTs = strtotime((string)$presence['last_seen']);
                    if ($lastSeenTs !== false) {
                        $isOnline = (time() - $lastSeenTs) < 300;
                    }
                    $lastSeenLabel = messaging_format_time((string)$presence['last_seen']);
                }

                $profileSummary = messaging_get_user_profile_summary($pdo, $contact_role, $contact_id);

                $result[] = [
                    'user_id' => $contact_id,
                    'user_role' => $contact_role,
                    'name' => $name,
                    'role_label' => $roleLabelMap[$contact_role] ?? 'Contact',
                    'headline' => (string)($profileSummary['headline'] ?? ''),
                    'email' => (string)($profileSummary['email'] ?? ''),
                    'profile_path' => (string)($profileSummary['profile_path'] ?? ''),
                    'profile_picture' => (string)($profileSummary['profile_picture'] ?? ''),
                    'is_online' => $isOnline,
                    'last_seen' => $lastSeenLabel,
                ];
            }
        }

        // Sort by name
        usort($result, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        messaging_api_respond([
            'ok' => true,
            'contacts' => $result,
            'total' => count($result),
        ]);
    }

    // ==================== GET CONVERSATION ====================
    if ($action === 'get_conversation') {
        $other_id = (int)($_GET['other_user_id'] ?? $_POST['other_user_id'] ?? 0);
        $other_role = strtolower(trim((string)($_GET['other_user_role'] ?? $_POST['other_user_role'] ?? '')));

        if ($other_id <= 0 || !messaging_validate_role($other_role)) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid user ID or role'], 400);
        }

        if (!messaging_role_exists($pdo, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'User not found'], 404);
        }

        if (!messaging_can_message($pdo, $role, $userId, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'Conversation is not allowed for this user pair'], 403);
        }

        $other_name = messaging_get_user_name($pdo, $other_role, $other_id);
        if (!$other_name) {
            messaging_api_respond(['ok' => false, 'error' => 'User not found'], 404);
        }

        $presenceStmt = $pdo->prepare(
            "SELECT last_seen FROM messaging_presence WHERE user_id = ? AND user_role = ? LIMIT 1"
        );
        $presenceStmt->execute([$other_id, $other_role]);
        $presence = $presenceStmt->fetch();

        $isOnline = false;
        $lastSeenLabel = 'Offline';
        if ($presence && !empty($presence['last_seen'])) {
            $lastSeenTs = strtotime((string)$presence['last_seen']);
            if ($lastSeenTs !== false) {
                $isOnline = (time() - $lastSeenTs) < 300;
            }
            $lastSeenLabel = messaging_format_time((string)$presence['last_seen']);
        }

        $profileSummary = messaging_get_user_profile_summary($pdo, $other_role, $other_id);

        // Get messages (last 50)
        $stmt = $pdo->prepare(
            "SELECT message_id, sender_id, sender_role, receiver_id, receiver_role, message_text, is_read, created_at
             FROM direct_message
             WHERE (
                (sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?) OR
                (sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?)
             )
             ORDER BY created_at ASC
             LIMIT 50"
        );
        $stmt->execute([$userId, $role, $other_id, $other_role, $other_id, $other_role, $userId, $role]);
        $messages = $stmt->fetchAll();

        // Mark all unread messages from other user as read
        $stmt2 = $pdo->prepare(
            "UPDATE direct_message SET is_read = 1
             WHERE sender_id = ? AND sender_role = ? AND receiver_id = ? AND receiver_role = ?"
        );
        $stmt2->execute([$other_id, $other_role, $userId, $role]);

        // Update presence
        $stmt3 = $pdo->prepare(
            "INSERT INTO messaging_presence (user_role, user_id, last_seen) 
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW()"
        );
        $stmt3->execute([$role, $userId]);

        $result = array_map(static function (array $msg): array {
            return [
                'message_id' => (int)($msg['message_id'] ?? 0),
                'sender_id' => (int)($msg['sender_id'] ?? 0),
                'sender_role' => (string)($msg['sender_role'] ?? ''),
                'receiver_id' => (int)($msg['receiver_id'] ?? 0),
                'receiver_role' => (string)($msg['receiver_role'] ?? ''),
                'message_text' => (string)($msg['message_text'] ?? ''),
                'is_read' => (int)($msg['is_read'] ?? 0) === 1,
                'created_at' => (string)($msg['created_at'] ?? ''),
                'time_label' => messaging_format_time((string)($msg['created_at'] ?? '')),
            ];
        }, $messages);

        messaging_api_respond([
            'ok' => true,
            'other_user_id' => $other_id,
            'other_user_role' => $other_role,
            'other_user_name' => $other_name,
            'other_user_headline' => (string)($profileSummary['headline'] ?? ''),
            'other_user_email' => (string)($profileSummary['email'] ?? ''),
            'other_user_profile_path' => (string)($profileSummary['profile_path'] ?? ''),
            'other_user_profile_picture' => (string)($profileSummary['profile_picture'] ?? ''),
            'is_online' => $isOnline,
            'last_seen' => $lastSeenLabel,
            'messages' => $result,
            'total' => count($result),
        ]);
    }

    // ==================== SEND MESSAGE ====================
    if ($action === 'send_message') {
        $other_id = (int)($_POST['receiver_id'] ?? 0);
        $other_role = strtolower(trim((string)($_POST['receiver_role'] ?? '')));
        $message = messaging_sanitize_message((string)($_POST['message'] ?? ''));
        $fileUrl = '';

        if ($other_id <= 0 || !messaging_validate_role($other_role)) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid recipient data'], 400);
        }

        if (!messaging_role_exists($pdo, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'Recipient not found'], 404);
        }

        if (!messaging_can_message($pdo, $role, $userId, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'Messaging is not allowed for this user pair'], 403);
        }

        // Handle file upload if present
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/backend/uploads/messages/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['file'];
            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExtensions, true)) {
                messaging_api_respond(['ok' => false, 'error' => 'File type not allowed'], 400);
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                messaging_api_respond(['ok' => false, 'error' => 'File size exceeds 10MB limit'], 400);
            }

            $fileName = 'msg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                messaging_api_respond(['ok' => false, 'error' => 'Failed to save file'], 500);
            }

            $fileUrl = '/SkillHive/assets/backend/uploads/messages/' . $fileName;
            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            
            // Create file metadata marker for frontend parsing
            $fileSize = filesize($filePath);
            $fileMeta = ($isImage ? '[IMG]' : '[FILE]') . $file['name'] . '|' . $fileUrl . '|' . $fileSize . ($isImage ? '[/IMG]' : '[/FILE]');
            
            // If no text message, use just file meta
            if (strlen($message) === 0) {
                $message = $fileMeta;
            } else {
                $message = $message . ' ' . $fileMeta;
            }
        }

        // Require either message text or file
        if (strlen($message) === 0) {
            messaging_api_respond(['ok' => false, 'error' => 'Message or file is required'], 400);
        }

        $other_name = messaging_get_user_name($pdo, $other_role, $other_id);
        if (!$other_name) {
            messaging_api_respond(['ok' => false, 'error' => 'Recipient not found'], 404);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO direct_message (sender_role, sender_id, receiver_role, receiver_id, message_text, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        $stmt->execute([$role, $userId, $other_role, $other_id, $message]);

        $messageId = (int)$pdo->lastInsertId();
        if ($messageId <= 0) {
            $pdo->rollBack();
            messaging_api_respond(['ok' => false, 'error' => 'Message was not saved'], 500);
        }

        $verifyStmt = $pdo->prepare(
            "SELECT message_id, sender_role, sender_id, receiver_role, receiver_id, message_text, is_read, created_at
             FROM direct_message
             WHERE message_id = ?
             LIMIT 1"
        );
        $verifyStmt->execute([$messageId]);
        $savedRow = $verifyStmt->fetch();

        if (!$savedRow) {
            $pdo->rollBack();
            messaging_api_respond(['ok' => false, 'error' => 'Message verification failed'], 500);
        }

        $pdo->commit();

        messaging_api_respond([
            'ok' => true,
            'saved_to_db' => true,
            'message_id' => $messageId,
            'file_url' => $fileUrl,
            'created_at' => (string)($savedRow['created_at'] ?? date('Y-m-d H:i:s')),
            'message' => [
                'message_id' => (int)($savedRow['message_id'] ?? 0),
                'sender_role' => (string)($savedRow['sender_role'] ?? ''),
                'sender_id' => (int)($savedRow['sender_id'] ?? 0),
                'receiver_role' => (string)($savedRow['receiver_role'] ?? ''),
                'receiver_id' => (int)($savedRow['receiver_id'] ?? 0),
                'message_text' => (string)($savedRow['message_text'] ?? ''),
                'is_read' => (int)($savedRow['is_read'] ?? 0) === 1,
                'time_label' => messaging_format_time((string)($savedRow['created_at'] ?? '')),
            ],
        ]);
    }

    // ==================== MARK AS READ ====================
    if ($action === 'mark_as_read') {
        $message_id = (int)($_POST['message_id'] ?? 0);
        if ($message_id <= 0) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid message ID'], 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE direct_message
             SET is_read = 1
             WHERE message_id = ?
               AND receiver_id = ?
               AND receiver_role = ?"
        );
        $stmt->execute([$message_id, $userId, $role]);

        messaging_api_respond(['ok' => true]);
    }

    // ==================== UPDATE PRESENCE ====================
    if ($action === 'update_presence') {
        $stmt = $pdo->prepare(
            "INSERT INTO messaging_presence (user_role, user_id, last_seen)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW()"
        );
        $stmt->execute([$role, $userId]);

        messaging_api_respond(['ok' => true]);
    }

    // ==================== GET ONLINE STATUS ====================
    if ($action === 'get_online_status') {
        $other_id = (int)($_GET['other_user_id'] ?? 0);
        $other_role = strtolower(trim((string)($_GET['other_user_role'] ?? '')));

        if ($other_id <= 0 || !messaging_validate_role($other_role)) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid user'], 400);
        }

        if (!messaging_role_exists($pdo, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'User not found'], 404);
        }

        if (!messaging_can_message($pdo, $role, $userId, $other_role, $other_id)) {
            messaging_api_respond(['ok' => false, 'error' => 'Online status is not available for this user pair'], 403);
        }

        $stmt = $pdo->prepare(
            "SELECT last_seen FROM messaging_presence 
             WHERE user_id = ? AND user_role = ? 
             LIMIT 1"
        );
        $stmt->execute([$other_id, $other_role]);
        $row = $stmt->fetch();

        $is_online = false;
        $last_seen = 'Offline';

        if ($row) {
            $last_seen_time = strtotime($row['last_seen']);
            $is_online = (time() - $last_seen_time) < 300; // 5 minutes
            $last_seen = messaging_format_time($row['last_seen']);
        }

        messaging_api_respond([
            'ok' => true,
            'is_online' => $is_online,
            'last_seen' => $last_seen,
        ]);
    }

    // ==================== GET CURRENT USER PROFILE ====================
    if ($action === 'get_current_user_profile') {
        $profileSummary = messaging_get_user_profile_summary($pdo, $role, $userId);
        $currentName = messaging_get_user_name($pdo, $role, $userId) ?: 'Unknown User';

        messaging_api_respond([
            'ok' => true,
            'user_id' => $userId,
            'user_role' => $role,
            'user_name' => $currentName,
            'user_headline' => (string)($profileSummary['headline'] ?? ''),
            'user_email' => (string)($profileSummary['email'] ?? ''),
            'user_profile_picture' => (string)($profileSummary['profile_picture'] ?? ''),
        ]);
    }

    // ==================== CREATE GROUP CHAT ====================
    if ($action === 'create_group_chat') {
        $groupName = trim((string)($_POST['group_name'] ?? ''));
        $memberIds = $_POST['member_ids'] ?? [];

        if (!$groupName || empty($memberIds)) {
            messaging_api_respond(['ok' => false, 'error' => 'Group name and members are required'], 400);
        }

        if (!is_array($memberIds)) {
            $memberIds = (array)$memberIds;
        }

        $memberIds = array_filter(array_map('intval', $memberIds));

        if (empty($memberIds)) {
            messaging_api_respond(['ok' => false, 'error' => 'Valid members are required'], 400);
        }

        try {
            // Ensure tables exist first
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat (
                    group_chat_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_name VARCHAR(255) NOT NULL,
                    creator_id INT NOT NULL,
                    creator_role VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (creator_id, creator_role)
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_members (
                    group_member_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    member_id INT NOT NULL,
                    member_role VARCHAR(50) NOT NULL,
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (member_id, member_role)
                )"
            );

            // Create group_chat_messages table
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_messages (
                    message_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    sender_id INT NOT NULL,
                    sender_role VARCHAR(50) NOT NULL,
                    message_text TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (sender_id, sender_role),
                    FOREIGN KEY (group_chat_id) REFERENCES group_chat(group_chat_id) ON DELETE CASCADE
                )"
            );

            // Create group chat entry
            $stmt = $pdo->prepare(
                "INSERT INTO group_chat (group_name, creator_id, creator_role) 
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$groupName, $userId, $role]);
            $groupChatId = $pdo->lastInsertId();

            // Add members to group
            $stmt = $pdo->prepare(
                "INSERT INTO group_chat_members (group_chat_id, member_id, member_role) 
                 VALUES (?, ?, ?)"
            );

            // Add creator
            $stmt->execute([$groupChatId, $userId, $role]);

            // Add other members - need to lookup their roles
            foreach ($memberIds as $memberId) {
                // Try to find member in student table
                $memberRole = null;
                try {
                    $checkStmt = $pdo->prepare("SELECT 1 FROM student WHERE student_id = ? LIMIT 1");
                    $checkStmt->execute([$memberId]);
                    if ($checkStmt->fetchColumn()) {
                        $memberRole = 'student';
                    }
                } catch (Throwable $e) {}

                // Try employer table
                if (!$memberRole) {
                    try {
                        $checkStmt = $pdo->prepare("SELECT 1 FROM employer WHERE employer_id = ? LIMIT 1");
                        $checkStmt->execute([$memberId]);
                        if ($checkStmt->fetchColumn()) {
                            $memberRole = 'employer';
                        }
                    } catch (Throwable $e) {}
                }

                // Try adviser table
                if (!$memberRole && messaging_has_internship_adviser_table($pdo)) {
                    try {
                        $checkStmt = $pdo->prepare("SELECT 1 FROM internship_adviser WHERE adviser_id = ? LIMIT 1");
                        $checkStmt->execute([$memberId]);
                        if ($checkStmt->fetchColumn()) {
                            $memberRole = 'adviser';
                        }
                    } catch (Throwable $e) {}
                }

                // Try admin table
                if (!$memberRole) {
                    try {
                        $checkStmt = $pdo->prepare("SELECT 1 FROM admin WHERE admin_id = ? LIMIT 1");
                        $checkStmt->execute([$memberId]);
                        if ($checkStmt->fetchColumn()) {
                            $memberRole = 'admin';
                        }
                    } catch (Throwable $e) {}
                }

                // Add member if role found
                if ($memberRole) {
                    $stmt->execute([$groupChatId, $memberId, $memberRole]);
                }
            }

            messaging_api_respond([
                'ok' => true,
                'group_chat_id' => (int)$groupChatId,
                'message' => 'Group chat created successfully',
            ]);
        } catch (Throwable $e) {
            messaging_api_respond(['ok' => false, 'error' => 'Failed to create group chat: ' . $e->getMessage()], 400);
        }
    }

    // ==================== GET GROUP CHAT MESSAGES ====================
    if ($action === 'get_group_messages') {
        $groupChatId = (int)($_GET['group_chat_id'] ?? 0);

        if ($groupChatId <= 0) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid group chat ID'], 400);
        }

        try {
            // Ensure tables exist
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat (
                    group_chat_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_name VARCHAR(255) NOT NULL,
                    creator_id INT NOT NULL,
                    creator_role VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (creator_id, creator_role)
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_members (
                    group_member_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    member_id INT NOT NULL,
                    member_role VARCHAR(50) NOT NULL,
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (member_id, member_role)
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_messages (
                    message_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    sender_id INT NOT NULL,
                    sender_role VARCHAR(50) NOT NULL,
                    message_text TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (sender_id, sender_role),
                    FOREIGN KEY (group_chat_id) REFERENCES group_chat(group_chat_id) ON DELETE CASCADE
                )"
            );

            // Verify user is member of this group
            $stmt = $pdo->prepare(
                "SELECT 1 FROM group_chat_members 
                 WHERE group_chat_id = ? AND member_id = ? AND member_role = ?
                 LIMIT 1"
            );
            $stmt->execute([$groupChatId, $userId, $role]);
            if (!$stmt->fetchColumn()) {
                messaging_api_respond(['ok' => false, 'error' => 'Access denied'], 403);
            }

            // Get group info
            $stmt = $pdo->prepare(
                "SELECT group_name FROM group_chat WHERE group_chat_id = ? LIMIT 1"
            );
            $stmt->execute([$groupChatId]);
            $groupInfo = $stmt->fetch() ?: [];

            // Get messages
            $stmt = $pdo->prepare(
                "SELECT message_id, sender_id, sender_role, message_text, created_at
                 FROM group_chat_messages
                 WHERE group_chat_id = ?
                 ORDER BY created_at ASC
                 LIMIT 100"
            );
            $stmt->execute([$groupChatId]);
            $messages = $stmt->fetchAll();

            // Get members
            $stmt = $pdo->prepare(
                "SELECT member_id, member_role FROM group_chat_members
                 WHERE group_chat_id = ?
                 ORDER BY joined_at ASC"
            );
            $stmt->execute([$groupChatId]);
            $members = $stmt->fetchAll();

            // Enrich messages with sender info
            $enrichedMessages = [];
            foreach ($messages as $msg) {
                $senderName = messaging_get_user_name($pdo, (string)$msg['sender_role'], (int)$msg['sender_id']);
                $profileSummary = messaging_get_user_profile_summary($pdo, (string)$msg['sender_role'], (int)$msg['sender_id']);
                $enrichedMessages[] = [
                    'message_id' => (int)$msg['message_id'],
                    'sender_id' => (int)$msg['sender_id'],
                    'sender_role' => (string)$msg['sender_role'],
                    'sender_name' => $senderName ?: 'Unknown',
                    'sender_profile_picture' => (string)($profileSummary['profile_picture'] ?? ''),
                    'message_text' => (string)$msg['message_text'],
                    'created_at' => (string)$msg['created_at'],
                ];
            }

            messaging_api_respond([
                'ok' => true,
                'group_chat_id' => $groupChatId,
                'group_name' => (string)($groupInfo['group_name'] ?? ''),
                'messages' => $enrichedMessages,
                'members' => $members,
            ]);
        } catch (Throwable $e) {
            messaging_api_respond(['ok' => false, 'error' => 'Failed to load messages: ' . $e->getMessage()], 500);
        }
    }

    // ==================== SEND GROUP MESSAGE ====================
    if ($action === 'send_group_message') {
        $groupChatId = (int)($_POST['group_chat_id'] ?? 0);
        $messageText = messaging_sanitize_message((string)($_POST['message'] ?? ''));

        if ($groupChatId <= 0 || empty($messageText)) {
            messaging_api_respond(['ok' => false, 'error' => 'Invalid group chat or message'], 400);
        }

        try {
            // Ensure tables exist
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat (
                    group_chat_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_name VARCHAR(255) NOT NULL,
                    creator_id INT NOT NULL,
                    creator_role VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (creator_id, creator_role)
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_members (
                    group_member_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    member_id INT NOT NULL,
                    member_role VARCHAR(50) NOT NULL,
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (member_id, member_role)
                )"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS group_chat_messages (
                    message_id INT AUTO_INCREMENT PRIMARY KEY,
                    group_chat_id INT NOT NULL,
                    sender_id INT NOT NULL,
                    sender_role VARCHAR(50) NOT NULL,
                    message_text TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (group_chat_id),
                    INDEX (sender_id, sender_role),
                    FOREIGN KEY (group_chat_id) REFERENCES group_chat(group_chat_id) ON DELETE CASCADE
                )"
            );

            // Verify user is member of this group
            $stmt = $pdo->prepare(
                "SELECT 1 FROM group_chat_members 
                 WHERE group_chat_id = ? AND member_id = ? AND member_role = ?
                 LIMIT 1"
            );
            $stmt->execute([$groupChatId, $userId, $role]);
            if (!$stmt->fetchColumn()) {
                messaging_api_respond(['ok' => false, 'error' => 'Access denied'], 403);
            }

            // Insert message
            $stmt = $pdo->prepare(
                "INSERT INTO group_chat_messages (group_chat_id, sender_id, sender_role, message_text)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$groupChatId, $userId, $role, $messageText]);

            messaging_api_respond([
                'ok' => true,
                'message_id' => (int)$pdo->lastInsertId(),
                'message' => 'Message sent successfully',
            ]);
        } catch (Throwable $e) {
            messaging_api_respond(['ok' => false, 'error' => 'Failed to send message: ' . $e->getMessage()], 500);
        }
    }

    // Unknown action
    messaging_api_respond(['ok' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    messaging_api_respond(['ok' => false, 'error' => 'Server error'], 500);
}
?>
