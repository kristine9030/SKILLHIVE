<?php
/**
 * Purpose: Loads internship postings for an employer, including applicant totals per posting.
 * Tables/columns used: internship(internship_id, employer_id, title, location, duration_weeks, status, posted_at, created_at), application(application_id, internship_id).
 */

if (!function_exists('getEmployerInternshipPostings')) {
    function getEmployerInternshipPostings(PDO $pdo, int $employerId, int $limit = 20, int $offset = 0): array
    {
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);

        $sql = '
            SELECT
                i.internship_id,
                i.title,
                i.description,
                i.location,
                i.duration_weeks,
                i.allowance,
                i.work_setup,
                i.slots_available,
                i.status,
                COALESCE(i.posted_at, i.created_at) AS posted_at,
                COUNT(a.application_id) AS applicants_count
            FROM internship i
            LEFT JOIN application a ON a.internship_id = i.internship_id
            WHERE i.employer_id = :employer_id
            GROUP BY i.internship_id, i.title, i.description, i.location, i.duration_weeks, i.allowance, i.work_setup, i.slots_available, i.status, posted_at
            ORDER BY posted_at DESC
            LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('getEmployerInternshipPostingsTotal')) {
    function getEmployerInternshipPostingsTotal(PDO $pdo, int $employerId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM internship i
             WHERE i.employer_id = :employer_id'
        );
        $stmt->execute([':employer_id' => $employerId]);
        return (int)$stmt->fetchColumn();
    }
}
