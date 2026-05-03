<?php

/**
 * Purpose: Handle employer settings actions (password change, account deletion).
 */

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('employer_settings_respond')) {
    function employer_settings_respond(array $payload, int $statusCode = 200): void
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
require_once __DIR__ . '/../post_internship/auth_helpers.php';

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);

if ($role !== 'employer' || !$employerId) {
    employer_settings_respond(['ok' => false, 'message' => 'Unauthorized'], 401);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    employer_settings_respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));

try {
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
            employer_settings_respond([
                'ok' => false,
                'message' => 'Please check the password fields.',
                'errors' => $errors,
            ], 422);
        }

        $stmt = $pdo->prepare(
            'SELECT password_hash
             FROM employer
             WHERE employer_id = :employer_id
             LIMIT 1'
        );
        $stmt->execute([':employer_id' => (int)$employerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            employer_settings_respond(['ok' => false, 'message' => 'Account not found.'], 404);
        }

        $storedHash = (string)($row['password_hash'] ?? '');

        if ($storedHash === '') {
            employer_settings_respond([
                'ok' => false,
                'message' => 'No password is set for this account.',
                'errors' => ['current_password' => 'No password is set.'],
            ], 422);
        }

        $passwordValid = false;
        if (password_verify($currentPassword, $storedHash)) {
            $passwordValid = true;
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE employer SET password_hash = ? WHERE employer_id = ?')
                    ->execute([password_hash($currentPassword, PASSWORD_DEFAULT), (int)$employerId]);
            }
        }

        if (!$passwordValid) {
            $storedLower = strtolower($storedHash);
            if (hash_equals($storedHash, $currentPassword)) {
                $passwordValid = true;
            } elseif (preg_match('/^[a-f0-9]{32}$/', $storedLower) && hash_equals($storedLower, md5($currentPassword))) {
                $passwordValid = true;
            } elseif (preg_match('/^[a-f0-9]{40}$/', $storedLower) && hash_equals($storedLower, sha1($currentPassword))) {
                $passwordValid = true;
            }
        }

        if (!$passwordValid) {
            employer_settings_respond([
                'ok' => false,
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => 'Current password is incorrect.'],
            ], 422);
        }

        if ($storedHash !== '' && password_verify($newPassword, $storedHash)) {
            employer_settings_respond([
                'ok' => false,
                'message' => 'New password must be different from your current password.',
                'errors' => ['new_password' => 'New password must be different.'],
            ], 422);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE employer
             SET password_hash = :password_hash,
                 updated_at = NOW()
             WHERE employer_id = :employer_id'
        );
        $updateStmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':employer_id' => (int)$employerId,
        ]);

        employer_settings_respond([
            'ok' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    if ($action === 'delete_account') {
        $confirm = trim((string)($_POST['confirm'] ?? ''));

        $stmt = $pdo->prepare(
            'SELECT company_name FROM employer WHERE employer_id = :employer_id LIMIT 1'
        );
        $stmt->execute([':employer_id' => (int)$employerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            employer_settings_respond(['ok' => false, 'message' => 'Account not found.'], 404);
        }

        if ($confirm !== $row['company_name']) {
            employer_settings_respond([
                'ok' => false,
                'message' => 'Company name does not match. Please type it exactly.',
            ], 422);
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT internship_id FROM internship WHERE employer_id = ?');
            $stmt->execute([(int)$employerId]);
            $internshipIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($internshipIds)) {
                $placeholders = implode(',', array_fill(0, count($internshipIds), '?'));
                $pdo->prepare("DELETE FROM application WHERE internship_id IN ($placeholders)")->execute($internshipIds);
                $pdo->prepare("DELETE FROM interview WHERE application_id IN (SELECT application_id FROM application WHERE internship_id IN ($placeholders))")->execute($internshipIds);
                $pdo->prepare("DELETE FROM internship WHERE employer_id = ?")->execute([(int)$employerId]);
            }

            $pdo->prepare('DELETE FROM employer WHERE employer_id = ?')->execute([(int)$employerId]);

            $pdo->commit();

            session_unset();
            session_destroy();

            employer_settings_respond([
                'ok' => true,
                'message' => 'Account deleted successfully. Redirecting...',
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            employer_settings_respond(['ok' => false, 'message' => 'Unable to delete account right now.'], 500);
        }
    }

    if ($action === 'update_profile') {
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $industry = trim((string)($_POST['industry'] ?? ''));
        $companyAddress = trim((string)($_POST['company_address'] ?? ''));
        $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
        $websiteUrl = trim((string)($_POST['website_url'] ?? ''));

        $errors = [];
        if ($companyName === '') $errors['company_name'] = 'Company name is required.';
        if ($industry === '') $errors['industry'] = 'Industry is required.';
        if ($companyAddress === '') $errors['company_address'] = 'Company address is required.';
        if ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) $errors['website_url'] = 'Must be a valid URL.';
        if (strlen($contactNumber) > 20) $errors['contact_number'] = 'Max 20 characters.';

        if (!empty($errors)) {
            employer_settings_respond(['ok' => false, 'message' => 'Please check the fields.', 'errors' => $errors], 422);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE employer
             SET company_name = :company_name,
                 industry = :industry,
                 company_address = :company_address,
                 contact_number = :contact_number,
                 website_url = :website_url,
                 updated_at = NOW()
             WHERE employer_id = :employer_id'
        );
        $updateStmt->execute([
            ':company_name' => $companyName,
            ':industry' => $industry,
            ':company_address' => $companyAddress,
            ':contact_number' => $contactNumber,
            ':website_url' => $websiteUrl,
            ':employer_id' => (int)$employerId,
        ]);

        $_SESSION['user_name'] = $companyName;

        employer_settings_respond(['ok' => true, 'message' => 'Profile updated successfully.']);
    }

    if ($action === 'upload_logo') {
        $uploadDir = __DIR__ . '/../../../assets/backend/uploads/company';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

        $uploadedLogo = '';

        if (isset($_FILES['company_logo']) && is_array($_FILES['company_logo'])) {
            $file = $_FILES['company_logo'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['size'] ?? 0) > 0) {
                $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                $maxSize = 5 * 1024 * 1024;

                if (!in_array($ext, $allowedExt, true)) {
                    employer_settings_respond(['ok' => false, 'message' => 'Only JPG, PNG, WEBP allowed.'], 422);
                }
                if ((int)$file['size'] > $maxSize) {
                    employer_settings_respond(['ok' => false, 'message' => 'File must be under 5MB.'], 422);
                }

                $filename = 'logo_' . $employerId . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;

                if (move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                    $uploadedLogo = $filename;
                } else {
                    employer_settings_respond(['ok' => false, 'message' => 'Failed to upload file.'], 500);
                }
            }
        }

        if ($uploadedLogo === '') {
            employer_settings_respond(['ok' => false, 'message' => 'No file uploaded.'], 400);
        }

        $existingStmt = $pdo->prepare('SELECT company_logo FROM employer WHERE employer_id = ?');
        $existingStmt->execute([(int)$employerId]);
        $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $oldLogo = $existingRow ? trim((string)$existingRow['company_logo']) : '';
        if ($oldLogo !== '' && $oldLogo !== $uploadedLogo && file_exists($uploadDir . '/' . $oldLogo)) {
            @unlink($uploadDir . '/' . $oldLogo);
        }

        $pdo->prepare('UPDATE employer SET company_logo = ?, updated_at = NOW() WHERE employer_id = ?')
            ->execute([$uploadedLogo, (int)$employerId]);

        $logoUrl = '/SkillHive/assets/backend/uploads/company/' . rawurlencode($uploadedLogo);

        employer_settings_respond([
            'ok' => true,
            'message' => 'Logo uploaded successfully.',
            'logo_url' => $logoUrl,
            'logo_filename' => $uploadedLogo,
        ]);
    }

    employer_settings_respond(['ok' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    employer_settings_respond(['ok' => false, 'message' => 'Unable to process request right now.'], 500);
}
