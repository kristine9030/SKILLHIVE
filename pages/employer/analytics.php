<?php
/**
 * Purpose: Employer analytics dashboard
 * Shows graphs for inquiries, applications, and recruitment metrics
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null) ?? 0;

if ($employerId <= 0) {
  header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
  exit;
}

// Get date range from filters (default: last 30 days)
$endDate   = isset($_GET['end_date'])   && $_GET['end_date']   !== '' ? $_GET['end_date']   : date('Y-m-d');
$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$filterPosting = isset($_GET['posting_id']) && (int)$_GET['posting_id'] > 0 ? (int)$_GET['posting_id'] : null;
$filterStatus  = isset($_GET['status'])     && $_GET['status']     !== '' ? $_GET['status']     : null;

// Clamp dates to sane order
if ($startDate > $endDate) { [$startDate, $endDate] = [$endDate, $startDate]; }

// Fetch posting list for the filter dropdown
$postingsListStmt = $pdo->prepare("SELECT internship_id, title FROM internship WHERE employer_id = :eid ORDER BY title ASC");
$postingsListStmt->execute([':eid' => $employerId]);
$postingsList = $postingsListStmt->fetchAll(PDO::FETCH_ASSOC);

// Build shared WHERE extras for posting / status filters
$postingWhere = $filterPosting ? ' AND a.internship_id = :posting_id' : '';
$statusWhere  = $filterStatus  ? ' AND a.status = :status'            : '';

function bindFilters(PDOStatement $stmt, int $employerId, string $startDate, string $endDate, ?int $filterPosting, ?string $filterStatus): void {
  $stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
  $stmt->bindValue(':start_date',  $startDate);
  $stmt->bindValue(':end_date',    $endDate);
  if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
  if ($filterStatus)  $stmt->bindValue(':status',     $filterStatus);
}

// Fetch applications timeline data
$applicationsQuery = "
  SELECT DATE(a.application_date) as date, COUNT(*) as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
    AND DATE(a.application_date) BETWEEN :start_date AND :end_date
    $postingWhere
    $statusWhere
  GROUP BY DATE(a.application_date)
  ORDER BY date ASC
";
$stmt = $pdo->prepare($applicationsQuery);
bindFilters($stmt, $employerId, $startDate, $endDate, $filterPosting, $filterStatus);
$stmt->execute();
$applicationsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inquiries (simulated as 2× applications)
$inquiriesQuery = "
  SELECT DATE(a.application_date) as date, COUNT(*) * 2 as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
    AND DATE(a.application_date) BETWEEN :start_date AND :end_date
    $postingWhere
    $statusWhere
  GROUP BY DATE(a.application_date)
  ORDER BY date ASC
";
$stmt = $pdo->prepare($inquiriesQuery);
bindFilters($stmt, $employerId, $startDate, $endDate, $filterPosting, $filterStatus);
$stmt->execute();
$inquiriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch application status breakdown (respects date + posting, but NOT status filter — otherwise the pie shows only one slice)
$statusQuery = "
  SELECT a.status, COUNT(*) as count
  FROM application a
  INNER JOIN internship i ON a.internship_id = i.internship_id
  WHERE i.employer_id = :employer_id
    AND DATE(a.application_date) BETWEEN :start_date AND :end_date
    $postingWhere
  GROUP BY a.status
";
$stmt = $pdo->prepare($statusQuery);
$stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
$stmt->bindValue(':start_date',  $startDate);
$stmt->bindValue(':end_date',    $endDate);
if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
$stmt->execute();
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch top performing internships (respects date + status, limited to selected posting if set)
$topQuery = "
  SELECT i.title, COUNT(a.application_id) as applications
  FROM internship i
  LEFT JOIN application a ON i.internship_id = a.internship_id
    AND DATE(a.application_date) BETWEEN :start_date AND :end_date
    $statusWhere
  WHERE i.employer_id = :employer_id
    " . ($filterPosting ? 'AND i.internship_id = :posting_id' : '') . "
  GROUP BY i.internship_id, i.title
  ORDER BY applications DESC
  LIMIT 5
";
$stmt = $pdo->prepare($topQuery);
$stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
$stmt->bindValue(':start_date',  $startDate);
$stmt->bindValue(':end_date',    $endDate);
if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
if ($filterStatus)  $stmt->bindValue(':status',     $filterStatus);
$stmt->execute();
$topPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Student ranking by skill match against each posting's required skills.
// This is intentionally all-time for the employer because skill fit is a candidate/profile metric,
// while posting/status filters still narrow the ranking when selected.
$rankingPostingWhere = $filterPosting ? ' AND a.internship_id = :posting_id' : '';
$rankingStatusWhere = $filterStatus ? ' AND a.status = :status' : '';
$rankingQuery = "
  SELECT
    a.application_id,
    a.status,
    a.application_date,
    a.compatibility_score,
    s.student_id,
    s.student_number,
    s.first_name,
    s.last_name,
    s.program,
    s.year_level,
    i.internship_id,
    i.title AS internship_title,
    COALESCE(req.required_count, 0) AS required_count,
    COALESCE(req.mandatory_count, 0) AS mandatory_count,
    COALESCE(mat.matched_count, 0) AS matched_count,
    COALESCE(mat.mandatory_matched_count, 0) AS mandatory_matched_count,
    COALESCE(mat.matched_skills, '') AS matched_skills,
    COALESCE(mis.missing_skills, '') AS missing_skills
  FROM application a
  INNER JOIN internship i ON i.internship_id = a.internship_id
  INNER JOIN student s ON s.student_id = a.student_id
  LEFT JOIN (
    SELECT
      internship_id,
      COUNT(DISTINCT skill_id) AS required_count,
      COUNT(DISTINCT CASE WHEN is_mandatory = 1 THEN skill_id END) AS mandatory_count
    FROM internship_skill
    GROUP BY internship_id
  ) req ON req.internship_id = i.internship_id
  LEFT JOIN (
    SELECT
      a2.application_id,
      COUNT(DISTINCT ins.skill_id) AS matched_count,
      COUNT(DISTINCT CASE WHEN ins.is_mandatory = 1 THEN ins.skill_id END) AS mandatory_matched_count,
      GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.skill_name SEPARATOR ', ') AS matched_skills
    FROM application a2
    INNER JOIN internship_skill ins ON ins.internship_id = a2.internship_id
    INNER JOIN student_skill ss ON ss.student_id = a2.student_id
      AND ss.skill_id = ins.skill_id
    INNER JOIN skill sk ON sk.skill_id = ins.skill_id
    GROUP BY a2.application_id
  ) mat ON mat.application_id = a.application_id
  LEFT JOIN (
    SELECT
      a3.application_id,
      GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.skill_name SEPARATOR ', ') AS missing_skills
    FROM application a3
    INNER JOIN internship_skill ins ON ins.internship_id = a3.internship_id
    INNER JOIN skill sk ON sk.skill_id = ins.skill_id
    LEFT JOIN student_skill ss ON ss.student_id = a3.student_id
      AND ss.skill_id = ins.skill_id
    WHERE ss.skill_id IS NULL
    GROUP BY a3.application_id
  ) mis ON mis.application_id = a.application_id
  WHERE i.employer_id = :employer_id
    $rankingPostingWhere
    $rankingStatusWhere
";
$stmt = $pdo->prepare($rankingQuery);
$stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
if ($filterStatus) $stmt->bindValue(':status', $filterStatus);
$stmt->execute();
$studentRankings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($studentRankings as &$ranking) {
  $requiredCount = (int)($ranking['required_count'] ?? 0);
  $matchedCount = (int)($ranking['matched_count'] ?? 0);
  $mandatoryCount = (int)($ranking['mandatory_count'] ?? 0);
  $mandatoryMatched = (int)($ranking['mandatory_matched_count'] ?? 0);
  $fallbackCompatibility = is_numeric($ranking['compatibility_score'] ?? null) ? (float)$ranking['compatibility_score'] : 0.0;

  $ranking['skill_match_score'] = $requiredCount > 0
    ? (int)round(($matchedCount / max(1, $requiredCount)) * 100)
    : (int)round($fallbackCompatibility);
  $ranking['mandatory_match_score'] = $mandatoryCount > 0
    ? (int)round(($mandatoryMatched / max(1, $mandatoryCount)) * 100)
    : null;
}
unset($ranking);

usort($studentRankings, static function (array $a, array $b): int {
  $scoreCompare = ((int)($b['skill_match_score'] ?? 0)) <=> ((int)($a['skill_match_score'] ?? 0));
  if ($scoreCompare !== 0) {
    return $scoreCompare;
  }

  $mandatoryCompare = ((int)($b['mandatory_matched_count'] ?? 0)) <=> ((int)($a['mandatory_matched_count'] ?? 0));
  if ($mandatoryCompare !== 0) {
    return $mandatoryCompare;
  }

  return strcmp((string)($b['application_date'] ?? ''), (string)($a['application_date'] ?? ''));
});

$studentRankings = array_slice($studentRankings, 0, 8);

// Calculate total stats
$totalApplications = array_sum(array_column($applicationsData, 'count'));
$totalInquiries = array_sum(array_column($inquiriesData, 'count'));

// Fetch KPI stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM internship WHERE employer_id = :employer_id AND status = 'Active'" . ($filterPosting ? ' AND internship_id = :posting_id' : ''));
$stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
$stmt->execute();
$activePostings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM application a INNER JOIN internship i ON a.internship_id = i.internship_id WHERE i.employer_id = :employer_id AND a.status = 'Shortlisted' AND DATE(a.application_date) BETWEEN :start_date AND :end_date" . ($filterPosting ? ' AND a.internship_id = :posting_id' : ''));
$stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
$stmt->bindValue(':start_date',  $startDate);
$stmt->bindValue(':end_date',    $endDate);
if ($filterPosting) $stmt->bindValue(':posting_id', $filterPosting, PDO::PARAM_INT);
$stmt->execute();
$shortlisted = (int)$stmt->fetchColumn();

// Prepare data for charts
$chartDates = [];
$chartApplications = [];
$chartInquiries = [];

$allDates = [];
foreach ($applicationsData as $row) {
  $allDates[$row['date']] = true;
}
foreach ($inquiriesData as $row) {
  $allDates[$row['date']] = true;
}

ksort($allDates);

$appMap = array_column($applicationsData, 'count', 'date');
$inqMap = array_column($inquiriesData, 'count', 'date');

foreach ($allDates as $date => $v) {
  $chartDates[] = date('M d', strtotime($date));
  $chartApplications[] = $appMap[$date] ?? 0;
  $chartInquiries[] = $inqMap[$date] ?? 0;
}

// Status breakdown
$statusLabels = [];
$statusCounts = [];
foreach ($statusData as $row) {
  $statusLabels[] = $row['status'];
  $statusCounts[] = (int)$row['count'];
}

// Top postings
$topLabels = [];
$topCounts = [];
foreach ($topPostings as $row) {
  $topLabels[] = substr($row['title'], 0, 20);
  $topCounts[] = (int)$row['applications'];
}

if (empty($topLabels)) {
  $topLabels = ['No Data'];
  $topCounts = [0];
}
?>

<div class="analytics-banner" id="analyticsBanner">
  <!-- decorative art -->
  <div class="banner-art banner-art-left"></div>
  <div class="banner-art banner-art-right"></div>

  <!-- content -->
  <div class="banner-body">
    <div class="banner-meta"><?php echo date('l, j F Y'); ?></div>
    <div class="banner-heading">
      <?php
        $hour = (int)date('H');
        if ($hour < 12)      echo 'Good morning!';
        elseif ($hour < 18)  echo 'Good afternoon!';
        else                 echo 'Good evening!';
      ?>
    </div>
    <div class="banner-sub">Track your recruitment performance, application trends, and posting results.</div>

    <div class="banner-pills">
      <span class="banner-pill"><i class="fas fa-chart-line"></i> <?php echo $totalApplications; ?> Applications</span>
      <span class="banner-pill"><i class="fas fa-briefcase"></i> <?php echo $activePostings; ?> Active Postings</span>
      <span class="banner-pill"><i class="fas fa-user-check"></i> <?php echo $shortlisted; ?> Shortlisted</span>
    </div>
  </div>

  <button type="button" class="banner-collapse-btn" onclick="toggleAnalyticsBanner()" title="Collapse banner">
    <i class="fas fa-chevron-up"></i>
  </button>
</div>

<div class="banner-collapsed-bar" id="bannerCollapsedBar" onclick="toggleAnalyticsBanner()">
  <span><i class="fas fa-chart-bar" style="margin-right:6px;color:#0f766e;"></i>Analytics Overview</span>
  <span class="banner-collapsed-meta"><?php echo date('l, j F'); ?></span>
  <i class="fas fa-chevron-down" style="color:#9ca3af;font-size:12px;"></i>
</div>

<div class="stat-cards">
  <div class="stat-card employer-stat-postings">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Applicants.png" alt="Total Applications"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">last 30 days</div>
        <div class="stat-card-num"><?php echo $totalApplications; ?></div>
      </div>
      <div class="stat-card-label">Total Applications</div>
    </div>
  </div>
  <div class="stat-card employer-stat-applicants">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Inquiries.png" alt="Total Inquiries"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">estimated</div>
        <div class="stat-card-num"><?php echo $totalInquiries; ?></div>
      </div>
      <div class="stat-card-label">Total Inquiries</div>
    </div>
  </div>
  <div class="stat-card employer-stat-interviews">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Active%20Posting.png" alt="Active Postings"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">postings</div>
        <div class="stat-card-num"><?php echo $activePostings; ?></div>
      </div>
      <div class="stat-card-label">Active Postings</div>
    </div>
  </div>
  <div class="stat-card employer-stat-hired">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Shortlisted.png" alt="Shortlisted"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">candidates</div>
        <div class="stat-card-num"><?php echo $shortlisted; ?></div>
      </div>
      <div class="stat-card-label">Shortlisted</div>
    </div>
  </div>
</div>

<style>
/* ── Analytics Banner ─────────────────────────────────── */
.analytics-banner {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 32px 36px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  background: linear-gradient(120deg, #0d5f58 0%, #0f766e 45%, #134e4a 100%);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.22), 0 2px 6px rgba(0,0,0,0.08);
  border: 1px solid rgba(255,255,255,0.08);
  transition: all 0.35s cubic-bezier(.4,0,.2,1);
}

/* Decorative blobs */
.banner-art {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  opacity: 0.12;
}

.banner-art-left {
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, #fff 0%, transparent 70%);
  top: -80px;
  left: -60px;
}

.banner-art-right {
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, #5eead4 0%, transparent 70%);
  bottom: -90px;
  right: 60px;
  opacity: 0.18;
}

.banner-body {
  position: relative;
  z-index: 1;
  flex: 1;
}

.banner-meta {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.55);
  margin-bottom: 6px;
}

