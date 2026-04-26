<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';
$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

if ($adviserId <= 0) {
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

// Handle profile picture upload
$pictureUploadError = '';
$pictureUploadSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_picture' && isset($_FILES['profile_picture'])) {
    $uploadDir = __DIR__ . '/../../assets/backend/uploads/profile/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (!in_array($file['type'], $allowedTypes)) {
            $pictureUploadError = 'Invalid file type. Please upload JPG, PNG, GIF, or WebP.';
        } elseif ($file['size'] > $maxFileSize) {
            $pictureUploadError = 'File size exceeds 5MB limit.';
        } else {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'adviser_' . $adviserId . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE internship_adviser SET profile_picture = ? WHERE adviser_id = ?'
                    );
                    $stmt->execute([$fileName, $adviserId]);
                    $pictureUploadSuccess = 'Profile picture updated successfully!';
                } catch (Throwable $e) {
                    $pictureUploadError = 'Failed to save picture information.';
                    @unlink($filePath);
                }
            } else {
                $pictureUploadError = 'Failed to upload file.';
            }
        }
    } else {
        $pictureUploadError = 'File upload error.';
    }
}

if (!function_exists('adviser_profile_escape')) {
    function adviser_profile_escape(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_profile_has_column')) {
    function adviser_profile_has_column(PDO $pdo, string $columnName): bool
    {
        static $cache = [];
        $key = strtolower(trim($columnName));
        if ($key === '') {
            return false;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "internship_adviser"
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$columnName]);
        $cache[$key] = (bool)$stmt->fetchColumn();

        return $cache[$key];
    }
}

