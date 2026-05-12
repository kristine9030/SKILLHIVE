<?php
/**
 * Purpose: School year filtering helpers for adviser students page
 * Tables/columns used: school_years, student, ojt_record, endorsement, application
 */

// Ensure all required helper functions are available
require_once __DIR__ . '/formatters.php';

if (!function_exists('adviser_students_get_selected_school_year')) {
    /**
     * Get the currently selected school year ID from session, or default to active
     */
    function adviser_students_get_selected_school_year(PDO $pdo): array
    {
        try {
            $selectedId = (int)($_SESSION['selected_school_year_id'] ?? 0);

            if ($selectedId > 0) {
                $stmt = $pdo->prepare('SELECT id, school_year FROM school_years WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $selectedId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'id' => (int)$row['id'],
                        'school_year' => (string)$row['school_year'],
                    ];
                }
            }

            // Default to active school year
            $stmt = $pdo->prepare('SELECT id, school_year FROM school_years WHERE status = "Active" LIMIT 1');
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $_SESSION['selected_school_year_id'] = (int)$row['id'];
                $_SESSION['selected_school_year'] = (string)$row['school_year'];

                return [
                    'id' => (int)$row['id'],
                    'school_year' => (string)$row['school_year'],
                ];
            }

            return ['id' => 0, 'school_year' => ''];
        } catch (Throwable $e) {
            // Log error but don't output it
            error_log('Error in adviser_students_get_selected_school_year: ' . $e->getMessage());
            return ['id' => 0, 'school_year' => ''];
        }
    }
}

if (!function_exists('adviser_students_get_school_year_options')) {
    /**
     * Get all school years for dropdown, grouped by status
     */
    function adviser_students_get_school_year_options(PDO $pdo): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, school_year, status FROM school_years ORDER BY school_year DESC'
            );
            $stmt->execute();
            $allYears = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $active = [];
            $archived = [];

            foreach ($allYears as $year) {
                $item = [
                    'id' => (int)$year['id'],
                    'school_year' => (string)$year['school_year'],
                    'status' => (string)$year['status'],
                ];

                if ($year['status'] === 'Active') {
                    $active[] = $item;
                } else {
                    $archived[] = $item;
                }
            }

            return [
                'active' => $active,
                'archived' => $archived,
            ];
        } catch (Throwable $e) {
            error_log('Error in adviser_students_get_school_year_options: ' . $e->getMessage());
            return ['active' => [], 'archived' => []];
        }
    }
}

if (!function_exists('adviser_students_get_tab_students')) {
    /**
     * Get students for a specific tab (Active, Archived, Alumni)
     * Returns student rows filtered by school year and archive status
     */
    function adviser_students_get_tab_students(
        PDO $pdo,
        int $adviserId,
        int $schoolYearId,
        string $tab = 'active',
        array $filters = []
    ): array {
        try {
            $hasAcademicYearColumn = adviser_students_has_academic_year_column($pdo);
        $academicYearSelect = $hasAcademicYearColumn
            ? 's.academic_year,'
            : '"" AS academic_year,';

        $baseSQL = '
            SELECT
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.email,
                s.program,
                s.department,
                s.track,
                s.section,
                s.year_level,
                ' . $academicYearSelect . '
                s.availability_status,
                "Active" AS account_status,
                "" AS account_status_reason,
                s.archived_at,
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
                    WHERE r_total.applicable_to = "Student"
                ) AS total_requirements,
                (
                    SELECT COUNT(DISTINCT sr.requirement_id)
                    FROM student_requirement sr
                    INNER JOIN requirement r_link ON r_link.requirement_id = sr.requirement_id
                    WHERE sr.student_id = s.student_id
                        AND r_link.applicable_to = "Student"
                        AND sr.status IN ("Submitted", "Approved")
                        AND (
                            (o.internship_id IS NULL AND sr.internship_id IS NULL)
                            OR (o.internship_id IS NOT NULL AND sr.internship_id = o.internship_id)
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
            WHERE s.school_year_id = :school_year_id';

        $params = [
            ':adviser_id' => $adviserId,
            ':moa_adviser_id' => $adviserId,
            ':school_year_id' => $schoolYearId,
        ];

        // Tab filtering
        if ($tab === 'archived') {
            $baseSQL .= ' AND s.archived_at IS NOT NULL';
        } elseif ($tab === 'alumni') {
            $baseSQL .= ' AND s.archived_at IS NOT NULL 
                        AND (SELECT completion_status FROM ojt_record WHERE student_id = s.student_id ORDER BY record_id DESC LIMIT 1) = "Completed"';
        } else {
            // Active tab - non-archived students
            $baseSQL .= ' AND (s.archived_at IS NULL OR s.archived_at = "0000-00-00 00:00:00")';
        }

        // Apply filters
        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $baseSQL .= ' AND CASE
                WHEN COALESCE(NULLIF(TRIM(s.section), ""), "") = "" THEN "Unassigned"
                WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "business analytics" THEN CONCAT("BA ", TRIM(s.section))
                WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "networking" THEN CONCAT("NT ", TRIM(s.section))
                ELSE TRIM(s.section)
            END = :department';
            $params[':department'] = $department;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $baseSQL .= ' AND CASE
                WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "completed" THEN "Completed"
                WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "ongoing" THEN "Ongoing"
                WHEN LOWER(TRIM(COALESCE(o.completion_status, ""))) = "dropped" THEN "Dropped"
                WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "currently interning" THEN "Currently Interning"
                WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "unavailable" THEN "Unavailable"
                WHEN LOWER(TRIM(COALESCE(s.availability_status, ""))) = "available" THEN "Available"
                ELSE "No OJT"
            END = :completion_status';
            $params[':completion_status'] = $status;
        }

        $accountStatus = trim((string)($filters['account_status'] ?? ''));
        if ($accountStatus !== '') {
            // Note: account_status column doesn't exist, so we only filter if explicitly "Active"
            // This prevents breaking existing functionality
            if ($accountStatus !== 'Active') {
                // If filtering for non-Active status, return empty results since column doesn't exist
                return [];
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $baseSQL .= ' AND (
                CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) LIKE :search
                OR COALESCE(s.student_number, "") LIKE :search
                OR s.program LIKE :search
                OR s.department LIKE :search
                OR COALESCE(s.track, "") LIKE :search
                OR COALESCE(s.section, "") LIKE :search
                ' . ($hasAcademicYearColumn ? 'OR COALESCE(s.academic_year, "") LIKE :search' : '') . '
                OR COALESCE(i.title, "") LIKE :search
                OR COALESCE(e.company_name, "") LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $baseSQL .= ' ORDER BY s.last_name ASC, s.first_name ASC';

        $stmt = $pdo->prepare($baseSQL);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Format rows
        foreach ($rows as &$row) {
            if (!array_key_exists('account_status', $row)) {
                $row['account_status'] = 'Active';
                $row['account_status_reason'] = '';
            }

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
        } catch (Throwable $e) {
            error_log('Error in adviser_students_get_tab_students: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('adviser_students_school_year_exists')) {
    /**
     * Check if a school year exists and is valid
     */
    function adviser_students_school_year_exists(PDO $pdo, int $schoolYearId): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM school_years WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $schoolYearId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Error in adviser_students_school_year_exists: ' . $e->getMessage());
            return false;
        }
    }
}