.banner-heading {
  font-size: 26px;
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 6px;
  line-height: 1.2;
}

.banner-sub {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  line-height: 1.5;
  max-width: 480px;
  margin-bottom: 18px;
}

.banner-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.banner-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
  backdrop-filter: blur(4px);
}

.banner-collapse-btn {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.2);
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.75);
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}

.banner-collapse-btn:hover {
  background: rgba(255,255,255,0.22);
  color: #fff;
}

/* Collapsed bar (shown when banner is hidden) */
.banner-collapsed-bar {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  margin: 0 0 16px 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: background 0.15s;
  user-select: none;
}

.banner-collapsed-bar:hover {
  background: #f9fafb;
}

.banner-collapsed-meta {
  font-size: 12px;
  font-weight: 400;
  color: #9ca3af;
  margin-left: auto;
}

/* Collapsed state */
.analytics-banner.collapsed {
  display: none;
}

.banner-collapsed-bar.visible {
  display: flex;
}

@media (max-width: 768px) {
  .analytics-banner { padding: 24px 20px 20px; }
  .banner-heading { font-size: 20px; }
  .banner-sub { display: none; }
  .banner-pills { gap: 6px; }
  .banner-pill { font-size: 11px; padding: 4px 10px; }
}

.analytics-container {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.analytics-sidebar {
  flex: 0 0 300px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  height: fit-content;
}

.analytics-sidebar h3 {
  margin: 0 0 16px 0;
  font-size: 14px;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.analytics-main-content {
  flex: 1;
}

.top-picks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.top-pick-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  text-align: center;
}

.top-pick-card .posting-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.top-pick-card .posting-count {
  font-size: 24px;
  font-weight: 700;
  color: #0f766e;
}

.top-pick-card .posting-label {
  font-size: 12px;
  color: #9ca3af;
}

.skill-ranking-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  margin-bottom: 20px;
}