if (!function_exists('adviser_profile_initials')) {
    function adviser_profile_initials(string $firstName, string $lastName): string
    {
        $first = trim($firstName);
        $last = trim($lastName);

        $initials = '';
        if ($first !== '') {
            $initials .= strtoupper(substr($first, 0, 1));
        }
        if ($last !== '') {
            $initials .= strtoupper(substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'AD';
    }
}

$form = [
    'first_name' => '',
    'last_name' => '',
    'department' => '',
    'email' => '',
    'profile_picture' => '',
];

$profileError = '';
$passwordError = '';

try {
    $stmt = $pdo->prepare(
        'SELECT adviser_id, first_name, last_name, department, email, profile_picture
         FROM internship_adviser
         WHERE adviser_id = ?
         LIMIT 1'
    );
    $stmt->execute([$adviserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row && !empty($_SESSION['user_email'])) {
        $stmt = $pdo->prepare(
            'SELECT adviser_id, first_name, last_name, department, email, profile_picture
             FROM internship_adviser
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([(string)$_SESSION['user_email']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        $profileError = 'Adviser profile record not found.';
    } else {
        $adviserId = (int)($row['adviser_id'] ?? $adviserId);
        $_SESSION['adviser_id'] = $adviserId;

        $form['first_name'] = trim((string)($row['first_name'] ?? ''));
        $form['last_name'] = trim((string)($row['last_name'] ?? ''));
        $form['department'] = trim((string)($row['department'] ?? ''));
        $form['email'] = trim((string)($row['email'] ?? ''));
        $form['profile_picture'] = trim((string)($row['profile_picture'] ?? ''));
    }
} catch (Throwable $e) {
    $profileError = 'Unable to load profile details right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profileError === '') {
    $action = trim((string)($_POST['action'] ?? 'update_profile'));

    if ($action === 'update_profile') {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));

        $form['first_name'] = $firstName;
        $form['last_name'] = $lastName;
        $form['department'] = $department;

        if ($firstName === '') {
            $profileError = 'First name is required.';
        } elseif ($lastName === '') {
            $profileError = 'Last name is required.';
        }

        if ($profileError === '') {
            try {
                $hasUpdatedAtColumn = adviser_profile_has_column($pdo, 'updated_at');
                $sql = $hasUpdatedAtColumn
                    ? 'UPDATE internship_adviser
                       SET first_name = ?,
                           last_name = ?,
                           department = ?,
                           updated_at = NOW()
                       WHERE adviser_id = ?'
                    : 'UPDATE internship_adviser
                       SET first_name = ?,
                           last_name = ?,
                           department = ?
                       WHERE adviser_id = ?';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$firstName, $lastName, $department, $adviserId]);

                $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
                $_SESSION['department'] = $department;
                $_SESSION['status'] = 'Adviser profile updated successfully.';

                header('Location: ' . $baseUrl . '/layout.php?page=adviser/profile');
                exit;
            } catch (Throwable $e) {
                $profileError = 'Unable to save profile details right now.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $passwordError = 'All password fields are required.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New password and confirmation do not match.';
        }

        if ($passwordError === '') {
            try {
                $stmt = $pdo->prepare(
                    'SELECT password_hash
                     FROM internship_adviser
                     WHERE adviser_id = ?
                     LIMIT 1'
                );
                $stmt->execute([$adviserId]);
                $storedHash = (string)($stmt->fetchColumn() ?? '');

                if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $hasUpdatedAtColumn = adviser_profile_has_column($pdo, 'updated_at');

                    $updateSql = $hasUpdatedAtColumn
                        ? 'UPDATE internship_adviser
                           SET password_hash = ?,
                               updated_at = NOW()
                           WHERE adviser_id = ?'
                        : 'UPDATE internship_adviser
                           SET password_hash = ?
                           WHERE adviser_id = ?';

                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$newHash, $adviserId]);

                    $_SESSION['status'] = 'Password updated successfully.';
                    header('Location: ' . $baseUrl . '/layout.php?page=adviser/profile');
                    exit;
                }
            } catch (Throwable $e) {
                $passwordError = 'Unable to update password right now.';
            }
        }
    }
}

$stats = [
    'assigned_students' => 0,
    'pending_endorsements' => 0,
];

try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT student_id)
         FROM adviser_assignment
         WHERE adviser_id = ?
           AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"'
    );
    $stmt->execute([$adviserId]);
    $stats['assigned_students'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

try {
  $stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM endorsement
     WHERE adviser_id = ?
       AND LOWER(COALESCE(status, "")) IN ("pending", "for review", "submitted", "reviewing")'
  );
  $stmt->execute([$adviserId]);
  $stats['pending_endorsements'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

$adviserName = trim($form['first_name'] . ' ' . $form['last_name']);
if ($adviserName === '') {
    $adviserName = trim((string)($_SESSION['user_name'] ?? 'Adviser'));
}
?>

<style>
  .adviser-profile-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
    color: var(--text);
    font-size: var(--font-size-body);
    position: relative;
  }

  .adviser-profile-cover {
    height: 200px;
    background: linear-gradient(135deg, #050505 0%, #050505 40%, #12b3ac 72%, #12b3ac 100%);
    border-radius: 16px;
    position: relative;
    overflow: visible;
    z-index: 1;
    margin: 0 -24px;
    padding: 0 24px;
    width: calc(100% + 48px);
  }

  .adviser-profile-cover::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
  }

  .adviser-profile-cover::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: -20px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
  }

  .adviser-profile-header {
    display: flex;
    gap: 24px;
    align-items: center;
    margin-top: -90px;
    margin-bottom: 24px;
    position: relative;
    z-index: 2;
    padding: 0 24px 20px 24px;
  }

  .adviser-profile-avatar-container {
    position: relative;
    flex-shrink: 0;
    z-index: 3;
  }

  .adviser-profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    box-shadow: 0 12px 32px rgba(13, 27, 46, 0.5), 0 0 0 5px #fff;
    border: 5px solid #fff;
    object-fit: cover;
    cursor: pointer;
    transition: all .3s ease;
  }

  .adviser-profile-avatar:hover {
    box-shadow: 0 16px 40px rgba(13, 27, 46, 0.6), 0 0 0 5px #fff;
    transform: scale(1.05);
  }

  .adviser-profile-avatar.has-image {
    background: none;
  }

  .adviser-profile-avatar-upload {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    border: 3px solid #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: white;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(13, 27, 46, 0.4);
    transition: all .2s ease;
    z-index: 4;
  }

  .adviser-profile-avatar-upload:hover {
    background: linear-gradient(135deg, #050505 0%, #0a0f1a 100%);
    transform: scale(1.1);
  }

  #profilePictureInput {
    display: none;
  }

  .adviser-profile-info {
    flex: 1;
    background: url('/Skillhive/assets/media/element%201.png') no-repeat right center, rgba(255, 255, 255, 0.99);
    background-size: 200px 200px, 100%;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    align-self: flex-end;
    margin-bottom: 0;
    position: relative;
    overflow: hidden;
  }

  .adviser-profile-name {
    margin: 0 0 8px;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text);
  }

  .adviser-profile-title {
    font-size: 1.1rem;
    color: var(--text3);
    margin: 0 0 16px;
    font-weight: 600;
  }

  .adviser-profile-bio {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 0;
  }

  .adviser-profile-bio-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text3);
    font-size: .95rem;
  }

  .adviser-profile-bio-item i {
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1rem;
  }

  .adviser-profile-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(300px, 1fr);
    gap: 16px;
    position: relative;
    z-index: 2;
  }

  .adviser-profile-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all var(--transition);
  }

  .adviser-profile-panel::before {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.06);
    pointer-events: none;
  }

  .adviser-profile-panel::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -20px;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.04);
    pointer-events: none;
  }

  .adviser-profile-panel:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  }

  .adviser-profile-section-title {
    margin: 0 0 18px;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .adviser-profile-section-title i {
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-profile-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .adviser-profile-label {
    display: block;
    margin: 0 0 8px;
    font-size: .82rem;
    font-weight: 600;
    color: var(--text);
    text-transform: uppercase;
    letter-spacing: .5px;
  }

  .adviser-profile-input {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    color: var(--text);
    font-size: .86rem;
    padding: 12px 14px;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .adviser-profile-input:focus {
    border-color: #050505;
    box-shadow: 0 0 0 4px rgba(13, 27, 46, 0.1);
  }

  .adviser-profile-input[readonly] {
    background: #ffffff;
    color: #6b7280;
    cursor: not-allowed;
  }

  .adviser-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 12px;
    border: none;
    min-height: 42px;
    padding: 0 20px;
    cursor: pointer;
    text-decoration: none;
    font-size: .86rem;
    font-weight: 700;
    background: #050505;
    color: #fff;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.3);
    transition: all .2s ease;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 24, 39, 0.4);
    background: #0f172a;
  }

  .adviser-profile-btn-secondary {
    background: #f3f4f6;
    color: #050505;
    box-shadow: none;
    border: 1px solid #e5e7eb;
  }

  .adviser-profile-btn-secondary:hover {
    background: #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .adviser-profile-error {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #12b3ac;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: .82rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .adviser-profile-success {
    border: 1px solid #86efac;
    background: #f0fdf4;
    color: #166534;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: .82rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .adviser-profile-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .adviser-profile-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-stat {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px 12px;
    background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%);
    transition: all .2s ease;
    text-align: center;
  }

  .adviser-profile-stat:first-child {
    background: linear-gradient(135deg, #f0f9ff 0%, #e1f5fe 100%);
    border: 1px solid rgba(165, 243, 252, 0.4);
  }

  .adviser-profile-stat:last-child {
    background: linear-gradient(135deg, #f3f4fd 0%, #ede9fe 100%);
    border: 1px solid rgba(196, 181, 253, 0.4);
  }

  .adviser-profile-stat:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
  }

  .adviser-profile-stat:first-child:hover {
    border-color: rgba(165, 243, 252, 0.6);
    box-shadow: 0 8px 16px rgba(165, 243, 252, 0.15);
  }

  .adviser-profile-stat:last-child:hover {
    border-color: rgba(196, 181, 253, 0.6);
    box-shadow: 0 8px 16px rgba(196, 181, 253, 0.15);
  }

  .adviser-profile-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-profile-stat:first-child .adviser-profile-stat-value {
    background: linear-gradient(135deg, #5ba5c9 0%, #4da8c9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-profile-stat:last-child .adviser-profile-stat-value {
    background: linear-gradient(135deg, #9b7dd9 0%, #8b7dd9 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-profile-stat-label {
    margin-top: 6px;
    font-size: .75rem;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 600;
  }

  .adviser-profile-stat:first-child .adviser-profile-stat-label {
    color: #5ba5c9;
    font-weight: 700;
  }

  .adviser-profile-stat:last-child .adviser-profile-stat-label {
    color: #9b7dd9;
    font-weight: 700;
  }

  .adviser-profile-password-section {
    background: linear-gradient(135deg, #050505 0%, #050505 100%);
    border: 1px solid rgba(0, 128, 255, 0.1);
    border-radius: 14px;
    padding: 20px;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-password-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .adviser-profile-password-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .adviser-profile-password-section .adviser-profile-label {
    color: rgba(255, 255, 255, 0.9);
  }

  .adviser-profile-password-input {
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    font-size: .86rem;
    padding: 12px 14px;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .adviser-profile-password-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
  }

  .adviser-profile-password-input:focus {
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.12);
  }

  .adviser-profile-password-btn {
    background: #050505;
    color: #fff;
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.3);
  }

  .adviser-profile-password-btn:hover {
    background: #0f172a;
    box-shadow: 0 6px 20px rgba(17, 24, 39, 0.4);
  }

  .adviser-profile-messaging-banner {
    background: linear-gradient(135deg, #050505 0%, #050505 50%, #12b3ac 100%);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.26);
    transition: all .3s ease;
  }

  .adviser-profile-messaging-banner:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.36);
  }

  .adviser-profile-messaging-banner::before {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(154, 228, 221, 0.14);
    pointer-events: none;
  }

  .adviser-profile-messaging-banner::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -20px;
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(75, 204, 190, 0.12);
    pointer-events: none;
  }

  .adviser-profile-messaging-icon {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-messaging-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0, 128, 255, 0.2));
  }

  .adviser-profile-messaging-content {
    flex: 1;
    position: relative;
    z-index: 1;
  }

  .adviser-profile-messaging-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 8px;
  }

  .adviser-profile-messaging-description {
    color: rgba(255, 255, 255, 0.8);
    font-size: .95rem;
    margin: 0 0 12px;
    line-height: 1.4;
  }

  .adviser-profile-messaging-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #00a8e8 0%, #0096d1 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 18px;
    font-size: .85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s ease;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 168, 232, 0.3);
  }

  .adviser-profile-messaging-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 168, 232, 0.4);
    background: linear-gradient(135deg, #0096d1 0%, #0080b8 100%);
  }

  @media (max-width: 640px) {
    .adviser-profile-messaging-banner {
      flex-direction: column;
      text-align: center;
      padding: 20px;
    }

    .adviser-profile-messaging-icon {
      width: 60px;
      height: 60px;
    }

    .adviser-profile-messaging-title {
      font-size: 1rem;
    }

    .adviser-profile-messaging-description {
      font-size: .85rem;
    }
  }

  @media (max-width: 1000px) {
    .adviser-profile-layout {
      grid-template-columns: 1fr;
    }

    .adviser-profile-cover {
      height: 150px;
      padding-bottom: 50px;
    }

    .adviser-profile-header {
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-top: -80px;
    }

    .adviser-profile-info {
      width: 100%;
      text-align: center;
      align-self: auto;
    }

    .adviser-profile-bio {
      justify-content: center;
    }
  }

  @media (max-width: 640px) {
    .adviser-profile-grid {
      grid-template-columns: 1fr;
    }

    .adviser-profile-password-grid {
      grid-template-columns: 1fr;
    }

    .adviser-profile-stats {
      grid-template-columns: 1fr;
    }

    .adviser-profile-panel {
      padding: 18px;
    }

    .adviser-profile-cover {
      height: 130px;
      padding-bottom: 40px;
    }

    .adviser-profile-avatar {
      width: 100px;
      height: 100px;
      font-size: 2.2rem;
    }

    .adviser-profile-name {
      font-size: 1.3rem;
    }

    .adviser-profile-header {
      gap: 16px;
      margin-top: -70px;
    }

    .adviser-profile-info {
      padding: 16px;
    }
  }
