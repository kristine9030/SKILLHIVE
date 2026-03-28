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
require_once __DIR__ . '/../monitoring/formatters.php';
require_once __DIR__ . '/../monitoring/monitoring_query.php';

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
                'placed_students' => 0,
                'endorsed' => 0,
                'pending_review' => 0,
                'at_risk_students' => 0,
                'partner_companies' => 0,
            ],
            'departments' => [],
            'recent_activity' => [],
            'pending_endorsements' => [],
            'at_risk_students' => [],
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

        try {
            $monitoringRows = adviser_monitoring_get_rows($pdo, $adviserId);
            $atRiskCount = 0;
            $attentionRows = [];

            foreach ($monitoringRows as $row) {
                $statusLabel = (string)($row['status_label'] ?? '');
                if ($statusLabel === 'At Risk') {
                    $atRiskCount++;
                }

                if ($statusLabel !== 'At Risk' && $statusLabel !== 'Warning') {
                    continue;
                }

                $row['risk_summary'] = adviser_dashboard_risk_summary($row);
                $attentionRows[] = $row;
            }

            usort($attentionRows, static function (array $left, array $right): int {
                $leftWeight = (($left['status_label'] ?? '') === 'At Risk') ? 0 : 1;
                $rightWeight = (($right['status_label'] ?? '') === 'At Risk') ? 0 : 1;

                if ($leftWeight !== $rightWeight) {
                    return $leftWeight <=> $rightWeight;
                }

                $leftHours = (float)($left['hours_completed'] ?? 0);
                $rightHours = (float)($right['hours_completed'] ?? 0);
                return $leftHours <=> $rightHours;
            });

            $data['stats']['at_risk_students'] = $atRiskCount;
            $data['at_risk_students'] = array_slice($attentionRows, 0, 3);
        } catch (Throwable $e) {
        }

        return $data;
    }
}