.skill-ranking-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 14px;
}

.skill-ranking-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}

.skill-ranking-sub {
  margin: 5px 0 0;
  color: #6b7280;
  font-size: 12px;
  line-height: 1.45;
}

.skill-ranking-table-wrap {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.skill-ranking-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}

.skill-ranking-table th,
.skill-ranking-table td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  vertical-align: top;
  font-size: 12px;
}

.skill-ranking-table th {
  background: #f9fafb;
  color: #6b7280;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 700;
}

.skill-ranking-table tbody tr:last-child td {
  border-bottom: 0;
}

.skill-rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 9px;
  background: #0f766e;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
}

.skill-candidate-name {
  display: block;
  color: #111827;
  font-size: 13px;
  font-weight: 800;
}

.skill-candidate-meta {
  display: block;
  margin-top: 3px;
  color: #6b7280;
  font-size: 11px;
  line-height: 1.35;
}

.skill-score-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 12px;
  font-weight: 800;
}

.skill-score-pill.warn {
  background: #fef3c7;
  color: #a16207;
}

.skill-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.skill-chip {
  display: inline-flex;
  align-items: center;
  max-width: 160px;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f0fdfa;
  border: 1px solid #99f6e4;
  color: #0f766e;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.skill-chip.missing {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #64748b;
}

