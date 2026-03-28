<?php
/**
 * Purpose: Adviser companies page data orchestrator.
 * Tables/columns used: Delegates to companies modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/companies_query.php';
require_once __DIR__ . '/actions.php';

if (!function_exists('getAdviserCompaniesPageData')) {
    function getAdviserCompaniesPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'industry' => trim((string)($filters['industry'] ?? '')),
            'status' => trim((string)($filters['status'] ?? '')),
            'search' => trim((string)($filters['search'] ?? '')),
        ];

        return [
            'selected' => $selected,
            'filter_options' => adviser_companies_get_filter_options($pdo),
            'rows' => adviser_companies_get_rows($pdo, $adviserId, $selected),
        ];
    }
}
