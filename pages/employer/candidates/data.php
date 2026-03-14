<?php
/**
 * Purpose: Candidates data orchestrator that combines filters, dropdown metadata, candidate rows, and grouped student skills.
 * Tables/columns used: Delegates to modules that read application(application_id, internship_id, student_id, status, compatibility_score, application_date), internship(internship_id, employer_id, title), student(student_id, first_name, last_name, program, year_level, internship_readiness_score), student_skill(student_id, skill_id, verified), skill(skill_id, skill_name).
 */

require_once __DIR__ . '/filter_helpers.php';
require_once __DIR__ . '/meta_queries.php';
require_once __DIR__ . '/candidate_queries.php';
require_once __DIR__ . '/skill_queries.php';
require_once __DIR__ . '/actions.php';

if (!function_exists('getEmployerCandidatesData')) {
    function getEmployerCandidatesData(PDO $pdo, int $employerId, array $filters = []): array
    {
        $parsedFilters = candidates_parse_filters($filters);
        $positions = candidates_get_positions($pdo, $employerId);
        $statuses = candidates_get_statuses($pdo, $employerId);
        $candidates = candidates_get_candidate_rows($pdo, $employerId, $parsedFilters);

        $studentIds = candidates_collect_student_ids($candidates);
        $skillsByStudent = candidates_get_skills_by_student($pdo, $studentIds, 3);

        return [
            'candidates' => $candidates,
            'skills_by_student' => $skillsByStudent,
            'positions' => $positions,
            'statuses' => $statuses,
            'selected' => [
                'search' => $parsedFilters['search'],
                'position' => $parsedFilters['position'],
                'status' => $parsedFilters['status'],
                'sort' => $parsedFilters['sort'],
            ],
        ];
    }
}
