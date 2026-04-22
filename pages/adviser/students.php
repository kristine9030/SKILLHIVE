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
        // Keep default pageData on error
    }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
$addStudentForm = adviser_students_default_add_form();
$addStudentErrors = [];
$shouldOpenAddStudentModal = false;
$bulkImportResult = null;
$bulkImportSummaryMessage = '';
$shouldOpenBulkImportModal = false;
$staticProgramLabel = adviser_students_static_program_label();
$staticDepartmentLabel = adviser_students_static_department_label();
$postAction = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'add_student') {
    $addStudentResult = adviser_students_process_add_student($pdo, $adviserId, $_POST);

    if ($addStudentResult['success']) {
      $credentialsEmailResult = adviser_students_send_credentials_email([
        'student_name' => (string)($addStudentResult['student_name'] ?? ''),
        'student_email' => (string)($addStudentResult['student_email'] ?? ''),
        'student_number' => (string)($addStudentResult['student_number'] ?? ''),
        'temp_password' => (string)($addStudentResult['temp_password'] ?? ''),
        'login_url' => adviser_students_build_login_url($baseUrl ?? '/SkillHive'),
      ]);

      if (!empty($credentialsEmailResult['ok'])) {
        $_SESSION['status'] = 'Student account created, assigned, and credentials emailed successfully.';
      } else {
        $_SESSION['status'] = 'Student account created and assigned, but credentials email failed: ' . (string)($credentialsEmailResult['error'] ?? 'Unknown email error.');
      }
        header('Location: ' . $baseUrl . '/layout.php?page=adviser/students');
        exit;
    }

    $addStudentForm = $addStudentResult['form'];
    $addStudentErrors = $addStudentResult['errors'];
    $shouldOpenAddStudentModal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'bulk_add_students') {
    $bulkImportResult = adviser_students_process_bulk_add_students(
        $pdo,
        $adviserId,
        is_array($_FILES['bulk_students_csv'] ?? null) ? $_FILES['bulk_students_csv'] : [],
        $baseUrl ?? '/SkillHive'
    );

    $bulkImportSummaryMessage = 'Bulk import finished: '
      . (int)($bulkImportResult['created'] ?? 0) . ' created, '
      . (int)($bulkImportResult['failed'] ?? 0) . ' failed, '
      . (int)($bulkImportResult['emails_sent'] ?? 0) . ' credentials emails sent, '
      . (int)($bulkImportResult['emails_failed'] ?? 0) . ' email failures.';

    $isFullySuccessful = !empty($bulkImportResult['success'])
      && (int)($bulkImportResult['failed'] ?? 0) === 0
      && (int)($bulkImportResult['emails_failed'] ?? 0) === 0
      && empty($bulkImportResult['errors'])
      && empty($bulkImportResult['warnings']);

    if ($isFullySuccessful) {
      $_SESSION['status'] = $bulkImportSummaryMessage;
      header('Location: ' . $baseUrl . '/layout.php?page=adviser/students');
      exit;
    }

    $shouldOpenBulkImportModal = true;

    if ($adviserId > 0) {
      try {
        $pageData = getAdviserStudentsPageData($pdo, $adviserId, $currentFilters);
      } catch (Throwable $e) {
        // Keep previous pageData on error
      }

      $selected = $pageData['selected'];
      $filterOptions = $pageData['filter_options'];
      $rows = $pageData['rows'];
    }
}
?>

