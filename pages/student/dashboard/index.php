<?php
require_once __DIR__ . '/../../../backend/db_connect.php';

// Fetch student data
$stmt = $pdo->prepare('SELECT * FROM student WHERE student_id = ?');
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$firstName = trim((string) ($student['first_name'] ?? ''));
if ($firstName === '') {
  $nameParts = preg_split('/\s+/', trim((string) $userName)) ?: ['Student'];
  $firstName = trim((string) ($nameParts[0] ?? 'Student'));
  if ($firstName === '') {
    $firstName = 'Student';
  }
}

// Fetch skills once and reuse across readiness + completeness widgets.
$stmt = $pdo->prepare(
  'SELECT ss.skill_level, ss.verified
   FROM student_skill ss
   WHERE ss.student_id = ?'
);
$stmt->execute([$userId]);
$studentSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasBasicInfo = !empty($student['first_name'])
  && !empty($student['last_name'])
  && !empty($student['program'])
  && !empty($student['department'])
  && !empty($student['year_level']);
$hasSkills = count($studentSkills) > 0;
$hasResume = !empty($student['resume_file']);
$hasPortfolio = !empty($student['profile_picture']);
$hasPortfolioProject = $hasPortfolio
  || trim((string) ($student['portfolio_url'] ?? '')) !== ''
  || trim((string) ($student['portfolio_entries'] ?? '')) !== '';

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

$verifiedSkillCount = 0;

if ($hasSkills) {
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
      $verifiedSkillCount++;
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

$readinessDelta = round($readinessScore - $storedScore, 2);
$readinessTrendClass = 'neutral';
$readinessTrendIcon = 'fa-minus';
$readinessTrendText = 'No change';
if ($readinessDelta > 0.01) {
  $readinessTrendClass = 'up';
  $readinessTrendIcon = 'fa-arrow-up';
  $readinessTrendText = '+' . number_format($readinessDelta, 2) . ' pts';
} elseif ($readinessDelta < -0.01) {
  $readinessTrendClass = 'down';
  $readinessTrendIcon = 'fa-arrow-down';
  $readinessTrendText = number_format($readinessDelta, 2) . ' pts';
}

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$todayDate = date('Y-m-d');

$applicationStatsStmt = $pdo->prepare(
  'SELECT
      COUNT(*) AS total_applications,
      SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ""))) = "shortlisted" THEN 1 ELSE 0 END) AS shortlisted_total,
      SUM(CASE WHEN DATE(application_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS applications_this_week,
      SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ""))) = "shortlisted" AND DATE(application_date) = ? THEN 1 ELSE 0 END) AS shortlisted_today
   FROM application
   WHERE student_id = ?'
);
$applicationStatsStmt->execute([$weekStart, $weekEnd, $todayDate, $userId]);
$applicationStats = $applicationStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalApplications = (int) ($applicationStats['total_applications'] ?? 0);
$shortlistedApplications = (int) ($applicationStats['shortlisted_total'] ?? 0);
$applicationsThisWeek = (int) ($applicationStats['applications_this_week'] ?? 0);
$shortlistedToday = (int) ($applicationStats['shortlisted_today'] ?? 0);

$applicationsTrendClass = $applicationsThisWeek > 0 ? 'up' : 'neutral';
$applicationsTrendIcon = $applicationsThisWeek > 0 ? 'fa-arrow-up' : 'fa-minus';
$applicationsTrendText = $applicationsThisWeek > 0
  ? '+' . number_format($applicationsThisWeek) . ' this week'
  : 'No new this week';

$shortlistedTrendClass = $shortlistedToday > 0 ? 'up' : 'neutral';
$shortlistedTrendIcon = $shortlistedToday > 0 ? 'fa-arrow-up' : 'fa-minus';
$shortlistedTrendText = $shortlistedToday > 0
  ? '+' . number_format($shortlistedToday) . ' today'
  : 'No shortlist today';

$ojtStatsStmt = $pdo->prepare(
  'SELECT
      COALESCE(SUM(hours_completed), 0) AS completed_hours,
      COALESCE(SUM(hours_required), 0) AS required_hours
   FROM ojt_record
   WHERE student_id = ?'
);
$ojtStatsStmt->execute([$userId]);
$ojtStats = $ojtStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalOjtHours = round((float) ($ojtStats['completed_hours'] ?? 0), 2);
$ojtTargetHours = round((float) ($ojtStats['required_hours'] ?? 0), 2);

$totalOjtHoursLabel = abs($totalOjtHours - round($totalOjtHours)) < 0.01
  ? number_format($totalOjtHours, 0)
  : number_format($totalOjtHours, 2);
$ojtTargetHoursLabel = abs($ojtTargetHours - round($ojtTargetHours)) < 0.01
  ? number_format($ojtTargetHours, 0)
  : number_format($ojtTargetHours, 2);
$ojtTargetText = $ojtTargetHours > 0
  ? 'of ' . $ojtTargetHoursLabel . ' target'
  : 'No OJT target yet';

$hasCertification = $verifiedSkillCount > 0;
$profileChecklist = [
  ['label' => 'Basic information', 'complete' => $hasBasicInfo],
  ['label' => 'Skills added', 'complete' => $hasSkills],
  ['label' => 'Resume uploaded', 'complete' => $hasResume],
  ['label' => 'Add portfolio project', 'complete' => $hasPortfolioProject],
  ['label' => 'Add certification', 'complete' => $hasCertification],
];

