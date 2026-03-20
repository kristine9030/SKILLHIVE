<?php
// analytics.php — Adviser analytics dashboard (dynamic)

require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/analytics/data.php';

// Unpack data
$stats = $data['stats'] ?? [];
$placement_by_dept = $data['placement_by_dept'] ?? [];
$top_companies = $data['top_companies'] ?? [];
$top_skills = $data['top_skills'] ?? [];
$trends = $data['trends'] ?? [];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Analytics</h2>
    <p class="page-subtitle">Department-wide internship analytics and placement rates.</p>
  </div>
</div>

<!-- Overview Stats -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-percentage" style="color:#06B6D4"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num"><?php echo adviser_analytics_escape($stats['placement_rate'] ?? 0); ?>%</div>
      <div class="stat-card-label">Placement Rate</div>
    </div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> Data from active OJTs</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-star" style="color:#10B981"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num"><?php echo adviser_analytics_escape($stats['avg_eval_rating'] ?? 0); ?></div>
      <div class="stat-card-label">Avg. Eval Rating</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num"><?php echo adviser_analytics_escape($stats['avg_ojt_hours'] ?? 0); ?></div>
      <div class="stat-card-label">Avg. OJT Hours</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-user-check" style="color:#10B981"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num"><?php echo adviser_analytics_escape($stats['completion_rate'] ?? 0); ?>%</div>
      <div class="stat-card-label">Completion Rate</div>
    </div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Placement by Department -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Placement Rate by Department</h3></div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php if (empty($placement_by_dept)): ?>
          <p style="color:#999;font-size:.9rem">No department data available yet.</p>
        <?php else: ?>
          <?php foreach ($placement_by_dept as $dept): ?>
            <div>
              <div class="skill-bar-header">
                <span><?php echo adviser_analytics_escape($dept['department']); ?></span>
                <span><?php echo adviser_analytics_escape($dept['placement_rate']); ?>%</span>
              </div>
              <div class="dept-bar">
                <div class="dept-bar-fill" style="width:<?php echo adviser_analytics_escape($dept['placement_rate']); ?>%;background:<?php echo adviser_analytics_get_gradient($dept['placement_rate']); ?>"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Companies -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Top Partner Companies</h3></div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Company</th><th>Interns</th><th>Avg. Rating</th><th>Completion</th></tr>
          </thead>
          <tbody>
            <?php if (empty($top_companies)): ?>
              <tr><td colspan="4" style="text-align:center;color:#999;padding:20px">No company data available yet.</td></tr>
            <?php else: ?>
              <?php foreach ($top_companies as $company): ?>
                <tr>
                  <td><?php echo adviser_analytics_escape($company['company_name']); ?></td>
                  <td><?php echo adviser_analytics_escape($company['intern_count']); ?></td>
                  <td><span style="color:#F59E0B"><i class="fas fa-star"></i> <?php echo adviser_analytics_escape($company['avg_rating'] ?? 'N/A'); ?></span></td>
                  <td><?php echo adviser_analytics_escape($company['completion_rate'] ?? 0); ?>%</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Skills in Demand -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Most Requested Skills</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php if (empty($top_skills)): ?>
          <p style="color:#999;font-size:.9rem">No skill data available yet.</p>
        <?php else: ?>
          <?php 
            $maxPostings = max(array_column($top_skills, 'postings'));
            $skillColors = ['#06B6D4', '#10B981', '#F59E0B', '#EF4444', '#6F42C1'];
            $colorIdx = 0;
          ?>
          <?php foreach ($top_skills as $skill): ?>
            <div>
              <div class="skill-bar-header">
                <span><?php echo adviser_analytics_escape($skill['skill']); ?></span>
                <span><?php echo adviser_analytics_escape($skill['postings']); ?> postings</span>
              </div>
              <div class="skill-bar-bg">
                <div class="skill-bar-fill" style="width:<?php echo round(($skill['postings'] / $maxPostings) * 100); ?>%;background:<?php echo $skillColors[$colorIdx % 5]; ?>"></div>
              </div>
            </div>
            <?php $colorIdx++; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Semester Summary -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Semester Summary</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row">
          <span>Total Students</span>
          <span style="font-weight:700"><?php echo adviser_analytics_escape($stats['total_students']); ?></span>
        </div>
        <div class="mini-row">
          <span>Placed</span>
          <span style="font-weight:700;color:#10B981"><?php echo adviser_analytics_escape($stats['placed']); ?></span>
        </div>
        <div class="mini-row">
          <span>Searching</span>
          <span style="font-weight:700;color:#F59E0B"><?php echo adviser_analytics_escape($stats['searching']); ?></span>
        </div>
        <div class="mini-row">
          <span>Completed OJT</span>
          <span style="font-weight:700;color:#10B981"><?php echo adviser_analytics_escape($stats['completed_ojt']); ?></span>
        </div>
        <div class="mini-row">
          <span>In Progress</span>
          <span style="font-weight:700;color:#06B6D4"><?php echo adviser_analytics_escape($stats['in_progress']); ?></span>
        </div>
      </div>
    </div>

    <!-- Trend -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Trends</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:.82rem">
        <?php if (empty($trends)): ?>
          <p style="color:#999;font-size:.85rem">Trends will appear as students progress.</p>
        <?php else: ?>
          <?php foreach ($trends as $trend): ?>
            <?php $style = adviser_analytics_get_trend_style($trend['type']); ?>
            <div style="padding:10px;background:<?php echo $style['bg']; ?>;border-radius:8px;color:<?php echo $style['color']; ?>">
              <i class="fas <?php echo adviser_analytics_escape($trend['icon']); ?>"></i> <?php echo adviser_analytics_escape($trend['text']); ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
