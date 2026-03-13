<?php
function profile_handle_resume(PDO $pdo, int $userId, array &$profileErrors, string &$profileSuccess): void
{
    if (($_POST['action'] ?? '') !== 'upload_resume') {
        return;
    }

    if (!isset($_FILES['resume']) || !is_array($_FILES['resume'])) {
        $profileErrors[] = 'Please choose a resume file.';
        return;
    }

    $file = $_FILES['resume'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $profileErrors[] = 'Please choose a resume file.';
        return;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $profileErrors[] = 'Resume upload failed. Please try again.';
        return;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx'];

    if (!in_array($ext, $allowed, true)) {
        $profileErrors[] = 'Only PDF, DOC, and DOCX files are allowed.';
    }

    if (($file['size'] ?? 0) > (5 * 1024 * 1024)) {
        $profileErrors[] = 'Resume must be 5MB or smaller.';
    }

    if ($profileErrors) {
        return;
    }

    $uploadDir = __DIR__ . '/../../../assets/backend/uploads/resumes';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'resume_' . (int) $userId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $profileErrors[] = 'Unable to save the uploaded file.';
        return;
    }

    $stmt = $pdo->prepare('UPDATE student SET resume_file = ?, updated_at = NOW() WHERE student_id = ?');
    $stmt->execute([$filename, $userId]);
    $profileSuccess = 'Resume uploaded successfully.';
}
