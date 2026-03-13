<?php
require_once __DIR__ . '/../../backend/db_connect.php';

function applications_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function applications_status_class(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'Shortlisted' => 'status-shortlisted',
        'Interview Scheduled' => 'status-interview',
        'Accepted' => 'status-accepted',
        'Rejected' => 'status-rejected',
        default => 'status-pending',
    };
}

function applications_company_gradient(string $companyName): string
{
    $gradients = [
        'linear-gradient(135deg,#06B6D4,#10B981)',
        'linear-gradient(135deg,#F59E0B,#EF4444)',
        'linear-gradient(135deg,#10B981,#06B6D4)',
        'linear-gradient(135deg,#111827,#374151)',
        'linear-gradient(135deg,#4F46E5,#06B6D4)',
        'linear-gradient(135deg,#EC4899,#F59E0B)',
    ];
    return $gradients[abs(crc32(strtolower($companyName))) % count($gradients)];
}

  function applications_redirect(string $baseUrl, string $statusFilter = ''): void
  {
    $query = ['page' => 'student/applications'];
    if ($statusFilter !== '') {
      $query['status'] = $statusFilter;
    }
    header('Location: ' . $baseUrl . '/layout.php?' . http_build_query($query));
    exit;
  }

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$validStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'cancel_application') {
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $statusFilter = trim((string) ($_POST['status_filter'] ?? ''));

    if ($applicationId <= 0) {
      $_SESSION['status'] = 'Invalid application selected.';
      applications_redirect($baseUrl, $statusFilter);
    }

    $stmt = $pdo->prepare('SELECT status FROM application WHERE application_id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$applicationId, (int) $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $_SESSION['status'] = 'Application not found.';
      applications_redirect($baseUrl, $statusFilter);
    }

    $currentStatus = (string) ($row['status'] ?? '');
    $cancelableStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled'];
    if (!in_array($currentStatus, $cancelableStatuses, true)) {
      $_SESSION['status'] = 'This application can no longer be canceled.';
      applications_redirect($baseUrl, $statusFilter);
    }

    try {
      $stmt = $pdo->prepare('DELETE FROM application WHERE application_id = ? AND student_id = ? LIMIT 1');
      $stmt->execute([$applicationId, (int) $userId]);

      $_SESSION['status'] = $stmt->rowCount() > 0
        ? 'Application canceled successfully.'
        : 'No application was canceled.';
    } catch (Throwable $e) {
      $_SESSION['status'] = 'Unable to cancel application right now. Please try again.';
    }

    applications_redirect($baseUrl, $statusFilter);
  }

$statsSql = 'SELECT status, COUNT(*) AS total FROM application WHERE student_id = ? GROUP BY status';
$stmt = $pdo->prepare($statsSql);
$stmt->execute([(int) $userId]);
$statusCounts = [
    'Pending' => 0,
    'Shortlisted' => 0,
    'Interview Scheduled' => 0,
    'Accepted' => 0,
    'Rejected' => 0,
];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[(string) $row['status']] = (int) $row['total'];
}

$sql =
  'SELECT a.application_id, a.application_date, a.status, a.compatibility_score, a.updated_at, a.consented_at, a.consent_version,
      a.resume_link_snapshot, a.profile_link_snapshot,
            i.internship_id, i.title,
            e.company_name, e.company_badge_status
     FROM application a
     INNER JOIN internship i ON i.internship_id = a.internship_id
     INNER JOIN employer e ON e.employer_id = i.employer_id
     WHERE a.student_id = ?';