<style>
  .adv-students { display:flex; flex-direction:column; gap:18px; font-size:var(--font-size-body); color:var(--text); }
  .adv-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:space-between; }
  .adv-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; flex:1; }
  .adv-toolbar-right { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .adv-search, .adv-select { height:44px; border:1px solid var(--border); border-radius:18px; background:var(--card); box-shadow:var(--card-shadow); color:var(--text); }
  .adv-search { flex:1 1 290px; max-width:420px; min-width:280px; display:flex; align-items:center; gap:10px; padding:0 16px; }
  .adv-search i { color:var(--text3); font-size:.92rem; }
  .adv-search input { width:100%; border:0; outline:0; background:transparent; color:var(--text); font-size:.92rem; }
  .adv-search input::placeholder { color:var(--text3); }
  .adv-select { min-width:188px; padding:0 42px 0 16px; font-size:.92rem; font-weight:500; outline:0; appearance:none; background-image:linear-gradient(45deg,transparent 50%,#111 50%),linear-gradient(135deg,#111 50%,transparent 50%); background-position:calc(100% - 20px) calc(50% - 3px),calc(100% - 14px) calc(50% - 3px); background-size:6px 6px,6px 6px; background-repeat:no-repeat; }
  .adv-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; height:44px; padding:0 22px; border-radius:999px; border:1px solid transparent; background:#111; color:#fff; font-size:.92rem; font-weight:700; text-decoration:none; cursor:pointer; }
  .adv-btn.is-secondary { background:var(--card); border-color:var(--border); color:var(--text); }
  .adv-btn:disabled { opacity:1; cursor:default; }
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
  .adv-dept { display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px; border-radius:999px; background:#f0edff; color:#000; font-size:.76rem; font-weight:700; }
  .adv-company { margin:0; font-size:.96rem; font-weight:700; color:var(--text); }
  .adv-hours { font-size:.98rem; font-weight:700; color:var(--text); }
  .adv-req { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .adv-req-count { font-size:.94rem; font-weight:700; color:#ef4444; }
  .adv-req-count.is-success { color:#16a34a; }
  .adv-req-link { padding:0; border:0; background:transparent; color:#dc2626; font-size:.86rem; font-weight:700; text-decoration:underline; cursor:pointer; }
  .adv-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .adv-row-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:34px; padding:0 16px; border-radius:999px; border:1px solid transparent; background:#111; color:#fff; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; }
  .adv-row-btn.is-icon { width:38px; min-width:38px; padding:0; border-color:var(--border); background:var(--card); color:var(--text); }
  .adv-row-btn.is-icon[aria-disabled="true"] { opacity:.55; cursor:default; }
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
  .adv-add-input[readonly] { background:#f9fafb; color:#4b5563; cursor:default; }
  .adv-add-input::placeholder { color:#a1a1aa; }
  .adv-add-input:focus, .adv-add-select:focus { border-color:#111827; box-shadow:0 0 0 3px rgba(17,24,39,.05); }
  .adv-add-help { min-height:16px; font-size:.74rem; color:#dc2626; }
  .adv-add-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top:18px; }
  .adv-add-modal.is-wide { width:min(680px,100%); }
  .adv-bulk-note { margin-top:10px; padding:10px 12px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155; font-size:.8rem; line-height:1.5; }
  .adv-bulk-summary { margin-bottom:12px; padding:12px 14px; border-radius:14px; border:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; font-size:.82rem; }
  .adv-bulk-summary strong { color:#1d4ed8; }
  .adv-bulk-list { margin:10px 0 0; padding-left:18px; max-height:170px; overflow:auto; color:#7f1d1d; font-size:.79rem; line-height:1.45; }
  .adv-bulk-list.is-warning { color:#92400e; }
  @media (max-width:640px) { .adv-search, .adv-select, .adv-btn { width:100%; max-width:none; } }
  @media (max-width:640px) { .adv-add-grid { grid-template-columns:1fr; } .adv-add-modal { padding:22px 18px; border-radius:20px; } }
</style>

<div class="adv-students">
  <!-- Adviser Students Banner -->
  <div style="background:linear-gradient(90deg, #0d1b2e 0%, #1a2f4a 40%, rgba(13, 27, 46, 0.3) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:20px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(13, 27, 46, 0.4);">
    <div style="z-index:2;flex:1;">
      <h2 style="font-size:1.8rem;font-weight:900;margin:0 0 12px 0;line-height:1.2;color:white;">Empower Your Students</h2>
      <p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Guide students through their journey, provide endorsements, monitor progress, and help them succeed in their internship placements.</p>
    </div>
  </div>

  <form class="adv-toolbar" method="get" action="<?php echo $baseUrl; ?>/layout.php">
    <input type="hidden" name="page" value="adviser/students">

    <div class="adv-toolbar-left">
      <label class="adv-search" aria-label="Search students">
        <i class="fas fa-search"></i>
        <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_students_escape($selected['search'] ?? ''); ?>">
      </label>

      <select class="adv-select" name="department" aria-label="Filter by department">
        <option value="">All Section</option>
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
    </div>

    <div class="adv-toolbar-right">
      <button class="adv-btn is-secondary" type="button" onclick="openBulkImportModal()"><i class="fas fa-file-upload"></i>Bulk Import</button>
      <button class="adv-btn" type="button" onclick="openAddStudentModal()"><i class="fas fa-user-plus"></i>Add Student</button>
    </div>
  </form>

  <div class="adv-card">
    <div class="adv-table-wrap">
      <table class="adv-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Department</th>
            <th>Company / MOA</th>
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
              $moaLabel = (string)($row['moa_label'] ?? adviser_students_moa_label((string)($row['moa_status'] ?? ''), $companyName, (string)($row['application_status'] ?? '')));
              $subtitle = trim((string)($row['program'] ?? '')) . ' - ' . ($companyName !== '' ? $companyName : 'No company assigned');
              $academicYearLabel = trim((string)($row['academic_year'] ?? ''));
              if ($academicYearLabel === '') {
                $academicYearLabel = adviser_students_year_level_label($row['year_level'] ?? '');
              }
              $yearProgram = 'Academic Year: ' . $academicYearLabel . ' - ' . trim((string)($row['program'] ?? 'N/A'));
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
                  <p class="adv-company-meta"><?php echo adviser_students_escape($moaLabel); ?></p>
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

    <form id="addStudentForm" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/students">
      <input type="hidden" name="action" value="add_student">

      <div class="adv-add-grid">
        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentNumber">Student ID</label>
          <input id="addStudentNumber" class="adv-add-input" type="text" name="student_number" placeholder="e.g. 2021-12345" value="<?php echo adviser_students_escape($addStudentForm['student_number'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['student_number'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentFirstName">First Name</label>
          <input id="addStudentFirstName" class="adv-add-input" type="text" name="first_name" placeholder="e.g. Juan" value="<?php echo adviser_students_escape($addStudentForm['first_name'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['first_name'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentLastName">Last Name</label>
          <input id="addStudentLastName" class="adv-add-input" type="text" name="last_name" placeholder="e.g. dela Cruz" value="<?php echo adviser_students_escape($addStudentForm['last_name'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['last_name'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentEmail">Email Address</label>
          <input id="addStudentEmail" class="adv-add-input" type="email" name="email" placeholder="Auto-generated from Student ID" value="<?php echo adviser_students_escape($addStudentForm['email'] ?? ''); ?>" required readonly>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['email'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentDepartment">Department</label>
          <input id="addStudentDepartment" class="adv-add-input" type="text" name="department" value="<?php echo adviser_students_escape((string)($addStudentForm['department'] ?? $staticDepartmentLabel)); ?>" readonly>
          <div class="adv-add-help"></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentProgram">Program</label>
          <input id="addStudentProgram" class="adv-add-input" type="text" name="program" value="<?php echo adviser_students_escape((string)($addStudentForm['program'] ?? $staticProgramLabel)); ?>" readonly>
          <div class="adv-add-help"></div>
        </div>

        <div class="adv-add-field full">
          <label class="adv-add-label" for="addStudentAcademicYear">Academic Year</label>
          <input id="addStudentAcademicYear" class="adv-add-input" type="text" name="academic_year" placeholder="System generated" value="<?php echo adviser_students_escape((string)($addStudentForm['academic_year'] ?? '')); ?>" required readonly>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['academic_year'] ?? ''); ?></div>
        </div>
      </div>

      <div class="adv-add-actions">
        <button type="button" class="adv-btn is-secondary" onclick="closeAddStudentModal()">Cancel</button>
        <button id="addStudentSubmitBtn" type="submit" class="adv-btn"><i class="fas fa-user-plus"></i>Add Student</button>
      </div>
    </form>
  </div>
</div>

<div id="bulkImportModal" class="adv-modal-overlay<?php echo $shouldOpenBulkImportModal ? ' open' : ''; ?>">
  <div class="adv-add-modal is-wide" role="dialog" aria-modal="true" aria-labelledby="bulkImportTitle">
    <div class="adv-add-header">
      <div>
        <h2 id="bulkImportTitle" class="adv-add-title">Bulk Import Students</h2>
      </div>
      <button type="button" class="adv-add-close" onclick="closeBulkImportModal()">&times;</button>
    </div>

    <?php if ($bulkImportResult !== null): ?>
      <div class="adv-bulk-summary">
        <strong><?php echo adviser_students_escape($bulkImportSummaryMessage !== '' ? $bulkImportSummaryMessage : 'Bulk import processed.'); ?></strong>
        <div style="margin-top:6px;color:#334155;">
          Processed rows: <?php echo (int)($bulkImportResult['processed_rows'] ?? 0); ?>
        </div>
      </div>

      <?php if (!empty($bulkImportResult['errors'])): ?>
        <div class="adv-add-error" style="margin-bottom:12px;">
          <strong>Rows with errors</strong>
          <ul class="adv-bulk-list">
            <?php foreach ((array)$bulkImportResult['errors'] as $bulkError): ?>
              <li><?php echo adviser_students_escape((string)$bulkError); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($bulkImportResult['warnings'])): ?>
        <div class="adv-bulk-note" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
          <strong>Warnings</strong>
          <ul class="adv-bulk-list is-warning">
            <?php foreach ((array)$bulkImportResult['warnings'] as $bulkWarning): ?>
              <li><?php echo adviser_students_escape((string)$bulkWarning); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form id="bulkImportForm" method="post" action="<?php echo $baseUrl; ?>/layout.php?page=adviser/students" enctype="multipart/form-data">
      <input type="hidden" name="action" value="bulk_add_students">

      <div class="adv-add-grid">
        <div class="adv-add-field full">
          <label class="adv-add-label" for="bulkStudentsCsv">CSV / Excel File</label>
          <input id="bulkStudentsCsv" class="adv-add-input" type="file" name="bulk_students_csv" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
          <div class="adv-add-help"></div>
        </div>
      </div>

      <div class="adv-bulk-note">
        CSV or Excel header format: <strong>student_number,first_name,last_name</strong>. Maximum 500 non-empty rows per upload.
      </div>
      <div class="adv-bulk-note" style="margin-top:8px;background:#f0f9ff;border-color:#bae6fd;color:#0c4a6e;">
        Student accounts are created first, then credentials emails are sent automatically after bulk creation completes.
      </div>
      <div class="adv-bulk-note" style="margin-top:8px;">
        Need a sample file?
        <a href="<?php echo $baseUrl; ?>/assets/templates/adviser_students_bulk_template.csv" download style="font-weight:700;color:#1d4ed8;text-decoration:underline;">Download CSV template</a>
      </div>

      <div class="adv-add-actions">
        <button type="button" class="adv-btn is-secondary" onclick="closeBulkImportModal()">Cancel</button>
        <button id="bulkImportSubmitBtn" type="submit" class="adv-btn"><i class="fas fa-file-import"></i>Import Students</button>
      </div>
    </form>
  </div>
</div>

<div id="requirementsModal" style="position:fixed;inset:0;background:rgba(0,0,0,.40);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1200;padding:16px;">
  <div style="background:#fff;width:720px;max-width:100%;border-radius:22px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:24px;max-height:90vh;overflow:auto;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;gap:16px;">
      <div>
        <h2 id="requirementsTitle" style="font-size:1.05rem;font-weight:700;margin:0;color:#111827;">Student - Local OJT Requirements Checklist</h2>
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
  syncAutoStudentEmail();
}

function closeAddStudentModal() {
    var modal = document.getElementById('addStudentModal');
    if (!modal) return;
    modal.classList.remove('open');
}

function openBulkImportModal() {
  var modal = document.getElementById('bulkImportModal');
  if (!modal) return;
  modal.classList.add('open');
}

function closeBulkImportModal() {
  var modal = document.getElementById('bulkImportModal');
  if (!modal) return;
  modal.classList.remove('open');
}

function bindAddStudentFormSubmission() {
  var form = document.getElementById('addStudentForm');
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (event) {
    if (form.dataset.submitting === '1') {
      event.preventDefault();
      return;
    }

    form.dataset.submitting = '1';

    var submitButton = document.getElementById('addStudentSubmitBtn');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    }

    closeAddStudentModal();
  });
}

function bindBulkImportFormSubmission() {
  var form = document.getElementById('bulkImportForm');
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (event) {
    if (form.dataset.submitting === '1') {
      event.preventDefault();
      return;
    }

    form.dataset.submitting = '1';

    var submitButton = document.getElementById('bulkImportSubmitBtn');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
    }
  });
}

  function buildStudentSchoolEmail(studentNumber) {
    var value = String(studentNumber || '').trim().replace(/\s+/g, '');
    if (!value) {
      return '';
    }
    return value.toLowerCase() + '@g.batstate-u.edu.ph';
  }

  function syncAutoStudentEmail() {
    var studentNumberInput = document.getElementById('addStudentNumber');
    var emailInput = document.getElementById('addStudentEmail');
    if (!studentNumberInput || !emailInput) {
      return;
    }

    emailInput.value = buildStudentSchoolEmail(studentNumberInput.value);
  }

  function bindAddStudentEmailAutomation() {
    var studentNumberInput = document.getElementById('addStudentNumber');
    if (!studentNumberInput) {
      return;
    }

    studentNumberInput.addEventListener('input', syncAutoStudentEmail);
    studentNumberInput.addEventListener('blur', syncAutoStudentEmail);
    studentNumberInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        syncAutoStudentEmail();
        var firstNameInput = document.getElementById('addStudentFirstName');
        if (firstNameInput) {
          firstNameInput.focus();
        }
      }
    });

    syncAutoStudentEmail();
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
  var hasAnyRows = false;

    orderedPhases.forEach(function (phaseName) {
        var phaseRows = (phases && phases[phaseName]) ? phases[phaseName] : [];

      if (!phaseRows.length) {
        return;
      }

      hasAnyRows = true;

        phaseRows.forEach(function (item) {
            var isSubmitted = !!item.is_submitted;
            var canToggleItem = item.can_toggle !== false;
            var boxBorder = isSubmitted ? '#bbf7d0' : '#e5e7eb';
            var boxBg = isSubmitted ? '#f0fdf4' : '#fff';
            var statusColor = isSubmitted ? '#16a34a' : '#ef4444';
            var statusText = item.status || (isSubmitted ? 'Submitted' : 'Pending');
            var dateText = item.date_label ? item.date_label : statusText;
            var requirementId = Number(item.requirement_id || 0);
            var requirementKey = String(item.requirement_key || '');

            html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid ' + boxBorder + ';background:' + boxBg + ';border-radius:14px;padding:12px 14px;">';
            html += '<div style="display:flex;align-items:center;gap:10px;">';
            html += '<input type="checkbox" class="js-requirement-checkbox" data-requirement-id="' + requirementId + '" data-requirement-key="' + escapeHtml(requirementKey) + '" ' + (isSubmitted ? 'checked ' : '') + (canToggleItem ? '' : 'disabled ') + 'style="width:18px;height:18px;' + (canToggleItem ? 'cursor:pointer;' : 'cursor:default;') + (isSubmitted ? 'accent-color:#22c55e;' : '') + '">';
            html += '<p style="font-size:.85rem;margin:0;color:#111827;">' + escapeHtml(item.name || 'Requirement') + '</p>';
            html += '</div>';
            html += '<div style="display:flex;align-items:center;gap:8px;font-size:.72rem;flex-wrap:wrap;justify-content:flex-end;">';
            html += '<span style="background:#e0e7ff;color:#4f46e5;padding:4px 8px;border-radius:999px;font-weight:700;">' + escapeHtml(item.phase || phaseName) + '</span>';
            html += '<span style="color:' + statusColor + ';font-weight:600;">' + escapeHtml(dateText) + '</span>';
            html += '</div>';
            html += '</div>';
        });
    });

    if (!hasAnyRows) {
        html += '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:12px 14px;color:#9ca3af;font-size:.8rem;">No requirements found.</div>';
    }

    container.innerHTML = html;

    var checkboxes = container.querySelectorAll('.js-requirement-checkbox');
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            toggleRequirementCheckbox(checkbox);
        });
    });
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
          var contextInternshipId = Number((payload && payload.internship_id_context) || 0);
          requirementsContext.internshipId = contextInternshipId > 0 ? String(contextInternshipId) : '';
            setRequirementsSummary(payload.summary || {});
            renderRequirementsChecklist(payload.phases || {});
        })
        .catch(function (error) {
            setRequirementsErrorStateWithMessage(error && error.message ? error.message : 'Unable to load requirements right now.');
        });
}

