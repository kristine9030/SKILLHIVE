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

$appliedCount = (int) ($statusCounts['Applied'] ?? 0);
$shortlistedCount = (int) ($statusCounts['Shortlisted'] ?? 0);
$interviewCount = (int) ($statusCounts['Interview'] ?? 0);
$acceptedCount = (int) ($statusCounts['Accepted'] ?? 0);
$rejectedCount = (int) ($statusCounts['Rejected'] ?? 0);

$shortlistedRate = $appliedCount > 0 ? (int) round(($shortlistedCount / $appliedCount) * 100) : 0;
$interviewRate = $appliedCount > 0 ? (int) round(($interviewCount / $appliedCount) * 100) : 0;
$acceptedRate = $appliedCount > 0 ? (int) round(($acceptedCount / $appliedCount) * 100) : 0;
$rejectedRate = $appliedCount > 0 ? (int) round(($rejectedCount / $appliedCount) * 100) : 0;

$funnelRows = [
  ['label' => 'Applied', 'count' => $appliedCount, 'percent' => $appliedCount > 0 ? 100 : 0, 'color' => '#0EA5E9'],
  ['label' => 'Shortlisted', 'count' => $shortlistedCount, 'percent' => $shortlistedRate, 'color' => '#14B8A6'],
  ['label' => 'Interview', 'count' => $interviewCount, 'percent' => $interviewRate, 'color' => '#12b3ac'],
  ['label' => 'Accepted', 'count' => $acceptedCount, 'percent' => $acceptedRate, 'color' => '#22C55E'],
  ['label' => 'Rejected', 'count' => $rejectedCount, 'percent' => $rejectedRate, 'color' => '#12b3ac'],
];

$classStandingText = $classTotal > 0 ? ('Top ' . $topPercent . '%') : 'N/A';
?>

