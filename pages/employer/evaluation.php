<?php
/**
 * Purpose: Employer evaluations page that handles evaluation form submission and renders evaluation history plus summary metrics.
 * Tables/columns used: Indirectly uses ojt_record(student_id, internship_id), internship(internship_id, employer_id, title), student(student_id, first_name, last_name), employer_evaluation(evaluation_id, student_id, internship_id, employer_id, technical_score, behavioral_score, comments, recommendation_status, evaluation_date).
 */
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/auth_helpers.php';
require_once __DIR__ . '/evaluation/data.php';

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
    $employerId = (int)($stmtEmployer->fetchColumn() ?: 0);
    if ($employerId > 0) {
      $employerIdCandidates[] = $employerId;
    }
  } catch (Throwable $e) {
    $employerId = 0;
  }
}

// Pick the best-matching employer id based on who actually has internship postings.
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
  $_SESSION['status'] = 'Your employer account is pending admin verification. Evaluation module is locked until approval.';
  header('Location: ' . $baseUrl . '/layout.php?page=employer/dashboard');
  exit;
}

$errorMessage = '';

$formState = [
  'candidate_key' => '',
  'period' => 'Midterm',
  'technical_score' => 0,
  'communication_score' => 0,
  'work_ethic_score' => 0,
  'comments' => '',
  'internship_id' => 0,
];

$selectedFilters = [
  'internship_id' => max(0, (int)($_REQUEST['internship_id'] ?? 0)),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employerId > 0) {
  $formState['internship_id'] = $selectedFilters['internship_id'];
  $formState['candidate_key'] = trim((string)($_POST['candidate_key'] ?? ''));
  $formState['period'] = trim((string)($_POST['period'] ?? 'Midterm'));
  $formState['technical_score'] = (float)($_POST['technical_score'] ?? 0);
  $formState['communication_score'] = (float)($_POST['communication_score'] ?? 0);
  $formState['work_ethic_score'] = (float)($_POST['work_ethic_score'] ?? 0);
  $formState['comments'] = trim((string)($_POST['comments'] ?? ''));

  try {
    $saveResult = saveEmployerEvaluation($pdo, $employerId, $_POST);
    if (!empty($saveResult['success'])) {
      $_SESSION['status'] = 'Evaluation submitted successfully.';
      $redirectQuery = http_build_query([
        'page' => 'employer/evaluation',
        'internship_id' => $selectedFilters['internship_id'],
      ]);
      header('Location: ' . $baseUrl . '/layout.php?' . $redirectQuery);
      exit;
    }
    $errorMessage = (string)($saveResult['error'] ?? 'Unable to save evaluation.');
  } catch (Throwable $e) {
    $errorMessage = 'Unable to save evaluation right now. Please try again.';
  }
}

$pageData = [
  'internship_options' => [],
  'intern_options' => [],
  'history' => [],
  'summary' => [
    'total_evaluations' => 0,
    'average_rating' => 0,
    'pending' => 0,
  ],
  'selected' => [
    'internship_id' => 0,
  ],
];

if ($employerId > 0) {
  try {
    $pageData = getEmployerEvaluationPageData($pdo, $employerId, $selectedFilters);
  } catch (Throwable $e) {
    $pageData = $pageData;
  }
}

$internshipOptions = $pageData['internship_options'];
$internOptions = $pageData['intern_options'];
$history = $pageData['history'];
$summary = $pageData['summary'];
$selectedFilters = $pageData['selected'];
$submitDisabled = empty($internOptions);

$exportMode = strtolower(trim((string)($_GET['export'] ?? '')));
if ($exportMode === 'csv' && $employerId > 0) {
  $filename = 'employer-evaluations-' . date('Ymd-His') . '.csv';

  if (!headers_sent()) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
  }

  $out = fopen('php://output', 'w');
  if ($out !== false) {
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Intern', 'Internship', 'Period', 'Technical', 'Communication', 'Work Ethic', 'Overall', 'Feedback', 'Evaluation Date']);

    foreach ($history as $row) {
      fputcsv($out, [
        (string)($row['intern'] ?? ''),
        (string)($row['internship_title'] ?? ''),
        (string)($row['period'] ?? ''),
        (string)number_format((float)($row['technical'] ?? 0), 1),
        (string)number_format((float)($row['communication'] ?? 0), 1),
        (string)number_format((float)($row['ethic'] ?? 0), 1),
        (string)number_format((float)($row['overall'] ?? 0), 1),
        (string)($row['comment'] ?? ''),
        (string)($row['evaluation_date'] ?? ''),
      ]);
    }

    fclose($out);
  }

  exit;
}
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Evaluations</h2>
    <p class="page-subtitle">Rate and evaluate intern performance.</p>
  </div>
