<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/evaluation/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';

$currentFilters = [
  'department' => trim((string)($_REQUEST['department'] ?? '')),
  'status' => trim((string)($_REQUEST['status'] ?? '')),
  'search' => trim((string)($_REQUEST['search'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adviserId > 0) {
  $action = trim((string)($_POST['action'] ?? ''));

  if ($action === 'save_grade') {
    $target = trim((string)($_POST['grade_target'] ?? ''));
    $targetParts = explode(':', $target);
    $studentId = isset($targetParts[0]) ? (int)$targetParts[0] : 0;
    $internshipId = isset($targetParts[1]) ? (int)$targetParts[1] : 0;

    $finalGrade = trim((string)($_POST['final_grade'] ?? ''));
    $comments = trim((string)($_POST['comments'] ?? ''));

    $technicalScore = trim((string)($_POST['technical_score'] ?? ''));
    $workEthicScore = trim((string)($_POST['work_ethic_score'] ?? ''));
    $communicationScore = trim((string)($_POST['communication_score'] ?? ''));

    if ($comments !== '') {
      $comments .= "\n\nRubric — Technical: " . $technicalScore
        . ', Work Ethic: ' . $workEthicScore
        . ', Communication: ' . $communicationScore;
    }

    try {
      $result = adviser_evaluation_save_grade($pdo, $adviserId, $studentId, $internshipId, $finalGrade, $comments);
      if (!empty($result['success'])) {
        $_SESSION['status'] = 'Adviser evaluation saved successfully.';
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to save adviser evaluation.');
      }
    } catch (Throwable $e) {
      $errorMessage = 'Unable to save adviser evaluation right now.';
    }
  }

  if ($errorMessage === '') {
    $query = http_build_query([
      'page' => 'adviser/evaluation',
      'department' => (string)$currentFilters['department'],
      'status' => (string)$currentFilters['status'],
      'search' => (string)$currentFilters['search'],
    ]);
    header('Location: ' . $baseUrl . '/layout.php?' . $query);
    exit;
  }
}

$pageData = [
  'selected' => ['department' => '', 'status' => '', 'search' => ''],
  'filter_options' => ['departments' => [], 'statuses' => []],
  'rows' => [],
  'grade_targets' => [],
  'grade_options' => adviser_evaluation_grade_options(),
];

if ($adviserId > 0) {
  try {
    $pageData = getAdviserEvaluationPageData($pdo, $adviserId, $currentFilters);
  } catch (Throwable $e) {
    $pageData = $pageData;
  }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
$gradeTargets = $pageData['grade_targets'];
$gradeOptions = $pageData['grade_options'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Student Evaluations</h2>
    <p class="page-subtitle">Review and grade student internship performance.</p>
  </div>
</div>

<?php if ($errorMessage !== ''): ?>
  <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
    <?php echo adviser_evaluation_escape($errorMessage); ?>
  </div>
<?php endif; ?>

<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row" style="margin-bottom:20px">
  <input type="hidden" name="page" value="adviser/evaluation">

  <div class="topbar-search" style="flex:1;max-width:260px">
    <i class="fas fa-search"></i>
    <input type="text" name="search" placeholder="Search students..." value="<?php echo adviser_evaluation_escape($selected['search'] ?? ''); ?>">
  </div>

  <select class="filter-select" name="department" onchange="this.form.submit()">
    <option value="">All Departments</option>
    <?php foreach (($filterOptions['departments'] ?? []) as $departmentOption): ?>
      <option value="<?php echo adviser_evaluation_escape($departmentOption); ?>" <?php echo ($selected['department'] ?? '') === $departmentOption ? 'selected' : ''; ?>><?php echo adviser_evaluation_escape($departmentOption); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
      <option value="<?php echo adviser_evaluation_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_evaluation_escape($statusOption); ?></option>
    <?php endforeach; ?>
  </select>

  <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

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
      <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $employerRating = $row['employer_rating'];
          $employerRatingText = is_numeric($employerRating) ? number_format((float)$employerRating, 2) : '—';
          $finalGrade = trim((string)($row['final_grade'] ?? ''));
          $isEligible = !empty($row['is_eligible']);
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="avatar-placeholder" style="width:34px;height:34px;border-radius:50%;background:#e0f2fe;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.75rem;color:#0369a1"><?php echo adviser_evaluation_escape((string)($row['initials'] ?? 'NA')); ?></div>
                <div><div style="font-weight:600"><?php echo adviser_evaluation_escape((string)($row['student_name'] ?? 'Student')); ?></div><div style="font-size:.75rem;color:var(--text-lighter)"><?php echo adviser_evaluation_escape((string)($row['program'] ?? 'N/A')); ?> &middot; <?php echo adviser_evaluation_escape((string)($row['year_level'] ?? 'N/A')); ?></div></div>
              </div>
            </td>
            <td><?php echo adviser_evaluation_escape((string)($row['company_name'] ?? 'N/A')); ?></td>
            <td><?php echo (int)round((float)($row['hours_completed'] ?? 0)); ?> / <?php echo (int)round((float)($row['hours_required'] ?? 0)); ?></td>
            <td>
              <?php if (is_numeric($employerRating)): ?>
                <span style="color:#F59E0B"><i class="fas fa-star"></i> <?php echo adviser_evaluation_escape($employerRatingText); ?></span>
              <?php else: ?>
                <span style="color:var(--text-lighter)">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($finalGrade !== ''): ?>
                <span class="status-badge badge-active"><?php echo adviser_evaluation_escape($finalGrade); ?></span>
              <?php else: ?>
                <span class="status-badge badge-pending">Pending</span>
              <?php endif; ?>
            </td>
            <td><span class="status-badge <?php echo adviser_evaluation_escape((string)($row['status_class'] ?? 'badge-pending')); ?>"><?php echo adviser_evaluation_escape((string)($row['status_label'] ?? 'Pending')); ?></span></td>
            <td>
              <?php if (!$isEligible): ?>
                <button class="btn-outline btn-sm" type="button" disabled>Awaiting Completion</button>
              <?php elseif (!empty($row['has_adviser_evaluation'])): ?>
                <button class="btn-outline btn-sm" type="button" disabled><i class="fas fa-eye"></i> Graded</button>
              <?php else: ?>
                <button class="btn-primary btn-sm" type="button" onclick="selectGradeTarget('<?php echo (int)($row['student_id'] ?? 0); ?>:<?php echo (int)($row['internship_id'] ?? 0); ?>')"><i class="fas fa-pen"></i> Grade</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" style="text-align:center;color:#9ca3af;padding:18px 10px;">No evaluation records found for the selected filters.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel-card" style="margin-top:24px">
  <div class="panel-card-header"><h3>Grade Student</h3></div>
  <form method="post" style="display:flex;flex-direction:column;gap:16px">
    <input type="hidden" name="action" value="save_grade">
    <input type="hidden" name="search" value="<?php echo adviser_evaluation_escape((string)$selected['search']); ?>">
    <input type="hidden" name="department" value="<?php echo adviser_evaluation_escape((string)$selected['department']); ?>">
    <input type="hidden" name="status" value="<?php echo adviser_evaluation_escape((string)$selected['status']); ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Student</label>
        <select class="form-input" id="gradeTargetSelect" name="grade_target" required>
          <option value="">Select student...</option>
          <?php foreach ($gradeTargets as $target): ?>
            <?php $targetValue = (int)$target['student_id'] . ':' . (int)$target['internship_id']; ?>
            <option value="<?php echo adviser_evaluation_escape($targetValue); ?>"><?php echo adviser_evaluation_escape((string)$target['label']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Final Grade</label>
        <select class="form-input" name="final_grade" required>
          <option value="">Select grade...</option>
          <?php foreach ($gradeOptions as $gradeOption): ?>
            <option value="<?php echo adviser_evaluation_escape($gradeOption); ?>"><?php echo adviser_evaluation_escape($gradeOption === '5.00' ? '5.00 (Failed)' : $gradeOption); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="form-label">Performance Summary</label>
      <textarea class="form-input" name="comments" rows="3" placeholder="Brief evaluation summary..." required></textarea>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
      <div>
        <label class="form-label">Technical Skills</label>
        <select class="form-input" name="technical_score">
          <option value="5">5 — Excellent</option><option value="4">4 — Very Good</option><option value="3">3 — Good</option><option value="2">2 — Fair</option><option value="1">1 — Poor</option>
        </select>
      </div>
      <div>
        <label class="form-label">Work Ethic</label>
        <select class="form-input" name="work_ethic_score">
          <option value="5">5 — Excellent</option><option value="4">4 — Very Good</option><option value="3">3 — Good</option><option value="2">2 — Fair</option><option value="1">1 — Poor</option>
        </select>
      </div>
      <div>
        <label class="form-label">Communication</label>
        <select class="form-input" name="communication_score">
          <option value="5">5 — Excellent</option><option value="4">4 — Very Good</option><option value="3">3 — Good</option><option value="2">2 — Fair</option><option value="1">1 — Poor</option>
        </select>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a href="<?php echo $baseUrl; ?>/layout.php?page=adviser/evaluation" class="btn-outline" style="text-decoration:none;display:inline-flex;align-items:center;">Cancel</a>
      <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Submit Grade</button>
    </div>
  </form>
</div>

<script>
function selectGradeTarget(value) {
  var select = document.getElementById('gradeTargetSelect');
  if (!select) return;
  select.value = value || '';
  select.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
