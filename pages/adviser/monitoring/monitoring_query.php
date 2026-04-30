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
                o.start_date AS ojt_start_date,
                o.created_at AS ojt_created_at,
                i.title AS internship_title,
                i.location AS internship_location,
                i.work_setup AS internship_work_setup,
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
                (string)($row['latest_log_date'] ?? ''),
                (string)($row['ojt_start_date'] ?? ''),
                (string)($row['ojt_created_at'] ?? '')
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

        $recordIds = [];
        foreach ($mapped as $row) {
            $recordId = (int)($row['record_id'] ?? 0);
            if ($recordId > 0) {
                $recordIds[] = $recordId;
            }
        }

        $logsByRecord = adviser_monitoring_get_recent_logs_by_record($pdo, $recordIds, 6);

        foreach ($mapped as &$row) {
            $recordId = (int)($row['record_id'] ?? 0);
            $row['recent_logs'] = $recordId > 0 ? ($logsByRecord[$recordId] ?? []) : [];
        }
        unset($row);

        return $mapped;
    }
}

if (!function_exists('adviser_monitoring_get_recent_logs_by_record')) {
    function adviser_monitoring_get_recent_logs_by_record(PDO $pdo, array $recordIds, int $maxPerRecord = 6): array
    {
        $cleanIds = [];
        foreach ($recordIds as $recordId) {
            $id = (int)$recordId;
            if ($id > 0) {
                $cleanIds[$id] = $id;
            }
        }

        if (empty($cleanIds)) {
            return [];
        }

        $ids = array_values($cleanIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = 'SELECT
                    d.record_id,
                    d.log_id,
                    d.log_date,
                    d.accomplishment,
                    d.hours_rendered,
                    d.mood_tag,
                    d.task_file,
                    d.created_at
                FROM daily_log d
                WHERE d.record_id IN (' . $placeholders . ')
                ORDER BY d.record_id ASC, d.log_date DESC, d.log_id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $recordId = (int)($row['record_id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }

            if (!isset($grouped[$recordId])) {
                $grouped[$recordId] = [];
            }

            if (count($grouped[$recordId]) >= $maxPerRecord) {
                continue;
            }

            $grouped[$recordId][] = [
                'log_id' => (int)($row['log_id'] ?? 0),
                'log_date' => (string)($row['log_date'] ?? ''),
                'accomplishment' => trim((string)($row['accomplishment'] ?? '')),
                'hours_rendered' => (float)($row['hours_rendered'] ?? 0),
                'mood_tag' => trim((string)($row['mood_tag'] ?? '')),
                'task_file' => trim((string)($row['task_file'] ?? '')),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        return $grouped;
    }
}

if (!function_exists('adviser_monitoring_get_map_students')) {
    function adviser_monitoring_get_map_students(PDO $pdo, int $adviserId): array
    {
        $sql = '
            SELECT DISTINCT
                a.application_id,
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                o.record_id,
                o.hours_required,
                o.hours_completed,
                o.completion_status AS ojt_status,
                i.internship_id,
                i.title AS internship_title,
                i.location AS internship_location,
                e.company_name
            FROM adviser_assignment aa
            INNER JOIN student s ON s.student_id = aa.student_id
            INNER JOIN application a ON a.student_id = s.student_id
                AND COALESCE(NULLIF(TRIM(a.status), ""), "") = "Accepted"
            INNER JOIN internship i ON i.internship_id = a.internship_id
            LEFT JOIN employer e ON e.employer_id = i.employer_id
            LEFT JOIN (
                SELECT o1.*
                FROM ojt_record o1
                INNER JOIN (
                    SELECT student_id, internship_id, MAX(record_id) AS max_record_id
                    FROM ojt_record
                    GROUP BY student_id, internship_id
                ) latest ON latest.max_record_id = o1.record_id
            ) o ON o.student_id = s.student_id
                AND o.internship_id = a.internship_id
            WHERE aa.adviser_id = :adviser_id
              AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
              AND TRIM(COALESCE(i.location, "")) <> ""
            ORDER BY i.location ASC, e.company_name ASC, s.last_name ASC, s.first_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':adviser_id' => $adviserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapped = [];
        foreach ($rows as $row) {
            $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $hasOjtRecord = (int)($row['record_id'] ?? 0) > 0;
            $hoursCompleted = (float)($row['hours_completed'] ?? 0);
            $hoursRequired = (float)($row['hours_required'] ?? 0);
            $hoursLabel = $hasOjtRecord
                ? ((int)round($hoursCompleted) . '/' . (int)round($hoursRequired) . ' hrs')
                : 'No OJT record yet';

            $mapped[] = [
                'application_id' => (int)($row['application_id'] ?? 0),
                'student_id' => (int)($row['student_id'] ?? 0),
                'student_number' => trim((string)($row['student_number'] ?? '')),
                'student_name' => $studentName !== '' ? $studentName : 'Unnamed Student',
                'program' => trim((string)($row['program'] ?? '')),
                'year_level' => (int)($row['year_level'] ?? 0),
                'record_id' => (int)($row['record_id'] ?? 0),
                'hours_required' => $hoursRequired,
                'hours_completed' => $hoursCompleted,
                'hours_label' => $hoursLabel,
                'progress_percent' => $hasOjtRecord ? adviser_monitoring_progress_percent($hoursCompleted, $hoursRequired) : 0,
                'ojt_status' => trim((string)($row['ojt_status'] ?? '')),
                'internship_id' => (int)($row['internship_id'] ?? 0),
                'internship_title' => trim((string)($row['internship_title'] ?? 'Internship')),
                'location' => trim((string)($row['internship_location'] ?? '')),
                'company_name' => trim((string)($row['company_name'] ?? 'No company listed')),
            ];
        }

        return $mapped;
    }
}