</div>

<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row" style="margin-bottom:16px;">
  <input type="hidden" name="page" value="employer/evaluation">

  <select class="filter-select" name="internship_id" onchange="this.form.submit()">
    <option value="0">All Internships</option>
    <?php foreach ($internshipOptions as $internship): ?>
      <?php $internshipId = (int)($internship['internship_id'] ?? 0); ?>
      <option value="<?php echo $internshipId; ?>" <?php echo $selectedFilters['internship_id'] === $internshipId ? 'selected' : ''; ?>>
        <?php echo dashboard_escape((string)($internship['title'] ?? 'Internship')); ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button class="btn btn-primary btn-sm" type="submit">Apply</button>
  <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?<?php echo http_build_query(['page' => 'employer/evaluation', 'internship_id' => (int)$selectedFilters['internship_id'], 'export' => 'csv']); ?>">Export CSV</a>
  <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/evaluation">Reset</a>
</form>

<div class="feed-layout">
  <div class="feed-main">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Submit Evaluation</h3></div>

      <?php if ($errorMessage !== ''): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:.82rem;">
          <?php echo dashboard_escape($errorMessage); ?>
        </div>
      <?php endif; ?>

      <form method="post" style="display:flex;flex-direction:column;gap:16px">
        <input type="hidden" name="internship_id" value="<?php echo (int)$selectedFilters['internship_id']; ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Select Intern</label>
            <select class="form-input" name="candidate_key" required>
              <option value="">— Choose Intern —</option>
              <?php foreach ($internOptions as $intern): ?>
                <?php
                $studentId = (int)($intern['student_id'] ?? 0);
                $internshipId = (int)($intern['internship_id'] ?? 0);
                $candidateKey = $studentId . ':' . $internshipId;
                $internName = trim((string)($intern['first_name'] ?? '') . ' ' . (string)($intern['last_name'] ?? ''));
                $internshipTitle = (string)($intern['title'] ?? 'Internship');
                ?>
                <option value="<?php echo dashboard_escape($candidateKey); ?>" <?php echo $formState['candidate_key'] === $candidateKey ? 'selected' : ''; ?>>
                  <?php echo dashboard_escape($internName . ' — ' . $internshipTitle); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Evaluation Period</label>
            <select class="form-input" name="period">
              <option value="Midterm" <?php echo $formState['period'] === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
              <option value="Final" <?php echo $formState['period'] === 'Final' ? 'selected' : ''; ?>>Final</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Technical Skills</label>
          <input type="hidden" name="technical_score" id="technical_score" value="<?php echo (float)$formState['technical_score']; ?>">
          <div class="eval-stars" id="techStars">
            <i class="fas fa-star" onclick="setRating('techStars',1,'technical_score')"></i>
            <i class="fas fa-star" onclick="setRating('techStars',2,'technical_score')"></i>
            <i class="fas fa-star" onclick="setRating('techStars',3,'technical_score')"></i>
            <i class="fas fa-star" onclick="setRating('techStars',4,'technical_score')"></i>
            <i class="fas fa-star" onclick="setRating('techStars',5,'technical_score')"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Communication</label>
          <input type="hidden" name="communication_score" id="communication_score" value="<?php echo (float)$formState['communication_score']; ?>">
          <div class="eval-stars" id="commStars">
            <i class="fas fa-star" onclick="setRating('commStars',1,'communication_score')"></i>
            <i class="fas fa-star" onclick="setRating('commStars',2,'communication_score')"></i>
            <i class="fas fa-star" onclick="setRating('commStars',3,'communication_score')"></i>
            <i class="fas fa-star" onclick="setRating('commStars',4,'communication_score')"></i>
            <i class="fas fa-star" onclick="setRating('commStars',5,'communication_score')"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Work Ethic</label>
          <input type="hidden" name="work_ethic_score" id="work_ethic_score" value="<?php echo (float)$formState['work_ethic_score']; ?>">
          <div class="eval-stars" id="ethicStars">
            <i class="fas fa-star" onclick="setRating('ethicStars',1,'work_ethic_score')"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',2,'work_ethic_score')"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',3,'work_ethic_score')"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',4,'work_ethic_score')"></i>
            <i class="fas fa-star" onclick="setRating('ethicStars',5,'work_ethic_score')"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Comments & Feedback</label>
          <textarea class="form-input" name="comments" rows="4" placeholder="Provide detailed feedback about the intern's performance..."><?php echo dashboard_escape($formState['comments']); ?></textarea>
        </div>

        <div><button type="submit" class="btn btn-primary btn-sm" <?php echo $submitDisabled ? 'disabled' : ''; ?>>Submit Evaluation</button></div>
      </form>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Past Evaluations</h3></div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr><th>Intern</th><th>Internship</th><th>Period</th><th>Technical</th><th>Comm.</th><th>Ethics</th><th>Overall</th><th>Feedback</th><th>Evaluated On</th></tr>
          </thead>
          <tbody>
            <?php if (!empty($history)): ?>
              <?php foreach ($history as $row): ?>
                <?php
                $feedbackRaw = trim((string)($row['comment'] ?? ''));
                $feedbackText = $feedbackRaw !== '' ? $feedbackRaw : 'No written feedback.';
                if (strlen($feedbackText) > 90) {
                  $feedbackText = substr($feedbackText, 0, 87) . '...';
                }
                $evaluatedDateText = trim((string)($row['evaluation_date'] ?? ''));
                $evaluatedDateLabel = $evaluatedDateText !== '' ? date('M j, Y', strtotime($evaluatedDateText)) : 'N/A';
                ?>
                <tr>
                  <td><?php echo dashboard_escape($row['intern']); ?></td>
                  <td><?php echo dashboard_escape($row['internship_title']); ?></td>
                  <td><?php echo dashboard_escape($row['period']); ?></td>
                  <td><span style="color:#F59E0B"><i class="fas fa-star"></i> <?php echo number_format((float)$row['technical'], 1); ?></span></td>
                  <td><span style="color:#F59E0B"><i class="fas fa-star"></i> <?php echo number_format((float)$row['communication'], 1); ?></span></td>
                  <td><span style="color:#F59E0B"><i class="fas fa-star"></i> <?php echo number_format((float)$row['ethic'], 1); ?></span></td>
                  <td><span style="font-weight:700;color:#10B981"><?php echo number_format((float)$row['overall'], 1); ?></span></td>
                  <td style="max-width:240px;"><?php echo dashboard_escape($feedbackText); ?></td>
                  <td><?php echo dashboard_escape($evaluatedDateLabel); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" style="text-align:center;color:#999;">No evaluations submitted yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <div class="panel-card">
      <div class="panel-card-header"><h3>Eval Summary</h3></div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
        <div class="mini-row"><span>Total Evaluations</span><span style="font-weight:700"><?php echo (int)$summary['total_evaluations']; ?></span></div>
        <div class="mini-row"><span>Average Rating</span><span style="font-weight:700;color:#F59E0B"><i class="fas fa-star"></i> <?php echo number_format((float)$summary['average_rating'], 1); ?></span></div>
        <div class="mini-row"><span>Pending</span><span style="font-weight:700;color:#EF4444"><?php echo (int)$summary['pending']; ?></span></div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Rating Guide</h3></div>
      <div style="display:flex;flex-direction:column;gap:6px;font-size:.82rem">
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 5</span><span>Outstanding</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 4</span><span>Very Good</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 3</span><span>Good</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 2</span><span>Fair</span></div>
        <div class="mini-row"><span><i class="fas fa-star" style="color:#F59E0B"></i> 1</span><span>Needs Improvement</span></div>
      </div>
    </div>
  </div>
</div>

<script>
function setRating(groupId, rating, inputId) {
  var starsWrap = document.getElementById(groupId);
  if (!starsWrap) return;

  var stars = starsWrap.querySelectorAll('.fa-star');
  stars.forEach(function(star, index) {
    star.style.color = index < rating ? '#F59E0B' : '#ddd';
  });

  if (inputId) {
    var input = document.getElementById(inputId);
    if (input) input.value = rating;
  }
}

document.addEventListener('DOMContentLoaded', function () {
  setRating('techStars', parseFloat(document.getElementById('technical_score').value || '0'), 'technical_score');
  setRating('commStars', parseFloat(document.getElementById('communication_score').value || '0'), 'communication_score');
  setRating('ethicStars', parseFloat(document.getElementById('work_ethic_score').value || '0'), 'work_ethic_score');
});
</script>
