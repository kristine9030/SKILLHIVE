<?php
/**
 * Purpose: CSV export for adviser student performance analytics.
 * Tables/columns used: Delegates to analytics queries.
 */

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/formatters.php';

$baseUrl = '/SkillHive';
$role = strtolower(trim((string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))));
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'adviser' || $adviserId <= 0) {
    http_response_code(403);
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

try {
    $report = adviser_analytics_get_student_performance_report($pdo, $adviserId);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to generate student performance report right now.';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'adviser-student-performance-report-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Section', 'Student', 'Student Number', 'Program', 'Company', 'Internship', 'Metric', 'Value', 'Details']);

foreach (($report['early_finishers'] ?? []) as $row) {
    fputcsv($output, [
        'Early Finishers',
        (string)($row['student_name'] ?? 'Student'),
        (string)($row['student_number'] ?? ''),
        (string)($row['program'] ?? ''),
        (string)($row['company_name'] ?? ''),
        (string)($row['internship_title'] ?? ''),
        'Days Early',
        (string)(int)($row['days_early'] ?? 0),
        'Completed on ' . adviser_analytics_format_report_date($row['completion_date'] ?? '') . '; Expected end ' . adviser_analytics_format_report_date($row['end_date'] ?? '') . '; Hours ' . (string)($row['hours_text'] ?? ''),
    ]);
}

foreach (($report['punctual_students'] ?? []) as $row) {
    fputcsv($output, [
        'Most Punctual',
        (string)($row['student_name'] ?? 'Student'),
        (string)($row['student_number'] ?? ''),
        (string)($row['program'] ?? ''),
        (string)($row['company_name'] ?? ''),
        (string)($row['internship_title'] ?? ''),
        'On-Time Rate',
        (string)(int)($row['on_time_rate'] ?? 0) . '%',
        (string)(int)($row['on_time_logs'] ?? 0) . ' on-time logs; ' . (string)(int)($row['late_logs'] ?? 0) . ' late logs; ' . (string)(int)($row['total_logs'] ?? 0) . ' total logs',
    ]);
}

foreach (($report['needs_attention'] ?? []) as $row) {
    fputcsv($output, [
        'Needs Attention',
        (string)($row['student_name'] ?? 'Student'),
        (string)($row['student_number'] ?? ''),
        (string)($row['program'] ?? ''),
        (string)($row['company_name'] ?? ''),
        (string)($row['internship_title'] ?? ''),
        'Reason',
        (string)($row['reason_text'] ?? ''),
        'Overall ' . adviser_analytics_score_text($row['overall_score'] ?? null) . '; Technical ' . adviser_analytics_score_text($row['technical_score'] ?? null) . '; Behavioral ' . adviser_analytics_score_text($row['behavioral_score'] ?? null) . '; Evaluated ' . adviser_analytics_format_report_date($row['evaluation_date'] ?? ''),
    ]);
}

fclose($output);