$profileCompletedChecks = 0;
foreach ($profileChecklist as $checkItem) {
  if (!empty($checkItem['complete'])) {
    $profileCompletedChecks++;
  }
}

$profileCompleteness = (int) round(($profileCompletedChecks / max(1, count($profileChecklist))) * 100);
$profileDashArray = 220;
$profileDashOffset = (int) round($profileDashArray * (1 - ($profileCompleteness / 100)));
$profileScoreColor = $profileCompleteness >= 75
  ? '#10B981'
  : ($profileCompleteness >= 50 ? '#F59E0B' : '#EF4444');

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

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg, #0a0e27 0%, #162550 40%, #1a3a5c 70%, #0f2a45 100%);border-radius:16px;padding:32px;margin-bottom:24px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 12px 40px rgba(10, 14, 39, 0.4), 0 0 1px rgba(255, 255, 255, 0.1) inset;">
  <div style="z-index:2;flex:1;">
    <h2 style="font-size:1.8rem;font-weight:800;margin:0 0 12px 0;line-height:1.2;">Welcome back, <?php echo htmlspecialchars($firstName); ?>! 👋</h2>
    <p style="font-size:1rem;margin:0 0 16px 0;opacity:0.95;line-height:1.4;">You're <?php echo number_format((float)($student['internship_readiness_score'] ?? 0), 0); ?>% ready for internship opportunities. Keep improving your profile!</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <a href="<?php echo $baseUrl; ?>/layout.php?page=student/profile" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.2);color:white;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;border:1px solid rgba(255,255,255,0.3);transition:all 0.2s;cursor:pointer;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        <i class="fas fa-user"></i> Complete Profile
      </a>
      <a href="<?php echo $baseUrl; ?>/layout.php?page=student/marketplace" style="display:inline-flex;align-items:center;gap:8px;background:white;color:#0f2a45;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;transition:all 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(59,130,246,0.3)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
        <i class="fas fa-briefcase"></i> Browse Internships
      </a>
    </div>
  </div>
  <div style="z-index:1;opacity:0.15;position:absolute;right:0;top:0;font-size:8rem;line-height:1;">
    <i class="fas fa-briefcase"></i>
  </div>
</div>

<div class="page-header">
  <div>
    <h2 class="page-title">Your Internship Journey</h2>
    <p class="page-subtitle">Track your applications and opportunities.</p>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card stat-card-applications">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Applications.png" alt="Applications" style="width:100%;height:100%;object-fit:contain;"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend <?php echo htmlspecialchars($applicationsTrendClass); ?>"><i class="fas <?php echo htmlspecialchars($applicationsTrendIcon); ?>"></i> <?php echo htmlspecialchars($applicationsTrendText); ?></div>
        <div class="stat-card-num"><?php echo number_format($totalApplications); ?></div>
      </div>
      <div class="stat-card-label">Applications</div>
    </div>
  </div>
  <div class="stat-card stat-card-shortlisted">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Shortlisted.png" alt="Shortlisted" style="width:100%;height:100%;object-fit:contain;"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend <?php echo htmlspecialchars($shortlistedTrendClass); ?>"><i class="fas <?php echo htmlspecialchars($shortlistedTrendIcon); ?>"></i> <?php echo htmlspecialchars($shortlistedTrendText); ?></div>
        <div class="stat-card-num"><?php echo number_format($shortlistedApplications); ?></div>
      </div>
      <div class="stat-card-label">Shortlisted</div>
    </div>
  </div>
  <div class="stat-card stat-card-ojt">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/OJT%20Hours.png" alt="OJT Hours" style="width:100%;height:100%;object-fit:contain;"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo htmlspecialchars($ojtTargetText); ?></div>
        <div class="stat-card-num"><?php echo htmlspecialchars($totalOjtHoursLabel); ?></div>
      </div>
      <div class="stat-card-label">OJT Hours</div>
    </div>
  </div>
  <div class="stat-card stat-card-readiness">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Readiness%20Score.png" alt="Readiness Score" style="width:100%;height:100%;object-fit:contain;"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend <?php echo htmlspecialchars($readinessTrendClass); ?>"><i class="fas <?php echo htmlspecialchars($readinessTrendIcon); ?>"></i> <?php echo htmlspecialchars($readinessTrendText); ?></div>
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
      <?php if (empty($recommendedInternships)): ?>
        <div class="mini-row"><span style="color:#999">No open internships available right now.</span></div>
      <?php endif; ?>
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
          <svg width="90" height="90"><circle cx="45" cy="45" r="35" stroke="#F0F0F0" stroke-width="6" fill="none"/><circle cx="45" cy="45" r="35" fill="none" stroke="#2C5AA0" stroke-width="6" stroke-linecap="round" stroke-dasharray="<?php echo (int) $profileDashArray; ?>" stroke-dashoffset="<?php echo (int) $profileDashOffset; ?>" transform="rotate(-90,45,45)"/></svg>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:800;font-size:1.1rem;background:linear-gradient(135deg, #0a0e27 0%, #162550 40%, #1a3a5c 70%, #0f2a45 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text"><?php echo (int) $profileCompleteness; ?>%</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($profileChecklist as $checkItem): ?>
          <div class="mini-row">
            <span>
              <?php if (!empty($checkItem['complete'])): ?>
                <i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i><?php echo htmlspecialchars((string) $checkItem['label']); ?>
              <?php else: ?>
                <i class="fas fa-circle" style="color:#ddd;margin-right:6px;font-size:.7rem"></i><span style="color:#999"><?php echo htmlspecialchars((string) $checkItem['label']); ?></span>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>