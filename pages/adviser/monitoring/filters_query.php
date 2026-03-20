<?php
/**
 * Purpose: Loads filter options for adviser monitoring page.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), ojt_record(student_id, internship_id), internship(internship_id, employer_id), employer(employer_id, company_name).
 */

if (!function_exists('adviser_monitoring_get_filter_options')) {
    function adviser_monitoring_get_filter_options(PDO $pdo, int $adviserId): array
    {
        $companyStmt = $pdo->prepare(
            'SELECT DISTINCT COALESCE(NULLIF(TRIM(e.company_name), ""), "No Company") AS company_name
             FROM adviser_assignment aa
             LEFT JOIN ojt_record o ON o.student_id = aa.student_id
             LEFT JOIN internship i ON i.internship_id = o.internship_id
             LEFT JOIN employer e ON e.employer_id = i.employer_id
             WHERE aa.adviser_id = :adviser_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             ORDER BY company_name ASC'
        );
        $companyStmt->execute([':adviser_id' => $adviserId]);
        $companies = $companyStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'companies' => $companies,
            'progresses' => ['On Track', 'Progressing', 'Behind', 'Pending', 'Completed'],
        ];
    }
}
