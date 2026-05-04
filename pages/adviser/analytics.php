<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/analytics/data.php';

$stats = $data['stats'] ?? [];
$placementByDept = $data['placement_by_dept'] ?? [];
$topCompanies = array_slice($data['top_companies'] ?? [], 0, 4);
$topSkills = $data['top_skills'] ?? [];
$trends = $data['trends'] ?? [];

$placementRate = (int)($stats['placement_rate'] ?? 0);
$hiringCompanies = (int)($stats['hiring_companies'] ?? 0);
$completionRate = (int)($stats['completion_rate'] ?? 0);
$topSkill = trim((string)($topSkills[0]['skill'] ?? 'No data yet'));
$topSkillDemand = 0;
$placementInsight = 'Based on active OJT records';
$statusChart = [];
$statusChartTotal = 0;
$deptChart = array_slice($placementByDept, 0, 6);
$moduleSettings = is_array($_SESSION['adviser_module_settings'] ?? null) ? $_SESSION['adviser_module_settings'] : [];
$showAnalyticsGraphs = array_key_exists('show_analytics_graphs', $moduleSettings)
  ? (bool)$moduleSettings['show_analytics_graphs']
  : true;

if (!empty($topSkills)) {
    $maxSkillPostings = max(array_map(static function ($skill) {
        return (int)($skill['postings'] ?? 0);
    }, $topSkills));

    if ($maxSkillPostings > 0) {
        $topSkillDemand = (int)round(((int)($topSkills[0]['postings'] ?? 0) / $maxSkillPostings) * 100);
    }
}

if (($stats['total_students'] ?? 0) > 0) {
    $placementInsight = (int)($stats['placed'] ?? 0) . ' of ' . (int)$stats['total_students'] . ' students placed';
}

foreach ($trends as $trend) {
    if (($trend['type'] ?? '') === 'positive' && !empty($trend['text'])) {
        $placementInsight = (string)$trend['text'];
        break;
    }
}

$statusChart = [
  ['label' => 'Completed', 'value' => (int)($stats['placed'] ?? 0), 'color' => '#1f6f6b'],
  ['label' => 'In Progress', 'value' => (int)($stats['in_progress'] ?? 0), 'color' => '#2b8a84'],
  ['label' => 'Searching', 'value' => (int)($stats['searching'] ?? 0), 'color' => '#78a9a6'],
];

foreach ($statusChart as $item) {
  $statusChartTotal += (int)($item['value'] ?? 0);
}
?>

