<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/requirement_details_query.php';

header('Content-Type: application/json; charset=utf-8');

$role = (string)($_SESSION['role'] ?? '');
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'adviser' || $adviserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = (int)($_GET['student_id'] ?? 0);
$internshipIdRaw = trim((string)($_GET['internship_id'] ?? ''));
$internshipId = $internshipIdRaw === '' ? null : (int)$internshipIdRaw;

if ($studentId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid student id']);
    exit;
}

try {
    $data = adviser_students_get_requirements_modal_data($pdo, $adviserId, $studentId, $internshipId);
    echo json_encode([
        'success' => true,
        'summary' => $data['summary'],
        'phases' => $data['phases'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load requirements']);
}
