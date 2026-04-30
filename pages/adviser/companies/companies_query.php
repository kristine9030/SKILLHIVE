<?php
/**
 * Purpose: Loads companies for adviser companies page with adviser-relevant intern metrics.
 * Tables/columns used: employer(employer_id, company_name, industry, company_address, email, contact_number, verification_status, company_badge_status, website_url, created_at), internship(internship_id, employer_id), ojt_record(student_id, internship_id, completion_status), adviser_assignment(adviser_id, student_id, status), employer_evaluation(employer_id, technical_score, behavioral_score).
 */

if (!function_exists('adviser_companies_get_rows')) {
    function adviser_companies_get_rows(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $sql = '
            SELECT
                e.employer_id,
                e.company_name,
                e.industry,
                e.company_address,
                e.email,
                e.contact_number,
                e.verification_status,
                e.company_badge_status,
                e.website_url,
                e.created_at,
                COUNT(DISTINCT CASE
                    WHEN aa.student_id IS NOT NULL AND COALESCE(NULLIF(TRIM(o.completion_status), ""), "Ongoing") = "Ongoing"
                    THEN o.student_id
                    ELSE NULL
                END) AS current_interns,
                AVG((COALESCE(ev.technical_score, 0) + COALESCE(ev.behavioral_score, 0)) / 2) AS avg_rating
            FROM employer e
            LEFT JOIN internship i ON i.employer_id = e.employer_id
            LEFT JOIN ojt_record o ON o.internship_id = i.internship_id
            LEFT JOIN adviser_assignment aa ON aa.student_id = o.student_id
                AND aa.adviser_id = :adviser_id
                AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
            LEFT JOIN employer_evaluation ev ON ev.employer_id = e.employer_id
            WHERE 1=1';

        $params = [':adviser_id' => $adviserId];

        $industry = trim((string)($filters['industry'] ?? ''));
        if ($industry !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(e.industry), ""), "Unspecified") = :industry';
            $params[':industry'] = $industry;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(e.verification_status), ""), "Pending") = :verification_status';
            $params[':verification_status'] = $status;
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
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY
                e.employer_id,
                e.company_name,
                e.industry,
                e.company_address,
                e.email,
                e.contact_number,
                e.verification_status,
                e.company_badge_status,
                e.website_url,
                e.created_at
            ORDER BY
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
