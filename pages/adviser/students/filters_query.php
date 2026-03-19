<?php
/**
 * Purpose: Loads filter options for adviser students page.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, department), ojt_record(student_id, completion_status, record_id).
 */

if (!function_exists('adviser_students_get_filter_options')) {
    function adviser_students_get_filter_options(PDO $pdo, int $adviserId): array
    {
        $departmentStmt = $pdo->prepare(
            'SELECT DISTINCT COALESCE(NULLIF(TRIM(s.department), ""), "Unassigned") AS department
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
                         WHERE aa.adviser_id = :adviser_id
                             AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             ORDER BY department ASC'
        );
        $departmentStmt->execute([':adviser_id' => $adviserId]);
        $departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $statusStmt = $pdo->prepare(
            'SELECT DISTINCT COALESCE(NULLIF(TRIM(o.completion_status), ""), "No OJT") AS completion_status
             FROM adviser_assignment aa
             LEFT JOIN (
                SELECT o1.*
                FROM ojt_record o1
                INNER JOIN (
                    SELECT student_id, MAX(record_id) AS max_record_id
                    FROM ojt_record
                    GROUP BY student_id
                ) latest ON latest.max_record_id = o1.record_id
             ) o ON o.student_id = aa.student_id
             WHERE aa.adviser_id = :adviser_id
               AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             ORDER BY completion_status ASC'
        );
        $statusStmt->execute([':adviser_id' => $adviserId]);
        $rawStatuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $statuses = [];
        foreach ($rawStatuses as $rawStatus) {
            $clean = trim((string)$rawStatus);
            $statuses[] = $clean !== '' ? $clean : 'No OJT';
        }

        $statuses = array_values(array_unique($statuses));

        return [
            'departments' => $departments,
            'statuses' => $statuses,
        ];
    }
}
