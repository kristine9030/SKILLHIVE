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

  .adviser-analytics-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
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

  .adviser-analytics-chart-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }

  .adviser-analytics-chart-section-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--aa-ink);
  }

  .adviser-analytics-chart-card.is-wide {
    grid-column: 1 / -1;
  }

  .adviser-analytics-chart-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
    padding: 22px;
  }

  .adviser-analytics-chart-title {
    margin: 0 0 16px;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--aa-ink);
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
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--aa-ink);
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
  <section class="adviser-analytics-stats">
    <article class="adviser-analytics-card">
      <span class="adviser-analytics-icon" style="background:rgba(31,111,107,.14);color:#1f6f6b;">
        <i class="fas fa-percent"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Placement Rate</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($placementRate); ?>%</p>
      </div>
      <p class="adviser-analytics-card-note">
        <i class="fas fa-arrow-up"></i>
        <?php echo adviser_analytics_escape($placementInsight); ?>
      </p>
    </article>

    <article class="adviser-analytics-card">
      <span class="adviser-analytics-icon" style="background:rgba(31,111,107,.14);color:#1f6f6b;">
        <i class="fas fa-building"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Hiring Companies</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($hiringCompanies); ?></p>
      </div>
    </article>

    <article class="adviser-analytics-card">
      <span class="adviser-analytics-icon" style="background:rgba(43,138,132,.16);color:#1f6f6b;">
        <i class="fas fa-tools"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Top Skill Demand</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($topSkill); ?></p>
      </div>
      <p class="adviser-analytics-card-note is-muted">
        <?php echo adviser_analytics_escape($topSkillDemand); ?>% demand index
      </p>
    </article>

    <article class="adviser-analytics-card">
      <span class="adviser-analytics-icon" style="background:rgba(31,111,107,.14);color:#1f6f6b;">
        <i class="fas fa-graduation-cap"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Completions</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($completionRate); ?>%</p>
      </div>
    </article>
  </section>

  <?php if ($showAnalyticsGraphs): ?>
  <h3 class="adviser-analytics-chart-section-title">Analytics Graphs</h3>

  <section class="adviser-analytics-chart-grid">
    <article class="adviser-analytics-chart-card">
      <h3 class="adviser-analytics-chart-title">Student Status Distribution</h3>
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
        <div class="adviser-status-donut" style="background:<?php echo adviser_analytics_escape($donutBackground); ?>;">
          <div class="adviser-status-center">
            <span class="adviser-status-total"><?php echo adviser_analytics_escape($statusChartTotal); ?></span>
            <span class="adviser-status-caption">Students</span>
          </div>
        </div>

        <div class="adviser-status-legend">
          <?php foreach ($statusChart as $item): ?>
            <?php
              $count = (int)($item['value'] ?? 0);
              $share = $statusChartTotal > 0 ? (int)round(($count / $statusChartTotal) * 100) : 0;
            ?>
            <div class="adviser-status-legend-item">
              <span class="adviser-status-key">
                <span class="adviser-status-dot" style="background:<?php echo adviser_analytics_escape($item['color']); ?>;"></span>
                <?php echo adviser_analytics_escape($item['label']); ?>
              </span>
              <strong><?php echo adviser_analytics_escape($count); ?> (<?php echo adviser_analytics_escape($share); ?>%)</strong>
            </div>
          <?php endforeach; ?>
          <?php if ($statusChartTotal === 0): ?>
            <div class="adviser-analytics-empty" style="padding:10px 12px;">
              No status data yet.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </article>

    <article class="adviser-analytics-chart-card">
      <h3 class="adviser-analytics-chart-title">Department Placement Chart</h3>
      <?php if (!empty($deptChart)): ?>
        <div class="adviser-dept-chart">
          <?php foreach ($deptChart as $department): ?>
            <?php
              $rate = max(0, min(100, (int)($department['placement_rate'] ?? 0)));
              $deptLabel = adviser_analytics_department_label($department['department'] ?? '');
            ?>
            <div class="adviser-dept-bar">
              <span class="adviser-dept-bar-rate"><?php echo adviser_analytics_escape($rate); ?>%</span>
              <div class="adviser-dept-bar-track">
                <div class="adviser-dept-bar-fill" style="height:<?php echo adviser_analytics_escape($rate); ?>%;"></div>
              </div>
              <span class="adviser-dept-bar-label"><?php echo adviser_analytics_escape($deptLabel); ?></span>
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
      <h3 class="adviser-analytics-chart-title">Top Skills Trend</h3>
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
            <div class="adviser-skills-row">
              <span class="adviser-skills-label"><?php echo adviser_analytics_escape($skill['skill'] ?? 'Unknown skill'); ?></span>
              <div class="adviser-skills-track">
                <div class="adviser-skills-fill" style="width:<?php echo adviser_analytics_escape($skillsWidth); ?>%"></div>
              </div>
              <span class="adviser-skills-value"><?php echo adviser_analytics_escape($postings); ?> posts</span>
            </div>
          <?php endforeach; ?>
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
    <article class="adviser-analytics-panel">
      <h3 class="adviser-analytics-panel-title">Placement Rate by Department</h3>

      <?php if (!empty($placementByDept)): ?>
        <div class="adviser-analytics-bars">
          <?php foreach ($placementByDept as $department): ?>
            <?php $rate = max(0, min(100, (int)($department['placement_rate'] ?? 0))); ?>
            <div class="adviser-analytics-bar-row">
              <div class="adviser-analytics-bar-top">
                <span class="adviser-analytics-bar-label"><?php echo adviser_analytics_escape(adviser_analytics_department_label($department['department'] ?? '')); ?></span>
                <span class="adviser-analytics-bar-value"><?php echo adviser_analytics_escape($rate); ?>%</span>
              </div>
              <div class="adviser-analytics-track">
                <div class="adviser-analytics-fill" style="width:<?php echo $rate; ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="adviser-analytics-empty">
          Placement insights will appear once your assigned students begin their OJT progress.
        </div>
      <?php endif; ?>
    </article>

    <article class="adviser-analytics-panel">
      <h3 class="adviser-analytics-panel-title">Top In-Demand Skills</h3>

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
            <div class="adviser-analytics-bar-row">
              <div class="adviser-analytics-bar-top">
                <span class="adviser-analytics-bar-label"><?php echo adviser_analytics_escape($skill['skill'] ?? 'Unknown skill'); ?></span>
                <span class="adviser-analytics-bar-value"><?php echo adviser_analytics_escape($demandRate); ?>%</span>
              </div>
              <div class="adviser-analytics-track">
                <div class="adviser-analytics-fill" style="width:<?php echo $demandRate; ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="adviser-analytics-empty">
          Skill demand trends will show here once internship data includes skill requirements.
        </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="adviser-analytics-panel adviser-analytics-companies-panel">
    <h3 class="adviser-analytics-panel-title">Top Hiring Companies</h3>

    <?php if (!empty($topCompanies)): ?>
      <div class="adviser-analytics-companies-grid">
        <?php foreach ($topCompanies as $company): ?>
          <?php
          $internCount = (int)($company['intern_count'] ?? 0);
          $companyRating = $company['avg_rating'] !== null ? number_format((float)$company['avg_rating'], 1) : '0.0';
          $companyCompletion = max(0, min(100, (int)($company['completion_rate'] ?? 0)));
          $companySeed = (int)($company['employer_id'] ?? $internCount);
          ?>
          <article class="adviser-company-card">
            <div class="adviser-company-top">
              <span class="adviser-company-logo" style="background:<?php echo adviser_analytics_company_gradient($companySeed); ?>;">
                <?php echo adviser_analytics_escape(adviser_analytics_company_initials($company['company_name'] ?? '')); ?>
              </span>

              <div>
                <h4 class="adviser-company-name"><?php echo adviser_analytics_escape($company['company_name'] ?? 'Partner company'); ?></h4>
                <p class="adviser-company-industry"><?php echo adviser_analytics_escape($company['industry'] ?? 'General'); ?></p>
              </div>
            </div>

            <span class="adviser-company-badge">
              <i class="fas fa-shield-alt"></i>
              <?php echo adviser_analytics_escape(adviser_analytics_company_partner_label($company)); ?>
            </span>

            <div class="adviser-analytics-track" style="height:12px;background:#dcebe9;">
              <div class="adviser-analytics-fill" style="width:<?php echo $companyCompletion; ?>%;background:linear-gradient(90deg,#1f6f6b 0%,#2b8a84 100%);"></div>
            </div>

            <div class="adviser-company-footer">
              <div>
                <span class="adviser-company-metric-value"><?php echo adviser_analytics_escape($internCount); ?></span>
                <span class="adviser-company-metric-label">Interns</span>
              </div>
              <div>
                <span class="adviser-company-metric-value"><?php echo adviser_analytics_escape($companyRating); ?></span>
                <span class="adviser-company-metric-label">Rating</span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="adviser-analytics-empty">
        Top hiring companies will appear here after students are matched with internship partners.
      </div>
    <?php endif; ?>
  </section>
</div>
