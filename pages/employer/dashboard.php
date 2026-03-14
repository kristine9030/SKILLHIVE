<?php
/**
 * Purpose: Employer dashboard entry page that renders summary cards, postings, applicants, and upcoming interviews.
 * Tables/columns used: Indirectly uses employer(employer_id, company_name, verification_status, company_badge_status), internship(internship_id, employer_id, title, location, duration_weeks, status, posted_at, created_at), application(application_id, internship_id, student_id, status, compatibility_score, application_date, updated_at), interview(application_id, interview_date, interview_status), student(student_id, first_name, last_name).
 */
require_once __DIR__ . '/../../backend/db_connect.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';
$userName = isset($userName) ? (string)$userName : 'Employer';
$initials = isset($initials) ? (string)$initials : 'EM';

$dashboardDir = __DIR__ . '/dashboard';
if (!is_dir($dashboardDir) && is_dir(__DIR__ . '/dashbpard')) {
  $dashboardDir = __DIR__ . '/dashbpard';
}

$dashboardBootstrapFile = $dashboardDir . '/dashboard_data.php';
$dashboardLoadError = null;

if (file_exists($dashboardBootstrapFile)) {
  include_once $dashboardBootstrapFile;
} else {
  $dashboardLoadError = 'Dashboard data source not found.';
}

$employerId = (int)($_SESSION['employer_id'] ?? ($userId ?? 0));
$postingsPage = max(1, (int)($_GET['postings_page'] ?? 1));

$dashboardData = [
  'company' => [
    'company_name' => $userName,
    'verification_status' => 'pending',
    'company_badge_status' => null,
  ],
  'stats' => [
    'active_postings' => 0,
    'total_applicants' => 0,
    'week_applicants' => 0,
    'interviews' => 0,
    'hired' => 0,
  ],
  'month' => [
    'applications_received' => 0,
    'interviews_conducted' => 0,
    'offers_extended' => 0,
    'acceptance_rate' => 0,
  ],
  'postings' => [],
  'postings_pagination' => [
    'current_page' => 1,
    'per_page' => 5,
    'total_items' => 0,
    'total_pages' => 1,
  ],
  'recent_applicants' => [],
  'upcoming_interviews' => [],
];

if ($employerId > 0 && function_exists('getEmployerDashboardData')) {
  try {
    $dashboardData = getEmployerDashboardData($pdo, $employerId, $postingsPage, 5);
  } catch (Throwable $e) {
    $dashboardLoadError = 'Unable to load dashboard metrics right now.';
  }
}

$companyName = $dashboardData['company']['company_name'] ?: $userName;

$companyInitials = '';
foreach (preg_split('/\s+/', trim((string)$companyName)) as $part) {
  if ($part !== '') {
    $companyInitials .= strtoupper(substr($part, 0, 1));
  }
}
$companyInitials = $companyInitials !== '' ? substr($companyInitials, 0, 2) : $initials;

$verificationRaw = (string)($dashboardData['company']['verification_status'] ?? 'pending');
$companyStatusClass = dashboard_status_class($verificationRaw);
$companyStatusLabel = dashboard_status_label($verificationRaw);

