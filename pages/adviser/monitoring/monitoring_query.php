<?php
/**
 * Purpose: Loads adviser monitoring cards for assigned students with OJT and latest daily log details.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, first_name, last_name, email, program), ojt_record(record_id, student_id, internship_id, hours_required, hours_completed, completion_status), internship(internship_id, title, employer_id), employer(employer_id, company_name), daily_log(log_id, record_id, log_date, accomplishment).
 */

if (!function_exists('adviser_monitoring_get_rows')) {
    function adviser_monitoring_get_rows(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                s.student_id,
                s.first_name,
                s.last_name,
                s.email,
                s.program,
                o.record_id,
                o.hours_required,
                o.hours_completed,
                o.completion_status,
                i.title AS internship_title,
                e.company_name,
                dl.log_date AS latest_log_date,
                dl.accomplishment AS latest_accomplishment
            FROM (
                SELECT DISTINCT student_id
                FROM adviser_assignment
                WHERE adviser_id = :adviser_id
                  AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"
            ) aa
            INNER JOIN student s ON s.student_id = aa.student_id
            LEFT JOIN (
                SELECT o1.*
                FROM ojt_record o1
                INNER JOIN (
                    SELECT student_id, MAX(record_id) AS max_record_id
                    FROM ojt_record
                    GROUP BY student_id
                ) latest ON latest.max_record_id = o1.record_id
            ) o ON o.student_id = s.student_id
            LEFT JOIN internship i ON i.internship_id = o.internship_id
            LEFT JOIN employer e ON e.employer_id = i.employer_id
            LEFT JOIN (
                SELECT d1.*
                FROM daily_log d1
                INNER JOIN (
                    SELECT record_id, MAX(log_id) AS max_log_id
                    FROM daily_log
                    GROUP BY record_id
                ) latest_log ON latest_log.max_log_id = d1.log_id
            ) dl ON dl.record_id = o.record_id
            WHERE 1=1';

        $params = [':adviser_id' => $adviserId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) LIKE :search
                OR COALESCE(s.program, "") LIKE :search
                OR COALESCE(i.title, "") LIKE :search
                OR COALESCE(e.company_name, "") LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $company = trim((string)($filters['company'] ?? ''));
        if ($company !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(e.company_name), ""), "No Company") = :company';
            $params[':company'] = $company;
        }

        $sql .= ' ORDER BY COALESCE(dl.log_date, "1970-01-01") DESC, s.last_name ASC, s.first_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $progressFilter = trim((string)($filters['progress'] ?? ''));
        $mapped = [];
        foreach ($rows as $row) {
            $percent = adviser_monitoring_progress_percent($row['hours_completed'] ?? 0, $row['hours_required'] ?? 0);
            $badge = adviser_monitoring_status_badge(
                (string)($row['completion_status'] ?? ''),
                $percent,
                (string)($row['latest_log_date'] ?? '')
            );

            if ($progressFilter !== '' && $badge['label'] !== $progressFilter) {
                continue;
            }

            $row['initials'] = adviser_monitoring_initials((string)($row['first_name'] ?? ''), (string)($row['last_name'] ?? ''));
            $row['progress_percent'] = $percent;
            $row['status_label'] = $badge['label'];
            $row['status_class'] = $badge['class'];
            $row['progress_gradient'] = adviser_monitoring_progress_gradient($badge['label']);
            $row['latest_log_date_label'] = adviser_monitoring_format_log_date((string)($row['latest_log_date'] ?? ''));

            $mapped[] = $row;
        }

        return $mapped;
    }
}
