<?php
/**
 * Purpose: Endorsement page data orchestrator for filters, pending cards, and history table.
 * Tables/columns used: Delegates to endorsement modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/pending_query.php';
require_once __DIR__ . '/history_query.php';

if (!function_exists('getAdviserEndorsementPageData')) {
    function getAdviserEndorsementPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'status' => trim((string)($filters['status'] ?? '')),
            'department' => trim((string)($filters['department'] ?? '')),
            'search' => trim((string)($filters['search'] ?? '')),
        ];

        return [
            'selected' => $selected,
            'filter_options' => adviser_endorsement_get_filter_options($pdo, $adviserId),
            'pending' => adviser_endorsement_get_pending($pdo, $adviserId, $selected),
            'history' => adviser_endorsement_get_history($pdo, $adviserId, $selected),
        ];
    }
}
