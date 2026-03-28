<?php
/**
 * Purpose: Loads pending endorsement rows and review-modal data.
 * Tables/columns used: endorsement(endorsement_id, application_id, adviser_id, status, endorsement_file, moa_status, created_at, reviewed_at, notes), application(application_id, student_id, internship_id, cover_letter), student(student_id, first_name, last_name, department, program, year_level, resume_file), internship(internship_id, title, employer_id), employer(employer_id, company_name), adviser_assignment(adviser_id, student_id, status), student_requirement(student_id, internship_id, requirement_id, status), requirement(requirement_id, name).
 */

if (!function_exists('adviser_endorsement_get_pending')) {
    function adviser_endorsement_get_pending(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                COALESCE(e.endorsement_id, 0) AS endorsement_id,
                COALESCE(NULLIF(TRIM(e.status), ""), "Pending") AS status,
                COALESCE(e.created_at, a.updated_at, a.application_date) AS created_at,
                e.reviewed_at,
                e.notes,
                e.endorsement_file,
                COALESCE(NULLIF(TRIM(e.moa_status), ""), "Not Started") AS moa_status,
                a.application_id,
                a.internship_id,
                a.cover_letter,
                a.status AS application_status,
                s.student_id,
                s.first_name,
                s.last_name,
                s.program,
                s.year_level,
                s.department,
                s.resume_file,
                i.title AS internship_title,
                emp.company_name,
                CASE
                    WHEN COALESCE(NULLIF(TRIM(e.endorsement_file), ""), "") <> "" THEN 1
                    ELSE COALESCE(doc_flags.has_endorsement_letter, 0)
                END AS has_endorsement_letter,
                CASE WHEN COALESCE(NULLIF(TRIM(s.resume_file), ""), "") <> "" THEN 1 ELSE 0 END AS has_resume,
                CASE
                    WHEN COALESCE(NULLIF(TRIM(a.cover_letter), ""), "") <> "" THEN 1
                    ELSE COALESCE(doc_flags.has_application_form, 0)
                END AS has_application_form,
                COALESCE(doc_flags.has_parent_consent, 0) AS has_parent_consent,
                COALESCE(doc_flags.has_medical_certificate, 0) AS has_medical_certificate
                 FROM application a
             INNER JOIN student s ON s.student_id = a.student_id
             INNER JOIN internship i ON i.internship_id = a.internship_id
             INNER JOIN employer emp ON emp.employer_id = i.employer_id
             INNER JOIN adviser_assignment aa ON aa.student_id = s.student_id
                     AND aa.adviser_id = :adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                 LEFT JOIN endorsement e ON e.endorsement_id = (
                     SELECT MAX(e2.endorsement_id)
                     FROM endorsement e2
                     WHERE e2.application_id = a.application_id
                        AND e2.adviser_id = aa.adviser_id
                 )
             LEFT JOIN (
                SELECT
                    sr.student_id,
                    sr.internship_id,
                    MAX(CASE WHEN LOWER(r.name) LIKE "%endorsement%" AND LOWER(COALESCE(sr.status, "")) IN ("submitted", "approved") THEN 1 ELSE 0 END) AS has_endorsement_letter,
                    MAX(CASE WHEN LOWER(r.name) LIKE "%application%form%" AND LOWER(COALESCE(sr.status, "")) IN ("submitted", "approved") THEN 1 ELSE 0 END) AS has_application_form,
                    MAX(CASE WHEN LOWER(r.name) LIKE "%parent%consent%" AND LOWER(COALESCE(sr.status, "")) IN ("submitted", "approved") THEN 1 ELSE 0 END) AS has_parent_consent,
                    MAX(CASE WHEN LOWER(r.name) LIKE "%medical%" AND LOWER(COALESCE(sr.status, "")) IN ("submitted", "approved") THEN 1 ELSE 0 END) AS has_medical_certificate
                FROM student_requirement sr
                INNER JOIN requirement r ON r.requirement_id = sr.requirement_id
                GROUP BY sr.student_id, sr.internship_id
             ) doc_flags ON doc_flags.student_id = s.student_id AND doc_flags.internship_id = a.internship_id
             WHERE LOWER(COALESCE(a.status, "")) = "shortlisted"';

        $params = [
            ':adviser_id' => $adviserId,
        ];

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

        $sql .= ' ORDER BY COALESCE(e.created_at, a.updated_at, a.application_date) DESC, a.application_id DESC LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $docs = [
                (int)($row['has_endorsement_letter'] ?? 0),
                (int)($row['has_resume'] ?? 0),
                (int)($row['has_application_form'] ?? 0),
                (int)($row['has_parent_consent'] ?? 0),
                (int)($row['has_medical_certificate'] ?? 0),
            ];
            $uploaded = 0;
            foreach ($docs as $flag) {
                if ($flag > 0) {
                    $uploaded++;
                }
            }

            $row['documents_uploaded'] = $uploaded;
            $row['documents_total'] = 5;
            $row['documents_status'] = $uploaded >= 5 ? 'Complete' : 'Partial';
        }
        unset($row);

        return $rows;
    }
}
