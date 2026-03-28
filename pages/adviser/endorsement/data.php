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

if (!function_exists('adviser_endorsement_sync_missing_pending')) {
    function adviser_endorsement_sync_missing_pending(PDO $pdo, int $adviserId): void
    {
        if ($adviserId <= 0) {
            return;
        }

        $sql = '
            INSERT INTO endorsement (application_id, adviser_id, status, moa_status, notes, created_at)
            SELECT seed.application_id, seed.adviser_id, "Pending", "Not Started", NULL, NOW()
            FROM (
                SELECT DISTINCT
                    a.application_id,
                    aa.adviser_id
                FROM application a
                INNER JOIN adviser_assignment aa ON aa.student_id = a.student_id
                WHERE aa.adviser_id = :adviser_id
                  AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
                                    AND LOWER(COALESCE(a.status, "")) = "shortlisted"
            ) AS seed
            WHERE NOT EXISTS (
                SELECT 1
                FROM endorsement e
                WHERE e.application_id = seed.application_id
                  AND e.adviser_id = seed.adviser_id
            )';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':adviser_id' => $adviserId]);
    }
}

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

        try {
            adviser_endorsement_sync_missing_pending($pdo, $adviserId);
        } catch (Throwable $e) {
            // Keep the page usable even if sync fails; queries below still run.
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
