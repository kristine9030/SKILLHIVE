<?php
/**
 * Purpose: Dashboard data orchestrator that composes company, stats, monthly metrics, postings, applicants, and interview data for the page.
 * Tables/columns used: Delegates to modules that read employer(employer_id, company_name, verification_status, company_badge_status), internship(internship_id, employer_id, title, location, duration_weeks, status, posted_at, created_at), application(application_id, internship_id, student_id, status, compatibility_score, application_date, updated_at), interview(application_id, interview_date, interview_status), student(student_id, first_name, last_name).
 */

require_once __DIR__ . '/formatters.php';
require_once __DIR__ . '/company_query.php';
require_once __DIR__ . '/stats_query.php';
require_once __DIR__ . '/monthly_query.php';
require_once __DIR__ . '/postings_query.php';
require_once __DIR__ . '/applicants_query.php';
require_once __DIR__ . '/interviews_query.php';

if (!function_exists('getEmployerDashboardData')) {
    function getEmployerDashboardData(PDO $pdo, int $employerId, int $postingsPage = 1, int $postingsPerPage = 5): array
    {
        $safePostingsPage = max(1, $postingsPage);
        $safePostingsPerPage = max(1, min(20, $postingsPerPage));

        $data = [
            'company' => [
                'company_name' => 'Employer',
                'verification_status' => 'pending',
                'company_badge_status' => null,
            ],
            'stats' => [
                'active_postings' => 0,
                'total_applicants' => 0,
                'week_applicants' => 0,
                'interviews' => 0,
                'hired' => 0,
            ],
            'month' => [
                'applications_received' => 0,
                'interviews_conducted' => 0,
                'offers_extended' => 0,
                'acceptance_rate' => 0,
            ],
            'postings' => [],
            'postings_pagination' => [
                'current_page' => 1,
                'per_page' => $safePostingsPerPage,
                'total_items' => 0,
                'total_pages' => 1,
            ],
            'recent_applicants' => [],
            'upcoming_interviews' => [],
        ];

        $data['company'] = dashboard_get_company_data($pdo, $employerId);
        $data['stats'] = dashboard_get_stats($pdo, $employerId);
        $data['month'] = dashboard_get_monthly_metrics($pdo, $employerId);
        $totalPostings = dashboard_get_postings_total($pdo, $employerId);
        $totalPages = max(1, (int)ceil($totalPostings / $safePostingsPerPage));
        $currentPage = min($safePostingsPage, $totalPages);
        $offset = ($currentPage - 1) * $safePostingsPerPage;

        $data['postings'] = dashboard_get_active_postings($pdo, $employerId, $safePostingsPerPage, $offset);
        $data['postings_pagination'] = [
            'current_page' => $currentPage,
            'per_page' => $safePostingsPerPage,
            'total_items' => $totalPostings,
            'total_pages' => $totalPages,
        ];
        $data['recent_applicants'] = dashboard_get_recent_applicants($pdo, $employerId, 3);
        $data['upcoming_interviews'] = dashboard_get_upcoming_interviews($pdo, $employerId, 2);

        return $data;
    }
}
