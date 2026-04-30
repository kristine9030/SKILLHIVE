<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/monitoring/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';

$currentFilters = [
  'search' => trim((string)($_REQUEST['search'] ?? '')),
  'company' => trim((string)($_REQUEST['company'] ?? '')),
  'progress' => trim((string)($_REQUEST['progress'] ?? '')),
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $adviserId > 0) {
  $action = trim((string)($_POST['action'] ?? ''));
  $recordId = (int)($_POST['record_id'] ?? 0);

  if ($action === 'approve_all_logs') {
    try {
      $result = adviser_monitoring_approve_all_logs($pdo, $adviserId, $recordId);
      if (!empty($result['success'])) {
        $_SESSION['status'] = 'All logs approved and hours updated.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to approve logs.');
      }
    } catch (Throwable $e) {
      $errorMessage = 'Action failed. Please try again.';
    }
  }

  if ($errorMessage === '') {
    $query = http_build_query([
      'page' => 'adviser/monitoring',
      'search' => (string)$currentFilters['search'],
      'company' => (string)$currentFilters['company'],
      'progress' => (string)$currentFilters['progress'],
    ]);
    header('Location: ' . $baseUrl . '/layout.php?' . $query);
    exit;
  }
}

$pageData = [
  'selected' => ['search' => '', 'company' => '', 'progress' => ''],
  'filter_options' => ['companies' => [], 'progresses' => []],
  'rows' => [],
  'map_students' => [],
];

