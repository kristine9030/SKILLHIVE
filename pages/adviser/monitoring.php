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
?>

<div class="page-header">
  <div>
    <h2 class="page-title">OJT Monitoring</h2>
    <p class="page-subtitle">Track student internship hours and daily accomplishments.</p>
  </div>
</div>

<!-- Filters -->
<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row">
  <input type="hidden" name="page" value="adviser/monitoring">

  <div class="topbar-search" style="flex:1;max-width:250px">
    <i class="fas fa-search"></i>
    <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_monitoring_escape($selected['search'] ?? ''); ?>">
  </div>

  <select class="filter-select" name="company" onchange="this.form.submit()">
    <option value="">All Companies</option>
    <?php foreach (($filterOptions['companies'] ?? []) as $companyOption): ?>
      <option value="<?php echo adviser_monitoring_escape($companyOption); ?>" <?php echo ($selected['company'] ?? '') === $companyOption ? 'selected' : ''; ?>><?php echo adviser_monitoring_escape($companyOption); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="progress" onchange="this.form.submit()">
    <option value="">All Progress</option>
    <?php foreach (($filterOptions['progresses'] ?? []) as $progressOption): ?>
      <option value="<?php echo adviser_monitoring_escape($progressOption); ?>" <?php echo ($selected['progress'] ?? '') === $progressOption ? 'selected' : ''; ?>><?php echo adviser_monitoring_escape($progressOption); ?></option>
    <?php endforeach; ?>
  </select>

  <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

<!-- Student Monitoring Cards -->
<div class="cards-grid" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))">
  <?php if (!empty($rows)): ?>
    <?php foreach ($rows as $row): ?>
      <?php
      $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
      $companyName = trim((string)($row['company_name'] ?? ''));
      $internshipTitle = trim((string)($row['internship_title'] ?? ''));
      $hoursCompleted = (float)($row['hours_completed'] ?? 0);
      $hoursRequired = (float)($row['hours_required'] ?? 0);
      $latestLog = trim((string)($row['latest_accomplishment'] ?? ''));
      ?>
      <div class="panel-card">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem"><?php echo adviser_monitoring_escape((string)($row['initials'] ?? 'NA')); ?></div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.95rem"><?php echo adviser_monitoring_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></div>
            <div style="font-size:.78rem;color:#999"><?php echo adviser_monitoring_escape($companyName !== '' ? $companyName : 'No Company'); ?> — <?php echo adviser_monitoring_escape($internshipTitle !== '' ? $internshipTitle : 'No Internship'); ?></div>
          </div>
          <span class="status-pill <?php echo adviser_monitoring_escape((string)($row['status_class'] ?? 'status-pending')); ?>"><?php echo adviser_monitoring_escape((string)($row['status_label'] ?? 'Pending')); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
          <span><?php echo (int)round($hoursCompleted); ?> / <?php echo (int)round($hoursRequired); ?> hours</span><span style="font-weight:700;color:#06B6D4"><?php echo (int)($row['progress_percent'] ?? 0); ?>%</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo (int)($row['progress_percent'] ?? 0); ?>%;background:<?php echo adviser_monitoring_escape((string)($row['progress_gradient'] ?? 'linear-gradient(90deg,#06B6D4,#10B981)')); ?>"></div></div>
        <div style="margin-top:12px;font-size:.78rem;color:#999">
          <strong>Latest log:</strong> <?php echo adviser_monitoring_escape($latestLog !== '' ? $latestLog : 'No daily log yet'); ?> (<?php echo adviser_monitoring_escape((string)($row['latest_log_date_label'] ?? 'No date')); ?>)
        </div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <a class="btn btn-ghost btn-sm" style="flex:1" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/students&amp;search=<?php echo urlencode($studentName); ?>"><i class="fas fa-eye"></i> View Logs</a>
          <?php if (!empty($row['email'])): ?>
            <a class="btn btn-ghost btn-sm" style="flex:1" href="mailto:<?php echo adviser_monitoring_escape((string)$row['email']); ?>"><i class="fas fa-comment"></i> Feedback</a>
          <?php else: ?>
            <button class="btn btn-ghost btn-sm" style="flex:1" type="button" disabled><i class="fas fa-comment"></i> Feedback</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="panel-card" style="grid-column:1/-1;text-align:center;color:#9ca3af;">
      No monitoring records found for the selected filters.
    </div>
  <?php endif; ?>
</div>
