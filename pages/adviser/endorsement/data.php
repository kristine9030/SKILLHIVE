<?php
/**
 * Purpose: Endorsement page data orchestrator for filters, pending cards, and history table.
 * Tables/columns used: Delegates to endorsement modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/pending_query.php';
require_once __DIR__ . '/approved_query.php';
require_once __DIR__ . '/all_query.php';

if (!function_exists('getAdviserEndorsementPageData')) {
    function getAdviserEndorsementPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'tab' => trim((string)($filters['tab'] ?? 'pending')),
            'status' => trim((string)($filters['status'] ?? '')),
            'department' => trim((string)($filters['department'] ?? '')),
            'search' => trim((string)($filters['search'] ?? '')),
        ];

        if (!in_array($selected['tab'], ['pending', 'approved', 'all'], true)) {
            $selected['tab'] = 'pending';
        }

        $pendingRows = [];
        $approvedRows = [];
        $allRows = [];

        try {
            $pendingRows = adviser_endorsement_get_pending($pdo, $adviserId, $selected);
        } catch (Throwable $e) {
            $pendingRows = [];
        }

        try {
            $approvedRows = adviser_endorsement_get_approved($pdo, $adviserId, $selected);
        } catch (Throwable $e) {
            $approvedRows = [];
        }

        try {
            $allRows = adviser_endorsement_get_all($pdo, $adviserId, $selected);
        } catch (Throwable $e) {
            $allRows = [];
        }

        return [
            'selected' => $selected,
            'filter_options' => adviser_endorsement_get_filter_options($pdo, $adviserId),
            'pending' => $pendingRows,
            'approved' => $approvedRows,
            'all' => $allRows,
        ];
    }
}
