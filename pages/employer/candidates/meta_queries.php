<?php
/**
 * Purpose: Loads employer internship postings and application statuses for the candidates page filter dropdowns.
 * Tables/columns used: internship(internship_id, employer_id, title), application(internship_id, status).
 */

if (!function_exists('candidates_get_positions')) {
    function candidates_get_positions(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT i.internship_id, i.title
             FROM internship i
             WHERE i.employer_id = :employer_id
             ORDER BY i.title ASC, i.internship_id DESC'
        );
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('candidates_get_statuses')) {
    function candidates_get_statuses(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT a.status
             FROM application a
             INNER JOIN internship i ON i.internship_id = a.internship_id
             WHERE i.employer_id = :employer_id
               AND a.status IS NOT NULL
               AND a.status <> ""
             ORDER BY a.status ASC'
        );
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
