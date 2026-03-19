<?php
/**
 * Purpose: Loads pending endorsement requests with internship and student context.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, created_at), application(application_id, student_id, internship_id, compatibility_score), student(student_id, first_name, last_name, program, year_level, department), internship(internship_id, title, duration_weeks, work_setup, employer_id), employer(employer_id, company_name).
 */

if (!function_exists('adviser_endorsement_get_pending')) {
    function adviser_endorsement_get_pending(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                e.endorsement_id,
                e.status,
                e.created_at,
                a.application_id,
                a.compatibility_score,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.department,
                i.title AS internship_title,
                i.duration_weeks,
                i.work_setup,
                emp.company_name
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN student s ON s.student_id = a.student_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN employer emp ON emp.employer_id = i.employer_id
             WHERE e.adviser_id = :adviser_id
               AND LOWER(COALESCE(e.status, \'\')) IN (\'pending\', \'reviewing\', \'for review\', \'submitted\')';

        $params = [':adviser_id' => $adviserId];

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(s.department), \'\'), \'Unassigned\') = :department';
            $params[':department'] = $department;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(s.first_name, \'\'), \' \', COALESCE(s.last_name, \'\')) LIKE :search
                OR i.title LIKE :search
                OR emp.company_name LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY e.created_at DESC, e.endorsement_id DESC LIMIT 10';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
