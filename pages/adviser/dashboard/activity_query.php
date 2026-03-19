<?php
/**
 * Purpose: Loads recent OJT activity for students assigned to the current adviser.
 * Tables/columns used: adviser_assignment(adviser_id, student_id), ojt_record(record_id, student_id, internship_id, hours_required, hours_completed, completion_status, updated_at, created_at), internship(internship_id, employer_id), employer(employer_id, company_name), student(student_id, first_name, last_name).
 */

if (!function_exists('adviser_dashboard_get_recent_activity')) {
    function adviser_dashboard_get_recent_activity(PDO $pdo, int $adviserId, int $limit = 4): array
    {
        $safeLimit = max(1, min(10, $limit));

        $sql = '
            SELECT
                o.record_id,
                o.hours_completed,
                o.hours_required,
                o.completion_status,
                o.updated_at,
                s.first_name,
                s.last_name,
                e.company_name
                 FROM (SELECT DISTINCT student_id FROM adviser_assignment WHERE adviser_id = :adviser_id) aa
             INNER JOIN ojt_record o ON o.student_id = aa.student_id
             INNER JOIN internship i ON i.internship_id = o.internship_id
             INNER JOIN employer e ON e.employer_id = i.employer_id
                 INNER JOIN student s ON s.student_id = aa.student_id
             ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.record_id DESC
             LIMIT ' . $safeLimit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':adviser_id' => $adviserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $progressPercent = adviser_dashboard_progress_percent($row['hours_completed'] ?? 0, $row['hours_required'] ?? 0);
            $badge = adviser_dashboard_activity_badge($row['completion_status'] ?? '', $progressPercent);
            $row['progress_percent'] = $progressPercent;
            $row['status_label'] = $badge['label'];
            $row['status_class'] = $badge['class'];
            $row['initials'] = adviser_dashboard_initials($row['first_name'] ?? '', $row['last_name'] ?? '');
        }
        unset($row);

        return $rows;
    }
}