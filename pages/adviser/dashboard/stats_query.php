<?php
/**
 * Purpose: Computes top-line adviser dashboard statistics.
 * Tables/columns used: adviser_assignment(adviser_id, student_id), endorsement(adviser_id, status), ojt_record(student_id, internship_id), internship(internship_id, employer_id), employer(employer_id).
 */

if (!function_exists('adviser_dashboard_get_stats')) {
    function adviser_dashboard_get_stats(PDO $pdo, int $adviserId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(DISTINCT aa.student_id)
                 FROM adviser_assignment aa
                 WHERE aa.adviser_id = :students_adviser_id) AS my_students,
                (SELECT COUNT(*)
                 FROM endorsement e
                 WHERE e.adviser_id = :endorsed_adviser_id
                                     AND LOWER(COALESCE(e.status, \'\')) IN (\'endorsed\', \'approved\')) AS endorsed,
                (SELECT COUNT(*)
                 FROM endorsement e
                 WHERE e.adviser_id = :pending_adviser_id
                                     AND LOWER(COALESCE(e.status, \'\')) IN (\'pending\', \'reviewing\', \'for review\', \'submitted\')) AS pending_review,
                (SELECT COUNT(DISTINCT i.employer_id)
                 FROM (SELECT DISTINCT student_id FROM adviser_assignment WHERE adviser_id = :partners_adviser_id) aa
                 INNER JOIN ojt_record o ON o.student_id = aa.student_id
                 INNER JOIN internship i ON i.internship_id = o.internship_id
                 ) AS partner_companies'
        );
        $stmt->execute([
            ':students_adviser_id' => $adviserId,
            ':endorsed_adviser_id' => $adviserId,
            ':pending_adviser_id' => $adviserId,
            ':partners_adviser_id' => $adviserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'my_students' => (int)($row['my_students'] ?? 0),
            'endorsed' => (int)($row['endorsed'] ?? 0),
            'pending_review' => (int)($row['pending_review'] ?? 0),
            'partner_companies' => (int)($row['partner_companies'] ?? 0),
        ];
    }
}