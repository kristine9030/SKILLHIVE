<?php
/**
 * Purpose: Adviser evaluation page data orchestrator.
 * Tables/columns used: Delegates to evaluation modules.
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/filters_query.php';
require_once __DIR__ . '/rows_query.php';
require_once __DIR__ . '/save.php';

if (!function_exists('getAdviserEvaluationPageData')) {
    function getAdviserEvaluationPageData(PDO $pdo, int $adviserId, array $filters = []): array
    {
        $selected = [
            'department' => trim((string)($filters['department'] ?? '')),
            'status' => trim((string)($filters['status'] ?? '')),
            'search' => trim((string)($filters['search'] ?? '')),
        ];

        $rows = adviser_evaluation_get_rows($pdo, $adviserId, $selected);

        $gradeTargets = [];
        foreach ($rows as $row) {
            if (!empty($row['is_eligible'])) {
                $gradeTargets[] = [
                    'student_id' => (int)($row['student_id'] ?? 0),
                    'internship_id' => (int)($row['internship_id'] ?? 0),
                    'label' => trim((string)($row['student_name'] ?? 'Student') . ' — ' . (string)($row['program'] ?? 'N/A') . ' (' . (string)($row['company_name'] ?? 'Company') . ')'),
                ];
            }
        }

        return [
            'selected' => $selected,
            'filter_options' => adviser_evaluation_get_filter_options($pdo, $adviserId),
            'rows' => $rows,
            'grade_targets' => $gradeTargets,
            'grade_options' => adviser_evaluation_grade_options(),
        ];
    }
}
