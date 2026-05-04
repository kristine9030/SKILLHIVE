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
    --ad-ink: #000000;
    --ad-verdigris-dark: #1f6f6b;
    --ad-verdigris: #2b8a84;
    --ad-verdigris-soft: #78a9a6;
    display: flex;
    flex-direction: column;
    gap: 20px;
    color: var(--ad-ink);
    font-size: var(--font-size-body);
  }

  .adviser-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }

  .adviser-dashboard-panel {
    padding: 18px;
  }

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
    position: relative;
    overflow: hidden;
    transition: all var(--transition);
  }

  .adviser-stat-card::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -30px;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(156, 163, 175, 0.08);
    pointer-events: none;
  }

  .adviser-stat-card::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: -20px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(156, 163, 175, 0.06);
    pointer-events: none;
  }

  .adviser-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.10);
  }

  .adviser-dashboard-panel {
    padding: 18px;
  }

  .adviser-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }

  .adviser-stat-card {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all var(--transition);
    min-height: 140px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  }

  .adviser-stat-card:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    transform: translateY(-4px);
  }

  .adviser-stat-card::before {
    content: '';
    position: absolute;
    top: -30px;
    right: -20px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    pointer-events: none;
    opacity: 0.08;
    background: #162550;
  }

  .adviser-stat-card::after {
    content: '';
    position: absolute;
    top: 40px;
    right: -80px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    pointer-events: none;
    opacity: 0.06;
    background: #162550;
  }

  .adviser-stat-card-icon {
    width: 200px !important;
    height: 200px !important;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0.08) 100%) !important;
    color: transparent !important;
    box-shadow: none !important;
    margin: -25px 0 -25px -25px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    z-index: 2;
    flex-shrink: 0;
  }

  .adviser-stat-card-icon i {
    color: #1f6f6b;
    font-size: 5rem;
  }

  .adviser-stat-card-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    z-index: 2;
    flex: 1;
    align-items: flex-start;
    text-align: left;
  }

  .adviser-stat-card-num-row {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    justify-content: flex-end;
    width: 100%;
  }

  .adviser-stat-card-num {
    font-family: 'Montserrat', sans-serif;
    font-size: 3.2rem;
    font-weight: 800;
    line-height: 1;
    position: relative;
    z-index: 2;
    flex-shrink: 0;
    max-width: 180px;
    word-break: break-word;
    overflow-wrap: break-word;
    text-align: right;
  }

  .adviser-stat-card-label {
    font-size: .85rem;
    color: var(--text2);
    font-weight: 500;
    position: relative;
    z-index: 2;
    text-align: right;
    align-self: flex-end;
  }

  .adviser-stat-card-trend {
    font-size: .7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    position: relative;
    z-index: 2;
    flex-shrink: 0;
    white-space: nowrap;
    color: var(--accent2);
    justify-content: flex-end;
    align-self: flex-end;
    text-align: right;
  }

  .adviser-stat-students::before,
  .adviser-stat-students::after {
    background: #1f6f6b !important;
  }

  .adviser-stat-placed::before,
  .adviser-stat-placed::after {
    background: #10B981 !important;
  }

  .adviser-stat-risk::before,
  .adviser-stat-risk::after {
    background: #F59E0B !important;
  }

  .adviser-stat-partners::before,
  .adviser-stat-partners::after {
    background: #06B6D4 !important;
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
    color: var(--ad-ink);
  }

  .adviser-dashboard-link {
    color: var(--ad-ink);
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
  }

  .adviser-dashboard-link:hover {
    color: var(--ad-verdigris-dark);
  }

  .adviser-dashboard-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #e4f0ee;
    color: var(--ad-verdigris-dark);
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
    color: var(--ad-ink);
    text-align: left;
    font-weight: 600;
  }

  .adviser-dashboard-table td {
    padding: 16px 14px;
    border-top: 1px solid var(--border);
    font-size: 0.84rem;
    vertical-align: middle;
    color: var(--ad-ink);
  }

  .adviser-dashboard-table tr:first-child td {
    border-top-color: var(--border);
  }

  .adviser-dashboard-table tr:hover td {
    background: #ffffff;
  }

  .adviser-student-name {
    font-weight: 600;
    color: var(--ad-ink);
  }

  .adviser-muted {
    font-size: 0.74rem;
    color: #222;
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
    background: #dcebe9;
    color: var(--ad-verdigris-dark);
  }

  .adviser-dashboard-pill.is-warning {
    background: #e4f0ee;
    color: var(--ad-verdigris-dark);
  }

  .adviser-dashboard-pill.is-success {
    background: #d2e8e6;
    color: var(--ad-verdigris-dark);
  }

  .adviser-dashboard-pill.is-neutral {
    background: #edf5f4;
    color: var(--ad-verdigris);
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
    background: var(--ad-verdigris-dark);
    color: #fff;
    box-shadow: none;
  }

  .adviser-dashboard-btn.secondary {
    background: #fff;
    color: var(--ad-ink);
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
    border-color: #9cc7c3;
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
    color: var(--ad-ink);
  }

  .adviser-dashboard-risk-meta {
    margin: 0;
    color: var(--ad-ink);
    font-size: 0.78rem;
    line-height: 1.45;
  }

  .adviser-dashboard-empty {
    padding: 18px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px dashed var(--border);
    color: var(--ad-ink);
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

    .adviser-dashboard-panel {
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
  <div class="analytics-title-header">
    <h2 class="analytics-page-title">Dashboard</h2>
    <p class="analytics-page-subtitle">Guide students through their journey, provide endorsements, monitor progress, and help them succeed in their internship placements.</p>
  </div>

  <div class="stat-cards">
    <div class="stat-card adviser-stat-students">
      <div class="stat-card-icon"><img src="/SkillHive/assets/media/Active%20Posting.png" alt="Assigned Students"></div>
      <div class="stat-card-info">
        <div class="stat-card-num-row">
          <div class="stat-card-trend neutral"><?php echo (int)($stats['my_students'] ?? 0); ?> students</div>
          <div class="stat-card-num"><?php echo (int)($stats['my_students'] ?? 0); ?></div>
        </div>
        <div class="stat-card-label">Assigned Students</div>
      </div>
    </div>

    <div class="stat-card adviser-stat-placed">
      <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Applicants.png" alt="Placed Students"></div>
      <div class="stat-card-info">
        <div class="stat-card-num-row">
          <div class="stat-card-trend neutral"><?php echo (int)($stats['placed_students'] ?? 0); ?> placed</div>
          <div class="stat-card-num"><?php echo (int)($stats['placed_students'] ?? 0); ?></div>
        </div>
        <div class="stat-card-label">Placed Students</div>
      </div>
    </div>

    <div class="stat-card adviser-stat-risk">
      <div class="stat-card-icon"><img src="/SkillHive/assets/media/Interviews.png" alt="At Risk"></div>
      <div class="stat-card-info">
        <div class="stat-card-num-row">
          <div class="stat-card-trend neutral"><?php echo (int)($stats['at_risk_students'] ?? 0); ?> at risk</div>
          <div class="stat-card-num"><?php echo (int)($stats['at_risk_students'] ?? 0); ?></div>
        </div>
        <div class="stat-card-label">At Risk</div>
      </div>
    </div>

    <div class="stat-card adviser-stat-partners">
      <div class="stat-card-icon"><img src="/SkillHive/assets/media/Hiredd.png" alt="Partner Companies"></div>
      <div class="stat-card-info">
        <div class="stat-card-num-row">
          <div class="stat-card-trend neutral"><?php echo (int)($stats['partner_companies'] ?? 0); ?> partners</div>
          <div class="stat-card-num"><?php echo (int)($stats['partner_companies'] ?? 0); ?></div>
        </div>
        <div class="stat-card-label">Partner Companies</div>
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
