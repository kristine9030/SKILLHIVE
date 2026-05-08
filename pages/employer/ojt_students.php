<?php
/**
 * Purpose: Employer OJT Students management page
 * Shows all OJT students under the employer's company with edit capabilities
 * Tables used: ojt_record, internship, student
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';
require_once __DIR__ . '/ojt_students/data.php';
require_once __DIR__ . '/ojt_students/update.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$resolvedEmployerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);
$employerId = (int)($resolvedEmployerId ?? 0);

$employerIdCandidates = [];
if ($employerId > 0) {
  $employerIdCandidates[] = $employerId;
}

$sessionEmployerId = isset($_SESSION['employer_id']) ? (int)$_SESSION['employer_id'] : 0;
if ($sessionEmployerId > 0) {
  $employerIdCandidates[] = $sessionEmployerId;
}

$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($sessionUserId > 0) {
  $employerIdCandidates[] = $sessionUserId;
}

if ($employerId <= 0 && !empty($userEmail)) {
  try {
    $stmtEmployer = $pdo->prepare('SELECT employer_id FROM employer WHERE email = :email LIMIT 1');
    $stmtEmployer->execute([':email' => (string)$userEmail]);
    $col = $stmtEmployer->fetchColumn();
    $employerId = (int)($col ? $col : 0);
    if ($employerId > 0) {
      $employerIdCandidates[] = $employerId;
    }
  } catch (Throwable $e) {
    $employerId = 0;
  }
}

$employerIdCandidates = array_values(array_unique(array_filter(array_map('intval', $employerIdCandidates))));
if (!empty($employerIdCandidates)) {
  $bestEmployerId = 0;
  $bestCount = -1;

  try {
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM internship WHERE employer_id = :employer_id');
    foreach ($employerIdCandidates as $candidateEmployerId) {
      $stmtCount->execute([':employer_id' => $candidateEmployerId]);
      $count = (int)$stmtCount->fetchColumn();
      if ($count > $bestCount) {
        $bestCount = $count;
        $bestEmployerId = $candidateEmployerId;
      }
    }
  } catch (Throwable $e) {
    $bestEmployerId = $employerId;
  }

  if ($bestEmployerId > 0) {
    $employerId = $bestEmployerId;
  }
}

if ($employerId > 0 && $sessionEmployerId <= 0) {
  $_SESSION['employer_id'] = $employerId;
}

$verificationStatus = getEmployerVerificationStatus($pdo, (int)$employerId) ?? (string)($_SESSION['verification_status'] ?? '');
$_SESSION['verification_status'] = $verificationStatus;
if (!isEmployerApproved($verificationStatus)) {
  $_SESSION['status'] = 'Your employer account is pending admin verification. OJT Students module is locked until approval.';
  header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
  exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employerId > 0) {
  $recordId = (int)($_POST['record_id'] ?? 0);
  $updateData = [];

  if (isset($_POST['internship_id']) && $_POST['internship_id'] !== '') {
    $updateData['internship_id'] = (int)$_POST['internship_id'];
  }
  if (isset($_POST['completion_status']) && $_POST['completion_status'] !== '') {
    $updateData['completion_status'] = $_POST['completion_status'];
  }
  if (isset($_POST['start_date']) && $_POST['start_date'] !== '') {
    $updateData['start_date'] = $_POST['start_date'];
  }
  if (isset($_POST['end_date']) && $_POST['end_date'] !== '') {
    $updateData['end_date'] = $_POST['end_date'];
  }

  $result = updateOjtStudentDetails($pdo, $employerId, $recordId, $updateData);
  if ($result['success']) {
    $message = 'Record updated successfully.';
    $messageType = 'success';
  } else {
    $message = $result['error'] ?? 'Failed to update record.';
    $messageType = 'error';
  }
}

$ojtStudents = [];
$internships = [];
$kpi = ['total' => 0, 'hours_completed' => 0, 'ongoing' => 0, 'completed' => 0];

if ($employerId > 0) {
  $ojtStudents = getEmployerOjtStudents($pdo, $employerId);
  $internships = getEmployerInternships($pdo, $employerId);

  foreach ($ojtStudents as $student) {
    $kpi['total']++;
    $kpi['hours_completed'] += (int)$student['hours_completed'];
    if ($student['completion_status'] === 'Ongoing') $kpi['ongoing']++;
    if ($student['completion_status'] === 'Completed') $kpi['completed']++;
  }
}
?>

<?php if ($message !== ''): ?>
  <div class="panel-card" style="margin-bottom:16px;border-left:4px solid <?php echo $messageType === 'success' ? '#10b981' : '#ef4444'; ?>;">
    <div style="font-size:.85rem;color:#666;">
      <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'triangle-exclamation'; ?>" style="color:<?php echo $messageType === 'success' ? '#10b981' : '#ef4444'; ?>;margin-right:6px"></i>
      <?php echo dashboard_escape($message); ?>
    </div>
  </div>
<?php endif; ?>

<style>

/* ── ojt Banner ─────────────────────────────────── */
.ojt-banner {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 32px 36px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  gap: 20px;
  background: linear-gradient(120deg, #0d5f58 0%, #0f766e 45%, #134e4a 100%);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.22), 0 2px 6px rgba(0,0,0,0.08);
  border: 1px solid rgba(255,255,255,0.08);
  transition: all 0.35s cubic-bezier(.4,0,.2,1);
}

.ojt-banner .bnr-art-left {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, #fff 0%, transparent 70%);
  top: -80px;
  left: -60px;
  opacity: 0.12;
}

.ojt-banner .bnr-art-right {
  position: absolute;
  pointer-events: none;
  border-radius: 50%;
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, #5eead4 0%, transparent 70%);
  bottom: -90px;
  right: 60px;
  opacity: 0.18;
}

.ojt-banner .bnr-body {
  position: relative;
  z-index: 1;
  flex: 1;
}

.ojt-banner .bnr-meta {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.55);
  margin-bottom: 6px;
}