$stats = $dashboardData['stats'];
$month = $dashboardData['month'];
$postings = $dashboardData['postings'];
$postingsPagination = $dashboardData['postings_pagination'];
$recentApplicants = $dashboardData['recent_applicants'];
$upcomingInterviews = $dashboardData['upcoming_interviews'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Employer Dashboard</h2>
    <p class="page-subtitle">Manage your internship postings and track candidates.</p>
  </div>
  <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Post Internship</a>
</div>

<?php if ($dashboardLoadError !== null): ?>
  <div class="panel-card" style="margin-bottom:14px;border-left:4px solid #F59E0B;">
    <div style="font-size:.85rem;color:#666;">
      <i class="fas fa-triangle-exclamation" style="color:#F59E0B;margin-right:6px"></i>
      <?php echo htmlspecialchars($dashboardLoadError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  </div>
<?php endif; ?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-briefcase" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['active_postings']; ?></div><div class="stat-card-label">Active Postings</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-users" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['total_applicants']; ?></div><div class="stat-card-label">Total Applicants</div></div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +<?php echo (int)$stats['week_applicants']; ?> this week</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-calendar-check" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['interviews']; ?></div><div class="stat-card-label">Interviews</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-double" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int)$stats['hired']; ?></div><div class="stat-card-label">Hired</div></div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Active Postings</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship&postings_page=1" class="btn btn-ghost btn-sm">View All</a>
      </div>

      <?php if (!empty($postings)): ?>
        <?php foreach ($postings as $posting): ?>
          <div class="job-card">
            <div class="job-card-header">
              <div class="job-card-info">
                <div class="job-card-title"><?php echo dashboard_escape($posting['title'] ?? 'Untitled Internship'); ?></div>
                <div class="job-card-company"><?php echo dashboard_escape(dashboard_time_ago($posting['posted_at'] ?? null)); ?></div>
              </div>
              <span class="status-pill <?php echo dashboard_status_class($posting['status'] ?? 'pending'); ?>"><?php echo dashboard_escape(dashboard_status_label($posting['status'] ?? 'pending')); ?></span>
            </div>
            <div class="job-card-meta">
              <span><i class="fas fa-users"></i> <?php echo (int)($posting['applicants_count'] ?? 0); ?> applicants</span>
              <span><i class="fas fa-map-marker-alt"></i> <?php echo dashboard_escape($posting['location'] ?? 'N/A'); ?></span>
              <span><i class="fas fa-clock"></i> <?php echo dashboard_escape(dashboard_duration_label((int)($posting['duration_weeks'] ?? 0))); ?></span>
            </div>
            <div class="job-card-actions">
              <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates" class="btn btn-ghost btn-sm">View Applicants</a>
              <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</a>
            </div>
          </div>
        <?php endforeach; ?>

        <?php
        $currentPostingsPage = (int)($postingsPagination['current_page'] ?? 1);
        $totalPostingsPages = (int)($postingsPagination['total_pages'] ?? 1);
        if ($totalPostingsPages > 1):
          $startPage = max(1, $currentPostingsPage - 2);
          $endPage = min($totalPostingsPages, $currentPostingsPage + 2);
          if (($endPage - $startPage) < 4) {
            if ($startPage === 1) {
              $endPage = min($totalPostingsPages, $startPage + 4);
            } elseif ($endPage === $totalPostingsPages) {
              $startPage = max(1, $endPage - 4);
            }
          }
        ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:14px;">
            <?php if ($startPage > 1): ?>
              <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/dashboard&postings_page=1">1</a>
              <?php if ($startPage > 2): ?>
                <span style="padding:6px 4px;color:#999;">...</span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($pageNum = $startPage; $pageNum <= $endPage; $pageNum++): ?>
              <?php if ($pageNum === $currentPostingsPage): ?>
                <span class="btn btn-primary btn-sm" style="pointer-events:none;"><?php echo $pageNum; ?></span>
              <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/dashboard&postings_page=<?php echo $pageNum; ?>"><?php echo $pageNum; ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPostingsPages): ?>
              <?php if ($endPage < ($totalPostingsPages - 1)): ?>
                <span style="padding:6px 4px;color:#999;">...</span>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/dashboard&postings_page=<?php echo $totalPostingsPages; ?>"><?php echo $totalPostingsPages; ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="job-card">
          <div class="job-card-header">
            <div class="job-card-info">
              <div class="job-card-title">No postings yet</div>
              <div class="job-card-company">Create a new internship to show it on your dashboard.</div>
            </div>
            <span class="status-pill status-pending">Pending</span>
          </div>
          <div class="job-card-meta">
            <span><i class="fas fa-users"></i> 0 applicants</span>
            <span><i class="fas fa-map-marker-alt"></i> N/A</span>
            <span><i class="fas fa-clock"></i> N/A</span>
          </div>
          <div class="job-card-actions">
            <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-ghost btn-sm">Post Internship</a>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Recent Applicants</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Candidate</th><th>Position</th><th>Match</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (!empty($recentApplicants)): ?>
              <?php foreach ($recentApplicants as $applicant): ?>
                <?php
                $candidateName = trim((string)($applicant['first_name'] ?? '') . ' ' . (string)($applicant['last_name'] ?? ''));
                $candidateInitials = dashboard_initials($applicant['first_name'] ?? '', $applicant['last_name'] ?? '');
                $matchScore = $applicant['compatibility_score'];
                $matchText = is_numeric($matchScore) ? ((int)round((float)$matchScore) . '%') : 'N/A';
                ?>
                <tr>
                  <td><div style="display:flex;align-items:center;gap:10px"><div class="topbar-avatar" style="width:32px;height:32px;font-size:.7rem"><?php echo dashboard_escape($candidateInitials); ?></div><?php echo dashboard_escape($candidateName); ?></div></td>
                  <td><?php echo dashboard_escape($applicant['internship_title'] ?? 'N/A'); ?></td>
                  <td><span class="match-badge"><?php echo dashboard_escape($matchText); ?></span></td>
                  <td><span class="status-pill <?php echo dashboard_status_class($applicant['status'] ?? 'pending'); ?>"><?php echo dashboard_escape(dashboard_status_label($applicant['status'] ?? 'pending')); ?></span></td>
                  <td><a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates" class="btn btn-ghost btn-sm">Review</a></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align:center;color:#999;">No applicants yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card" style="text-align:center">
      <div class="profile-avatar-lg" style="margin:0 auto 12px"><?php echo dashboard_escape($companyInitials); ?></div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:4px"><?php echo dashboard_escape($companyName); ?></div>
      <div style="font-size:.78rem;color:#999;margin-bottom:10px">Employer Account</div>
      <span class="status-pill <?php echo $companyStatusClass; ?>"><?php echo dashboard_escape($companyStatusLabel); ?></span>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>This Month</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Applications Received</span><span style="font-weight:700"><?php echo (int)$month['applications_received']; ?></span></div>
        <div class="mini-row"><span>Interviews Conducted</span><span style="font-weight:700"><?php echo (int)$month['interviews_conducted']; ?></span></div>
        <div class="mini-row"><span>Offers Extended</span><span style="font-weight:700"><?php echo (int)$month['offers_extended']; ?></span></div>
        <div class="mini-row"><span>Acceptance Rate</span><span style="font-weight:700;color:#10B981"><?php echo (int)$month['acceptance_rate']; ?>%</span></div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Upcoming</h3></div>
      <div class="timeline">
        <?php if (!empty($upcomingInterviews)): ?>
          <?php foreach ($upcomingInterviews as $interview): ?>
            <?php
            $dotColor = '#06B6D4';
            $interviewStatusClass = dashboard_status_class($interview['interview_status'] ?? 'pending');
            if ($interviewStatusClass === 'status-interview') {
              $dotColor = '#4F46E5';
            } elseif ($interviewStatusClass === 'status-pending') {
              $dotColor = '#F59E0B';
            } elseif ($interviewStatusClass === 'status-accepted') {
              $dotColor = '#10B981';
            } elseif ($interviewStatusClass === 'status-rejected') {
              $dotColor = '#EF4444';
            }
            $first = (string)($interview['first_name'] ?? 'Candidate');
            $last = (string)($interview['last_name'] ?? '');
            $shortName = trim($first . ' ' . ($last !== '' ? strtoupper(substr($last, 0, 1)) . '.' : ''));
            ?>
            <div class="timeline-item">
              <div class="timeline-dot" style="background:<?php echo $dotColor; ?>"></div>
              <div class="timeline-content">
                <div style="font-weight:600;font-size:.85rem">Interview — <?php echo dashboard_escape($shortName); ?></div>
                <div style="font-size:.75rem;color:#999"><?php echo dashboard_escape(dashboard_format_interview_datetime($interview['interview_date'] ?? null)); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="timeline-item">
            <div class="timeline-dot" style="background:#F59E0B"></div>
            <div class="timeline-content">
              <div style="font-weight:600;font-size:.85rem">No upcoming interviews</div>
              <div style="font-size:.75rem;color:#999">Schedule interviews from the candidates page.</div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
