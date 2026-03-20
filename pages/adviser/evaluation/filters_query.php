<?php
/**
 * Purpose: Loads filter options for adviser evaluation page.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, department).
 */

if (!function_exists('adviser_evaluation_get_filter_options')) {
    function adviser_evaluation_get_filter_options(PDO $pdo, int $adviserId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT COALESCE(NULLIF(TRIM(s.department), ""), "Unassigned") AS department
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
             WHERE aa.adviser_id = :adviser_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             ORDER BY department ASC'
        );
        $stmt->execute([':adviser_id' => $adviserId]);

        return [
            'departments' => $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'statuses' => ['Graded', 'Pending'],
        ];
    }
}
