<?php

/**
 * Purpose: Handle student settings actions (notification toggle, password change).
 */

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('student_settings_respond')) {
    function student_settings_respond(array $payload, int $statusCode = 200): void
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

require_once __DIR__ . '/../../../backend/db_connect.php';

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$studentId = (int)($_SESSION['student_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'student' || $studentId <= 0) {
    student_settings_respond(['ok' => false, 'message' => 'Unauthorized'], 401);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    student_settings_respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));

try {
    if ($action === 'toggle_email_notifications') {
        $enabledRaw = $_POST['enabled'] ?? null;
        if ($enabledRaw === null) {
            student_settings_respond(['ok' => false, 'message' => 'Missing toggle value.'], 400);
        }

        $enabled = ((string)$enabledRaw === '1' || strtolower((string)$enabledRaw) === 'true') ? 1 : 0;

        $stmt = $pdo->prepare(
            'UPDATE student
             SET email_notifications_enabled = :enabled,
                 updated_at = NOW()
             WHERE student_id = :student_id'
        );
        $stmt->execute([
            ':enabled' => $enabled,
            ':student_id' => $studentId,
        ]);

        student_settings_respond([
            'ok' => true,
            'enabled' => $enabled === 1,
            'message' => $enabled === 1 ? 'Email notifications enabled.' : 'Email notifications disabled.',
        ]);
    }

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $errors = [];
        if ($currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        }
        if ($newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        }
        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            student_settings_respond([
                'ok' => false,
                'message' => 'Please check the password fields.',
                'errors' => $errors,
            ], 422);
        }

        $stmt = $pdo->prepare(
            'SELECT password_hash
             FROM student
             WHERE student_id = :student_id
             LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            student_settings_respond(['ok' => false, 'message' => 'Student account not found.'], 404);
        }

        $storedHash = (string)($row['password_hash'] ?? '');
        if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
            student_settings_respond([
                'ok' => false,
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => 'Current password is incorrect.'],
            ], 422);
        }

        if (password_verify($newPassword, $storedHash)) {
            student_settings_respond([
                'ok' => false,
                'message' => 'New password must be different from your current password.',
                'errors' => ['new_password' => 'New password must be different from your current password.'],
            ], 422);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE student
             SET password_hash = :password_hash,
                 must_change_password = 0,
                 updated_at = NOW()
             WHERE student_id = :student_id'
        );
        $updateStmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':student_id' => $studentId,
        ]);

        $_SESSION['must_change_password'] = false;

        student_settings_respond([
            'ok' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    student_settings_respond(['ok' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    student_settings_respond(['ok' => false, 'message' => 'Unable to process settings right now.'], 500);
}
