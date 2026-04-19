<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

// ── Live stats ──────────────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM student)  AS total_students,
        (SELECT COUNT(*) FROM employer) AS total_employers,
        (SELECT COUNT(*) FROM admin)    AS total_admins,
        (SELECT COUNT(*) FROM internship WHERE status='Open') AS open_internships,
        (SELECT COUNT(*) FROM application) AS total_applications,
  (SELECT COUNT(*) FROM ojt_record WHERE completion_status='Ongoing') AS active_ojts,
        (SELECT COUNT(*) FROM employer WHERE verification_status='Pending') AS pending_verifications,
        (SELECT COUNT(*) FROM employer WHERE verification_status='Approved') AS verified_companies
")->fetch();

$recentApps = $pdo->query("
  SELECT a.application_date, s.first_name, s.last_name, i.title, e.company_name, a.status
    FROM application a
    JOIN student s ON s.student_id = a.student_id
    JOIN internship i ON i.internship_id = a.internship_id
    JOIN employer e ON e.employer_id = i.employer_id
  ORDER BY a.application_date DESC, a.application_id DESC LIMIT 6
")->fetchAll();

$recentEmployers = $pdo->query("
    SELECT employer_id, company_name, industry, verification_status, created_at
    FROM employer ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$appStatusBreakdown = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM application GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalUsers = ($stats['total_students'] ?? 0) + ($stats['total_employers'] ?? 0) + ($stats['total_admins'] ?? 0);
$breakdownCards = [
    ['label' => 'Pending', 'value' => $appStatusBreakdown['Pending'] ?? 0, 'accent' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
    ['label' => 'Shortlisted', 'value' => $appStatusBreakdown['Shortlisted'] ?? 0, 'accent' => '#0f766e', 'bg' => 'rgba(15, 118, 110, 0.1)'],
    ['label' => 'Interview Scheduled', 'value' => $appStatusBreakdown['Interview Scheduled'] ?? 0, 'accent' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.1)'],
    ['label' => 'Accepted', 'value' => $appStatusBreakdown['Accepted'] ?? 0, 'accent' => '#4f46e5', 'bg' => 'rgba(79, 70, 229, 0.1)'],
    ['label' => 'Rejected', 'value' => $appStatusBreakdown['Rejected'] ?? 0, 'accent' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
];
$healthCards = [
    ['icon' => 'fa-briefcase', 'label' => 'Open Listings', 'copy' => 'Active internship roles accepting applicants right now.', 'value' => (int) ($stats['open_internships'] ?? 0), 'tone' => '#111827', 'bg' => 'rgba(15, 23, 42, 0.08)'],
    ['icon' => 'fa-paper-plane', 'label' => 'Applications', 'copy' => 'Total submissions currently flowing through the marketplace.', 'value' => (int) ($stats['total_applications'] ?? 0), 'tone' => '#4f46e5', 'bg' => 'rgba(79, 70, 229, 0.1)'],
    ['icon' => 'fa-shield-halved', 'label' => 'Pending Review', 'copy' => 'Companies waiting for admin approval or additional checks.', 'value' => (int) ($stats['pending_verifications'] ?? 0), 'tone' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
    ['icon' => 'fa-badge-check', 'label' => 'Verified Companies', 'copy' => 'Approved partners currently visible across the platform.', 'value' => (int) ($stats['verified_companies'] ?? 0), 'tone' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
];
$statusStyles = [
    'Pending' => ['color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.12)'],
    'Shortlisted' => ['color' => '#0f766e', 'bg' => 'rgba(15, 118, 110, 0.12)'],
    'Interview Scheduled' => ['color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.12)'],
    'Accepted' => ['color' => '#4f46e5', 'bg' => 'rgba(79, 70, 229, 0.12)'],
    'Rejected' => ['color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.12)'],
    'Approved' => ['color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.12)'],
    'Flagged' => ['color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.12)'],
];
?>

<div class="admin-page">
  <section class="admin-hero">
    <div class="admin-hero-grid">
      <div>
        <div class="admin-kicker"><i class="fas fa-shield-halved"></i> System Control Center</div>
        <h2 class="admin-hero-title">See the platform pulse in one screen.</h2>
        <p class="admin-hero-text">Track user growth, review company approvals, and spot application movement before it turns into support issues. This view is built for quick scanning, not hunting through tables.</p>
        <div class="admin-hero-actions">
          <a href="<?= $baseUrl ?>/layout.php?page=admin/verify-companies" class="btn btn-ghost"><i class="fas fa-building"></i> Review Companies</a>
          <a href="<?= $baseUrl ?>/layout.php?page=admin/users" class="btn btn-primary"><i class="fas fa-users"></i> Manage Users</a>
        </div>
      </div>
      <div class="admin-highlight-grid">
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-user-plus"></i> Total Accounts</div>
          <div class="admin-highlight-value"><?= number_format($totalUsers) ?></div>
          <div class="admin-highlight-note"><?= number_format($stats['total_students'] ?? 0) ?> students, <?= number_format($stats['total_employers'] ?? 0) ?> companies</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-hourglass-half"></i> Review Queue</div>
          <div class="admin-highlight-value"><?= number_format($stats['pending_verifications'] ?? 0) ?></div>
          <div class="admin-highlight-note">Company submissions waiting for admin action</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-briefcase"></i> Open Listings</div>
          <div class="admin-highlight-value"><?= number_format($stats['open_internships'] ?? 0) ?></div>
          <div class="admin-highlight-note">Internship roles students can apply to today</div>
        </div>
        <div class="admin-highlight-card">
          <div class="admin-highlight-label"><i class="fas fa-route"></i> Active OJT</div>
          <div class="admin-highlight-value"><?= number_format($stats['active_ojts'] ?? 0) ?></div>
          <div class="admin-highlight-note">Students currently progressing through placements</div>
        </div>
      </div>
    </div>
  </section>

  <?php
  // Banner variables for admin
  $bannerGreeting = 'Welcome back';
  $bannerUserName = 'Administrator';
  $bannerTitle = 'System Overview';
  $bannerDescription = 'Monitor platform health, manage users, verify companies, and ensure smooth operations across the entire system.';
  $bannerStats = [
    ['value' => number_format($stats['total_students'] ?? 0), 'label' => 'Students'],
    ['value' => number_format($stats['total_employers'] ?? 0), 'label' => 'Companies'],
    ['value' => number_format($stats['open_internships'] ?? 0), 'label' => 'Open Roles'],
  ];
  include __DIR__ . '/../../components/dashboard_banner.php';
  ?>

  <section class="admin-stat-grid">
    <article class="admin-stat-card" style="--admin-accent:#111827;--admin-accent-soft:rgba(17,24,39,0.08)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Total Users</div>
        <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
      <div class="admin-stat-note">Combined student, employer, and administrator accounts.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#06b6d4;--admin-accent-soft:rgba(6,182,212,0.1)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Students</div>
        <div class="admin-stat-icon"><i class="fas fa-user-graduate"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($stats['total_students'] ?? 0) ?></div>
      <div class="admin-stat-note">Learners building profiles, applying, and tracking OJT progress.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#10b981;--admin-accent-soft:rgba(16,185,129,0.1)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Companies</div>
        <div class="admin-stat-icon"><i class="fas fa-building"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($stats['total_employers'] ?? 0) ?></div>
      <div class="admin-stat-note"><?= number_format($stats['verified_companies'] ?? 0) ?> verified and <?= number_format($stats['pending_verifications'] ?? 0) ?> still awaiting review.</div>
    </article>
    <article class="admin-stat-card" style="--admin-accent:#f59e0b;--admin-accent-soft:rgba(245,158,11,0.1)">
      <div class="admin-stat-top">
        <div class="admin-stat-label">Active OJT</div>
        <div class="admin-stat-icon"><i class="fas fa-briefcase"></i></div>
      </div>
      <div class="admin-stat-value"><?= number_format($stats['active_ojts'] ?? 0) ?></div>
      <div class="admin-stat-note">Only ongoing placements are counted here for a truer live snapshot.</div>
    </article>
  </section>

  <section class="admin-grid-2">
    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Application Funnel</div>
          <div class="admin-card-copy">Status distribution shown as a compact scan card for faster triage.</div>
        </div>
        <span class="admin-badge-subtle"><i class="fas fa-layer-group"></i> Live totals</span>
      </div>
      <div class="admin-chip-grid">
        <?php foreach ($breakdownCards as $card): ?>
        <div class="admin-chip-card" style="--chip-bg:<?= $card['bg'] ?>;--chip-border:<?= $card['bg'] ?>;--chip-color:<?= $card['accent'] ?>">
          <div class="admin-chip-value"><?= number_format($card['value']) ?></div>
          <div class="admin-chip-label"><?= htmlspecialchars($card['label']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Platform Health</div>
          <div class="admin-card-copy">Core operational signals that usually indicate where admin attention is needed next.</div>
        </div>
      </div>
      <div class="admin-stack">
        <?php foreach ($healthCards as $card): ?>
        <div class="admin-health-row" style="--tone-color:<?= $card['tone'] ?>;--tone-bg:<?= $card['bg'] ?>">
          <div class="admin-health-icon"><i class="fas <?= $card['icon'] ?>"></i></div>
          <div>
            <div class="admin-health-label"><?= htmlspecialchars($card['label']) ?></div>
            <div class="admin-health-copy"><?= htmlspecialchars($card['copy']) ?></div>
          </div>
          <div class="admin-health-value"><?= number_format($card['value']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="admin-grid-2">
    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">Recent Applications</div>
          <div class="admin-card-copy">Newest submissions entering the placement pipeline.</div>
        </div>
      </div>
      <?php if (empty($recentApps)): ?>
      <div class="admin-empty">
        <i class="fas fa-inbox"></i>
        <div>No applications yet.</div>
      </div>
      <?php else: ?>
      <div class="admin-list">
        <?php foreach ($recentApps as $app):
          $style = $statusStyles[$app['status']] ?? ['color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.12)'];
          $studentInitials = strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1));
        ?>
        <div class="admin-list-item">
          <div class="admin-avatar"><?= htmlspecialchars($studentInitials) ?></div>
          <div>
            <div class="admin-list-title"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
            <div class="admin-list-copy"><?= htmlspecialchars($app['title']) ?> at <?= htmlspecialchars($app['company_name']) ?><?php if (!empty($app['application_date'])): ?> · <?= htmlspecialchars(date('M d, Y', strtotime($app['application_date']))) ?><?php endif; ?></div>
          </div>
          <span class="admin-badge" style="--badge-color:<?= $style['color'] ?>;--badge-bg:<?= $style['bg'] ?>"><?= htmlspecialchars($app['status']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>

    <article class="admin-card">
      <div class="admin-card-header">
        <div>
          <div class="admin-card-title">New Companies</div>
          <div class="admin-card-copy">Latest employer accounts entering the system and their current verification state.</div>
        </div>
        <a href="<?= $baseUrl ?>/layout.php?page=admin/verify-companies" class="btn btn-ghost btn-sm">View Registry</a>
      </div>
      <?php if (empty($recentEmployers)): ?>
      <div class="admin-empty">
        <i class="fas fa-building"></i>
        <div>No companies yet.</div>
      </div>
      <?php else: ?>
      <div class="admin-list">
        <?php foreach ($recentEmployers as $emp):
          $style = $statusStyles[$emp['verification_status']] ?? ['color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.12)'];
          $companyMark = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $emp['company_name']), 0, 2));
        ?>
        <div class="admin-list-item">
          <div class="admin-mark"><?= htmlspecialchars($companyMark ?: 'CO') ?></div>
          <div>
            <div class="admin-list-title"><?= htmlspecialchars($emp['company_name']) ?></div>
            <div class="admin-list-copy"><?= htmlspecialchars($emp['industry']) ?><?php if (!empty($emp['created_at'])): ?> · Joined <?= htmlspecialchars(date('M Y', strtotime($emp['created_at']))) ?><?php endif; ?></div>
          </div>
          <span class="admin-badge" style="--badge-color:<?= $style['color'] ?>;--badge-bg:<?= $style['bg'] ?>"><?= htmlspecialchars($emp['verification_status']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
  </section>
</div>
