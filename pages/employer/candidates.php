<?php
/**
 * Purpose: Employer candidates page that loads filters, candidate cards, and student skill chips for internship applicants.
 * Tables/columns used: Indirectly uses application(application_id, internship_id, student_id, status, compatibility_score, application_date), internship(internship_id, employer_id, title), student(student_id, first_name, last_name, program, year_level, internship_readiness_score), student_skill(student_id, skill_id, verified), skill(skill_id, skill_name).
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';
require_once __DIR__ . '/candidates/data.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null) ?? 0;
$verificationStatus = getEmployerVerificationStatus($pdo, (int)$employerId) ?? (string)($_SESSION['verification_status'] ?? '');
$_SESSION['verification_status'] = $verificationStatus;
if (!isEmployerApproved($verificationStatus)) {
  $_SESSION['status'] = 'Your employer account is pending admin verification. Candidates module is locked until approval.';
  header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
  exit;
}

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
  $showInterviewSuccessOnRedirect = false;

  try {
    if ($action === 'change_status') {
      $nextStatus = trim((string)($_POST['next_status'] ?? ''));
      $result = candidates_update_application_status($pdo, $employerId, $applicationId, $nextStatus);

      if (!empty($result['success'])) {
        $_SESSION['status'] = 'Candidate stage updated to ' . candidates_status_display_label($nextStatus) . '.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to update candidate status.');
      }
    } elseif ($action === 'schedule_interview') {
      $result = candidates_schedule_interview($pdo, $employerId, $applicationId, $_POST);

      if (!empty($result['success'])) {
        $showInterviewSuccessOnRedirect = true;
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
      'interview_success' => $showInterviewSuccessOnRedirect ? '1' : null,
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
$showInterviewSuccessModal = ((int)($_GET['interview_success'] ?? 0) === 1);

$pipelineStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Candidates</h2>
    <p class="page-subtitle">Review and rank applicants for your internship positions.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div style="background:rgba(18,179,172,.12);border:1px solid rgba(18,179,172,.3);color:#12b3ac;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
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
    <option value="">Posted Applications</option>
    <?php foreach ($positions as $position): ?>
      <?php
      $positionId = (string)($position['internship_id'] ?? '');
      $positionTitle = (string)($position['title'] ?? 'Untitled Internship');
      ?>
      <option value="<?php echo dashboard_escape($positionId); ?>" <?php echo (string)$selected['position'] === $positionId ? 'selected' : ''; ?>><?php echo dashboard_escape($positionTitle); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="">All Application Stages</option>
    <?php foreach ($statuses as $status): ?>
      <?php
      $normalizedFilterStatus = candidates_normalize_status((string)$status);
      $filterStatusLabel = candidates_status_display_label($normalizedFilterStatus);
      ?>
      <option value="<?php echo dashboard_escape($status); ?>" <?php echo $selected['status'] === $status ? 'selected' : ''; ?>><?php echo dashboard_escape($filterStatusLabel); ?></option>
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
      $statusDisplay = candidates_status_display_label($statusCanonical);
      $pipelineFlow = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
      $isEndorsementApproved = !empty($candidate['endorsement_approved']);
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
          <div style="font-size:.72rem;color:#777;margin-bottom:6px;">Current Stage: <strong><?php echo dashboard_escape($statusDisplay); ?></strong></div>
          <div style="display:flex;gap:5px;flex-wrap:wrap;">
            <?php foreach ($pipelineFlow as $stage): ?>
              <?php
              $isActiveStage = $stage === $statusCanonical;
              $stageDisplay = candidates_status_display_label($stage);
              $stageStyle = $isActiveStage
                ? 'background:rgba(139,0,0,.1);color:#8b0000;border:1px solid rgba(139,0,0,.25);'
                : 'background:rgba(0,0,0,.04);color:#666;border:1px solid rgba(0,0,0,.08);';
              ?>
              <span style="font-size:.68rem;padding:3px 8px;border-radius:999px;<?php echo $stageStyle; ?>"><?php echo dashboard_escape($stageDisplay); ?></span>
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
          <span style="background:rgba(16,185,129,.1);color:#12b3ac;padding:4px 10px;border-radius:50px">Year: <?php echo dashboard_escape($yearLevel); ?></span>
          <span style="background:rgba(18,179,172,.12);color:#12b3ac;padding:4px 10px;border-radius:50px">Readiness: <?php echo dashboard_escape($readinessText); ?></span>
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
                <?php
                $pipelineDisplay = candidates_status_display_label($pipelineStatus);
                $isInterviewOptionLocked = !$isEndorsementApproved && $pipelineStatus === 'Interview Scheduled' && $statusCanonical !== 'Interview Scheduled';
                ?>
                <option value="<?php echo dashboard_escape($pipelineStatus); ?>" <?php echo $statusCanonical === $pipelineStatus ? 'selected' : ''; ?> <?php echo $isInterviewOptionLocked ? 'disabled' : ''; ?>><?php echo dashboard_escape($pipelineDisplay); ?><?php echo $isInterviewOptionLocked ? ' (Locked)' : ''; ?></option>
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
            data-status="<?php echo dashboard_escape($statusDisplay); ?>"
            data-position="<?php echo dashboard_escape((string)($candidate['internship_title'] ?? 'N/A')); ?>"
            data-skills="<?php echo dashboard_escape($skillsText !== '' ? $skillsText : 'No skills listed'); ?>"
          >View Profile</button>

          <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#12b3ac" type="button" onclick="quickRejectCandidate(<?php echo (int)($candidate['application_id'] ?? 0); ?>, '<?php echo dashboard_escape($selected['search']); ?>', '<?php echo dashboard_escape($selected['position']); ?>', '<?php echo dashboard_escape($selected['status']); ?>', '<?php echo dashboard_escape($selected['sort']); ?>')"><i class="fas fa-times"></i></button>
        </div>

        <?php if (!$isEndorsementApproved && in_array($statusCanonical, ['Pending', 'Shortlisted'], true)): ?>
          <div style="margin-top:10px;font-size:.75rem;color:#B45309;background:rgba(18,179,172,.12);border:1px solid rgba(245,158,11,.25);padding:8px 10px;border-radius:8px;">
            Interview is locked until adviser endorsement is approved.
          </div>
        <?php endif; ?>
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

<div id="interviewSuccessModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1260;align-items:center;justify-content:center;padding:16px;" onclick="if(event.target===this){closeInterviewSuccessModal(true);}">
  <div style="background:#fff;border-radius:12px;max-width:460px;width:100%;padding:18px 18px 14px;max-height:90vh;overflow:auto;box-shadow:0 18px 45px rgba(0,0,0,.24);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px;">
      <div style="display:flex;align-items:flex-start;gap:10px;">
        <div style="width:30px;height:30px;border-radius:999px;background:rgba(16,185,129,.14);color:#12b3ac;display:flex;align-items:center;justify-content:center;font-size:.92rem;flex-shrink:0;">
          <i class="fas fa-check"></i>
        </div>
        <div>
          <h3 style="margin:0;font-size:1rem;line-height:1.35;">Interview Scheduled Successfully</h3>
          <div id="interviewSuccessMessage" style="margin-top:4px;font-size:.84rem;color:#555;line-height:1.45;">The student will see the interview details immediately.</div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeInterviewSuccessModal(true)">Close</button>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
      <button type="button" class="btn btn-primary btn-sm" onclick="closeInterviewSuccessModal(true)">OK</button>
    </div>
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

function openInterviewSuccessModal(message) {
  var modal = document.getElementById('interviewSuccessModal');
  var messageEl = document.getElementById('interviewSuccessMessage');
  if (!modal) return;

  if (messageEl) {
    messageEl.textContent = message || 'The student will see the interview details immediately.';
  }

  modal.style.display = 'flex';
}

function closeInterviewSuccessModal(shouldReload) {
  var modal = document.getElementById('interviewSuccessModal');
  if (modal) {
    modal.style.display = 'none';
  }

  if (shouldReload) {
    location.reload();
  }
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

// ════════════════════════════════════════════════════════════════════════════
// Real-time polling for employer to see new applications
// ════════════════════════════════════════════════════════════════════════════

var candidatePolling = {
  enabled: true,
  pollInterval: 5000, // 5 seconds
  pollTimer: null,
  candidateCount: <?php echo count($candidates ?? []); ?>,

  start: function() {
    if (!this.enabled) return;
    this.poll();
    this.pollTimer = setInterval(this.poll.bind(this), this.pollInterval);
  },

  stop: function() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  },

  poll: function() {
    var self = candidatePolling;
    var positionFilter = (new URLSearchParams(window.location.search)).get('position') || '';
    var statusFilter = (new URLSearchParams(window.location.search)).get('status') || '';

    fetch('<?php echo $baseUrl; ?>/pages/employer/candidates/candidates_api.php?action=fetch_candidates&position=' + encodeURIComponent(positionFilter) + '&status=' + encodeURIComponent(statusFilter))
      .then(function(response) {
        if (!response.ok) throw new Error('API error');
        return response.json();
      })
      .then(function(data) {
        if (data.ok && Array.isArray(data.candidates)) {
          var newCount = data.candidates.length;
          if (newCount > self.candidateCount) {
            self.candidateCount = newCount;
            self.showNewApplicationNotification(data.candidates);
          }
        }
      })
      .catch(function(error) {
        console.log('Candidate poll error:', error);
      });
  },

  showNewApplicationNotification: function(candidates) {
    if (candidates.length === 0) return;

    var latestCandidate = candidates[0];
    var message = '🚀 New Application! ' + latestCandidate.student_name + ' (' + latestCandidate.compatibility_score + '% match) just applied.';

    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#12b3ac;color:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.2);z-index:9999;font-weight:600;max-width:360px;cursor:pointer';
    toast.textContent = message;
    toast.onclick = function() {
      location.reload();
    };
    document.body.appendChild(toast);

    setTimeout(function() {
      if (toast.parentNode) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(function() {
          if (toast.parentNode) {
            document.body.removeChild(toast);
          }
        }, 300);
      }
    }, 5000);
  }
};

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
  if (<?php echo $showInterviewSuccessModal ? 'true' : 'false'; ?>) {
    openInterviewSuccessModal('The student will see the interview details immediately.');

    if (window.history && window.history.replaceState) {
      var cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete('interview_success');
      window.history.replaceState({}, document.title, cleanUrl.toString());
    }
  }

  if (document.querySelector('.cards-grid')) {
    candidatePolling.start();
  }
});

// Stop polling when user leaves
window.addEventListener('beforeunload', function() {
  candidatePolling.stop();
});

// ════════════════════════════════════════════════════════════════════════════
// Interview form submission with API
// ════════════════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('interviewScheduleForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();

      var appId = parseInt(document.getElementById('interviewApplicationId').value || '0', 10);
      if (appId <= 0) {
        alert('No application selected');
        return;
      }

      var dateTimeInput = form.querySelector('input[name="interview_date"]').value;
      if (!dateTimeInput) {
        alert('Please select interview date and time');
        return;
      }

      var dateTimeParts = dateTimeInput.split('T');
      if (dateTimeParts.length !== 2) {
        alert('Invalid interview date/time format');
        return;
      }
      var dateStr = dateTimeParts[0];
      var timeStr = dateTimeParts[1].length === 5 ? (dateTimeParts[1] + ':00') : dateTimeParts[1];

      var mode = form.querySelector('select[name="interview_mode"]').value || 'Online';
      var meetingLink = form.querySelector('input[name="meeting_link"]').value || '';

      var formData = new FormData();
      formData.append('action', 'schedule_interview');
      formData.append('application_id', appId);
      formData.append('interview_date', dateStr);
      formData.append('interview_time', timeStr);
      formData.append('interview_mode', mode);
      formData.append('meeting_link', meetingLink);

      fetch('<?php echo $baseUrl; ?>/pages/employer/candidates/candidates_api.php', {
        method: 'POST',
        body: formData
      })
        .then(function(response) {
          return response.text().then(function(text) {
            var data = null;
            try {
              data = JSON.parse(text);
            } catch (parseErr) {
              throw new Error('Invalid API response: ' + text.slice(0, 180));
            }

            if (!response.ok) {
              throw new Error(data && data.error ? data.error : 'API error');
            }

            return data;
          });
        })
        .then(function(data) {
          if (data.ok) {
            closeInterviewModal();
            openInterviewSuccessModal('The student will see the interview details immediately.');
          } else {
            alert('Error: ' + (data.error || 'Could not schedule interview'));
          }
        })
        .catch(function(error) {
          alert('Error scheduling interview: ' + error.message);
        });
    });
  }
});
</script>