<style>
  .adviser-analytics-page {
    --aa-ink: #000000;
    --aa-verdigris-dark: #1f6f6b;
    --aa-verdigris: #2b8a84;
    --aa-verdigris-soft: #78a9a6;
    --aa-surface: #eef4f3;
  }

  .adviser-analytics-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    color: var(--aa-ink);
    font-size: var(--font-size-body);
  }

  .analytics-title-header {
    margin-bottom: 20px;
  }

  .analytics-page-title {
    font-size: 2.2rem;
    font-weight: 900;
    background: linear-gradient(135deg, #050505 0%, #12b3ac 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 6px 0;
    line-height: 1.2;
  }

  .analytics-page-subtitle {
    font-size: 0.85rem;
    color: var(--text3);
    margin: 0;
    line-height: 1.5;
  }

  .stat-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
  }

  .stat-cards .stat-card {
    padding: 16px;
    min-height: auto;
    gap: 10px;
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-cards .stat-card::before {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(18, 179, 172, 0.06);
    pointer-events: none;
  }

  .stat-cards .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
    border-color: #12b3ac;
  }

  .stat-cards .stat-card-icon {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
    opacity: 0.8;
  }

  .stat-cards .stat-card-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .stat-cards .stat-card-num {
    font-size: 1.8rem;
    line-height: 1.1;
    font-weight: 800;
    color: #050505;
  }

  .stat-cards .stat-card-label {
    font-size: 0.78rem;
    margin-top: 2px;
    font-weight: 600;
    color: #64748b;
  }

  .stat-cards .stat-card-trend {
    font-size: 0.7rem;
    margin-bottom: 2px;
    color: #94a3b8;
  }

  .stat-cards .stat-card-info {
    gap: 4px;
    flex: 1;
  }

  .adviser-analytics-card,
  .adviser-analytics-panel,
  .adviser-company-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
  }

  .adviser-analytics-card {
    min-height: 172px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .adviser-analytics-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .adviser-analytics-card-label {
    margin: 0 0 6px;
    font-size: 0.82rem;
    color: var(--aa-ink);
    font-weight: 500;
  }

  .adviser-analytics-card-value {
    margin: 0;
    font-size: 1.95rem;
    line-height: 1;
    font-weight: 700;
    color: var(--aa-ink);
  }

  .adviser-analytics-card-note {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.79rem;
    color: var(--aa-ink);
    font-weight: 600;
  }

  .adviser-analytics-card-note.is-muted {
    color: var(--aa-ink);
    font-weight: 500;
  }

  .adviser-analytics-chart-section-title {
    margin: 0 0 18px;
    font-size: 1.3rem;
    font-weight: 800;
    background: linear-gradient(135deg, #050505 0%, #12b3ac 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-analytics-chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
  }

  .adviser-analytics-chart-card {
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .adviser-analytics-chart-card::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(18, 179, 172, 0.05);
    pointer-events: none;
  }

  .adviser-analytics-chart-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }

  .adviser-analytics-chart-card.is-wide {
    grid-column: 1 / -1;
  }

  .adviser-analytics-chart-title {
    margin: 0 0 18px;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--aa-ink);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .adviser-analytics-chart-title::before {
    content: '';
    width: 4px;
    height: 20px;
    background: linear-gradient(180deg, #050505, #12b3ac);
    border-radius: 2px;
    flex-shrink: 0;
  }

  .adviser-status-chart-wrap {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
  }

  .adviser-status-donut {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    position: relative;
    flex-shrink: 0;
  }

  .adviser-status-donut::after {
    content: '';
    position: absolute;
    inset: 22px;
    background: #fff;
    border-radius: 50%;
    border: 1px solid #e6edf6;
  }

  .adviser-status-center {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    z-index: 1;
    text-align: center;
  }

  .adviser-status-total {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--aa-ink);
    line-height: 1;
  }

  .adviser-status-caption {
    margin-top: 4px;
    font-size: 0.72rem;
    color: var(--aa-ink);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .adviser-status-legend {
    display: grid;
    gap: 8px;
    min-width: 210px;
  }

  .adviser-status-legend-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.82rem;
    color: var(--aa-ink);
  }

  .adviser-status-legend-item strong {
    font-weight: 700;
    color: var(--aa-ink);
  }

  .adviser-status-key {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .adviser-status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
  }

  .adviser-dept-chart {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    height: 190px;
    padding-top: 8px;
  }

  .adviser-dept-bar {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
  }

  .adviser-dept-bar-track {
    width: 100%;
    height: 140px;
    border-radius: 12px;
    background: var(--aa-surface);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    border: 1px solid #e3eaf4;
  }

  .adviser-dept-bar-fill {
    width: 100%;
    background: linear-gradient(180deg, var(--aa-verdigris) 0%, var(--aa-verdigris-dark) 100%);
    border-radius: 10px 10px 0 0;
    min-height: 2px;
  }

  .adviser-dept-bar-rate {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--aa-ink);
  }

  .adviser-dept-bar-label {
    font-size: 0.72rem;
    color: var(--aa-ink);
    text-align: center;
    line-height: 1.2;
    word-break: break-word;
  }

  .adviser-skills-chart {
    display: grid;
    gap: 10px;
  }

  .adviser-skills-row {
    display: grid;
    grid-template-columns: minmax(120px, 190px) minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
  }

  .adviser-skills-label {
    font-size: 0.78rem;
    color: var(--aa-ink);
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .adviser-skills-track {
    height: 10px;
    border-radius: 999px;
    background: #ecf1f8;
    overflow: hidden;
  }

  .adviser-skills-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--aa-verdigris-dark) 0%, var(--aa-verdigris) 100%);
  }

  .adviser-skills-value {
    font-size: 0.78rem;
    color: var(--aa-ink);
    font-weight: 700;
  }

  .adviser-analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }

  .adviser-analytics-panel {
    padding: 22px;
  }

  .adviser-analytics-panel-title {
    margin: 0 0 18px;
    font-size: 1.1rem;
    font-weight: 800;
    background: linear-gradient(135deg, #050505 0%, #12b3ac 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .adviser-analytics-bars {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .adviser-analytics-bar-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .adviser-analytics-bar-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 0.82rem;
  }

  .adviser-analytics-bar-label {
    color: var(--aa-ink);
    font-weight: 600;
  }

  .adviser-analytics-bar-value {
    color: var(--aa-ink);
    font-weight: 600;
  }

  .adviser-analytics-track {
    height: 10px;
    border-radius: 999px;
    background: #ecf1f8;
    overflow: hidden;
  }

  .adviser-analytics-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--aa-verdigris-dark) 0%, var(--aa-verdigris) 100%);
  }

  .adviser-analytics-empty {
    padding: 18px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    background: var(--aa-surface);
    color: var(--aa-ink);
    font-size: 0.82rem;
  }

  .adviser-analytics-companies-panel {
    padding: 22px;
  }

  .adviser-analytics-companies-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
  }

  .adviser-company-card {
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .adviser-company-top {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .adviser-company-logo {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    flex-shrink: 0;
  }

  .adviser-company-name {
    margin: 0 0 4px;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--aa-ink);
  }

  .adviser-company-industry {
    margin: 0;
    font-size: 0.8rem;
    color: var(--aa-ink);
  }

  .adviser-company-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 6px 10px;
    border-radius: 999px;
    background: var(--aa-surface);
    color: var(--aa-ink);
    font-size: 0.76rem;
    font-weight: 600;
  }

  .adviser-company-footer {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .adviser-company-metric-value {
    display: block;
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--aa-ink);
  }

  .adviser-company-metric-label {
    display: block;
    margin-top: 3px;
    font-size: 0.76rem;
    color: var(--aa-ink);
  }

  @media (max-width: 1240px) {
    .adviser-analytics-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .adviser-analytics-companies-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stat-cards {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 720px) {
    .stat-cards {
      grid-template-columns: 1fr;
    }

    .analytics-page-title {
      font-size: 1.2rem;
    }
  }

  @media (max-width: 900px) {
    .adviser-analytics-chart-grid,
    .adviser-analytics-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 680px) {
    .adviser-skills-row {
      grid-template-columns: 1fr;
      gap: 6px;
    }

    .adviser-skills-label,
    .adviser-skills-value {
      white-space: normal;
    }
  }

  @media (max-width: 680px) {
    .adviser-analytics-stats,
    .adviser-analytics-companies-grid {
      grid-template-columns: 1fr;
    }

    .adviser-analytics-card,
    .adviser-analytics-panel,
    .adviser-analytics-companies-panel,
    .adviser-company-card {
      padding: 18px;
      border-radius: 16px;
    }
  }
</style>

  <div class="adviser-analytics-page">
    <div class="analytics-title-header">
      <h2 class="analytics-page-title">Analytics</h2>
      <p class="analytics-page-subtitle">Overview of placement performance and student metrics</p>
    </div>

    <div class="stat-cards">
      <div class="stat-card adviser-stat-placement">
        <div class="stat-card-icon"><img src="/SkillHive/assets/media/Active%20Posting.png" alt="Placement Rate"></div>
        <div class="stat-card-info">
          <div class="stat-card-num-row">
            <div class="stat-card-trend neutral"><?php echo adviser_analytics_escape($placementInsight); ?></div>
            <div class="stat-card-num"><?php echo adviser_analytics_escape($placementRate); ?>%</div>
          </div>
          <div class="stat-card-label">Placement Rate</div>
        </div>
      </div>

      <div class="stat-card adviser-stat-companies">
        <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Applicants.png" alt="Hiring Companies"></div>
        <div class="stat-card-info">
          <div class="stat-card-num-row">
            <div class="stat-card-trend neutral"><?php echo adviser_analytics_escape($hiringCompanies); ?> partners</div>
            <div class="stat-card-num"><?php echo adviser_analytics_escape($hiringCompanies); ?></div>
          </div>
          <div class="stat-card-label">Hiring Companies</div>
        </div>
      </div>

      <div class="stat-card adviser-stat-skill">
        <div class="stat-card-icon"><img src="/SkillHive/assets/media/Interviews.png" alt="Top Skill Demand"></div>
        <div class="stat-card-info">
          <div class="stat-card-num-row">
            <div class="stat-card-trend neutral"><?php echo adviser_analytics_escape($topSkillDemand); ?>% demand</div>
            <div class="stat-card-num"><?php echo adviser_analytics_escape($topSkill); ?></div>
          </div>
          <div class="stat-card-label">Top Skill Demand</div>
        </div>
      </div>

      <div class="stat-card adviser-stat-completion">
        <div class="stat-card-icon"><img src="/SkillHive/assets/media/Hiredd.png" alt="Completions"></div>
        <div class="stat-card-info">
          <div class="stat-card-num-row">
            <div class="stat-card-trend neutral"><?php echo adviser_analytics_escape($completionRate); ?>% done</div>
            <div class="stat-card-num"><?php echo adviser_analytics_escape($completionRate); ?>%</div>
          </div>
          <div class="stat-card-label">Completions</div>
        </div>
      </div>
    </div>

  <?php if ($showAnalyticsGraphs): ?>
      <h3 class="adviser-analytics-chart-section-title">Analytics Graphs</h3>

    <section class="adviser-analytics-chart-grid">
      <article class="adviser-analytics-chart-card">
        <h3 class="adviser-analytics-chart-title"><i class="fas fa-chart-pie" style="color:#12b3ac;"></i> Student Placement Status</h3>
        <?php
          $completedPct = $statusChartTotal > 0 ? (int)round(((int)$statusChart[0]['value'] / $statusChartTotal) * 100) : 0;
          $inProgressPct = $statusChartTotal > 0 ? (int)round(((int)$statusChart[1]['value'] / $statusChartTotal) * 100) : 0;
          $searchingPct = max(0, 100 - $completedPct - $inProgressPct);
          $donutBackground = $statusChartTotal > 0
            ? 'conic-gradient(' .
                $statusChart[0]['color'] . ' 0 ' . $completedPct . '%, ' .
                $statusChart[1]['color'] . ' ' . $completedPct . '% ' . ($completedPct + $inProgressPct) . '%, ' .
                $statusChart[2]['color'] . ' ' . ($completedPct + $inProgressPct) . '% 100%)'
            : 'conic-gradient(#dbe5f1 0 100%)';
        ?>
        <div class="adviser-status-chart-wrap">
          <div class="adviser-status-donut" style="background:<?php echo adviser_analytics_escape($donutBackground); ?>; width:180px; height:180px;">
            <div class="adviser-status-center">
              <span class="adviser-status-total" style="font-size:1.8rem;"><?php echo adviser_analytics_escape($statusChartTotal); ?></span>
              <span class="adviser-status-caption">Students</span>
            </div>
          </div>

          <div class="adviser-status-legend">
            <?php foreach ($statusChart as $item): ?>
              <?php
                $count = (int)($item['value'] ?? 0);
                $share = $statusChartTotal > 0 ? (int)round(($count / $statusChartTotal) * 100) : 0;
              ?>
              <div class="adviser-status-legend-item" style="padding:8px 12px; background:#f8fafc; border-radius:8px; margin-bottom:6px;">
                <span class="adviser-status-key" style="font-weight:600;">
                  <span class="adviser-status-dot" style="background:<?php echo adviser_analytics_escape($item['color']); ?>; width:12px; height:12px;"></span>
                  <?php echo adviser_analytics_escape($item['label']); ?>
                </span>
                <strong style="font-size:1.1rem;"><?php echo adviser_analytics_escape($count); ?> (<?php echo adviser_analytics_escape($share); ?>%)</strong>
              </div>
            <?php endforeach; ?>
            <?php if ($statusChartTotal === 0): ?>
              <div class="adviser-analytics-empty" style="padding:10px 12px;">
                No status data yet.
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:12px; padding:10px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:8px; font-size:0.82rem; color:#166534;">
          <i class="fas fa-lightbulb"></i> <strong>Insight:</strong> <?php echo adviser_analytics_escape($placementInsight); ?>
        </div>
      </article>

      <article class="adviser-analytics-chart-card">
        <h3 class="adviser-analytics-chart-title"><i class="fas fa-building" style="color:#12b3ac;"></i> Dept. Placement Rates</h3>
        <?php if (!empty($deptChart)): ?>
          <div class="adviser-dept-chart" style="height:220px;">
            <?php foreach ($deptChart as $department): ?>
              <?php
                $rate = max(0, min(100, (int)($department['placement_rate'] ?? 0)));
                $deptLabel = adviser_analytics_department_label($department['department'] ?? '');
              ?>
              <div class="adviser-dept-bar">
                <span class="adviser-dept-bar-rate" style="font-weight:700; color:#050505;"><?php echo adviser_analytics_escape($rate); ?>%</span>
                <div class="adviser-dept-bar-track" style="border-radius:8px;">
                  <div class="adviser-dept-bar-fill" style="height:<?php echo adviser_analytics_escape($rate); ?>%; background:linear-gradient(180deg, #12b3ac, #050505); border-radius:6px 6px 0 0;"></div>
                </div>
                <span class="adviser-dept-bar-label" style="font-size:0.72rem; font-weight:600;"><?php echo adviser_analytics_escape($deptLabel); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="adviser-analytics-empty">
            Department placement chart will appear once student placement data is available.
          </div>
        <?php endif; ?>
      </article>

      <article class="adviser-analytics-chart-card is-wide">
        <h3 class="adviser-analytics-chart-title"><i class="fas fa-fire" style="color:#12b3ac;"></i> Top Skills in Demand</h3>
        <?php if (!empty($topSkills)): ?>
          <?php
            $skillsMax = max(array_map(static function ($skill) {
                return (int)($skill['postings'] ?? 0);
            }, $topSkills));
          ?>
          <div class="adviser-skills-chart">
            <?php foreach ($topSkills as $skill): ?>
              <?php
                $postings = (int)($skill['postings'] ?? 0);
                $skillsWidth = $skillsMax > 0 ? (int)round(($postings / $skillsMax) * 100) : 0;
              ?>
              <div class="adviser-skills-row" style="padding:8px 0;">
                <span class="adviser-skills-label" style="font-weight:600; color:#334155;"><?php echo adviser_analytics_escape($skill['skill'] ?? 'Unknown skill'); ?></span>
                <div class="adviser-skills-track" style="border-radius:6px; height:12px;">
                  <div class="adviser-skills-fill" style="width:<?php echo adviser_analytics_escape($skillsWidth); ?>%; background:linear-gradient(90deg, #050505, #12b3ac); border-radius:inherit;"></div>
                </div>
                <span class="adviser-skills-value" style="font-weight:700; color:#050505;"><?php echo adviser_analytics_escape($postings); ?> posts</span>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:12px; padding:10px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:8px; font-size:0.82rem; color:#166534;">
            <i class="fas fa-chart-line"></i> <strong>University Insight:</strong> Top skill "<?php echo adviser_analytics_escape($topSkill); ?>" shows highest industry demand - consider enhancing curriculum.
          </div>
        <?php else: ?>
          <div class="adviser-analytics-empty">
            Top skills trend will appear once internship postings include skill requirements.
          </div>
        <?php endif; ?>
      </article>
    </section>
  <?php endif; ?>

  <section class="adviser-analytics-grid">
    <article class="adviser-analytics-panel" style="background:linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%); border:1px solid var(--border);">
      <h3 class="adviser-analytics-panel-title" style="font-size:1.05rem; background:linear-gradient(135deg, #050505 0%, #12b3ac 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
        <i class="fas fa-university" style="-webkit-text-fill-color:#12b3ac;"></i> Dept. Placement Performance
      </h3>

      <?php if (!empty($placementByDept)): ?>
        <div class="adviser-analytics-bars">
          <?php foreach ($placementByDept as $department): ?>
            <?php $rate = max(0, min(100, (int)($department['placement_rate'] ?? 0))); ?>
            <div class="adviser-analytics-bar-row" style="padding:10px; background:#f8fafc; border-radius:8px; margin-bottom:8px;">
              <div class="adviser-analytics-bar-top">
                <span class="adviser-analytics-bar-label" style="font-weight:600; color:#334155;"><?php echo adviser_analytics_escape(adviser_analytics_department_label($department['department'] ?? '')); ?></span>
                <span class="adviser-analytics-bar-value" style="font-size:1.1rem; color:#050505;"><?php echo adviser_analytics_escape($rate); ?>%</span>
              </div>
              <div class="adviser-analytics-track" style="height:12px; border-radius:6px; background:#e2e8f0;">
                <div class="adviser-analytics-fill" style="width:<?php echo $rate; ?>%; background:linear-gradient(90deg, #050505, #12b3ac); border-radius:inherit;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:12px; padding:10px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:8px; font-size:0.82rem; color:#166534;">
          <i class="fas fa-graduation-cap"></i> <strong>University Insight:</strong> Track department performance to identify programs needing more industry partnerships.
        </div>
      <?php else: ?>
        <div class="adviser-analytics-empty">
          Placement insights will appear once your assigned students begin their OJT progress.
        </div>
      <?php endif; ?>
    </article>

    <article class="adviser-analytics-panel" style="background:linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%); border:1px solid var(--border);">
      <h3 class="adviser-analytics-panel-title" style="font-size:1.05rem; background:linear-gradient(135deg, #050505 0%, #12b3ac 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
        <i class="fas fa-cogs" style="-webkit-text-fill-color:#12b3ac;"></i> Industry Skill Gaps
      </h3>

      <?php if (!empty($topSkills)): ?>
        <?php
          $skillMax = max(array_map(static function ($skill) {
              return (int)($skill['postings'] ?? 0);
          }, $topSkills));
        ?>
        <div class="adviser-analytics-bars">
          <?php foreach ($topSkills as $skill): ?>
            <?php
              $postings = (int)($skill['postings'] ?? 0);
              $demandRate = $skillMax > 0 ? (int)round(($postings / $skillMax) * 100) : 0;
            ?>
            <div class="adviser-analytics-bar-row" style="padding:10px; background:#f8fafc; border-radius:8px; margin-bottom:8px;">
              <div class="adviser-analytics-bar-top">
                <span class="adviser-analytics-bar-label" style="font-weight:600; color:#334155;"><?php echo adviser_analytics_escape($skill['skill'] ?? 'Unknown skill'); ?></span>
                <span class="adviser-analytics-bar-value" style="font-size:1.1rem; color:#050505;"><?php echo adviser_analytics_escape($demandRate); ?>%</span>
              </div>
              <div class="adviser-analytics-track" style="height:12px; border-radius:6px; background:#e2e8f0;">
                <div class="adviser-analytics-fill" style="width:<?php echo $demandRate; ?>%; background:linear-gradient(90deg, #12b3ac, #050505); border-radius:inherit;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:12px; padding:10px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:8px; font-size:0.82rem; color:#166534;">
          <i class="fas fa-lightbulb"></i> <strong>Curriculum Insight:</strong> Align coursework with top industry skills to improve graduate employability.
        </div>
      <?php else: ?>
        <div class="adviser-analytics-empty">
          Skill demand trends will show here once internship data includes skill requirements.
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="adviser-analytics-panel adviser-analytics-companies-panel" style="background:linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%); border:1px solid var(--border);">
    <h3 class="adviser-analytics-panel-title" style="font-size:1.1rem; background:linear-gradient(135deg, #050505 0%, #12b3ac 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
      <i class="fas fa-trophy" style="-webkit-text-fill-color:#12b3ac;"></i> Top Hiring Partners
    </h3>

    <?php if (!empty($topCompanies)): ?>
      <div class="adviser-analytics-companies-grid">
        <?php foreach ($topCompanies as $index => $company): ?>
          <?php
            $internCount = (int)($company['intern_count'] ?? 0);
            $companyRating = $company['avg_rating'] !== null ? number_format((float)$company['avg_rating'], 1) : '0.0';
            $companyCompletion = max(0, min(100, (int)($company['completion_rate'] ?? 0)));
            $companySeed = (int)($company['employer_id'] ?? $internCount);
            $rankBadge = $index === 0 ? '🥇' : ($index === 1 ? '🥈' : ($index === 2 ? '🥉' : '#' . ($index + 1)));
          ?>
          <article class="adviser-company-card" style="background:#ffffff; border:2px solid <?php echo $index === 0 ? '#12b3ac' : 'var(--border)'; ?>; padding:16px;">
            <div class="adviser-company-top">
              <span class="adviser-company-logo" style="background:<?php echo adviser_analytics_escape(adviser_analytics_company_gradient($companySeed)); ?>; width:52px; height:52px; font-size:1.1rem;">
                <?php echo adviser_analytics_escape(adviser_analytics_company_initials($company['company_name'] ?? '')); ?>
              </span>

              <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                  <h4 class="adviser-company-name" style="margin:0; font-size:0.95rem;"><?php echo $rankBadge; ?> <?php echo adviser_analytics_escape($company['company_name'] ?? 'Partner company'); ?></h4>
                </div>
                <p class="adviser-company-industry" style="margin:0; font-size:0.78rem;"><?php echo adviser_analytics_escape($company['industry'] ?? 'General'); ?></p>
              </div>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin:8px 0;">
              <span class="adviser-company-badge" style="background:#e8f8f2; color:#047857; padding:4px 10px; border-radius:999px; font-size:0.72rem;">
                <i class="fas fa-shield-alt"></i>
                <?php echo adviser_analytics_escape(adviser_analytics_company_partner_label($company)); ?>
              </span>
              <span style="background:#f0f9ff; color:#1e40af; padding:4px 10px; border-radius:999px; font-size:0.72rem; font-weight:600;">
                <i class="fas fa-star" style="color:#fbbf24;"></i> <?php echo adviser_analytics_escape($companyRating); ?>/5
              </span>
            </div>

            <div style="margin:8px 0;">
              <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.75rem;">
                <span style="color:#64748b;">Completion Rate</span>
                <span style="font-weight:700; color:#050505;"><?php echo $companyCompletion; ?>%</span>
              </div>
              <div class="adviser-analytics-track" style="height:10px; border-radius:999px; background:#e2e8f0;">
                <div class="adviser-analytics-fill" style="width:<?php echo $companyCompletion; ?>%; background:linear-gradient(90deg, #050505, #12b3ac); border-radius:inherit;"></div>
              </div>
            </div>

            <div class="adviser-company-footer" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; margin-top:8px;">
              <div style="text-align:center; padding:8px; background:linear-gradient(135deg, #f8fafc, #ffffff); border-radius:8px;">
                <span class="adviser-company-metric-value" style="display:block; font-size:1.3rem; font-weight:800; color:#050505;"><?php echo adviser_analytics_escape($internCount); ?></span>
                <span class="adviser-company-metric-label" style="font-size:0.7rem; color:#64748b;">Interns</span>
              </div>
              <div style="text-align:center; padding:8px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:8px;">
                <span class="adviser-company-metric-value" style="display:block; font-size:1.3rem; font-weight:800; color:#050505;"><?php echo adviser_analytics_escape($companyRating); ?></span>
                <span class="adviser-company-metric-label" style="font-size:0.7rem; color:#64748b;">Rating</span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px; padding:12px; background:linear-gradient(135deg, #f0fdf4, #ffffff); border-radius:10px; font-size:0.82rem; color:#166534;">
        <i class="fas fa-university"></i> <strong>University Strategy:</strong> Strengthen partnerships with top performers to increase placement success rates.
      </div>
    <?php else: ?>
      <div class="adviser-analytics-empty">
        Top hiring companies will appear here after students are matched with internship partners.
      </div>
    <?php endif; ?>
  </section>
</div>