<style>
  .analytics-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .analytics-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    border: 1px solid rgba(8, 47, 73, .08);
    padding: 24px;
    background: radial-gradient(circle at 92% 12%, rgba(255, 255, 255, .5), transparent 30%), linear-gradient(135deg, #ecfeff 0%, #f0f9ff 48%, #fefce8 100%);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .analytics-hero::after {
    content: '';
    position: absolute;
    right: -36px;
    bottom: -52px;
    width: 190px;
    height: 190px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(14, 165, 233, .2) 0%, rgba(14, 165, 233, 0) 68%);
    pointer-events: none;
  }

  .analytics-hero-kicker {
    margin: 0;
    font-size: .72rem;
    letter-spacing: .07em;
    text-transform: uppercase;
    font-weight: 700;
    color: #0369a1;
  }

  .analytics-hero .page-title {
    margin: 6px 0 0;
    font-size: 2rem;
  }

  .analytics-hero .page-subtitle {
    margin-top: 8px;
    color: #334155;
    background: none;
    -webkit-text-fill-color: currentColor;
    max-width: 620px;
  }

  .analytics-hero-badge {
    position: relative;
    z-index: 2;
    min-width: 180px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 16px;
    padding: 12px 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
  }

  .analytics-hero-badge-label {
    display: block;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 700;
    color: #64748b;
  }

  .analytics-hero-badge-value {
    display: block;
    margin-top: 6px;
    font-size: 1.22rem;
    font-weight: 800;
    color: #0f172a;
    font-family: 'Poppins', sans-serif;
  }

  .analytics-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }

  .analytics-kpi-card {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    background: #fff;
    padding: 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
  }

  .analytics-kpi-card::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 3px;
    background: #0ea5e9;
  }

  .analytics-kpi-card.is-ranking::before {
    background: #14b8a6;
  }

  .analytics-kpi-card.is-certified::before {
    background: #12b3ac;
  }

  .analytics-kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eff6ff;
    color: #0284c7;
    font-size: 1rem;
    flex-shrink: 0;
  }

  .analytics-kpi-card.is-ranking .analytics-kpi-icon {
    background: #ecfeff;
    color: #0f766e;
  }

  .analytics-kpi-card.is-certified .analytics-kpi-icon {
    background: #fffbeb;
    color: #b45309;
  }

  .analytics-kpi-label {
    margin: 0;
    color: #64748b;
    font-size: .74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
  }

  .analytics-kpi-value {
    margin: 4px 0 0;
    color: #0f172a;
    font-size: 1.5rem;
    line-height: 1.1;
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
  }

  .analytics-kpi-meta {
    margin: 6px 0 0;
    color: #64748b;
    font-size: .78rem;
    line-height: 1.35;
  }

  .analytics-layout {
    grid-template-columns: minmax(0, 1.72fr) minmax(280px, 1fr);
    gap: 14px;
  }

  .analytics-section {
    margin-bottom: 14px;
  }

  .analytics-section:last-child {
    margin-bottom: 0;
  }

  .analytics-section-meta {
    font-size: .78rem;
    color: #64748b;
    font-weight: 600;
  }

  .analytics-empty {
    font-size: .86rem;
    color: #64748b;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 14px;
    background: #ffffff;
  }

  .analytics-skill-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .analytics-skill-row {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 11px 12px;
    background: #fff;
  }

  .analytics-skill-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
  }

  .analytics-skill-title-wrap {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .analytics-skill-name {
    color: #0f172a;
    font-size: .86rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .analytics-pill {
    border-radius: 999px;
    padding: 2px 8px;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .analytics-pill.is-verified {
    background: #dcfce7;
    color: #166534;
  }

  .analytics-pill.is-unverified {
    background: #f1f5f9;
    color: #475569;
  }

  .analytics-skill-score {
    color: #0f172a;
    font-size: .8rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .analytics-skill-score small {
    color: #16a34a;
    font-size: .7rem;
    font-weight: 700;
  }

  .analytics-skill-track {
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
  }

  .analytics-skill-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #0ea5e9, #22c55e);
  }

  .analytics-funnel {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .analytics-funnel-row {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    padding: 11px 12px;
  }

  .analytics-funnel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }

  .analytics-funnel-stage {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-size: .84rem;
    font-weight: 700;
  }

  .analytics-funnel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .analytics-funnel-value {
    color: #475569;
    font-size: .8rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .analytics-funnel-track {
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
  }

  .analytics-funnel-fill {
    height: 100%;
    border-radius: 999px;
  }

  .analytics-funnel-metrics {
    margin-top: 10px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }

  .analytics-metric-chip {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px;
  }

  .analytics-metric-label {
    color: #64748b;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
  }

  .analytics-metric-value {
    margin-top: 4px;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
  }

  .analytics-achievement-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .analytics-achievement {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    padding: 10px;
  }

  .analytics-achievement.is-earned {
    border-color: #bbf7d0;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 90%);
  }

  .analytics-achievement-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #64748b;
    flex-shrink: 0;
  }

  .analytics-achievement.is-earned .analytics-achievement-icon {
    background: #dcfce7;
    color: #15803d;
  }

  .analytics-achievement-title {
    color: #0f172a;
    font-size: .84rem;
    font-weight: 700;
  }

  .analytics-achievement-status {
    margin-top: 2px;
    color: #64748b;
    font-size: .74rem;
  }

  .analytics-achievement.is-earned .analytics-achievement-status {
    color: #166534;
    font-weight: 700;
  }

  .analytics-achievement-progress {
    margin-top: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    height: 6px;
    overflow: hidden;
  }

  .analytics-achievement-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #0ea5e9, #22c55e);
  }

  .analytics-week-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .analytics-week-item {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    padding: 11px;
  }

  .analytics-week-label {
    color: #64748b;
    font-size: .74rem;
  }

  .analytics-week-value {
    margin-top: 6px;
    color: #0f172a;
    font-size: 1.12rem;
    font-weight: 800;
    font-family: 'Poppins', sans-serif;
  }

  @media (max-width: 1120px) {
    .analytics-layout {
      grid-template-columns: 1fr;
    }

    .analytics-kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .analytics-hero {
      flex-direction: column;
    }

    .analytics-hero-badge {
      min-width: 0;
    }
  }

  @media (max-width: 720px) {
    .analytics-hero {
      padding: 18px;
      border-radius: 18px;
    }

    .analytics-hero .page-title {
      font-size: 1.6rem;
    }

    .analytics-kpi-grid,
    .analytics-week-grid,
    .analytics-funnel-metrics {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="analytics-page">
  <div class="analytics-hero">
    <div>
      <p class="analytics-hero-kicker">Student Insights</p>
      <h2 class="page-title">Growth Analytics</h2>
      <p class="page-subtitle">Visualize your skill momentum, application funnel, and weekly performance in one place.</p>
    </div>

    <div class="analytics-hero-badge">
      <span class="analytics-hero-badge-label">Class Standing</span>
      <span class="analytics-hero-badge-value"><?php echo analytics_e($classStandingText); ?></span>
    </div>
  </div>

  <div class="analytics-kpi-grid">
    <article class="analytics-kpi-card is-readiness">
      <span class="analytics-kpi-icon"><i class="fas fa-chart-line"></i></span>
      <div>
        <p class="analytics-kpi-label">Readiness Score</p>
        <p class="analytics-kpi-value"><?php echo analytics_e(number_format($readinessScore, 2)); ?></p>
        <p class="analytics-kpi-meta">Built from profile strength and verified skills.</p>
      </div>
    </article>

    <article class="analytics-kpi-card is-ranking">
      <span class="analytics-kpi-icon"><i class="fas fa-trophy"></i></span>
      <div>
        <p class="analytics-kpi-label">Class Ranking</p>
        <p class="analytics-kpi-value"><?php echo analytics_e($classStandingText); ?></p>
        <p class="analytics-kpi-meta"><?php echo $classTotal > 0 ? ('Position: ' . analytics_e((string) $rankPos) . ' of ' . analytics_e((string) $classTotal)) : 'Waiting for enough class data.'; ?></p>
      </div>
    </article>

    <article class="analytics-kpi-card is-certified">
      <span class="analytics-kpi-icon"><i class="fas fa-certificate"></i></span>
      <div>
        <p class="analytics-kpi-label">Skills Certified</p>
        <p class="analytics-kpi-value"><?php echo analytics_e((string) $verifiedCount); ?></p>
        <p class="analytics-kpi-meta"><?php echo analytics_e((string) count($skillsForBars)); ?> tracked skills in your profile.</p>
      </div>
    </article>
  </div>

  <div class="feed-layout analytics-layout">
    <div class="feed-main">
      <div class="panel-card analytics-section">
        <div class="panel-card-header">
          <h3>Skills Growth</h3>
          <span class="analytics-section-meta">Top 8 skills</span>
        </div>

        <div class="analytics-skill-list">
          <?php if (!$skillsForBars): ?>
            <div class="analytics-empty">No skills added yet. Add skills in your profile to unlock growth analytics.</div>
          <?php else: ?>
            <?php foreach (array_slice($skillsForBars, 0, 8) as $skill): ?>
              <div class="analytics-skill-row">
                <div class="analytics-skill-head">
                  <div class="analytics-skill-title-wrap">
                    <span class="analytics-skill-name"><?php echo analytics_e($skill['name']); ?></span>
                    <span class="analytics-pill <?php echo !empty($skill['verified']) ? 'is-verified' : 'is-unverified'; ?>"><?php echo !empty($skill['verified']) ? 'Verified' : 'Learning'; ?></span>
                  </div>

                  <span class="analytics-skill-score">
                    <?php echo analytics_e((string) $skill['score']); ?>%
                    <small>(<?php echo analytics_e($skill['delta']); ?>)</small>
                  </span>
                </div>

                <div class="analytics-skill-track">
                  <div class="analytics-skill-fill" style="width:<?php echo (int) $skill['score']; ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel-card analytics-section">
        <div class="panel-card-header">
          <h3>Application Funnel</h3>
          <span class="analytics-section-meta">Based on total applied</span>
        </div>

        <div class="analytics-funnel">
          <?php foreach ($funnelRows as $row): ?>
            <div class="analytics-funnel-row">
              <div class="analytics-funnel-head">
                <span class="analytics-funnel-stage">
                  <span class="analytics-funnel-dot" style="background:<?php echo analytics_e($row['color']); ?>"></span>
                  <?php echo analytics_e($row['label']); ?>
                </span>
                <span class="analytics-funnel-value"><?php echo analytics_e((string) $row['count']); ?> (<?php echo analytics_e((string) $row['percent']); ?>%)</span>
              </div>
              <div class="analytics-funnel-track">
                <div class="analytics-funnel-fill" style="width:<?php echo (int) $row['percent']; ?>%;background:<?php echo analytics_e($row['color']); ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="analytics-funnel-metrics">
            <div class="analytics-metric-chip">
              <div class="analytics-metric-label">Shortlist Rate</div>
              <div class="analytics-metric-value"><?php echo analytics_e((string) $shortlistedRate); ?>%</div>
            </div>
            <div class="analytics-metric-chip">
              <div class="analytics-metric-label">Interview Rate</div>
              <div class="analytics-metric-value"><?php echo analytics_e((string) $interviewRate); ?>%</div>
            </div>
            <div class="analytics-metric-chip">
              <div class="analytics-metric-label">Acceptance Rate</div>
              <div class="analytics-metric-value"><?php echo analytics_e((string) $acceptedRate); ?>%</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="feed-side">
      <div class="panel-card analytics-section">
        <div class="panel-card-header">
          <h3>Achievements</h3>
        </div>

        <div class="analytics-achievement-list">
          <?php foreach ($achievements as $achievement): ?>
            <?php
            $isEarned = (bool) ($achievement['earned'] ?? false);
            $label = (string) ($achievement['label'] ?? 'Achievement');
            $statusText = $isEarned ? 'Earned' : 'In Progress';
            $progressPercent = 0;

            if ($label === 'AI Match Master' && !$isEarned) {
              $statusText = 'Locked';
            }

            if ($label === '100 OJT Hours' && !$isEarned) {
              $statusText = number_format(min(100, $totalOjtHours), 0) . '/100 hrs';
              $progressPercent = min(100, (int) round(($totalOjtHours / 100) * 100));
            }
            ?>
            <div class="analytics-achievement <?php echo $isEarned ? 'is-earned' : ''; ?>">
              <span class="analytics-achievement-icon"><i class="<?php echo analytics_e((string) $achievement['icon']); ?>"></i></span>
              <div style="min-width:0;flex:1">
                <div class="analytics-achievement-title"><?php echo analytics_e($label); ?></div>
                <div class="analytics-achievement-status"><?php echo analytics_e($statusText); ?></div>

                <?php if ($label === '100 OJT Hours' && !$isEarned): ?>
                  <div class="analytics-achievement-progress">
                    <div class="analytics-achievement-progress-fill" style="width:<?php echo (int) $progressPercent; ?>%"></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel-card analytics-section">
        <div class="panel-card-header">
          <h3>This Week</h3>
        </div>

        <div class="analytics-week-grid">
          <div class="analytics-week-item">
            <div class="analytics-week-label">Applications Sent</div>
            <div class="analytics-week-value"><?php echo analytics_e((string) $applicationsThisWeek); ?></div>
          </div>
          <div class="analytics-week-item">
            <div class="analytics-week-label">Hours Logged</div>
            <div class="analytics-week-value"><?php echo analytics_e(number_format($hoursThisWeek, 2)); ?></div>
          </div>
          <div class="analytics-week-item">
            <div class="analytics-week-label">Tasks Completed</div>
            <div class="analytics-week-value"><?php echo analytics_e((string) $tasksThisWeek); ?></div>
          </div>
          <div class="analytics-week-item">
            <div class="analytics-week-label">Skills Improved</div>
            <div class="analytics-week-value"><?php echo analytics_e((string) $skillsImprovedThisWeek); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>