function toggleRequirementCheckbox(checkbox) {
    var requirementId = Number(checkbox.getAttribute('data-requirement-id') || '0');
  var requirementKey = String(checkbox.getAttribute('data-requirement-key') || '').trim();

  if (requirementsContext.studentId <= 0 || (requirementId <= 0 && requirementKey === '')) {
        checkbox.checked = !checkbox.checked;
        return;
    }

    checkbox.disabled = true;

    var formBody = new URLSearchParams();
    formBody.append('action', 'toggle_requirement');
    formBody.append('student_id', String(requirementsContext.studentId));
    formBody.append('internship_id', requirementsContext.internshipId || '');
    formBody.append('requirement_id', String(requirementId));
    formBody.append('requirement_key', requirementKey);
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
          var contextInternshipId = Number((payload && payload.internship_id_context) || 0);
          requirementsContext.internshipId = contextInternshipId > 0 ? String(contextInternshipId) : '';
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
    if (titleEl) titleEl.textContent = name + ' - Local OJT Requirements Checklist';
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

  var bulkImportModal = document.getElementById('bulkImportModal');
  if (bulkImportModal && event.target === bulkImportModal) {
    closeBulkImportModal();
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
    closeBulkImportModal();
        closeRequirementsModal();
    }
});

bindAddStudentEmailAutomation();
bindAddStudentFormSubmission();
bindBulkImportFormSubmission();
</script>
