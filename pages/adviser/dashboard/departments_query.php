<?php
/**
 * Purpose: Loads assigned student counts grouped by department and program for adviser dashboard bars.
 * Tables/columns used: adviser_assignment(adviser_id, student_id), student(student_id, department, program).
 */

if (!function_exists('adviser_dashboard_get_departments')) {
    function adviser_dashboard_get_departments(PDO $pdo, int $adviserId, int $limit = 4): array
    {
        $safeLimit = max(1, min(12, $limit));

        $sql = '
            SELECT
                COALESCE(NULLIF(TRIM(s.department), \'\'), \'Unassigned\') AS department,
                COALESCE(NULLIF(TRIM(s.program), \'\'), \'General\') AS program,
                COUNT(DISTINCT aa.student_id) AS student_count
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
             WHERE aa.adviser_id = :adviser_id
             GROUP BY COALESCE(NULLIF(TRIM(s.department), \'\'), \'Unassigned\'),
                      COALESCE(NULLIF(TRIM(s.program), \'\'), \'General\')
             ORDER BY student_count DESC, department ASC, program ASC
             LIMIT ' . $safeLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':adviser_id' => $adviserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $maxCount = 0;
        foreach ($rows as $row) {
            $maxCount = max($maxCount, (int)($row['student_count'] ?? 0));
        }

        foreach ($rows as $index => &$row) {
            $count = (int)($row['student_count'] ?? 0);
            $row['bar_width'] = $maxCount > 0 ? max(8, (int)round(($count / $maxCount) * 100)) : 0;
            $row['bar_gradient'] = adviser_dashboard_bar_gradient($index);
        }
        unset($row);

        return $rows;
    }
}