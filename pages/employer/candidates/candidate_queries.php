<?php
/**
 * Purpose: Fetches filtered candidate application rows for the employer candidates page.
 * Tables/columns used: application(application_id, internship_id, student_id, status, compatibility_score, application_date), internship(internship_id, employer_id, title), student(student_id, first_name, last_name, program, year_level, internship_readiness_score).
 */

if (!function_exists('candidates_get_candidate_rows')) {
    function candidates_get_candidate_rows(PDO $pdo, int $employerId, array $parsedFilters): array
    {
        $sql = '
            SELECT
                a.application_id,
                a.status,
                a.compatibility_score,
                a.application_date,
                s.student_id,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.internship_readiness_score,
                i.title AS internship_title
            FROM application a
            INNER JOIN internship i ON i.internship_id = a.internship_id
            INNER JOIN student s ON s.student_id = a.student_id
            WHERE i.employer_id = :employer_id
        ';

        $params = [':employer_id' => $employerId];

        if ($parsedFilters['search'] !== '') {
            $sql .= ' AND (s.first_name LIKE :search OR s.last_name LIKE :search OR i.title LIKE :search)';
            $params[':search'] = '%' . $parsedFilters['search'] . '%';
        }

        if ($parsedFilters['position'] !== '') {
            $sql .= ' AND a.internship_id = :position_internship_id';
            $params[':position_internship_id'] = (int)$parsedFilters['position'];
        }

        if ($parsedFilters['status'] !== '') {
            $sql .= ' AND a.status = :status';
            $params[':status'] = $parsedFilters['status'];
        }

        $sql .= ' ORDER BY ' . $parsedFilters['order_by'] . ' LIMIT 12';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
