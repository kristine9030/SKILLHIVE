<?php
/**
 * Purpose: Loads companies for adviser companies page with adviser-relevant intern metrics.
 * Tables/columns used: employer(employer_id, company_name, contact_person_name, industry, company_address, email, contact_number, verification_status, company_badge_status, website_url, created_at), internship(internship_id, employer_id, title, slots_available, status), application(student_id, internship_id, status), ojt_record(student_id, internship_id, completion_status), adviser_assignment(adviser_id, student_id, status), employer_evaluation(employer_id, technical_score, behavioral_score).
 */

if (!function_exists('adviser_companies_employer_has_column')) {
    function adviser_companies_employer_has_column(PDO $pdo, string $columnName): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = \'employer\'
                   AND COLUMN_NAME = :column_name
                 LIMIT 1'
            );
            $stmt->execute([':column_name' => $columnName]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('adviser_companies_get_rows')) {
    function adviser_companies_get_rows(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $hasContactPersonColumn = adviser_companies_employer_has_column($pdo, 'contact_person_name');
        $contactPersonSelect = $hasContactPersonColumn
            ? 'e.contact_person_name,'
            : '"" AS contact_person_name,';
        $contactPersonSearch = $hasContactPersonColumn
            ? ' OR COALESCE(e.contact_person_name, "") LIKE :search'
            : '';

        $sql = '
            SELECT
                e.employer_id,
                e.company_name,
                ' . $contactPersonSelect . '
                e.industry,
                e.company_address,
                e.email,
                e.contact_number,
                e.verification_status,
                e.company_badge_status,
                e.website_url,
                e.created_at,
                COALESCE(interns.current_interns, 0) AS current_interns,
                COALESCE(postings.total_postings, 0) AS total_postings,
                COALESCE(postings.open_postings, 0) AS open_postings,
                COALESCE(postings.open_slots, 0) AS open_slots,
                ev.avg_rating AS avg_rating
            FROM employer e
            LEFT JOIN (
                SELECT
                    employer_id,
                    COUNT(*) AS total_postings,
                    SUM(CASE WHEN LOWER(COALESCE(status, "")) = "open" THEN 1 ELSE 0 END) AS open_postings,
                    SUM(CASE WHEN LOWER(COALESCE(status, "")) = "open" THEN COALESCE(slots_available, 0) ELSE 0 END) AS open_slots
                FROM internship
                GROUP BY employer_id
            ) postings ON postings.employer_id = e.employer_id
            LEFT JOIN (
                SELECT
                    i.employer_id,
                    COUNT(DISTINCT o.student_id) AS current_interns
                FROM internship i
                INNER JOIN ojt_record o ON o.internship_id = i.internship_id
                INNER JOIN adviser_assignment aa ON aa.student_id = o.student_id
                    AND aa.adviser_id = :adviser_id
                    AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                WHERE COALESCE(NULLIF(TRIM(o.completion_status), ""), "Ongoing") = "Ongoing"
                GROUP BY i.employer_id
            ) interns ON interns.employer_id = e.employer_id
            LEFT JOIN (
                SELECT
                    employer_id,
                    AVG((COALESCE(technical_score, 0) + COALESCE(behavioral_score, 0)) / 2) AS avg_rating
                FROM employer_evaluation
                GROUP BY employer_id
            ) ev ON ev.employer_id = e.employer_id
            WHERE 1=1';

        $params = [':adviser_id' => $adviserId];

        $industry = trim((string)($filters['industry'] ?? ''));
        if ($industry !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(e.industry), ""), "Unspecified") = :industry';
            $params[':industry'] = $industry;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $normalizedStatus = strtolower($status);
            if (in_array($normalizedStatus, ['verified', 'approved'], true)) {
                $sql .= ' AND LOWER(COALESCE(NULLIF(TRIM(e.verification_status), ""), "pending")) IN ("approved", "verified")';
            } elseif (in_array($normalizedStatus, ['unverified', 'pending', 'rejected', 'flagged'], true)) {
                $sql .= ' AND LOWER(COALESCE(NULLIF(TRIM(e.verification_status), ""), "pending")) NOT IN ("approved", "verified")';
            } else {
                $sql .= ' AND COALESCE(NULLIF(TRIM(e.verification_status), ""), "Pending") = :verification_status';
                $params[':verification_status'] = $status;
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                COALESCE(e.company_name, "") LIKE :search
                OR COALESCE(e.industry, "") LIKE :search
                OR COALESCE(e.company_address, "") LIKE :search
                OR COALESCE(e.website_url, "") LIKE :search
                OR COALESCE(e.email, "") LIKE :search
                OR COALESCE(e.contact_number, "") LIKE :search
                ' . $contactPersonSearch . '
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY
                CASE COALESCE(NULLIF(TRIM(e.verification_status), ""), "Pending")
                    WHEN "Pending" THEN 0
                    WHEN "Flagged" THEN 1
                    WHEN "Approved" THEN 2
                    WHEN "Rejected" THEN 3
                    ELSE 4
                END,
                e.company_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return adviser_companies_attach_students($pdo, $adviserId, $rows);
    }
}

