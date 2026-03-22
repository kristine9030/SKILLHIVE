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
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Welcome back, <?php echo htmlspecialchars($firstName); ?>! 👋</h2>
    <p class="page-subtitle">Here's what's happening with your internship journey.</p>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-paper-plane" style="color:#06B6D4"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num">12</div>
      <div class="stat-card-label">Applications</div>
    </div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +3 this week</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-circle" style="color:#10B981"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num">4</div>
      <div class="stat-card-label">Shortlisted</div>
    </div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +1 today</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(8,145,178,.12)"><i class="fas fa-list-check" style="color:#0E7490"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num">2</div>
      <div class="stat-card-label">Waitlisted</div>
    </div>
    <div class="stat-card-trend neutral">awaiting openings</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num">248</div>
      <div class="stat-card-label">OJT Hours</div>
    </div>
    <div class="stat-card-trend neutral">of 400 target</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(111,66,193,.1)"><i class="fas fa-star" style="color:#6F42C1"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num"><?php echo number_format((float) ($student['internship_readiness_score'] ?? 0), 2); ?></div>
      <div class="stat-card-label">Readiness Score</div>
    </div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +5 pts</div>
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

      <div class="job-card">
        <div class="job-card-header">
          <div class="co-logo" style="background:linear-gradient(135deg,#06B6D4,#10B981)">G</div>
          <div class="job-card-info">
            <div class="job-card-title">UI/UX Design Internship</div>
            <div class="job-card-company">Google Philippines</div>
          </div>
          <div class="match-badge">87% fit</div>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-map-marker-alt"></i> BGC, Taguig</span>
          <span><i class="fas fa-clock"></i> 3 months</span>
          <span><i class="fas fa-money-bill"></i> ₱15,000/mo</span>
        </div>
        <div class="job-card-skills">
          <span class="skill-chip match">Figma ✓</span>
          <span class="skill-chip match">React ✓</span>
          <span class="skill-chip gap">Flutter ↑</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-primary btn-sm">Apply Now</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-bookmark"></i></button>
        </div>
      </div>

      <div class="job-card">
        <div class="job-card-header">
          <div class="co-logo" style="background:linear-gradient(135deg,#F59E0B,#EF4444)">A</div>
          <div class="job-card-info">
            <div class="job-card-title">Software Engineering Intern</div>
            <div class="job-card-company">Accenture Philippines</div>
          </div>
          <div class="match-badge">82% fit</div>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-map-marker-alt"></i> Makati City</span>
          <span><i class="fas fa-clock"></i> 6 months</span>
          <span><i class="fas fa-money-bill"></i> ₱12,000/mo</span>
        </div>
        <div class="job-card-skills">
          <span class="skill-chip match">Java ✓</span>
          <span class="skill-chip match">SQL ✓</span>
          <span class="skill-chip gap">AWS ↑</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-primary btn-sm">Apply Now</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-bookmark"></i></button>
        </div>
      </div>

      <div class="job-card">
        <div class="job-card-header">
          <div class="co-logo" style="background:linear-gradient(135deg,#10B981,#06B6D4)">S</div>
          <div class="job-card-info">
            <div class="job-card-title">Data Science Intern</div>
            <div class="job-card-company">Shopee Philippines</div>
          </div>
          <div class="match-badge">78% fit</div>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-map-marker-alt"></i> Remote</span>
          <span><i class="fas fa-clock"></i> 4 months</span>
          <span><i class="fas fa-money-bill"></i> ₱10,000/mo</span>
        </div>
        <div class="job-card-skills">
          <span class="skill-chip match">Python ✓</span>
          <span class="skill-chip gap">TensorFlow ↑</span>
          <span class="skill-chip gap">Spark ↑</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-primary btn-sm">Apply Now</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-bookmark"></i></button>
        </div>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Profile Completeness -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Profile Status</h3></div>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
        <div style="position:relative;width:70px;height:70px">
          <svg width="70" height="70"><circle cx="35" cy="35" r="28" stroke="#F0F0F0" stroke-width="5" fill="none"/><circle cx="35" cy="35" r="28" fill="none" stroke="#06B6D4" stroke-width="5" stroke-linecap="round" stroke-dasharray="176" stroke-dashoffset="26" transform="rotate(-90,35,35)"/></svg>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:800;font-size:.9rem;color:#06B6D4">85%</div>
        </div>
        <div>
          <div style="font-weight:700;font-size:.9rem;margin-bottom:4px">Almost there!</div>
          <div style="font-size:.78rem;color:#999">Complete your profile to get better matches.</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i>Basic Info</span><span style="color:#10B981">Done</span></div>
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px"></i>Skills Added</span><span style="color:#10B981">Done</span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd;margin-right:6px"></i>Upload Resume</span><span style="color:#F59E0B">Pending</span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd;margin-right:6px"></i>Add Portfolio</span><span style="color:#F59E0B">Pending</span></div>
      </div>
    </div>

    <!-- Upcoming -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Upcoming</h3></div>
      <div class="timeline">
        <div class="timeline-item">
          <div class="timeline-dot" style="background:#06B6D4"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem">Application Update — Google</div>
            <div style="font-size:.75rem;color:#999">Status review tomorrow, 2:00 PM</div>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:#F59E0B"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem">Submit Weekly Report</div>
            <div style="font-size:.75rem;color:#999">Friday, 5:00 PM</div>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:#10B981"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem">Skill Assessment Due</div>
            <div style="font-size:.75rem;color:#999">Next Monday</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Quick Actions</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo $baseUrl; ?>/layout.php?page=student/resume-ai" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-file-lines"></i> Open CV Builder</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=student/matching" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-brain"></i> Find AI Matches</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=student/ojt-log" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-clock"></i> Log OJT Hours</a>
      </div>
    </div>
  </div>
</div>