<?php
/**
 * Purpose: JSON API endpoint for real-time application status polling and interview details.
 * Used by: Student applications page for live updates via fetch().
 */

require_once __DIR__ . '/../../../backend/db_connect.php';

header('Content-Type: application/json');

$action = trim((string) ($_GET['action'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($action === 'fetch_applications') {
    try {
        $sql = '
            SELECT
                a.application_id,
                a.application_date,
                a.status,
                a.compatibility_score,
                a.updated_at,
                i.internship_id,
                i.title AS internship_title,
                COALESCE(i.posted_at, i.created_at) AS internship_posted_at,
                e.company_name,
                e.company_badge_status,
                evt.interview_date,
                evt.interview_time,
                evt.interview_mode,
                evt.meeting_link,
                evt.venue,
                evt.interview_status
            FROM application a
            INNER JOIN internship i ON i.internship_id = a.internship_id
            INNER JOIN employer e ON e.employer_id = i.employer_id
            LEFT JOIN interview evt ON evt.application_id = a.application_id
            WHERE a.student_id = ?
            ORDER BY a.application_date DESC, a.application_id DESC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'ok' => true,
            'applications' => array_map(static function (array $app): array {
                return [
                    'application_id' => (int) $app['application_id'],
                    'status' => (string) $app['status'],
                    'updated_at' => (string) $app['updated_at'],
                    'compatibility_score' => (int) $app['compatibility_score'],
                    'internship_title' => (string) $app['internship_title'],
                    'company_name' => (string) $app['company_name'],
                    'interview_date' => $app['interview_date'] ? (string) $app['interview_date'] : null,
                    'interview_time' => $app['interview_time'] ? (string) $app['interview_time'] : null,
                    'interview_mode' => $app['interview_mode'] ? (string) $app['interview_mode'] : null,
                    'meeting_link' => $app['meeting_link'] ? (string) $app['meeting_link'] : null,
                    'venue' => $app['venue'] ? (string) $app['venue'] : null,
                    'interview_status' => $app['interview_status'] ? (string) $app['interview_status'] : null,
                ];
            }, $applications),
        ];

        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
}
