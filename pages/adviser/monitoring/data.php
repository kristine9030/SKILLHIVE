<?php
/**
 * Purpose: Adviser monitoring page data orchestrator.
 * Tables/columns used: Delegates to monitoring modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/monitoring_query.php';

if (!function_exists('getAdviserMonitoringPageData')) {
    function getAdviserMonitoringPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'search' => trim((string)($filters['search'] ?? '')),
            'company' => trim((string)($filters['company'] ?? '')),
            'progress' => trim((string)($filters['progress'] ?? '')),
        ];

        return [
            'selected' => $selected,
            'filter_options' => adviser_monitoring_get_filter_options($pdo, $adviserId),
            'rows' => adviser_monitoring_get_rows($pdo, $adviserId, $selected),
            'map_students' => adviser_monitoring_get_map_students($pdo, $adviserId),
        ];
    }
}
