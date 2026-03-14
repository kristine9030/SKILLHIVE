<?php
/**
 * Purpose: Loads selectable intern plus internship pairs for the evaluation form.
 * Tables/columns used: ojt_record(student_id, internship_id, completion_status), internship(internship_id, employer_id, title), student(student_id, first_name, last_name).
 */

if (!function_exists('getEmployerInternOptions')) {
    function getEmployerInternOptions(PDO $pdo, int $employerId, int $internshipId = 0): array
    {
        $sql =
            'SELECT DISTINCT
                s.student_id,
                s.first_name,
                s.last_name,
                i.internship_id,
                i.title
             FROM ojt_record o
             INNER JOIN internship i ON i.internship_id = o.internship_id
             INNER JOIN student s ON s.student_id = o.student_id
                         WHERE i.employer_id = :employer_id
                             AND LOWER(COALESCE(o.completion_status, "")) = "completed"';

        $params = [':employer_id' => $employerId];
        if ($internshipId > 0) {
            $sql .= ' AND i.internship_id = :internship_id';
            $params[':internship_id'] = $internshipId;
        }

        $sql .= ' ORDER BY i.title ASC, s.last_name ASC, s.first_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('getEmployerEvaluationInternships')) {
    function getEmployerEvaluationInternships(PDO $pdo, int $employerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT
                i.internship_id,
                i.title
             FROM internship i
             WHERE i.employer_id = :employer_id
             ORDER BY i.title ASC, i.internship_id DESC'
        );
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
