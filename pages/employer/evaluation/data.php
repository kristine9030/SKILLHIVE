<?php
/**
 * Purpose: Evaluation data orchestrator that bundles intern options, evaluation history, and evaluation summary data for the page.
 * Tables/columns used: Delegates to modules that read ojt_record(student_id, internship_id), internship(internship_id, employer_id, title), student(student_id, first_name, last_name), employer_evaluation(evaluation_id, student_id, internship_id, employer_id, technical_score, behavioral_score, comments, recommendation_status, evaluation_date).
 */

require_once __DIR__ . '/evaluation_helpers.php';
require_once __DIR__ . '/intern_options_query.php';
require_once __DIR__ . '/evaluation_save.php';
require_once __DIR__ . '/evaluation_history_query.php';
require_once __DIR__ . '/evaluation_summary_query.php';

if (!function_exists('getEmployerEvaluationPageData')) {
    function getEmployerEvaluationPageData(PDO $pdo, int $employerId, array $filters = []): array
    {
        $internshipId = (int)($filters['internship_id'] ?? 0);

        return [
            'internship_options' => getEmployerEvaluationInternships($pdo, $employerId),
            'intern_options'     => getEmployerInternOptions($pdo, $employerId, $internshipId),
            'history'            => evaluation_get_history($pdo, $employerId, $internshipId),
            'summary'            => evaluation_get_summary($pdo, $employerId, $internshipId),
            'selected'           => [
                'internship_id' => $internshipId,
            ],
        ];
    }
}
