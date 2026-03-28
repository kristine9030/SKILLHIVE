<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/students/data.php';
require_once __DIR__ . '/students/add_student_action.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$currentFilters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'department' => trim((string)($_GET['department'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];

$pageData = [
    'selected' => ['search' => '', 'department' => '', 'status' => ''],
    'filter_options' => ['departments' => [], 'statuses' => []],
    'rows' => [],
];

if ($adviserId > 0) {
    try {
        $pageData = getAdviserStudentsPageData($pdo, $adviserId, $currentFilters);
    } catch (Throwable $e) {
        $pageData = $pageData;
    }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
$addStudentForm = adviser_students_default_add_form();
$addStudentErrors = [];
$shouldOpenAddStudentModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_student') {
    $addStudentResult = adviser_students_process_add_student($pdo, $adviserId, $_POST);

    if ($addStudentResult['success']) {
        $_SESSION['status'] = 'Student added to advisory successfully.';
        header('Location: ' . $baseUrl . '/layout.php?page=adviser/students');
        exit;
    }

    $addStudentForm = $addStudentResult['form'];
    $addStudentErrors = $addStudentResult['errors'];
    $shouldOpenAddStudentModal = true;
}

$programOptions = adviser_students_program_options();
$yearLevelOptions = adviser_students_year_level_options();
?>

