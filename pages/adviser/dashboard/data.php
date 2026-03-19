<?php
/**
 * Purpose: Adviser dashboard data orchestrator that bundles modular query results for the view.
 * Tables/columns used: Delegates to adviser dashboard modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/profile_query.php';
require_once __DIR__ . '/stats_query.php';
require_once __DIR__ . '/departments_query.php';
require_once __DIR__ . '/activity_query.php';
require_once __DIR__ . '/endorsements_query.php';

if (!function_exists('getAdviserDashboardData')) {
    function getAdviserDashboardData(PDO $pdo, int $adviserId): array
    {
        $data = [
            'profile' => [
                'adviser_id' => $adviserId,
                'first_name' => '',
                'last_name' => '',
                'department' => '',
                'email' => '',
            ],
            'stats' => [
                'my_students' => 0,
                'endorsed' => 0,
                'pending_review' => 0,
                'partner_companies' => 0,
            ],
            'departments' => [],
            'recent_activity' => [],
            'pending_endorsements' => [],
        ];

        try {
            $data['profile'] = adviser_dashboard_get_profile($pdo, $adviserId);
        } catch (Throwable $e) {
        }

        try {
            $data['stats'] = adviser_dashboard_get_stats($pdo, $adviserId);
        } catch (Throwable $e) {
        }

        try {
            $data['departments'] = adviser_dashboard_get_departments($pdo, $adviserId, 4);
        } catch (Throwable $e) {
        }

        try {
            $data['recent_activity'] = adviser_dashboard_get_recent_activity($pdo, $adviserId, 4);
        } catch (Throwable $e) {
        }

        try {
            $data['pending_endorsements'] = adviser_dashboard_get_pending_endorsements($pdo, $adviserId, 2);
        } catch (Throwable $e) {
        }

        return $data;
    }
}