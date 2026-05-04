<?php
/**
 * pages/student/requirements/requirements_view.php
 *
 * Secure file-serving proxy for student OJT requirement submissions.
 * Reads the file binary directly from the `student_requirement` table
 * (file_data LONGBLOB) — no filesystem path is involved.
 *
 * Usage:
 *   requirements_view.php?id=<req_submission_id>            → inline preview
 *   requirements_view.php?id=<req_submission_id>&download=1 → force download
 *
 * Access rules:
 *   • Must be logged in.
 *   • Students may only access their OWN submissions.
 *   • Advisers / admins / coordinators may access any submission.
 */

require_once __DIR__ . '/../../../backend/db_connect.php';

// ─── Bootstrap ────────────────────────────────────────────────────────────────
if (ob_get_level()) {
    ob_end_clean();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorised.');
}

$currentUserId = (int) $_SESSION['user_id'];
$currentRole   = $_SESSION['role'] ?? '';
$submissionId  = (int) ($_GET['id'] ?? 0);
$forceDownload = !empty($_GET['download']);

if ($submissionId <= 0) {
    http_response_code(400);
    exit('Invalid submission ID.');
}

// ─── Fetch submission record (binary + metadata) ──────────────────────────────
$stmt = $pdo->prepare(
    "SELECT sr.req_submission_id,
            sr.student_id,
            sr.file_data,
            sr.file_name,
            sr.file_mime,
            sr.file_size,
            r.name AS requirement_name
     FROM   student_requirement sr
     JOIN   requirement r ON r.requirement_id = sr.requirement_id
     WHERE  sr.req_submission_id = ?
     LIMIT  1"
);
$stmt->execute([$submissionId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Submission not found.');
}

// ─── Authorisation ────────────────────────────────────────────────────────────
$isPrivileged = in_array($currentRole, ['adviser', 'admin', 'coordinator'], true);

if (!$isPrivileged && (int) $row['student_id'] !== $currentUserId) {
    http_response_code(403);
    exit('Access denied.');
}

// ─── Validate file data ───────────────────────────────────────────────────────
// file_data may be a stream resource (some PDO drivers) or a plain string.
$fileData = is_resource($row['file_data'])
    ? stream_get_contents($row['file_data'])
    : $row['file_data'];

if (empty($fileData)) {
    http_response_code(404);
    exit('No file has been uploaded for this submission yet.');
}

// ─── Build safe download filename ────────────────────────────────────────────
$ext          = strtolower(pathinfo($row['file_name'] ?? '', PATHINFO_EXTENSION));
$safeReqName  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $row['requirement_name']);
$downloadName = $safeReqName . '_req_' . $submissionId . ($ext ? '.' . $ext : '');

// Use stored MIME; fall back to octet-stream if missing
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->buffer($fileData);

$mimeType = $detectedMime ?: 'application/octet-stream';
$fileSize = $row['file_size'] ?: strlen($fileData);

// ─── Send headers ─────────────────────────────────────────────────────────────
header('Content-Type: '   . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$disposition = $forceDownload ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');

// ─── Stream binary to browser ─────────────────────────────────────────────────
echo $fileData;
exit;