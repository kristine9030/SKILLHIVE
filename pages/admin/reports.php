<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

// ── Reports data ────────────────────────────────────────────────────────────
$appsByStatus = $pdo->query("SELECT status, COUNT(*) cnt FROM application GROUP BY status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalApps = array_sum($appsByStatus);

$topCompanies = $pdo->query("
    SELECT e.company_name, e.industry, COUNT(DISTINCT app.application_id) AS apps,
        COUNT(DISTINCT i.internship_id) AS listings,
        SUM(CASE WHEN app.status='Accepted' THEN 1 ELSE 0 END) AS accepted
    FROM employer e
    LEFT JOIN internship i ON i.employer_id=e.employer_id
    LEFT JOIN application app ON app.internship_id=i.internship_id
    WHERE e.verification_status='Approved'
    GROUP BY e.employer_id
    ORDER BY apps DESC LIMIT 8
")->fetchAll();

$topStudents = $pdo->query("
    SELECT s.first_name, s.last_name, s.program, s.department,
        COUNT(a.application_id) AS apps,
        SUM(CASE WHEN a.status='Accepted' THEN 1 ELSE 0 END) AS accepted
    FROM student s
    LEFT JOIN application a ON a.student_id=s.student_id
    GROUP BY s.student_id
    ORDER BY apps DESC LIMIT 8
")->fetchAll();

$ojtStats = $pdo->query("
    SELECT COUNT(*) AS total,
    SUM(CASE WHEN r.completion_status='Ongoing' THEN 1 ELSE 0 END) AS in_progress,
    SUM(CASE WHEN r.completion_status='Completed' THEN 1 ELSE 0 END) AS completed
    FROM ojt_record r
")->fetch();

// Monthly registrations (last 6 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month, COUNT(*) AS cnt
    FROM student
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
")->fetchAll();

$maxMonthly = max(array_column($monthly,'cnt') ?: [1]);
$statusPalette = [
    'Accepted' => '#12b3ac',
    'Pending' => '#12b3ac',
    'Shortlisted' => '#0f766e',
    'Interview Scheduled' => '#12b3ac',
    'Rejected' => '#12b3ac',
];
$reportStats = [
    ['label' => 'Total Applications', 'value' => (int) $totalApps, 'icon' => 'fa-paper-plane', 'accent' => '#050505', 'soft' => 'rgba(17,24,39,0.08)', 'note' => 'Across all internships and student profiles.'],
    ['label' => 'Accepted', 'value' => (int) ($appsByStatus['Accepted'] ?? 0), 'icon' => 'fa-check-double', 'accent' => '#12b3ac', 'soft' => 'rgba(16,185,129,0.1)', 'note' => 'Successful placements that moved past selection.'],
    ['label' => 'Active OJT', 'value' => (int) ($ojtStats['in_progress'] ?? 0), 'icon' => 'fa-clock', 'accent' => '#12b3ac', 'soft' => 'rgba(6,182,212,0.1)', 'note' => 'Students currently ongoing in their placements.'],
    ['label' => 'Completed', 'value' => (int) ($ojtStats['completed'] ?? 0), 'icon' => 'fa-graduation-cap', 'accent' => '#12b3ac', 'soft' => 'rgba(15,103,101,0.12)', 'note' => 'Records marked complete in the OJT tracker.'],
];
?>

<div class="admin-page">
  <section class="admin-hero">
    <div class="admin-hero-grid">
      <div>
        <div class="admin-kicker"><i class="fas fa-chart-line"></i> Reporting Deck</div>
        <h2 class="admin-hero-title">Make platform trends readable at a glance.</h2>
        <p class="admin-hero-text">This report view focuses on the metrics an admin actually acts on: application momentum, OJT throughput, and the companies and students driving the most activity.</p>
        <div class="admin-hero-actions">
          <a href="<?= $baseUrl ?>/pages/admin/admin_actions.php?action=export_applications_csv" class="btn btn-primary"><i class="fas fa-file-csv"></i> Export Applications</a>
          <a href="<?= $baseUrl ?>/layout.php?page=admin/audit-logs" class="btn btn-ghost"><i class="fas fa-history"></i> Open Audit Logs</a>
        </div>
      </div>
      <div class="admin-highlight-grid">
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-paper-plane"></i> Application Volume</div>
          <div class="admin-highlight-value"><?= number_format($totalApps) ?></div>
          <div class="admin-highlight-note">Submissions tracked across all current records</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-check-double"></i> Accepted</div>
          <div class="admin-highlight-value"><?= number_format($appsByStatus['Accepted'] ?? 0) ?></div>
          <div class="admin-highlight-note">Placements that successfully moved forward</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-clock"></i> Active OJT</div>
          <div class="admin-highlight-value"><?= number_format($ojtStats['in_progress'] ?? 0) ?></div>
          <div class="admin-highlight-note">Records currently marked ongoing</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-graduation-cap"></i> Completed OJT</div>
          <div class="admin-highlight-value"><?= number_format($ojtStats['completed'] ?? 0) ?></div>
          <div class="admin-highlight-note">Finished OJT cycles logged in the system</div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-stat-grid">
    <?php foreach ($reportStats as $card): ?>
    <article class="admin-stat-card" style="--admin-accent:<?= $card['accent'] ?>;--admin-accent-soft:<?= $card['soft'] ?>">
      <div class="admin-stat-top">
        <div class="admin-stat-label"><?= htmlspecialchars($card['label']) ?></div>
        <div class="admin-stat-icon"><i class="fas <?= $card['icon'] ?>"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($card['value']) ?></div>
      <div class="admin-stat-note"><?= htmlspecialchars($card['note']) ?></div>
    </article>
    <?php endforeach; ?>
  </section>

  <section class="admin-grid-2">
    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Application Status Breakdown</div>
          <div class="admin-card-copy">A cleaner read of the hiring pipeline by percentage and raw count.</div>
        </div>
      </div>
      <?php if (empty($appsByStatus)): ?>
      <div class="admin-empty">
        <i class="fas fa-chart-pie"></i>
        <div>No application data available.</div>
      </div>
      <?php else: ?>
      <div class="admin-bar-list">
        <?php foreach ($appsByStatus as $status => $cnt):
          $pct = $totalApps > 0 ? round(($cnt / $totalApps) * 100) : 0;
          $color = $statusPalette[$status] ?? '#64748b';
        ?>
        <div class="admin-bar-row">
          <div class="admin-bar-meta">
            <div class="admin-bar-label"><?= htmlspecialchars($status) ?></div>
            <div class="admin-bar-value"><?= number_format($cnt) ?> · <?= $pct ?>%</div>
          </div>
          <div class="admin-bar-track">
            <div class="admin-bar-fill" style="--fill:<?= $color ?>;width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>

    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Student Registrations</div>
          <div class="admin-card-copy">Monthly growth over the last six months, tuned for quick scanning.</div>
        </div>
        <span class="admin-badge-subtle"><i class="fas fa-user-plus"></i> 6 month view</span>
      </div>
      <?php if (empty($monthly)): ?>
      <div class="admin-empty">
        <i class="fas fa-users-slash"></i>
        <div>No registration data available.</div>
      </div>
      <?php else: ?>
      <div class="admin-columns-chart">
        <?php foreach ($monthly as $m):
          $height = max(16, (int) (($m['cnt'] / $maxMonthly) * 100));
        ?>
        <div class="admin-column">
          <div class="admin-column-value"><?= number_format($m['cnt']) ?></div>
          <div class="admin-column-bar" style="height:<?= $height ?>%"></div>
          <div class="admin-column-label"><?= htmlspecialchars($m['month']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
  </section>

  <section class="admin-grid-2">
    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Top Companies by Applications</div>
          <div class="admin-card-copy">Approved employers generating the strongest applicant demand.</div>
        </div>
      </div>
      <?php if (empty($topCompanies)): ?>
      <div class="admin-empty">
        <i class="fas fa-building-circle-xmark"></i>
        <div>No company ranking data available.</div>
      </div>
      <?php else: ?>
      <div class="admin-rank-list">
        <?php foreach ($topCompanies as $i => $co):
          $companyMark = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $co['company_name']), 0, 2));
        ?>
        <div class="admin-rank-row">
          <div class="admin-rank-number">#<?= $i + 1 ?></div>
          <div class="admin-mark"><?= htmlspecialchars($companyMark ?: 'CO') ?></div>
          <div class="admin-rank-meta">
            <div class="admin-rank-title"><?= htmlspecialchars($co['company_name']) ?></div>
            <div class="admin-rank-copy"><?= htmlspecialchars($co['industry']) ?> · <?= number_format($co['listings']) ?> listings</div>
          </div>
          <div class="admin-rank-stats">
            <div class="admin-rank-value"><?= number_format($co['apps']) ?> apps</div>
            <div class="admin-rank-note"><?= number_format($co['accepted']) ?> accepted</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>

    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Most Active Students</div>
          <div class="admin-card-copy">Students generating the most placement activity in the current dataset.</div>
        </div>
      </div>
      <?php if (empty($topStudents)): ?>
      <div class="admin-empty">
        <i class="fas fa-user-large-slash"></i>
        <div>No student ranking data available.</div>
      </div>
      <?php else: ?>
      <div class="admin-rank-list">
        <?php foreach ($topStudents as $i => $st):
          $initials = strtoupper(substr($st['first_name'], 0, 1) . substr($st['last_name'], 0, 1));
        ?>
        <div class="admin-rank-row">
          <div class="admin-rank-number">#<?= $i + 1 ?></div>
          <div class="admin-avatar"><?= htmlspecialchars($initials) ?></div>
          <div class="admin-rank-meta">
            <div class="admin-rank-title"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></div>
            <div class="admin-rank-copy"><?= htmlspecialchars($st['program']) ?><?php if (!empty($st['department'])): ?> · <?= htmlspecialchars($st['department']) ?><?php endif; ?></div>
          </div>
          <div class="admin-rank-stats">
            <div class="admin-rank-value"><?= number_format($st['apps']) ?> apps</div>
            <div class="admin-rank-note"><?= number_format($st['accepted']) ?> accepted</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
  </section>
</div>