<style>
  .adv-students { display:flex; flex-direction:column; gap:18px; font-size:var(--font-size-body); color:var(--text); }
  .adv-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .adv-search, .adv-select { height:44px; border:1px solid var(--border); border-radius:18px; background:var(--card); box-shadow:var(--card-shadow); color:var(--text); }
  .adv-search { flex:1 1 290px; max-width:420px; min-width:280px; display:flex; align-items:center; gap:10px; padding:0 16px; }
  .adv-search i { color:var(--text3); font-size:.92rem; }
  .adv-search input { width:100%; border:0; outline:0; background:transparent; color:var(--text); font-size:.92rem; }
  .adv-search input::placeholder { color:var(--text3); }
  .adv-select { min-width:188px; padding:0 42px 0 16px; font-size:.92rem; font-weight:500; outline:0; appearance:none; background-image:linear-gradient(45deg,transparent 50%,#111 50%),linear-gradient(135deg,#111 50%,transparent 50%); background-position:calc(100% - 20px) calc(50% - 3px),calc(100% - 14px) calc(50% - 3px); background-size:6px 6px,6px 6px; background-repeat:no-repeat; }
  .adv-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; height:44px; padding:0 22px; border-radius:999px; border:1px solid transparent; background:#111; color:#fff; font-size:.92rem; font-weight:700; text-decoration:none; cursor:pointer; }
  .adv-btn.is-secondary { background:var(--card); border-color:var(--border); color:var(--text); }
  .adv-btn:disabled { opacity:1; cursor:not-allowed; }
  .adv-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--card-shadow); overflow:hidden; }
  .adv-table-wrap { overflow-x:auto; }
  .adv-table { width:100%; min-width:1080px; border-collapse:separate; border-spacing:0; }
  .adv-table th { padding:14px 14px 10px; text-align:left; font-size:.77rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text3); border-bottom:1px solid var(--border); background:rgba(255,255,255,.82); }
  .adv-table td { padding:14px; border-bottom:1px solid var(--border); background:rgba(255,255,255,.9); vertical-align:middle; font-size:.9rem; color:var(--text); }
  .adv-table tr:last-child td { border-bottom:0; }
  .adv-table tr:hover td { background:#fffaf8; }
  .adv-student { display:flex; align-items:center; gap:12px; }
  .adv-avatar { width:38px; height:38px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:.88rem; font-weight:700; flex:0 0 auto; }
  .adv-name { margin:0; font-size:.98rem; font-weight:700; color:var(--text); }
  .adv-subtext, .adv-company-meta { margin:2px 0 0; font-size:.82rem; color:var(--text3); }
  .adv-dept { display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px; border-radius:999px; background:#f0edff; color:#e11d48; font-size:.76rem; font-weight:700; }
  .adv-company { margin:0; font-size:.96rem; font-weight:700; color:var(--text); }
  .adv-hours { font-size:.98rem; font-weight:700; color:var(--text); }
  .adv-req { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .adv-req-count { font-size:.94rem; font-weight:700; color:#ef4444; }
  .adv-req-count.is-success { color:#16a34a; }
  .adv-req-link { padding:0; border:0; background:transparent; color:#dc2626; font-size:.86rem; font-weight:700; text-decoration:underline; cursor:pointer; }
  .adv-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .adv-row-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:34px; padding:0 16px; border-radius:999px; border:1px solid transparent; background:#111; color:#fff; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; }
  .adv-row-btn.is-icon { width:38px; min-width:38px; padding:0; border-color:var(--border); background:var(--card); color:var(--text); }
  .adv-row-btn.is-icon[aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
  .adv-empty { padding:34px 18px; text-align:center; color:var(--text3); font-size:.9rem; }
  .adv-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.35); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; z-index:1250; padding:18px; }
  .adv-modal-overlay.open { display:flex; }
  .adv-add-modal { width:min(520px,100%); background:#fff; border-radius:24px; box-shadow:0 24px 70px rgba(15,23,42,.26); padding:26px 28px; border:1px solid rgba(229,231,235,.9); }
  .adv-add-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
  .adv-add-title { margin:0; font-size:1rem; font-weight:800; color:#111827; }
  .adv-add-close { width:36px; height:36px; border:0; background:transparent; color:#a1a1aa; font-size:1.55rem; line-height:1; cursor:pointer; }
  .adv-add-error { margin-bottom:14px; padding:12px 14px; border-radius:14px; border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; font-size:.82rem; }
  .adv-add-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; }
  .adv-add-field { display:flex; flex-direction:column; gap:8px; }
  .adv-add-field.full { grid-column:1 / -1; }
  .adv-add-label { font-size:.82rem; font-weight:700; color:#27272a; }
  .adv-add-input, .adv-add-select { width:100%; height:42px; border:1px solid #e5e7eb; border-radius:14px; background:#fff; padding:0 14px; font-size:.91rem; color:#111827; outline:0; }
  .adv-add-input::placeholder { color:#a1a1aa; }
  .adv-add-input:focus, .adv-add-select:focus { border-color:#111827; box-shadow:0 0 0 3px rgba(17,24,39,.05); }
  .adv-add-help { min-height:16px; font-size:.74rem; color:#dc2626; }
  .adv-add-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top:18px; }
  @media (max-width:640px) { .adv-search, .adv-select, .adv-btn { width:100%; max-width:none; } }
  @media (max-width:640px) { .adv-add-grid { grid-template-columns:1fr; } .adv-add-modal { padding:22px 18px; border-radius:20px; } }
</style>

<div class="adv-students">
  <form class="adv-toolbar" method="get" action="<?php echo $baseUrl; ?>/layout.php">
    <input type="hidden" name="page" value="adviser/students">

    <label class="adv-search" aria-label="Search students">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_students_escape($selected['search'] ?? ''); ?>">
    </label>

    <select class="adv-select" name="department" aria-label="Filter by department">
      <option value="">All Departments</option>
      <?php foreach (($filterOptions['departments'] ?? []) as $departmentOption): ?>
        <option value="<?php echo adviser_students_escape($departmentOption); ?>" <?php echo ($selected['department'] ?? '') === $departmentOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($departmentOption); ?></option>
      <?php endforeach; ?>
    </select>

    <select class="adv-select" name="status" aria-label="Filter by status">
      <option value="">All Status</option>
      <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
        <option value="<?php echo adviser_students_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($statusOption); ?></option>
      <?php endforeach; ?>
    </select>

    <button class="adv-btn is-secondary" type="submit">Apply Filters</button>
    <button class="adv-btn" type="button" onclick="openAddStudentModal()"><i class="fas fa-user-plus"></i>Add Student</button>
  </form>

  <div class="adv-card">
    <div class="adv-table-wrap">
      <table class="adv-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Department</th>
            <th>Company / Status</th>
            <th>OJT Hours</th>
            <th>Requirements</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row): ?>
              <?php
              $studentId = (int)($row['student_id'] ?? 0);
              $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
              $companyName = trim((string)($row['company_name'] ?? ''));
              $internshipTitle = trim((string)($row['internship_title'] ?? ''));
              $subtitle = trim((string)($row['program'] ?? '')) . ' - ' . ($companyName !== '' ? $companyName : 'No company assigned');
              $yearProgram = adviser_students_year_level_label($row['year_level'] ?? '') . ' - ' . trim((string)($row['program'] ?? 'N/A'));
              $hoursCompleted = (float)($row['hours_completed'] ?? 0);
              $hoursRequired = (float)($row['hours_required'] ?? 0);
              $totalRequirements = (int)($row['total_requirements'] ?? 0);
              $requirementsSubmitted = (int)($row['requirements_submitted'] ?? 0);
              $requirementsPending = (int)($row['requirements_pending'] ?? 0);
              $requirementsCompletion = (int)($row['requirements_completion'] ?? 0);
              $internshipId = isset($row['internship_id']) ? (int)$row['internship_id'] : 0;
              $buttonId = 'requirements-trigger-' . $studentId;
              ?>
              <tr>
                <td>
                  <div class="adv-student">
                    <span class="adv-avatar" style="background:<?php echo adviser_students_escape(adviser_students_avatar_gradient($studentId)); ?>;"><?php echo adviser_students_escape($row['initials'] ?? 'NA'); ?></span>
                    <div>
                      <p class="adv-name"><?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></p>
                      <p class="adv-subtext"><?php echo adviser_students_escape($yearProgram); ?></p>
                    </div>
                  </div>
                </td>
                <td><span class="adv-dept"><?php echo adviser_students_escape((string)($row['department'] ?? 'Unassigned')); ?></span></td>
                <td>
                  <p class="adv-company"><?php echo adviser_students_escape($companyName !== '' ? $companyName : '-'); ?></p>
                  <p class="adv-company-meta"><?php echo adviser_students_escape($companyName !== '' ? ($internshipTitle !== '' ? $internshipTitle : 'Internship placement') : 'Not yet placed'); ?></p>
                </td>
                <td><span class="adv-hours"><?php echo (int)round($hoursCompleted); ?>/<?php echo (int)round($hoursRequired); ?></span></td>
                <td>
                  <div class="adv-req">
                    <span class="adv-req-count js-req-progress-text <?php echo ($totalRequirements > 0 && $requirementsSubmitted >= $totalRequirements) ? 'is-success' : ''; ?>" data-student-id="<?php echo $studentId; ?>" data-total="<?php echo $totalRequirements > 0 ? $totalRequirements : 0; ?>">
                      <?php echo $requirementsSubmitted; ?>/<?php echo $totalRequirements > 0 ? $totalRequirements : 0; ?>
                    </span>
                    <button class="adv-req-link" type="button" onclick="openRequirementsModal(document.getElementById('<?php echo adviser_students_escape($buttonId); ?>'))">View</button>
                  </div>
                </td>
                <td>
                  <div class="adv-actions">
                    <button
                      id="<?php echo adviser_students_escape($buttonId); ?>"
                      class="adv-row-btn js-open-requirements-btn"
                      type="button"
                      onclick="openRequirementsModal(this)"
                      data-name="<?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Student'); ?>"
                      data-subtitle="<?php echo adviser_students_escape($subtitle); ?>"
                      data-submitted="<?php echo $requirementsSubmitted; ?>"
                      data-pending="<?php echo $requirementsPending; ?>"
                      data-completion="<?php echo $requirementsCompletion; ?>"
                      data-student-id="<?php echo $studentId; ?>"
                      data-internship-id="<?php echo $internshipId > 0 ? $internshipId : ''; ?>"
                    >
                      <i class="fas fa-clipboard-list"></i>
                      Requirements
                    </button>
                    <?php if (!empty($row['email'])): ?>
                      <a class="adv-row-btn is-icon" href="mailto:<?php echo adviser_students_escape((string)$row['email']); ?>" title="Email student"><i class="fas fa-envelope"></i></a>
                    <?php else: ?>
                      <span class="adv-row-btn is-icon" aria-disabled="true" title="No email on file"><i class="fas fa-envelope"></i></span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="adv-empty">No students found for the selected filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="addStudentModal" class="adv-modal-overlay<?php echo $shouldOpenAddStudentModal ? ' open' : ''; ?>">
  <div class="adv-add-modal" role="dialog" aria-modal="true" aria-labelledby="addStudentTitle">
    <div class="adv-add-header">
      <div>
        <h2 id="addStudentTitle" class="adv-add-title">Add Student to Advisory</h2>
      </div>
      <button type="button" class="adv-add-close" onclick="closeAddStudentModal()">&times;</button>
    </div>

    <?php if (!empty($addStudentErrors['form'])): ?>
      <div class="adv-add-error"><?php echo adviser_students_escape($addStudentErrors['form']); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/students">
      <input type="hidden" name="action" value="add_student">

      <div class="adv-add-grid">
        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentName">Student Name</label>
          <input id="addStudentName" class="adv-add-input" type="text" name="student_name" placeholder="e.g. Juan dela Cruz" value="<?php echo adviser_students_escape($addStudentForm['student_name'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['student_name'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentNumber">Student ID</label>
          <input id="addStudentNumber" class="adv-add-input" type="text" name="student_number" placeholder="e.g. 2021-12345" value="<?php echo adviser_students_escape($addStudentForm['student_number'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['student_number'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentDepartment">Department</label>
          <select id="addStudentDepartment" class="adv-add-select" name="department" required>
            <?php foreach ($programOptions as $programOption): ?>
              <option value="<?php echo adviser_students_escape($programOption['value']); ?>" <?php echo ($addStudentForm['department'] ?? '') === $programOption['value'] ? 'selected' : ''; ?>>
                <?php echo adviser_students_escape($programOption['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['department'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentYearLevel">Year Level</label>
          <select id="addStudentYearLevel" class="adv-add-select" name="year_level" required>
            <?php foreach ($yearLevelOptions as $yearLevelOption): ?>
              <option value="<?php echo adviser_students_escape($yearLevelOption['value']); ?>" <?php echo ($addStudentForm['year_level'] ?? '') === $yearLevelOption['value'] ? 'selected' : ''; ?>>
                <?php echo adviser_students_escape($yearLevelOption['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['year_level'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentEmail">Email Address</label>
          <input id="addStudentEmail" class="adv-add-input" type="email" name="email" placeholder="student@university.edu" value="<?php echo adviser_students_escape($addStudentForm['email'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['email'] ?? ''); ?></div>
        </div>
      </div>

      <div class="adv-add-actions">
        <button type="button" class="adv-btn is-secondary" onclick="closeAddStudentModal()">Cancel</button>
        <button type="submit" class="adv-btn"><i class="fas fa-user-plus"></i>Add Student</button>
      </div>
    </form>
  </div>
</div>

<div id="requirementsModal" style="position:fixed;inset:0;background:rgba(0,0,0,.40);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1200;padding:16px;">
  <div style="background:#fff;width:720px;max-width:100%;border-radius:22px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:24px;max-height:90vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;gap:16px;">
      <div>
        <h2 id="requirementsTitle" style="font-size:1.05rem;font-weight:700;margin:0;color:#111827;">Student - Requirements Checklist</h2>
        <p id="requirementsSubtitle" style="font-size:.82rem;color:#6b7280;margin:4px 0 0;">Program - Company</p>
      </div>
      <button type="button" onclick="closeRequirementsModal()" style="width:38px;height:38px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#9ca3af;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px;">
      <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:14px;padding:14px;text-align:center;">
        <p id="requirementsSubmitted" style="font-size:1.5rem;font-weight:700;color:#4f46e5;margin:0;">0</p>
        <p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Submitted</p>
      </div>
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:14px;text-align:center;">
        <p id="requirementsPending" style="font-size:1.5rem;font-weight:700;color:#f97316;margin:0;">0</p>
        <p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Pending</p>
      </div>
      <div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:14px;padding:14px;text-align:center;">
        <p id="requirementsCompletion" style="font-size:1.5rem;font-weight:700;color:#16a34a;margin:0;">0%</p>
        <p style="font-size:.75rem;color:#6b7280;margin:4px 0 0;">Completion</p>
      </div>
    </div>

    <div style="width:100%;background:#e5e7eb;height:8px;border-radius:999px;margin-bottom:20px;overflow:hidden;">
      <div id="requirementsProgressBar" style="height:8px;border-radius:999px;background:linear-gradient(90deg,#ef4444,#22c55e);width:0;"></div>
    </div>

    <div id="requirementsChecklist" style="display:flex;flex-direction:column;gap:10px;max-height:300px;overflow-y:auto;padding-right:4px;"></div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;">
      <button type="button" class="adv-btn is-secondary" onclick="closeRequirementsModal()">Close</button>
      <button id="requirementsSaveBtn" type="button" class="adv-btn" onclick="saveRequirementsChanges()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
</div>

<script>
function openAddStudentModal() {
    var modal = document.getElementById('addStudentModal');
    if (!modal) return;
    modal.classList.add('open');
}

function closeAddStudentModal() {
    var modal = document.getElementById('addStudentModal');
    if (!modal) return;
    modal.classList.remove('open');
}

var requirementsEndpoint = '<?php echo $baseUrl; ?>/pages/adviser/students/requirements_data.php';
var requirementsContext = {
    studentId: 0,
    internshipId: '',
    canEdit: false,
    activeButton: null
};

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderRequirementsChecklist(phases) {
    var container = document.getElementById('requirementsChecklist');
    if (!container) return;

    var orderedPhases = ['Pre-OJT', 'During OJT', 'Post-OJT'];
    var html = '';

    if (!requirementsContext.canEdit) {
        html += '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:14px;padding:12px 14px;color:#92400e;font-size:.8rem;">This student has no internship context yet. Checklist is view-only for now.</div>';
    }

    orderedPhases.forEach(function (phaseName) {
        var phaseRows = (phases && phases[phaseName]) ? phases[phaseName] : [];
        html += '<p style="font-size:.72rem;font-weight:700;color:#9ca3af;margin:10px 0 0;letter-spacing:.06em;">' + escapeHtml(phaseName.toUpperCase()) + ' PHASE</p>';

        if (!phaseRows.length) {
            html += '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:12px 14px;color:#9ca3af;font-size:.8rem;">No requirements found.</div>';
            return;
        }

        phaseRows.forEach(function (item) {
            var isSubmitted = !!item.is_submitted;
            var boxBorder = isSubmitted ? '#bbf7d0' : '#e5e7eb';
            var boxBg = isSubmitted ? '#f0fdf4' : '#fff';
            var statusColor = isSubmitted ? '#16a34a' : '#ef4444';
            var statusText = item.status || (isSubmitted ? 'Submitted' : 'Pending');
            var dateText = item.date_label ? item.date_label : statusText;
            var requirementId = Number(item.requirement_id || 0);

            html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid ' + boxBorder + ';background:' + boxBg + ';border-radius:14px;padding:12px 14px;">';
            html += '<div style="display:flex;align-items:center;gap:10px;">';
            html += '<input type="checkbox" class="js-requirement-checkbox" data-requirement-id="' + requirementId + '" ' + (isSubmitted ? 'checked ' : '') + (requirementsContext.canEdit ? '' : 'disabled ') + 'style="width:18px;height:18px;' + (requirementsContext.canEdit ? 'cursor:pointer;' : 'cursor:not-allowed;') + (isSubmitted ? 'accent-color:#22c55e;' : '') + '">';
            html += '<p style="font-size:.85rem;margin:0;color:#111827;">' + escapeHtml(item.name || 'Requirement') + '</p>';
            html += '</div>';
            html += '<div style="display:flex;align-items:center;gap:8px;font-size:.72rem;flex-wrap:wrap;justify-content:flex-end;">';
            html += '<span style="background:#e0e7ff;color:#4f46e5;padding:4px 8px;border-radius:999px;font-weight:700;">' + escapeHtml(item.phase || phaseName) + '</span>';
            html += '<span style="color:' + statusColor + ';font-weight:600;">' + escapeHtml(dateText) + '</span>';
            html += '</div>';
            html += '</div>';
        });
    });

    container.innerHTML = html;

    if (requirementsContext.canEdit) {
        var checkboxes = container.querySelectorAll('.js-requirement-checkbox');
        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                toggleRequirementCheckbox(checkbox);
            });
        });
    }
}

function setRequirementsSummary(summary) {
    var submittedEl = document.getElementById('requirementsSubmitted');
    var pendingEl = document.getElementById('requirementsPending');
    var completionEl = document.getElementById('requirementsCompletion');
    var progressBarEl = document.getElementById('requirementsProgressBar');

    var submittedValue = Number((summary && summary.submitted) || 0);
    var pendingValue = Number((summary && summary.pending) || 0);
    var completionValue = Number((summary && summary.completion) || 0);

    if (submittedEl) submittedEl.textContent = submittedValue;
    if (pendingEl) pendingEl.textContent = pendingValue;
    if (completionEl) completionEl.textContent = completionValue + '%';
    if (progressBarEl) progressBarEl.style.width = completionValue + '%';

    syncRequirementsRowSummary(submittedValue, completionValue);
}

function syncRequirementsRowSummary(submittedValue, completionValue) {
    var studentId = Number(requirementsContext.studentId || 0);
    if (studentId <= 0) return;

    var textEl = document.querySelector('.js-req-progress-text[data-student-id="' + studentId + '"]');
    if (textEl) {
        var total = Number(textEl.getAttribute('data-total') || '0');
        textEl.textContent = Number(submittedValue || 0) + '/' + (total > 0 ? total : 0);
        textEl.classList.toggle('is-success', total > 0 && Number(submittedValue || 0) >= total);
    }

    if (requirementsContext.activeButton) {
        requirementsContext.activeButton.setAttribute('data-submitted', String(Number(submittedValue || 0)));
        requirementsContext.activeButton.setAttribute('data-completion', String(Number(completionValue || 0)));
        var totalRequirements = textEl ? Number(textEl.getAttribute('data-total') || '0') : 0;
        var pendingValue = Math.max(0, totalRequirements - Number(submittedValue || 0));
        requirementsContext.activeButton.setAttribute('data-pending', String(pendingValue));
    }
}

function loadRequirementsData() {
    if (requirementsContext.studentId <= 0) {
        setRequirementsErrorState();
        return;
    }

    var query = '?student_id=' + encodeURIComponent(requirementsContext.studentId);
    if (requirementsContext.internshipId !== '') {
        query += '&internship_id=' + encodeURIComponent(requirementsContext.internshipId);
    }

    fetch(requirementsEndpoint + query, { credentials: 'same-origin' })
        .then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Failed request');
                }
                return payload;
            });
        })
        .then(function (payload) {
            requirementsContext.canEdit = !!payload.can_edit;
            if (!requirementsContext.internshipId && payload.internship_id_context) {
                requirementsContext.internshipId = String(payload.internship_id_context);
            }
            setRequirementsSummary(payload.summary || {});
            renderRequirementsChecklist(payload.phases || {});
        })
        .catch(function (error) {
            setRequirementsErrorStateWithMessage(error && error.message ? error.message : 'Unable to load requirements right now.');
        });
}

function toggleRequirementCheckbox(checkbox) {
    var requirementId = Number(checkbox.getAttribute('data-requirement-id') || '0');
    if (requirementsContext.studentId <= 0 || requirementId <= 0) {
        checkbox.checked = !checkbox.checked;
        return;
    }

    checkbox.disabled = true;

    var formBody = new URLSearchParams();
    formBody.append('action', 'toggle_requirement');
    formBody.append('student_id', String(requirementsContext.studentId));
    formBody.append('internship_id', requirementsContext.internshipId || '');
    formBody.append('requirement_id', String(requirementId));
    formBody.append('is_checked', checkbox.checked ? '1' : '0');

    fetch(requirementsEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body: formBody.toString()
    })
        .then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Failed update');
                }
                return payload;
            });
        })
        .then(function (payload) {
            requirementsContext.canEdit = !!payload.can_edit;
            if (!requirementsContext.internshipId && payload.internship_id_context) {
                requirementsContext.internshipId = String(payload.internship_id_context);
            }
            setRequirementsSummary(payload.summary || {});
            renderRequirementsChecklist(payload.phases || {});
        })
        .catch(function (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            setRequirementsErrorStateWithMessage(error && error.message ? error.message : 'Unable to update requirement status right now.');
        });
}

