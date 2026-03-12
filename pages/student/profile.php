<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$stmt = $pdo->prepare("SELECT * FROM student WHERE student_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-header">
  <div>
    <h2 class="page-title">My Profile</h2>
    <p class="page-subtitle">Manage your professional profile and portfolio.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('editModal').style.display='flex'"><i class="fas fa-edit"></i> Edit Profile</button>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Basic Info Card -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Basic Information</h3></div>
      <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
        <div class="profile-avatar-lg"><?php echo $initials; ?></div>
        <div>
          <div style="font-weight:700;font-size:1.1rem"><?php echo htmlspecialchars($userName); ?></div>
          <div style="color:#999;font-size:.85rem"><?php echo htmlspecialchars($student['email'] ?? $userEmail); ?></div>
          <div style="margin-top:6px">
            <span class="skill-chip match"><?php echo htmlspecialchars($student['program'] ?? 'BS Computer Science'); ?></span>
          </div>
        </div>
      </div>
      <div class="info-grid">
        <div class="info-item"><div class="info-label">Student Number</div><div class="info-value"><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></div></div>
        <div class="info-item"><div class="info-label">Department</div><div class="info-value"><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></div></div>
        <div class="info-item"><div class="info-label">Year Level</div><div class="info-value"><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?></div></div>
        <div class="info-item"><div class="info-label">Availability</div><div class="info-value"><?php echo htmlspecialchars($student['availability_status'] ?? 'Available'); ?></div></div>
        <div class="info-item"><div class="info-label">Preferred Industry</div><div class="info-value"><?php echo htmlspecialchars($student['preferred_industry'] ?? 'Not set'); ?></div></div>
        <div class="info-item"><div class="info-label">Readiness Score</div><div class="info-value"><?php echo htmlspecialchars($student['internship_readiness_score'] ?? '0'); ?>/100</div></div>
      </div>
    </div>

    <!-- Skills Matrix -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Skills Matrix</h3><button class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> Add Skill</button></div>
      <div class="skill-bar-item"><div class="skill-bar-header"><span>PHP / Laravel</span><span>85%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:85%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div></div>
      <div class="skill-bar-item"><div class="skill-bar-header"><span>JavaScript / React</span><span>78%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:78%;background:linear-gradient(90deg,#10B981,#06B6D4)"></div></div></div>
      <div class="skill-bar-item"><div class="skill-bar-header"><span>Python</span><span>70%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:70%;background:linear-gradient(90deg,#F59E0B,#10B981)"></div></div></div>
      <div class="skill-bar-item"><div class="skill-bar-header"><span>UI/UX Design</span><span>65%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:65%;background:linear-gradient(90deg,#EF4444,#F59E0B)"></div></div></div>
      <div class="skill-bar-item"><div class="skill-bar-header"><span>MySQL / Database</span><span>80%</span></div><div class="skill-bar-bg"><div class="skill-bar-fill" style="width:80%;background:linear-gradient(90deg,#06B6D4,#111)"></div></div></div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Profile Completeness -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Completeness</h3></div>
      <div style="text-align:center;margin-bottom:16px">
        <div style="position:relative;width:90px;height:90px;margin:0 auto">
          <svg width="90" height="90"><circle cx="45" cy="45" r="36" stroke="#F0F0F0" stroke-width="6" fill="none"/><circle cx="45" cy="45" r="36" fill="none" stroke="#06B6D4" stroke-width="6" stroke-linecap="round" stroke-dasharray="226" stroke-dashoffset="34" transform="rotate(-90,45,45)"/></svg>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:800;font-size:1.1rem;color:#06B6D4">85%</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;font-size:.82rem">
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981"></i> Basic Info</span></div>
        <div class="mini-row"><span><i class="fas fa-check-circle" style="color:#10B981"></i> Skills Added</span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd"></i> Upload Resume</span></div>
        <div class="mini-row"><span><i class="fas fa-circle" style="color:#ddd"></i> Portfolio</span></div>
      </div>
    </div>

    <!-- Resume Upload -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Resume</h3></div>
      <?php if (!empty($student['resume_file'])): ?>
        <div class="mini-row">
          <span><i class="fas fa-file-pdf" style="color:#EF4444;margin-right:6px"></i> <?php echo htmlspecialchars($student['resume_file']); ?></span>
          <span style="color:#10B981">Uploaded</span>
        </div>
      <?php else: ?>
        <div class="upload-zone" style="padding:20px;text-align:center">
          <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:#ccc;margin-bottom:8px;display:block"></i>
          <div style="font-size:.82rem;color:#999;margin-bottom:8px">Drag & drop your resume or</div>
          <button class="btn btn-primary btn-sm">Browse Files</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
