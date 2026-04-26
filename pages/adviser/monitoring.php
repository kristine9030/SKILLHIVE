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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adviserId > 0) {
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

$ojtCount = count($rows);
$onTrackCount = 0;
$completedCount = 0;
$warningCount = 0;
$atRiskCount = 0;

foreach ($rows as $row) {
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

if (($_GET['export'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="monitoring-export-' . date('Ymd-His') . '.csv"');

  $output = fopen('php://output', 'w');
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

    <button class="monitoring-export-btn" type="submit" name="export" value="csv">
      <i class="fas fa-download"></i> Export
    </button>
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
