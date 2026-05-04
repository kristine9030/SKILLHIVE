<?php
/**
 * pages/student/requirements/requirements_upload.php
 * Handles AJAX file upload for student OJT requirement submissions.
 *
 * File content is stored directly in the database as a LONGBLOB —
 * no local filesystem path is saved, so files are always retrievable
 * regardless of server storage layout.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

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

// ─── Check existing submission (block re-upload if Approved) ─────────────────
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

// Allowed MIME types — verified server-side via finfo, not browser-supplied
$allowedMime = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMime, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, DOC, DOCX.']);
    exit;
}

// Extension whitelist (secondary check)
$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid file extension.']);
    exit;
}

// ─── Read file binary into memory ─────────────────────────────────────────────
$fileData = file_get_contents($file['tmp_name']);
if ($fileData === false) {
    error_log("requirements_upload: file_get_contents failed for student $studentId req $reqId");
    echo json_encode(['ok' => false, 'error' => 'Failed to read uploaded file. Please try again.']);
    exit;
}

// Sanitise the original filename for storage (strip path components)
$originalName = basename($file['name']);
$fileSize     = $file['size'];

// ─── Persist to database ──────────────────────────────────────────────────────
try {
    if ($existing) {
        // UPDATE existing row — replace binary, reset to Submitted, clear old review
        $upd = $pdo->prepare(
            "UPDATE student_requirement
             SET file_data    = :data,
                 file_name    = :name,
                 file_mime    = :mime,
                 file_size    = :size,
                 status       = 'Submitted',
                 submitted_at = NOW(),
                 reviewed_at  = NULL,
                 reviewed_by  = NULL,
                 notes        = NULL
             WHERE student_id     = :sid
               AND requirement_id = :rid"
        );
        $upd->bindValue(':data', $fileData,     PDO::PARAM_LOB);
        $upd->bindValue(':name', $originalName, PDO::PARAM_STR);
        $upd->bindValue(':mime', $mimeType,     PDO::PARAM_STR);
        $upd->bindValue(':size', $fileSize,     PDO::PARAM_INT);
        $upd->bindValue(':sid',  $studentId,    PDO::PARAM_INT);
        $upd->bindValue(':rid',  $reqId,        PDO::PARAM_INT);
        $upd->execute();
    } else {
        // INSERT new row
        $ins = $pdo->prepare(
            "INSERT INTO student_requirement
               (student_id, internship_id, requirement_id,
                status, file_data, file_name, file_mime, file_size, submitted_at)
             VALUES
               (:sid, :iid, :rid,
                'Submitted', :data, :name, :mime, :size, NOW())"
        );
        $ins->bindValue(':sid',  $studentId,    PDO::PARAM_INT);
        $ins->bindValue(':iid',  $internshipId, PDO::PARAM_INT);
        $ins->bindValue(':rid',  $reqId,        PDO::PARAM_INT);
        $ins->bindValue(':data', $fileData,     PDO::PARAM_LOB);
        $ins->bindValue(':name', $originalName, PDO::PARAM_STR);
        $ins->bindValue(':mime', $mimeType,     PDO::PARAM_STR);
        $ins->bindValue(':size', $fileSize,     PDO::PARAM_INT);
        $ins->execute();
    }
} catch (PDOException $e) {
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