.chart-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  margin-bottom: 16px;
}

.chart-card h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.chart-wrapper {
  position: relative;
  height: 300px;
  margin-bottom: 16px;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.no-data {
  text-align: center;
  padding: 40px;
  color: #6b7280;
}

.no-data i {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.3;
  display: block;
}

@media (max-width: 1024px) {
  .analytics-container {
    flex-direction: column;
  }
  .analytics-sidebar {
    flex: 0 0 auto;
  }
}

/* Filter form styles */
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-sub-label {
  font-size: 11px;
  color: #9ca3af;
  display: block;
  margin-bottom: 2px;
}

.filter-input {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #111827;
  background: #fff;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.15s;
}

.filter-input:focus {
  border-color: #0f766e;
  box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.1);
}

.range-btn {
  flex: 1 1 auto;
  padding: 5px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
  background: #f9fafb;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.range-btn:hover {
  border-color: #0f766e;
  color: #0f766e;
}

.range-btn.active {
  background: #0f766e;
  border-color: #0f766e;
  color: #fff;
}

.filter-btn-apply {
  padding: 9px 14px;
  background: #0f766e;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.filter-btn-apply:hover {
  background: #0d5f58;
}

.filter-btn-reset {
  padding: 8px 14px;
  background: transparent;
  color: #6b7280;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
  display: block;
  transition: all 0.15s;
}

