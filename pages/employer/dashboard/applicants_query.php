<?php
/**
 * Purpose: Fetches the most recent applicants for dashboard preview rows.
 * Tables/columns used: application(application_id, internship_id, student_id, status, compatibility_score, application_date), internship(internship_id, employer_id, title), student(student_id, first_name, last_name).
 */

if (!function_exists('dashboard_get_recent_applicants')) {
    function dashboard_get_recent_applicants(PDO $pdo, int $employerId, int $limit = 3): array
    {
        $safeLimit = max(1, min(20, $limit));

        $sql = '
            SELECT
                a.application_id,
                     a.internship_id,
                a.status,
                a.compatibility_score,
                a.application_date,
                s.first_name,
                s.last_name,
                i.title AS internship_title
             FROM application a
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN student s ON s.student_id = a.student_id
             WHERE i.employer_id = :employer_id
             ORDER BY a.application_date DESC, a.application_id DESC
             LIMIT ' . $safeLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
