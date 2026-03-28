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
?>

<style>
  .adviser-analytics-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    color: var(--text);
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
    color: var(--text3);
    font-weight: 500;
  }

  .adviser-analytics-card-value {
    margin: 0;
    font-size: 1.95rem;
    line-height: 1;
    font-weight: 700;
    color: var(--text);
  }

  .adviser-analytics-card-note {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.79rem;
    color: #13a66b;
    font-weight: 600;
  }

  .adviser-analytics-card-note.is-muted {
    color: var(--text3);
    font-weight: 500;
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
    color: var(--text);
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
    color: var(--text);
    font-weight: 600;
  }

  .adviser-analytics-bar-value {
    color: #e53935;
    font-weight: 600;
  }

  .adviser-analytics-track {
    height: 10px;
    border-radius: 999px;
    background: #ecebff;
    overflow: hidden;
  }

  .adviser-analytics-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #b71c1c 0%, #ff4d4f 100%);
  }

  .adviser-analytics-empty {
    padding: 18px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    background: #fafafa;
    color: #6b7280;
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
    color: var(--text);
  }

  .adviser-company-industry {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text3);
  }

  .adviser-company-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 6px 10px;
    border-radius: 999px;
    background: #e2f5f1;
    color: #12a975;
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
    color: var(--text);
  }

  .adviser-company-metric-label {
    display: block;
    margin-top: 3px;
    font-size: 0.76rem;
    color: var(--text3);
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
    .adviser-analytics-grid {
      grid-template-columns: 1fr;
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
      <span class="adviser-analytics-icon" style="background:rgba(124,58,237,.10);color:#d32f2f;">
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
      <span class="adviser-analytics-icon" style="background:rgba(20,184,166,.12);color:#11b89a;">
        <i class="fas fa-building"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Hiring Companies</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($hiringCompanies); ?></p>
      </div>
    </article>

    <article class="adviser-analytics-card">
      <span class="adviser-analytics-icon" style="background:rgba(245,158,11,.14);color:#f59e0b;">
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
      <span class="adviser-analytics-icon" style="background:rgba(14,165,233,.12);color:#06b6d4;">
        <i class="fas fa-graduation-cap"></i>
      </span>
      <div>
        <p class="adviser-analytics-card-label">Completions</p>
        <p class="adviser-analytics-card-value"><?php echo adviser_analytics_escape($completionRate); ?>%</p>
      </div>
    </article>
  </section>

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

            <div class="adviser-analytics-track" style="height:12px;background:#e4f6f1;">
              <div class="adviser-analytics-fill" style="width:<?php echo $companyCompletion; ?>%;background:linear-gradient(90deg,#16a34a 0%,#34d399 100%);"></div>
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