.filter-btn-reset:hover {
  border-color: #9ca3af;
  color: #374151;
}

.filter-active-badge {
  padding: 8px 10px;
  background: #f0fdfa;
  border: 1px solid #99f6e4;
  border-radius: 8px;
  font-size: 12px;
  color: #0f766e;
  font-weight: 500;
}
</style>

<div class="analytics-container">
  <!-- Left Sidebar with Filters -->
  <div class="analytics-sidebar">
    <h3><i class="fas fa-filter" style="margin-right:8px;"></i>Filters</h3>
    <form method="GET" id="analyticsFilterForm" style="display:flex;flex-direction:column;gap:14px;">

      <!-- Date Range -->
      <div class="filter-group">
        <label class="filter-label">Date Range</label>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php
            $ranges = [
              '7'   => 'Last 7 days',
              '30'  => 'Last 30 days',
              '90'  => 'Last 90 days',
              '365' => 'This year',
            ];
            $currentRange = isset($_GET['range']) ? $_GET['range'] : '30';
          ?>
          <?php foreach ($ranges as $val => $label): ?>
            <button type="button"
              class="range-btn <?php echo $currentRange == $val ? 'active' : ''; ?>"
              onclick="setRange(<?php echo $val; ?>)">
              <?php echo $label; ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
          <input type="hidden" name="range" id="rangeInput" value="<?php echo htmlspecialchars($currentRange); ?>">
          <div>
            <label class="filter-sub-label">From</label>
            <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($startDate); ?>">
          </div>
          <div>
            <label class="filter-sub-label">To</label>
            <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($endDate); ?>">
          </div>
        </div>
      </div>

      <!-- Posting Filter -->
      <div class="filter-group">
        <label class="filter-label">Internship Posting</label>
        <select name="posting_id" class="filter-input">
          <option value="">All Postings</option>
          <?php foreach ($postingsList as $p): ?>
            <option value="<?php echo (int)$p['internship_id']; ?>"
              <?php echo $filterPosting == (int)$p['internship_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($p['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status Filter -->
      <div class="filter-group">
        <label class="filter-label">Application Status</label>
        <select name="status" class="filter-input">
          <option value="">All Statuses</option>
          <?php
            $statuses = ['Pending', 'Shortlisted', 'Interview', 'Accepted', 'Rejected', 'Withdrawn'];
            foreach ($statuses as $s):
          ?>
            <option value="<?php echo $s; ?>" <?php echo $filterStatus === $s ? 'selected' : ''; ?>>
              <?php echo $s; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:8px;">
        <button type="submit" class="filter-btn-apply">
          <i class="fas fa-check" style="margin-right:6px;"></i>Apply Filters
        </button>
        <a href="?" class="filter-btn-reset">
          <i class="fas fa-undo" style="margin-right:6px;"></i>Reset
        </a>
      </div>

      <!-- Active filter summary -->
      <?php if ($filterPosting || $filterStatus || $currentRange != '30'): ?>
      <div class="filter-active-badge">
        <i class="fas fa-info-circle" style="margin-right:4px;"></i>
        Filters active
        <?php if ($filterPosting): ?>
          &middot; 1 posting
        <?php endif; ?>
        <?php if ($filterStatus): ?>
          &middot; <?php echo htmlspecialchars($filterStatus); ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </form>
  </div>

  <!-- Main Content -->
  <div class="analytics-main-content">
    <!-- Top Performing Internships Cards -->
    <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 16px 0;">
      <i class="fas fa-star" style="color:#f59e0b;margin-right:8px;"></i>Top Performing Internships
    </h3>
    <div class="top-picks-grid">
      <?php if (!empty($topPostings)): ?>
        <?php foreach ($topPostings as $posting): ?>
          <div class="top-pick-card">
            <div class="posting-title"><?php echo htmlspecialchars($posting['title']); ?></div>
            <div class="posting-count"><?php echo $posting['applications']; ?></div>
            <div class="posting-label">Applications</div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-data" style="grid-column: 1 / -1;">
          <i class="fas fa-inbox"></i>
          <p>No application data available</p>
        </div>
      <?php endif; ?>
    </div>

    <section class="skill-ranking-card">
      <div class="skill-ranking-head">
        <div>
          <h3 class="skill-ranking-title"><i class="fas fa-ranking-star" style="color:#0f766e;margin-right:8px;"></i>Student Skill Match Ranking</h3>
          <p class="skill-ranking-sub">Ranks applicants by how many of their profile skills match the required skills in your internship postings.</p>
        </div>
      </div>

      <?php if (!empty($studentRankings)): ?>
        <div class="skill-ranking-table-wrap">
          <table class="skill-ranking-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Student</th>
                <th>Posting</th>
                <th>Match</th>
                <th>Matched Skills</th>
                <th>Missing Skills</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentRankings as $rankIndex => $candidate): ?>
                <?php
                  $requiredCount = (int)($candidate['required_count'] ?? 0);
                  $matchedCount = (int)($candidate['matched_count'] ?? 0);
                  $matchScore = (int)($candidate['skill_match_score'] ?? 0);
                  $matchedSkills = array_values(array_filter(array_map('trim', explode(',', (string)($candidate['matched_skills'] ?? '')))));
                  $missingSkills = array_values(array_filter(array_map('trim', explode(',', (string)($candidate['missing_skills'] ?? '')))));
                  $studentName = trim((string)($candidate['first_name'] ?? '') . ' ' . (string)($candidate['last_name'] ?? ''));
                  $studentName = $studentName !== '' ? $studentName : 'Unnamed Student';
                  $scoreClass = $matchScore >= 70 ? '' : 'warn';
                ?>
                <tr>
                  <td><span class="skill-rank-badge">#<?php echo (int)($rankIndex + 1); ?></span></td>
                  <td>
                    <span class="skill-candidate-name"><?php echo htmlspecialchars($studentName); ?></span>
                    <span class="skill-candidate-meta"><?php echo htmlspecialchars((string)($candidate['program'] ?? '')); ?><?php echo !empty($candidate['year_level']) ? ' | Year ' . (int)$candidate['year_level'] : ''; ?></span>
                  </td>
                  <td>
                    <span class="skill-candidate-name"><?php echo htmlspecialchars((string)($candidate['internship_title'] ?? 'Internship')); ?></span>
                    <span class="skill-candidate-meta">Applied <?php echo htmlspecialchars(date('M j, Y', strtotime((string)($candidate['application_date'] ?? 'now')))); ?></span>
                  </td>
                  <td>
                    <span class="skill-score-pill <?php echo $scoreClass; ?>">
                      <i class="fas fa-bullseye"></i>
                      <?php echo $matchScore; ?>%
                    </span>
                    <span class="skill-candidate-meta"><?php echo $matchedCount; ?>/<?php echo $requiredCount; ?> required skills matched</span>
                    <?php if ($candidate['mandatory_match_score'] !== null): ?>
                      <span class="skill-candidate-meta"><?php echo (int)$candidate['mandatory_matched_count']; ?>/<?php echo (int)$candidate['mandatory_count']; ?> mandatory matched</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="skill-chip-row">
                      <?php if (!empty($matchedSkills)): ?>
                        <?php foreach (array_slice($matchedSkills, 0, 5) as $skillName): ?>
                          <span class="skill-chip"><?php echo htmlspecialchars($skillName); ?></span>
                        <?php endforeach; ?>
                      <?php elseif ($requiredCount === 0): ?>
                        <span class="skill-chip missing">No required skills set</span>
                      <?php else: ?>
                        <span class="skill-chip missing">No matched skills</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div class="skill-chip-row">
                      <?php if (!empty($missingSkills)): ?>
                        <?php foreach (array_slice($missingSkills, 0, 5) as $skillName): ?>
                          <span class="skill-chip missing"><?php echo htmlspecialchars($skillName); ?></span>
                        <?php endforeach; ?>
                      <?php elseif ($requiredCount > 0): ?>
                        <span class="skill-chip">Complete match</span>
                      <?php else: ?>
                        <span class="skill-chip missing">N/A</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <span class="skill-score-pill <?php echo strtolower((string)($candidate['status'] ?? '')) === 'rejected' ? 'warn' : ''; ?>">
                      <?php echo htmlspecialchars((string)($candidate['status'] ?? 'Pending')); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="no-data">
          <i class="fas fa-users-viewfinder"></i>
          <p>No student skill match data available yet.</p>
        </div>
      <?php endif; ?>
    </section>

    <!-- Charts Section -->
    <div class="charts-grid">
      <div class="chart-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-chart-line" style="color:#3b82f6;margin-right:8px;"></i>Inquiries & Applications (30 Days)</h3>
        <div class="chart-wrapper">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
      
      <div class="chart-card">
        <h3><i class="fas fa-pie-chart" style="color:#10b981;margin-right:8px;"></i>Application Status</h3>
        <div class="chart-wrapper">
          <canvas id="statusChart"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-bar-chart" style="color:#6366f1;margin-right:8px;"></i>Applications Breakdown</h3>
        <div class="chart-wrapper">
          <canvas id="topChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Trend Chart (Inquiries & Applications)
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($chartDates); ?>,
    datasets: [
      {
        label: 'Inquiries',
        data: <?php echo json_encode($chartInquiries); ?>,
        borderColor: '#0f766e',
        backgroundColor: 'rgba(15, 118, 110, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#0f766e',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      },
      {
        label: 'Applications',
        data: <?php echo json_encode($chartApplications); ?>,
        borderColor: '#12b3ac',
        backgroundColor: 'rgba(18, 179, 172, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#12b3ac',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          font: { size: 12, weight: 500 },
          color: '#6b7280',
          usePointStyle: true,
          padding: 16,
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: '#f3f4f6',
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      },
      x: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      }
    }
  }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($statusLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($statusCounts); ?>,
      backgroundColor: [
        '#0f766e',
        '#12b3ac',
        '#0d5f58',
        '#134e4a',
        '#10b981',
      ],
      borderColor: '#fff',
      borderWidth: 2,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'right',
        labels: {
          font: { size: 12 },
          color: '#6b7280',
          padding: 16,
          usePointStyle: true,
        }
      }
    }
  }
});

