<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/monitoring/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$currentFilters = [
  'search' => trim((string)($_GET['search'] ?? '')),
  'company' => trim((string)($_GET['company'] ?? '')),
  'progress' => trim((string)($_GET['progress'] ?? '')),
];

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
$warningCount = 0;
$atRiskCount = 0;

foreach ($rows as $row) {
  $statusLabel = (string)($row['status_label'] ?? '');
  if ($statusLabel === 'On Track') {
    $onTrackCount++;
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
    color: #10b981;
  }

  .monitoring-summary-icon.warning {
    background: #fff4e3;
    color: #f59e0b;
  }

  .monitoring-summary-icon.risk {
    background: #fdecec;
    color: #ef4444;
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
    color: #111827;
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
    color: #10b981;
  }

  .monitoring-status-warning {
    background: #fff4e3;
    color: #f59e0b;
  }

  .monitoring-status-risk {
    background: #fdecec;
    color: #ef4444;
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
  }
</style>

<div class="page-header">
  <div>
    <h2 class="page-title">OJT Monitoring</h2>
    <p class="page-subtitle">Track student progress, activity, and risk levels during internship.</p>
  </div>
</div>

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
            <?php $avatarColors = ['#3B82F6', '#F97316', '#14B8A6', '#7C3AED', '#8B5CF6', '#EC4899']; ?>
            <?php foreach ($rows as $index => $row): ?>
              <?php
              $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
              $companyName = trim((string)($row['company_name'] ?? ''));
              $hoursCompleted = (int)round((float)($row['hours_completed'] ?? 0));
              $hoursRequired = (int)round((float)($row['hours_required'] ?? 0));
              $statusLabel = (string)($row['status_label'] ?? 'On Track');
              $statusClass = (string)($row['status_class'] ?? 'monitoring-status-ontrack');
              $avatarColor = $avatarColors[$index % count($avatarColors)];
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
                    <a class="monitoring-view-btn" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/students&amp;search=<?php echo urlencode($studentName); ?>"><i class="fas fa-eye"></i> View Logs</a>
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
</div>
