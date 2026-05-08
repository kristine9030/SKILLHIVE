<?php
/**
 * Serves the uploaded file binary for a student_requirement record.
 * Accessible only to the adviser assigned to the student.
 *
 * Query params:
 *   req_submission_id  – integer, required
 *   action             – 'view' (inline) | 'download' (attachment, default)
 */

require_once __DIR__ . '/../../../backend/db_connect.php';

$role      = (string)($_SESSION['role']    ?? '');
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'adviser' || $adviserId <= 0) {
    http_response_code(403);
    exit('Unauthorized');
}

$submissionId = (int)($_GET['req_submission_id'] ?? 0);
if ($submissionId <= 0) {
    http_response_code(422);
    exit('Invalid submission id');
}

$action = (string)($_GET['action'] ?? 'download');

try {
    // Verify the adviser is assigned to the student who owns this submission.
    $stmt = $pdo->prepare(
        'SELECT sr.file_data, sr.file_name, sr.file_mime, sr.file_size, sr.student_id
         FROM student_requirement sr
         WHERE sr.req_submission_id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $submissionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['file_data'] === null) {
        http_response_code(404);
        exit('File not found');
    }

    // Auth: adviser must be actively assigned to this student.
    $authStmt = $pdo->prepare(
        'SELECT 1
         FROM adviser_assignment
         WHERE adviser_id  = :adviser_id
           AND student_id  = :student_id
           AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"
         LIMIT 1'
    );
    $authStmt->execute([
        ':adviser_id' => $adviserId,
        ':student_id' => (int)$row['student_id'],
    ]);
    if (!$authStmt->fetchColumn()) {
        http_response_code(403);
        exit('Unauthorized');
    }

    $fileName = $row['file_name'] ?: 'requirement_file';
    $mime     = $row['file_mime'] ?: 'application/octet-stream';
    $size     = (int)($row['file_size'] ?? strlen($row['file_data']));

    // Sanitise filename for Content-Disposition header.
    $safeFileName = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $fileName);

    $disposition = ($action === 'view') ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    echo $row['file_data'];
} catch (Throwable $e) {
    http_response_code(500);
    exit('Server error');
}