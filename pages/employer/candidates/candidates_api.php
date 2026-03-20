<?php
/**
 * Purpose: JSON API endpoint for employer real-time candidate management.
 * Handles: fetching candidates, updating status, scheduling interviews.
 */

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/candidates/data.php';

header('Content-Type: application/json');

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$employerId = (int) ($_SESSION['employer_id'] ?? $_SESSION['user_id'] ?? 0);

if ($employerId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
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

        echo json_encode($result);
    } elseif ($action === 'update_status') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $allowedStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];

        if ($applicationId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }

        // Verify employer owns this application
        $stmt = $pdo->prepare('
            SELECT 1 FROM application a
            INNER JOIN internship i ON i.internship_id = a.internship_id
            WHERE a.application_id = ? AND i.employer_id = ?
        ');
        $stmt->execute([$applicationId, $employerId]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // Update status
        $stmt = $pdo->prepare('
            UPDATE application
            SET status = ?, updated_at = NOW()
            WHERE application_id = ?
        ');
        $stmt->execute([$newStatus, $applicationId]);

        echo json_encode(['ok' => true, 'message' => 'Status updated']);
    } elseif ($action === 'schedule_interview') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
        $interviewTime = trim((string) ($_POST['interview_time'] ?? ''));
        $interviewMode = trim((string) ($_POST['interview_mode'] ?? 'Online'));
        $meetingLink = trim((string) ($_POST['meeting_link'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));

        if ($applicationId <= 0 || !$interviewDate || !$interviewTime) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Verify employer owns this application
        $stmt = $pdo->prepare('
            SELECT a.application_id FROM application a
            INNER JOIN internship i ON i.internship_id = a.internship_id
            WHERE a.application_id = ? AND i.employer_id = ?
        ');
        $stmt->execute([$applicationId, $employerId]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // Check if interview record exists
        $stmt = $pdo->prepare('SELECT interview_id FROM interview WHERE application_id = ?');
        $stmt->execute([$applicationId]);
        $existingInterview = $stmt->fetch();

        $pdo->beginTransaction();

        try {
            if ($existingInterview) {
                // Update
                $stmt = $pdo->prepare('
                    UPDATE interview
                    SET interview_date = ?, interview_time = ?, interview_mode = ?,
                        meeting_link = ?, venue = ?
                    WHERE application_id = ?
                ');
                $stmt->execute([$interviewDate, $interviewTime, $interviewMode, $meetingLink ?: null, $venue ?: null, $applicationId]);
            } else {
                // Create new
                $stmt = $pdo->prepare('
                    INSERT INTO interview
                    (application_id, interview_date, interview_time, interview_mode, meeting_link, venue, interview_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$applicationId, $interviewDate, $interviewTime, $interviewMode, $meetingLink ?: null, $venue ?: null, 'Scheduled']);
            }

            // Update application status if needed
            $stmt = $pdo->prepare('
                UPDATE application
                SET status = ?, updated_at = NOW()
                WHERE application_id = ? AND status != ?
            ');
            $stmt->execute(['Interview Scheduled', $applicationId, 'Interview Scheduled']);

            $pdo->commit();
            echo json_encode(['ok' => true, 'message' => 'Interview scheduled successfully']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Database error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