if ($adviserId > 0) {
  try {
    $pageData = getAdviserMonitoringPageData($pdo, $adviserId, $currentFilters);
  } catch (Throwable $e) {
    $pageData = $pageData;
  }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
$mapStudents = $pageData['map_students'] ?? [];

$acceptedPlacementCount = count($mapStudents);
$ojtCount = 0;
$onTrackCount = 0;
$completedCount = 0;
$warningCount = 0;
$atRiskCount = 0;

foreach ($rows as $row) {
  $recordId = (int)($row['record_id'] ?? 0);
  $completionStatus = strtolower(trim((string)($row['completion_status'] ?? '')));
  if ($recordId > 0 && $completionStatus === 'ongoing') {
    $ojtCount++;
  }

  $statusLabel = (string)($row['status_label'] ?? '');
  if ($statusLabel === 'On Track') {
    $onTrackCount++;
  } elseif ($statusLabel === 'Completed') {
    $completedCount++;
  } elseif ($statusLabel === 'Warning') {
    $warningCount++;
  } elseif ($statusLabel === 'At Risk') {
    $atRiskCount++;
  }
}

$mapLocationGroups = [];
$mapStudentTotal = 0;

foreach ($mapStudents as $student) {
  $location = trim((string)($student['location'] ?? ''));
  $companyName = trim((string)($student['company_name'] ?? ''));

  if ($location === '') {
    continue;
  }

  $locationKey = strtolower(preg_replace('/\s+/', ' ', $companyName . '|' . $location));

  if (!isset($mapLocationGroups[$locationKey])) {
    $mapLocationGroups[$locationKey] = [
      'location' => $location,
      'company_name' => $companyName !== '' ? $companyName : 'No company listed',
      'students' => [],
    ];
  }

  $mapLocationGroups[$locationKey]['students'][] = $student;
  $mapStudentTotal++;
}

$mapLocations = array_values($mapLocationGroups);

if (($_GET['export'] ?? '') === 'csv') {
  if (ob_get_length() !== false && ob_get_length() > 0) {
    ob_clean();
  }

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="monitoring-export-' . date('Ymd-His') . '.csv"');

  $output = fopen('php://output', 'w');
  fwrite($output, "\xEF\xBB\xBF");
  fputcsv($output, ['Student', 'Program', 'Company', 'Hours Logged', 'Progress', 'Last Activity', 'Status']);

  foreach ($rows as $row) {
    $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $hoursCompleted = (int)round((float)($row['hours_completed'] ?? 0));
    $hoursRequired = (int)round((float)($row['hours_required'] ?? 0));

    fputcsv($output, [
      $studentName !== '' ? $studentName : 'Unnamed Student',
      (string)($row['program'] ?? 'N/A'),
      (string)($row['company_name'] ?? 'No Company'),
      $hoursCompleted . ' / ' . $hoursRequired . ' hrs',
      (int)($row['progress_percent'] ?? 0) . '%',
      (string)($row['latest_log_date_label'] ?? 'No activity'),
      (string)($row['status_label'] ?? 'Unknown'),
    ]);
  }

  fclose($output);
  exit;
}

$exportQuery = http_build_query([
  'page' => 'adviser/monitoring',
  'search' => (string)($selected['search'] ?? ''),
  'company' => (string)($selected['company'] ?? ''),
  'progress' => (string)($selected['progress'] ?? ''),
  'export' => 'csv',
]);
$exportUrl = $baseUrl . '/layout.php?' . $exportQuery;
?>

<style>
  .monitoring-page {
    padding: 0 0 24px;
    background: transparent;
    border-radius: 0;
  }

  .monitoring-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
  }

  .monitoring-summary-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    min-height: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: none;
  }

  .monitoring-summary-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .monitoring-summary-icon.total {
    background: #f3f1ff;
    color: #dc2626;
  }

  .monitoring-summary-icon.track {
    background: #e8f8f2;
    color: #12b3ac;
  }

  .monitoring-summary-icon.completed {
    background: #e7f0ff;
    color: #12b3ac;
  }

  .monitoring-summary-icon.warning {
    background: #fff4e3;
    color: #12b3ac;
  }

  .monitoring-summary-icon.risk {
    background: #fdecec;
    color: #12b3ac;
  }

  .monitoring-summary-label {
    margin-top: 10px;
    font-size: .78rem;
    color: var(--text3);
  }

  .monitoring-summary-value {
    font-size: 1.3rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text);
  }

  .monitoring-map-panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: none;
    margin-bottom: 20px;
  }

  .monitoring-map-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
  }

  .monitoring-map-title {
    margin: 0;
    color: var(--text);
    font-size: 1rem;
    font-weight: 800;
  }

  .monitoring-map-subtitle {
    margin-top: 4px;
    color: var(--text3);
    font-size: .84rem;
    line-height: 1.4;
  }

  .monitoring-map-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .monitoring-map-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 30px;
    padding: 0 12px;
    border-radius: 999px;
    background: #e8f8f2;
    color: #0f766e;
    font-size: .78rem;
    font-weight: 800;
    white-space: nowrap;
  }

  .monitoring-map-body {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(260px, .7fr);
    min-height: 420px;
  }

  #map {
    height: 420px;
    min-height: 420px;
    width: 100%;
    background: #eef2f1;
  }

  .monitoring-map-list {
    border-left: 1px solid var(--border);
    padding: 14px;
    max-height: 420px;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .monitoring-map-location {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    background: #fff;
    cursor: pointer;
    text-align: left;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .monitoring-map-location:hover,
  .monitoring-map-location:focus-visible,
  .monitoring-map-location.is-active {
    border-color: #111;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
    transform: translateY(-1px);
    outline: none;
  }

  .monitoring-map-location-name {
    color: var(--text);
    font-size: .88rem;
    font-weight: 800;
    line-height: 1.3;
  }

  .monitoring-map-location-count {
    margin-top: 4px;
    color: var(--text3);
    font-size: .78rem;
  }

  .monitoring-map-student-list {
    margin: 10px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
  }

  .monitoring-map-student-list li {
    display: grid;
    gap: 2px;
    font-size: .78rem;
    color: var(--text2);
    line-height: 1.35;
  }

  .monitoring-map-student-list strong {
    color: var(--text);
    font-size: .82rem;
  }

  .monitoring-map-empty {
    padding: 24px 16px;
    text-align: center;
    color: var(--text3);
    font-size: .88rem;
  }

  .monitoring-map-warning {
    border-top: 1px solid var(--border);
    background: #fff7ed;
    padding: 12px 18px;
    color: #9a3412;
    font-size: .82rem;
  }

  .monitoring-map-warning-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 800;
  }

  .monitoring-map-warning ul {
    margin: 0;
    padding-left: 18px;
    display: grid;
    gap: 5px;
  }

  .monitoring-map-legend {
    border-top: 1px solid var(--border);
    padding: 12px 18px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
    background: #fbfdfc;
  }

  .monitoring-map-legend-item {
    display: grid;
    gap: 3px;
    font-size: .78rem;
    color: var(--text3);
  }

  .monitoring-map-legend-item strong {
    color: var(--text);
    font-size: .84rem;
  }

  .monitoring-map-popup {
    min-width: 220px;
  }

  .monitoring-map-popup-title {
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
  }

  .monitoring-map-popup-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
  }

  .monitoring-map-popup-list li {
    font-size: 12px;
    line-height: 1.35;
    color: #4b5563;
  }

  .monitoring-map-popup-list strong {
    color: #111827;
  }

  .monitoring-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
  }

  .monitoring-search {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 260px;
  }

  .monitoring-search i {
    position: absolute;
    top: 50%;
    left: 16px;
    transform: translateY(-50%);
    color: #8f96a3;
    font-size: 1rem;
  }

  .monitoring-search input,
  .monitoring-select,
  .monitoring-export-btn {
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    min-height: 42px;
    box-shadow: none;
  }

  .monitoring-search input {
    width: 100%;
    padding: 0 16px 0 40px;
    font-size: .88rem;
    color: var(--text);
    outline: none;
  }

  .monitoring-search input:focus,
  .monitoring-select:focus {
    border-color: #111;
  }

  .monitoring-select {
    min-width: 140px;
    padding: 0 18px;
    font-size: .86rem;
    color: var(--text);
    outline: none;
  }

  .monitoring-export-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 18px;
    font-size: .84rem;
    font-weight: 700;
    color: var(--text2);
    cursor: pointer;
  }

  .monitoring-export-btn:hover {
    border-color: #111;
    color: #111;
  }

  .monitoring-table-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: none;
  }

  .monitoring-table-card .app-table th {
    padding: 10px 12px;
    color: var(--text3);
    background: #fff;
  }

  .monitoring-table-card .app-table td {
    padding: 10px 12px;
    font-size: .88rem;
    vertical-align: middle;
  }

  .monitoring-student {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .monitoring-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: .82rem;
    font-weight: 800;
    flex-shrink: 0;
  }

  .monitoring-student-name {
    font-weight: 700;
    color: #050505;
    line-height: 1.15;
  }

  .monitoring-student-sub {
    margin-top: 3px;
    font-size: .84rem;
    color: #8f96a3;
    line-height: 1.15;
  }

  .monitoring-hours,
  .monitoring-progress {
    font-weight: 700;
    color: #0f172a;
  }

  .monitoring-date {
    color: #8f96a3;
  }

  .monitoring-status-pill {
    padding: 4px 13px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 78px;
  }

  .monitoring-status-ontrack {
    background: #e8f8f2;
    color: #12b3ac;
  }

  .monitoring-status-completed {
    background: #e7f0ff;
    color: #12b3ac;
  }

  .monitoring-status-warning {
    background: #fff4e3;
    color: #12b3ac;
  }

  .monitoring-status-risk {
    background: #fdecec;
    color: #12b3ac;
  }

  .monitoring-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .monitoring-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 32px;
    padding: 0 14px;
    border-radius: 999px;
    background: #111;
    color: #fff;
    text-decoration: none;
    font-size: .86rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .monitoring-alert-btn {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
  }

  .monitoring-empty {
    padding: 24px 16px;
    text-align: center;
    color: var(--text3);
    font-size: .88rem;
  }

  .monitoring-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .28);
    backdrop-filter: blur(2px);
    z-index: 1200;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .25s ease, visibility .25s ease, backdrop-filter .25s ease;
  }

  .monitoring-modal.is-open {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    backdrop-filter: blur(5px);
  }

  .monitoring-modal-dialog {
    width: min(640px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 18px 40px rgba(15, 23, 42, .25);
    padding: 18px;
    transform: translateY(10px) scale(.985);
    opacity: 0;
    transition: transform .28s cubic-bezier(.2,.8,.2,1), opacity .22s ease;
  }

  .monitoring-modal.is-open .monitoring-modal-dialog {
    transform: translateY(0) scale(1);
    opacity: 1;
  }

  .monitoring-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
  }

  .monitoring-modal-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--text);
  }

  .monitoring-modal-subtitle {
    margin-top: 5px;
    font-size: .83rem;
    color: var(--text3);
  }

  .monitoring-modal-close {
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    font-size: .82rem;
    line-height: 1;
    cursor: pointer;
    padding: 8px 10px;
    border-radius: 10px;
    transition: all .2s ease;
  }

  .monitoring-modal-close:hover {
    border-color: #111;
    color: #111;
    transform: translateY(-1px);
  }

  .monitoring-modal-progress {
    background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%);
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }

  .monitoring-modal-progress-label {
    font-size: .9rem;
    font-weight: 700;
    color: var(--text);
  }

  .monitoring-modal-progress-percent {
    font-size: 1rem;
    font-weight: 800;
    color: #dc2626;
  }

  .monitoring-modal-section-title {
    margin: 0 0 10px;
    font-size: .9rem;
    font-weight: 800;
    color: var(--text);
  }

  .monitoring-log-list {
    display: grid;
    gap: 10px;
  }

  .monitoring-log-item {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 13px 12px;
    background: #fff;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
  }

  .monitoring-log-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 6px 16px rgba(15, 23, 42, .06);
    transform: translateY(-1px);
  }

  .monitoring-log-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
  }

  .monitoring-log-date {
    font-size: .86rem;
    font-weight: 700;
    color: var(--text2);
  }

  .monitoring-log-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .monitoring-log-hours {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px 10px;
    border-radius: 999px;
    background: #f3efff;
    color: #12b3ac;
    font-size: .78rem;
    font-weight: 800;
  }

  .monitoring-log-mood {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .78rem;
    font-weight: 700;
    color: var(--text2);
  }

  .monitoring-log-text {
    font-size: .85rem;
    color: var(--text2);
    line-height: 1.45;
  }

  .monitoring-log-empty {
    border: 1px dashed var(--border);
    border-radius: 10px;
    padding: 14px;
    color: var(--text3);
    font-size: .84rem;
    text-align: center;
    background: #fff;
  }

  .monitoring-modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
  }

  .monitoring-modal-btn {
    flex: 1;
    min-height: 40px;
    border-radius: 999px;
    font-size: .95rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    cursor: pointer;
    text-decoration: none;
    transition: all .2s ease;
  }

  .monitoring-modal-btn:hover:not(:disabled) {
    border-color: #111;
    color: #111;
    transform: translateY(-1px);
  }

  .monitoring-modal-btn.primary {
    background: #111;
    border-color: #111;
    color: #fff;
  }

  .monitoring-modal-btn.primary:hover:not(:disabled) {
    background: #000;
    border-color: #000;
    color: #fff;
  }

  .monitoring-modal-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
  }

  @media (max-width: 768px) {
    .monitoring-summary-card {
      padding: 12px;
    }

    .monitoring-map-header,
    .monitoring-map-body {
      grid-template-columns: 1fr;
    }

    .monitoring-map-header {
      flex-direction: column;
      align-items: stretch;
    }

    .monitoring-map-stats {
      justify-content: flex-start;
    }

    .monitoring-map-list {
      border-left: 0;
      border-top: 1px solid var(--border);
      max-height: none;
    }

    #map {
      height: 340px;
      min-height: 340px;
    }

    .monitoring-toolbar {
      gap: 10px;
    }

    .monitoring-search {
      max-width: none;
      width: 100%;
    }

    .monitoring-modal-dialog {
      padding: 14px;
    }

    .monitoring-modal-progress,
    .monitoring-log-top,
    .monitoring-modal-actions {
      flex-direction: column;
      align-items: stretch;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .monitoring-modal,
    .monitoring-modal-dialog,
    .monitoring-log-item,
    .monitoring-modal-btn,
    .monitoring-modal-close {
      transition: none !important;
      transform: none !important;
    }
  }
