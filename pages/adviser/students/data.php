<?php
/**
 * Purpose: Adviser students page data orchestrator.
 * Tables/columns used: Delegates to students modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/students_query.php';

if (!function_exists('getAdviserStudentsPageData')) {
    function getAdviserStudentsPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'search' => trim((string)($filters['search'] ?? '')),
            'department' => trim((string)($filters['department'] ?? '')),
            'status' => trim((string)($filters['status'] ?? '')),
        ];

        return [
            'selected' => $selected,
            'filter_options' => adviser_students_get_filter_options($pdo, $adviserId),
            'rows' => adviser_students_get_rows($pdo, $adviserId, $selected),
        ];
    }
}
