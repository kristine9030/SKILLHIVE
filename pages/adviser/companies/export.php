<?php
/**
 * Purpose: Clean download endpoint for adviser company exports.
 * Tables/columns used: Delegates to companies/data.php.
 */

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/data.php';

$baseUrl = '/SkillHive';
$role = (string)($_SESSION['role'] ?? '');
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'adviser' || $adviserId <= 0) {
    http_response_code(403);
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? $_GET['export'] ?? 'csv')));
$filters = [
    'industry' => trim((string)($_GET['industry'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
];

try {
    $pageData = getAdviserCompaniesPageData($pdo, $adviserId, $filters);
    $rows = $pageData['rows'] ?? [];
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to generate partner companies export right now.';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

if ($format === 'doc' || $format === 'document' || $format === 'word') {
    adviser_companies_export_doc($rows, $filters);
    exit;
}

adviser_companies_export_csv($rows);
exit;

function adviser_companies_export_filename(string $extension): string
{
    return 'adviser-company-verification-report-' . date('Ymd-His') . '.' . $extension;
}

function adviser_companies_export_rows(array $rows): array
{
    $exportRows = [];

    foreach ($rows as $row) {
        $documents = adviser_companies_documents_meta($row);
        $risk = adviser_companies_risk_meta($row);
        $actionMeta = adviser_companies_action_meta($row);
        $acceptingMeta = adviser_companies_accepting_status_meta($row);
        $students = is_array($row['students'] ?? null) ? $row['students'] : [];

        $exportRows[] = [
            'Company' => trim((string)($row['company_name'] ?? 'Company')),
            'Contact Person' => adviser_companies_contact_person_label($row),
            'Industry' => trim((string)($row['industry'] ?? '')) ?: 'Unspecified',
            'Verification' => adviser_companies_verification_label((string)($row['verification_status'] ?? 'Pending')),
            'Verification Detail' => (string)$acceptingMeta['detail'],
            'Submitted' => adviser_companies_format_date((string)($row['created_at'] ?? '')),
            'Documents' => (string)$documents['label'],
            'Risk' => (string)$risk['label'],
            'Suggested Action' => (string)$actionMeta['label'],
            'Email' => trim((string)($row['email'] ?? '')),
            'Phone' => trim((string)($row['contact_number'] ?? '')),
            'Website' => trim((string)($row['website_url'] ?? '')),
            'Address' => trim((string)($row['company_address'] ?? '')),
            'Current Interns' => (string)(int)($row['current_interns'] ?? 0),
            'Open Postings' => (string)(int)($row['open_postings'] ?? 0),
            'Listed Slots' => (string)(int)($row['open_slots'] ?? 0),
            'Assigned Students' => (string)count($students),
            'Student Names' => adviser_companies_student_export_text($students),
            'Average Rating' => adviser_companies_rating_text($row['avg_rating'] ?? null),
        ];
    }

    return $exportRows;
}

function adviser_companies_export_csv(array $rows): void
{
    $headers = [
        'Company',
        'Contact Person',
        'Industry',
        'Verification',
        'Verification Detail',
        'Submitted',
        'Documents',
        'Risk',
        'Suggested Action',
        'Email',
        'Phone',
        'Website',
        'Address',
        'Current Interns',
        'Open Postings',
        'Listed Slots',
        'Assigned Students',
        'Student Names',
        'Average Rating',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . adviser_companies_export_filename('csv') . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        return;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);

    foreach (adviser_companies_export_rows($rows) as $exportRow) {
        fputcsv($output, array_values($exportRow));
    }

    fclose($output);
}

function adviser_companies_export_doc(array $rows, array $filters): void
{
    header('Content-Type: application/vnd.ms-word; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . adviser_companies_export_filename('doc') . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $activeFilters = [];
    foreach (['industry' => 'Industry', 'status' => 'Verification', 'search' => 'Search'] as $key => $label) {
        $value = trim((string)($filters[$key] ?? ''));
        if ($value !== '') {
            $activeFilters[] = $label . ': ' . $value;
        }
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Adviser Company Verification Report</title>';
    echo '<style>body{font-family:Arial,sans-serif;color:#111;padding:18px}.report-head{border-bottom:2px solid #e5e7eb;padding-bottom:10px;margin-bottom:14px}.report-brand{font-size:11px;color:#6b7280;letter-spacing:.08em;text-transform:uppercase}.report-title{font-size:22px;margin:4px 0 0}.report-sub{margin:8px 0;color:#444}.report-filters{margin:0 0 14px;color:#555;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:8px 10px;font-size:12px;text-align:left;vertical-align:top}th{background:#f3f4f6;font-weight:700}</style>';
    echo '</head><body>';
    echo '<div class="report-head"><div class="report-brand">SkillHive Adviser</div><h1 class="report-title">Partner Companies Report</h1></div>';
    echo '<p class="report-sub">Generated on ' . adviser_companies_escape(date('M j, Y g:i A')) . '</p>';
    echo '<p class="report-filters">' . adviser_companies_escape(!empty($activeFilters) ? implode(' | ', $activeFilters) : 'Filters: All companies') . '</p>';
    echo '<table><thead><tr>';

    $headers = [
        'Company',
        'Contact Person',
        'Industry',
        'Verification',
        'Verification Detail',
        'Submitted',
        'Documents',
        'Risk',
        'Suggested Action',
        'Email',
        'Phone',
        'Current Interns',
        'Open Postings',
        'Listed Slots',
        'Assigned Students',
        'Student Names',
        'Average Rating',
    ];

    foreach ($headers as $header) {
        echo '<th>' . adviser_companies_escape($header) . '</th>';
    }

    echo '</tr></thead><tbody>';

    $exportRows = adviser_companies_export_rows($rows);
    if (empty($exportRows)) {
        echo '<tr><td colspan="' . count($headers) . '">No matching companies found.</td></tr>';
    } else {
        foreach ($exportRows as $exportRow) {
            echo '<tr>';
            foreach ($headers as $header) {
                echo '<td>' . adviser_companies_escape((string)($exportRow[$header] ?? '')) . '</td>';
            }
            echo '</tr>';
        }
    }

    echo '</tbody></table></body></html>';
}
