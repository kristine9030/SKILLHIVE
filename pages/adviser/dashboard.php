<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$dashboardData = [
  'profile' => [
    'adviser_id' => $adviserId,
    'first_name' => '',
    'last_name' => '',
    'department' => '',
    'email' => '',
  ],
  'stats' => [
    'my_students' => 0,
    'endorsed' => 0,
    'pending_review' => 0,
    'partner_companies' => 0,
  ],
  'departments' => [],
  'recent_activity' => [],
  'pending_endorsements' => [],
];

if ($adviserId > 0) {
  try {
    $dashboardData = getAdviserDashboardData($pdo, $adviserId);
  } catch (Throwable $e) {
    $dashboardData = $dashboardData;
  }
}

$stats = $dashboardData['stats'];
$departments = $dashboardData['departments'];
$recentActivity = $dashboardData['recent_activity'];
$pendingEndorsements = $dashboardData['pending_endorsements'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Adviser Dashboard</h2>
    <p class="page-subtitle">Monitor student internships, endorsements, and OJT progress.</p>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-user-graduate" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['my_students']; ?></div><div class="stat-card-label">My Students</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-stamp" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['endorsed']; ?></div><div class="stat-card-label">Endorsed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['pending_review']; ?></div><div class="stat-card-label">Pending Review</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-building" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['partner_companies']; ?></div><div class="stat-card-label">Partner Companies</div></div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Students by Department -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Students by Department</h3></div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php if (!empty($departments)): ?>
          <?php foreach ($departments as $departmentRow): ?>
            <div>
              <div class="skill-bar-header"><span><?php echo adviser_dashboard_escape($departmentRow['department']); ?> — <?php echo adviser_dashboard_escape($departmentRow['program']); ?></span><span><?php echo (int)($departmentRow['student_count'] ?? 0); ?> students</span></div>
              <div class="dept-bar"><div class="dept-bar-fill" style="width:<?php echo (int)($departmentRow['bar_width'] ?? 0); ?>%;background:<?php echo adviser_dashboard_escape($departmentRow['bar_gradient'] ?? 'linear-gradient(90deg,#06B6D4,#10B981)'); ?>"></div></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div>
            <div class="skill-bar-header"><span>No assigned students yet</span><span>0 students</span></div>
            <div class="dept-bar"><div class="dept-bar-fill" style="width:0%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Student Activity -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Recent Student Activity</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Student</th><th>Company</th><th>Hours</th><th>Progress</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if (!empty($recentActivity)): ?>
              <?php foreach ($recentActivity as $activity): ?>
                <tr>
                  <td><div style="display:flex;align-items:center;gap:8px"><div class="topbar-avatar" style="width:28px;height:28px;font-size:.65rem"><?php echo adviser_dashboard_escape($activity['initials']); ?></div><?php echo adviser_dashboard_escape(trim((string)($activity['first_name'] ?? '') . ' ' . (string)($activity['last_name'] ?? ''))); ?></div></td>
                  <td><?php echo adviser_dashboard_escape($activity['company_name'] ?? 'N/A'); ?></td>
                  <td><?php echo number_format((float)($activity['hours_completed'] ?? 0), 0); ?>/<?php echo number_format((float)($activity['hours_required'] ?? 0), 0); ?></td>
                  <td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:<?php echo (int)($activity['progress_percent'] ?? 0); ?>%;background:<?php echo (($activity['progress_percent'] ?? 0) >= 75) ? '#10B981' : ((($activity['progress_percent'] ?? 0) >= 35) ? '#F59E0B' : '#EF4444'); ?>"></div></div></td>
                  <td><span class="status-pill <?php echo adviser_dashboard_escape($activity['status_class'] ?? 'status-pending'); ?>"><?php echo adviser_dashboard_escape($activity['status_label'] ?? 'Pending'); ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align:center;color:#999;">No OJT activity found for your assigned students.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Pending Endorsements -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Pending Endorsements</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php if (!empty($pendingEndorsements)): ?>
          <?php foreach ($pendingEndorsements as $endorsement): ?>
            <div style="padding:12px;background:#f9fafb;border-radius:10px">
              <div style="font-weight:600;font-size:.85rem"><?php echo adviser_dashboard_escape(trim((string)($endorsement['first_name'] ?? '') . ' ' . (string)($endorsement['last_name'] ?? ''))); ?></div>
              <div style="font-size:.75rem;color:#999;margin:4px 0 8px">→ <?php echo adviser_dashboard_escape($endorsement['company_name'] ?? 'Company'); ?> — <?php echo adviser_dashboard_escape($endorsement['internship_title'] ?? 'Internship'); ?></div>
              <div style="display:flex;gap:6px">
                <button class="btn btn-primary btn-sm" style="flex:1" type="button" onclick="window.location.href='<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement'">Endorse</button>
                <button class="btn btn-ghost btn-sm" style="flex:1" type="button" onclick="window.location.href='<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement'">Review</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="padding:12px;background:#f9fafb;border-radius:10px">
            <div style="font-weight:600;font-size:.85rem">No pending endorsements</div>
            <div style="font-size:.75rem;color:#999;margin:4px 0 8px">All endorsement requests are currently cleared.</div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-primary btn-sm" style="flex:1" type="button" onclick="window.location.href='<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement'">Endorse</button>
              <button class="btn btn-ghost btn-sm" style="flex:1" type="button" onclick="window.location.href='<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement'">Review</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Quick Actions</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-stamp"></i> Manage Endorsements</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-eye"></i> OJT Monitoring</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/companies" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-building"></i> Verify Companies</a>
      </div>
    </div>
  </div>
</div>
