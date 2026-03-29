<?php
/**
 * Purpose: Loads adviser endorsement filter options.
 * Tables/columns used: endorsement(adviser_id, status), application(application_id, student_id), student(student_id, department), adviser_assignment(adviser_id, student_id, status).
 */

if (!function_exists('adviser_endorsement_get_filter_options')) {
    function adviser_endorsement_get_filter_options(PDO $pdo, int $adviserId): array
    {
        $statusStmt = $pdo->prepare(
            'SELECT DISTINCT e.status AS status
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN adviser_assignment aa ON aa.student_id = a.student_id
                AND aa.adviser_id = :status_assignment_adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             WHERE e.adviser_id = :status_endorsement_adviser_id
             ORDER BY status ASC'
        );
        $statusStmt->execute([
            ':status_assignment_adviser_id' => $adviserId,
            ':status_endorsement_adviser_id' => $adviserId,
        ]);
        $statusRows = $statusStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $statusOptions = [];
        foreach ($statusRows as $status) {
            $normalized = adviser_endorsement_normalize_status((string)$status);
            if (!in_array($normalized, $statusOptions, true)) {
                $statusOptions[] = $normalized;
            }
        }

        usort($statusOptions, static function (string $a, string $b): int {
            $weight = ['Pending' => 1, 'Approved' => 2, 'Rejected' => 3];
            $wa = $weight[$a] ?? 99;
            $wb = $weight[$b] ?? 99;
            return $wa === $wb ? strcmp($a, $b) : ($wa <=> $wb);
        });

        $deptStmt = $pdo->prepare(
            "SELECT DISTINCT COALESCE(NULLIF(TRIM(s.department), ''), 'Unassigned') AS department
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN student s ON s.student_id = a.student_id
             INNER JOIN adviser_assignment aa ON aa.student_id = s.student_id
                AND aa.adviser_id = :dept_assignment_adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ''), 'Active') = 'Active'
             WHERE e.adviser_id = :dept_endorsement_adviser_id
             ORDER BY department ASC"
        );
        $deptStmt->execute([
            ':dept_assignment_adviser_id' => $adviserId,
            ':dept_endorsement_adviser_id' => $adviserId,
        ]);
        $departmentOptions = $deptStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'statuses' => $statusOptions,
            'departments' => $departmentOptions,
        ];
    }
}
