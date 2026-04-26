<?php
function profile_ensure_media_columns(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM student') as $column) {
        $existing[(string) $column['Field']] = true;
    }

    if (!isset($existing['cover_photo'])) {
        $pdo->exec('ALTER TABLE student ADD COLUMN cover_photo VARCHAR(255) NULL AFTER profile_picture');
    }

    if (!isset($existing['cover_gradient'])) {
        $pdo->exec('ALTER TABLE student ADD COLUMN cover_gradient VARCHAR(255) NULL AFTER cover_photo');
    }

    if (!isset($existing['avatar_preset'])) {
        $pdo->exec('ALTER TABLE student ADD COLUMN avatar_preset VARCHAR(50) NULL AFTER cover_gradient');
    }

    $ensured = true;
}

function profile_handle_media(PDO $pdo, int $userId, array &$profileErrors, string &$profileSuccess): void
{
    profile_ensure_media_columns($pdo);

    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['update_media', 'update_cover_style', 'update_avatar_style'], true)) {
        return;
    }

    $uploadDir = __DIR__ . '/../../../assets/backend/uploads/profile';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $updates = [];

    $processUpload = static function (array $file, string $prefix) use ($allowedExt, $uploadDir, $userId, &$profileErrors): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $profileErrors[] = 'Image upload failed. Please try again.';
            return null;
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $profileErrors[] = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
            return null;
        }

        if ((int) ($file['size'] ?? 0) > (5 * 1024 * 1024)) {
            $profileErrors[] = 'Each image must be 5MB or smaller.';
            return null;
        }

        $filename = $prefix . '_' . $userId . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            $profileErrors[] = 'Unable to save uploaded image.';
            return null;
        }

        return $filename;
    };

    if (in_array($action, ['update_media', 'update_avatar_style'], true) && isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture'])) {
        $avatar = $processUpload($_FILES['profile_picture'], 'avatar');
        if ($avatar !== null) {
            $updates['profile_picture'] = $avatar;
            $updates['avatar_preset'] = null;
        }
    }

    if (isset($_FILES['cover_photo']) && is_array($_FILES['cover_photo'])) {
        $cover = $processUpload($_FILES['cover_photo'], 'cover');
        if ($cover !== null) {
            $updates['cover_photo'] = $cover;
            $updates['cover_gradient'] = null;
        }
    }

    if ($action === 'update_cover_style') {
        $gradient = trim((string) ($_POST['cover_gradient'] ?? ''));
        $allowedGradients = [
            'linear-gradient(135deg,#FDE047 0%,#38BDF8 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#22D3EE 100%)',
            'linear-gradient(135deg,#059669 0%,#34D399 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#4338CA 0%,#2a8b8d 100%)',
            'linear-gradient(135deg,#0EA5E9 0%,#38BDF8 100%)',
            'linear-gradient(135deg,#A21CAF 0%,#C084FC 100%)',
            'linear-gradient(135deg,#12b3ac 0%,#12b3ac 100%)',
            'linear-gradient(135deg,#16A34A 0%,#86EFAC 100%)',
            'linear-gradient(135deg,#050505 0%,#9CA3AF 100%)',
        ];

        if ($gradient !== '' && !array_key_exists('cover_photo', $updates)) {
            if (!in_array($gradient, $allowedGradients, true)) {
                $profileErrors[] = 'Invalid cover gradient selection.';
            } else {
                $updates['cover_gradient'] = $gradient;
                if (!array_key_exists('cover_photo', $updates)) {
                    $updates['cover_photo'] = null;
                }
            }
        }
    }

    if ($action === 'update_avatar_style') {
        $avatarPreset = trim((string) ($_POST['avatar_preset'] ?? ''));
        $allowedPresets = [
            'tech-girl',
            'creative-boy',
            'mentor-man',
            'student-boy',
            'astronaut',
            'coder-girl',
            'leader-girl',
            'intern-boy',
        ];

        if ($avatarPreset !== '' && !array_key_exists('profile_picture', $updates)) {
            if (!in_array($avatarPreset, $allowedPresets, true)) {
                $profileErrors[] = 'Invalid avatar selection.';
            } else {
                $updates['avatar_preset'] = $avatarPreset;
                $updates['profile_picture'] = null;
            }
        }
    }

    if ($profileErrors) {
        return;
    }

    if (!$updates) {
        if ($action === 'update_cover_style') {
            $profileErrors[] = 'Please select a gradient or upload an image.';
        } elseif ($action === 'update_avatar_style') {
            $profileErrors[] = 'Please select an avatar or upload an image.';
        } else {
            $profileErrors[] = 'Please choose at least one image to upload.';
        }
        return;
    }

    try {
        $setParts = [];
        $params = [];

        foreach (['profile_picture', 'cover_photo', 'cover_gradient', 'avatar_preset'] as $field) {
            if (array_key_exists($field, $updates)) {
                $setParts[] = $field . ' = ?';
                $params[] = $updates[$field];
            }
        }

        if (!$setParts) {
            $profileErrors[] = 'No media changes to save.';
            return;
        }

        $setParts[] = 'updated_at = NOW()';
        $sql = 'UPDATE student SET ' . implode(', ', $setParts) . ' WHERE student_id = ?';
        $params[] = $userId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        $profileErrors[] = 'Media field is not ready in database. Please run migration.';
        return;
    }

    if ($action === 'update_cover_style') {
        $profileSuccess = 'Cover updated successfully.';
    } elseif ($action === 'update_avatar_style') {
        $profileSuccess = 'Profile picture updated successfully.';
    } else {
        $profileSuccess = 'Profile media updated successfully.';
    }
}
