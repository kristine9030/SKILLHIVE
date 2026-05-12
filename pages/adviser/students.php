<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/students/data.php';
require_once __DIR__ . '/students/add_student_action.php';
require_once __DIR__ . '/students/school_years_query.php';
require_once __DIR__ . '/students/filters_query.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

// Get selected school year and options
$selectedSchoolYear = adviser_students_get_selected_school_year($pdo);
$schoolYearOptions = adviser_students_get_school_year_options($pdo);
$selectedTab = trim((string)($_GET['school_tab'] ?? 'active'));

$currentFilters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'department' => trim((string)($_GET['department'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'account_status' => trim((string)($_GET['account_status'] ?? '')),
];

$pageData = [
    'selected' => ['search' => '', 'department' => '', 'status' => ''],
    'filter_options' => ['departments' => [], 'statuses' => []],
    'rows' => [],
];

if ($adviserId > 0 && $selectedSchoolYear['id'] > 0) {
    try {
        // Get tab-specific data with school year filtering
        $pageData['rows'] = adviser_students_get_tab_students(
            $pdo,
            $adviserId,
            $selectedSchoolYear['id'],
            $selectedTab,
            $currentFilters
        );
        
        // Get filter options for current school year
        $pageData['selected'] = [
            'search' => $currentFilters['search'],
            'department' => $currentFilters['department'],
            'status' => $currentFilters['status'],
        ];
        
        $pageData['filter_options'] = [
            'departments' => adviser_students_get_filter_options($pdo, $adviserId)['departments'] ?? [],
            'statuses' => adviser_students_get_filter_options($pdo, $adviserId)['statuses'] ?? [],
        ];
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
$accountStatusActionResult = null;
$staticProgramLabel = adviser_students_static_program_label();
$staticDepartmentLabel = adviser_students_static_department_label();
$trackOptions = adviser_students_track_options();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'update_account_status') {
    $targetStudentId = (int)($_POST['student_id'] ?? 0);
    $newStatus       = trim((string)($_POST['new_status'] ?? ''));
    $reason          = trim((string)($_POST['reason'] ?? ''));

    $accountStatusActionResult = adviser_account_status_update($pdo, $adviserId, $targetStudentId, $newStatus, $reason);

    if ($accountStatusActionResult['success']) {
        $statusLabel = $accountStatusActionResult['new_status'];
        $_SESSION['status'] = 'Student account status updated to ' . $statusLabel . '.';
        header('Location: ' . $baseUrl . '/layout.php?page=adviser/students');
        exit;
    }
    // On failure, fall through to re-render page with the error stored in $accountStatusActionResult.
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
  .adv-req-count { font-size:.94rem; font-weight:700; color:#12b3ac; }
  .adv-req-count.is-success { color:#16a34a; }
  .adv-req-link { padding:0; border:0; background:transparent; color:#dc2626; font-size:.86rem; font-weight:700; text-decoration:underline; cursor:pointer; }
  .adv-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .adv-row-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:34px; padding:0 16px; border-radius:999px; border:1px solid transparent; background:#111; color:#fff; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; }
  .adv-row-btn.is-icon { width:38px; min-width:38px; padding:0; border-color:var(--border); background:var(--card); color:var(--text); }
  .adv-row-btn.is-icon[aria-disabled="true"] { opacity:.55; cursor:default; }
  .adv-empty { padding:34px 18px; text-align:center; color:var(--text3); font-size:.9rem; }
  /* ── Account Status Badges ─────────────────────────────────────── */
  .acct-badge { display:inline-flex; align-items:center; gap:5px; min-height:24px; padding:2px 10px; border-radius:999px; font-size:.72rem; font-weight:700; letter-spacing:.02em; white-space:nowrap; }
  .acct-badge--active   { background:#dcfce7; color:#15803d; }
  .acct-badge--inactive { background:#fef3c7; color:#b45309; }
  .acct-badge--archived { background:#f3f4f6; color:#6b7280; }
  /* ── Account Status Modal ──────────────────────────────────────── */
  .acct-modal { width:min(480px,100%); background:#fff; border-radius:24px; box-shadow:0 24px 70px rgba(15,23,42,.26); padding:26px 28px; border:1px solid rgba(229,231,235,.9); }
  .acct-modal-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
  .acct-modal-title { margin:0; font-size:1.1rem; font-weight:800; color:var(--text); }
  .acct-modal-sub { margin:4px 0 0; font-size:.85rem; color:var(--text3); }
  .acct-modal-close { width:34px; height:34px; flex:0 0 auto; border-radius:999px; border:1px solid var(--border); background:var(--card); color:var(--text3); font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  .acct-status-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin:16px 0; }
  .acct-status-option { position:relative; }
  .acct-status-option input[type="radio"] { position:absolute; opacity:0; pointer-events:none; }
  .acct-status-label { display:flex; flex-direction:column; align-items:center; gap:6px; padding:14px 10px; border-radius:16px; border:2px solid var(--border); background:var(--card); cursor:pointer; transition:border-color .15s, background .15s; text-align:center; }
  .acct-status-option input:checked + .acct-status-label { border-color:#111; background:#f8f8f8; }
  .acct-status-label i { font-size:1.3rem; }
  .acct-status-label .acct-opt-name { font-size:.82rem; font-weight:700; color:var(--text); }
  .acct-status-label .acct-opt-desc { font-size:.72rem; color:var(--text3); line-height:1.3; }
  .acct-status-option.opt-active   .acct-status-label i { color:#15803d; }
  .acct-status-option.opt-inactive .acct-status-label i { color:#b45309; }
  .acct-status-option.opt-archived .acct-status-label i { color:#6b7280; }
  .acct-reason-group { margin-top:12px; }
  .acct-reason-label { display:block; font-size:.83rem; font-weight:700; color:var(--text); margin-bottom:6px; }
  .acct-reason-input { width:100%; border:1px solid var(--border); border-radius:12px; background:var(--card); color:var(--text); font-size:.88rem; padding:10px 14px; outline:0; box-sizing:border-box; resize:vertical; min-height:70px; }
  .acct-modal-footer { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
  .acct-warn-box { background:#fef3c7; border:1px solid #fde68a; border-radius:10px; padding:10px 14px; margin-top:12px; font-size:.81rem; color:#92400e; display:flex; gap:8px; align-items:flex-start; }
  .acct-warn-box i { flex:0 0 auto; margin-top:1px; }
  .adv-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.35); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; z-index:1250; padding:18px; }
  .adv-modal-overlay.open { display:flex; }
  .adv-add-modal { width:min(520px,100%); background:#fff; border-radius:24px; box-shadow:0 24px 70px rgba(15,23,42,.26); padding:26px 28px; border:1px solid rgba(229,231,235,.9); }
  .adv-add-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
  .adv-add-title { margin:0; font-size:1rem; font-weight:800; color:#050505; }
  .adv-add-close { width:36px; height:36px; border:0; background:transparent; color:#a1a1aa; font-size:1.55rem; line-height:1; cursor:pointer; }
  .adv-add-error { margin-bottom:14px; padding:12px 14px; border-radius:14px; border:1px solid #fecaca; background:#fff1f2; color:#12b3ac; font-size:.82rem; }
  .adv-add-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 16px; }
  .adv-add-field { display:flex; flex-direction:column; gap:8px; }
  .adv-add-field.full { grid-column:1 / -1; }
  .adv-add-label { font-size:.82rem; font-weight:700; color:#27272a; }
  .adv-add-input, .adv-add-select { width:100%; height:42px; border:1px solid #e5e7eb; border-radius:14px; background:#fff; padding:0 14px; font-size:.91rem; color:#050505; outline:0; }
  .adv-add-input[readonly] { background:#ffffff; color:#4b5563; cursor:default; }
  .adv-add-input::placeholder { color:#a1a1aa; }
  .adv-add-input:focus, .adv-add-select:focus { border-color:#050505; box-shadow:0 0 0 3px rgba(17,24,39,.05); }
  .adv-add-help { min-height:16px; font-size:.74rem; color:#dc2626; }
  .adv-add-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top:18px; }
  .adv-add-modal.is-wide { width:min(680px,100%); }
  .adv-bulk-note { margin-top:10px; padding:10px 12px; border-radius:12px; background:#ffffff; border:1px solid #e2e8f0; color:#334155; font-size:.8rem; line-height:1.5; }
  .adv-bulk-summary { margin-bottom:12px; padding:12px 14px; border-radius:14px; border:1px solid #bfdbfe; background:#eff6ff; color:#12b3ac; font-size:.82rem; }
  .adv-bulk-summary strong { color:#12b3ac; }
  .adv-bulk-list { margin:10px 0 0; padding-left:18px; max-height:170px; overflow:auto; color:#7f1d1d; font-size:.79rem; line-height:1.45; }
  .adv-bulk-list.is-warning { color:#92400e; }
  @media (max-width:760px) {
    .adv-toolbar,
    .adv-toolbar-left,
    .adv-toolbar-right {
      align-items:stretch;
      flex-direction:column;
      width:100%;
    }
    .adv-search,
    .adv-select,
    .adv-btn {
      width:100%;
      max-width:none;
      min-width:0;
    }
    .adv-card {
      border-radius:14px;
      background:transparent;
      border:0;
      box-shadow:none;
      overflow:visible;
    }
    .adv-table-wrap {
      overflow:visible;
    }
    .adv-table,
    .adv-table tbody,
    .adv-table tr,
    .adv-table td {
      display:block;
      width:100%;
      min-width:0;
    }
    .adv-table {
      border-collapse:separate;
      border-spacing:0 12px;
    }
    .adv-table thead {
      display:none;
    }
    .adv-table tr {
      border:1px solid var(--border);
      border-radius:14px;
      overflow:hidden;
      background:#fff;
    }
    .adv-table td {
      display:grid;
      grid-template-columns:96px minmax(0, 1fr);
      gap:10px;
      padding:12px 14px;
      border-bottom:1px solid var(--border);
      background:#fff;
      text-align:left;
    }
    .adv-table td:last-child {
      border-bottom:0;
    }
    .adv-table td::before {
      color:var(--text3);
      font-size:.72rem;
      font-weight:700;
      letter-spacing:.04em;
      padding-top:3px;
      text-transform:uppercase;
    }
    .adv-table td:nth-child(1)::before { content:"Student"; }
    .adv-table td:nth-child(2)::before { content:"Section"; }
    .adv-table td:nth-child(3)::before { content:"Company"; }
    .adv-table td:nth-child(4)::before { content:"Hours"; }
    .adv-table td:nth-child(5)::before { content:"Requirements"; }
    .adv-table td:nth-child(6)::before { content:"Actions"; }
    .adv-table td[colspan] {
      display:block;
      text-align:center;
    }
    .adv-table td[colspan]::before {
      display:none;
    }
    .adv-student,
    .adv-actions,
    .adv-req {
      min-width:0;
    }
    .adv-actions {
      align-items:stretch;
    }
    .adv-row-btn {
      flex:1 1 120px;
    }
    .adv-row-btn.is-icon {
      flex:0 0 38px;
    }
    .adv-modal-overlay {
      align-items:flex-start;
      overflow-y:auto;
      padding:12px;
    }
    .adv-add-grid,
    .acct-status-grid {
      grid-template-columns:1fr;
    }
    .adv-add-modal,
    .acct-modal {
      width:100%;
      padding:22px 18px;
      border-radius:20px;
    }
    .adv-add-actions,
    .acct-modal-footer {
      flex-direction:column-reverse;
    }
    .adv-add-actions .adv-btn,
    .acct-modal-footer .adv-btn {
      width:100%;
    }
  }
</style>

<div class="adv-students">
  <!-- Adviser Students Banner -->
  <div style="background:linear-gradient(90deg, #050505 0%, #12b3ac 40%, rgba(0, 0, 0, 0.38) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:20px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(0, 0, 0, 0.44);">
    <div style="z-index:2;flex:1;">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;">
        <h2 style="font-size:1.8rem;font-weight:900;margin:0;line-height:1.2;color:white;">Empower Your Students</h2>
        <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);padding:8px 12px;border-radius:999px;backdrop-filter:blur(8px);">
          <select id="schoolYearSelector" class="adv-select" style="background:transparent;border:0;color:white;padding:0;height:auto;font-size:0.9rem;font-weight:600;appearance:none;background-image:linear-gradient(45deg,transparent 50%,#fff 50%),linear-gradient(135deg,#fff 50%,transparent 50%);background-position:calc(100% - 12px) calc(50% - 2px),calc(100% - 7px) calc(50% - 2px);background-size:5px 5px,5px 5px;background-repeat:no-repeat;padding-right:24px;" onchange="selectSchoolYear(this.value)">
            <?php foreach ($schoolYearOptions['active'] as $option): ?>
              <option value="<?php echo (int)$option['id']; ?>" <?php echo $selectedSchoolYear['id'] === $option['id'] ? 'selected' : ''; ?>>
                <?php echo adviser_students_escape($option['school_year']); ?> (Current)
              </option>
            <?php endforeach; ?>
            <?php if (!empty($schoolYearOptions['archived'])): ?>
              <optgroup label="Archived">
                <?php foreach ($schoolYearOptions['archived'] as $option): ?>
                  <option value="<?php echo (int)$option['id']; ?>" <?php echo $selectedSchoolYear['id'] === $option['id'] ? 'selected' : ''; ?>>
                    <?php echo adviser_students_escape($option['school_year']); ?> (Archived)
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
        </div>
      </div>
      <p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Guide students through their journey, provide endorsements, monitor progress, and help them succeed in their internship placements.</p>
    </div>
  </div>

  <form class="adv-toolbar" method="get" action="<?php echo $baseUrl; ?>/layout.php">
    <input type="hidden" name="page" value="adviser/students">

    <div class="adv-toolbar-left">
      <label class="adv-search" aria-label="Search students">
        <i class="fas fa-search"></i>
        <input id="advStudentSearchInput" type="text" name="search" placeholder="Search students..." value="<?php echo adviser_students_escape($selected['search'] ?? ''); ?>">
      </label>

      <select id="advStudentSectionFilter" class="adv-select" name="department" aria-label="Filter by section">
        <option value="">All Section</option>
        <?php foreach (($filterOptions['departments'] ?? []) as $departmentOption): ?>
          <option value="<?php echo adviser_students_escape($departmentOption); ?>" <?php echo ($selected['department'] ?? '') === $departmentOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($departmentOption); ?></option>
        <?php endforeach; ?>
      </select>

      <select id="advStudentStatusFilter" class="adv-select" name="status" aria-label="Filter by status">
        <option value="">All Status</option>
        <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
          <option value="<?php echo adviser_students_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_students_escape($statusOption); ?></option>
        <?php endforeach; ?>
      </select>

      <select id="advStudentAccountStatusFilter" class="adv-select" name="account_status" aria-label="Filter by account status">
        <option value="">All Account Status</option>
        <option value="Active" <?php echo ($currentFilters['account_status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
        <option value="Inactive" <?php echo ($currentFilters['account_status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
        <option value="Archived" <?php echo ($currentFilters['account_status'] ?? '') === 'Archived' ? 'selected' : ''; ?>>Archived</option>
      </select>
    </div>

    <div class="adv-toolbar-right">
      <button class="adv-btn is-secondary" type="button" onclick="openManageSchoolYearsModal()"><i class="fas fa-cog"></i>Manage School Years</button>
      <button class="adv-btn is-secondary" type="button" onclick="openBulkImportModal()"><i class="fas fa-file-upload"></i>Bulk Import</button>
      <button class="adv-btn" type="button" onclick="openAddStudentModal()"><i class="fas fa-user-plus"></i>Add Student</button>
    </div>
  </form>

  <div class="adv-card">
    <!-- Student Tabs -->
    <div style="display:flex;gap:2px;border-bottom:1px solid var(--border);padding:0;margin-bottom:0;">
      <button type="button" class="js-student-tab-btn" data-tab="active" onclick="switchStudentTab(this, 'active')" style="flex:1;padding:14px 18px;border:0;background:<?php echo $selectedTab === 'active' ? 'transparent' : 'transparent'; ?>;color:<?php echo $selectedTab === 'active' ? '#111' : '#999'; ?>;font-weight:<?php echo $selectedTab === 'active' ? '700' : '600'; ?>;font-size:0.95rem;cursor:pointer;border-bottom:3px solid <?php echo $selectedTab === 'active' ? '#12b3ac' : 'transparent'; ?>;transition:all 0.2s ease;">
        <i class="fas fa-users" style="margin-right:6px;"></i> Active Students
      </button>
      <button type="button" class="js-student-tab-btn" data-tab="archived" onclick="switchStudentTab(this, 'archived')" style="flex:1;padding:14px 18px;border:0;background:transparent;color:<?php echo $selectedTab === 'archived' ? '#111' : '#999'; ?>;font-weight:<?php echo $selectedTab === 'archived' ? '700' : '600'; ?>;font-size:0.95rem;cursor:pointer;border-bottom:3px solid <?php echo $selectedTab === 'archived' ? '#12b3ac' : 'transparent'; ?>;transition:all 0.2s ease;">
        <i class="fas fa-archive" style="margin-right:6px;"></i> Archived Students
      </button>
      <button type="button" class="js-student-tab-btn" data-tab="alumni" onclick="switchStudentTab(this, 'alumni')" style="flex:1;padding:14px 18px;border:0;background:transparent;color:<?php echo $selectedTab === 'alumni' ? '#111' : '#999'; ?>;font-weight:<?php echo $selectedTab === 'alumni' ? '700' : '600'; ?>;font-size:0.95rem;cursor:pointer;border-bottom:3px solid <?php echo $selectedTab === 'alumni' ? '#12b3ac' : 'transparent'; ?>;transition:all 0.2s ease;">
        <i class="fas fa-graduation-cap" style="margin-right:6px;"></i> Alumni Interns
      </button>
    </div>

    <div class="adv-table-wrap">
      <table class="adv-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Section</th>
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
              $trackLabel = trim((string)($row['track'] ?? ''));
              $sectionLabel = trim((string)($row['section'] ?? ''));
              $sectionDisplay = adviser_students_section_label($trackLabel, $sectionLabel);
              $statusLabel = (string)($row['status_label'] ?? 'No OJT');
              $yearProgram = 'Academic Year: ' . $academicYearLabel . ' - ' . trim((string)($row['program'] ?? 'N/A'));
              if ($trackLabel !== '') {
                $yearProgram .= ' - ' . $trackLabel;
              }
              $hoursCompleted = (float)($row['hours_completed'] ?? 0);
              $hoursRequired = (float)($row['hours_required'] ?? 0);
              $totalRequirements = (int)($row['total_requirements'] ?? 0);
              $requirementsSubmitted = (int)($row['requirements_submitted'] ?? 0);
              $requirementsPending = (int)($row['requirements_pending'] ?? 0);
              $requirementsCompletion = (int)($row['requirements_completion'] ?? 0);
              $internshipId = isset($row['internship_id']) ? (int)$row['internship_id'] : 0;
              $accountStatus = (string)($row['account_status'] ?? 'Active');
              $accountStatusReason = (string)($row['account_status_reason'] ?? '');
              $searchText = implode(' ', [
                $studentName,
                (string)($row['student_number'] ?? ''),
                (string)($row['email'] ?? ''),
                (string)($row['program'] ?? ''),
                $trackLabel,
                $sectionDisplay,
                $companyName,
                $internshipTitle,
                $statusLabel,
              ]);
              $buttonId = 'requirements-trigger-' . $studentId;
              ?>
              <tr class="js-student-row" data-search="<?php echo adviser_students_escape(strtolower($searchText)); ?>" data-section="<?php echo adviser_students_escape($sectionDisplay); ?>" data-status="<?php echo adviser_students_escape($statusLabel); ?>">
                <td>
                  <div class="adv-student">
                    <span class="adv-avatar" style="background:<?php echo adviser_students_escape(adviser_students_avatar_gradient($studentId)); ?>;"><?php echo adviser_students_escape($row['initials'] ?? 'NA'); ?></span>
                    <div>
                      <p class="adv-name"><?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Unnamed Student'); ?></p>
                      <p class="adv-subtext"><?php echo adviser_students_escape($yearProgram); ?></p>
                      <?php if ($accountStatus !== 'Active'): ?>
                        <span class="acct-badge <?php echo adviser_students_escape(adviser_students_account_status_badge_class($accountStatus)); ?>" title="<?php echo adviser_students_escape($accountStatusReason); ?>">
                          <i class="fas <?php echo adviser_students_escape(adviser_students_account_status_icon($accountStatus)); ?>"></i>
                          <?php echo adviser_students_escape($accountStatus); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><span class="adv-dept"><?php echo adviser_students_escape($sectionDisplay); ?></span></td>
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
                    <button
                      class="adv-row-btn is-icon js-open-acct-status-btn"
                      type="button"
                      title="Manage Account Status"
                      onclick="openAccountStatusModal(this)"
                      data-student-id="<?php echo $studentId; ?>"
                      data-student-name="<?php echo adviser_students_escape($studentName !== '' ? $studentName : 'Student'); ?>"
                      data-current-status="<?php echo adviser_students_escape($accountStatus); ?>"
                      data-current-reason="<?php echo adviser_students_escape($accountStatusReason); ?>"
                      data-completion-status="<?php echo adviser_students_escape((string)($row['completion_status'] ?? '')); ?>"
                    >
                      <i class="fas fa-user-shield"></i>
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
          <?php if (!empty($rows)): ?>
            <tr id="advStudentNoLiveMatches" style="display:none;">
              <td colspan="6" class="adv-empty">No students match your search or filters.</td>
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
          <label class="adv-add-label" for="addStudentProgram">Program</label>
          <input id="addStudentProgram" class="adv-add-input" type="text" name="program" value="<?php echo adviser_students_escape((string)($addStudentForm['program'] ?? $staticProgramLabel)); ?>" readonly>
          <div class="adv-add-help"></div>
        </div>

        <input type="hidden" name="department" value="<?php echo adviser_students_escape((string)($addStudentForm['department'] ?? $staticDepartmentLabel)); ?>">

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentTrack">Track</label>
          <select id="addStudentTrack" class="adv-add-select" name="track" required>
            <option value="">Select track</option>
            <?php foreach ($trackOptions as $trackOption): ?>
              <?php $trackValue = (string)($trackOption['value'] ?? ''); ?>
              <option value="<?php echo adviser_students_escape($trackValue); ?>" <?php echo (string)($addStudentForm['track'] ?? '') === $trackValue ? 'selected' : ''; ?>>
                <?php echo adviser_students_escape((string)($trackOption['label'] ?? $trackValue)); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['track'] ?? ''); ?></div>
        </div>

        <div class="adv-add-field">
          <label class="adv-add-label" for="addStudentSection">Section</label>
          <input id="addStudentSection" class="adv-add-input" type="text" name="section" placeholder="e.g. 01" value="<?php echo adviser_students_escape($addStudentForm['section'] ?? ''); ?>" required>
          <div class="adv-add-help"><?php echo adviser_students_escape($addStudentErrors['section'] ?? ''); ?></div>
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
        <a href="<?php echo $baseUrl; ?>/assets/templates/adviser_students_bulk_template.csv" download style="font-weight:700;color:#12b3ac;text-decoration:underline;">Download CSV template</a>
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
        <h2 id="requirementsTitle" style="font-size:1.05rem;font-weight:700;margin:0;color:#050505;">Student - Local OJT Requirements Checklist</h2>
        <p id="requirementsSubtitle" style="font-size:.82rem;color:#6b7280;margin:4px 0 0;">Program - Company</p>
      </div>
      <button type="button" onclick="closeRequirementsModal()" style="width:38px;height:38px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#9ca3af;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px;">
      <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:14px;padding:14px;text-align:center;">
        <p id="requirementsSubmitted" style="font-size:1.5rem;font-weight:700;color:#12b3ac;margin:0;">0</p>
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
      <div id="requirementsProgressBar" style="height:8px;border-radius:999px;background:linear-gradient(90deg,#12b3ac,#22c55e);width:0;"></div>
    </div>

    <div id="requirementsChecklist" style="display:flex;flex-direction:column;gap:10px;max-height:300px;overflow-y:auto;padding-right:4px;"></div>

    <div id="requirementsReviewNotice" style="display:none;background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:10px 14px;font-size:.8rem;color:#92400e;margin-top:14px;">
      <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>Please view or download all uploaded files before saving changes.
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;flex-wrap:wrap;">
      <button type="button" class="adv-btn is-secondary" onclick="closeRequirementsModal()">Close</button>
      <button id="requirementsSaveBtn" type="button" class="adv-btn" onclick="saveRequirementsChanges()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Account Status Modal
     Adviser manages Inactive / Archived / Active status.
     Students cannot log in or submit timesheets when
     Inactive or Archived. Adviser & employer retain
     full read-only access to all past records.
     ═══════════════════════════════════════════════════════ -->
<div id="accountStatusModal" class="adv-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="acctModalTitle">
  <div class="acct-modal">
    <div class="acct-modal-header">
      <div>
        <h3 class="acct-modal-title" id="acctModalTitle">Manage Account Status</h3>
        <p class="acct-modal-sub" id="acctModalSub">Choose the appropriate status for this student.</p>
      </div>
      <button type="button" class="acct-modal-close" onclick="closeAccountStatusModal()" aria-label="Close">&times;</button>
    </div>

    <?php if (!empty($accountStatusActionResult) && !$accountStatusActionResult['success']): ?>
      <div class="acct-warn-box" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars((string)($accountStatusActionResult['error'] ?? 'An error occurred.'), ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    <?php endif; ?>

    <form id="accountStatusForm" method="POST">
      <input type="hidden" name="action" value="update_account_status">
      <input type="hidden" name="student_id" id="acctStudentId" value="">

      <div class="acct-status-grid">
        <div class="acct-status-option opt-active">
          <input type="radio" name="new_status" id="acctStatusActive" value="Active">
          <label class="acct-status-label" for="acctStatusActive">
            <i class="fas fa-circle-check"></i>
            <span class="acct-opt-name">Active</span>
            <span class="acct-opt-desc">Student can log in and submit timesheets normally.</span>
          </label>
        </div>
        <div class="acct-status-option opt-inactive">
          <input type="radio" name="new_status" id="acctStatusInactive" value="Inactive">
          <label class="acct-status-label" for="acctStatusInactive">
            <i class="fas fa-ban"></i>
            <span class="acct-opt-name">Inactive</span>
            <span class="acct-opt-desc">Login and timesheet blocked. Use for drop/shift requests.</span>
          </label>
        </div>
        <div class="acct-status-option opt-archived">
          <input type="radio" name="new_status" id="acctStatusArchived" value="Archived">
          <label class="acct-status-label" for="acctStatusArchived">
            <i class="fas fa-box-archive"></i>
            <span class="acct-opt-name">Archived</span>
            <span class="acct-opt-desc">Login blocked. Use when OJT is complete or program ended.</span>
          </label>
        </div>
      </div>

      <div id="acctWarnBox" class="acct-warn-box" style="display:none;">
        <i class="fas fa-triangle-exclamation"></i>
        <span id="acctWarnText"></span>
      </div>

      <div class="acct-reason-group">
        <label class="acct-reason-label" for="acctReason">
          Reason <span style="font-weight:400;color:var(--text3);">(optional — shown to student on blocked login)</span>
        </label>
        <textarea class="acct-reason-input" id="acctReason" name="reason" maxlength="255" placeholder="e.g. Student filed a dropping request on May 5, 2026."></textarea>
      </div>

      <p style="font-size:.76rem;color:var(--text3);margin:10px 0 0;">
        <i class="fas fa-shield-halved" style="margin-right:4px;"></i>
        Adviser and employer always retain full <strong>read-only</strong> access to the student's past records, hours, and evaluations regardless of account status.
      </p>

      <div class="acct-modal-footer">
        <button type="button" class="adv-btn is-secondary" onclick="closeAccountStatusModal()">Cancel</button>
        <button type="submit" class="adv-btn" id="acctSaveBtn">
          <i class="fas fa-save"></i> Save Status
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Manage School Years Modal -->
<div id="manageSchoolYearsModal" class="adv-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="schoolYearsModalTitle">
  <div class="adv-add-modal is-wide">
    <div class="adv-add-header">
      <div>
        <h2 id="schoolYearsModalTitle" class="adv-add-title">Manage School Years</h2>
        <p style="font-size:0.82rem;color:#6b7280;margin:4px 0 0;">Create new school years, activate years, and manage archived records.</p>
      </div>
      <button type="button" class="adv-add-close" onclick="closeManageSchoolYearsModal()">&times;</button>
    </div>

    <!-- School Years List -->
    <div style="margin-bottom:20px;">
      <h3 style="font-size:0.95rem;font-weight:700;color:#050505;margin:0 0 12px;">School Years</h3>
      <div id="schoolYearsList" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;">
        <!-- Populated by JavaScript -->
      </div>
    </div>

    <!-- Create New School Year Section -->
    <div style="background:#f9fafb;border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:16px;">
      <h3 style="font-size:0.95rem;font-weight:700;color:#050505;margin:0 0 12px;">Create New School Year</h3>
      <div style="display:flex;gap:10px;align-items:flex-end;">
        <div style="flex:1;">
          <label style="display:block;font-size:0.82rem;font-weight:700;color:#050505;margin-bottom:6px;">School Year (Format: YYYY-YYYY)</label>
          <input id="newSchoolYearInput" type="text" placeholder="e.g. 2025-2026" style="width:100%;height:42px;border:1px solid var(--border);border-radius:10px;padding:0 12px;font-size:0.9rem;">
        </div>
        <button type="button" class="adv-btn" onclick="createNewSchoolYear()" style="height:42px;">
          <i class="fas fa-plus"></i> Create
        </button>
      </div>
    </div>

    <!-- Start New School Year Section -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:16px;margin-bottom:16px;">
      <h3 style="font-size:0.95rem;font-weight:700;color:#0c4a6e;margin:0 0 8px;">Start New School Year</h3>
      <p style="font-size:0.82rem;color:#0c4a6e;margin:0 0 12px;line-height:1.5;">This will archive the current school year, preserve all historical data, and make completed/dropped students viewable in archived records.</p>
      <div style="display:flex;gap:10px;align-items:flex-end;">
        <div style="flex:1;">
          <label style="display:block;font-size:0.82rem;font-weight:700;color:#0c4a6e;margin-bottom:6px;">New School Year (Format: YYYY-YYYY)</label>
          <input id="startNewYearInput" type="text" placeholder="e.g. 2025-2026" style="width:100%;height:42px;border:1px solid #bfdbfe;border-radius:10px;padding:0 12px;font-size:0.9rem;background:#f0f9ff;">
        </div>
        <button type="button" class="adv-btn" onclick="startNewSchoolYear()" style="height:42px;background:#0284c7;border-color:#0284c7;">
          <i class="fas fa-forward"></i> Start New Year
        </button>
      </div>
    </div>

    <div id="schoolYearsMessage" style="display:none;margin-bottom:12px;padding:12px 14px;border-radius:10px;font-size:0.82rem;"></div>

    <div style="display:flex;justify-content:flex-end;gap:10px;">
      <button type="button" class="adv-btn is-secondary" onclick="closeManageSchoolYearsModal()">Close</button>
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

/* ─── Account Status Modal ─────────────────────────────────── */
function openAccountStatusModal(btn) {
  var modal = document.getElementById('accountStatusModal');
  if (!modal) return;

  var studentId      = btn.getAttribute('data-student-id') || '';
  var studentName    = btn.getAttribute('data-student-name') || 'Student';
  var currentStatus  = btn.getAttribute('data-current-status') || 'Active';
  var currentReason  = btn.getAttribute('data-current-reason') || '';
  var completionSt   = (btn.getAttribute('data-completion-status') || '').toLowerCase();

  // Populate modal
  document.getElementById('acctStudentId').value = studentId;
  document.getElementById('acctModalSub').textContent = 'Managing account for: ' + studentName;
  document.getElementById('acctReason').value = currentReason;

  // Pre-select current status radio
  var radios = modal.querySelectorAll('input[name="new_status"]');
  radios.forEach(function(r) {
    r.checked = (r.value === currentStatus);
  });

  // Show contextual warning when completion_status is already dropped/completed
  updateAccountStatusWarning(completionSt, currentStatus);

  // Attach change listener to radios for live warning update
  radios.forEach(function(r) {
    r.addEventListener('change', function() {
      updateAccountStatusWarning(completionSt, r.value);
    });
  });

  modal.classList.add('open');
}

function updateAccountStatusWarning(completionStatus, selectedStatus) {
  var warnBox  = document.getElementById('acctWarnBox');
  var warnText = document.getElementById('acctWarnText');
  if (!warnBox || !warnText) return;

  var msg = '';
  if (selectedStatus === 'Inactive') {
    msg = 'Setting to Inactive will immediately block this student from logging in and submitting timesheets. Their adviser and employer records remain fully readable.';
  } else if (selectedStatus === 'Archived') {
    msg = 'Setting to Archived will permanently block login for this student. All past records, OJT hours, and evaluations remain readable by you and the employer.';
    if (completionStatus === 'completed') {
      msg = 'This student\'s OJT is marked Completed. Archiving is the recommended action. All past records are preserved for you and the employer.';
    }
  } else if (selectedStatus === 'Active' && (completionStatus === 'completed' || completionStatus === 'dropped')) {
    msg = 'Re-activating allows this student to log in and submit timesheets again. Use with care if OJT is already completed or dropped.';
  }

  if (msg) {
    warnText.textContent = msg;
    warnBox.style.display = 'flex';
  } else {
    warnBox.style.display = 'none';
  }
}

function closeAccountStatusModal() {
  var modal = document.getElementById('accountStatusModal');
  if (!modal) return;
  modal.classList.remove('open');
}

// Close modal on backdrop click
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('accountStatusModal');
    if (!modal) return;
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeAccountStatusModal();
    });
  });
})();

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

function bindStudentTableLiveFilters() {
  var toolbar = document.querySelector('.adv-toolbar');
  var searchInput = document.getElementById('advStudentSearchInput');
  var sectionFilter = document.getElementById('advStudentSectionFilter');
  var statusFilter = document.getElementById('advStudentStatusFilter');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.js-student-row'));
  var emptyRow = document.getElementById('advStudentNoLiveMatches');

  if (!searchInput || !sectionFilter || !statusFilter || rows.length === 0) {
    return;
  }

  if (toolbar) {
    toolbar.addEventListener('submit', function (event) {
      event.preventDefault();
      applyStudentTableLiveFilters();
    });
  }

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function applyStudentTableLiveFilters() {
    var query = normalize(searchInput.value);
    var selectedSection = normalize(sectionFilter.value);
    var selectedStatus = normalize(statusFilter.value);
    var visibleCount = 0;

    rows.forEach(function (row) {
      var haystack = normalize(row.getAttribute('data-search'));
      var rowSection = normalize(row.getAttribute('data-section'));
      var rowStatus = normalize(row.getAttribute('data-status'));
      var matchesSearch = query === '' || haystack.indexOf(query) !== -1;
      var matchesSection = selectedSection === '' || rowSection === selectedSection;
      var matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;
      var shouldShow = matchesSearch && matchesSection && matchesStatus;

      row.style.display = shouldShow ? '' : 'none';
      if (shouldShow) {
        visibleCount++;
      }
    });

    if (emptyRow) {
      emptyRow.style.display = visibleCount > 0 ? 'none' : '';
    }
  }

  searchInput.addEventListener('input', applyStudentTableLiveFilters);
  sectionFilter.addEventListener('change', applyStudentTableLiveFilters);
  statusFilter.addEventListener('change', applyStudentTableLiveFilters);
  applyStudentTableLiveFilters();
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

  /* ═════════════════════════════════════════════════════════ */
  /* SCHOOL YEAR MANAGEMENT */
  /* ═════════════════════════════════════════════════════════ */
  
  var schoolYearsApiEndpoint = '<?php echo $baseUrl; ?>/pages/adviser/students/school_years_api.php';

  function openManageSchoolYearsModal() {
    var modal = document.getElementById('manageSchoolYearsModal');
    if (!modal) return;
    modal.classList.add('open');
    loadSchoolYearsList();
  }

  function closeManageSchoolYearsModal() {
    var modal = document.getElementById('manageSchoolYearsModal');
    if (!modal) return;
    modal.classList.remove('open');
    clearSchoolYearsMessage();
  }

  function loadSchoolYearsList() {
    fetch(schoolYearsApiEndpoint + '?action=get_all', {
      credentials: 'same-origin'
    })
    .then(function(response) {
      return response.json().then(function(payload) {
        if (!response.ok || !payload || payload.success !== true) {
          throw new Error((payload && payload.message) ? payload.message : 'Failed to load school years');
        }
        return payload;
      });
    })
    .then(function(payload) {
      renderSchoolYearsList(payload.data || []);
    })
    .catch(function(error) {
      showSchoolYearsMessage('Error loading school years: ' + error.message, 'error');
    });
  }

  function renderSchoolYearsList(schoolYears) {
    var container = document.getElementById('schoolYearsList');
    if (!container) return;

    if (schoolYears.length === 0) {
      container.innerHTML = '<div style="padding:12px;color:#6b7280;font-size:0.82rem;">No school years created yet.</div>';
      return;
    }

    var html = '';
    schoolYears.forEach(function(year) {
      var isActive = year.status === 'Active';
      var statusBadge = isActive 
        ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:700;">Active</span>'
        : '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:700;">Archived</span>';

      html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff;">';
      html += '<div style="flex:1;">';
      html += '<p style="margin:0;font-size:0.95rem;font-weight:600;color:#050505;">' + escapeHtml(year.school_year) + '</p>';
      html += '<p style="margin:4px 0 0;font-size:0.75rem;color:#6b7280;">' + (year.student_count || 0) + ' students</p>';
      html += '</div>';
      html += '<div style="display:flex;align-items:center;gap:8px;">';
      html += statusBadge;
      if (!isActive) {
        html += '<button type="button" onclick="activateSchoolYear(' + year.id + ')" class="adv-btn" style="padding:0 12px;height:32px;font-size:0.8rem;"><i class="fas fa-check-circle"></i> Activate</button>';
      }
      html += '</div>';
      html += '</div>';
    });

    container.innerHTML = html;
  }

  function createNewSchoolYear() {
    var input = document.getElementById('newSchoolYearInput');
    if (!input || !input.value.trim()) {
      showSchoolYearsMessage('Please enter a school year in format YYYY-YYYY', 'error');
      return;
    }

    var formData = new URLSearchParams();
    formData.append('action', 'create');
    formData.append('school_year', input.value.trim());

    fetch(schoolYearsApiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: formData.toString()
    })
    .then(function(response) {
      return response.json().then(function(payload) {
        if (!response.ok || !payload || payload.success !== true) {
          var errorMsg = (payload && payload.message) ? payload.message : 'Failed to create school year';
          if (payload && payload.debug) {
            errorMsg += ' (' + payload.debug + ')';
          }
          throw new Error(errorMsg);
        }
        return payload;
      });
    })
    .then(function(payload) {
      showSchoolYearsMessage('School year created successfully!', 'success');
      input.value = '';
      loadSchoolYearsList();
    })
    .catch(function(error) {
      showSchoolYearsMessage('Error: ' + error.message, 'error');
    });
  }

  function activateSchoolYear(yearId) {
    if (!confirm('Activate this school year? The current active year will be archived.')) {
      return;
    }

    var formData = new URLSearchParams();
    formData.append('action', 'set_active');
    formData.append('school_year_id', yearId);

    fetch(schoolYearsApiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: formData.toString()
    })
    .then(function(response) {
      return response.json().then(function(payload) {
        if (!response.ok || !payload || payload.success !== true) {
          var errorMsg = (payload && payload.message) ? payload.message : 'Failed to activate school year';
          if (payload && payload.debug) {
            errorMsg += ' (' + payload.debug + ')';
          }
          throw new Error(errorMsg);
        }
        return payload;
      });
    })
    .then(function(payload) {
      showSchoolYearsMessage('School year activated! Reloading page...', 'success');
      setTimeout(function() {
        location.reload();
      }, 1500);
    })
    .catch(function(error) {
      showSchoolYearsMessage('Error: ' + error.message, 'error');
    });
  }

  function startNewSchoolYear() {
    var input = document.getElementById('startNewYearInput');
    if (!input || !input.value.trim()) {
      showSchoolYearsMessage('Please enter a school year in format YYYY-YYYY', 'error');
      return;
    }

    if (!confirm('Start new school year? This will:\n• Archive the current school year\n• Preserve all historical data\n• Make completed students viewable in archived records')) {
      return;
    }

    var formData = new URLSearchParams();
    formData.append('action', 'start_new');
    formData.append('school_year', input.value.trim());

    fetch(schoolYearsApiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: formData.toString()
    })
    .then(function(response) {
      return response.json().then(function(payload) {
        if (!response.ok || !payload || payload.success !== true) {
          throw new Error((payload && payload.message) ? payload.message : 'Failed to start new school year');
        }
        return payload;
      });
    })
    .then(function(payload) {
      showSchoolYearsMessage('New school year started successfully! Reloading page...', 'success');
      setTimeout(function() {
        location.reload();
      }, 1500);
    })
    .catch(function(error) {
      showSchoolYearsMessage('Error: ' + error.message, 'error');
    });
  }

  function selectSchoolYear(yearId) {
    if (!yearId || Number(yearId) <= 0) {
      return;
    }

    var formData = new URLSearchParams();
    formData.append('action', 'select');
    formData.append('school_year_id', yearId);

    fetch(schoolYearsApiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: formData.toString()
    })
    .then(function(response) {
      return response.json().then(function(payload) {
        if (!response.ok || !payload || payload.success !== true) {
          throw new Error((payload && payload.message) ? payload.message : 'Failed to select school year');
        }
        return payload;
      });
    })
    .then(function() {
      location.reload();
    })
    .catch(function(error) {
      console.error('Error selecting school year:', error);
    });
  }

  function switchStudentTab(btn, tab) {
    if (!btn) return;

    // Update active button state
    var buttons = document.querySelectorAll('.js-student-tab-btn');
    buttons.forEach(function(b) {
      b.style.color = b === btn ? '#111' : '#999';
      b.style.fontWeight = b === btn ? '700' : '600';
      b.style.borderBottomColor = b === btn ? '#12b3ac' : 'transparent';
    });

    // Reload page with new tab
    var url = new URL(window.location.href);
    url.searchParams.set('school_tab', tab);
    window.location.href = url.toString();
  }

  function showSchoolYearsMessage(message, type) {
    var messageEl = document.getElementById('schoolYearsMessage');
    if (!messageEl) return;

    var bgColor = type === 'error' ? '#fee2e2' : '#dcfce7';
    var borderColor = type === 'error' ? '#fca5a5' : '#bbf7d0';
    var textColor = type === 'error' ? '#991b1b' : '#15803d';

    messageEl.style.background = bgColor;
    messageEl.style.borderColor = borderColor;
    messageEl.style.border = '1px solid ' + borderColor;
    messageEl.style.color = textColor;
    messageEl.textContent = message;
    messageEl.style.display = 'block';

    setTimeout(function() {
      if (type === 'success') {
        messageEl.style.display = 'none';
      }
    }, 3000);
  }

  function clearSchoolYearsMessage() {
    var messageEl = document.getElementById('schoolYearsMessage');
    if (messageEl) {
      messageEl.style.display = 'none';
    }
  }

  function initializeSchoolYearManagement() {
    // Any initialization needed for school year management
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
var requirementsFileEndpoint = '<?php echo $baseUrl; ?>/pages/adviser/students/requirement_file.php';
var requirementsContext = {
    studentId: 0,
    internshipId: '',
    canEdit: false,
    activeButton: null,
    reviewedSubmissionIds: {}   // tracks which req_submission_ids the adviser has viewed/downloaded
};

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatFileSize(bytes) {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function markSubmissionReviewed(submissionId) {
    if (submissionId > 0) {
        requirementsContext.reviewedSubmissionIds[submissionId] = true;
        updateSaveButtonReviewState();
    }
}

function updateSaveButtonReviewState() {
    var container = document.getElementById('requirementsChecklist');
    var notice    = document.getElementById('requirementsReviewNotice');
    var saveBtn   = document.getElementById('requirementsSaveBtn');
    if (!container) return;

    // Collect all submission ids that have files and are submitted/checked
    var allReviewable = [];
    container.querySelectorAll('[data-submission-id]').forEach(function(el) {
        var sid = Number(el.getAttribute('data-submission-id') || 0);
        if (sid > 0) allReviewable.push(sid);
    });

    if (allReviewable.length === 0) {
        // No files to review — save is always allowed
        if (notice) notice.style.display = 'none';
        if (saveBtn) saveBtn.disabled = false;
        return;
    }

    var allReviewed = allReviewable.every(function(sid) {
        return !!requirementsContext.reviewedSubmissionIds[sid];
    });

    if (notice) notice.style.display = allReviewed ? 'none' : '';
    if (saveBtn) saveBtn.disabled = !allReviewed;
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

        // Phase label header
        html += '<div style="font-size:.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding:2px 4px;margin-top:4px;">' + escapeHtml(phaseName) + '</div>';

        phaseRows.forEach(function (item) {
            var isSubmitted   = !!item.is_submitted;
            var canToggleItem = item.can_toggle !== false;
            var hasFile       = !!item.has_file;
            var submissionId  = Number(item.req_submission_id || 0);
            var boxBorder     = isSubmitted ? '#bbf7d0' : '#e5e7eb';
            var boxBg         = isSubmitted ? '#f0fdf4' : '#fff';
            var statusColor   = isSubmitted ? '#16a34a' : '#9ca3af';
            var statusText    = item.status || (isSubmitted ? 'Submitted' : 'Pending');
            var dateText      = item.date_label ? item.date_label : statusText;
            var requirementId = Number(item.requirement_id || 0);
            var requirementKey = String(item.requirement_key || '');
            var alreadyReviewed = hasFile && submissionId > 0 && !!requirementsContext.reviewedSubmissionIds[submissionId];

            html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border:1px solid ' + boxBorder + ';background:' + boxBg + ';border-radius:14px;padding:12px 14px;">';

            // Left: checkbox + name
            html += '<div style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:0;">';
            html += '<input type="checkbox" class="js-requirement-checkbox" data-requirement-id="' + requirementId + '" data-requirement-key="' + escapeHtml(requirementKey) + '" '
                 + (isSubmitted ? 'checked ' : '')
                 + (canToggleItem ? '' : 'disabled ')
                 + 'style="width:18px;height:18px;margin-top:2px;flex-shrink:0;'
                 + (canToggleItem ? 'cursor:pointer;' : 'cursor:default;')
                 + (isSubmitted ? 'accent-color:#22c55e;' : '') + '">';
            html += '<div style="flex:1;min-width:0;">';
            html += '<p style="font-size:.85rem;margin:0 0 4px;color:#050505;font-weight:600;">' + escapeHtml(item.name || 'Requirement') + '</p>';

            // File badge + action buttons
            if (hasFile && submissionId > 0) {
                var viewUrl     = requirementsFileEndpoint + '?req_submission_id=' + submissionId + '&action=view';
                var downloadUrl = requirementsFileEndpoint + '?req_submission_id=' + submissionId + '&action=download';
                var fileName    = escapeHtml(item.file_name || 'file');
                var fileSize    = item.file_size ? ' (' + formatFileSize(item.file_size) + ')' : '';
                var reviewedBadge = alreadyReviewed
                    ? '<span style="background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:999px;font-size:.68rem;font-weight:700;"><i class="fas fa-check"></i> Reviewed</span>'
                    : '';

                html += '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-top:4px;">';
                html += '<i class="fas fa-paperclip" style="color:#6b7280;font-size:.75rem;"></i>';
                html += '<span style="font-size:.75rem;color:#374151;">' + fileName + fileSize + '</span>';
                html += reviewedBadge;
                html += '</div>';
                html += '<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">';
                html += '<a href="' + viewUrl + '" target="_blank" data-submission-id="' + submissionId + '" onclick="markSubmissionReviewed(' + submissionId + ')" '
                     + 'style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:600;color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:4px 10px;text-decoration:none;cursor:pointer;">'
                     + '<i class="fas fa-eye"></i> View</a>';
                html += '<a href="' + downloadUrl + '" download data-submission-id="' + submissionId + '" onclick="markSubmissionReviewed(' + submissionId + ')" '
                     + 'style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:600;color:#059669;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:4px 10px;text-decoration:none;cursor:pointer;">'
                     + '<i class="fas fa-download"></i> Download</a>';
                html += '</div>';
            } else if (isSubmitted) {
                html += '<p style="font-size:.73rem;color:#9ca3af;margin:2px 0 0;">No file uploaded by student.</p>';
            }

            html += '</div>';  // end name col
            html += '</div>';  // end left

            // Right: phase chip + status
            html += '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;font-size:.72rem;flex-shrink:0;">';
            html += '<span style="background:#e0e7ff;color:#4f46e5;padding:3px 9px;border-radius:999px;font-weight:700;white-space:nowrap;">' + escapeHtml(item.phase || phaseName) + '</span>';
            html += '<span style="color:' + statusColor + ';font-weight:600;white-space:nowrap;">' + escapeHtml(dateText) + '</span>';
            html += '</div>';

            html += '</div>';
        });
    });

    if (!hasAnyRows) {
        html += '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:14px;padding:12px 14px;color:#9ca3af;font-size:.8rem;">No requirements found.</div>';
    }

    container.innerHTML = html;

    // Attach change handlers to checkboxes
    container.querySelectorAll('.js-requirement-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            toggleRequirementCheckbox(checkbox);
        });
    });

    // Evaluate whether save should be enabled
    updateSaveButtonReviewState();
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
            // Reset reviewed state on fresh load so adviser must re-review on reload
            requirementsContext.reviewedSubmissionIds = {};
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
    container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:14px;padding:14px;color:#12b3ac;font-size:.82rem;">Unable to load requirements right now.</div>';
}

function setRequirementsErrorStateWithMessage(message) {
    var container = document.getElementById('requirementsChecklist');
    if (!container) return;
    container.innerHTML = '<div style="border:1px solid #fecaca;background:#fff1f2;border-radius:14px;padding:14px;color:#12b3ac;font-size:.82rem;">' + escapeHtml(message || 'Unable to load requirements right now.') + '</div>';
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
    requirementsContext.reviewedSubmissionIds = {};

    setRequirementsLoadingState();
    modal.style.display = 'flex';
    loadRequirementsData();
}

function saveRequirementsChanges() {
    // Guard: ensure all uploaded files have been reviewed/downloaded first
    var container = document.getElementById('requirementsChecklist');
    if (container) {
        var allReviewable = [];
        container.querySelectorAll('[data-submission-id]').forEach(function(el) {
            var sid = Number(el.getAttribute('data-submission-id') || 0);
            if (sid > 0) allReviewable.push(sid);
        });
        if (allReviewable.length > 0) {
            var allReviewed = allReviewable.every(function(sid) {
                return !!requirementsContext.reviewedSubmissionIds[sid];
            });
            if (!allReviewed) {
                var notice = document.getElementById('requirementsReviewNotice');
                if (notice) {
                    notice.style.display = '';
                    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return;
            }
        }
    }

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
    }, 300);
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
        closeManageSchoolYearsModal();
    }
});

bindAddStudentEmailAutomation();
bindAddStudentFormSubmission();
bindBulkImportFormSubmission();
bindStudentTableLiveFilters();
initializeSchoolYearManagement();
</script>
