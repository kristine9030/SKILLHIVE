<?php
require_once __DIR__ . '/../../backend/db_connect.php';

$companyName = $userName;
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Employer Dashboard</h2>
    <p class="page-subtitle">Manage your internship postings and track candidates.</p>
  </div>
  <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Post Internship</a>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-briefcase" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">5</div><div class="stat-card-label">Active Postings</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-users" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">48</div><div class="stat-card-label">Total Applicants</div></div>
    <div class="stat-card-trend up"><i class="fas fa-arrow-up"></i> +12 this week</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-calendar-check" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">8</div><div class="stat-card-label">Interviews</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-double" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">6</div><div class="stat-card-label">Hired</div></div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Active Postings -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Active Postings</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/post_internship" class="btn btn-ghost btn-sm">View All</a>
      </div>

      <div class="job-card">
        <div class="job-card-header">
          <div class="job-card-info">
            <div class="job-card-title">UI/UX Design Internship</div>
            <div class="job-card-company">Posted 3 days ago</div>
          </div>
          <span class="status-pill status-accepted">Open</span>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-users"></i> 12 applicants</span>
          <span><i class="fas fa-map-marker-alt"></i> BGC, Taguig</span>
          <span><i class="fas fa-clock"></i> 3 months</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-ghost btn-sm">View Applicants</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</button>
        </div>
      </div>

      <div class="job-card">
        <div class="job-card-header">
          <div class="job-card-info">
            <div class="job-card-title">Software Engineering Intern</div>
            <div class="job-card-company">Posted 1 week ago</div>
          </div>
          <span class="status-pill status-accepted">Open</span>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-users"></i> 24 applicants</span>
          <span><i class="fas fa-map-marker-alt"></i> Makati City</span>
          <span><i class="fas fa-clock"></i> 6 months</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-ghost btn-sm">View Applicants</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</button>
        </div>
      </div>

      <div class="job-card">
        <div class="job-card-header">
          <div class="job-card-info">
            <div class="job-card-title">Data Analyst Intern</div>
            <div class="job-card-company">Posted 2 weeks ago</div>
          </div>
          <span class="status-pill status-shortlisted">Closing Soon</span>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-users"></i> 18 applicants</span>
          <span><i class="fas fa-map-marker-alt"></i> Remote</span>
          <span><i class="fas fa-clock"></i> 4 months</span>
        </div>
        <div class="job-card-actions">
          <button class="btn btn-ghost btn-sm">View Applicants</button>
          <button class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i> Edit</button>
        </div>
      </div>
    </div>

    <!-- Recent Applicants -->
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
            <tr>
              <td><div style="display:flex;align-items:center;gap:10px"><div class="topbar-avatar" style="width:32px;height:32px;font-size:.7rem">JD</div>Juan dela Cruz</div></td>
              <td>UI/UX Design</td>
              <td><span class="match-badge">87%</span></td>
              <td><span class="status-pill status-shortlisted">Shortlisted</span></td>
              <td><button class="btn btn-ghost btn-sm">Review</button></td>
            </tr>
            <tr>
              <td><div style="display:flex;align-items:center;gap:10px"><div class="topbar-avatar" style="width:32px;height:32px;font-size:.7rem">MR</div>Maria Reyes</div></td>
              <td>Software Eng.</td>
              <td><span class="match-badge">82%</span></td>
              <td><span class="status-pill status-interview">Interview</span></td>
              <td><button class="btn btn-ghost btn-sm">Review</button></td>
            </tr>
            <tr>
              <td><div style="display:flex;align-items:center;gap:10px"><div class="topbar-avatar" style="width:32px;height:32px;font-size:.7rem">AL</div>Andre Lopez</div></td>
              <td>Data Analyst</td>
              <td><span class="match-badge">78%</span></td>
              <td><span class="status-pill status-pending">Pending</span></td>
              <td><button class="btn btn-ghost btn-sm">Review</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Company Badge -->
    <div class="panel-card" style="text-align:center">
      <div class="profile-avatar-lg" style="margin:0 auto 12px"><?php echo $initials; ?></div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:4px"><?php echo htmlspecialchars($companyName); ?></div>
      <div style="font-size:.78rem;color:#999;margin-bottom:10px">Employer Account</div>
      <span class="status-pill status-accepted">Verified</span>
    </div>

    <!-- Quick Stats -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>This Month</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Applications Received</span><span style="font-weight:700">48</span></div>
        <div class="mini-row"><span>Interviews Conducted</span><span style="font-weight:700">8</span></div>
        <div class="mini-row"><span>Offers Extended</span><span style="font-weight:700">6</span></div>
        <div class="mini-row"><span>Acceptance Rate</span><span style="font-weight:700;color:#10B981">83%</span></div>
      </div>
    </div>

    <!-- Upcoming Interviews -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Upcoming</h3></div>
      <div class="timeline">
        <div class="timeline-item">
          <div class="timeline-dot" style="background:#06B6D4"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem">Interview — Juan D.C.</div>
            <div style="font-size:.75rem;color:#999">Tomorrow, 2:00 PM</div>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:#F59E0B"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem">Interview — Maria R.</div>
            <div style="font-size:.75rem;color:#999">Jan 25, 10:00 AM</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
