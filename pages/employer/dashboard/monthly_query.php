<?php
/**
 * Purpose: Computes current-month dashboard metrics and acceptance rate.
 * Tables/columns used: application(internship_id, status, application_date, updated_at), internship(internship_id, employer_id), interview(application_id, interview_date).
 */

if (!function_exists('dashboard_get_monthly_metrics')) {
    function dashboard_get_monthly_metrics(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*)
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                                 WHERE i.employer_id = :employer_id_1
                   AND YEAR(a.application_date) = YEAR(CURDATE())
                   AND MONTH(a.application_date) = MONTH(CURDATE())) AS applications_received,
                (SELECT COUNT(*)
                 FROM interview iv
                 INNER JOIN application a ON a.application_id = iv.application_id
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                                 WHERE i.employer_id = :employer_id_2
                   AND YEAR(iv.interview_date) = YEAR(CURDATE())
                   AND MONTH(iv.interview_date) = MONTH(CURDATE())) AS interviews_conducted,
                (SELECT COUNT(*)
                 FROM application a
                 INNER JOIN internship i ON i.internship_id = a.internship_id
                                 WHERE i.employer_id = :employer_id_3
                                     AND LOWER(COALESCE(a.status, \'\')) IN (\'accepted\', \'hired\')
                         AND YEAR(a.application_date) = YEAR(CURDATE())
                         AND MONTH(a.application_date) = MONTH(CURDATE())) AS offers_extended'
        );
                $stmt->execute([
                        ':employer_id_1' => $employerId,
                        ':employer_id_2' => $employerId,
                        ':employer_id_3' => $employerId,
                ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $applicationsReceived = (int)($row['applications_received'] ?? 0);
        $offersExtended = (int)($row['offers_extended'] ?? 0);
        $acceptanceRate = $applicationsReceived > 0
            ? (int)round(($offersExtended / $applicationsReceived) * 100)
            : 0;

        return [
            'applications_received' => $applicationsReceived,
            'interviews_conducted' => (int)($row['interviews_conducted'] ?? 0),
            'offers_extended' => $offersExtended,
            'acceptance_rate' => $acceptanceRate,
        ];
    }
}