function setRequirementsLoadingState() {
    var container = document.getElementById('requirementsChecklist');
    if (!container) return;
    container.innerHTML = '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:14px;color:#6b7280;font-size:.82rem;">Loading requirements...</div>';
}

function setRequirementsErrorState() {
    var container = document.getElementById('requirementsChecklist');
    if (!container) return;
    container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:14px;padding:14px;color:#b91c1c;font-size:.82rem;">Unable to load requirements right now.</div>';
}

function setRequirementsErrorStateWithMessage(message) {
    var container = document.getElementById('requirementsChecklist');
    if (!container) return;
    container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:14px;padding:14px;color:#b91c1c;font-size:.82rem;">' + escapeHtml(message || 'Unable to load requirements right now.') + '</div>';
}

function openRequirementsModal(button) {
    if (!button) return;

    var modal = document.getElementById('requirementsModal');
    if (!modal) return;

    var name = button.getAttribute('data-name') || 'Student';
    var subtitle = button.getAttribute('data-subtitle') || 'Program - Company';
    var submitted = button.getAttribute('data-submitted') || '0';
    var pending = button.getAttribute('data-pending') || '0';
    var completion = button.getAttribute('data-completion') || '0';
    var studentId = button.getAttribute('data-student-id') || '0';
    var internshipId = button.getAttribute('data-internship-id') || '';

    var titleEl = document.getElementById('requirementsTitle');
    var subtitleEl = document.getElementById('requirementsSubtitle');
    if (titleEl) titleEl.textContent = name + ' - Requirements Checklist';
    if (subtitleEl) subtitleEl.textContent = subtitle;

    setRequirementsSummary({
        submitted: Number(submitted || 0),
        pending: Number(pending || 0),
        completion: Number(completion || 0)
    });

    requirementsContext.studentId = Number(studentId || 0);
    requirementsContext.internshipId = internshipId;
    requirementsContext.canEdit = false;
    requirementsContext.activeButton = button;

    setRequirementsLoadingState();
    modal.style.display = 'flex';
    loadRequirementsData();
}

function saveRequirementsChanges() {
    var button = document.getElementById('requirementsSaveBtn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-check"></i> Saved';
    }

    setTimeout(function () {
        closeRequirementsModal();
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }, 250);
}

function closeRequirementsModal() {
    var modal = document.getElementById('requirementsModal');
    if (!modal) return;
    modal.style.display = 'none';
    requirementsContext.activeButton = null;
}

document.addEventListener('click', function (event) {
    var addStudentModal = document.getElementById('addStudentModal');
    if (addStudentModal && event.target === addStudentModal) {
        closeAddStudentModal();
    }

    var modal = document.getElementById('requirementsModal');
    if (!modal || modal.style.display !== 'flex') return;
    if (event.target === modal) {
        closeRequirementsModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeAddStudentModal();
        closeRequirementsModal();
    }
});
</script>
