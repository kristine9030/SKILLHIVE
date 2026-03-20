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

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($requestMethod === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action !== 'toggle_requirement') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    $studentId = (int)($_POST['student_id'] ?? 0);
    $internshipIdRaw = trim((string)($_POST['internship_id'] ?? ''));
    $internshipId = $internshipIdRaw === '' ? null : (int)$internshipIdRaw;
    $requirementId = (int)($_POST['requirement_id'] ?? 0);
    $isChecked = ((string)($_POST['is_checked'] ?? '0')) === '1';

    if ($studentId <= 0 || $requirementId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid requirement toggle payload']);
        exit;
    }

    try {
        $data = adviser_students_toggle_requirement_submission(
            $pdo,
            $adviserId,
            $studentId,
            $internshipId,
            $requirementId,
            $isChecked
        );

        echo json_encode([
            'success' => true,
            'summary' => $data['summary'],
            'phases' => $data['phases'],
            'can_edit' => (bool)($data['can_edit'] ?? false),
            'internship_id_context' => $data['internship_id_context'] ?? null,
        ]);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update requirement status']);
    }
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
        'can_edit' => (bool)($data['can_edit'] ?? false),
        'internship_id_context' => $data['internship_id_context'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load requirements']);
}
