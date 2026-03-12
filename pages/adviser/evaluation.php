<div class="page-header">
  <div>
    <h2 class="page-title">Student Evaluations</h2>
    <p class="page-subtitle">Review and grade student internship performance.</p>
  </div>
</div>

<div class="filter-row" style="margin-bottom:20px">
  <select class="filter-select"><option>All Departments</option><option>CICS</option><option>COE</option><option>CBA</option></select>
  <select class="filter-select"><option>All Status</option><option>Graded</option><option>Pending</option></select>
</div>

<div class="app-table-wrap">
  <table class="app-table">
    <thead>
      <tr>
        <th>Student</th>
        <th>Company</th>
        <th>Hours</th>
        <th>Employer Rating</th>
        <th>Final Grade</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:34px;height:34px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.75rem;color:#0369a1">JD</div>
            <div><div style="font-weight:600">Juan Dela Cruz</div><div style="font-size:.75rem;color:var(--text-lighter)">BSCS &middot; 4th Year</div></div>
          </div>
        </td>
        <td>Google PH</td>
        <td>480 / 480</td>
        <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.9</span></td>
        <td>
          <span class="status-badge badge-active">1.25</span>
        </td>
        <td><span class="status-badge badge-active">Graded</span></td>
        <td><button class="btn-outline btn-sm"><i class="fas fa-eye"></i> View</button></td>
      </tr>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:34px;height:34px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.75rem;color:#92400e">MR</div>
            <div><div style="font-weight:600">Maria Reyes</div><div style="font-size:.75rem;color:var(--text-lighter)">BSIT &middot; 4th Year</div></div>
          </div>
        </td>
        <td>Accenture PH</td>
        <td>450 / 480</td>
        <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.5</span></td>
        <td>
          <span class="status-badge badge-active">1.50</span>
        </td>
        <td><span class="status-badge badge-active">Graded</span></td>
        <td><button class="btn-outline btn-sm"><i class="fas fa-eye"></i> View</button></td>
      </tr>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:34px;height:34px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.75rem;color:#3730a3">AS</div>
            <div><div style="font-weight:600">Ana Santos</div><div style="font-size:.75rem;color:var(--text-lighter)">BSCS &middot; 3rd Year</div></div>
          </div>
        </td>
        <td>Grab PH</td>
        <td>320 / 480</td>
        <td><span style="color:var(--text-lighter)">—</span></td>
        <td>
          <span class="status-badge badge-pending">Pending</span>
        </td>
        <td><span class="status-badge badge-pending">In Progress</span></td>
        <td><button class="btn-outline btn-sm" disabled>Awaiting</button></td>
      </tr>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:34px;height:34px;border-radius:50%;background:#fce7f3;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.75rem;color:#9d174d">PL</div>
            <div><div style="font-weight:600">Paolo Lim</div><div style="font-size:.75rem;color:var(--text-lighter)">BSIT &middot; 4th Year</div></div>
          </div>
        </td>
        <td>Accenture PH</td>
        <td>480 / 480</td>
        <td><span style="color:#F59E0B"><i class="fas fa-star"></i> 4.2</span></td>
        <td>
          <span class="status-badge badge-pending">Pending</span>
        </td>
        <td><span class="status-badge badge-pending">Needs Grading</span></td>
        <td><button class="btn-primary btn-sm"><i class="fas fa-pen"></i> Grade</button></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Grading Form -->
<div class="panel-card" style="margin-top:24px">
  <div class="panel-card-header"><h3>Grade Student</h3></div>
  <form style="display:flex;flex-direction:column;gap:16px" onsubmit="event.preventDefault()">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Student</label>
        <select class="form-input"><option>Select student...</option><option>Paolo Lim — BSIT</option></select>
      </div>
      <div>
        <label class="form-label">Final Grade</label>
        <select class="form-input"><option>Select grade...</option><option>1.00</option><option>1.25</option><option>1.50</option><option>1.75</option><option>2.00</option><option>2.25</option><option>2.50</option><option>2.75</option><option>3.00</option><option>5.00 (Failed)</option></select>
      </div>
    </div>

    <div>
      <label class="form-label">Performance Summary</label>
      <textarea class="form-input" rows="3" placeholder="Brief evaluation summary..."></textarea>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
      <div>
        <label class="form-label">Technical Skills</label>
        <select class="form-input"><option>5 — Excellent</option><option>4 — Very Good</option><option>3 — Good</option><option>2 — Fair</option><option>1 — Poor</option></select>
      </div>
      <div>
        <label class="form-label">Work Ethic</label>
        <select class="form-input"><option>5 — Excellent</option><option>4 — Very Good</option><option>3 — Good</option><option>2 — Fair</option><option>1 — Poor</option></select>
      </div>
      <div>
        <label class="form-label">Communication</label>
        <select class="form-input"><option>5 — Excellent</option><option>4 — Very Good</option><option>3 — Good</option><option>2 — Fair</option><option>1 — Poor</option></select>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="button" class="btn-outline">Cancel</button>
      <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Submit Grade</button>
    </div>
  </form>
</div>
