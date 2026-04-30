<?php
/**
 * Purpose: Employer internship posting page that validates the form, loads selectable skills, and creates internship plus internship_skill records.
 * Tables/columns used: Indirectly uses skill(skill_id, skill_name), internship(internship_id, employer_id, title, description, duration_weeks, allowance, work_setup, location, slots_available, status, posted_at, created_at), internship_skill(internship_id, skill_id, required_level, is_mandatory), application(application_id, internship_id), student(student_id), interview(application_id).
 */

// Database connection (layout.php doesn't require it globally)
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/dashboard/formatters.php';
require_once __DIR__ . '/post_internship/internship_data.php';

// ── 1. Auth check ──────────────────────────────
// The login flow stores the employer PK as `user_id` with role `employer`.
$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);

if (!$employerId) {
    // Not logged in as employer — bounce to login
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

$verificationStatus = getEmployerVerificationStatus($pdo, (int)$employerId) ?? (string)($_SESSION['verification_status'] ?? '');
$_SESSION['verification_status'] = $verificationStatus;
if (!isEmployerApproved($verificationStatus)) {
  $_SESSION['status'] = 'Your employer account is pending admin verification. Posting module is locked until approval.';
  header('Location: /SkillHive/layout.php?page=employer/dashboard');
  exit;
}

// ── 2. Constants & master data ─────────────────
$errors          = [];
$old             = [];
$allowedWorkSetup = ['Remote', 'On-site', 'Hybrid'];
$allowedStatus    = ['Draft', 'Open', 'Closed'];
$allowedLevels    = ['Beginner', 'Intermediate', 'Advanced'];

// Load every skill from the `skill` table
$skills = [];
try {
  $skills = getSkillMasterList($pdo);
} catch (Throwable $e) {
    $errors[] = 'Failed to load skills list.';
}
// Build a fast look‑up of valid IDs
$validSkillIds = array_map(static fn($s) => (int)$s['skill_id'], $skills);

$postingsPerPage = 4;
$postingsPage = max(1, (int)($_GET['postings_page'] ?? 1));
$postingsTotal = 0;
$postingsTotalPages = 1;
$myPostings = [];

// ── 3. Handle form submission ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['edit_posting_id'])) {
    $editPostingId = (int)($_POST['edit_posting_id'] ?? 0);
    $editStatus = (string)($_POST['edit_status'] ?? '');
    $editPage = max(1, (int)($_POST['postings_page'] ?? $postingsPage));

    try {
      $editResult = updateEmployerInternshipPostingStatus($pdo, (int)$employerId, $editPostingId, $editStatus);
      if (!empty($editResult['success'])) {
        $_SESSION['status'] = 'Posting updated successfully.';
      } else {
        $errors[] = (string)($editResult['error'] ?? 'Unable to update posting status.');
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to update posting right now. Please try again.';
    }

    if (empty($errors)) {
      header('Location: /SkillHive/layout.php?page=employer/post_internship&postings_page=' . $editPage . '&focus_posting=' . $editPostingId . '#my-postings');
      exit;
    }
  } elseif (isset($_POST['delete_posting_id'])) {
    $deletePostingId = (int)($_POST['delete_posting_id'] ?? 0);
    $deletePage = max(1, (int)($_POST['postings_page'] ?? $postingsPage));

    try {
      $deleteResult = deleteEmployerInternshipPosting($pdo, (int)$employerId, $deletePostingId);
      if (!empty($deleteResult['success'])) {
        $_SESSION['status'] = 'Posting deleted successfully.';
      } else {
        $errors[] = (string)($deleteResult['error'] ?? 'Unable to delete posting.');
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to delete posting right now. Please try again.';
    }

    if (empty($errors)) {
      header('Location: /SkillHive/layout.php?page=employer/post_internship&postings_page=' . $deletePage . '#my-postings');
      exit;
    }
  } else {
    $validated = validatePostInternshipPayload($_POST, $validSkillIds);
    $errors = $validated['errors'];
    $old = $validated['old'];
    $allowedWorkSetup = $validated['allowed_work_setup'];
    $allowedStatus = $validated['allowed_status'];
    $allowedLevels = $validated['allowed_levels'];

      /* ── 4. INSERT using a transaction (prepared stmts = no SQL‑injection) ── */
      if (empty($errors)) {
          try {
        createInternshipPosting($pdo, (int)$employerId, $validated);

              // 5. Flash success & stay on the postings page
              $_SESSION['status'] = 'Internship posted successfully!';
              header('Location: /SkillHive/layout.php?page=employer/post_internship&postings_page=1#my-postings');
              exit;

          } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $errors[] = 'Database error — please try again.';
          }
      }
  }
}

try {
  $postingsTotal = getEmployerInternshipPostingsTotal($pdo, (int)$employerId);
  $postingsTotalPages = max(1, (int)ceil($postingsTotal / $postingsPerPage));
  $postingsPage = min($postingsPage, $postingsTotalPages);
  $postingsOffset = ($postingsPage - 1) * $postingsPerPage;
  $myPostings = getEmployerInternshipPostings($pdo, (int)$employerId, $postingsPerPage, $postingsOffset);
} catch (Throwable $e) {
  $myPostings = [];
  $postingsTotal = 0;
  $postingsTotalPages = 1;
  $postingsPage = 1;
}

$focusPostingId = max(0, (int)($_GET['focus_posting'] ?? 0));
$selectedPosting = null;
if (!empty($myPostings)) {
  $selectedPosting = $myPostings[0];
  if ($focusPostingId > 0) {
    foreach ($myPostings as $postingRow) {
      if ((int)($postingRow['internship_id'] ?? 0) === $focusPostingId) {
        $selectedPosting = $postingRow;
        break;
      }
    }
  }
}

// ── Helpers ────────────────────────────────────
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function oldVal(array $old, string $key, string $default = ''): string {
    return e($old[$key] ?? $default);
}

function posting_duration_hours_label($durationWeeks): string {
  $requiredHoursFloor = defined('SKILLHIVE_REQUIRED_OJT_HOURS') ? (int)SKILLHIVE_REQUIRED_OJT_HOURS : 500;
  $hours = max($requiredHoursFloor, max(0, (int)$durationWeeks) * 40);
  if ($hours <= 0) {
    return 'N/A';
  }

  return $hours . ' hours';
}
?>

<!-- ═══════════════════════════════════════════
     HTML  —  uses skillhive.css classes
     ═══════════════════════════════════════════ -->