// Top Postings Chart
const topCtx = document.getElementById('topChart').getContext('2d');
new Chart(topCtx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($topLabels); ?>,
    datasets: [{
      label: 'Applications',
      data: <?php echo json_encode($topCounts); ?>,
      backgroundColor: '#0f766e',
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: {
          color: '#f3f4f6',
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      },
      y: {
        grid: {
          display: false,
          drawBorder: false,
        },
        ticks: {
          color: '#9ca3af',
          font: { size: 11 }
        }
      }
    }
  }
  });
</script>

<script>
function toggleAnalyticsBanner() {
  const banner = document.getElementById('analyticsBanner');
  const bar    = document.getElementById('bannerCollapsedBar');
  const isCollapsed = banner.classList.toggle('collapsed');
  bar.classList.toggle('visible', isCollapsed);
}

function setRange(days) {
  const form = document.getElementById('analyticsFilterForm');
  const end   = new Date();
  const start = new Date();
  start.setDate(end.getDate() - days);

  const fmt = d => d.toISOString().slice(0, 10);
  form.querySelector('[name="start_date"]').value = fmt(start);
  form.querySelector('[name="end_date"]').value   = fmt(end);
  document.getElementById('rangeInput').value     = days;

  document.querySelectorAll('.range-btn').forEach(btn => btn.classList.remove('active'));
  event.currentTarget.classList.add('active');
}
</script>
