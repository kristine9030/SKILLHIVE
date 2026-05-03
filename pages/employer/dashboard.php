<?php
/**
 * Purpose: Employer dashboard entry page that renders summary cards, postings, applicants, and upcoming interviews.
 * Tables/columns used: Indirectly uses employer(employer_id, company_name, verification_status, company_badge_status), internship(internship_id, employer_id, title, location, duration_weeks, status, posted_at, created_at), application(application_id, internship_id, student_id, status, compatibility_score, application_date, updated_at), interview(application_id, interview_date, interview_status), student(student_id, first_name, last_name).
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';

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

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null) ?? 0;
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
$isEmployerApproved = strtolower(trim($verificationRaw)) === 'approved';

$stats = $dashboardData['stats'];
$month = $dashboardData['month'];
$postings = $dashboardData['postings'];
$postingsPagination = $dashboardData['postings_pagination'];
$recentApplicants = $dashboardData['recent_applicants'];
$upcomingInterviews = $dashboardData['upcoming_interviews'];
?>

<div class="page-header" style="display: none;">
  <?php if ($isEmployerApproved): ?>
    <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Post Internship</a>
  <?php else: ?>
    <span class="btn btn-ghost btn-sm" style="opacity:.7;cursor:not-allowed;pointer-events:none;"><i class="fas fa-lock"></i> Approval Required</span>
  <?php endif; ?>
</div>

<?php
// Banner variables for employer
$bannerDate = date('l, jS F');
$bannerGreeting = 'Good afternoon';
$bannerUserName = $userName ?? 'Employer';
$bannerTitle = 'Build Your Team';
$bannerDescription = 'Review applications, schedule interviews, and find your perfect candidates. Everything you need to hire is right here.';
$bannerStats = [
  ['value' => (int)($stats['active_postings'] ?? 0), 'label' => 'Active Postings'],
  ['value' => (int)($stats['total_applicants'] ?? 0), 'label' => 'Total Applicants'],
  ['value' => (int)($stats['interviews'] ?? 0), 'label' => 'Interviews'],
];
$bannerShowMascot = false;
$bannerImage = $baseUrl . '/assets/media/Banner.png';
include __DIR__ . '/../../components/dashboard_banner.php';
?>

<?php if (!$isEmployerApproved): ?>
  <div class="panel-card" style="margin-bottom:14px;border-left:4px solid #12b3ac;">
    <div style="font-size:.85rem;color:#666;">
      <i class="fas fa-shield-halved" style="color:#12b3ac;margin-right:6px"></i>
      Your company is currently <strong><?php echo dashboard_escape($companyStatusLabel); ?></strong>. Posting, candidates, messaging, and evaluation modules unlock after admin approval.
    </div>
  </div>
<?php endif; ?>

<?php if ($dashboardLoadError !== null): ?>
  <div class="panel-card" style="margin-bottom:14px;border-left:4px solid #12b3ac;">
    <div style="font-size:.85rem;color:#666;">
      <i class="fas fa-triangle-exclamation" style="color:#12b3ac;margin-right:6px"></i>
      <?php echo htmlspecialchars($dashboardLoadError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  </div>
<?php endif; ?>

<div class="stat-cards">
  <div class="stat-card employer-stat-postings">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Active%20Posting.png" alt="Active Postings"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$stats['active_postings']; ?> active</div>
        <div class="stat-card-num"><?php echo (int)$stats['active_postings']; ?></div>
      </div>
      <div class="stat-card-label">Active Postings</div>
    </div>
  </div>
  <div class="stat-card employer-stat-applicants">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Applicants.png" alt="Total Applicants"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +<?php echo (int)$stats['week_applicants']; ?> this week</div>
        <div class="stat-card-num"><?php echo (int)$stats['total_applicants']; ?></div>
      </div>
      <div class="stat-card-label">Total Applicants</div>
    </div>
  </div>
  <div class="stat-card employer-stat-interviews">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Interviews.png" alt="Interviews"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$stats['interviews']; ?> scheduled</div>
        <div class="stat-card-num"><?php echo (int)$stats['interviews']; ?></div>
      </div>
      <div class="stat-card-label">Interviews</div>
    </div>
  </div>
  <div class="stat-card employer-stat-hired">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Hiredd.png" alt="Hired"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$stats['hired']; ?> hired</div>
        <div class="stat-card-num"><?php echo (int)$stats['hired']; ?></div>
      </div>
      <div class="stat-card-label">Hired</div>
    </div>
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
          <?php
            $postingStatus = $posting['status'] ?? 'pending';
            $statusClass = dashboard_status_class($postingStatus);
            $statusLabel = dashboard_status_label($postingStatus);
            $applicantsCount = (int)($posting['applicants_count'] ?? 0);
            $location = dashboard_escape($posting['location'] ?? 'N/A');
            $duration = dashboard_duration_label((int)($posting['duration_weeks'] ?? 0));
            $postedTime = dashboard_time_ago($posting['posted_at'] ?? null);
          ?>
          <div class="posting-card">
            <div class="posting-card-top">
              <div class="posting-card-title-row">
                <span class="posting-card-title"><?php echo dashboard_escape($posting['title'] ?? 'Untitled Internship'); ?></span>
                <span class="posting-card-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
              </div>
              <div class="posting-card-meta">
                <span class="posting-meta"><i class="fas fa-user-group"></i><?php echo $applicantsCount; ?> applicants</span>
                <span class="posting-meta"><i class="fas fa-location-dot"></i><?php echo $location; ?></span>
                <span class="posting-meta"><i class="fas fa-clock"></i><?php echo $duration; ?></span>
              </div>
            </div>
            <div class="posting-card-bottom">
              <span class="posting-card-time"><?php echo $postedTime; ?></span>
              <div class="posting-card-actions">
                <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates&position=<?php echo (int)($posting['internship_id'] ?? 0); ?>" class="btn btn-outline btn-sm">View Applicants</a>
                <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship&focus_posting=<?php echo (int)($posting['internship_id'] ?? 0); ?>#my-postings" class="btn btn-outline btn-sm"><i class="fas fa-pen"></i> Edit</a>
              </div>
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
        <div class="posting-card posting-card-empty">
          <div class="posting-card-top">
            <div class="posting-card-title-row">
              <span class="posting-card-title">No postings yet</span>
              <span class="posting-card-badge badge-pending">Pending</span>
            </div>
            <div class="posting-card-meta">
              <span class="posting-meta">Create a new internship to show it on your dashboard.</span>
            </div>
          </div>
          <div class="posting-card-bottom">
            <span class="posting-card-time"></span>
            <div class="posting-card-actions">
              <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-primary btn-sm">Post Internship</a>
            </div>
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
                  <td><a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates&position=<?php echo (int)($applicant['internship_id'] ?? 0); ?>" class="btn btn-ghost btn-sm">Review</a></td>
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
    <div class="panel-card">
      <div class="panel-card-header"><h3>This Month</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Applications Received</span><span style="font-weight:700"><?php echo (int)$month['applications_received']; ?></span></div>
        <div class="mini-row"><span>Interviews Conducted</span><span style="font-weight:700"><?php echo (int)$month['interviews_conducted']; ?></span></div>
        <div class="mini-row"><span>Offers Extended</span><span style="font-weight:700"><?php echo (int)$month['offers_extended']; ?></span></div>
        <div class="mini-row"><span>Acceptance Rate</span><span style="font-weight:700;color:#12b3ac"><?php echo (int)$month['acceptance_rate']; ?>%</span></div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Upcoming</h3></div>
      <div class="timeline">
        <?php if (!empty($upcomingInterviews)): ?>
          <?php foreach ($upcomingInterviews as $interview): ?>
            <?php
            $dotColor = '#12b3ac';
            $interviewStatusClass = dashboard_status_class($interview['interview_status'] ?? 'pending');
            if ($interviewStatusClass === 'status-interview') {
              $dotColor = '#12b3ac';
            } elseif ($interviewStatusClass === 'status-pending') {
              $dotColor = '#12b3ac';
            } elseif ($interviewStatusClass === 'status-accepted') {
              $dotColor = '#12b3ac';
            } elseif ($interviewStatusClass === 'status-rejected') {
              $dotColor = '#12b3ac';
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
            <div class="timeline-dot" style="background:#12b3ac"></div>
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
