<?php
/**
 * Purpose: Loads filter options for adviser students page.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, track, section), ojt_record(student_id, completion_status, record_id).
 */

if (!function_exists('adviser_students_get_filter_options')) {
    function adviser_students_get_filter_options(PDO $pdo, int $adviserId): array
    {
        $departmentStmt = $pdo->prepare(
            'SELECT DISTINCT
                CASE
                    WHEN COALESCE(NULLIF(TRIM(s.section), ""), "") = "" THEN "Unassigned"
                    WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "business analytics" THEN CONCAT("BA ", TRIM(s.section))
                    WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "networking" THEN CONCAT("NT ", TRIM(s.section))
                    ELSE TRIM(s.section)
                END AS department
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
                         WHERE aa.adviser_id = :adviser_id
                             AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
             ORDER BY department ASC'
        );
        $departmentStmt->execute([':adviser_id' => $adviserId]);
        $departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $statusStmt = $pdo->prepare(
            'SELECT DISTINCT
                CASE
                    WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "completed" THEN "Completed"
                    WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "ongoing" THEN "Ongoing"
                    WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "dropped" THEN "Dropped"
                    WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "currently interning" THEN "Currently Interning"
                    WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "unavailable" THEN "Unavailable"
                    WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "available" THEN "Available"
                    ELSE "No OJT"
                END AS completion_status
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
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