<style>
/* Modern color variables */
:root {
  --primary: #138b84;
  --primary-light: #12a89f;
  --secondary: #10B981;
  --border-light: #e5e7eb;
  --bg-light: #f9fafb;
  --text-primary: #111827;
  --text-secondary: #6b7280;
}

/* Modern page wrapper */
.posting-container {
  max-width: 1300px;
  margin: 0 auto;
  padding: 0;
}

/* Section header styling */
.section-header {
  margin-bottom: 28px;
  padding-bottom: 24px;
  border-bottom: 2px solid var(--border-light);
}

.section-header h2 {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 8px 0;
  font-size: 24px;
  font-weight: 700;
  color: var(--text-primary);
}

.section-header-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
}

.section-header p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 14px;
}

/* Modern postings grid */
.postings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.posting-card {
  background: #fff;
  border: 2px solid var(--border-light);
  border-radius: 14px;
  padding: 20px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.posting-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary), var(--secondary));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s ease;
}

.posting-card:hover {
  border-color: var(--primary);
  box-shadow: 0 12px 32px rgba(19, 139, 132, 0.12);
  transform: translateY(-2px);
}

.posting-card:hover::before {
  transform: scaleX(1);
}

.posting-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}

.posting-card-title {
  font-weight: 700;
  font-size: 16px;
  color: var(--text-primary);
  flex: 1;
  line-height: 1.4;
  margin: 0;
}

.posting-card-meta {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-light);
}

.posting-card-stat {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-secondary);
}

.posting-card-stat i {
  color: var(--primary);
  width: 16px;
  text-align: center;
}

.posting-card-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.posting-card-btn {
  flex: 1;
  min-width: 70px;
  padding: 8px 12px;
  border: none;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.posting-card-btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff;
}

.posting-card-btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(19, 139, 132, 0.3);
}

.posting-card-btn-secondary {
  background: var(--bg-light);
  color: var(--text-primary);
  border: 2px solid var(--border-light);
}

.posting-card-btn-secondary:hover {
  background: var(--text-primary);
  color: #fff;
  border-color: var(--text-primary);
}

/* Modern form styling */
.form-section {
  background: #fff;
  border-radius: 14px;
  border: 1px solid var(--border-light);
  padding: 28px;
  margin-bottom: 28px;
}

.form-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.form-section-subtitle {
  margin: 0 0 24px 0;
  color: var(--text-secondary);
  font-size: 13px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-weight: 600;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-primary);
  font-size: 14px;
}

.form-label-required {
  color: #ef4444;
  font-weight: 700;
}

.form-control-wrapper {
  position: relative;
}

.form-control {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--border-light);
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  background: #fff;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(19, 139, 132, 0.08);
}

.form-control:hover:not(:focus) {
  border-color: var(--primary);
}

.form-hint {
  display: block;
  margin-top: 6px;
  color: var(--text-secondary);
  font-size: 12px;
}

/* Modern location selector */
.location-selector {
  border: 1px solid var(--border-light);
  background: linear-gradient(135deg, #fafbfc 0%, #f0f9f8 100%);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.location-select-row {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 2px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 10px 14px;
  transition: all 0.3s ease;
}

.location-select-row:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(19, 139, 132, 0.08);
  background: #fafbfc;
}

.location-select-row.disabled {
  background: #f9fafb;
  border-color: #e5e7eb;
  opacity: 0.6;
}

.location-label {
  min-width: 72px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 6px;
}

.location-label::before {
  content: '';
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--primary);
}

.location-select-row select {
  border: none;
  background: transparent;
  padding: 8px 4px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
  flex: 1;
  cursor: pointer;
}

.location-select-row select:focus {
  outline: none;
}

.location-preview {
  margin-top: 12px;
  padding: 12px 14px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: #f0f9f8;
  font-weight: 500;
  color: var(--text-primary);
  transition: all 0.3s ease;
}

.location-preview:focus {
  border-color: var(--primary);
  background: #fff;
}

.location-help {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  font-size: 12px;
  color: var(--text-secondary);
}

.location-help i {
  color: var(--primary);
  font-size: 12px;
}

/* Modern currency input */
.currency-input-wrapper {
  display: flex;
  align-items: center;
  border: 2px solid var(--border-light);
  border-radius: 10px;
  background: #fff;
  padding: 0 14px;
  transition: all 0.3s ease;
}

.currency-input-wrapper:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(19, 139, 132, 0.08);
}

.currency-symbol {
  color: var(--text-secondary);
  font-weight: 600;
  font-size: 16px;
  margin-right: 8px;
}

.currency-input-wrapper input {
  border: none;
  padding: 12px 8px;
  font-size: 14px;
  flex: 1;
  background: transparent;
}

.currency-input-wrapper input:focus {
  outline: none;
}

/* Modern skills table */
.skills-container {
  border: 2px solid var(--border-light);
  border-radius: 12px;
  overflow: hidden;
  max-height: 480px;
  display: flex;
  flex-direction: column;
}

.skills-header {
  background: linear-gradient(135deg, var(--text-primary), #1f2937);
  color: #fff;
  padding: 14px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: grid;
  grid-template-columns: 50px 1fr 150px 100px;
  gap: 12px;
  align-items: center;
  sticky: top;
  z-index: 10;
}

.skills-body {
  overflow-y: auto;
  flex: 1;
}

.skills-row {
  display: grid;
  grid-template-columns: 50px 1fr 150px 100px;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-light);
  transition: background 0.2s ease;
}

.skills-row:hover {
  background: var(--bg-light);
}

.skills-row:nth-child(even) {
  background: #fafbfc;
}

.skills-row input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  cursor: pointer;
}

.skills-row select {
  padding: 8px 10px;
  font-size: 12px;
  border: 2px solid var(--border-light);
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.3s ease;
}

.skills-row select:focus {
  outline: none;
  border-color: var(--primary);
}

.skills-info {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
  padding: 12px 16px;
  background: #f0f9f8;
  border-radius: 10px;
  font-size: 13px;
  color: var(--text-secondary);
}

.skills-info i {
  color: var(--primary);
  font-size: 14px;
}

/* Modern buttons */
.btn-modern {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(19, 139, 132, 0.3);
}

.btn-secondary {
  background: var(--bg-light);
  color: var(--text-primary);
  border: 2px solid var(--border-light);
}

.btn-secondary:hover {
  background: var(--text-primary);
  color: #fff;
  border-color: var(--text-primary);
}

.btn-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 24px;
}

/* Empty state styling */
.empty-state {
  text-align: center;
  padding: 48px 32px;
  border: 2px dashed var(--border-light);
  border-radius: 12px;
  background: var(--bg-light);
}

