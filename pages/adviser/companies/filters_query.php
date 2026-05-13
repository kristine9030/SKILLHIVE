<?php
/**
 * Purpose: Loads filter options for adviser companies page.
 * Tables/columns used: employer(industry, verification_status).
 */

if (!function_exists('adviser_companies_get_filter_options')) {
    function adviser_companies_get_filter_options(PDO $pdo): array
    {
        $industryStmt = $pdo->query(
            'SELECT DISTINCT COALESCE(NULLIF(TRIM(industry), ""), "Unspecified") AS industry
             FROM employer
             ORDER BY industry ASC'
        );
        $industries = $industryStmt ? ($industryStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

        return [
            'industries' => $industries,
            'statuses' => ['Verified', 'Unverified'],
        ];
    }
}
