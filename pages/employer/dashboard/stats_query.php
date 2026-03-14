<?php
/**
 * Purpose: Computes top-line dashboard stats such as active postings, applicant counts, interview count, and hired count.
 * Tables/columns used: internship(employer_id, internship_id, status), application(application_id, internship_id, status, application_date), interview(application_id).
 */

if (!function_exists('dashboard_get_stats')) {
    function dashboard_get_stats(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*)
                 FROM internship i
                 WHERE i.employer_id = :employer_id_1
                                     AND LOWER(COALESCE(i.status, \'\')) = \'open\') AS active_postings,
                (SELECT COUNT(*)
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                 WHERE i.employer_id = :employer_id_2) AS total_applicants,
                (SELECT COUNT(*)
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                 WHERE i.employer_id = :employer_id_3
                   AND YEARWEEK(a.application_date, 1) = YEARWEEK(CURDATE(), 1)) AS week_applicants,
                (SELECT COUNT(*)
                 FROM interview iv
                 INNER JOIN application a ON a.application_id = iv.application_id
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                 WHERE i.employer_id = :employer_id_4) AS interviews,
                (SELECT COUNT(*)
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                 WHERE i.employer_id = :employer_id_5
                                     AND LOWER(COALESCE(a.status, \'\')) IN (\'accepted\', \'hired\')) AS hired'
        );
        $stmt->execute([
            ':employer_id_1' => $employerId,
            ':employer_id_2' => $employerId,
            ':employer_id_3' => $employerId,
            ':employer_id_4' => $employerId,
            ':employer_id_5' => $employerId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'active_postings' => (int)($row['active_postings'] ?? 0),
            'total_applicants' => (int)($row['total_applicants'] ?? 0),
            'week_applicants' => (int)($row['week_applicants'] ?? 0),
            'interviews' => (int)($row['interviews'] ?? 0),
            'hired' => (int)($row['hired'] ?? 0),
        ];
    }
}
