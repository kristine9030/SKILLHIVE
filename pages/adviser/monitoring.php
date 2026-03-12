<div class="page-header">
  <div>
    <h2 class="page-title">OJT Monitoring</h2>
    <p class="page-subtitle">Track student internship hours and daily accomplishments.</p>
  </div>
</div>

<!-- Filters -->
<div class="filter-row">
  <div class="topbar-search" style="flex:1;max-width:250px">
    <i class="fas fa-search"></i>
    <input type="text" placeholder="Search students...">
  </div>
  <select class="filter-select">
    <option>All Companies</option>
    <option>Google PH</option>
    <option>Accenture PH</option>
    <option>Shopee PH</option>
    <option>Grab PH</option>
  </select>
  <select class="filter-select">
    <option>All Progress</option>
    <option>On Track</option>
    <option>Progressing</option>
    <option>Behind</option>
  </select>
</div>

<!-- Student Monitoring Cards -->
<div class="cards-grid" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))">
  <div class="panel-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem">JD</div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.95rem">Juan dela Cruz</div>
        <div style="font-size:.78rem;color:#999">Google PH — UI/UX Design</div>
      </div>
      <span class="status-pill status-accepted">On Track</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
      <span>248 / 400 hours</span><span style="font-weight:700;color:#06B6D4">62%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:62%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div></div>
    <div style="margin-top:12px;font-size:.78rem;color:#999">
      <strong>Latest log:</strong> Developed login module with session management (Jan 20)
    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-eye"></i> View Logs</button>
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-comment"></i> Feedback</button>
    </div>
  </div>

  <div class="panel-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem;background:#10B981">MR</div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.95rem">Maria Reyes</div>
        <div style="font-size:.78rem;color:#999">Accenture PH — Software Eng.</div>
      </div>
      <span class="status-pill status-shortlisted">Progressing</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
      <span>180 / 400 hours</span><span style="font-weight:700;color:#F59E0B">45%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:45%;background:linear-gradient(90deg,#F59E0B,#10B981)"></div></div>
    <div style="margin-top:12px;font-size:.78rem;color:#999">
      <strong>Latest log:</strong> Created wireframes for dashboard redesign (Jan 19)
    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-eye"></i> View Logs</button>
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-comment"></i> Feedback</button>
    </div>
  </div>

  <div class="panel-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem;background:#F59E0B">AL</div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.95rem">Andre Lopez</div>
        <div style="font-size:.78rem;color:#999">Shopee PH — Data Analyst</div>
      </div>
      <span class="status-pill status-pending">Behind</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
      <span>90 / 400 hours</span><span style="font-weight:700;color:#EF4444">22%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:22%;background:linear-gradient(90deg,#EF4444,#F59E0B)"></div></div>
    <div style="margin-top:12px;font-size:.78rem;color:#999">
      <strong>Latest log:</strong> Unit testing of API endpoints (Jan 18)
    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-eye"></i> View Logs</button>
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-comment"></i> Feedback</button>
    </div>
  </div>

  <div class="panel-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="topbar-avatar" style="width:44px;height:44px;font-size:.85rem;background:#6F42C1">KP</div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.95rem">Kristine Padilla</div>
        <div style="font-size:.78rem;color:#999">Grab PH — Mobile Dev</div>
      </div>
      <span class="status-pill status-accepted">On Track</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px">
      <span>320 / 400 hours</span><span style="font-weight:700;color:#10B981">80%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:80%;background:linear-gradient(90deg,#10B981,#06B6D4)"></div></div>
    <div style="margin-top:12px;font-size:.78rem;color:#999">
      <strong>Latest log:</strong> Sprint review and demo presentation (Jan 20)
    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-eye"></i> View Logs</button>
      <button class="btn btn-ghost btn-sm" style="flex:1"><i class="fas fa-comment"></i> Feedback</button>
    </div>
  </div>
</div>
