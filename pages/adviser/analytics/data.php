<?php
// data.php — Analytics data orchestrator

require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/formatters.php';

$adviser_id = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));
$data = [
    'stats' => [
        'placement_rate' => 0,
        'avg_eval_rating' => 0,
        'avg_ojt_hours' => 0,
        'completion_rate' => 0,
        'total_students' => 0,
        'placed' => 0,
        'searching' => 0,
        'completed_ojt' => 0,
        'in_progress' => 0,
        'hiring_companies' => 0,
    ],
    'placement_by_dept' => [],
    'top_companies' => [],
    'top_skills' => [],
    'trends' => [],
];

if ($adviser_id > 0) {
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
