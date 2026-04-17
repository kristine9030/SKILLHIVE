<?php
/**
 * Purpose: Loads assigned students with latest internship/OJT context for adviser students page.
 * Tables/columns used: adviser_assignment(adviser_id, student_id, status), student(student_id, first_name, last_name, email, department, program, year_level, availability_status), ojt_record(record_id, student_id, internship_id, hours_required, hours_completed, completion_status), internship(internship_id, title, employer_id), employer(employer_id, company_name), requirement(requirement_id, applicable_to), student_requirement(student_id, internship_id, requirement_id, status).
 */

if (!function_exists('adviser_students_get_rows')) {
    function adviser_students_get_rows(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $hasAcademicYearColumn = adviser_students_has_academic_year_column($pdo);
        $academicYearSelect = $hasAcademicYearColumn
            ? 's.academic_year,'
            : '"" AS academic_year,';

        $sql = '
            SELECT
                s.student_id,
                s.first_name,
                s.last_name,
                s.email,
                s.program,
                s.department,
                s.year_level,
                ' . $academicYearSelect . '
                s.availability_status,
                o.record_id,
                o.internship_id,
                o.hours_required,
                o.hours_completed,
                o.completion_status,
                i.title AS internship_title,
                e.company_name,
                                (
                                        SELECT COALESCE(NULLIF(TRIM(a_status.status), ""), "Pending")
                                        FROM application a_status
                                        WHERE a_status.student_id = s.student_id
                                            AND (
                                                        o.internship_id IS NULL
                                                        OR a_status.internship_id = o.internship_id
                                            )
                                        ORDER BY a_status.application_id DESC
                                        LIMIT 1
                                ) AS application_status,
                                (
                                        SELECT COALESCE(NULLIF(TRIM(e_latest.moa_status), ""), "Not Started")
                                        FROM endorsement e_latest
                                        INNER JOIN application a_latest ON a_latest.application_id = e_latest.application_id
                                        WHERE e_latest.adviser_id = :moa_adviser_id
                                            AND a_latest.student_id = s.student_id
                                            AND (
                                                        o.internship_id IS NULL
                                                        OR a_latest.internship_id = o.internship_id
                                            )
                                        ORDER BY e_latest.endorsement_id DESC
                                        LIMIT 1
                                ) AS moa_status,
                (
                    SELECT COUNT(*)
                    FROM requirement r_total
                    WHERE r_total.applicable_to IN ("Student", "Both")
                ) AS total_requirements,
                (
                    SELECT COUNT(DISTINCT sr.requirement_id)
                    FROM student_requirement sr
                    INNER JOIN requirement r_link ON r_link.requirement_id = sr.requirement_id
                    WHERE sr.student_id = s.student_id
                      AND r_link.applicable_to IN ("Student", "Both")
                      AND sr.status IN ("Submitted", "Approved")
                      AND (
                            o.internship_id IS NULL
                            OR sr.internship_id = o.internship_id
                      )
                ) AS submitted_requirements
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
            WHERE 1=1';

        $params = [
            ':adviser_id' => $adviserId,
            ':moa_adviser_id' => $adviserId,
        ];

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(s.department), ""), "Unassigned") = :department';
            $params[':department'] = $department;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(o.completion_status), ""), "No OJT") = :completion_status';
            $params[':completion_status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) LIKE :search
                OR s.program LIKE :search
                OR s.department LIKE :search
                ' . ($hasAcademicYearColumn ? 'OR COALESCE(s.academic_year, "") LIKE :search' : '') . '
                OR COALESCE(i.title, "") LIKE :search
                OR COALESCE(e.company_name, "") LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY s.last_name ASC, s.first_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $progress = adviser_students_progress_percent($row['hours_completed'] ?? 0, $row['hours_required'] ?? 0);
            $statusLabel = adviser_students_status_label(
                (string)($row['completion_status'] ?? ''),
                (string)($row['availability_status'] ?? '')
            );
            $requirements = adviser_students_requirements_summary(
                (int)($row['submitted_requirements'] ?? 0),
                (int)($row['total_requirements'] ?? 0)
            );

            $row['initials'] = adviser_students_initials((string)($row['first_name'] ?? ''), (string)($row['last_name'] ?? ''));
            $row['progress_percent'] = $progress;
            $row['status_label'] = $statusLabel;
            $row['status_class'] = adviser_students_status_class($statusLabel);
            $row['moa_label'] = adviser_students_moa_label(
                (string)($row['moa_status'] ?? ''),
                (string)($row['company_name'] ?? ''),
                (string)($row['application_status'] ?? '')
            );
            $row['requirements_submitted'] = $requirements['submitted'];
            $row['requirements_pending'] = $requirements['pending'];
            $row['requirements_completion'] = $requirements['completion'];
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('adviser_students_has_academic_year_column')) {
    function adviser_students_has_academic_year_column(PDO $pdo): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "student"
               AND COLUMN_NAME = "academic_year"
             LIMIT 1'
        );
        $stmt->execute();
        $hasColumn = (bool)$stmt->fetchColumn();

        return $hasColumn;
    }
}
