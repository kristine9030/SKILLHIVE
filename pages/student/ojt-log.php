<div class="page-header">
  <div>
    <h2 class="page-title">OJT Tracker</h2>
    <p class="page-subtitle">Log your daily accomplishments and track internship hours.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('logModal').style.display='flex'"><i class="fas fa-plus"></i> Log Entry</button>
</div>

<!-- Hour Stats -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-clock" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">248</div><div class="stat-card-label">Hours Logged</div></div>
    <div class="stat-card-trend neutral">of 400 target</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-calendar-day" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">32</div><div class="stat-card-label">Days Present</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-tasks" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">18</div><div class="stat-card-label">Tasks Completed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-percentage" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num">62%</div><div class="stat-card-label">Progress</div></div>
  </div>
</div>

<!-- Progress Bar -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Overall Progress</h3></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <span style="font-size:.85rem;color:#666">248 of 400 hours completed</span>
    <span style="font-weight:700;color:#06B6D4">62%</span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" style="width:62%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.75rem;color:#999">
    <span>Started: Nov 4, 2024</span>
    <span>Target: Mar 28, 2025</span>
  </div>
</div>

<!-- Recent Logs -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Activity Log</h3></div>
  <div class="app-table-wrap">
    <table class="app-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Task / Activity</th>
          <th>Hours</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Jan 20, 2025</td>
          <td>Developed login module with session management</td>
          <td>8</td>
          <td><span class="status-pill status-accepted">Approved</span></td>
        </tr>
        <tr>
          <td>Jan 19, 2025</td>
          <td>Created wireframes for dashboard redesign</td>
          <td>7</td>
          <td><span class="status-pill status-accepted">Approved</span></td>
        </tr>
        <tr>
          <td>Jan 18, 2025</td>
          <td>Unit testing of API endpoints</td>
          <td>6</td>
          <td><span class="status-pill status-shortlisted">Reviewing</span></td>
        </tr>
        <tr>
          <td>Jan 17, 2025</td>
          <td>Bug fixing on payment gateway integration</td>
          <td>8</td>
          <td><span class="status-pill status-accepted">Approved</span></td>
        </tr>
        <tr>
          <td>Jan 16, 2025</td>
          <td>Team meeting + sprint planning for v2.0</td>
          <td>4</td>
          <td><span class="status-pill status-accepted">Approved</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Weekly Chart Placeholder -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Weekly Hours</h3></div>
  <div style="display:flex;align-items:flex-end;gap:8px;height:140px;padding:10px 0">
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:85%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Mon</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:100%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Tue</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:75%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Wed</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:100%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Thu</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:50%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Fri</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:#e5e5e5;height:0%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Sat</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:#e5e5e5;height:0%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Sun</div>
    </div>
  </div>
</div>