.empty-state-icon {
  font-size: 48px;
  color: #d1d5db;
  margin-bottom: 16px;
  display: block;
}

.empty-state-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.empty-state-text {
  font-size: 13px;
  color: #9ca3af;
  margin: 0;
}

/* Error styling */
.error-alert {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(255, 107, 107, 0.04));
  border: 2px solid rgba(239, 68, 68, 0.15);
  color: #c41c3b;
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 28px;
  font-size: 13px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.error-alert i {
  margin-top: 2px;
  font-size: 16px;
  flex-shrink: 0;
}

.error-alert strong {
  font-size: 14px;
  display: block;
  margin-bottom: 8px;
}

.error-alert ul {
  margin: 0;
  padding-left: 20px;
  line-height: 1.8;
}

/* Pagination styling */
.pagination-wrapper {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 24px;
  align-items: center;
}

.pagination-btn {
  padding: 8px 12px;
  border: 2px solid var(--border-light);
  border-radius: 8px;
  background: #fff;
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.pagination-btn:hover {
  background: var(--text-primary);
  color: #fff;
  border-color: var(--text-primary);
}

.pagination-btn-active {
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  color: #fff;
  border-color: var(--primary);
}

.pagination-ellipsis {
  padding: 6px 8px;
  color: var(--text-secondary);
  font-weight: 500;
}

@media (max-width: 900px) {
  .postings-grid {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .skills-header,
  .skills-row {
    grid-template-columns: 40px 1fr 120px 80px;
    gap: 8px;
  }
}
</style>

<!-- Page Header -->
<div class="posting-container">
  <div class="page-header" style="margin-bottom: 32px;">
    <div>
      <h1 class="page-title" style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
        <span class="section-header-icon">
          <i class="fa-solid fa-briefcase"></i>
        </span>
        Post New Internship
      </h1>
      <p class="page-sub">Create and manage internship listings to attract qualified student candidates</p>
    </div>
  </div>

  <!-- Errors -->
  <?php if (!empty($errors)): ?>
  <div class="error-alert">
    <div>
      <i class="fa-solid fa-circle-exclamation"></i>
    </div>
    <div style="flex: 1;">
      <strong>Please fix the following errors:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?php echo e($err); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <!-- My Postings Section -->
  <div class="section-header" id="my-postings">
    <h2>
      <i class="fa-solid fa-list"></i>
      My Internship Postings
    </h2>
    <p>Manage and track your active internship listings</p>
  </div>

  <?php if (!empty($myPostings)): ?>
  <div class="postings-grid">
    <?php foreach ($myPostings as $posting): ?>
      <?php
      $postingId = (int)($posting['internship_id'] ?? 0);
      $title = (string)($posting['title'] ?? 'Untitled Internship');
      $status = (string)($posting['status'] ?? 'pending');
      $location = (string)($posting['location'] ?? 'N/A');
      $applicants = (int)($posting['applicants_count'] ?? 0);
      $slots = (int)($posting['slots_available'] ?? 0);
      $allowance = (float)($posting['allowance'] ?? 0);
      $description = (string)($posting['description'] ?? 'No description provided.');
      $postedAt = (string)($posting['posted_at'] ?? '');
      $workSetup = (string)($posting['work_setup'] ?? 'N/A');
      $duration = max((defined('SKILLHIVE_REQUIRED_OJT_HOURS') ? (int)SKILLHIVE_REQUIRED_OJT_HOURS : 500), max(0, (int)($posting['duration_weeks'] ?? 0)) * 40);
      ?>
      <div class="posting-card">
        <div class="posting-card-header">
          <h3 class="posting-card-title"><?php echo e($title); ?></h3>
          <span class="status-pill <?php echo dashboard_status_class($status); ?>">
            <?php echo e(dashboard_status_label($status)); ?>
          </span>
        </div>

        <div class="posting-card-meta">
          <div class="posting-card-stat">
            <i class="fas fa-map-marker-alt"></i>
            <span><?php echo e($location); ?></span>
          </div>
          <div class="posting-card-stat">
            <i class="fas fa-users"></i>
            <span><?php echo $applicants; ?> applicant<?php echo $applicants !== 1 ? 's' : ''; ?></span>
          </div>
          <div class="posting-card-stat">
            <i class="fas fa-chair"></i>
            <span><?php echo $slots; ?> slot<?php echo $slots !== 1 ? 's' : ''; ?> available</span>
          </div>
          <div class="posting-card-stat">
            <i class="fas fa-peso-sign"></i>
            <span>₱<?php echo number_format($allowance, 2); ?> allowance</span>
          </div>
        </div>

        <div style="margin-bottom: 16px; font-size: 12px; color: #6b7280;">
          <i class="fas fa-clock"></i>
          Posted <?php echo e(dashboard_time_ago($postedAt)); ?>
        </div>

        <p style="margin: 0 0 16px 0; font-size: 13px; line-height: 1.5; color: #555; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
          <?php echo e($description); ?>
        </p>

        <div class="posting-card-actions">
          <a href="/SkillHive/layout.php?page=employer/candidates&position=<?php echo $postingId; ?>" class="posting-card-btn posting-card-btn-primary">
            <i class="fas fa-eye"></i> View Apps
          </a>
          <form method="post" action="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>" style="flex: 1;">
            <input type="hidden" name="edit_posting_id" value="<?php echo $postingId; ?>">
            <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
            <select name="edit_status" class="posting-card-btn posting-card-btn-secondary" style="padding: 8px 10px; width: 100%; border: 2px solid var(--border-light); border-radius: 8px; font-size: 12px;">
              <option value="Open" <?php echo (strtolower($status) === 'closed') ? '' : 'selected'; ?>>Open</option>
              <option value="Closed" <?php echo (strtolower($status) === 'closed') ? 'selected' : ''; ?>>Closed</option>
            </select>
          </form>
          <form method="post" action="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>#my-postings" onsubmit="return confirm('Are you sure you want to delete this posting? This action cannot be undone.');" style="flex: 1;">
            <input type="hidden" name="delete_posting_id" value="<?php echo $postingId; ?>">
            <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
            <button type="submit" class="posting-card-btn posting-card-btn-secondary" style="width: 100%; background: rgba(239, 68, 68, 0.08); color: #c41c3b; border: 2px solid rgba(239, 68, 68, 0.2);">
              <i class="fas fa-trash"></i> Delete
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

    <?php if ($postingsTotalPages > 1): ?>
      <?php
      $startPage = max(1, $postingsPage - 2);
      $endPage = min($postingsTotalPages, $postingsPage + 2);
      if (($endPage - $startPage) < 4) {
        if ($startPage === 1) {
          $endPage = min($postingsTotalPages, $startPage + 4);
        } elseif ($endPage === $postingsTotalPages) {
          $startPage = max(1, $endPage - 4);
        }
      }
      ?>
      <div class="pagination-wrapper">
        <?php if ($postingsPage > 1): ?>
          <a class="pagination-btn" href="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo ($postingsPage - 1); ?>">
            <i class="fas fa-chevron-left"></i> Previous
          </a>
        <?php endif; ?>

        <?php if ($startPage > 1): ?>
          <a class="pagination-btn" href="/SkillHive/layout.php?page=employer/post_internship&postings_page=1">1</a>
          <?php if ($startPage > 2): ?>
            <span class="pagination-ellipsis">…</span>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($pageNum = $startPage; $pageNum <= $endPage; $pageNum++): ?>
          <?php if ($pageNum === $postingsPage): ?>
            <span class="pagination-btn pagination-btn-active"><?php echo $pageNum; ?></span>
          <?php else: ?>
            <a class="pagination-btn" href="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $pageNum; ?>"><?php echo $pageNum; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $postingsTotalPages): ?>
          <?php if ($endPage < ($postingsTotalPages - 1)): ?>
            <span class="pagination-ellipsis">…</span>
          <?php endif; ?>
          <a class="pagination-btn" href="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsTotalPages; ?>"><?php echo $postingsTotalPages; ?></a>
        <?php endif; ?>

        <?php if ($postingsPage < $postingsTotalPages): ?>
          <a class="pagination-btn" href="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo ($postingsPage + 1); ?>">
            Next <i class="fas fa-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">
        <i class="fas fa-inbox"></i>
      </div>
      <div class="empty-state-title">No internship postings yet</div>
      <p class="empty-state-text">Start by creating your first internship posting using the form below</p>
    </div>
  <?php endif; ?>
</div>

<div class="page-header" style="margin-top:18px;">
  <h2 class="page-title"><i class="fa-solid fa-pen-to-square" style="color:var(--red);"></i> Create New Posting</h2>
  <p class="page-sub">Use the form below to add a new internship.</p>
</div>

<!-- Form Card -->
<div class="card">
  <h3 class="card-title"><i class="fa-solid fa-briefcase" style="color:var(--red);margin-right:6px;"></i> Internship Details</h3>

  <form method="post" action="/SkillHive/layout.php?page=employer/post_internship">

    <!-- Row: Title + Work Setup -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Title <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="text" name="title" placeholder="e.g. Web Developer Intern" value="<?php echo oldVal($old, 'title'); ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Work Setup <span style="color:var(--red);">*</span></label>
        <select class="form-control" name="work_setup" required>
          <option value="">— Select —</option>
          <?php foreach ($allowedWorkSetup as $w): ?>
            <option value="<?php echo e($w); ?>" <?php echo (oldVal($old,'work_setup') === $w ? 'selected' : ''); ?>>
              <?php echo e($w); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Description -->
    <div class="form-group">
      <label class="form-label">Description <span style="color:var(--red);">*</span></label>
      <textarea class="form-control" name="description" rows="5" placeholder="Describe the role, responsibilities, and what the intern will learn…" required><?php echo oldVal($old, 'description'); ?></textarea>
    </div>

    <!-- Row: Duration + Allowance -->
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Duration (hours) <span style="color:var(--red);">*</span></label>
        <input class="form-control" type="number" min="500" step="1" name="duration_hours" placeholder="e.g. 500" value="<?php echo oldVal($old, 'duration_hours', '500'); ?>" required>
  <!-- Create New Posting Section -->
  <div class="section-header" style="margin-top: 40px;">
    <h2>
      <i class="fa-solid fa-plus-circle"></i>
      Create New Posting
    </h2>
    <p>Add a new internship listing to attract qualified candidates</p>
  </div>

  <!-- Internship Details Form -->
  <div class="form-section">
    <h3 class="form-section-title">
      <i class="fa-solid fa-briefcase"></i>
      Internship Details
    </h3>
    <p class="form-section-subtitle">Fill in the basic information about your internship position</p>

    <form method="post" action="/SkillHive/layout.php?page=employer/post_internship" id="internshipForm">
      <!-- Row: Title + Work Setup -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">
            Position Title
            <span class="form-label-required">*</span>
          </label>
          <input class="form-control" type="text" name="title" placeholder="e.g., Web Developer Intern, UX Designer Intern" value="<?php echo oldVal($old, 'title'); ?>" required>
          <span class="form-hint">A clear, descriptive title helps attract the right candidates</span>
        </div>
        <div class="form-group">
          <label class="form-label">
            Work Setup
            <span class="form-label-required">*</span>
          </label>
          <select class="form-control" name="work_setup" required>
            <option value="">— Select work arrangement —</option>
            <?php foreach ($allowedWorkSetup as $w): ?>
              <option value="<?php echo e($w); ?>" <?php echo (oldVal($old,'work_setup') === $w ? 'selected' : ''); ?>>
                <?php echo e($w); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Description -->
      <div class="form-group">
        <label class="form-label">
          Job Description
          <span class="form-label-required">*</span>
        </label>
        <textarea class="form-control" name="description" rows="6" placeholder="Describe the role, key responsibilities, and what the intern will learn. Be detailed and engaging!" required><?php echo oldVal($old, 'description'); ?></textarea>
        <span class="form-hint">Well-written descriptions attract more qualified applicants</span>
      </div>

      <!-- Row: Duration + Allowance + Slots -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">
            Duration (hours)
            <span class="form-label-required">*</span>
          </label>
          <input class="form-control" type="number" min="500" step="1" name="duration_hours" placeholder="e.g., 500" value="<?php echo oldVal($old, 'duration_hours', '500'); ?>" required>
          <span class="form-hint">Minimum 500 hours. Typically 40 hours/week</span>
        </div>
        <div class="form-group">
          <label class="form-label">
            Monthly Allowance
            <span class="form-label-required">*</span>
          </label>
          <div class="currency-input-wrapper">
            <span class="currency-symbol">₱</span>
            <input type="number" min="0" step="0.01" name="allowance" placeholder="e.g., 5000" value="<?php echo oldVal($old, 'allowance'); ?>" required>
          </div>
          <span class="form-hint">This is the monthly stipend offered to interns</span>
        </div>
        <div class="form-group">
          <label class="form-label">
            Slots Available
            <span class="form-label-required">*</span>
          </label>
          <input class="form-control" type="number" min="1" placeholder="e.g., 3" name="slots_available" value="<?php echo oldVal($old, 'slots_available'); ?>" required>
          <span class="form-hint">How many interns do you want to hire?</span>
        </div>
      </div>

      <!-- Location Selection -->
      <div class="form-group">
        <label class="form-label">
          Work Location
          <span class="form-label-required">*</span>
        </label>
        <div class="location-selector">
          <div class="location-select-row" id="postingRegionWrap">
            <span class="location-label">Region</span>
            <select class="form-control" id="postingRegionSelect" name="region_id" data-old-value="<?php echo oldVal($old, 'region_id'); ?>">
              <option value="">Select region…</option>
            </select>
          </div>

          <div id="postingProvinceContainer">
            <div class="location-select-row disabled" id="postingProvinceWrap">
              <span class="location-label">Province</span>
              <select class="form-control" id="postingProvinceSelect" name="province_id" data-old-value="<?php echo oldVal($old, 'province_id'); ?>" disabled>
                <option value="">Select province…</option>
              </select>
            </div>
          </div>

          <div id="postingCityContainer">
            <div class="location-select-row disabled" id="postingCityWrap">
              <span class="location-label">City</span>
              <select class="form-control" id="postingCitySelect" name="city_id" data-old-value="<?php echo oldVal($old, 'city_id'); ?>" disabled>
                <option value="">Select city…</option>
              </select>
            </div>
          </div>

          <input class="location-preview" type="text" id="postingLocationPreview" placeholder="Your selected location will appear here" value="<?php echo oldVal($old, 'location'); ?>" readonly>
          <div class="location-help">
            <i class="fas fa-info-circle"></i>
            <span>Using Philippine PSGC database for accurate location data</span>
          </div>
        </div>

        <input type="hidden" id="postingRegionName" name="region_name" value="<?php echo oldVal($old, 'region_name'); ?>">
        <input type="hidden" id="postingProvinceName" name="province_name" value="<?php echo oldVal($old, 'province_name'); ?>">
        <input type="hidden" id="postingCityName" name="city_name" value="<?php echo oldVal($old, 'city_name'); ?>">
        <input type="hidden" id="postingLocationValue" name="location" value="<?php echo oldVal($old, 'location'); ?>">
      </div>

      <!-- Status -->
      <div class="form-group" style="max-width: 300px;">
        <label class="form-label">
          Initial Status
          <span class="form-label-required">*</span>
        </label>
        <select class="form-control" name="status" required>
          <?php foreach ($allowedStatus as $s): ?>
            <option value="<?php echo e($s); ?>" <?php echo (oldVal($old,'status','Open') === $s ? 'selected' : ''); ?>>
              <?php echo e($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">You can change this status anytime</span>
      </div>
    </form>
  </div>

  <!-- Required Skills Section -->
  <div class="form-section">
    <h3 class="form-section-title">
      <i class="fa-solid fa-list-check"></i>
      Required Skills
    </h3>
    <p class="form-section-subtitle">Select the skills candidates should have. Mark mandatory skills that are essential for the role</p>

    <!-- Skills Search -->
    <div class="form-group" style="max-width: 400px; margin-bottom: 20px;">
      <input class="form-control" type="text" id="skillSearch" placeholder="🔍  Search skills…" oninput="filterSkills()">
      <span class="form-hint">Type to filter the skills list below</span>
    </div>

    <!-- Skills Table -->
    <div class="skills-container">
      <div class="skills-header">
        <span></span>
        <span>Skill Name</span>
        <span>Proficiency Level</span>
        <span>Mandatory</span>
      </div>
      <div class="skills-body">
        <?php if (empty($skills)): ?>
          <div style="padding: 32px 24px; text-align: center; color: var(--text-secondary);">
            <i class="fas fa-exclamation-circle" style="font-size: 24px; margin-bottom: 12px; display: block; color: #d1d5db;"></i>
            <div style="font-weight: 600; margin-bottom: 4px;">No skills available</div>
            <div style="font-size: 12px;">Please ensure the skills database is populated</div>
          </div>
        <?php endif; ?>

        <?php foreach ($skills as $idx => $skill):
          $sid = (int)$skill['skill_id'];
          $checked = isset($_POST['skills']) && in_array((string)$sid, array_map('strval', $_POST['skills'] ?? []), true);
        ?>
        <div class="skills-row" data-name="<?php echo e(strtolower($skill['skill_name'])); ?>">
          <input type="checkbox" form="internshipForm" name="skills[]" value="<?php echo $sid; ?>" <?php echo $checked ? 'checked' : ''; ?>>
          <div style="font-weight: 500; color: var(--text-primary);"><?php echo e($skill['skill_name']); ?></div>
          <select form="internshipForm" name="skill_level[<?php echo $sid; ?>]" class="form-control">
            <?php foreach ($allowedLevels as $lvl): ?>
              <?php $sel = (($_POST['skill_level'][$sid] ?? 'Beginner') === $lvl) ? 'selected' : ''; ?>
              <option value="<?php echo e($lvl); ?>" <?php echo $sel; ?>><?php echo e($lvl); ?></option>
            <?php endforeach; ?>
          </select>
          <label style="display: flex; align-items: center; justify-content: center; cursor: pointer;">
            <input type="checkbox" form="internshipForm" name="skill_mandatory[<?php echo $sid; ?>]" value="1" <?php echo isset($_POST['skill_mandatory'][$sid]) ? 'checked' : ''; ?>>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="skills-info">
      <i class="fas fa-lightbulb"></i>
      <span>Tip: Mark skills as "Mandatory" if they are essential for the role. Other skills are considered "Nice to have"</span>
    </div>
  </div>

  <!-- Form Actions -->
  <div class="btn-actions">
    <button type="submit" form="internshipForm" class="btn-modern btn-primary">
      <i class="fa-solid fa-rocket"></i>
      Post Internship
    </button>
    <a href="/SkillHive/layout.php?page=employer/dashboard" class="btn-modern btn-secondary">
      <i class="fa-solid fa-arrow-left"></i>
      Cancel
    </a>
  </div>
</div>

<!-- Hidden form wrapper for form attribute reference -->
<form id="internshipForm" method="post" action="/SkillHive/layout.php?page=employer/post_internship" style="display:none;"></form>

<!-- Initialize form field associations -->
<script>
  // Attach all inputs/selects/textareas inside form sections to the internshipForm
  document.querySelectorAll('.form-section input, .form-section select, .form-section textarea').forEach(el => {
    if (!el.hasAttribute('form') && el.name) {
      el.setAttribute('form', 'internshipForm');
    }
  });

  const psgcApi = {
    region: 'https://psgc.rootscratch.com/region',
    province: 'https://psgc.rootscratch.com/province',
    municipalCity: 'https://psgc.rootscratch.com/municipal-city'
  };

  const fallbackPsgcApi = {
    provinces: 'https://psgc.gitlab.io/api/provinces/',
    citiesMunicipalities: 'https://psgc.gitlab.io/api/cities-municipalities/'
  };

  let fallbackProvincesCache = null;
  let fallbackCitiesCache = null;

  const regionSelect = document.getElementById('postingRegionSelect');
  const provinceContainer = document.getElementById('postingProvinceContainer');
  const cityContainer = document.getElementById('postingCityContainer');
  const regionWrap = document.getElementById('postingRegionWrap');
  const provinceWrap = document.getElementById('postingProvinceWrap');
  const cityWrap = document.getElementById('postingCityWrap');
  const provinceSelect = document.getElementById('postingProvinceSelect');
  const citySelect = document.getElementById('postingCitySelect');
  const regionNameInput = document.getElementById('postingRegionName');
  const provinceNameInput = document.getElementById('postingProvinceName');
  const cityNameInput = document.getElementById('postingCityName');
  const locationValueInput = document.getElementById('postingLocationValue');
  const locationPreviewInput = document.getElementById('postingLocationPreview');
  const locationHelp = document.getElementById('postingLocationHelp');

  function normalizeApiRows(payload) {
    if (Array.isArray(payload)) {
      return payload;
    }

    if (payload && typeof payload === 'object') {
      return [payload];
    }

    return [];
  }

  function normalizeNumericCode(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function normalizeProvinceCode(value) {
    const digits = normalizeNumericCode(value);
    if (digits.length >= 9) {
      return digits.slice(0, 9);
    }

    return digits;
  }

  function getRowPsgcId(row) {
    return String(
      (row && (
        row.psgc_id ||
        row.psgc10DigitCode ||
        row.psgc10digitcode ||
        row.code
      )) || ''
    ).trim();
  }

  function getRowCorrespondenceCode(row) {
    return String((row && (row.correspondence_code || row.correspondenceCode || '')) || '').trim();
  }

  async function fetchPsgcRows(url) {
    try {
      const response = await fetch(url, {
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        return [];
      }

      const raw = (await response.text()).trim();
      if (!raw) {
        return [];
      }

      return normalizeApiRows(JSON.parse(raw));
    } catch (error) {
      return [];
    }
  }

  function clearSelect(select, placeholder, disabled) {
    if (!select) {
      return;
    }

    select.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    select.disabled = !!disabled;
    select.value = '';
    syncLocationSelectState(select);
  }

  function syncLocationSelectState(select) {
    if (!select) {
      return;
    }

    const wrapById = {
      postingRegionSelect: regionWrap,
      postingProvinceSelect: provinceWrap,
      postingCitySelect: cityWrap,
    };

    const targetWrap = wrapById[select.id] || null;
    if (!targetWrap) {
      return;
    }

    targetWrap.classList.toggle('is-disabled', !!select.disabled);
  }

  function getSelectedOptionLabel(select) {
    if (!select) {
      return '';
    }

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      return '';
    }

    return String(selectedOption.textContent || '').trim();
  }

  function findSelectedValue(rows, selectedId, selectedName) {
    const preferredId = String(selectedId || '').trim();
    if (preferredId !== '' && rows.some(row => getRowPsgcId(row) === preferredId)) {
      return preferredId;
    }

    const normalizedName = String(selectedName || '').trim().toLowerCase();
    if (normalizedName === '') {
      return '';
    }

    const match = rows.find(row => String(row.name || '').trim().toLowerCase() === normalizedName);
    return match ? String(match.psgc_id || '') : '';
  }

  function populateSelect(select, rows, placeholder, selectedId, selectedName) {
    clearSelect(select, placeholder, false);

    rows.forEach(row => {
      const id = getRowPsgcId(row);
      const name = String(row.name || '').trim();
      const correspondenceCode = getRowCorrespondenceCode(row);

      if (id === '' || name === '') {
        return;
      }

      const option = document.createElement('option');
      option.value = id;
      option.textContent = name;
      if (correspondenceCode !== '') {
        option.setAttribute('data-correspondence-code', correspondenceCode);
      }
      select.appendChild(option);
    });

    const selectedValue = findSelectedValue(rows, selectedId, selectedName);
    if (selectedValue !== '') {
      select.value = selectedValue;
    }

    if (select.options.length <= 1) {
      select.disabled = true;
    }

    syncLocationSelectState(select);
  }

  async function fetchFallbackCitiesMunicipalities(parentProvinceId, regionId) {
    const parentDigits = normalizeNumericCode(parentProvinceId);
    const parentCode = normalizeProvinceCode(parentProvinceId);
    const regionCode = normalizeProvinceCode(regionId);

    if (parentDigits === '' && parentCode === '' && regionCode === '') {
      return [];
    }

    const isRegionParent = parentDigits.length === 10 && parentDigits.slice(2, 4) === '00';
    const provincePrefix = (parentDigits.length === 10 && parentDigits.slice(2, 4) !== '00')
      ? parentDigits.slice(0, 4)
      : '';

    if (fallbackCitiesCache === null) {
      const fallbackRows = await fetchPsgcRows(fallbackPsgcApi.citiesMunicipalities);
      fallbackCitiesCache = Array.isArray(fallbackRows) ? fallbackRows : [];
    }

    if (!Array.isArray(fallbackCitiesCache) || fallbackCitiesCache.length === 0) {
      return [];
    }

    return fallbackCitiesCache
      .filter(row => {
        const rowProvinceCode = normalizeProvinceCode(
          (row && (row.provinceCode || row.province_code || ''))
        );
        const rowRegionCode = normalizeProvinceCode(
          (row && (row.regionCode || row.region_code || ''))
        );
        const rowPsgc10 = normalizeNumericCode(
          (row && (row.psgc10DigitCode || row.psgc10digitcode || row.psgc_id || ''))
        );

        if (!isRegionParent && parentCode !== '' && rowProvinceCode !== '' && rowProvinceCode === parentCode) {
          return true;
        }

        if (provincePrefix !== '' && rowPsgc10 !== '' && rowPsgc10.slice(0, 4) === provincePrefix) {
          return true;
        }

        if (isRegionParent && parentCode !== '' && rowRegionCode !== '' && rowRegionCode === parentCode) {
          return true;
        }

        if (regionCode !== '' && rowRegionCode !== '' && rowRegionCode === regionCode) {
          return true;
        }

        return false;
      })
      .map(row => ({
        psgc_id: getRowPsgcId(row),
        name: String((row && row.name) || '').trim(),
      }))
      .filter(row => row.psgc_id !== '' && row.name !== '')
      .sort((a, b) => a.name.localeCompare(b.name, 'en', { sensitivity: 'base' }));
  }

  async function fetchFallbackProvinces(regionId) {
    const regionCode = normalizeProvinceCode(regionId);
    if (regionCode === '') {
      return [];
    }

    if (fallbackProvincesCache === null) {
      const fallbackRows = await fetchPsgcRows(fallbackPsgcApi.provinces);
      fallbackProvincesCache = Array.isArray(fallbackRows) ? fallbackRows : [];
    }

    if (!Array.isArray(fallbackProvincesCache) || fallbackProvincesCache.length === 0) {
      return [];
    }

    return fallbackProvincesCache
      .filter(row => {
        const rowRegionCode = normalizeProvinceCode(
          (row && (row.regionCode || row.region_code || ''))
        );
        return rowRegionCode !== '' && rowRegionCode === regionCode;
      })
      .map(row => ({
        psgc_id: getRowPsgcId(row),
        name: String((row && row.name) || '').trim(),
      }))
      .filter(row => row.psgc_id !== '' && row.name !== '')
      .sort((a, b) => a.name.localeCompare(b.name, 'en', { sensitivity: 'base' }));
  }

  function setLocationHelp(message) {
    if (locationHelp) {
      const helperText = locationHelp.querySelector('span');
      if (helperText) {
        helperText.textContent = message;
      } else {
        locationHelp.textContent = message;
      }
    }
  }

  function syncLocationComposite() {
    if (!locationValueInput || !locationPreviewInput) {
      return;
    }

    const cityName = String(cityNameInput ? cityNameInput.value : '').trim();
    const provinceName = String(provinceNameInput ? provinceNameInput.value : '').trim();
    const regionName = String(regionNameInput ? regionNameInput.value : '').trim();

    const parts = [cityName, provinceName, regionName].filter(Boolean);
    const composed = parts.join(', ');

    if (composed !== '') {
      locationValueInput.value = composed;
      locationPreviewInput.value = composed;
    }
  }

  async function loadCities(parentId, selectedCityId, selectedCityName, regionId) {
    clearSelect(citySelect, '-- Select Province or Region --', true);
    if (cityNameInput) {
      cityNameInput.value = '';
    }

    const parent = String(parentId || '').trim();
    if (parent === '') {
      syncLocationComposite();
      return;
    }

    let rows = await fetchPsgcRows(psgcApi.municipalCity + '?id=' + encodeURIComponent(parent));
    let usedFallbackCities = false;

    if (!Array.isArray(rows)) {
      rows = [];
    }

    if (rows.length <= 2) {
      const fallbackRows = await fetchFallbackCitiesMunicipalities(parent, regionId || regionSelect.value || '');
      if (Array.isArray(fallbackRows) && fallbackRows.length > rows.length) {
        rows = fallbackRows;
        usedFallbackCities = true;
      }
    }

    populateSelect(citySelect, rows, '-- Select City/Municipality --', selectedCityId, selectedCityName);

    if (rows.length === 0) {
      clearSelect(citySelect, '-- No city/municipality data --', true);
      setLocationHelp('No city/municipality records found for the selected area.');
    } else if (usedFallbackCities) {
      setLocationHelp('City/Municipality list loaded with PSGC API fallback data source.');
    } else {
      setLocationHelp('Data source: PSGC API (Region, Province, City/Municipality).');
    }

    if (cityContainer) {
      cityContainer.style.display = '';
    }

    if (cityNameInput) {
      cityNameInput.value = getSelectedOptionLabel(citySelect);
    }

    syncLocationComposite();
  }

  async function loadProvinces(regionId, selectedProvinceId, selectedProvinceName, selectedCityId, selectedCityName) {
    clearSelect(provinceSelect, '-- Select Region First --', true);
    clearSelect(citySelect, '-- Select Province First --', true);

    if (provinceNameInput) {
      provinceNameInput.value = '';
    }
    if (cityNameInput) {
      cityNameInput.value = '';
    }

    const region = String(regionId || '').trim();
    if (region === '') {
      setLocationHelp('Select region first, then province and city/municipality will load.');
      syncLocationComposite();
      return;
    }

    let rows = await fetchPsgcRows(psgcApi.province + '?id=' + encodeURIComponent(region));
    if (!Array.isArray(rows)) {
      rows = [];
    }

    if (rows.length <= 2) {
      const fallbackRows = await fetchFallbackProvinces(region);
      if (Array.isArray(fallbackRows) && fallbackRows.length > rows.length) {
        rows = fallbackRows;
        setLocationHelp('Province list loaded with PSGC API fallback data source.');
      }
    }

    populateSelect(provinceSelect, rows, '-- Select Province --', selectedProvinceId, selectedProvinceName);
    if (provinceContainer) {
      provinceContainer.style.display = '';
    }

    if (provinceNameInput) {
      provinceNameInput.value = getSelectedOptionLabel(provinceSelect);
    }

    const selectedProvinceOption = provinceSelect && provinceSelect.selectedIndex >= 0
      ? provinceSelect.options[provinceSelect.selectedIndex]
      : null;
    const selectedProvinceCorrespondenceCode = selectedProvinceOption
      ? String(selectedProvinceOption.getAttribute('data-correspondence-code') || '').trim()
      : '';

    const parentIdForCities = String(selectedProvinceCorrespondenceCode || (provinceSelect && provinceSelect.value) || region).trim();
    await loadCities(parentIdForCities, selectedCityId, selectedCityName, region);
    syncLocationComposite();
  }

  async function initializePsgcDropdowns() {
    if (!regionSelect || !provinceSelect || !citySelect) {
      return;
    }

    const oldRegionId = regionSelect.getAttribute('data-old-value') || '';
    const oldProvinceId = provinceSelect.getAttribute('data-old-value') || '';
    const oldCityId = citySelect.getAttribute('data-old-value') || '';
    const oldRegionName = regionNameInput ? regionNameInput.value : '';
    const oldProvinceName = provinceNameInput ? provinceNameInput.value : '';
    const oldCityName = cityNameInput ? cityNameInput.value : '';

    const regions = await fetchPsgcRows(psgcApi.region);
    populateSelect(regionSelect, regions, '-- Select Region --', oldRegionId, oldRegionName);

    if (regionNameInput) {
      regionNameInput.value = getSelectedOptionLabel(regionSelect) || oldRegionName;
    }

    await loadProvinces(regionSelect.value, oldProvinceId, oldProvinceName, oldCityId, oldCityName);
    syncLocationComposite();
    syncLocationSelectState(regionSelect);
    syncLocationSelectState(provinceSelect);
    syncLocationSelectState(citySelect);
  }

  if (regionSelect && provinceSelect && citySelect) {
    regionSelect.addEventListener('change', async function () {
      if (regionNameInput) {
        regionNameInput.value = getSelectedOptionLabel(regionSelect);
      }

      await loadProvinces(regionSelect.value, '', '', '', '');
      syncLocationComposite();
    });

    provinceSelect.addEventListener('change', async function () {
      if (provinceNameInput) {
        provinceNameInput.value = getSelectedOptionLabel(provinceSelect);
      }

      const selectedProvinceOption = provinceSelect.options[provinceSelect.selectedIndex] || null;
      const selectedProvinceCorrespondenceCode = selectedProvinceOption
        ? String(selectedProvinceOption.getAttribute('data-correspondence-code') || '').trim()
        : '';

      const parentIdForCities = String(selectedProvinceCorrespondenceCode || provinceSelect.value || regionSelect.value || '').trim();
      await loadCities(parentIdForCities, '', '', regionSelect.value || '');
      syncLocationComposite();
    });

    citySelect.addEventListener('change', function () {
      if (cityNameInput) {
        cityNameInput.value = getSelectedOptionLabel(citySelect);
      }
      syncLocationComposite();
    });

    initializePsgcDropdowns();
  }

  const internshipForm = document.getElementById('internshipForm');
  if (internshipForm) {
    internshipForm.addEventListener('submit', function (event) {
      // Controls are associated via form="internshipForm" and may live outside
      // the hidden form tag, so read values through FormData.
      const formData = new FormData(internshipForm);
      const selectedSkills = formData.getAll('skills[]');
      if (selectedSkills.length === 0) {
        event.preventDefault();
        alert('Please select at least one required skill before creating the internship.');
      }
    });
  }

  // Skill search filter
  function filterSkills() {
    const q = document.getElementById('skillSearch').value.toLowerCase();
    document.querySelectorAll('.skill-entry').forEach(row => {
      const name = row.getAttribute('data-name') || '';
      row.style.display = name.includes(q) ? '' : 'none';
    });
  }

  function setPostingCardActiveState(button) {
    document.querySelectorAll('[data-posting-card="1"]').forEach(card => {
      card.dataset.active = '0';
      card.setAttribute('aria-pressed', 'false');
    });

    document.querySelectorAll('[data-posting-card-wrap="1"]').forEach(wrap => {
      wrap.style.border = '1px solid var(--border,#e8e0e0)';
      wrap.style.background = '#fff';
    });

    button.dataset.active = '1';
    button.setAttribute('aria-pressed', 'true');
    const currentWrap = button.closest('[data-posting-card-wrap="1"]');
    if (currentWrap) {
      currentWrap.style.border = '2px solid var(--red,#8b0000)';
      currentWrap.style.background = 'rgba(139,0,0,.04)';
    }
  }

  function selectPostingCard(button) {
    if (!button) return;
    setPostingCardActiveState(button);

    const internshipId = parseInt(button.getAttribute('data-id') || '0', 10);
    const title = button.getAttribute('data-title') || 'Untitled Internship';
    const description = button.getAttribute('data-description') || 'No description provided.';
    const status = button.getAttribute('data-status') || 'pending';
    const posted = button.getAttribute('data-posted') || '';
    const location = button.getAttribute('data-location') || 'N/A';
    const durationHours = parseInt(button.getAttribute('data-duration-hours') || '0', 10);
    const applicants = button.getAttribute('data-applicants') || '0';
    const workSetup = button.getAttribute('data-work-setup') || 'N/A';
    const allowanceRaw = parseFloat(button.getAttribute('data-allowance') || '0');
    const slots = button.getAttribute('data-slots') || '0';

    const durationText = durationHours > 0 ? (durationHours + ' hours') : 'N/A';

    const statusLabel = status.replace(/[_-]+/g, ' ').trim();
    const prettyStatus = statusLabel ? statusLabel.replace(/\b\w/g, c => c.toUpperCase()) : 'N/A';

    const postedDate = new Date(posted);
    const postedText = isNaN(postedDate.getTime()) ? 'Posted recently' : 'Posted ' + postedDate.toLocaleString();

    document.getElementById('detailTitle').textContent = title;
    document.getElementById('detailStatus').textContent = prettyStatus;
    document.getElementById('detailStatus').className = 'status-pill ' + (
      ['accepted','hired','open','verified','approved','scheduled'].includes(status.toLowerCase()) ? 'status-accepted' :
      ['rejected','declined','closed','cancelled','canceled'].includes(status.toLowerCase()) ? 'status-rejected' :
      ['interview','interviewing','for interview'].includes(status.toLowerCase()) ? 'status-interview' :
      ['shortlisted','reviewed'].includes(status.toLowerCase()) ? 'status-shortlisted' :
      'status-pending'
    );
    document.getElementById('detailPosted').textContent = postedText;
    document.getElementById('detailLocation').textContent = location;
    document.getElementById('detailDuration').textContent = durationText;
    document.getElementById('detailWorkSetup').textContent = workSetup;
    document.getElementById('detailApplicants').textContent = applicants;
    document.getElementById('detailAllowance').textContent = '₱' + allowanceRaw.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('detailSlots').textContent = slots;
    document.getElementById('detailDescription').textContent = description;
    const applicantsHref = internshipId > 0
      ? ('/SkillHive/layout.php?page=employer/candidates&position=' + encodeURIComponent(String(internshipId)))
      : '/SkillHive/layout.php?page=employer/candidates';
    document.getElementById('detailApplicantsLink').setAttribute('href', applicantsHref);
    const detailEditPostingId = document.getElementById('detailEditPostingId');
    if (detailEditPostingId) {
      detailEditPostingId.value = button.getAttribute('data-id') || '0';
    }
    const detailEditStatus = document.getElementById('detailEditStatus');
    if (detailEditStatus) {
      detailEditStatus.value = status.toLowerCase() === 'closed' ? 'Closed' : 'Open';
    }
    const detailDeletePostingId = document.getElementById('detailDeletePostingId');
    if (detailDeletePostingId) {
      detailDeletePostingId.value = button.getAttribute('data-id') || '0';
    }
  }

  const initiallyActiveCard = document.querySelector('[data-posting-card="1"][data-active="1"]') || document.querySelector('[data-posting-card="1"]');
  if (initiallyActiveCard) {
    selectPostingCard(initiallyActiveCard);
  }
</script>


