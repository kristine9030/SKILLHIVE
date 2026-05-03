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
  'period' => 'Final',
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
  $formState['period'] = 'Final';
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
    $errorMessage = 'Already evaluated.';
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

<?php if ($errorMessage !== ''): ?>
  <div class="panel-card" style="margin-bottom:16px;border-left:4px solid #ef4444;">
    <div style="font-size:.85rem;color:#666;">
      <i class="fas fa-triangle-exclamation" style="color:#ef4444;margin-right:6px"></i>
      <?php echo dashboard_escape($errorMessage); ?>
    </div>
  </div>
<?php endif; ?>

<style>
.eval-banner {
  background:
    radial-gradient(circle at 95% 50%, rgba(6, 78, 59, 0.65) 0%, transparent 70%),
    radial-gradient(circle at 85% 50%, rgba(15, 118, 110, 0.55) 0%, transparent 60%),
    linear-gradient(90deg, #ffffff 0%, #f0fdfa 25%, #134e4a 60%, #0f766e 85%, #0d5f58 100%);
  border-radius: 16px;
  padding: 20px 28px;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  position: relative;
  overflow: hidden;
  color: #111827;
  border: 1.5px solid rgba(15, 118, 110, 0.35);
  box-shadow: 0 8px 32px rgba(15, 118, 110, 0.15), 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.eval-banner::before {
  content: '';
  position: absolute;
  left: 20px;
  top: 50%;
  transform: translateY(-50%);
  width: 550px;
  height: 550px;
  background-image: url('/SkillHive/assets/media/banner%20other.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.25;
  pointer-events: none;
}

.eval-banner::after {
  content: '';
  position: absolute;
  right: 20px;
  top: 30%;
  transform: translateY(-50%);
  width: 500px;
  height: 500px;
  background-image: url('/SkillHive/assets/media/Banner.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  opacity: 0.35;
  pointer-events: none;
}

.eval-banner.collapsed {
  padding: 8px 16px;
  min-height: 0;
}

.eval-banner.collapsed .eb-main {
  display: none;
}

.eval-banner.collapsed .eb-toggle {
  display: none;
}

.eb-main {
  display: flex;
  align-items: center;
  gap: 24px;
  position: relative;
  z-index: 1;
  flex: 1;
}

.eb-info {
  flex: 1;
}

.eb-date {
  font-size: 12px;
  font-weight: 100;
  color: #9ca3af;
  margin-bottom: 4px;
  letter-spacing: 1px;
}

  .eb-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 2px;
    text-transform: capitalize;
    display: inline;
  }

.eb-desc {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
  max-width: 450px;
}

.eb-toggle {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(20, 184, 166, 0.15);
  color: #0f766e;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
  font-size: 13px;
}

.eb-toggle:hover {
  background: #fff;
  border-color: rgba(20, 184, 166, 0.3);
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(20, 184, 166, 0.1);
}

.eb-expand-hint {
  display: none;
  text-align: center;
  font-size: 13px;
  color: #0f766e;
  font-weight: 500;
  opacity: 0.8;
  cursor: pointer;
  padding: 4px 0;
  width: 100%;
  transition: opacity 0.2s ease;
}

.eb-expand-hint:hover {
  opacity: 1;
}

.eval-banner.collapsed .eb-expand-hint {
  display: block;
}

.eval-banner:not(.collapsed) .eb-expand-hint {
  display: none !important;
}

.eb-info {
  flex: 1;
  border-left: 1.5px solid rgba(255, 255, 255, 0.25);
  padding-left: 16px;
}

@media (max-width: 768px) {
  .eval-banner { flex-direction: column; text-align: center; }
}
</style>

<div class="eval-banner">
  <div class="eb-main">
    <div class="eb-info">
      <div class="eb-date"><?php echo date('l, jS F'); ?></div>
      <div class="eb-title">Good afternoon!</div>
      <div class="eb-desc">Rate intern performance, provide feedback, and track evaluation history.</div>
    </div>
  </div>
  <button type="button" class="eb-toggle" onclick="toggleEvalBanner()" title="Hide banner">
    <i class="fas fa-chevron-up"></i>
  </button>
  <div class="eb-expand-hint" onclick="toggleEvalBanner()">
    <i class="fas fa-chevron-down"></i> Show banner
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card employer-stat-postings">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total%20Evaluation.png" alt="Total Evaluations"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$summary['total_evaluations']; ?> total</div>
        <div class="stat-card-num"><?php echo (int)$summary['total_evaluations']; ?></div>
      </div>
      <div class="stat-card-label">Total Evaluations</div>
    </div>
  </div>
  <div class="stat-card employer-stat-applicants">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Rating.png" alt="Average Rating"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">out of 5.0</div>
        <div class="stat-card-num"><?php echo number_format((float)$summary['average_rating'], 1); ?></div>
      </div>
      <div class="stat-card-label">Average Rating</div>
    </div>
  </div>
  <div class="stat-card employer-stat-interviews">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Pendingg.png" alt="Pending"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">needs review</div>
        <div class="stat-card-num"><?php echo (int)$summary['pending']; ?></div>
      </div>
      <div class="stat-card-label">Pending</div>
    </div>
  </div>
  <div class="stat-card employer-stat-hired">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Needs%20Evaluated.png" alt="Interns to Evaluate"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo count($internshipOptions); ?> postings</div>
        <div class="stat-card-num"><?php echo count($internOptions); ?></div>
      </div>
      <div class="stat-card-label">Interns to Evaluate</div>
    </div>
  </div>
</div>

<div class="eval-grid">
  <div class="eval-main">
    <div class="panel-card">
      <div class="panel-card-header">
        <h3><i class="fas fa-pen-to-square" style="color:#12b3ac;margin-right:8px;"></i>Submit Evaluation</h3>
      </div>

      <form method="post" class="eval-form">
        <input type="hidden" name="internship_id" value="<?php echo (int)$selectedFilters['internship_id']; ?>">
        <div class="eval-form-row">
          <div class="eval-form-group">
            <label class="eval-form-label">Select Intern</label>
            <select class="eval-form-input" name="candidate_key" required>
              <option value="">Choose an intern to evaluate</option>
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
          <div class="eval-form-group">
            <label class="eval-form-label">Evaluation Period</label>
            <input type="hidden" name="period" value="Final">
            <div class="eval-form-input eval-period-display">
              <i class="fas fa-calendar-check" style="color:#12b3ac;"></i> Final Evaluation
            </div>
          </div>
        </div>

        <div class="eval-criteria-grid">
          <div class="eval-criterion">
            <label class="eval-form-label">Technical Skills</label>
            <input type="hidden" name="technical_score" id="technical_score" value="<?php echo (float)$formState['technical_score']; ?>">
            <div class="eval-stars" id="techStars">
              <i class="fas fa-star" data-rating="1" onclick="setRating('techStars',1,'technical_score')"></i>
              <i class="fas fa-star" data-rating="2" onclick="setRating('techStars',2,'technical_score')"></i>
              <i class="fas fa-star" data-rating="3" onclick="setRating('techStars',3,'technical_score')"></i>
              <i class="fas fa-star" data-rating="4" onclick="setRating('techStars',4,'technical_score')"></i>
              <i class="fas fa-star" data-rating="5" onclick="setRating('techStars',5,'technical_score')"></i>
            </div>
            <span class="eval-score-label" id="techScoreLabel">Not rated</span>
          </div>
          <div class="eval-criterion">
            <label class="eval-form-label">Communication</label>
            <input type="hidden" name="communication_score" id="communication_score" value="<?php echo (float)$formState['communication_score']; ?>">
            <div class="eval-stars" id="commStars">
              <i class="fas fa-star" data-rating="1" onclick="setRating('commStars',1,'communication_score')"></i>
              <i class="fas fa-star" data-rating="2" onclick="setRating('commStars',2,'communication_score')"></i>
              <i class="fas fa-star" data-rating="3" onclick="setRating('commStars',3,'communication_score')"></i>
              <i class="fas fa-star" data-rating="4" onclick="setRating('commStars',4,'communication_score')"></i>
              <i class="fas fa-star" data-rating="5" onclick="setRating('commStars',5,'communication_score')"></i>
            </div>
            <span class="eval-score-label" id="commScoreLabel">Not rated</span>
          </div>
          <div class="eval-criterion">
            <label class="eval-form-label">Work Ethic</label>
            <input type="hidden" name="work_ethic_score" id="work_ethic_score" value="<?php echo (float)$formState['work_ethic_score']; ?>">
            <div class="eval-stars" id="ethicStars">
              <i class="fas fa-star" data-rating="1" onclick="setRating('ethicStars',1,'work_ethic_score')"></i>
              <i class="fas fa-star" data-rating="2" onclick="setRating('ethicStars',2,'work_ethic_score')"></i>
              <i class="fas fa-star" data-rating="3" onclick="setRating('ethicStars',3,'work_ethic_score')"></i>
              <i class="fas fa-star" data-rating="4" onclick="setRating('ethicStars',4,'work_ethic_score')"></i>
              <i class="fas fa-star" data-rating="5" onclick="setRating('ethicStars',5,'work_ethic_score')"></i>
            </div>
            <span class="eval-score-label" id="ethicScoreLabel">Not rated</span>
          </div>
        </div>

        <div class="eval-form-group">
          <label class="eval-form-label">Comments & Feedback</label>
          <textarea class="eval-form-input eval-textarea" name="comments" rows="4" placeholder="Provide detailed feedback about the intern's performance, strengths, and areas for improvement..."><?php echo dashboard_escape($formState['comments']); ?></textarea>
        </div>

        <div class="eval-form-actions">
          <button type="submit" class="btn btn-primary" <?php echo $submitDisabled ? 'disabled' : ''; ?>>
            <i class="fas fa-paper-plane"></i> Submit Evaluation
          </button>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <div class="panel-card-header">
        <h3><i class="fas fa-clock-rotate-left" style="color:#12b3ac;margin-right:8px;"></i>Evaluation History</h3>
        <div class="panel-card-header-actions">
          <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?<?php echo http_build_query(['page' => 'employer/evaluation', 'internship_id' => (int)$selectedFilters['internship_id'], 'export' => 'csv']); ?>"><i class="fas fa-download"></i> Export CSV</a>
        </div>
      </div>
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Intern</th>
              <th>Internship</th>
              <th>Period</th>
              <th>Technical</th>
              <th>Communication</th>
              <th>Work Ethic</th>
              <th>Overall</th>
              <th>Feedback</th>
              <th>Date</th>
            </tr>
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
                $overallScore = (float)$row['overall'];
                $overallClass = $overallScore >= 4.5 ? 'outstanding' : ($overallScore >= 3.5 ? 'good' : ($overallScore >= 2.5 ? 'fair' : 'needs-improvement'));
                ?>
                <tr>
                  <td>
                    <div class="eval-intern-cell">
                      <div class="eval-intern-avatar"><?php echo dashboard_escape(substr($row['intern'], 0, 2)); ?></div>
                      <span><?php echo dashboard_escape($row['intern']); ?></span>
                    </div>
                  </td>
                  <td><?php echo dashboard_escape($row['internship_title']); ?></td>
                  <td><span class="eval-period-badge"><?php echo dashboard_escape($row['period']); ?></span></td>
                  <td><span class="eval-score"><i class="fas fa-star"></i> <?php echo number_format((float)$row['technical'], 1); ?></span></td>
                  <td><span class="eval-score"><i class="fas fa-star"></i> <?php echo number_format((float)$row['communication'], 1); ?></span></td>
                  <td><span class="eval-score"><i class="fas fa-star"></i> <?php echo number_format((float)$row['ethic'], 1); ?></span></td>
                  <td><span class="eval-overall <?php echo $overallClass; ?>"><?php echo number_format($overallScore, 1); ?></span></td>
                  <td class="eval-feedback" title="<?php echo dashboard_escape($feedbackRaw); ?>"><?php echo dashboard_escape($feedbackText); ?></td>
                  <td class="eval-date"><?php echo dashboard_escape($evaluatedDateLabel); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="eval-empty">
                  <div class="eval-empty-icon"><i class="fas fa-clipboard-list"></i></div>
                  <div class="eval-empty-text">No evaluations submitted yet.</div>
                  <div class="eval-empty-sub">Select an intern above to submit your first evaluation.</div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="eval-side">
    <div class="panel-card eval-filter-card">
      <div class="panel-card-header"><h3>Filter</h3></div>
      <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="eval-filter-form">
        <input type="hidden" name="page" value="employer/evaluation">
        <label class="eval-form-label">Internship</label>
        <select class="eval-form-input" name="internship_id" onchange="this.form.submit()">
          <option value="0">All Internships</option>
          <?php foreach ($internshipOptions as $internship): ?>
            <?php $internshipId = (int)($internship['internship_id'] ?? 0); ?>
            <option value="<?php echo $internshipId; ?>" <?php echo $selectedFilters['internship_id'] === $internshipId ? 'selected' : ''; ?>>
              <?php echo dashboard_escape((string)($internship['title'] ?? 'Internship')); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="eval-filter-actions">
          <button class="btn btn-primary btn-sm" type="submit">Apply</button>
          <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/evaluation">Reset</a>
        </div>
      </form>
    </div>

    <div class="panel-card">
      <div class="panel-card-header"><h3>Rating Guide</h3></div>
      <div class="eval-rating-guide">
        <div class="eval-guide-item">
          <div class="eval-guide-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <div class="eval-guide-label">Outstanding</div>
        </div>
        <div class="eval-guide-item">
          <div class="eval-guide-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></div>
          <div class="eval-guide-label">Very Good</div>
        </div>
        <div class="eval-guide-item">
          <div class="eval-guide-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i></div>
          <div class="eval-guide-label">Good</div>
        </div>
        <div class="eval-guide-item">
          <div class="eval-guide-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i></div>
          <div class="eval-guide-label">Fair</div>
        </div>
        <div class="eval-guide-item">
          <div class="eval-guide-stars"><i class="fas fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i></div>
          <div class="eval-guide-label">Needs Improvement</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleEvalBanner() {
  document.querySelector('.eval-banner').classList.toggle('collapsed');
}

var evalLabels = ['','Needs Improvement','Fair','Good','Very Good','Outstanding'];

function setRating(groupId, rating, inputId) {
  var starsWrap = document.getElementById(groupId);
  if (!starsWrap) return;

  var stars = starsWrap.querySelectorAll('.fa-star, .fas.fa-star, .far.fa-star');
  stars.forEach(function(star, index) {
    if (index < rating) {
      star.className = 'fas fa-star';
      star.style.color = '#12b3ac';
    } else {
      star.className = 'far fa-star';
      star.style.color = '#e5e7eb';
    }
  });

  if (inputId) {
    var input = document.getElementById(inputId);
    if (input) input.value = rating;
  }

  var scoreLabelId = '';
  if (inputId === 'technical_score') scoreLabelId = 'techScoreLabel';
  else if (inputId === 'communication_score') scoreLabelId = 'commScoreLabel';
  else if (inputId === 'work_ethic_score') scoreLabelId = 'ethicScoreLabel';

  if (scoreLabelId) {
    var label = document.getElementById(scoreLabelId);
    if (label) {
      if (rating > 0) {
        label.textContent = rating + '/5 — ' + evalLabels[rating];
        label.className = 'eval-score-label rated';
      } else {
        label.textContent = 'Not rated';
        label.className = 'eval-score-label';
      }
    }
  }
}

document.addEventListener('DOMContentLoaded', function () {
  setRating('techStars', parseFloat(document.getElementById('technical_score').value || '0'), 'technical_score');
  setRating('commStars', parseFloat(document.getElementById('communication_score').value || '0'), 'communication_score');
  setRating('ethicStars', parseFloat(document.getElementById('work_ethic_score').value || '0'), 'work_ethic_score');
});
</script>
