<?php
/**
 * pages/student/requirements/requirements_upload.php
 * Handles AJAX file upload for student OJT requirement submissions.
 *
 * Expects POST:
 *   action         => 'upload'
 *   requirement_id => int
 *   req_file       => uploaded file
 *
 * Returns JSON: { ok: true, message: '...' }
 *            or { ok: false, error: '...' }
 */

require_once __DIR__ . '/../../../backend/db_connect.php';

// ─── Bootstrap ───────────────────────────────────────────────────────────────
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Authenticated student only
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorised. Please log in again.']);
    exit;
}

$studentId = (int) $_SESSION['user_id'];
$action    = trim($_POST['action'] ?? '');
$reqId     = (int) ($_POST['requirement_id'] ?? 0);

// ─── Validate action & IDs ────────────────────────────────────────────────────
if ($action !== 'upload') {
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

if ($reqId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid requirement ID.']);
    exit;
}

// ─── Verify requirement exists and is applicable to students ─────────────────
$chkStmt = $pdo->prepare(
    "SELECT requirement_id FROM requirement
     WHERE requirement_id = ? AND applicable_to IN ('Student', 'Both')"
);
$chkStmt->execute([$reqId]);
if (!$chkStmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Requirement not found.']);
    exit;
}

// ─── Check existing submission status (block re-upload if Approved) ───────────
$existStmt = $pdo->prepare(
    "SELECT req_submission_id, status, internship_id
     FROM student_requirement
     WHERE student_id = ? AND requirement_id = ?
     LIMIT 1"
);
$existStmt->execute([$studentId, $reqId]);
$existing = $existStmt->fetch(PDO::FETCH_ASSOC);

if ($existing && $existing['status'] === 'Approved') {
    echo json_encode(['ok' => false, 'error' => 'This requirement is already approved and cannot be replaced.']);
    exit;
}

// ─── Resolve student's active internship_id (nullable) ───────────────────────
// Re-use existing internship_id if already recorded; otherwise look it up.
$internshipId = null;
if ($existing && $existing['internship_id']) {
    $internshipId = (int) $existing['internship_id'];
} else {
    $ojtStmt = $pdo->prepare(
        "SELECT internship_id FROM ojt_record
         WHERE student_id = ? AND completion_status = 'Ongoing'
         ORDER BY created_at DESC LIMIT 1"
    );
    $ojtStmt->execute([$studentId]);
    $ojtRow = $ojtStmt->fetch(PDO::FETCH_ASSOC);
    if ($ojtRow) {
        $internshipId = (int) $ojtRow['internship_id'];
    }
}

// ─── File validation ──────────────────────────────────────────────────────────
if (empty($_FILES['req_file']) || $_FILES['req_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory missing.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    $code = $_FILES['req_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg  = $uploadErrors[$code] ?? 'Unknown upload error.';
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$file     = $_FILES['req_file'];
$maxBytes = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['ok' => false, 'error' => 'File too large. Maximum size is 10 MB.']);
    exit;
}

// Allowed MIME types
$allowedMime = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

// Use finfo for reliable MIME detection (not just the browser-supplied type)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMime, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX.']);
    exit;
}

// Extension whitelist (secondary check)
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid file extension.']);
    exit;
}

// ─── Save file to disk ────────────────────────────────────────────────────────
// Walks up from this file's location to the project root (SkillHive/),
// then into assets/backend/uploads/requirements/<studentId>/
// __DIR__ = <root>/pages/student/requirements  →  ../../.. = <root>
$projectRoot = realpath(__DIR__ . '/../../..') ?: dirname(dirname(dirname(__DIR__)));

// ── Adjust this constant if your uploads live elsewhere ──────────────────────
define('UPLOAD_BASE_DIR', $projectRoot . '/assets/backend/uploads/requirements');

$studentDir = UPLOAD_BASE_DIR . '/' . $studentId;

if (!is_dir($studentDir)) {
    // Create the full path including any missing parent directories.
    // Uses the current process umask; 0775 is the requested mode before masking.
    $created = @mkdir($studentDir, 0775, true);
    if (!$created && !is_dir($studentDir)) {          // guard against race condition
        $err = error_get_last();
        error_log(sprintf(
            'requirements_upload: mkdir failed for "%s" — %s',
            $studentDir,
            $err['message'] ?? 'unknown reason'
        ));
        echo json_encode([
            'ok'    => false,
            'error' => 'Server storage error: could not create upload directory. '
                     . 'Check that the web server has write permission on: '
                     . dirname(UPLOAD_BASE_DIR),
        ]);
        exit;
    }
}

// Build a unique filename: req_{reqId}_{timestamp}_{random}.{ext}
$uniqueName = 'req_' . $reqId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath   = $studentDir . '/' . $uniqueName;

// The relative path stored in DB (relative to /assets/backend/uploads/)
$dbFilePath = 'requirements/' . $studentId . '/' . $uniqueName;

// Delete old file if replacing
// $dbFilePath values are relative to assets/backend/uploads/, e.g.
// "requirements/6/req_1_1234567890_ab12cd34.pdf"
if ($existing && !empty($existing['file_path'])) {
    $oldFile = $projectRoot . '/assets/backend/uploads/' . $existing['file_path'];
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log("requirements_upload: move_uploaded_file failed for student $studentId req $reqId");
    echo json_encode(['ok' => false, 'error' => 'Failed to save file. Please try again.']);
    exit;
}

// ─── Persist to database ──────────────────────────────────────────────────────
try {
    if ($existing) {
        // UPDATE existing row — reset to Submitted, clear old review data
        $upd = $pdo->prepare(
            "UPDATE student_requirement
             SET file_path    = ?,
                 status       = 'Submitted',
                 submitted_at = NOW(),
                 reviewed_at  = NULL,
                 reviewed_by  = NULL,
                 notes        = NULL
             WHERE student_id     = ?
               AND requirement_id = ?"
        );
        $upd->execute([$dbFilePath, $studentId, $reqId]);
    } else {
        // INSERT new row
        $ins = $pdo->prepare(
            "INSERT INTO student_requirement
               (student_id, internship_id, requirement_id, status, file_path, submitted_at)
             VALUES
               (?, ?, ?, 'Submitted', ?, NOW())"
        );
        $ins->execute([$studentId, $internshipId, $reqId, $dbFilePath]);
    }
} catch (PDOException $e) {
    // Roll back the uploaded file so disk and DB stay in sync
    @unlink($destPath);
    error_log("requirements_upload DB error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

// ─── Success ──────────────────────────────────────────────────────────────────
echo json_encode([
    'ok'      => true,
    'message' => 'Document submitted successfully!',
]);
exit;