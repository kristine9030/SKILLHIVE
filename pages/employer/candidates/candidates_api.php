<?php
/**
 * Purpose: JSON API endpoint for employer real-time candidate management.
 * Handles: fetching candidates, updating status, scheduling interviews.
 */

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('candidates_api_respond')) {
    function candidates_api_respond(array $payload, int $statusCode = 200): void
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
require_once __DIR__ . '/data.php';

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$employerId = (int) ($_SESSION['employer_id'] ?? $_SESSION['user_id'] ?? 0);

if ($employerId <= 0) {
    candidates_api_respond(['ok' => false, 'error' => 'Unauthorized'], 401);
}

try {
    if ($action === 'fetch_candidates') {
        $positionFilter = trim((string) ($_GET['position'] ?? ''));
        $statusFilter = trim((string) ($_GET['status'] ?? ''));

        $sql = '
            SELECT
                a.application_id,
                a.internship_id,
                a.student_id,
                a.status,
                a.compatibility_score,
                a.application_date,
                a.updated_at,
                i.title AS position_title,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.internship_readiness_score,
                evt.interview_date,
                evt.interview_time,
                evt.interview_mode
            FROM application a
            INNER JOIN internship i ON i.internship_id = a.internship_id
            INNER JOIN student s ON s.student_id = a.student_id
            LEFT JOIN interview evt ON evt.application_id = a.application_id
            WHERE i.employer_id = ?
        ';

        $params = [$employerId];

        if ($positionFilter !== '') {
            $sql .= ' AND i.internship_id = ?';
            $params[] = (int) $positionFilter;
        }

        if ($statusFilter !== '') {
            $sql .= ' AND a.status = ?';
            $params[] = $statusFilter;
        }

        $sql .= ' ORDER BY a.updated_at DESC, a.application_id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'ok' => true,
            'candidates' => array_map(static function (array $c): array {
                return [
                    'application_id' => (int) $c['application_id'],
                    'student_id' => (int) $c['student_id'],
                    'internship_id' => (int) $c['internship_id'],
                    'status' => (string) $c['status'],
                    'updated_at' => (string) $c['updated_at'],
                    'compatibility_score' => (int) $c['compatibility_score'],
                    'position_title' => (string) $c['position_title'],
                    'student_name' => trim((string) $c['first_name'] . ' ' . $c['last_name']),
                    'program' => (string) $c['program'],
                    'year_level' => (string) $c['year_level'],
                    'readiness_score' => (int) ($c['internship_readiness_score'] ?? 0),
                    'interview_date' => $c['interview_date'] ? (string) $c['interview_date'] : null,
                    'interview_time' => $c['interview_time'] ? (string) $c['interview_time'] : null,
                    'interview_mode' => $c['interview_mode'] ? (string) $c['interview_mode'] : null,
                    'application_date' => (string) $c['application_date'],
                ];
            }, $candidates),
        ];

        candidates_api_respond($result);
    } elseif ($action === 'update_status') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $allowedStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];

        if ($applicationId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            candidates_api_respond(['ok' => false, 'error' => 'Invalid request'], 400);
        }

        $result = candidates_update_application_status($pdo, $employerId, $applicationId, $newStatus);
        if (!empty($result['success'])) {
            candidates_api_respond(['ok' => true, 'message' => 'Status updated']);
        }

        candidates_api_respond([
            'ok' => false,
            'error' => (string) ($result['error'] ?? 'Unable to update candidate status.'),
        ], 422);
    } elseif ($action === 'schedule_interview') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
        $interviewTime = trim((string) ($_POST['interview_time'] ?? ''));
        $interviewMode = trim((string) ($_POST['interview_mode'] ?? 'Online'));
        $meetingLink = trim((string) ($_POST['meeting_link'] ?? ''));

        if ($applicationId <= 0 || !$interviewDate || !$interviewTime) {
            candidates_api_respond(['ok' => false, 'error' => 'Missing required fields'], 400);
        }

        $result = candidates_schedule_interview($pdo, $employerId, $applicationId, [
            'interview_date' => $interviewDate . ' ' . $interviewTime,
            'interview_mode' => $interviewMode,
            'meeting_link' => $meetingLink,
        ]);

        if (!empty($result['success'])) {
            candidates_api_respond(['ok' => true, 'message' => 'Interview scheduled successfully']);
        } else {
            candidates_api_respond(['ok' => false, 'error' => (string) ($result['error'] ?? 'Unable to schedule interview.')], 422);
        }
    } else {
        candidates_api_respond(['ok' => false, 'error' => 'Invalid action'], 400);
    }
} catch (Throwable $e) {
    candidates_api_respond(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
