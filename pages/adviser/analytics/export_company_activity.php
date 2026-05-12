<?php
/**
 * Purpose: CSV export for adviser company activity analytics.
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
    $report = adviser_analytics_get_company_activity_report($pdo, $adviserId);
    $rows = $report['rows'] ?? [];
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to generate company activity report right now.';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'adviser-company-activity-report-' . date('Ymd-His') . '.csv';
$headers = [
    'Company',
    'Activity Status',
    'Activity Detail',
    'Verification Status',
    'Industry',
    'Contact Person',
    'Email',
    'Phone',
    'Open Postings',
    'Open Slots',
    'Assigned Students',
    'Active Interns',
    'Completed Interns',
    'Latest Placement',
];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $headers);

foreach ($rows as $row) {
    fputcsv($output, [
        trim((string)($row['company_name'] ?? 'Company')),
        trim((string)($row['activity_status'] ?? 'Pending')),
        trim((string)($row['activity_detail'] ?? '')),
        trim((string)($row['verification_status'] ?? 'Pending')),
        trim((string)($row['industry'] ?? 'General')),
        trim((string)($row['contact_person_name'] ?? '')),
        trim((string)($row['email'] ?? '')),
        trim((string)($row['contact_number'] ?? '')),
        (string)(int)($row['open_postings'] ?? 0),
        (string)(int)($row['open_slots'] ?? 0),
        (string)(int)($row['student_count'] ?? 0),
        (string)(int)($row['active_interns'] ?? 0),
        (string)(int)($row['completed_interns'] ?? 0),
        adviser_analytics_format_report_date($row['latest_placement_date'] ?? ''),
    ]);
}

fclose($output);
