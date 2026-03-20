<?php
/**
 * Purpose: Loads adviser evaluation table rows based on OJT lifecycle flow.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, first_name, last_name, program, year_level, department), ojt_record(record_id, student_id, internship_id, hours_required, hours_completed, completion_status), internship(internship_id, employer_id, title), employer(employer_id, company_name), employer_evaluation(student_id, internship_id, technical_score, behavioral_score), adviser_evaluation(adviser_eval_id, adviser_id, student_id, internship_id, final_grade, comments, evaluation_date).
 */

if (!function_exists('adviser_evaluation_get_rows')) {
    function adviser_evaluation_get_rows(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                s.student_id,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.department,
                o.record_id,
                o.internship_id,
                o.hours_required,
                o.hours_completed,
                o.completion_status,
                e.company_name,
                i.title AS internship_title,
                ROUND((AVG(ee.technical_score) + AVG(ee.behavioral_score)) / 2, 2) AS employer_rating,
                ae_latest.adviser_eval_id,
                ae_latest.final_grade,
                ae_latest.comments,
                ae_latest.evaluation_date
            FROM (
                SELECT DISTINCT student_id
                FROM adviser_assignment
                WHERE adviser_id = :adviser_id
                  AND COALESCE(NULLIF(TRIM(status), ""), "Active") = "Active"
            ) aa
            INNER JOIN student s ON s.student_id = aa.student_id
            INNER JOIN (
                SELECT o1.*
                FROM ojt_record o1
                INNER JOIN (
                    SELECT student_id, MAX(record_id) AS max_record_id
                    FROM ojt_record
                    GROUP BY student_id
                ) latest ON latest.max_record_id = o1.record_id
            ) o ON o.student_id = s.student_id
            INNER JOIN internship i ON i.internship_id = o.internship_id
            INNER JOIN employer e ON e.employer_id = i.employer_id
            LEFT JOIN employer_evaluation ee ON ee.student_id = s.student_id
                AND ee.internship_id = o.internship_id
            LEFT JOIN (
                SELECT ae1.*
                FROM adviser_evaluation ae1
                INNER JOIN (
                    SELECT adviser_id, student_id, internship_id, MAX(adviser_eval_id) AS max_eval_id
                    FROM adviser_evaluation
                    GROUP BY adviser_id, student_id, internship_id
                ) latest_eval ON latest_eval.max_eval_id = ae1.adviser_eval_id
            ) ae_latest ON ae_latest.adviser_id = :adviser_eval_adviser_id
                AND ae_latest.student_id = s.student_id
                AND ae_latest.internship_id = o.internship_id
            WHERE 1=1';

        $params = [
            ':adviser_id' => $adviserId,
            ':adviser_eval_adviser_id' => $adviserId,
        ];

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(s.department), ""), "Unassigned") = :department';
            $params[':department'] = $department;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) LIKE :search
                OR COALESCE(i.title, "") LIKE :search
                OR COALESCE(e.company_name, "") LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= '
            GROUP BY
                s.student_id,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.department,
                o.record_id,
                o.internship_id,
                o.hours_required,
                o.hours_completed,
                o.completion_status,
                e.company_name,
                i.title,
                ae_latest.adviser_eval_id,
                ae_latest.final_grade,
                ae_latest.comments,
                ae_latest.evaluation_date
            ORDER BY
                CASE LOWER(COALESCE(o.completion_status, ""))
                    WHEN "completed" THEN 0
                    WHEN "ongoing" THEN 1
                    ELSE 2
                END,
                s.last_name ASC,
                s.first_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $statusFilter = trim((string)($filters['status'] ?? ''));
        $mapped = [];
        foreach ($rows as $row) {
            $hasAdviserEval = !empty($row['adviser_eval_id']);
            $rowStatus = adviser_evaluation_row_status((string)($row['completion_status'] ?? ''), $hasAdviserEval);

            if ($statusFilter === 'Graded' && $rowStatus['label'] !== 'Graded') {
                continue;
            }
            if ($statusFilter === 'Pending' && $rowStatus['label'] === 'Graded') {
                continue;
            }

            $row['student_name'] = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $row['initials'] = adviser_evaluation_initials((string)($row['first_name'] ?? ''), (string)($row['last_name'] ?? ''));
            $row['status_label'] = $rowStatus['label'];
            $row['status_class'] = $rowStatus['class'];
            $row['is_eligible'] = strtolower(trim((string)($row['completion_status'] ?? ''))) === 'completed';
            $row['has_adviser_evaluation'] = $hasAdviserEval;

            $mapped[] = $row;
        }

        return $mapped;
    }
}