.ojt-banner .bnr-heading {
  font-size: 26px;
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 6px;
  line-height: 1.2;
}

.ojt-banner .bnr-sub {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  line-height: 1.5;
  max-width: 520px;
  margin-bottom: 18px;
}

.ojt-banner .bnr-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ojt-banner .bnr-pill {
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

.ojt-banner .bnr-collapse-btn {
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

.ojt-banner .bnr-collapse-btn:hover {
  background: rgba(255,255,255,0.22);
  color: #fff;
}

.ojt-banner.bnr-collapsed {
  display: none;
}

.ojt-restore-bar {
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
  user-select: none;
}

.ojt-restore-bar:hover { background: #f9fafb; }

.ojt-restore-bar .bnr-restore-date {
  font-size: 12px;
  font-weight: 400;
  color: #9ca3af;
  margin-left: auto;
}

.ojt-restore-bar.bnr-visible { display: flex; }

@media (max-width: 768px) {
  .ojt-banner { padding: 24px 20px 20px; }
  .ojt-banner .bnr-heading { font-size: 20px; }
  .ojt-banner .bnr-sub { display: none; }
}

.search-filter-container {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.search-filter-box {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 250px;
  position: relative;
  display: flex;
  align-items: center;
}

.search-input-wrapper i {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  font-size: 14px;
}

.search-input {
  flex: 1;
  padding: 10px 12px 10px 36px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  font-size: 13px;
  background: #f9fafb;
}

.search-input::placeholder {
  color: #9ca3af;
}

.search-input:focus {
  outline: none;
  border-color: #0f766e;
  background: #fff;
  box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.2);
}

.filter-dropdown {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  font-size: 13px;
  background: #f9fafb;
  cursor: pointer;
  color: #374151;
  min-width: 150px;
}

.filter-dropdown:focus {
  outline: none;
  border-color: #0f766e;
  background: #fff;
  box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.2);
}

.filter-buttons {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn-apply {
  background: #111827;
  color: #fff;
  border: none;
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-apply:hover {
  background: #1f2937;
  transform: translateY(-1px);
}

.btn-reset {
  background: transparent;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 10px 16px;
  border-radius: 12px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-reset:hover {
  background: #f9fafb;
  border-color: #9ca3af;
}

.editable-select {
  padding: 6px 8px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  width: 100%;
  background: #fff;
  cursor: pointer;
  disabled: true;
}

.editable-select:focus {
  outline: none;
  border-color: #0f766e;
  box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.2);
}

.editable-select:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
  color: #6b7280;
}

.read-only-cell {
  padding: 6px 8px;
  color: #6b7280;
  font-weight: 500;
}

.btn-edit-row {
  background: #111827;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
  margin-right: 4px;
}

.btn-edit-row:hover {
  background: #1f2937;
  transform: translateY(-1px);
}

.btn-save-row {
  background: #10b981;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
  margin-right: 4px;
  display: none;
}

.btn-save-row:hover {
  background: #059669;
  transform: translateY(-1px);
}

.btn-cancel-row {
  background: #ef4444;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
  display: none;
}

.btn-cancel-row:hover {
  background: #dc2626;
  transform: translateY(-1px);
}

.ojt-row.edit-mode .btn-edit-row {
  display: none;
}

.ojt-row.edit-mode .btn-save-row {
  display: inline-block;
}

.ojt-row.edit-mode .btn-cancel-row {
  display: inline-block;
}

.ojt-row.edit-mode .editable-select {
  background: #fff;
  cursor: pointer;
}

.no-results {
  text-align: center;
  padding: 40px;
  color: #6b7280;
}

.no-results i {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.3;
  display: block;
}

.no-results-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 8px;
}

.no-results-desc {
  font-size: 14px;
}

@media (max-width: 768px) {
  .search-filter-box {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-input-wrapper {
    width: 100%;
  }
  
  .filter-buttons {
    width: 100%;
    gap: 8px;
  }
}
</style>

<div class="ojt-banner" id="ojtBanner">
  <div class="bnr-art-left"></div>
  <div class="bnr-art-right"></div>
  <div class="bnr-body">
    <div class="bnr-meta"><?php echo date('l, j F Y'); ?></div>
    <div class="bnr-heading"><?php echo 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . '!'; ?></div>
    <div class="bnr-sub">Manage your OJT students, track their progress, and update their internship details.</div>
    <div class="bnr-pills">
      <span class="bnr-pill"><i class="fas fa-user-graduate"></i> Active Interns</span>
    </div>
  </div>
  <button type="button" class="bnr-collapse-btn" onclick="toggleOjtBanner()" title="Collapse banner">
    <i class="fas fa-chevron-up"></i>
  </button>
</div>
<div class="ojt-restore-bar" id="ojtRestoreBar" onclick="toggleOjtBanner()">
  <span><i class="fas fa-graduation-cap" style="margin-right:6px;color:#0f766e;"></i>OJT Students</span>
  <span class="bnr-restore-date"><?php echo date('l, j F'); ?></span>
  <i class="fas fa-chevron-down" style="color:#9ca3af;font-size:12px;"></i>
</div>


<style>
.ojt-stat-cards {
  display: flex;
  flex-direction: row;
  gap: 14px;
  margin-bottom: 20px;
}
.ojt-stat-cards .stat-card {
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px 18px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
.ojt-stat-cards .stat-card-icon img {
  width: 44px;
  height: 44px;
  object-fit: contain;
  flex-shrink: 0;
}
.ojt-stat-cards .stat-card-info { flex: 1; min-width: 0; }
.ojt-stat-cards .stat-card-num-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ojt-stat-cards .stat-card-num {
  font-size: 22px;
  font-weight: 800;
  color: #111827;
  line-height: 1;
}
.ojt-stat-cards .stat-card-trend {
  font-size: 11px;
  font-weight: 500;
  color: #9ca3af;
}
.ojt-stat-cards .stat-card-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 3px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
@media (max-width: 900px) {
  .ojt-stat-cards { display: grid; grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
  .ojt-stat-cards { grid-template-columns: 1fr; }
}
</style>

<div class="ojt-stat-cards">
  <div class="stat-card employer-stat-postings">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Evaluation.png" alt="Total Students"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-num"><?php echo $kpi['total']; ?></div>
        <div class="stat-card-trend neutral">enrolled</div>
      </div>
      <div class="stat-card-label">Total OJT Students</div>
    </div>
  </div>
  <div class="stat-card employer-stat-applicants">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Rating.png" alt="Hours Completed"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-num"><?php echo $kpi['hours_completed']; ?></div>
        <div class="stat-card-trend neutral">total hours</div>
      </div>
      <div class="stat-card-label">Hours Completed</div>
    </div>
  </div>
  <div class="stat-card employer-stat-interviews">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Pendingg.png" alt="Ongoing"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-num"><?php echo $kpi['ongoing']; ?></div>
        <div class="stat-card-trend neutral">in progress</div>
      </div>
      <div class="stat-card-label">Ongoing</div>
    </div>
  </div>
  <div class="stat-card employer-stat-hired">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Needs%20Evaluated.png" alt="Completed"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-num"><?php echo $kpi['completed']; ?></div>
        <div class="stat-card-trend neutral">finished</div>
      </div>
      <div class="stat-card-label">Completed</div>
    </div>
  </div>
</div>

<div class="panel-card">
  <div class="panel-card-header">
    <h3><i class="fas fa-user-graduate" style="color:#12b3ac;margin-right:8px;"></i>OJT Students</h3>
  </div>
  
  <?php if (!empty($ojtStudents)): ?>
  <div class="search-filter-container">
    <div class="search-filter-box">
      <div class="search-input-wrapper">
        <i class="fas fa-search"></i>
        <input 
          type="text" 
          id="searchInput" 
          class="search-input" 
          placeholder="Search students..." 
          onkeyup="filterTable()"
        >
      </div>
      
      <select id="filterSchool" class="filter-dropdown" onchange="filterTable()">
        <option value="">All Schools</option>
        <?php
        $schools = array_unique(array_map(fn($s) => $s['school'], $ojtStudents));
        sort($schools);
        foreach ($schools as $school): ?>
          <option value="<?php echo dashboard_escape($school); ?>"><?php echo dashboard_escape($school); ?></option>
        <?php endforeach; ?>
      </select>
      
      <select id="filterDept" class="filter-dropdown" onchange="filterTable()">
        <option value="">All Departments</option>
        <?php foreach ($internships as $internship): ?>
          <option value="<?php echo (int)$internship['internship_id']; ?>"><?php echo dashboard_escape($internship['title']); ?></option>
        <?php endforeach; ?>
      </select>
      
      <div class="filter-buttons">
        <button type="button" class="btn-apply" onclick="filterTable()"><i class="fas fa-filter"></i> Apply</button>
        <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="app-table-wrap">
    <?php if (empty($ojtStudents)): ?>
      <div class="no-results">
        <i class="fas fa-user-graduate"></i>
        <div class="no-results-title">No OJT Students Found</div>
        <div class="no-results-desc">OJT students will appear here once they are assigned to your internships.</div>
      </div>
    <?php else: ?>
      <table class="app-table">
        <thead>
          <tr>
            <th>Student Name</th>
            <th>School</th>
            <th>Hours Rendered</th>
            <th>Department</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="ojtTableBody">
          <?php foreach ($ojtStudents as $student): ?>
            <?php $studentName = trim($student['first_name'] . ' ' . $student['last_name']); ?>
            <tr class="ojt-row" data-record-id="<?php echo (int)$student['record_id']; ?>" data-school="<?php echo dashboard_escape($student['school']); ?>" data-dept="<?php echo (int)$student['internship_id']; ?>" data-search-text="<?php echo dashboard_escape(strtolower($studentName . ' ' . $student['school'] . ' ' . $student['department'])); ?>">
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:32px;height:32px;border-radius:8px;background:#0f766e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;">
                    <?php echo dashboard_escape(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                  </div>
                  <span style="font-weight:500;"><?php echo dashboard_escape($studentName); ?></span>
                </div>
              </td>
              <td><?php echo dashboard_escape($student['school']); ?></td>
              <td style="text-align:center;">
                <span class="read-only-cell"><?php echo (int)$student['hours_completed']; ?> hrs</span>
              </td>
              <td>
                <select data-field="internship_id" data-original="<?php echo (int)$student['internship_id']; ?>" class="editable-select department-select" disabled>
                  <?php foreach ($internships as $internship): ?>
                    <option value="<?php echo (int)$internship['internship_id']; ?>" <?php echo (int)$internship['internship_id'] === (int)$student['internship_id'] ? 'selected' : ''; ?>>
                      <?php echo dashboard_escape($internship['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="white-space:nowrap;">
                <button type="button" class="btn-edit-row" onclick="editRow(this)"><i class="fas fa-edit"></i> Edit</button>
                <button type="button" class="btn-save-row" onclick="saveRow(this)"><i class="fas fa-save"></i> Save</button>
                <button type="button" class="btn-cancel-row" onclick="cancelEdit(this)"><i class="fas fa-times"></i> Cancel</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div id="noResultsMsg" class="no-results" style="display:none;margin-top:0;">
        <i class="fas fa-search"></i>
        <div class="no-results-title">No matches found</div>
        <div class="no-results-desc">Try adjusting your search or filters.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function filterTable() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const filterSchool = document.getElementById('filterSchool').value;
  const filterDept = document.getElementById('filterDept').value;
  const tableBody = document.getElementById('ojtTableBody');
  const rows = tableBody.querySelectorAll('.ojt-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const searchText = row.getAttribute('data-search-text');
    const school = row.getAttribute('data-school');
    const dept = row.getAttribute('data-dept');
    
    const matchSearch = searchInput === '' || searchText.includes(searchInput);
    const matchSchool = filterSchool === '' || school === filterSchool;
    const matchDept = filterDept === '' || dept === filterDept;
    
    if (matchSearch && matchSchool && matchDept) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const noResultsMsg = document.getElementById('noResultsMsg');
  if (visibleCount === 0) {
    noResultsMsg.style.display = 'block';
  } else {
    noResultsMsg.style.display = 'none';
  }
}

function resetFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('filterSchool').value = '';
  document.getElementById('filterDept').value = '';
  filterTable();
}

function editRow(button) {
  const row = button.closest('.ojt-row');
  row.classList.add('edit-mode');
  const select = row.querySelector('.department-select');
  select.disabled = false;
}

function cancelEdit(button) {
  const row = button.closest('.ojt-row');
  row.classList.remove('edit-mode');
  const select = row.querySelector('.department-select');
  select.disabled = true;
  const originalId = select.getAttribute('data-original');
  select.value = originalId;
}

function saveRow(button) {
  const row = button.closest('.ojt-row');
  const recordId = row.getAttribute('data-record-id');
  const select = row.querySelector('.department-select');
  const internshipId = select.value;
  const originalId = select.getAttribute('data-original');

  if (internshipId === originalId) {
    alert('No changes made.');
    return;
  }

  const formData = new FormData();
  formData.append('record_id', recordId);
  formData.append('internship_id', internshipId);

  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (response.ok) {
      select.setAttribute('data-original', internshipId);
      select.disabled = true;
      row.classList.remove('edit-mode');
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-save"></i> Save';
      
      const successMsg = document.createElement('div');
      successMsg.className = 'panel-card';
      successMsg.style.cssText = 'margin-bottom:16px;border-left:4px solid #10b981;';
      successMsg.innerHTML = '<div style="font-size:.85rem;color:#666;"><i class="fas fa-check-circle" style="color:#10b981;margin-right:6px;"></i>Record updated successfully.</div>';
      
      const panel = document.querySelector('.panel-card');
      panel.parentNode.insertBefore(successMsg, panel);
      setTimeout(() => successMsg.remove(), 3000);
    }
  })
  .catch(error => {
    button.disabled = false;
    button.innerHTML = '<i class="fas fa-save"></i> Save';
    alert('Error saving record. Please try again.');
    console.error('Error:', error);
  });
</script>

<script>
function toggleOjtBanner() {
  const banner = document.getElementById('ojtBanner');
  const bar    = document.getElementById('ojtRestoreBar');
  const isCollapsed = banner.classList.toggle('bnr-collapsed');
  bar.classList.toggle('bnr-visible', isCollapsed);
}
</script>