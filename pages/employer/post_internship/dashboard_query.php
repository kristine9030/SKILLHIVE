<?php
/**
 * Purpose: Computes compact employer dashboard summary metrics for modules that need posting, applicant, interview, and hired counts.
 * Tables/columns used: internship(employer_id, internship_id, status), application(internship_id, status), interview(application_id).
 */

if (!function_exists('getEmployerDashboardSummary')) {
    function getEmployerDashboardSummary(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM internship i WHERE i.employer_id = :employer_id AND LOWER(COALESCE(i.status, "")) = "open") AS active_postings,
                (SELECT COUNT(*) FROM application a INNER JOIN internship i ON i.internship_id = a.internship_id WHERE i.employer_id = :employer_id) AS total_applicants,
                (SELECT COUNT(*) FROM interview iv INNER JOIN application a ON a.application_id = iv.application_id INNER JOIN internship i ON i.internship_id = a.internship_id WHERE i.employer_id = :employer_id) AS interviews,
                (SELECT COUNT(*) FROM application a INNER JOIN internship i ON i.internship_id = a.internship_id WHERE i.employer_id = :employer_id AND LOWER(COALESCE(a.status, "")) IN ("accepted", "hired")) AS hired'
        );
        $stmt->execute([':employer_id' => $employerId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'active_postings' => (int)($row['active_postings'] ?? 0),
            'total_applicants' => (int)($row['total_applicants'] ?? 0),
            'interviews' => (int)($row['interviews'] ?? 0),
            'hired' => (int)($row['hired'] ?? 0),
        ];
    }
}
