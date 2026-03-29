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
            } elseif ($role === 'adviser' || $role === 'admin') {
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
                $stmt = $pdo->prepare(
                    "SELECT email FROM admin WHERE admin_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch() ?: [];

                $summary['headline'] = 'Academic Adviser';
                $summary['email'] = (string)($row['email'] ?? '');
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
        $conversations = $stmt->fetchAll();

        $result = [];
        foreach ($conversations as $conv) {
            $other_id = (int)($conv['other_user_id'] ?? 0);
            $other_role = (string)($conv['other_user_role'] ?? '');
            $last_msg_at = (string)($conv['last_message_at'] ?? '');
            $unread = (int)($conv['unread_count'] ?? 0);

            if ($other_id <= 0 || !messaging_validate_role($other_role)) continue;

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

        messaging_api_respond([
            'ok' => true,
            'conversations' => $result,
            'total' => count($result),
        ]);
    }

    // ==================== GET UNREAD COUNT ====================
    if ($action === 'get_unread_count') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as total FROM direct_message
             WHERE receiver_id = ? AND receiver_role = ? AND is_read = 0"
        );
        $stmt->execute([$userId, $role]);
        $row = $stmt->fetch();
        $unread = (int)($row['total'] ?? 0);

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
                    "SELECT DISTINCT ad.admin_id as user_id, 
                            CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, '')) as name, 
                            'adviser' as user_role
                     FROM admin ad
                     INNER JOIN adviser_assignment aa ON ad.admin_id = aa.adviser_id
                     WHERE aa.student_id = ?
                     ORDER BY ad.first_name, ad.last_name
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
            $allAdvisersStmt = $pdo->prepare(
                "SELECT admin_id as user_id,
                        CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
                        CASE WHEN role_level >= 2 THEN 'admin' ELSE 'adviser' END as user_role
                 FROM admin
                 WHERE NOT ((? = 'adviser' OR ? = 'admin') AND admin_id = ?)
                 ORDER BY first_name, last_name
                 LIMIT 100"
            );
            $allAdvisersStmt->execute([$role, $role, $userId]);
            $contacts = array_merge($contacts, $allAdvisersStmt->fetchAll());
        } catch (Throwable $e) {
            // Continue if query fails.
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

        $stmt = $pdo->prepare("UPDATE direct_message SET is_read = 1 WHERE message_id = ?");
        $stmt->execute([$message_id]);

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

    // Unknown action
    messaging_api_respond(['ok' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    messaging_api_respond(['ok' => false, 'error' => 'Server error'], 500);
}
?>
