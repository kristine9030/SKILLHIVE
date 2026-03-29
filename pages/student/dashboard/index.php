<?php
require_once __DIR__ . '/../../../backend/db_connect.php';

$firstName = explode(' ', $userName)[0];

// Fetch student data
$stmt = $pdo->prepare("SELECT * FROM student WHERE student_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Compute readiness score dynamically and persist if needed.
$stmt = $pdo->prepare(
  'SELECT ss.skill_level, ss.verified
   FROM student_skill ss
   WHERE ss.student_id = ?'
);
$stmt->execute([$userId]);
$studentSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasResume = !empty($student['resume_file']);
$hasPortfolio = !empty($student['profile_picture']);

$readinessScore = 0.0;
$basicFields = [
  !empty($student['first_name']),
  !empty($student['last_name']),
  !empty($student['program']),
  !empty($student['department']),
  !empty($student['year_level']),
];
$filledBasic = 0;
foreach ($basicFields as $ok) {
  if ($ok) $filledBasic++;
}
$readinessScore += $filledBasic * 6;

if (!empty($student['preferred_industry'])) {
  $readinessScore += 10;
}

if (($student['availability_status'] ?? '') !== 'Unavailable') {
  $readinessScore += 10;
}

if ($hasResume) {
  $readinessScore += 20;
}

if ($hasPortfolio) {
  $readinessScore += 10;
}

if (count($studentSkills) > 0) {
  $skillMap = [
    'Beginner' => 40,
    'Intermediate' => 70,
    'Advanced' => 90,
  ];

  $totalSkillValue = 0;
  foreach ($studentSkills as $row) {
    $value = $skillMap[$row['skill_level']] ?? 40;
    if ((int) ($row['verified'] ?? 0) === 1) {
      $value = min(100, $value + 10);
    }
    $totalSkillValue += $value;
  }

  $avgSkill = $totalSkillValue / max(1, count($studentSkills));
  $readinessScore += ($avgSkill * 0.20);
}

$readinessScore = round(max(0, min(100, $readinessScore)), 2);
$storedScore = isset($student['internship_readiness_score']) ? (float) $student['internship_readiness_score'] : 0.0;
if (abs($storedScore - $readinessScore) >= 0.01) {
  $stmt = $pdo->prepare('UPDATE student SET internship_readiness_score = ?, updated_at = NOW() WHERE student_id = ?');
  $stmt->execute([$readinessScore, $userId]);
}

$student['internship_readiness_score'] = $readinessScore;

$recommendedStmt = $pdo->query(
  "SELECT
      i.internship_id,
      i.title,
      i.location,
      i.duration_weeks,
      i.allowance,
      COALESCE(i.posted_at, i.created_at) AS posted_at,
      e.company_name,
      COUNT(DISTINCT a.application_id) AS applicant_count,
      GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name SEPARATOR ', ') AS skills_csv
   FROM internship i
   INNER JOIN employer e ON e.employer_id = i.employer_id
   LEFT JOIN application a ON a.internship_id = i.internship_id
   LEFT JOIN internship_skill iskill ON iskill.internship_id = i.internship_id
   LEFT JOIN skill s ON s.skill_id = iskill.skill_id
   WHERE i.status = 'Open'
  GROUP BY i.internship_id, i.title, i.location, i.duration_weeks, i.allowance, COALESCE(i.posted_at, i.created_at), e.company_name
   ORDER BY posted_at DESC, i.internship_id DESC
   LIMIT 3"
);
$recommendedInternships = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Welcome back, <span class="name-gradient"><?php echo htmlspecialchars($firstName); ?></span>! 👋</h2>
    <p class="page-subtitle">Here's what's happening with your internship journey.</p>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card stat-card-applications">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-paper-plane" style="color:#06B6D4"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +3 this week</div>
        <div class="stat-card-num">12</div>
      </div>
      <div class="stat-card-label">Applications</div>
    </div>
  </div>
  <div class="stat-card stat-card-shortlisted">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-circle" style="color:#10B981"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +1 today</div>
        <div class="stat-card-num">4</div>
      </div>
      <div class="stat-card-label">Shortlisted</div>
    </div>
  </div>
  <div class="stat-card stat-card-ojt">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">of 400 target</div>
        <div class="stat-card-num">248</div>
      </div>
      <div class="stat-card-label">OJT Hours</div>
    </div>
  </div>
  <div class="stat-card stat-card-readiness">
    <div class="stat-card-icon" style="background:rgba(111,66,193,.1)"><i class="fas fa-star" style="color:#6F42C1"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +5 pts</div>
        <div class="stat-card-num"><?php echo number_format((float) ($student['internship_readiness_score'] ?? 0), 2); ?></div>
      </div>
      <div class="stat-card-label">Readiness Score</div>
    </div>
  </div>