if (!function_exists('adviser_companies_attach_students')) {
    function adviser_companies_attach_students(PDO $pdo, int $adviserId, array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $employerIds = [];
        foreach ($rows as $row) {
            $employerId = (int)($row['employer_id'] ?? 0);
            if ($employerId > 0) {
                $employerIds[$employerId] = $employerId;
            }
        }

        $studentsByCompany = adviser_companies_get_students_by_company($pdo, $adviserId, array_values($employerIds));

        foreach ($rows as &$row) {
            $employerId = (int)($row['employer_id'] ?? 0);
            $students = $studentsByCompany[$employerId] ?? [];
            $row['students'] = $students;
            $row['student_count'] = count($students);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('adviser_companies_get_students_by_company')) {
    function adviser_companies_get_students_by_company(PDO $pdo, int $adviserId, array $employerIds): array
    {
        $cleanEmployerIds = [];
        foreach ($employerIds as $employerId) {
            $id = (int)$employerId;
            if ($id > 0) {
                $cleanEmployerIds[$id] = $id;
            }
        }

        if (empty($cleanEmployerIds)) {
            return [];
        }

        $ids = array_values($cleanEmployerIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = '
            SELECT *
            FROM (
                SELECT
                    i.employer_id,
                    s.student_id,
                    s.student_number,
                    s.first_name,
                    s.last_name,
                    s.program,
                    s.year_level,
                    i.internship_id,
                    i.title AS internship_title,
                    a.status AS application_status,
                    COALESCE(o.completion_status, "") AS completion_status,
                    COALESCE(o.hours_completed, 0) AS hours_completed,
                    COALESCE(o.hours_required, 0) AS hours_required,
                    o.start_date
                FROM adviser_assignment aa
                INNER JOIN student s ON s.student_id = aa.student_id
                INNER JOIN application a ON a.student_id = s.student_id
                    AND LOWER(COALESCE(a.status, "")) IN ("accepted", "hired")
                INNER JOIN internship i ON i.internship_id = a.internship_id
                LEFT JOIN ojt_record o ON o.student_id = s.student_id
                    AND o.internship_id = i.internship_id
                WHERE aa.adviser_id = ?
                  AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                  AND i.employer_id IN (' . $placeholders . ')

                UNION ALL

                SELECT
                    i.employer_id,
                    s.student_id,
                    s.student_number,
                    s.first_name,
                    s.last_name,
                    s.program,
                    s.year_level,
                    i.internship_id,
                    i.title AS internship_title,
                    COALESCE(a.status, "") AS application_status,
                    COALESCE(o.completion_status, "") AS completion_status,
                    COALESCE(o.hours_completed, 0) AS hours_completed,
                    COALESCE(o.hours_required, 0) AS hours_required,
                    o.start_date
                FROM adviser_assignment aa
                INNER JOIN student s ON s.student_id = aa.student_id
                INNER JOIN ojt_record o ON o.student_id = s.student_id
                INNER JOIN internship i ON i.internship_id = o.internship_id
                LEFT JOIN application a ON a.student_id = s.student_id
                    AND a.internship_id = i.internship_id
                WHERE aa.adviser_id = ?
                  AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                  AND i.employer_id IN (' . $placeholders . ')
            ) placement_rows
            ORDER BY employer_id ASC, last_name ASC, first_name ASC, internship_title ASC';

        $params = array_merge([$adviserId], $ids, [$adviserId], $ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        $seen = [];
        foreach ($rows as $row) {
            $employerId = (int)($row['employer_id'] ?? 0);
            $studentId = (int)($row['student_id'] ?? 0);
            $internshipId = (int)($row['internship_id'] ?? 0);
            if ($employerId <= 0 || $studentId <= 0 || $internshipId <= 0) {
                continue;
            }

            $key = $employerId . ':' . $studentId . ':' . $internshipId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $completionStatus = trim((string)($row['completion_status'] ?? ''));
            $applicationStatus = trim((string)($row['application_status'] ?? ''));
            $hoursCompleted = (float)($row['hours_completed'] ?? 0);
            $hoursRequired = (float)($row['hours_required'] ?? 0);

            if (!isset($grouped[$employerId])) {
                $grouped[$employerId] = [];
            }

            $grouped[$employerId][] = [
                'student_id' => $studentId,
                'student_number' => trim((string)($row['student_number'] ?? '')),
                'student_name' => $studentName !== '' ? $studentName : 'Unnamed Student',
                'program' => trim((string)($row['program'] ?? '')),
                'year_level' => (int)($row['year_level'] ?? 0),
                'internship_id' => $internshipId,
                'internship_title' => trim((string)($row['internship_title'] ?? 'Internship')),
                'application_status' => $applicationStatus,
                'completion_status' => $completionStatus !== '' ? $completionStatus : ($applicationStatus !== '' ? $applicationStatus : 'Assigned'),
                'hours_completed' => $hoursCompleted,
                'hours_required' => $hoursRequired,
                'start_date' => (string)($row['start_date'] ?? ''),
            ];
        }

        return $grouped;
    }
}
