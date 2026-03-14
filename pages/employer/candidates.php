<?php
/**
 * Purpose: Employer candidates page that loads filters, candidate cards, and student skill chips for internship applicants.
 * Tables/columns used: Indirectly uses application(application_id, internship_id, student_id, status, compatibility_score, application_date), internship(internship_id, employer_id, title), student(student_id, first_name, last_name, program, year_level, internship_readiness_score), student_skill(student_id, skill_id, verified), skill(skill_id, skill_name).
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/candidates/data.php';

$employerId = (int)($_SESSION['employer_id'] ?? ($userId ?? 0));
$errorMessage = '';

$currentFilters = [
  'search' => trim((string)($_REQUEST['search'] ?? '')),
  'position' => trim((string)($_REQUEST['position'] ?? '')),
  'status' => trim((string)($_REQUEST['status'] ?? '')),
  'sort' => trim((string)($_REQUEST['sort'] ?? 'match')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employerId > 0) {
  $action = trim((string)($_POST['action'] ?? ''));
  $applicationId = (int)($_POST['application_id'] ?? 0);

  try {
    if ($action === 'change_status') {
      $nextStatus = trim((string)($_POST['next_status'] ?? ''));
      $result = candidates_update_application_status($pdo, $employerId, $applicationId, $nextStatus);

      if (!empty($result['success'])) {
        $_SESSION['status'] = 'Candidate status updated to ' . candidates_normalize_status($nextStatus) . '.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to update candidate status.');
      }
    } elseif ($action === 'schedule_interview') {
      $result = candidates_schedule_interview($pdo, $employerId, $applicationId, $_POST);

      if (!empty($result['success'])) {
        $_SESSION['status'] = 'Interview scheduled successfully.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to schedule interview.');
      }
    }
  } catch (Throwable $e) {
    $errorMessage = 'Action failed. Please try again.';
  }

  if ($errorMessage === '') {
    $queryString = http_build_query([
      'page' => 'employer/candidates',
      'search' => (string)$currentFilters['search'],
      'position' => (string)$currentFilters['position'],
      'status' => (string)$currentFilters['status'],
      'sort' => (string)$currentFilters['sort'],
    ]);
    header('Location: ' . $baseUrl . '/layout.php?' . $queryString);
    exit;
  }
}

$candidateData = [
  'candidates' => [],
  'skills_by_student' => [],
  'positions' => [],
  'statuses' => [],
  'selected' => [
    'search' => '',
    'position' => '',
    'status' => '',
    'sort' => 'match',
  ],
];

if ($employerId > 0) {
  try {
    $candidateData = getEmployerCandidatesData($pdo, $employerId, [
      'search' => $currentFilters['search'],
      'position' => $currentFilters['position'],
      'status' => $currentFilters['status'],
      'sort' => $currentFilters['sort'],
    ]);
  } catch (Throwable $e) {
    $candidateData = $candidateData;
  }
}

$candidates = $candidateData['candidates'];
$skillsByStudent = $candidateData['skills_by_student'];
$positions = $candidateData['positions'];
$statuses = $candidateData['statuses'];
$selected = $candidateData['selected'];

$pipelineStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Candidates</h2>
    <p class="page-subtitle">Review and rank applicants for your internship positions.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
    <?php echo dashboard_escape($errorMessage); ?>
  </div>
<?php endif; ?>

<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row">
  <input type="hidden" name="page" value="employer/candidates">

  <div class="topbar-search" style="flex:1;max-width:300px">
    <i class="fas fa-search"></i>
    <input type="text" name="search" placeholder="Search candidates..." value="<?php echo dashboard_escape($selected['search']); ?>">
  </div>

  <select class="filter-select" name="position" onchange="this.form.submit()">
    <option value="">All Positions</option>
    <?php foreach ($positions as $position): ?>
      <option value="<?php echo dashboard_escape($position); ?>" <?php echo $selected['position'] === $position ? 'selected' : ''; ?>><?php echo dashboard_escape($position); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach ($statuses as $status): ?>
      <option value="<?php echo dashboard_escape($status); ?>" <?php echo $selected['status'] === $status ? 'selected' : ''; ?>><?php echo dashboard_escape(dashboard_status_label($status)); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="sort" onchange="this.form.submit()">
    <option value="match" <?php echo $selected['sort'] === 'match' ? 'selected' : ''; ?>>Sort by Match %</option>
    <option value="date" <?php echo $selected['sort'] === 'date' ? 'selected' : ''; ?>>Sort by Date</option>
    <option value="name" <?php echo $selected['sort'] === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
  </select>

  <button class="btn btn-primary btn-sm" type="submit">Apply</button>
  <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates">Reset</a>
</form>

<div class="cards-grid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
  <?php if (!empty($candidates)): ?>
    <?php foreach ($candidates as $candidate): ?>
      <?php
      $studentId = (int)($candidate['student_id'] ?? 0);
      $firstName = (string)($candidate['first_name'] ?? '');
      $lastName = (string)($candidate['last_name'] ?? '');
      $fullName = trim($firstName . ' ' . $lastName);
      $initialsText = dashboard_initials($firstName, $lastName);
      $program = (string)($candidate['program'] ?? 'N/A');
      $yearLevel = (string)($candidate['year_level'] ?? 'N/A');
      $score = $candidate['compatibility_score'];
      $scoreText = is_numeric($score) ? ((int)round((float)$score) . '%') : 'N/A';
      $readiness = $candidate['internship_readiness_score'];
      $readinessText = is_numeric($readiness) ? ((int)round((float)$readiness) . '/100') : 'N/A';
      $statusRaw = (string)($candidate['status'] ?? 'pending');
      $statusCanonical = candidates_normalize_status($statusRaw);
      $pipelineFlow = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
      $chipSkills = $skillsByStudent[$studentId] ?? [];
      $skillsTextList = [];
      foreach ($chipSkills as $chip) {
        $skillsTextList[] = (string)($chip['skill_name'] ?? '');
      }
      $skillsText = implode(', ', array_filter($skillsTextList));
      ?>

      <div class="candidate-card">
        <div class="candidate-header">
          <div class="topbar-avatar" style="width:48px;height:48px;font-size:.9rem"><?php echo dashboard_escape($initialsText); ?></div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.95rem"><?php echo dashboard_escape($fullName); ?></div>
            <div style="font-size:.78rem;color:#999"><?php echo dashboard_escape($program); ?> — <?php echo dashboard_escape($yearLevel); ?></div>
          </div>
          <div class="match-badge"><?php echo dashboard_escape($scoreText); ?></div>
        </div>

        <div style="font-size:.82rem;color:#666;margin:10px 0">Applied for <strong><?php echo dashboard_escape($candidate['internship_title'] ?? 'N/A'); ?></strong></div>

        <div style="margin:6px 0 10px;">
          <div style="font-size:.72rem;color:#777;margin-bottom:6px;">Current Stage: <strong><?php echo dashboard_escape($statusCanonical); ?></strong></div>
          <div style="display:flex;gap:5px;flex-wrap:wrap;">
            <?php foreach ($pipelineFlow as $stage): ?>
              <?php
              $isActiveStage = $stage === $statusCanonical;
              $stageStyle = $isActiveStage
                ? 'background:rgba(139,0,0,.1);color:#8b0000;border:1px solid rgba(139,0,0,.25);'
                : 'background:rgba(0,0,0,.04);color:#666;border:1px solid rgba(0,0,0,.08);';
              ?>
              <span style="font-size:.68rem;padding:3px 8px;border-radius:999px;<?php echo $stageStyle; ?>"><?php echo dashboard_escape($stage); ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="job-card-skills">
          <?php if (!empty($chipSkills)): ?>
            <?php foreach ($chipSkills as $chip): ?>
              <?php if (!empty($chip['verified'])): ?>
                <span class="skill-chip match"><?php echo dashboard_escape($chip['skill_name']); ?> ✓</span>
              <?php else: ?>
                <span class="skill-chip gap"><?php echo dashboard_escape($chip['skill_name']); ?> ↑</span>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <span class="skill-chip gap">No skills listed</span>
          <?php endif; ?>
        </div>

        <div style="display:flex;gap:6px;margin-top:12px;font-size:.78rem">
          <span style="background:rgba(16,185,129,.1);color:#10B981;padding:4px 10px;border-radius:50px">Year: <?php echo dashboard_escape($yearLevel); ?></span>
          <span style="background:rgba(6,182,212,.1);color:#06B6D4;padding:4px 10px;border-radius:50px">Readiness: <?php echo dashboard_escape($readinessText); ?></span>
        </div>

        <div style="display:flex;gap:8px;margin-top:14px">
          <form method="post" class="candidate-status-form" style="flex:2" data-application-id="<?php echo (int)($candidate['application_id'] ?? 0); ?>" data-current-status="<?php echo dashboard_escape($statusCanonical); ?>">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="application_id" value="<?php echo (int)($candidate['application_id'] ?? 0); ?>">
            <input type="hidden" name="search" value="<?php echo dashboard_escape($selected['search']); ?>">
            <input type="hidden" name="position" value="<?php echo dashboard_escape($selected['position']); ?>">
            <input type="hidden" name="status" value="<?php echo dashboard_escape($selected['status']); ?>">
            <input type="hidden" name="sort" value="<?php echo dashboard_escape($selected['sort']); ?>">
            <select name="next_status" class="btn btn-primary btn-sm" style="width:100%;text-align:left;" onchange="handleStatusChange(this)">
              <?php foreach ($pipelineStatuses as $pipelineStatus): ?>
                <option value="<?php echo dashboard_escape($pipelineStatus); ?>" <?php echo $statusCanonical === $pipelineStatus ? 'selected' : ''; ?>><?php echo dashboard_escape($pipelineStatus); ?></option>
              <?php endforeach; ?>
            </select>
          </form>

          <button
            class="btn btn-ghost btn-sm"
            style="flex:none;white-space:nowrap"
            type="button"
            onclick="openProfileModal(this)"
            data-full-name="<?php echo dashboard_escape($fullName); ?>"
            data-program="<?php echo dashboard_escape($program); ?>"
            data-year-level="<?php echo dashboard_escape($yearLevel); ?>"
            data-readiness="<?php echo dashboard_escape($readinessText); ?>"
            data-match="<?php echo dashboard_escape($scoreText); ?>"
            data-status="<?php echo dashboard_escape($statusCanonical); ?>"
            data-position="<?php echo dashboard_escape((string)($candidate['internship_title'] ?? 'N/A')); ?>"
            data-skills="<?php echo dashboard_escape($skillsText !== '' ? $skillsText : 'No skills listed'); ?>"
          >View Profile</button>

          <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444" type="button" onclick="quickRejectCandidate(<?php echo (int)($candidate['application_id'] ?? 0); ?>, '<?php echo dashboard_escape($selected['search']); ?>', '<?php echo dashboard_escape($selected['position']); ?>', '<?php echo dashboard_escape($selected['status']); ?>', '<?php echo dashboard_escape($selected['sort']); ?>')"><i class="fas fa-times"></i></button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="candidate-card" style="grid-column:1/-1;text-align:center;">
      <div style="font-weight:700;font-size:.95rem;margin-bottom:8px">No candidates found</div>
      <div style="font-size:.82rem;color:#666;">Try adjusting your filters or wait for new applications.</div>
    </div>
  <?php endif; ?>
</div>

<div id="profileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1200;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:12px;max-width:520px;width:100%;padding:16px;max-height:90vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:1rem;">Applicant Profile</h3>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeProfileModal()">Close</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.85rem;">
      <div><strong>Name:</strong> <span id="profileName">—</span></div>
      <div><strong>Status:</strong> <span id="profileStatus">—</span></div>
      <div><strong>Program:</strong> <span id="profileProgram">—</span></div>
      <div><strong>Year:</strong> <span id="profileYear">—</span></div>
      <div><strong>Readiness:</strong> <span id="profileReadiness">—</span></div>
      <div><strong>Match:</strong> <span id="profileMatch">—</span></div>
    </div>
    <div style="margin-top:10px;font-size:.85rem;"><strong>Applied Position:</strong> <span id="profilePosition">—</span></div>
    <div style="margin-top:10px;font-size:.85rem;"><strong>Skills:</strong> <span id="profileSkills">—</span></div>
  </div>
</div>

<div id="interviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1250;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:12px;max-width:560px;width:100%;padding:16px;max-height:90vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:1rem;">Schedule Interview</h3>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeInterviewModal()">Close</button>
    </div>

    <form method="post" id="interviewScheduleForm" style="display:flex;flex-direction:column;gap:12px;">
      <input type="hidden" name="action" value="schedule_interview">
      <input type="hidden" name="application_id" id="interviewApplicationId" value="0">
      <input type="hidden" name="search" value="<?php echo dashboard_escape($selected['search']); ?>">
      <input type="hidden" name="position" value="<?php echo dashboard_escape($selected['position']); ?>">
      <input type="hidden" name="status" value="<?php echo dashboard_escape($selected['status']); ?>">
      <input type="hidden" name="sort" value="<?php echo dashboard_escape($selected['sort']); ?>">

      <div class="form-group">
        <label class="form-label">Interview Date & Time</label>
        <input class="form-input" type="datetime-local" name="interview_date" required>
      </div>

      <div class="form-group">
        <label class="form-label">Mode</label>
        <select class="form-input" name="interview_mode" id="interviewMode" onchange="toggleMeetingLabel()" required>
          <option value="Online">Online</option>
          <option value="In-Person">In-Person</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" id="meetingLabel">Meeting Link</label>
        <input class="form-input" type="text" name="meeting_link" id="meetingInput" placeholder="Paste meeting link" required>
      </div>

      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeInterviewModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Interview</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="quickRejectForm" style="display:none;">
  <input type="hidden" name="action" value="change_status">
  <input type="hidden" name="application_id" id="quickRejectApplicationId" value="0">
  <input type="hidden" name="next_status" value="Rejected">
  <input type="hidden" name="search" id="quickRejectSearch" value="">
  <input type="hidden" name="position" id="quickRejectPosition" value="">
  <input type="hidden" name="status" id="quickRejectStatus" value="">
  <input type="hidden" name="sort" id="quickRejectSort" value="">
</form>

<script>
function handleStatusChange(selectEl) {
  var form = selectEl.closest('form');
  if (!form) return;

  var selected = selectEl.value || 'Pending';
  if (selected === 'Interview Scheduled') {
    var appIdInput = form.querySelector('input[name="application_id"]');
    var appId = appIdInput ? parseInt(appIdInput.value || '0', 10) : 0;
    if (appId > 0) {
      openInterviewModal(appId);
    }

    var currentStatus = form.getAttribute('data-current-status') || 'Pending';
    selectEl.value = currentStatus;
    return;
  }

  form.submit();
}

function openProfileModal(button) {
  document.getElementById('profileName').textContent = button.getAttribute('data-full-name') || '—';
  document.getElementById('profileStatus').textContent = button.getAttribute('data-status') || '—';
  document.getElementById('profileProgram').textContent = button.getAttribute('data-program') || '—';
  document.getElementById('profileYear').textContent = button.getAttribute('data-year-level') || '—';
  document.getElementById('profileReadiness').textContent = button.getAttribute('data-readiness') || '—';
  document.getElementById('profileMatch').textContent = button.getAttribute('data-match') || '—';
  document.getElementById('profilePosition').textContent = button.getAttribute('data-position') || '—';
  document.getElementById('profileSkills').textContent = button.getAttribute('data-skills') || '—';
  document.getElementById('profileModal').style.display = 'flex';
}

function closeProfileModal() {
  document.getElementById('profileModal').style.display = 'none';
}

function openInterviewModal(applicationId) {
  document.getElementById('interviewApplicationId').value = applicationId;
  document.getElementById('interviewModal').style.display = 'flex';
  toggleMeetingLabel();
}

function closeInterviewModal() {
  document.getElementById('interviewModal').style.display = 'none';
}

function toggleMeetingLabel() {
  var mode = document.getElementById('interviewMode').value;
  var label = document.getElementById('meetingLabel');
  var input = document.getElementById('meetingInput');
  if (mode === 'In-Person') {
    label.textContent = 'Venue';
    input.placeholder = 'Enter interview venue';
  } else {
    label.textContent = 'Meeting Link';
    input.placeholder = 'Paste meeting link';
  }
}

function quickRejectCandidate(applicationId, search, position, status, sort) {
  if (!confirm('Reject this candidate?')) {
    return;
  }

  document.getElementById('quickRejectApplicationId').value = applicationId;
  document.getElementById('quickRejectSearch').value = search || '';
  document.getElementById('quickRejectPosition').value = position || '';
  document.getElementById('quickRejectStatus').value = status || '';
  document.getElementById('quickRejectSort').value = sort || 'match';
  document.getElementById('quickRejectForm').submit();
}
</script>