$params = [(int) $userId];
if ($statusFilter !== '') {
    $sql .= ' AND a.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY a.application_date DESC, a.application_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recentApplications = array_slice($applications, 0, 5);
$totalApplied = array_sum($statusCounts);
?>

<div class="page-header">
  <div>
    <h2 class="page-title">My Applications</h2>
    <p class="page-subtitle">Track all your internship applications in one place.</p>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-paper-plane" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $totalApplied; ?></div><div class="stat-card-label">Total Applied</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-hourglass-half" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $statusCounts['Pending']; ?></div><div class="stat-card-label">Pending</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(111,66,193,.1)"><i class="fas fa-calendar-check" style="color:#6F42C1"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $statusCounts['Interview Scheduled']; ?></div><div class="stat-card-label">Interviews</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-double" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $statusCounts['Accepted']; ?></div><div class="stat-card-label">Accepted</div></div>
  </div>
</div>

<div class="panel-card">
  <div class="panel-card-header">
    <h3>Application History</h3>
    <form method="get" action="<?php echo applications_e($baseUrl); ?>/layout.php" class="filter-row" style="gap:8px">
      <input type="hidden" name="page" value="student/applications">
      <select class="filter-select" style="min-width:180px" name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <?php foreach ($validStatuses as $status): ?>
          <option value="<?php echo applications_e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo applications_e($status); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="app-table-wrap">
    <table class="app-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Position</th>
          <th>Date Applied</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$applications): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:#94a3b8;padding:28px">No applications found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($applications as $application): ?>
            <?php $companyName = (string) $application['company_name']; ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="co-logo" style="background:<?php echo applications_company_gradient($companyName); ?>;width:32px;height:32px;font-size:.7rem"><?php echo applications_e(strtoupper(substr($companyName, 0, 1))); ?></div>
                  <div>
                    <div><?php echo applications_e($companyName); ?></div>
                    <?php if ((string) ($application['company_badge_status'] ?? '') !== 'None'): ?>
                      <div style="font-size:.72rem;color:#94a3b8"><?php echo applications_e((string) $application['company_badge_status']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight:600;color:#111"><?php echo applications_e((string) $application['title']); ?></div>
                <?php if ($application['compatibility_score'] !== null): ?>
                  <div style="font-size:.72rem;color:#94a3b8">Compatibility: <?php echo number_format((float) $application['compatibility_score'], 0); ?>%</div>
                <?php endif; ?>
                <?php if (!empty($application['consented_at']) && !empty($application['consent_version'])): ?>
                  <div style="font-size:.72rem;color:#94a3b8">Consent: <?php echo applications_e((string) $application['consent_version']); ?> · <?php echo applications_e(date('M j, Y g:i A', strtotime((string) $application['consented_at']))); ?></div>
                <?php endif; ?>
                <?php if (!empty($application['resume_link_snapshot']) || !empty($application['profile_link_snapshot'])): ?>
                  <div style="font-size:.72rem;color:#94a3b8;display:flex;gap:8px;flex-wrap:wrap;margin-top:2px">
                    <?php if (!empty($application['resume_link_snapshot'])): ?>
                      <a href="<?php echo applications_e((string) $application['resume_link_snapshot']); ?>" target="_blank" rel="noopener noreferrer">Resume Shared</a>
                    <?php endif; ?>
                    <?php if (!empty($application['profile_link_snapshot'])): ?>
                      <a href="<?php echo applications_e((string) $application['profile_link_snapshot']); ?>" target="_blank" rel="noopener noreferrer">Profile Shared</a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo applications_e(date('M j, Y', strtotime((string) $application['application_date']))); ?></td>
              <td><span class="status-pill <?php echo applications_status_class((string) $application['status']); ?>"><?php echo applications_e((string) $application['status']); ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <a class="btn btn-ghost btn-sm" href="<?php echo applications_e($baseUrl); ?>/layout.php?page=student/marketplace&amp;detail=<?php echo (int) $application['internship_id']; ?>">View</a>
                <?php if (in_array((string) $application['status'], ['Pending', 'Shortlisted', 'Interview Scheduled'], true)): ?>
                  <form method="post" onsubmit="return confirm('Cancel this application?');" style="margin:0">
                    <input type="hidden" name="action" value="cancel_application">
                    <input type="hidden" name="application_id" value="<?php echo (int) $application['application_id']; ?>">
                    <input type="hidden" name="status_filter" value="<?php echo applications_e($statusFilter); ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" style="color:#dc2626;border-color:#fecaca">Cancel</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel-card">
  <div class="panel-card-header"><h3>Recent Activity</h3></div>
  <div class="timeline">
    <?php if (!$recentApplications): ?>
      <div style="color:#94a3b8">No application activity yet.</div>
    <?php else: ?>
      <?php foreach ($recentApplications as $application): ?>
        <?php
          $status = (string) $application['status'];
          $dotColor = match ($status) {
              'Accepted' => '#10B981',
              'Interview Scheduled' => '#4F46E5',
              'Shortlisted' => '#06B6D4',
              'Rejected' => '#EF4444',
              default => '#F59E0B',
          };
          $activityTitle = match ($status) {
              'Accepted' => 'Accepted by ' . (string) $application['company_name'],
              'Interview Scheduled' => 'Interview scheduled by ' . (string) $application['company_name'],
              'Shortlisted' => 'Shortlisted by ' . (string) $application['company_name'],
              'Rejected' => 'Not selected by ' . (string) $application['company_name'],
              default => 'Application sent to ' . (string) $application['company_name'],
          };
        ?>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:<?php echo $dotColor; ?>"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem"><?php echo applications_e($activityTitle); ?></div>
            <div style="font-size:.75rem;color:#999"><?php echo applications_e((string) $application['title']); ?> — <?php echo applications_e(date('M j, Y', strtotime((string) $application['updated_at']))); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>