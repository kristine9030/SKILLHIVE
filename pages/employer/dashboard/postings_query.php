<?php
/**
 * Purpose: Fetches paginated employer postings for the dashboard with applicant totals per posting, prioritizing open ones first.
 * Tables/columns used: internship(internship_id, employer_id, title, location, duration_weeks, status, posted_at, created_at), application(application_id, internship_id).
 */

if (!function_exists('dashboard_get_active_postings')) {
    function dashboard_get_active_postings(PDO $pdo, int $employerId, int $limit = 3, int $offset = 0): array
    {
        $safeLimit = max(1, min(20, $limit));
        $safeOffset = max(0, $offset);

        $sql = '
            SELECT
                i.internship_id,
                i.title,
                i.location,
                i.duration_weeks,
                i.status,
                COALESCE(i.posted_at, i.created_at) AS posted_at,
                COUNT(a.application_id) AS applicants_count
             FROM internship i
             LEFT JOIN application a ON a.internship_id = i.internship_id
             WHERE i.employer_id = :employer_id
             GROUP BY i.internship_id, i.title, i.location, i.duration_weeks, i.status, COALESCE(i.posted_at, i.created_at)
             ORDER BY CASE WHEN LOWER(TRIM(COALESCE(i.status, \'\'))) = \'open\' THEN 0 ELSE 1 END,
                      posted_at DESC,
                      i.internship_id DESC
             LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employer_id' => $employerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('dashboard_get_postings_total')) {
    function dashboard_get_postings_total(PDO $pdo, int $employerId): int
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
