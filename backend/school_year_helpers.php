<?php
/**
 * School Year Helpers - Global utility functions for school year filtering
 * Purpose: Provides reusable functions for all modules to support school year filtering
 */

if (!function_exists('get_selected_school_year_id')) {
    /**
     * Get the currently selected school year ID from session
     * Falls back to active school year if none selected
     */
    function get_selected_school_year_id(PDO $pdo): int
    {
        $selectedId = (int)($_SESSION['selected_school_year_id'] ?? 0);

        if ($selectedId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM school_years WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $selectedId]);
            if ($stmt->fetchColumn()) {
                return $selectedId;
            }
        }

        // Default to active school year
        $stmt = $pdo->prepare('SELECT id FROM school_years WHERE status = "Active" LIMIT 1');
        $stmt->execute();
        $activeId = (int)($stmt->fetchColumn());

        if ($activeId > 0) {
            $_SESSION['selected_school_year_id'] = $activeId;
        }

        return $activeId;
    }
}

if (!function_exists('get_selected_school_year')) {
    /**
     * Get the currently selected school year with full details
     */
    function get_selected_school_year(PDO $pdo): array
    {
        $id = get_selected_school_year_id($pdo);

        if ($id <= 0) {
            return ['id' => 0, 'school_year' => '', 'status' => ''];
        }

        $stmt = $pdo->prepare('SELECT id, school_year, status FROM school_years WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: ['id' => $id, 'school_year' => '', 'status' => ''];
    }
}

if (!function_exists('add_school_year_filter_to_query')) {
    /**
     * Helper to add school year filter clause to SQL queries
     * 
     * Usage:
     *   $sql = 'SELECT ... FROM student WHERE 1=1';
     *   list($sql, $params) = add_school_year_filter_to_query($pdo, $sql, [], 's');
     * 
     * @param PDO $pdo Database connection
     * @param string $sql Base SQL query
     * @param array $params Existing parameters array
     * @param string $tableAlias Table alias to use (default 's' for student)
     * @return array [modified_sql, modified_params]
     */
    function add_school_year_filter_to_query(
        PDO $pdo,
        string $sql,
        array $params = [],
        string $tableAlias = 's'
    ): array {
        $selectedId = get_selected_school_year_id($pdo);

        if ($selectedId > 0) {
            $sql .= ' AND ' . $tableAlias . '.school_year_id = :school_year_id_filter';
            $params[':school_year_id_filter'] = $selectedId;
        }

        return [$sql, $params];
    }
}

if (!function_exists('apply_school_year_filter')) {
    /**
     * Apply school year filter to a SQL query
     * Returns the modified SQL and parameters
     */
    function apply_school_year_filter(
        PDO $pdo,
        string $sql,
        array $params,
        string $table_alias = 's'
    ): array {
        $schoolYearId = get_selected_school_year_id($pdo);

        if ($schoolYearId > 0) {
            $sql = $sql . ' AND ' . $table_alias . '.school_year_id = :sy_filter';
            $params[':sy_filter'] = $schoolYearId;
        }

        return [$sql, $params];
    }
}

if (!function_exists('ensure_student_school_year')) {
    /**
     * Ensure a student has a school_year_id, assigning current active if needed
     */
    function ensure_student_school_year(PDO $pdo, int $studentId): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        try {
            // Check if student already has a school_year_id
            $checkStmt = $pdo->prepare('SELECT school_year_id FROM student WHERE student_id = :id');
            $checkStmt->execute([':id' => $studentId]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['school_year_id'])) {
                return true; // Already has one
            }

            // Get active school year
            $activeStmt = $pdo->prepare('SELECT id FROM school_years WHERE status = "Active" LIMIT 1');
            $activeStmt->execute();
            $activeId = (int)($activeStmt->fetchColumn());

            if ($activeId > 0) {
                $updateStmt = $pdo->prepare('UPDATE student SET school_year_id = :sy_id WHERE student_id = :id');
                $updateStmt->execute([':sy_id' => $activeId, ':id' => $studentId]);
                return true;
            }

            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('get_school_year_student_count')) {
    /**
     * Get count of students in a specific school year and status
     */
    function get_school_year_student_count(
        PDO $pdo,
        int $schoolYearId,
        string $status = 'active'
    ): int {
        try {
            $sql = 'SELECT COUNT(*) FROM student WHERE school_year_id = :sy_id';
            $params = [':sy_id' => $schoolYearId];

            if ($status === 'archived') {
                $sql .= ' AND archived_at IS NOT NULL';
            } elseif ($status === 'active') {
                $sql .= ' AND (archived_at IS NULL OR archived_at = "0000-00-00 00:00:00")';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('migrate_student_to_school_year')) {
    /**
     * Move a student from one school year to another (for manual reassignment if needed)
     */
    function migrate_student_to_school_year(
        PDO $pdo,
        int $studentId,
        int $newSchoolYearId
    ): bool {
        if ($studentId <= 0 || $newSchoolYearId <= 0) {
            return false;
        }

        try {
            // Verify new school year exists
            $checkStmt = $pdo->prepare('SELECT id FROM school_years WHERE id = :id');
            $checkStmt->execute([':id' => $newSchoolYearId]);
            if (!$checkStmt->fetchColumn()) {
                return false;
            }

            // Update student
            $updateStmt = $pdo->prepare('UPDATE student SET school_year_id = :sy_id WHERE student_id = :id');
            $updateStmt->execute([':sy_id' => $newSchoolYearId, ':id' => $studentId]);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
