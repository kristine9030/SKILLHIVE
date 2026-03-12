<div class="page-header">
  <div>
    <h2 class="page-title">Adviser Dashboard</h2>
    <p class="page-subtitle">Monitor student internships, endorsements, and OJT progress.</p>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-user-graduate" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">42</div><div class="stat-card-label">My Students</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-stamp" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">28</div><div class="stat-card-label">Endorsed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-clock" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">8</div><div class="stat-card-label">Pending Review</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-building" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">15</div><div class="stat-card-label">Partner Companies</div></div>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Students by Department -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Students by Department</h3></div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <div class="skill-bar-header"><span>CICS — Computer Science</span><span>18 students</span></div>
          <div class="dept-bar"><div class="dept-bar-fill" style="width:100%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>CICS — Information Technology</span><span>12 students</span></div>
          <div class="dept-bar"><div class="dept-bar-fill" style="width:67%;background:linear-gradient(90deg,#F59E0B,#10B981)"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>COE — Engineering</span><span>8 students</span></div>
          <div class="dept-bar"><div class="dept-bar-fill" style="width:44%;background:linear-gradient(90deg,#EF4444,#F59E0B)"></div></div>
        </div>
        <div>
          <div class="skill-bar-header"><span>CBA — Business Admin</span><span>4 students</span></div>
          <div class="dept-bar"><div class="dept-bar-fill" style="width:22%;background:linear-gradient(90deg,#6F42C1,#06B6D4)"></div></div>
        </div>
      </div>
    </div>

    <!-- Recent Student Activity -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Recent Student Activity</h3>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Student</th><th>Company</th><th>Hours</th><th>Progress</th><th>Status</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><div style="display:flex;align-items:center;gap:8px"><div class="topbar-avatar" style="width:28px;height:28px;font-size:.65rem">JD</div>Juan dela Cruz</div></td>
              <td>Google PH</td>
              <td>248/400</td>
              <td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:62%;background:#10B981"></div></div></td>
              <td><span class="status-pill status-accepted">On Track</span></td>
            </tr>
            <tr>
              <td><div style="display:flex;align-items:center;gap:8px"><div class="topbar-avatar" style="width:28px;height:28px;font-size:.65rem;background:#10B981">MR</div>Maria Reyes</div></td>
              <td>Accenture PH</td>
              <td>180/400</td>
              <td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:45%;background:#F59E0B"></div></div></td>
              <td><span class="status-pill status-shortlisted">Progressing</span></td>
            </tr>
            <tr>
              <td><div style="display:flex;align-items:center;gap:8px"><div class="topbar-avatar" style="width:28px;height:28px;font-size:.65rem;background:#F59E0B">AL</div>Andre Lopez</div></td>
              <td>Shopee PH</td>
              <td>90/400</td>
              <td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:22%;background:#EF4444"></div></div></td>
              <td><span class="status-pill status-pending">Behind</span></td>
            </tr>
            <tr>
              <td><div style="display:flex;align-items:center;gap:8px"><div class="topbar-avatar" style="width:28px;height:28px;font-size:.65rem;background:#6F42C1">KP</div>Kristine Padilla</div></td>
              <td>Grab PH</td>
              <td>320/400</td>
              <td><div class="progress-bar" style="width:80px"><div class="progress-fill" style="width:80%;background:#10B981"></div></div></td>
              <td><span class="status-pill status-accepted">On Track</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Pending Endorsements -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Pending Endorsements</h3></div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="padding:12px;background:#f9fafb;border-radius:10px">
          <div style="font-weight:600;font-size:.85rem">Juan dela Cruz</div>
          <div style="font-size:.75rem;color:#999;margin:4px 0 8px">→ Google PH — UI/UX Design</div>
          <div style="display:flex;gap:6px">
            <button class="btn btn-primary btn-sm" style="flex:1">Endorse</button>
            <button class="btn btn-ghost btn-sm" style="flex:1">Review</button>
          </div>
        </div>
        <div style="padding:12px;background:#f9fafb;border-radius:10px">
          <div style="font-weight:600;font-size:.85rem">Maria Reyes</div>
          <div style="font-size:.75rem;color:#999;margin:4px 0 8px">→ Accenture — Software Eng.</div>
          <div style="display:flex;gap:6px">
            <button class="btn btn-primary btn-sm" style="flex:1">Endorse</button>
            <button class="btn btn-ghost btn-sm" style="flex:1">Review</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Quick Actions</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/endorsement" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-stamp"></i> Manage Endorsements</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/monitoring" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-eye"></i> OJT Monitoring</a>
        <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/companies" class="btn btn-ghost btn-sm" style="justify-content:flex-start;width:100%"><i class="fas fa-building"></i> Verify Companies</a>
      </div>
    </div>
  </div>
</div>
