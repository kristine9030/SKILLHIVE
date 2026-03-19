<?php
/**
 * Purpose: Loads pending endorsement preview cards for the current adviser.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, created_at, reviewed_at), application(application_id, student_id, internship_id), internship(internship_id, title, employer_id), employer(employer_id, company_name), student(student_id, first_name, last_name).
 */

if (!function_exists('adviser_dashboard_get_pending_endorsements')) {
    function adviser_dashboard_get_pending_endorsements(PDO $pdo, int $adviserId, int $limit = 2): array
    {
        $safeLimit = max(1, min(10, $limit));

        $sql = '
            SELECT
                e.endorsement_id,
                e.status,
                s.first_name,
                s.last_name,
                emp.company_name,
                i.title AS internship_title
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN employer emp ON emp.employer_id = i.employer_id
             INNER JOIN student s ON s.student_id = a.student_id
             WHERE e.adviser_id = :adviser_id
               AND LOWER(COALESCE(e.status, \'\')) IN (\'pending\', \'reviewing\', \'for review\', \'submitted\')
             ORDER BY COALESCE(e.created_at, e.reviewed_at) DESC, e.endorsement_id DESC
             LIMIT ' . $safeLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':adviser_id' => $adviserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}