</style>

<div style="background:linear-gradient(90deg, #050505 0%, #12b3ac 40%, rgba(0, 0, 0, 0.38) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:20px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(0, 0, 0, 0.44);">
  <div style="z-index:2;flex:1;">
    <h2 style="font-size:1.8rem;font-weight:900;margin:0 0 12px 0;line-height:1.2;color:white;">OJT Monitoring</h2>
    <p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Track student progress, activity, and risk levels during internship.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div class="error-msg" style="margin-bottom:14px;">
    <?php echo adviser_monitoring_escape($errorMessage); ?>
  </div>
<?php endif; ?>

<div class="monitoring-page">
  <div class="monitoring-summary-grid">
    <div class="monitoring-summary-card">
      <div class="monitoring-summary-icon total"><i class="fas fa-users"></i></div>
      <div>
        <div class="monitoring-summary-label">Currently On OJT</div>
        <div class="monitoring-summary-value"><?php echo $ojtCount; ?></div>
      </div>
    </div>
    <div class="monitoring-summary-card">
      <div class="monitoring-summary-icon track"><i class="fas fa-check"></i></div>
      <div>
        <div class="monitoring-summary-label">On Track</div>
        <div class="monitoring-summary-value"><?php echo $onTrackCount; ?></div>
      </div>
    </div>
    <div class="monitoring-summary-card">
      <div class="monitoring-summary-icon completed"><i class="fas fa-check-double"></i></div>
      <div>
        <div class="monitoring-summary-label">Completed</div>
        <div class="monitoring-summary-value"><?php echo $completedCount; ?></div>
      </div>
    </div>
    <div class="monitoring-summary-card">
      <div class="monitoring-summary-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
      <div>
        <div class="monitoring-summary-label">Warning</div>
        <div class="monitoring-summary-value"><?php echo $warningCount; ?></div>
      </div>
    </div>
    <div class="monitoring-summary-card">
      <div class="monitoring-summary-icon risk"><i class="fas fa-times-circle"></i></div>
      <div>
        <div class="monitoring-summary-label">At Risk</div>
        <div class="monitoring-summary-value"><?php echo $atRiskCount; ?></div>
      </div>
    </div>
  </div>

  <section class="monitoring-map-panel" aria-labelledby="monitoringMapTitle">
    <div class="monitoring-map-header">
      <div>
        <h3 class="monitoring-map-title" id="monitoringMapTitle">OJT Location Map</h3>
        <div class="monitoring-map-subtitle">Internship locations from accepted applications of your assigned students.</div>
      </div>
      <div class="monitoring-map-stats">
        <span class="monitoring-map-chip"><i class="fas fa-map-location-dot"></i> <?php echo count($mapLocations); ?> locations</span>
        <span class="monitoring-map-chip"><i class="fas fa-user-graduate"></i> <?php echo $acceptedPlacementCount; ?> accepted</span>
      </div>
    </div>

    <div class="monitoring-map-body">
      <div id="map" aria-label="Map of adviser student internship locations"></div>
      <aside class="monitoring-map-list" aria-label="OJT locations list">
        <?php if (!empty($mapLocations)): ?>
          <?php foreach ($mapLocations as $locationIndex => $locationGroup): ?>
            <?php $studentsAtLocation = (array)($locationGroup['students'] ?? []); ?>
            <article class="monitoring-map-location" role="button" tabindex="0" data-map-location-index="<?php echo (int)$locationIndex; ?>" aria-label="Focus map on <?php echo adviser_monitoring_escape((string)($locationGroup['location'] ?? 'Location not set')); ?>">
              <div class="monitoring-map-location-name"><?php echo adviser_monitoring_escape((string)($locationGroup['location'] ?? 'Location not set')); ?></div>
              <div class="monitoring-map-location-count"><?php echo adviser_monitoring_escape((string)($locationGroup['company_name'] ?? 'No company listed')); ?> - <?php echo count($studentsAtLocation); ?> student<?php echo count($studentsAtLocation) === 1 ? '' : 's'; ?></div>
              <ul class="monitoring-map-student-list">
                <?php foreach ($studentsAtLocation as $studentAtLocation): ?>
                  <li>
                    <strong><?php echo adviser_monitoring_escape((string)($studentAtLocation['student_name'] ?? 'Student')); ?></strong>
                    <span><?php echo adviser_monitoring_escape((string)($studentAtLocation['student_number'] ?? '')); ?> - <?php echo adviser_monitoring_escape((string)($studentAtLocation['internship_title'] ?? 'Internship')); ?></span>
                    <span><?php echo adviser_monitoring_escape((string)($studentAtLocation['hours_label'] ?? '0/0 hrs')); ?> - <?php echo (int)($studentAtLocation['progress_percent'] ?? 0); ?>%</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="monitoring-map-empty">No accepted internship applications with locations were found for this adviser.</div>
        <?php endif; ?>
      </aside>
    </div>
    <div class="monitoring-map-warning" id="monitoringMapWarnings" hidden>
      <div class="monitoring-map-warning-title"><i class="fas fa-triangle-exclamation"></i> Locations not found</div>
      <ul id="monitoringMapWarningList"></ul>
    </div>
    <?php if (!empty($mapStudents)): ?>
      <div class="monitoring-map-legend" id="monitoringMapLegend">
        <?php foreach ($mapStudents as $mapStudent): ?>
          <div class="monitoring-map-legend-item">
            <strong><?php echo adviser_monitoring_escape((string)($mapStudent['student_name'] ?? 'Student')); ?></strong>
            <span><?php echo adviser_monitoring_escape((string)($mapStudent['company_name'] ?? 'No company listed')); ?> - <?php echo adviser_monitoring_escape((string)($mapStudent['hours_label'] ?? '0/0 hrs')); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="monitoring-toolbar">
    <input type="hidden" name="page" value="adviser/monitoring">

    <div class="monitoring-search">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_monitoring_escape($selected['search'] ?? ''); ?>">
    </div>

    <select class="monitoring-select" name="company" onchange="this.form.submit()">
      <option value="">All Companies</option>
      <?php foreach (($filterOptions['companies'] ?? []) as $companyOption): ?>
        <option value="<?php echo adviser_monitoring_escape($companyOption); ?>" <?php echo ($selected['company'] ?? '') === $companyOption ? 'selected' : ''; ?>><?php echo adviser_monitoring_escape($companyOption); ?></option>
      <?php endforeach; ?>
    </select>

    <select class="monitoring-select" name="progress" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach (($filterOptions['progresses'] ?? []) as $progressOption): ?>
        <option value="<?php echo adviser_monitoring_escape($progressOption); ?>" <?php echo ($selected['progress'] ?? '') === $progressOption ? 'selected' : ''; ?>><?php echo adviser_monitoring_escape($progressOption); ?></option>
      <?php endforeach; ?>
    </select>

    <a class="monitoring-export-btn" href="<?php echo adviser_monitoring_escape($exportUrl); ?>">
      <i class="fas fa-download"></i> Export
    </a>
  </form>

  <div class="monitoring-table-card">
    <div class="app-table-wrap">
      <table class="app-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Company</th>
            <th>Hours Logged</th>
            <th>Progress</th>
            <th>Last Activity</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($rows)): ?>
            <?php $avatarColors = ['#12b3ac', '#F97316', '#14B8A6', '#12b3ac', '#12b3ac', '#EC4899']; ?>
            <?php foreach ($rows as $index => $row): ?>
              <?php
              $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
              $companyName = trim((string)($row['company_name'] ?? ''));
              $hoursCompleted = (int)round((float)($row['hours_completed'] ?? 0));
              $hoursRequired = (int)round((float)($row['hours_required'] ?? 0));
              $statusLabel = (string)($row['status_label'] ?? 'On Track');
              $statusClass = (string)($row['status_class'] ?? 'monitoring-status-ontrack');
              $avatarColor = $avatarColors[$index % count($avatarColors)];
              $modalId = 'monitoring-log-modal-' . $index;
              ?>
              <tr>
                <td>
                  <div class="monitoring-student">
                    <div class="monitoring-avatar" style="background:<?php echo adviser_monitoring_escape($avatarColor); ?>;"><?php echo adviser_monitoring_escape((string)($row['initials'] ?? 'NA')); ?></div>
                    <div>
                      <div class="monitoring-student-name"><?php echo adviser_monitoring_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></div>
                      <div class="monitoring-student-sub"><?php echo adviser_monitoring_escape((string)($row['program'] ?? 'N/A')); ?></div>
                    </div>
                  </div>
                </td>
                <td><?php echo adviser_monitoring_escape($companyName !== '' ? $companyName : 'No Company'); ?></td>
                <td><span class="monitoring-hours"><?php echo $hoursCompleted; ?> / <?php echo $hoursRequired; ?> hrs</span></td>
                <td><span class="monitoring-progress"><?php echo (int)($row['progress_percent'] ?? 0); ?>%</span></td>
                <td><span class="monitoring-date"><?php echo adviser_monitoring_escape((string)($row['latest_log_date_label'] ?? 'No activity')); ?></span></td>
                <td><span class="monitoring-status-pill <?php echo adviser_monitoring_escape($statusClass); ?>"><?php echo adviser_monitoring_escape($statusLabel); ?></span></td>
                <td>
                  <div class="monitoring-actions">
                    <button class="monitoring-view-btn" type="button" data-open-monitoring-modal="<?php echo adviser_monitoring_escape($modalId); ?>"><i class="fas fa-eye"></i> View Logs</button>
                    <?php if ($statusLabel === 'At Risk' && !empty($row['email'])): ?>
                      <a class="monitoring-alert-btn" href="mailto:<?php echo adviser_monitoring_escape((string)$row['email']); ?>" title="Send reminder">
                        <i class="fas fa-bell"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="monitoring-empty">No monitoring records found for the selected filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($rows)): ?>
    <?php foreach ($rows as $index => $row): ?>
      <?php
      $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
      $companyName = trim((string)($row['company_name'] ?? ''));
      $hoursCompleted = (int)round((float)($row['hours_completed'] ?? 0));
      $hoursRequired = (int)round((float)($row['hours_required'] ?? 0));
      $progressPercent = (int)($row['progress_percent'] ?? 0);
      $recentLogs = array_slice((array)($row['recent_logs'] ?? []), 0, 3);
      $modalId = 'monitoring-log-modal-' . $index;
      ?>
      <div class="monitoring-modal" id="<?php echo adviser_monitoring_escape($modalId); ?>" aria-hidden="true">
        <div class="monitoring-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo adviser_monitoring_escape($modalId); ?>-title">
          <div class="monitoring-modal-head">
            <div>
              <h3 class="monitoring-modal-title" id="<?php echo adviser_monitoring_escape($modalId); ?>-title"><?php echo adviser_monitoring_escape($studentName !== '' ? $studentName : 'Student'); ?> - OJT Activity Logs</h3>
              <div class="monitoring-modal-subtitle"><?php echo adviser_monitoring_escape($companyName !== '' ? $companyName : 'No Company'); ?> · <?php echo $hoursCompleted; ?>/<?php echo $hoursRequired; ?> hours</div>
            </div>
              <button class="monitoring-modal-close" type="button" data-close-monitoring-modal aria-label="Close">Close</button>
          </div>

          <div class="monitoring-modal-progress">
            <div class="monitoring-modal-progress-label">Overall Progress: <?php echo $hoursCompleted; ?> / <?php echo $hoursRequired; ?> hours</div>
            <div class="monitoring-modal-progress-percent"><?php echo $progressPercent; ?>%</div>
          </div>

          <h4 class="monitoring-modal-section-title">Recent Activity Logs:</h4>
          <div class="monitoring-log-list">
            <?php if (!empty($recentLogs)): ?>
              <?php foreach ($recentLogs as $log): ?>
                <?php
                $logDateLabel = adviser_monitoring_format_log_date((string)($log['log_date'] ?? ''));
                $logHours = (float)($log['hours_rendered'] ?? 0);
                $hoursText = $logHours > 0 ? (rtrim(rtrim(number_format($logHours, 2, '.', ''), '0'), '.') . ' hrs') : 'N/A';
                $moodTag = trim((string)($log['mood_tag'] ?? ''));
                $accomplishment = trim((string)($log['accomplishment'] ?? ''));
                ?>
                <div class="monitoring-log-item">
                  <div class="monitoring-log-top">
                    <div class="monitoring-log-date"><?php echo adviser_monitoring_escape($logDateLabel); ?></div>
                    <div class="monitoring-log-meta">
                      <span class="monitoring-log-hours"><?php echo adviser_monitoring_escape($hoursText); ?></span>
                      <?php if ($moodTag !== ''): ?>
                        <span class="monitoring-log-mood"><i class="fas fa-tag"></i> <?php echo adviser_monitoring_escape($moodTag); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="monitoring-log-text"><?php echo adviser_monitoring_escape($accomplishment !== '' ? $accomplishment : 'No accomplishment details provided.'); ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="monitoring-log-empty">No daily logs found for this student yet.</div>
            <?php endif; ?>
          </div>

          <div class="monitoring-modal-actions">
            <button class="monitoring-modal-btn" type="button" disabled>
              <i class="fas fa-bell"></i> Send Reminder
            </button>
            <form method="post" style="flex:1;margin:0;">
              <input type="hidden" name="action" value="approve_all_logs">
              <input type="hidden" name="record_id" value="<?php echo (int)($row['record_id'] ?? 0); ?>">
              <input type="hidden" name="search" value="<?php echo adviser_monitoring_escape((string)($selected['search'] ?? '')); ?>">
              <input type="hidden" name="company" value="<?php echo adviser_monitoring_escape((string)($selected['company'] ?? '')); ?>">
              <input type="hidden" name="progress" value="<?php echo adviser_monitoring_escape((string)($selected['progress'] ?? '')); ?>">
              <button class="monitoring-modal-btn primary" type="submit" style="width:100%;">
                <i class="fas fa-check"></i> Approve All Logs
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
  (function () {
    var locationGroups = <?php echo json_encode($mapLocations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    var mapElement = document.getElementById('map');
    var warningPanel = document.getElementById('monitoringMapWarnings');
    var warningList = document.getElementById('monitoringMapWarningList');

    if (!mapElement || !Array.isArray(locationGroups)) {
      return;
    }

    if (typeof L === 'undefined') {
      mapElement.innerHTML = '<div class="monitoring-map-empty">Map library could not be loaded.</div>';
      return;
    }

    var map = L.map('map', {
      scrollWheelZoom: false
    }).setView([13.7565, 121.0583], 10);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    window.setTimeout(function () {
      map.invalidateSize();
    }, 150);

    var statusControl = L.control({ position: 'bottomleft' });
    var statusElement = null;

    statusControl.onAdd = function () {
      statusElement = L.DomUtil.create('div');
      statusElement.style.background = 'rgba(255,255,255,.94)';
      statusElement.style.border = '1px solid #d1d5db';
      statusElement.style.borderRadius = '8px';
      statusElement.style.padding = '6px 9px';
      statusElement.style.fontSize = '12px';
      statusElement.style.color = '#374151';
      statusElement.style.boxShadow = '0 4px 12px rgba(15,23,42,.12)';
      statusElement.textContent = 'Resolving internship locations...';
      return statusElement;
    };
    statusControl.addTo(map);

    function setStatus(message) {
      if (statusElement) {
        statusElement.textContent = message;
      }
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function buildSearchQuery(locationText) {
      var value = String(locationText || '').trim();
      if (value === '') {
        return '';
      }

      return /philippines/i.test(value) ? value : value + ', Philippines';
    }

    function cacheKey(query) {
      return 'skillhive:ojt-map:geocode:' + query.toLowerCase();
    }

    function readCachedPoint(query) {
      try {
        var cached = window.localStorage.getItem(cacheKey(query));
        if (!cached) {
          return null;
        }

        var parsed = JSON.parse(cached);
        var lat = Number(parsed.lat);
        var lon = Number(parsed.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
          return null;
        }

        return { lat: lat, lon: lon };
      } catch (error) {
        return null;
      }
    }

    function writeCachedPoint(query, point) {
      try {
        window.localStorage.setItem(cacheKey(query), JSON.stringify(point));
      } catch (error) {
      }
    }

    function delay(ms) {
      return new Promise(function (resolve) {
        window.setTimeout(resolve, ms);
      });
    }

    function geocodeLocation(locationText) {
      var query = buildSearchQuery(locationText);
      if (query === '') {
        return Promise.resolve(null);
      }

      var cachedPoint = readCachedPoint(query);
      if (cachedPoint) {
        return Promise.resolve(cachedPoint);
      }

      var url = 'https://nominatim.openstreetmap.org/search?q='
        + encodeURIComponent(query)
        + '&format=json&limit=1';

      return fetch(url, {
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (response) {
          if (!response.ok) {
            return null;
          }
          return response.json();
        })
        .then(function (rows) {
          if (!Array.isArray(rows) || rows.length === 0) {
            return null;
          }

          var point = {
            lat: Number(rows[0].lat),
            lon: Number(rows[0].lon)
          };

          if (!Number.isFinite(point.lat) || !Number.isFinite(point.lon)) {
            return null;
          }

          writeCachedPoint(query, point);
          return point;
        })
        .catch(function () {
          return null;
        });
    }

    function buildPopup(group) {
      var students = Array.isArray(group.students) ? group.students : [];
      var items = students.map(function (student) {
        var yearLevel = Number(student.year_level || 0);
        var programYear = [
          student.program || 'Program not set',
          yearLevel > 0 ? ('Year ' + yearLevel) : ''
        ].filter(Boolean).join(' - ');

        return '<li>'
          + '<strong>' + escapeHtml(student.student_name || 'Student') + '</strong><br>'
          + escapeHtml(student.student_number || 'No student number') + '<br>'
          + escapeHtml(programYear) + '<br>'
          + escapeHtml(student.company_name || group.company_name || 'No company listed') + '<br>'
          + escapeHtml(student.internship_title || 'Internship') + '<br>'
          + escapeHtml(student.hours_label || '0/0 hrs') + '<br>'
          + escapeHtml(student.location || group.location || 'Location not set')
          + '</li>';
      }).join('');

      return '<div class="monitoring-map-popup">'
        + '<div class="monitoring-map-popup-title">' + escapeHtml((group.company_name || 'OJT Location') + ' - ' + (group.location || 'Location not set')) + '</div>'
        + '<ul class="monitoring-map-popup-list">' + items + '</ul>'
        + '</div>';
    }

    function showWarning(group) {
      if (!warningPanel || !warningList) {
        return;
      }

      var students = Array.isArray(group.students) ? group.students : [];
      students.forEach(function (student) {
        var item = document.createElement('li');
        item.textContent = (student.student_name || 'Student') + ' - ' + (group.location || 'Location not set');
        warningList.appendChild(item);
      });

      warningPanel.hidden = false;
    }

    var bounds = [];
    var mappedCount = 0;
    var unresolvedCount = 0;
    var markersByIndex = {};
    var cardElements = document.querySelectorAll('[data-map-location-index]');

    function setActiveCard(index) {
      cardElements.forEach(function (card) {
        card.classList.toggle('is-active', card.getAttribute('data-map-location-index') === String(index));
      });
    }

    function focusLocation(index) {
      var marker = markersByIndex[index];
      if (!marker) {
        setStatus('Location is still loading or could not be mapped');
        return;
      }

      setActiveCard(index);
      map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 14), {
        duration: 0.8
      });

      window.setTimeout(function () {
        marker.openPopup();
      }, 450);
    }

    cardElements.forEach(function (card) {
      card.addEventListener('click', function () {
        focusLocation(card.getAttribute('data-map-location-index'));
      });

      card.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }

        event.preventDefault();
        focusLocation(card.getAttribute('data-map-location-index'));
      });
    });

    locationGroups.reduce(function (chain, group, index) {
      return chain.then(function () {
        setStatus('Resolving location ' + (index + 1) + ' of ' + locationGroups.length + '...');

        return geocodeLocation(group.location).then(function (point) {
          if (!point) {
            unresolvedCount++;
            showWarning(group);
            return null;
          }

          var latLng = [point.lat, point.lon];
          bounds.push(latLng);
          mappedCount++;

          var marker = L.marker(latLng)
            .addTo(map)
            .bindPopup(buildPopup(group));
          markersByIndex[index] = marker;

          marker.on('click', function () {
            setActiveCard(index);
          });

          return delay(1000);
        });
      });
    }, Promise.resolve()).then(function () {
      if (bounds.length > 0) {
        map.fitBounds(bounds, {
          padding: [30, 30],
          maxZoom: 13
        });
      } else {
        setStatus(locationGroups.length === 0 ? 'No accepted internship locations found for the current adviser' : 'No locations could be mapped');
        return;
      }

      setStatus(mappedCount + ' mapped' + (unresolvedCount > 0 ? ', ' + unresolvedCount + ' needs review' : ''));
    });
  })();
</script>

<script>
  (function () {
    var body = document.body;

    function closeModal(modal) {
      if (!modal) {
        return;
      }
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      body.style.overflow = '';
    }

    function openModal(modal) {
      if (!modal) {
        return;
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      body.style.overflow = 'hidden';
    }

    document.querySelectorAll('[data-open-monitoring-modal]').forEach(function (button) {
      button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-open-monitoring-modal');
        if (!modalId) {
          return;
        }
        openModal(document.getElementById(modalId));
      });
    });

    document.querySelectorAll('.monitoring-modal').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });

      modal.querySelectorAll('[data-close-monitoring-modal]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
          closeModal(modal);
        });
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }
      var openModalElement = document.querySelector('.monitoring-modal.is-open');
      if (openModalElement) {
        closeModal(openModalElement);
      }
    });
  })();
</script>
