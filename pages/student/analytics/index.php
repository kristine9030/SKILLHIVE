<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/analytics_helpers.php';
require_once __DIR__ . '/analytics_data.php';
require_once __DIR__ . '/analytics_job.php';

if (!isset($userId) && isset($_SESSION['user_id'])) {
  $userId = (int) $_SESSION['user_id'];
}

$studentId = (int) ($userId ?? 0);
$analytics = analytics_job_load($pdo, $studentId);

$student = $analytics['student'];
$readinessScore = $analytics['readinessScore'];
$classTotal = $analytics['classTotal'];
$rankPos = $analytics['rankPos'];
$topPercent = $analytics['topPercent'];
$verifiedCount = $analytics['verifiedCount'];
$skillsForBars = $analytics['skillsForBars'];
$statusCounts = $analytics['statusCounts'];
$totalApplied = $analytics['totalApplied'];
$applicationsThisWeek = $analytics['applicationsThisWeek'];
$hoursThisWeek = $analytics['hoursThisWeek'];
$tasksThisWeek = $analytics['tasksThisWeek'];
$skillsImprovedThisWeek = $analytics['skillsImprovedThisWeek'];
$totalOjtHours = $analytics['totalOjtHours'];
$avgCompatibility = $analytics['avgCompatibility'];
$achievements = $analytics['achievements'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Growth Analytics</h2>
    <p class="page-subtitle">Visualize your skill growth and internship progress.</p>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-chart-line" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo analytics_e(number_format($readinessScore, 2)); ?></div><div class="stat-card-label">Readiness Score</div></div>
    <div class="stat-card-trend neutral">Based on your profile and verified skills</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-trophy" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $classTotal > 0 ? 'Top ' . analytics_e((string) $topPercent) . '%' : 'N/A'; ?></div><div class="stat-card-label">Class Ranking</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-certificate" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo analytics_e((string) $verifiedCount); ?></div><div class="stat-card-label">Skills Certified</div></div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Skills Growth</h3></div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php if (!$skillsForBars): ?>
          <div style="font-size:.86rem;color:#94a3b8">No skills added yet. Add skills in your profile to see growth analytics.</div>
        <?php else: ?>
          <?php foreach (array_slice($skillsForBars, 0, 8) as $skill): ?>
            <div>
              <div class="skill-bar-header">
                <span><?php echo analytics_e($skill['name']); ?></span>
                <span><?php echo analytics_e((string) $skill['score']); ?>% <span style="color:#10B981;font-size:.72rem">(<?php echo analytics_e($skill['delta']); ?>)</span></span>
              </div>
              <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:<?php echo (int) $skill['score']; ?>%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Monthly Application Status</h3></div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <div class="skill-bar-header"><span>Applied</span><span><?php echo analytics_e((string) $statusCounts['Applied']); ?></span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:100%;background:#06B6D4"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Shortlisted</span><span><?php echo analytics_e((string) $statusCounts['Shortlisted']); ?></span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:<?php echo (int) round(($statusCounts['Shortlisted'] / $totalApplied) * 100); ?>%;background:#F59E0B"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Interview</span><span><?php echo analytics_e((string) $statusCounts['Interview']); ?></span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:<?php echo (int) round(($statusCounts['Interview'] / $totalApplied) * 100); ?>%;background:#6F42C1"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Accepted</span><span><?php echo analytics_e((string) $statusCounts['Accepted']); ?></span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:<?php echo (int) round(($statusCounts['Accepted'] / $totalApplied) * 100); ?>%;background:#10B981"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>Rejected</span><span><?php echo analytics_e((string) $statusCounts['Rejected']); ?></span></div>
          <div class="skill-bar-bg"><div class="skill-bar-fill" style="width:<?php echo (int) round(($statusCounts['Rejected'] / $totalApplied) * 100); ?>%;background:#EF4444"></div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Achievements</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($achievements as $achievement): ?>
          <?php
            $isEarned = (bool) ($achievement['earned'] ?? false);
            $label = (string) ($achievement['label'] ?? 'Achievement');
            $statusText = $isEarned ? 'Earned' : 'In Progress';
            $statusColor = $isEarned ? '#10B981' : '#F59E0B';
            if ($label === 'AI Match Master' && !$isEarned) {
              $statusText = 'Locked';
              $statusColor = '#999';
            }
            if ($label === '100 OJT Hours' && !$isEarned) {
              $statusText = number_format(min(100, $totalOjtHours), 0) . '/100 hrs';
            }
          ?>
          <div class="mini-row">
            <span><i class="<?php echo analytics_e((string) $achievement['icon']); ?>" style="color:<?php echo analytics_e($isEarned ? (string) $achievement['color'] : '#ccc'); ?>;margin-right:6px"></i> <?php echo analytics_e($label); ?></span>
            <span style="color:<?php echo analytics_e($statusColor); ?>;font-size:.75rem"><?php echo analytics_e($statusText); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>This Week</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Applications Sent</span><span style="font-weight:700"><?php echo analytics_e((string) $applicationsThisWeek); ?></span></div>
        <div class="mini-row"><span>Hours Logged</span><span style="font-weight:700"><?php echo analytics_e(number_format($hoursThisWeek, 2)); ?></span></div>
        <div class="mini-row"><span>Tasks Completed</span><span style="font-weight:700"><?php echo analytics_e((string) $tasksThisWeek); ?></span></div>
        <div class="mini-row"><span>Skills Improved</span><span style="font-weight:700"><?php echo analytics_e((string) $skillsImprovedThisWeek); ?></span></div>
      </div>
    </div>
  </div>
</div>