<?php
/**
 * Purpose: Fetches upcoming interviews for the dashboard timeline.
 * Tables/columns used: interview(application_id, interview_date, interview_status), application(application_id, internship_id, student_id), internship(internship_id, employer_id), student(student_id, first_name, last_name).
 */

if (!function_exists('dashboard_get_upcoming_interviews')) {
    function dashboard_get_upcoming_interviews(PDO $pdo, int $employerId, int $limit = 2): array
    {
        $safeLimit = max(1, min(20, $limit));

        $sql = '
            SELECT
                iv.interview_date,
                iv.interview_status,
                s.first_name,
                s.last_name
             FROM interview iv
             INNER JOIN application a ON a.application_id = iv.application_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN student s ON s.student_id = a.student_id
             WHERE i.employer_id = :employer_id
               AND iv.interview_date >= NOW()
             ORDER BY iv.interview_date ASC
             LIMIT ' . $safeLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
