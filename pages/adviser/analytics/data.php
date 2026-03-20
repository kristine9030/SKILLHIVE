<?php
// data.php — Analytics data orchestrator

require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/formatters.php';

$adviser_id = $_SESSION['user_id'] ?? null;
$data = [
    'stats' => [],
    'placement_by_dept' => [],
    'top_companies' => [],
    'top_skills' => [],
    'trends' => []
];

if ($adviser_id) {
    try {
        $data['stats'] = adviser_analytics_get_stats($pdo, $adviser_id);
        $data['placement_by_dept'] = adviser_analytics_get_placement_by_dept($pdo, $adviser_id);
        $data['top_companies'] = adviser_analytics_get_top_companies($pdo, $adviser_id);
        $data['top_skills'] = adviser_analytics_get_top_skills($pdo, $adviser_id);
        $data['trends'] = adviser_analytics_get_trends($pdo, $adviser_id);
    } catch (Exception $e) {
        error_log("Analytics data orchestrator error: " . $e->getMessage());
    }
}