</div>

<!-- Main Content Grid -->
<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Recommended For You</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=student/marketplace" class="btn btn-ghost btn-sm">View All</a>
      </div>

      <div class="job-feed">
      <?php
      $logoPalettes = [
        'linear-gradient(135deg,#06B6D4,#10B981)',
        'linear-gradient(135deg,#2563EB,#06B6D4)',
        'linear-gradient(135deg,#22C55E,#06B6D4)',
      ];
      ?>
      <?php foreach ($recommendedInternships as $idx => $internship): ?>
        <?php
        $companyName = (string) ($internship['company_name'] ?? 'Company');
        $title = (string) ($internship['title'] ?? 'Internship Opportunity');
        $location = trim((string) ($internship['location'] ?? 'Remote'));
        $durationWeeks = (int) ($internship['duration_weeks'] ?? 0);
        $allowance = (float) ($internship['allowance'] ?? 0);
        $applicantCount = (int) ($internship['applicant_count'] ?? 0);
        $skillsCsv = (string) ($internship['skills_csv'] ?? '');
        $skills = array_values(array_filter(array_map('trim', explode(',', $skillsCsv))));
        $postedAtRaw = (string) ($internship['posted_at'] ?? '');
        $postedLabel = 'N/A';
        if ($postedAtRaw !== '') {
          $postedTs = strtotime($postedAtRaw);
          if ($postedTs !== false) {
            $postedLabel = date('M j, Y', $postedTs);
          }
        }
        $companyInitial = strtoupper(substr(trim($companyName), 0, 1));
        $companyInitial = $companyInitial !== '' ? $companyInitial : 'C';
        $palette = $logoPalettes[$idx % count($logoPalettes)];
        ?>
        <div class="job-card">
          <div class="job-card-header">
            <div class="co-logo" style="background:<?php echo htmlspecialchars($palette); ?>"><?php echo htmlspecialchars($companyInitial); ?></div>
            <div class="job-card-info">
              <div class="job-card-title"><?php echo htmlspecialchars($title); ?></div>
              <div class="job-card-company"><?php echo htmlspecialchars($companyName); ?></div>
            </div>
          </div>
          <div class="job-card-meta">
            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location !== '' ? $location : 'Remote'); ?></span>
            <span><i class="fas fa-clock"></i> <?php echo $durationWeeks > 0 ? $durationWeeks . ' weeks' : 'Flexible'; ?></span>
            <span><i class="fas fa-money-bill"></i> <?php echo $allowance > 0 ? '₱' . number_format($allowance, 0) . '/mo' : 'Allowance TBD'; ?></span>
          </div>
          <div class="job-card-skills">
            <?php if (!empty($skills)): ?>
              <?php foreach (array_slice($skills, 0, 3) as $skill): ?>
                <span class="skill-chip match"><?php echo htmlspecialchars($skill); ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="skill-chip gap">General Internship</span>
            <?php endif; ?>
          </div>
          <div class="job-card-bottom">
            <div class="job-card-stats">
              <span><i class="fas fa-users"></i> <?php echo number_format($applicantCount); ?> applicants</span>
              <span><i class="fas fa-calendar-alt"></i> Posted <?php echo htmlspecialchars($postedLabel); ?></span>
            </div>
            <div class="job-card-actions">
              <a href="<?php echo $baseUrl; ?>/layout.php?page=student/marketplace" class="btn btn-primary btn-sm">Apply Now</a>
              <button class="btn btn-ghost btn-sm" type="button" aria-label="Save internship"><i class="fas fa-bookmark"></i></button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Profile Completeness -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Profile Completeness</h3></div>
      <div style="display:flex;align-items:center;justify-content:center;margin-bottom:20px">
        <div style="position:relative;width:90px;height:90px">
          <svg width="90" height="90"><circle cx="45" cy="45" r="35" stroke="#F0F0F0" stroke-width="6" fill="none"/><circle cx="45" cy="45" r="35" fill="none" stroke="#06B6D4" stroke-width="6" stroke-linecap="round" stroke-dasharray="220" stroke-dashoffset="33" transform="rotate(-90,45,45)"/></svg>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:800;font-size:1.1rem;color:#EF4444">85%</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i>Basic information</span></div>
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i>Skills added</span></div>
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i>Resume uploaded</span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd;margin-right:6px;font-size:.7rem"></i><span style="color:#999">Add portfolio project</span></span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd;margin-right:6px;font-size:.7rem"></i><span style="color:#999">Add certification</span></span></div>
      </div>
    </div>
  </div>
</div>