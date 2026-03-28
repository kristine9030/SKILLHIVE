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
        'placed_students' => 0,
        'endorsed' => 0,
        'pending_review' => 0,
        'at_risk_students' => 0,
        'partner_companies' => 0,
    ],
    'departments' => [],
    'recent_activity' => [],
    'pending_endorsements' => [],
    'at_risk_students' => [],
];

if ($adviserId > 0) {
    try {
        $dashboardData = getAdviserDashboardData($pdo, $adviserId);
    } catch (Throwable $e) {
        $dashboardData = $dashboardData;
    }
}

$stats = $dashboardData['stats'];
$profile = $dashboardData['profile'];
$pendingEndorsements = $dashboardData['pending_endorsements'];
$atRiskStudents = $dashboardData['at_risk_students'];
?>

<style>
  .adviser-dashboard-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    color: var(--text);
    font-size: var(--font-size-body);
  }

  .adviser-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
  }

  .adviser-stat-card,
  .adviser-dashboard-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
  }

  .adviser-stat-card {
    padding: 18px;
    min-height: 132px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .adviser-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .adviser-stat-label {
    margin: 0 0 6px;
    font-size: 0.82rem;
    color: var(--text3);
    font-weight: 500;
  }

  .adviser-stat-value {
    margin: 0;
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1;
    color: var(--text);
  }

  .adviser-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.08fr) minmax(340px, 1fr);
    gap: 18px;
  }

  .adviser-dashboard-panel {
    padding: 18px;
  }

  .adviser-dashboard-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
  }

  .adviser-dashboard-panel-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
  }

  .adviser-dashboard-link {
    color: var(--text2);
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
  }

  .adviser-dashboard-link:hover {
    color: var(--text);
  }

  .adviser-dashboard-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #4b5563;
    font-size: 0.74rem;
    font-weight: 700;
  }

  .adviser-dashboard-table-wrap {
    overflow-x: auto;
  }

  .adviser-dashboard-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .adviser-dashboard-table th {
    padding: 0 14px 12px;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text3);
    text-align: left;
    font-weight: 600;
  }

  .adviser-dashboard-table td {
    padding: 16px 14px;
    border-top: 1px solid var(--border);
    font-size: 0.84rem;
    vertical-align: middle;
    color: var(--text);
  }

  .adviser-dashboard-table tr:first-child td {
    border-top-color: var(--border);
  }

  .adviser-dashboard-table tr:hover td {
    background: #fafafa;
  }

  .adviser-student-name {
    font-weight: 600;
    color: var(--text);
  }

  .adviser-muted {
    font-size: 0.74rem;
    color: #999;
  }

  .adviser-dashboard-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 600;
    white-space: nowrap;
  }

  .adviser-dashboard-pill.is-danger {
    background: #ffe5e2;
    color: #d32f2f;
  }

  .adviser-dashboard-pill.is-warning {
    background: #fff0cf;
    color: #d98309;
  }

  .adviser-dashboard-pill.is-success {
    background: #ddf8eb;
    color: #16855a;
  }

  .adviser-dashboard-pill.is-neutral {
    background: #f4edf0;
    color: #7b6570;
  }

  .adviser-dashboard-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .adviser-dashboard-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 32px;
    padding: 6px 12px;
    border-radius: 50px;
    border: 1px solid transparent;
    text-decoration: none;
    font-size: 0.78rem;
    font-weight: 600;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  }

  .adviser-dashboard-btn:hover {
    transform: translateY(-1px);
  }

  .adviser-dashboard-btn.primary {
    background: #111;
    color: #fff;
    box-shadow: none;
  }

  .adviser-dashboard-btn.secondary {
    background: #fff;
    color: var(--text);
    border-color: var(--border);
  }

  .adviser-dashboard-risk-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .adviser-dashboard-risk-card {
    padding: 16px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: #fff;
  }

  .adviser-dashboard-risk-card.is-warning {
    background: #fff;
    border-color: #fde68a;
  }

  .adviser-dashboard-risk-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
  }

  .adviser-dashboard-risk-name {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-dashboard-risk-meta {
    margin: 0;
    color: #6b7280;
    font-size: 0.78rem;
    line-height: 1.45;
  }

  .adviser-dashboard-empty {
    padding: 18px;
    border-radius: 12px;
    background: #fafafa;
    border: 1px dashed var(--border);
    color: #6b7280;
    font-size: 0.82rem;
  }

  @media (max-width: 1180px) {
    .adviser-dashboard-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .adviser-dashboard-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .adviser-dashboard-stats {
      grid-template-columns: 1fr;
    }

    .adviser-dashboard-panel,
    .adviser-stat-card {
      border-radius: 14px;
      padding: 18px;
    }

    .adviser-dashboard-table th,
    .adviser-dashboard-table td {
      padding-left: 10px;
      padding-right: 10px;
    }

    .adviser-dashboard-actions {
      gap: 8px;
    }

    .adviser-dashboard-btn {
      width: 100%;
    }
  }
</style>

<div class="adviser-dashboard-page">

  <div class="adviser-dashboard-stats">
    <div class="adviser-stat-card">
      <span class="adviser-stat-icon" style="background:rgba(124,58,237,.10);color:#7c3aed;"><i class="fas fa-user-graduate"></i></span>
      <div>
        <p class="adviser-stat-label">Assigned Students</p>
        <p class="adviser-stat-value"><?php echo (int)($stats['my_students'] ?? 0); ?></p>
      </div>
    </div>

    <div class="adviser-stat-card">
      <span class="adviser-stat-icon" style="background:rgba(22,163,74,.12);color:#149356;"><i class="fas fa-circle-check"></i></span>
      <div>
        <p class="adviser-stat-label">Placed Students</p>
        <p class="adviser-stat-value"><?php echo (int)($stats['placed_students'] ?? 0); ?></p>
      </div>
    </div>

    <div class="adviser-stat-card">
      <span class="adviser-stat-icon" style="background:rgba(245,158,11,.14);color:#dd8a04;"><i class="fas fa-triangle-exclamation"></i></span>
      <div>
        <p class="adviser-stat-label">At Risk</p>
        <p class="adviser-stat-value"><?php echo (int)($stats['at_risk_students'] ?? 0); ?></p>
      </div>
    </div>

    <div class="adviser-stat-card">
      <span class="adviser-stat-icon" style="background:rgba(14,165,233,.12);color:#0891b2;"><i class="fas fa-building"></i></span>
      <div>
        <p class="adviser-stat-label">Partner Companies</p>
        <p class="adviser-stat-value"><?php echo (int)($stats['partner_companies'] ?? 0); ?></p>
      </div>
    </div>
  </div>

  <div class="adviser-dashboard-grid">
    <section class="adviser-dashboard-panel">
      <div class="adviser-dashboard-panel-header">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <h3 class="adviser-dashboard-panel-title">Pending Endorsements</h3>
          <span class="adviser-dashboard-chip"><?php echo count($pendingEndorsements); ?></span>
        </div>
        <a class="adviser-dashboard-link" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement">Open endorsements</a>
      </div>

      <div class="adviser-dashboard-table-wrap">
        <table class="adviser-dashboard-table">
          <thead>
            <tr>
              <th>Student</th>
              <th>Company</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($pendingEndorsements)): ?>
              <?php foreach ($pendingEndorsements as $endorsement): ?>
                <?php
                $endorsementMeta = adviser_dashboard_endorsement_meta($endorsement['status'] ?? '');
                $studentName = trim((string)($endorsement['first_name'] ?? '') . ' ' . (string)($endorsement['last_name'] ?? ''));
                ?>
                <tr>
                  <td>
                    <div class="adviser-student-name"><?php echo adviser_dashboard_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></div>
                    <div class="adviser-muted"><?php echo adviser_dashboard_escape($endorsement['internship_title'] ?? 'Internship application'); ?></div>
                  </td>
                  <td><?php echo adviser_dashboard_escape($endorsement['company_name'] ?? 'No company yet'); ?></td>
                  <td>
                    <span class="adviser-dashboard-pill <?php echo adviser_dashboard_escape($endorsementMeta['pill_class']); ?>">
                      <?php echo adviser_dashboard_escape($endorsementMeta['label']); ?>
                    </span>
                  </td>
                  <td>
                    <a class="adviser-dashboard-btn <?php echo $endorsementMeta['action_label'] === 'Approve' ? 'primary' : 'secondary'; ?>" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement">
                      <?php echo adviser_dashboard_escape($endorsementMeta['action_label']); ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">
                  <div class="adviser-dashboard-empty">
                    No pending endorsements right now. New endorsement requests will show here once students submit them.
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="adviser-dashboard-panel">
      <div class="adviser-dashboard-panel-header">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <h3 class="adviser-dashboard-panel-title">Students At Risk</h3>
          <span class="adviser-dashboard-chip"><?php echo count($atRiskStudents); ?></span>
        </div>
        <a class="adviser-dashboard-link" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring">Open monitoring</a>
      </div>

      <div class="adviser-dashboard-risk-list">
        <?php if (!empty($atRiskStudents)): ?>
          <?php foreach ($atRiskStudents as $student): ?>
            <?php
            $studentName = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
            $riskLabel = (string)($student['status_label'] ?? 'Warning');
            $riskSearch = rawurlencode($studentName);
            $reminderSubject = rawurlencode('SkillHive OJT Reminder');
            $reminderBody = rawurlencode(
                'Hello ' . $studentName . ',' . "\n\n"
                . 'This is a reminder to update your OJT logs and internship progress in SkillHive.' . "\n\n"
                . 'Thank you.'
            );
            ?>
            <article class="adviser-dashboard-risk-card <?php echo $riskLabel === 'Warning' ? 'is-warning' : ''; ?>">
              <div class="adviser-dashboard-risk-top">
                <div>
                  <h4 class="adviser-dashboard-risk-name"><?php echo adviser_dashboard_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></h4>
                  <p class="adviser-dashboard-risk-meta"><?php echo adviser_dashboard_escape($student['risk_summary'] ?? 'Monitoring attention needed.'); ?></p>
                </div>
                <span class="adviser-dashboard-pill <?php echo adviser_dashboard_escape(adviser_dashboard_pill_class($riskLabel)); ?>">
                  <?php echo adviser_dashboard_escape($riskLabel); ?>
                </span>
              </div>

              <div class="adviser-dashboard-actions">
                <a class="adviser-dashboard-btn secondary" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring&search=<?php echo $riskSearch; ?>">
                  <i class="fas fa-eye"></i>
                  View Logs
                </a>
                <?php if (!empty($student['email'])): ?>
                  <a class="adviser-dashboard-btn secondary" href="mailto:<?php echo adviser_dashboard_escape($student['email']); ?>?subject=<?php echo $reminderSubject; ?>&body=<?php echo $reminderBody; ?>">
                    <i class="fas fa-paper-plane"></i>
                    Send Reminder
                  </a>
                <?php else: ?>
                  <span class="adviser-dashboard-btn secondary" style="opacity:.6;cursor:not-allowed;">
                    <i class="fas fa-envelope-slash"></i>
                    No Email
                  </span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="adviser-dashboard-empty">
            No students are currently flagged as at risk. Keep monitoring daily logs to make sure progress stays on track.
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
