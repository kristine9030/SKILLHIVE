<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/evaluation/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';
$successMessage = trim((string)($_SESSION['status'] ?? ''));
unset($_SESSION['status']);

$currentTab = trim((string)($_REQUEST['tab'] ?? 'submit'));
if ($currentTab !== 'history') {
    $currentTab = 'submit';
}

$currentFilters = [
    'department' => trim((string)($_REQUEST['department'] ?? '')),
    'status' => trim((string)($_REQUEST['status'] ?? '')),
    'search' => trim((string)($_REQUEST['search'] ?? '')),
];

$formState = [
    'grade_target' => trim((string)($_POST['grade_target'] ?? '')),
    'remarks' => trim((string)($_POST['comments'] ?? '')),
    'final_grade' => trim((string)($_POST['final_grade'] ?? '2.00')),
    'recommend_future' => !empty($_POST['recommend_future']),
    'professional_conduct' => max(1, min(5, (int)($_POST['professional_conduct'] ?? 3))),
    'report_submission' => max(1, min(5, (int)($_POST['report_submission'] ?? 3))),
    'learning_progress' => max(1, min(5, (int)($_POST['learning_progress'] ?? 3))),
    'goal_achievement' => max(1, min(5, (int)($_POST['goal_achievement'] ?? 3))),
    'overall_cooperation' => max(1, min(5, (int)($_POST['overall_cooperation'] ?? 3))),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adviserId > 0) {
    $action = trim((string)($_POST['action'] ?? ''));
    $currentTab = trim((string)($_POST['tab'] ?? $currentTab));
    if ($currentTab !== 'history') {
        $currentTab = 'submit';
    }

    if ($action === 'save_grade') {
        $target = trim((string)($_POST['grade_target'] ?? ''));
        $targetParts = explode(':', $target);
        $studentId = isset($targetParts[0]) ? (int)$targetParts[0] : 0;
        $internshipId = isset($targetParts[1]) ? (int)$targetParts[1] : 0;

        $finalGrade = trim((string)($_POST['final_grade'] ?? ''));
        $remarks = trim((string)($_POST['comments'] ?? ''));

        $criteria = [
            'Professional Conduct' => max(1, min(5, (int)($_POST['professional_conduct'] ?? 3))),
            'Report Submission' => max(1, min(5, (int)($_POST['report_submission'] ?? 3))),
            'Learning Progress' => max(1, min(5, (int)($_POST['learning_progress'] ?? 3))),
            'Goal Achievement' => max(1, min(5, (int)($_POST['goal_achievement'] ?? 3))),
            'Overall Cooperation' => max(1, min(5, (int)($_POST['overall_cooperation'] ?? 3))),
        ];

        $technicalScore = (int)round((($criteria['Learning Progress'] ?? 0) + ($criteria['Goal Achievement'] ?? 0)) / 2);
        $workEthicScore = (int)round((($criteria['Professional Conduct'] ?? 0) + ($criteria['Report Submission'] ?? 0)) / 2);
        $communicationScore = (int)round($criteria['Overall Cooperation'] ?? 0);

        $criteriaSummaryParts = [];
        foreach ($criteria as $label => $value) {
            $criteriaSummaryParts[] = $label . ': ' . $value . '/5';
        }

        $comments = $remarks;
        if ($comments !== '') {
            $comments .= "\n\nEvaluation Rubric - " . implode(', ', $criteriaSummaryParts)
                . "\nDerived Scores - Technical: " . $technicalScore
                . ', Work Ethic: ' . $workEthicScore
                . ', Communication: ' . $communicationScore;

            if (!empty($_POST['recommend_future'])) {
                $comments .= "\nRecommendation - Recommended for future employment";
            }
        }

        try {
            $result = adviser_evaluation_save_grade($pdo, $adviserId, $studentId, $internshipId, $finalGrade, $comments);
            if (!empty($result['success'])) {
                $_SESSION['status'] = 'Adviser evaluation saved successfully.';
                $formState = [
                    'grade_target' => '',
                    'remarks' => '',
                    'final_grade' => '2.00',
                    'recommend_future' => false,
                    'professional_conduct' => 3,
                    'report_submission' => 3,
                    'learning_progress' => 3,
                    'goal_achievement' => 3,
                    'overall_cooperation' => 3,
                ];
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
            'tab' => $currentTab,
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

$awaitingRows = array_values(array_filter($rows, static function ($row) {
    return !empty($row['is_eligible']) && empty($row['has_adviser_evaluation']);
}));

$gradedRows = array_values(array_filter($rows, static function ($row) {
    return !empty($row['has_adviser_evaluation']);
}));

$avgGradeValue = null;
if (!empty($gradedRows)) {
    $gradeNumbers = array_values(array_filter(array_map(static function ($row) {
        $grade = trim((string)($row['final_grade'] ?? ''));
        return is_numeric($grade) ? (float)$grade : null;
    }, $gradedRows), static function ($value) {
        return $value !== null;
    }));

    if (!empty($gradeNumbers)) {
        $avgGradeValue = array_sum($gradeNumbers) / count($gradeNumbers);
    }
}

$defaultTarget = $formState['grade_target'];
if ($defaultTarget === '' && !empty($awaitingRows)) {
    $defaultTarget = (int)($awaitingRows[0]['student_id'] ?? 0) . ':' . (int)($awaitingRows[0]['internship_id'] ?? 0);
}

$tabSubmitQuery = http_build_query([
    'page' => 'adviser/evaluation',
    'tab' => 'submit',
    'department' => $selected['department'] ?? '',
    'status' => $selected['status'] ?? '',
    'search' => $selected['search'] ?? '',
]);

$tabHistoryQuery = http_build_query([
    'page' => 'adviser/evaluation',
    'tab' => 'history',
    'department' => $selected['department'] ?? '',
    'status' => $selected['status'] ?? '',
    'search' => $selected['search'] ?? '',
]);

$criteriaConfig = [
    'professional_conduct' => 'Professional Conduct',
    'report_submission' => 'Report Submission',
    'learning_progress' => 'Learning Progress',
    'goal_achievement' => 'Goal Achievement',
    'overall_cooperation' => 'Overall Cooperation',
];
?>
<style>
  .adviser-evaluation-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    color: var(--text);
    font-size: var(--font-size-body);
  }

  .adviser-evaluation-tabs {
    display: flex;
    align-items: flex-end;
    gap: 26px;
    border-bottom: 1px solid var(--border);
  }

  .adviser-evaluation-tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0 2px 12px;
    color: #9ca3af;
    text-decoration: none;
    font-size: 0.94rem;
    font-weight: 700;
  }

  .adviser-evaluation-tab.is-active {
    color: #e53935;
  }

  .adviser-evaluation-tab.is-active::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 2px;
    border-radius: 999px;
    background: #e53935;
  }

  .adviser-evaluation-message {
    padding: 12px 14px;
    border-radius: 14px;
    font-size: 0.82rem;
    font-weight: 500;
  }

  .adviser-evaluation-message.is-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
  }

  .adviser-evaluation-message.is-error {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
  }

  .adviser-evaluation-content {
    display: none;
  }

  .adviser-evaluation-content.is-active {
    display: block;
  }

  .adviser-evaluation-submit-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.82fr);
    gap: 20px;
  }

  .adviser-evaluation-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
    padding: 22px;
  }

  .adviser-evaluation-panel-title {
    margin: 0 0 18px;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--text);
  }

  .adviser-evaluation-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .adviser-evaluation-label {
    display: block;
    margin: 0 0 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-evaluation-input,
  .adviser-evaluation-textarea {
    width: 100%;
    border: 1px solid #d8dee8;
    border-radius: 12px;
    background: #fff;
    color: var(--text);
    font-size: 0.84rem;
    padding: 11px 14px;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .adviser-evaluation-input:focus,
  .adviser-evaluation-textarea:focus {
    border-color: #e7a39f;
    box-shadow: 0 0 0 4px rgba(229, 57, 53, .08);
  }

  .adviser-evaluation-textarea {
    min-height: 100px;
    resize: vertical;
  }

  .adviser-evaluation-section-label {
    margin: 2px 0 2px;
    font-size: 0.84rem;
    color: var(--text3);
    font-weight: 700;
  }

  .adviser-evaluation-rating-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .adviser-evaluation-rating-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .adviser-evaluation-rating-title {
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-evaluation-stars {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .adviser-evaluation-star {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid #d9dee7;
    background: #fff;
    color: #9ca3af;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
  }

  .adviser-evaluation-star:hover {
    transform: translateY(-1px);
  }

  .adviser-evaluation-star.is-active {
    background: #fff4e5;
    border-color: #f2b766;
    color: #f59e0b;
  }

  .adviser-evaluation-check {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #eef7f6;
    border: 1px solid #dcecec;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-evaluation-check input {
    width: 16px;
    height: 16px;
  }

  .adviser-evaluation-submit-btn {
    width: 100%;
    min-height: 42px;
    border: 0;
    border-radius: 999px;
    background: #111;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
  }

  .adviser-evaluation-side {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .adviser-evaluation-awaiting-list,
  .adviser-evaluation-stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .adviser-evaluation-awaiting-card,
  .adviser-evaluation-stat-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(90deg, rgba(255,255,255,.96) 0%, rgba(255,239,239,.92) 100%);
    border: 1px solid #f6e5e5;
  }

  .adviser-evaluation-student {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }

  .adviser-evaluation-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.76rem;
    font-weight: 700;
    flex-shrink: 0;
  }

  .adviser-evaluation-student-name {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-evaluation-student-meta {
    margin: 2px 0 0;
    font-size: 0.79rem;
    color: var(--text3);
  }

  .adviser-evaluation-pill-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 6px 16px;
    border-radius: 999px;
    border: 0;
    background: #111;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
  }

  .adviser-evaluation-stat-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-evaluation-stat-value {
    font-size: 1.8rem;
    line-height: 1;
    font-weight: 700;
  }

  .adviser-evaluation-stat-value.is-success {
    color: #10b981;
  }

  .adviser-evaluation-stat-value.is-warning {
    color: #f59e0b;
  }

  .adviser-evaluation-stat-value.is-danger {
    color: #e53935;
  }

  .adviser-evaluation-history-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
  }

  .adviser-evaluation-history-copy {
    margin: 6px 0 0;
    font-size: 0.8rem;
    color: var(--text3);
  }

  .adviser-evaluation-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .adviser-evaluation-search {
    position: relative;
    min-width: 260px;
  }

  .adviser-evaluation-search i {
    position: absolute;
    top: 50%;
    left: 14px;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 0.86rem;
  }

  .adviser-evaluation-search input {
    padding-left: 40px;
  }

  .adviser-evaluation-filter {
    min-width: 180px;
  }

  .adviser-evaluation-filter-btn {
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    border: 1px solid #111;
    background: #111;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
  }

  .adviser-evaluation-table-wrap {
    overflow-x: auto;
  }

  .adviser-evaluation-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .adviser-evaluation-table th {
    padding: 0 14px 12px;
    text-align: left;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text3);
    font-weight: 600;
  }

  .adviser-evaluation-table td {
    padding: 16px 14px;
    border-top: 1px solid var(--border);
    font-size: 0.84rem;
    color: var(--text);
    vertical-align: middle;
  }

  .adviser-evaluation-table tr:hover td {
    background: #fafafa;
  }

  .adviser-evaluation-student-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 220px;
  }

  .adviser-evaluation-subtext {
    display: block;
    margin-top: 3px;
    font-size: 0.76rem;
    color: var(--text3);
  }

  .adviser-evaluation-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 90px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 600;
  }

  .adviser-evaluation-status.is-success {
    background: #e1f8ee;
    color: #10a56f;
  }

  .adviser-evaluation-status.is-warning {
    background: #fff2dd;
    color: #ef9a17;
  }

  .adviser-evaluation-history-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 6px 14px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    font-size: 0.79rem;
    font-weight: 700;
    cursor: pointer;
  }

  .adviser-evaluation-history-action.primary {
    background: #111;
    border-color: #111;
    color: #fff;
  }

  .adviser-evaluation-empty {
    padding: 18px;
    border-radius: 14px;
    border: 1px dashed var(--border);
    background: #fafafa;
    color: #6b7280;
    font-size: 0.82rem;
  }

  @media (max-width: 1180px) {
    .adviser-evaluation-submit-grid {
      grid-template-columns: 1fr;
    }

    .adviser-evaluation-history-head {
      flex-direction: column;
    }
  }

  @media (max-width: 760px) {
    .adviser-evaluation-tabs {
      gap: 16px;
      overflow-x: auto;
    }

    .adviser-evaluation-panel {
      padding: 18px;
      border-radius: 16px;
    }

    .adviser-evaluation-search,
    .adviser-evaluation-filter {
      min-width: 100%;
      width: 100%;
    }

    .adviser-evaluation-filter-btn,
    .adviser-evaluation-pill-btn {
      width: 100%;
    }

    .adviser-evaluation-awaiting-card,
    .adviser-evaluation-stat-card {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>

<div class="adviser-evaluation-page">
  <nav class="adviser-evaluation-tabs" aria-label="Evaluation sections">
    <a class="adviser-evaluation-tab <?php echo $currentTab === 'submit' ? 'is-active' : ''; ?>" href="<?php echo $baseUrl; ?>/layout.php?<?php echo adviser_evaluation_escape($tabSubmitQuery); ?>" data-evaluation-tab="submit">
      Submit Evaluation
    </a>
    <a class="adviser-evaluation-tab <?php echo $currentTab === 'history' ? 'is-active' : ''; ?>" href="<?php echo $baseUrl; ?>/layout.php?<?php echo adviser_evaluation_escape($tabHistoryQuery); ?>" data-evaluation-tab="history">
      Evaluation History
    </a>
  </nav>

  <?php if ($successMessage !== ''): ?>
    <div class="adviser-evaluation-message is-success">
      <?php echo adviser_evaluation_escape($successMessage); ?>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage !== ''): ?>
    <div class="adviser-evaluation-message is-error">
      <?php echo adviser_evaluation_escape($errorMessage); ?>
    </div>
  <?php endif; ?>

  <section class="adviser-evaluation-content <?php echo $currentTab === 'submit' ? 'is-active' : ''; ?>" data-evaluation-content="submit">
    <div class="adviser-evaluation-submit-grid">
      <article class="adviser-evaluation-panel">
        <h3 class="adviser-evaluation-panel-title">Adviser Evaluation Form</h3>

        <form method="post" class="adviser-evaluation-form">
          <input type="hidden" name="action" value="save_grade">
          <input type="hidden" name="tab" value="submit" id="evaluationTabField">
          <input type="hidden" name="search" value="<?php echo adviser_evaluation_escape((string)$selected['search']); ?>">
          <input type="hidden" name="department" value="<?php echo adviser_evaluation_escape((string)$selected['department']); ?>">
          <input type="hidden" name="status" value="<?php echo adviser_evaluation_escape((string)$selected['status']); ?>">

          <div>
            <label class="adviser-evaluation-label" for="gradeTargetSelect">Select Student</label>
            <select class="adviser-evaluation-input" id="gradeTargetSelect" name="grade_target" required>
              <option value="">Select student...</option>
              <?php foreach ($gradeTargets as $target): ?>
                <?php $targetValue = (int)$target['student_id'] . ':' . (int)$target['internship_id']; ?>
                <option value="<?php echo adviser_evaluation_escape($targetValue); ?>" <?php echo $defaultTarget === $targetValue ? 'selected' : ''; ?>>
                  <?php echo adviser_evaluation_escape((string)$target['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <p class="adviser-evaluation-section-label">Performance Criteria</p>
            <div class="adviser-evaluation-rating-list">
              <?php foreach ($criteriaConfig as $key => $label): ?>
                <?php $ratingValue = (int)($formState[$key] ?? 3); ?>
                <div class="adviser-evaluation-rating-row">
                  <span class="adviser-evaluation-rating-title"><?php echo adviser_evaluation_escape($label); ?></span>
                  <input type="hidden" name="<?php echo adviser_evaluation_escape($key); ?>" value="<?php echo $ratingValue; ?>" data-rating-input="<?php echo adviser_evaluation_escape($key); ?>">
                  <div class="adviser-evaluation-stars" data-rating-group="<?php echo adviser_evaluation_escape($key); ?>">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                      <button
                        class="adviser-evaluation-star <?php echo $star <= $ratingValue ? 'is-active' : ''; ?>"
                        type="button"
                        data-rating-value="<?php echo $star; ?>"
                        aria-label="<?php echo adviser_evaluation_escape($label . ' ' . $star . ' stars'); ?>"
                      >
                        <i class="fas fa-star"></i>
                      </button>
                    <?php endfor; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div>
            <label class="adviser-evaluation-label" for="evaluationRemarks">Adviser Remarks</label>
            <textarea class="adviser-evaluation-textarea" id="evaluationRemarks" name="comments" placeholder="Enter your observations and remarks about this student's OJT performance..." required><?php echo adviser_evaluation_escape($formState['remarks']); ?></textarea>
          </div>

          <div>
            <label class="adviser-evaluation-label" for="finalGradeSelect">Final Grade</label>
            <select class="adviser-evaluation-input" id="finalGradeSelect" name="final_grade" required>
              <?php foreach ($gradeOptions as $gradeOption): ?>
                <option value="<?php echo adviser_evaluation_escape($gradeOption); ?>" <?php echo $formState['final_grade'] === $gradeOption ? 'selected' : ''; ?>>
                  <?php echo adviser_evaluation_escape(adviser_evaluation_grade_label($gradeOption)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <label class="adviser-evaluation-check">
            <input type="checkbox" name="recommend_future" value="1" <?php echo !empty($formState['recommend_future']) ? 'checked' : ''; ?>>
            Recommend this student for future employment
          </label>

          <button type="submit" class="adviser-evaluation-submit-btn">
            <i class="fas fa-paper-plane"></i>
            Submit Evaluation
          </button>
        </form>
      </article>

      <aside class="adviser-evaluation-side">
        <article class="adviser-evaluation-panel">
          <h3 class="adviser-evaluation-panel-title">Students Awaiting Evaluation</h3>

          <?php if (!empty($awaitingRows)): ?>
            <div class="adviser-evaluation-awaiting-list">
              <?php foreach (array_slice($awaitingRows, 0, 4) as $index => $row): ?>
                <?php $targetValue = (int)($row['student_id'] ?? 0) . ':' . (int)($row['internship_id'] ?? 0); ?>
                <div class="adviser-evaluation-awaiting-card">
                  <div class="adviser-evaluation-student">
                    <span class="adviser-evaluation-avatar" style="background:<?php echo adviser_evaluation_escape(adviser_evaluation_avatar_gradient($index)); ?>;">
                      <?php echo adviser_evaluation_escape((string)($row['initials'] ?? 'NA')); ?>
                    </span>
                    <div>
                      <p class="adviser-evaluation-student-name"><?php echo adviser_evaluation_escape((string)($row['student_name'] ?? 'Student')); ?></p>
                      <p class="adviser-evaluation-student-meta"><?php echo adviser_evaluation_escape((string)($row['company_name'] ?? 'Company')); ?></p>
                    </div>
                  </div>

                  <button class="adviser-evaluation-pill-btn" type="button" onclick="selectGradeTarget('<?php echo adviser_evaluation_escape($targetValue); ?>')">
                    Evaluate
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="adviser-evaluation-empty">
              No students are currently waiting for adviser evaluation.
            </div>
          <?php endif; ?>
        </article>

        <article class="adviser-evaluation-panel">
          <h3 class="adviser-evaluation-panel-title">Quick Stats</h3>

          <div class="adviser-evaluation-stats-list">
            <div class="adviser-evaluation-stat-card">
              <span class="adviser-evaluation-stat-label">Evaluations Done</span>
              <span class="adviser-evaluation-stat-value is-success"><?php echo count($gradedRows); ?></span>
            </div>
            <div class="adviser-evaluation-stat-card">
              <span class="adviser-evaluation-stat-label">Pending</span>
              <span class="adviser-evaluation-stat-value is-warning"><?php echo count($awaitingRows); ?></span>
            </div>
            <div class="adviser-evaluation-stat-card">
              <span class="adviser-evaluation-stat-label">Average Grade</span>
              <span class="adviser-evaluation-stat-value is-danger"><?php echo $avgGradeValue !== null ? adviser_evaluation_escape(number_format($avgGradeValue, 2)) : 'N/A'; ?></span>
            </div>
          </div>
        </article>
      </aside>
    </div>
  </section>

  <section class="adviser-evaluation-content <?php echo $currentTab === 'history' ? 'is-active' : ''; ?>" data-evaluation-content="history">
    <article class="adviser-evaluation-panel">
      <div class="adviser-evaluation-history-head">
        <div>
          <h3 class="adviser-evaluation-panel-title" style="margin-bottom:0;">Evaluation History</h3>
          <p class="adviser-evaluation-history-copy">Review completed adviser evaluations and track grading progress.</p>
        </div>

        <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="adviser-evaluation-filters">
          <input type="hidden" name="page" value="adviser/evaluation">
          <input type="hidden" name="tab" value="history">

          <div class="adviser-evaluation-search">
            <i class="fas fa-search"></i>
            <input class="adviser-evaluation-input" type="text" name="search" placeholder="Search students..." value="<?php echo adviser_evaluation_escape($selected['search'] ?? ''); ?>">
          </div>

          <select class="adviser-evaluation-input adviser-evaluation-filter" name="department">
            <option value="">All Departments</option>
            <?php foreach (($filterOptions['departments'] ?? []) as $departmentOption): ?>
              <option value="<?php echo adviser_evaluation_escape($departmentOption); ?>" <?php echo ($selected['department'] ?? '') === $departmentOption ? 'selected' : ''; ?>>
                <?php echo adviser_evaluation_escape($departmentOption); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select class="adviser-evaluation-input adviser-evaluation-filter" name="status">
            <option value="">All Status</option>
            <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
              <option value="<?php echo adviser_evaluation_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>>
                <?php echo adviser_evaluation_escape($statusOption); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button class="adviser-evaluation-filter-btn" type="submit">Apply</button>
        </form>
      </div>

      <?php if (!empty($rows)): ?>
        <div class="adviser-evaluation-table-wrap">
          <table class="adviser-evaluation-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Company / Status</th>
                <th>OJT Hours</th>
                <th>Employer Rating</th>
                <th>Final Grade</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $index => $row): ?>
                <?php
                $employerRating = $row['employer_rating'];
                $employerRatingText = is_numeric($employerRating) ? number_format((float)$employerRating, 2) : 'N/A';
                $finalGrade = trim((string)($row['final_grade'] ?? ''));
                $isEligible = !empty($row['is_eligible']);
                $targetValue = (int)($row['student_id'] ?? 0) . ':' . (int)($row['internship_id'] ?? 0);
                $statusClass = !empty($row['has_adviser_evaluation']) ? 'is-success' : 'is-warning';
                ?>
                <tr>
                  <td>
                    <div class="adviser-evaluation-student-cell">
                      <span class="adviser-evaluation-avatar" style="background:<?php echo adviser_evaluation_escape(adviser_evaluation_avatar_gradient($index)); ?>;">
                        <?php echo adviser_evaluation_escape((string)($row['initials'] ?? 'NA')); ?>
                      </span>
                      <div>
                        <span style="font-weight:600;"><?php echo adviser_evaluation_escape((string)($row['student_name'] ?? 'Student')); ?></span>
                        <span class="adviser-evaluation-subtext">
                          <?php echo adviser_evaluation_escape(adviser_evaluation_year_level_label($row['year_level'] ?? '')); ?> · <?php echo adviser_evaluation_escape((string)($row['program'] ?? 'N/A')); ?>
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span style="font-weight:600;"><?php echo adviser_evaluation_escape((string)($row['company_name'] ?? 'Company')); ?></span>
                    <span class="adviser-evaluation-subtext">
                      <span class="adviser-evaluation-status <?php echo $statusClass; ?>">
                        <?php echo adviser_evaluation_escape((string)($row['status_label'] ?? 'Pending')); ?>
                      </span>
                    </span>
                  </td>
                  <td><?php echo (int)round((float)($row['hours_completed'] ?? 0)); ?>/<?php echo (int)round((float)($row['hours_required'] ?? 0)); ?></td>
                  <td><?php echo adviser_evaluation_escape($employerRatingText); ?></td>
                  <td><?php echo adviser_evaluation_escape($finalGrade !== '' ? $finalGrade : 'Pending'); ?></td>
                  <td>
                    <?php if (!$isEligible): ?>
                      <button class="adviser-evaluation-history-action" type="button" disabled>Awaiting Completion</button>
                    <?php elseif (!empty($row['has_adviser_evaluation'])): ?>
                      <button class="adviser-evaluation-history-action" type="button" onclick="selectGradeTarget('<?php echo adviser_evaluation_escape($targetValue); ?>')">Review</button>
                    <?php else: ?>
                      <button class="adviser-evaluation-history-action primary" type="button" onclick="selectGradeTarget('<?php echo adviser_evaluation_escape($targetValue); ?>')">Evaluate</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="adviser-evaluation-empty">
          No evaluation records found for the selected filters.
        </div>
      <?php endif; ?>
    </article>
  </section>
</div>

<script>
  (function () {
    function setActiveTab(tabName) {
      document.querySelectorAll('[data-evaluation-content]').forEach(function (section) {
        section.classList.toggle('is-active', section.getAttribute('data-evaluation-content') === tabName);
      });

      document.querySelectorAll('[data-evaluation-tab]').forEach(function (tabLink) {
        tabLink.classList.toggle('is-active', tabLink.getAttribute('data-evaluation-tab') === tabName);
      });

      var tabField = document.getElementById('evaluationTabField');
      if (tabField) {
        tabField.value = tabName;
      }
    }

    document.querySelectorAll('[data-rating-group]').forEach(function (group) {
      var groupName = group.getAttribute('data-rating-group');
      var hiddenInput = document.querySelector('[data-rating-input="' + groupName + '"]');
      if (!hiddenInput) {
        return;
      }

      function refreshStars(nextValue) {
        group.querySelectorAll('[data-rating-value]').forEach(function (button) {
          var buttonValue = parseInt(button.getAttribute('data-rating-value') || '0', 10);
          button.classList.toggle('is-active', buttonValue <= nextValue);
        });
      }

      refreshStars(parseInt(hiddenInput.value || '0', 10));

      group.querySelectorAll('[data-rating-value]').forEach(function (button) {
        button.addEventListener('click', function () {
          var nextValue = parseInt(button.getAttribute('data-rating-value') || '0', 10);
          hiddenInput.value = nextValue;
          refreshStars(nextValue);
        });
      });
    });

    window.selectGradeTarget = function (value) {
      var select = document.getElementById('gradeTargetSelect');
      if (!select) {
        return;
      }
      select.value = value || '';
      setActiveTab('submit');
      select.scrollIntoView({ behavior: 'smooth', block: 'center' });
      select.focus();
    };

    setActiveTab('<?php echo adviser_evaluation_escape($currentTab); ?>');
  })();
</script>