</style>

<div class="adviser-profile-page">
  <div class="adviser-profile-cover"></div>

  <!-- Profile Header (LinkedIn Style) -->
  <div class="adviser-profile-header">
    <div class="adviser-profile-avatar-container">
      <?php 
        $profilePicturePath = '';
        if (!empty($form['profile_picture'])) {
          $profilePicturePath = $baseUrl . '/assets/backend/uploads/profile/' . adviser_profile_escape($form['profile_picture']);
        }
      ?>
      <?php if ($profilePicturePath): ?>
        <img id="profileAvatar" class="adviser-profile-avatar has-image" src="<?php echo $profilePicturePath; ?>" alt="Profile Picture" onclick="document.getElementById('profilePictureInput').click();">
      <?php else: ?>
        <div id="profileAvatar" class="adviser-profile-avatar" onclick="document.getElementById('profilePictureInput').click();">
          <?php echo adviser_profile_escape(adviser_profile_initials($form['first_name'], $form['last_name'])); ?>
        </div>
      <?php endif; ?>
      <div class="adviser-profile-avatar-upload" onclick="document.getElementById('profilePictureInput').click();" title="Change profile picture">
        <i class="fas fa-camera"></i>
      </div>
    </div>

    <div class="adviser-profile-info">
      <h1 class="adviser-profile-name"><?php echo adviser_profile_escape($adviserName); ?></h1>
      <p class="adviser-profile-title"><i class="fas fa-briefcase"></i> Internship Adviser</p>
      <div class="adviser-profile-bio">
        <div class="adviser-profile-bio-item">
          <i class="fas fa-building"></i>
          <span><?php echo adviser_profile_escape($form['department'] !== '' ? $form['department'] : 'Department not set'); ?></span>
        </div>
        <div class="adviser-profile-bio-item">
          <i class="fas fa-envelope"></i>
          <span><?php echo adviser_profile_escape($form['email']); ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden file input for profile picture -->
  <input type="file" id="profilePictureInput" accept="image/jpeg,image/png,image/gif,image/webp">

  <!-- Main Profile Layout -->
  <div class="adviser-profile-layout">
    <!-- Left Column: Edit Profile -->
    <section class="adviser-profile-panel">
      <h3 class="adviser-profile-section-title">
        <i class="fas fa-user-edit"></i> Edit Profile
      </h3>

      <?php if ($profileError !== ''): ?>
        <div class="adviser-profile-error" style="margin-bottom:14px;">
          <i class="fas fa-exclamation-circle"></i> 
          <?php echo adviser_profile_escape($profileError); ?>
        </div>
      <?php endif; ?>

      <form class="adviser-profile-form" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/profile">
        <input type="hidden" name="action" value="update_profile">

        <div class="adviser-profile-grid">
          <div>
            <label class="adviser-profile-label" for="adviserFirstName">First Name</label>
            <input id="adviserFirstName" class="adviser-profile-input" type="text" name="first_name" value="<?php echo adviser_profile_escape($form['first_name']); ?>" required>
          </div>

          <div>
            <label class="adviser-profile-label" for="adviserLastName">Last Name</label>
            <input id="adviserLastName" class="adviser-profile-input" type="text" name="last_name" value="<?php echo adviser_profile_escape($form['last_name']); ?>" required>
          </div>
        </div>

        <div>
          <label class="adviser-profile-label" for="adviserDepartment">Department</label>
          <input id="adviserDepartment" class="adviser-profile-input" type="text" name="department" value="<?php echo adviser_profile_escape($form['department']); ?>" placeholder="e.g. College of Informatics">
        </div>

        <div>
          <label class="adviser-profile-label" for="adviserEmail">Email</label>
          <input id="adviserEmail" class="adviser-profile-input" type="email" value="<?php echo adviser_profile_escape($form['email']); ?>" readonly>
        </div>

        <div>
          <button class="adviser-profile-btn" type="submit">
            <i class="fas fa-floppy-disk"></i> Save Profile
          </button>
        </div>
      </form>
    </section>

    <!-- Right Column: Stats -->
    <aside class="adviser-profile-panel adviser-profile-card">
      <h3 class="adviser-profile-section-title">
        <i class="fas fa-chart-bar"></i> Statistics
      </h3>
      <div class="adviser-profile-stats">
        <div class="adviser-profile-stat">
          <div class="adviser-profile-stat-value"><?php echo (int)$stats['assigned_students']; ?></div>
          <div class="adviser-profile-stat-label">Students Assigned</div>
        </div>
        <div class="adviser-profile-stat">
          <div class="adviser-profile-stat-value"><?php echo (int)$stats['pending_endorsements']; ?></div>
          <div class="adviser-profile-stat-label">Pending Reviews</div>
        </div>
      </div>
    </aside>
  </div>

  <!-- Messaging Banner -->
  <div class="adviser-profile-messaging-banner">
    <div class="adviser-profile-messaging-icon">
      <img src="<?php echo $baseUrl; ?>/assets/media/cutie bee.png" alt="Messaging">
    </div>
    <div class="adviser-profile-messaging-content">
      <h3 class="adviser-profile-messaging-title">Stay Connected with Your Students</h3>
      <p class="adviser-profile-messaging-description">
        Send direct messages to your students, share important updates, and stay in touch with your advisees through our secure messaging platform.
      </p>
      <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/messaging" class="adviser-profile-messaging-btn">
        <i class="fas fa-comments"></i> Open Messaging
      </a>
    </div>
  </div>

  <!-- Security Section -->
  <section class="adviser-profile-panel">
    <h3 class="adviser-profile-section-title">
      <i class="fas fa-lock"></i> Security & Password
    </h3>

    <?php if ($passwordError !== ''): ?>
      <div class="adviser-profile-error" style="margin-bottom:16px;">
        <i class="fas fa-shield-alt"></i>
        <?php echo adviser_profile_escape($passwordError); ?>
      </div>
    <?php endif; ?>

    <div class="adviser-profile-password-section">
      <form class="adviser-profile-password-form" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/profile">
        <input type="hidden" name="action" value="change_password">

        <div class="adviser-profile-password-grid">
          <div>
            <label class="adviser-profile-label" for="currentPassword">Current Password</label>
            <input id="currentPassword" class="adviser-profile-password-input" type="password" name="current_password" required>
          </div>

          <div></div>

          <div>
            <label class="adviser-profile-label" for="newPassword">New Password</label>
            <input id="newPassword" class="adviser-profile-password-input" type="password" name="new_password" minlength="8" required placeholder="At least 8 characters">
          </div>

          <div>
            <label class="adviser-profile-label" for="confirmPassword">Confirm New Password</label>
            <input id="confirmPassword" class="adviser-profile-password-input" type="password" name="confirm_password" minlength="8" required placeholder="Re-enter new password">
          </div>
        </div>

        <div>
          <button class="adviser-profile-btn adviser-profile-password-btn" type="submit">
            <i class="fas fa-key"></i> Update Password
          </button>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
  // Handle profile picture upload
  const profilePictureInput = document.getElementById('profilePictureInput');
  
  profilePictureInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
      const file = this.files[0];
      
      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        alert('Please upload a valid image file (JPG, PNG, GIF, or WebP)');
        return;
      }
      
      // Validate file size (5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
      }
      
      // Create form data
      const formData = new FormData();
      formData.append('action', 'upload_picture');
      formData.append('profile_picture', file);
      
      // Show loading state
      const avatar = document.getElementById('profileAvatar');
      const originalContent = avatar.innerHTML;
      avatar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      
      // Upload file
      fetch('<?php echo $baseUrl; ?>/layout.php?page=adviser/profile', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(html => {
        // Reload the page to show updated picture
        location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error uploading picture. Please try again.');
        avatar.innerHTML = originalContent;
      });
    }
  });
</script>
