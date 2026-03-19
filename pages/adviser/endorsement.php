<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/endorsement/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';

$currentFilters = [
  'status' => trim((string)($_REQUEST['status'] ?? '')),
  'department' => trim((string)($_REQUEST['department'] ?? '')),
  'search' => trim((string)($_REQUEST['search'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adviserId > 0) {
  $action = trim((string)($_POST['action'] ?? ''));
  $endorsementId = (int)($_POST['endorsement_id'] ?? 0);

  if ($action === 'endorse' || $action === 'decline') {
    $nextStatus = $action === 'endorse' ? 'Endorsed' : 'Declined';

    try {
      $result = adviser_endorsement_update_status($pdo, $adviserId, $endorsementId, $nextStatus);
      if (!empty($result['success'])) {
        $_SESSION['status'] = $nextStatus === 'Endorsed' ? 'Endorsement approved.' : 'Endorsement declined.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to update endorsement status.');
      }
    } catch (Throwable $e) {
      $errorMessage = 'Action failed. Please try again.';
    }
  }

  if ($errorMessage === '') {
    $query = http_build_query([
      'page' => 'adviser/endorsement',
      'status' => (string)$currentFilters['status'],
      'department' => (string)$currentFilters['department'],
      'search' => (string)$currentFilters['search'],
    ]);
    header('Location: ' . $baseUrl . '/layout.php?' . $query);
    exit;
  }
}

$pageData = [
  'selected' => ['status' => '', 'department' => '', 'search' => ''],
  'filter_options' => ['statuses' => [], 'departments' => []],
  'pending' => [],
  'history' => [],
];

if ($adviserId > 0) {
  try {
    $pageData = getAdviserEndorsementPageData($pdo, $adviserId, $currentFilters);
  } catch (Throwable $e) {
    $pageData = $pageData;
  }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$pendingRows = $pageData['pending'];
$historyRows = $pageData['history'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Endorsements</h2>
    <p class="page-subtitle">Review and endorse student internship applications.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
    <?php echo adviser_endorsement_escape($errorMessage); ?>
  </div>
<?php endif; ?>

<!-- Filters -->
<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row">
  <input type="hidden" name="page" value="adviser/endorsement">

  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
      <option value="<?php echo adviser_endorsement_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_endorsement_escape($statusOption); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="department" onchange="this.form.submit()">
    <option value="">All Departments</option>
    <?php foreach (($filterOptions['departments'] ?? []) as $deptOption): ?>
      <option value="<?php echo adviser_endorsement_escape($deptOption); ?>" <?php echo ($selected['department'] ?? '') === $deptOption ? 'selected' : ''; ?>><?php echo adviser_endorsement_escape($deptOption); ?></option>
    <?php endforeach; ?>
  </select>

  <div class="topbar-search" style="flex:1;max-width:250px">
    <i class="fas fa-search"></i>
    <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_endorsement_escape($selected['search'] ?? ''); ?>">
  </div>
</form>

<!-- Endorsement Requests -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Pending Endorsements</h3><span style="background:rgba(245,158,11,.1);color:#F59E0B;padding:4px 12px;border-radius:50px;font-size:.78rem;font-weight:600"><?php echo (int)count($pendingRows); ?> pending</span></div>

  <?php if (!empty($pendingRows)): ?>
    <?php foreach ($pendingRows as $row): ?>
      <?php
      $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
      $initials = adviser_endorsement_initials((string)($row['first_name'] ?? ''), (string)($row['last_name'] ?? ''));
      $statusLabel = adviser_endorsement_normalize_status((string)($row['status'] ?? 'Pending'));
      $matchScore = $row['compatibility_score'];
      $matchText = is_numeric($matchScore) ? ((int)round((float)$matchScore) . '%') : 'N/A';
      $durationLabel = adviser_endorsement_duration_label((int)($row['duration_weeks'] ?? 0));
      ?>
      <div class="job-card" style="border-left:3px solid #F59E0B">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div style="display:flex;gap:12px;align-items:center">
            <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem"><?php echo adviser_endorsement_escape($initials); ?></div>
            <div>
              <div style="font-weight:700;font-size:.95rem"><?php echo adviser_endorsement_escape($studentName); ?></div>
              <div style="font-size:.78rem;color:#999"><?php echo adviser_endorsement_escape((string)($row['program'] ?? 'N/A')); ?> — <?php echo adviser_endorsement_escape((string)($row['year_level'] ?? 'N/A')); ?></div>
            </div>
          </div>
          <span class="status-pill status-pending"><?php echo adviser_endorsement_escape($statusLabel); ?></span>
        </div>
        <div style="margin:12px 0;padding:12px;background:#f9fafb;border-radius:8px">
          <div style="font-size:.82rem;color:#666"><strong>Company:</strong> <?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></div>
          <div style="font-size:.82rem;color:#666"><strong>Position:</strong> <?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?></div>
          <div style="font-size:.82rem;color:#666"><strong>Duration:</strong> <?php echo adviser_endorsement_escape($durationLabel); ?> | <strong>Setup:</strong> <?php echo adviser_endorsement_escape((string)($row['work_setup'] ?? 'N/A')); ?></div>
          <div style="font-size:.82rem;color:#666;margin-top:6px"><strong>AI Match:</strong> <span class="match-badge"><?php echo adviser_endorsement_escape($matchText); ?></span></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <form method="post" style="margin:0;display:inline-block;">
            <input type="hidden" name="action" value="endorse">
            <input type="hidden" name="endorsement_id" value="<?php echo (int)($row['endorsement_id'] ?? 0); ?>">
            <input type="hidden" name="status" value="<?php echo adviser_endorsement_escape($selected['status'] ?? ''); ?>">
            <input type="hidden" name="department" value="<?php echo adviser_endorsement_escape($selected['department'] ?? ''); ?>">
            <input type="hidden" name="search" value="<?php echo adviser_endorsement_escape($selected['search'] ?? ''); ?>">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-check"></i> Endorse</button>
          </form>
          <button class="btn btn-ghost btn-sm" type="button"><i class="fas fa-eye"></i> View Profile</button>
          <form method="post" style="margin:0;display:inline-block;">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="endorsement_id" value="<?php echo (int)($row['endorsement_id'] ?? 0); ?>">
            <input type="hidden" name="status" value="<?php echo adviser_endorsement_escape($selected['status'] ?? ''); ?>">
            <input type="hidden" name="department" value="<?php echo adviser_endorsement_escape($selected['department'] ?? ''); ?>">
            <input type="hidden" name="search" value="<?php echo adviser_endorsement_escape($selected['search'] ?? ''); ?>">
            <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444" type="submit"><i class="fas fa-times"></i> Decline</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="job-card" style="margin-bottom:0;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:6px;">No pending endorsements</div>
      <div style="font-size:.82rem;color:#666;">No pending endorsement requests match your current filters.</div>
    </div>
  <?php endif; ?>
</div>

<!-- Past Endorsements -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Endorsement History</h3></div>
  <div class="app-table-wrap">
    <table class="app-table">
      <thead>
        <tr><th>Student</th><th>Company</th><th>Position</th><th>Date</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (!empty($historyRows)): ?>
          <?php foreach ($historyRows as $row): ?>
            <?php
            $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $statusClass = adviser_endorsement_status_class((string)($row['status'] ?? ''));
            $statusLabel = adviser_endorsement_normalize_status((string)($row['status'] ?? ''));
            $historyDate = adviser_endorsement_format_date((string)($row['reviewed_at'] ?? $row['created_at'] ?? ''));
            ?>
            <tr>
              <td><?php echo adviser_endorsement_escape($studentName); ?></td>
              <td><?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></td>
              <td><?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?></td>
              <td><?php echo adviser_endorsement_escape($historyDate); ?></td>
              <td><span class="status-pill <?php echo adviser_endorsement_escape($statusClass); ?>"><?php echo adviser_endorsement_escape($statusLabel); ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align:center;color:#999;">No endorsement history yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
