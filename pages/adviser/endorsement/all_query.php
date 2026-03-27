<?php
/**
 * Purpose: Loads all endorsement rows for adviser tab.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, created_at, reviewed_at), application(application_id, student_id, internship_id), student(student_id, first_name, last_name, department), internship(internship_id, title, employer_id), employer(employer_id, company_name), adviser_assignment(adviser_id, student_id, status).
 */

if (!function_exists('adviser_endorsement_get_all')) {
    function adviser_endorsement_get_all(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                e.endorsement_id,
                e.status,
                e.created_at,
                e.reviewed_at,
                s.first_name,
                s.last_name,
                s.department,
                i.title AS internship_title,
                emp.company_name
             FROM endorsement e
             INNER JOIN application a ON a.application_id = e.application_id
             INNER JOIN student s ON s.student_id = a.student_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN employer emp ON emp.employer_id = i.employer_id
             INNER JOIN adviser_assignment aa ON aa.student_id = s.student_id
                AND aa.adviser_id = :aa_adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                         WHERE e.adviser_id = :adviser_id
                             AND e.endorsement_id = (
                                        SELECT MAX(e2.endorsement_id)
                                        FROM endorsement e2
                                        WHERE e2.application_id = e.application_id
                                            AND e2.adviser_id = e.adviser_id
                             )';

        $params = [
            ':adviser_id' => $adviserId,
            ':aa_adviser_id' => $adviserId,
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
                OR i.title LIKE :search
                OR emp.company_name LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY COALESCE(e.reviewed_at, e.created_at) DESC, e.endorsement_id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
