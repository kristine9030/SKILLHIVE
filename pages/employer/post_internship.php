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
  if (isset($_POST['edit_title'])) {
    // Full edit posting (from modal)
    $editPostingId = (int)($_POST['edit_posting_id'] ?? 0);
    $editTitle = (string)($_POST['edit_title'] ?? '');
    $editDescription = (string)($_POST['edit_description'] ?? '');
    $editWorkSetup = (string)($_POST['edit_work_setup'] ?? 'On-site');
    $editSlots = max(1, (int)($_POST['edit_slots_available'] ?? 1));
    $editDuration = max(1, min(52, (int)($_POST['edit_duration_weeks'] ?? 4)));
    $editAllowance = max(0, (float)($_POST['edit_allowance'] ?? 0));

    if (empty($editTitle) || empty($editDescription)) {
      $errors[] = 'Title and description are required.';
    } else {
      try {
        $stmt = $pdo->prepare(
          'UPDATE internship
           SET title = :title, description = :description, work_setup = :work_setup, 
               slots_available = :slots, duration_weeks = :duration, allowance = :allowance
           WHERE internship_id = :id AND employer_id = :employer_id'
        );
        $stmt->execute([
          ':title' => $editTitle,
          ':description' => $editDescription,
          ':work_setup' => $editWorkSetup,
          ':slots' => $editSlots,
          ':duration' => $editDuration,
          ':allowance' => $editAllowance,
          ':id' => $editPostingId,
          ':employer_id' => $employerId,
        ]);

        if ($stmt->rowCount() > 0) {
          $_SESSION['status'] = 'Posting updated successfully!';
          $_SESSION['status_type'] = 'success';
        } else {
          $errors[] = 'Posting not found or no changes made.';
          $_SESSION['status_type'] = 'error';
        }
      } catch (Throwable $e) {
        $errors[] = 'Unable to update posting. Please try again.';
        $_SESSION['status_type'] = 'error';
      }
    }

    if (empty($errors)) {
      header('Location: /SkillHive/layout.php?page=employer/post_internship&focus_posting=' . $editPostingId . '#my-postings');
      exit;
    }
  } elseif (isset($_POST['edit_posting_id']) && !isset($_POST['edit_title'])) {
    // Quick status edit
    $editPostingId = (int)($_POST['edit_posting_id'] ?? 0);
    $editStatus = (string)($_POST['edit_status'] ?? '');
    $editPage = max(1, (int)($_POST['postings_page'] ?? $postingsPage));

    try {
      $editResult = updateEmployerInternshipPostingStatus($pdo, (int)$employerId, $editPostingId, $editStatus);
      if (!empty($editResult['success'])) {
        $_SESSION['status'] = 'Posting status updated successfully.';
        $_SESSION['status_type'] = 'success';
      } else {
        $errors[] = (string)($editResult['error'] ?? 'Unable to update posting status.');
        $_SESSION['status_type'] = 'error';
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to update posting right now. Please try again.';
      $_SESSION['status_type'] = 'error';
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
        $_SESSION['status_type'] = 'success';
      } else {
        $errors[] = (string)($deleteResult['error'] ?? 'Unable to delete posting.');
        $_SESSION['status_type'] = 'error';
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to delete posting right now. Please try again.';
      $_SESSION['status_type'] = 'error';
    }

    if (empty($errors)) {
      header('Location: /SkillHive/layout.php?page=employer/post_internship&postings_page=' . $deletePage . '#my-postings');
      exit;
    }
  } elseif (isset($_POST['extend_posting_id'])) {
    $extendPostingId = (int)($_POST['extend_posting_id'] ?? 0);
    $extendPage = max(1, (int)($_POST['postings_page'] ?? $postingsPage));

    try {
      $extendResult = extendInternshipPosting($pdo, (int)$employerId, $extendPostingId);
      if (!empty($extendResult['success'])) {
        $_SESSION['status'] = 'Posting extended successfully! It will be visible for another ' . (int)($_POST['duration_weeks'] ?? 4) . ' weeks.';
        $_SESSION['status_type'] = 'success';
      } else {
        $errors[] = (string)($extendResult['error'] ?? 'Unable to extend posting.');
        $_SESSION['status_type'] = 'error';
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = 'Unable to extend posting right now. Please try again.';
      $_SESSION['status_type'] = 'error';
    }

    if (empty($errors)) {
      header('Location: /SkillHive/layout.php?page=employer/post_internship&postings_page=1#expired-postings');
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
              $_SESSION['status_type'] = 'success';
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
  
  // Load expired postings that can be extended
  $expiredPostings = getExpiredInternshipPostings($pdo, (int)$employerId, 20, 0);
} catch (Throwable $e) {
  $myPostings = [];
  $expiredPostings = [];
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
.posting-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 0;
}

.posting-layout {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.posting-banner {
  background:
    radial-gradient(circle at 95% 50%, rgba(6, 78, 59, 0.65) 0%, transparent 70%),
    radial-gradient(circle at 85% 50%, rgba(15, 118, 110, 0.55) 0%, transparent 60%),
    linear-gradient(90deg, #ffffff 0%, #f0fdfa 25%, #134e4a 60%, #0f766e 85%, #0d5f58 100%);
  border-radius: 16px;
  padding: 20px 28px;
  margin: 0 4px 16px 4px;
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

.posting-banner::before {
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

.posting-banner::after {
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

.posting-banner.collapsed {
  padding: 8px 16px;
  min-height: 0;
}

.posting-banner.collapsed .pb-main {
  display: none;
}

.posting-banner.collapsed .pb-toggle {
  display: none;
}

.pb-expand-hint {
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

.pb-expand-hint:hover {
  opacity: 1;
}

.posting-banner.collapsed .pb-expand-hint {
  display: block;
}

.posting-banner:not(.collapsed) .pb-expand-hint {
  display: none !important;
}

.pb-info {
  flex: 1;
  border-left: 1.5px solid rgba(255, 255, 255, 0.25);
  padding-left: 16px;
}

.pb-date {
  font-size: 13px;
  font-weight: 100;
  color: #9ca3af;
  margin-bottom: 2px;
}

.pb-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 2px;
  text-transform: capitalize;
  display: inline;
}

.pb-desc {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
  max-width: 450px;
}

/* Posting cards */
.postings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.posting-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  transition: all .2s ease;
  margin-bottom: 12px;
}

.posting-card:hover {
  border-color: #12b3ac;
  box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
}

.posting-card-top {
  margin-bottom: 12px;
}

.posting-card-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.posting-card-title {
  font-weight: 600;
  font-size: .95rem;
  color: #111;
}

.posting-card-badge {
  font-size: .7rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
}

.badge-active { background: #dcfce7; color: #16a34a; }
.badge-pending { background: #fef3c7; color: #d97706; }
.badge-closed { background: #fee2e2; color: #dc2626; }
.badge-draft { background: #f3f4f6; color: #6b7280; }

.posting-card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
}

.posting-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: .8rem;
  color: #6b7280;
}

.posting-meta i {
  font-size: .7rem;
  color: #12b3ac;
}

.posting-card-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid #f3f4f6;
  flex-wrap: wrap;
}

.posting-card-time {
  font-size: .75rem;
  color: #9ca3af;
}

.posting-card-actions {
  display: flex;
  gap: 8px;
}

.btn-outline {
  background: #555;
  border: 1.5px solid #555;
  color: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: .8rem;
  text-decoration: none;
  transition: all .15s ease;
}

.btn-outline:hover {
  background: #777;
  border-color: #777;
  color: #fff;
}

/* Error alert */
.error-alert {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(255, 107, 107, 0.04));
  border: 2px solid rgba(239, 68, 68, 0.15);
  color: #c41c3b;
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 13px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 48px 32px;
  border: 2px dashed #e5e7eb;
  border-radius: 12px;
  background: #f9fafb;
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
  color: #6b7280;
  margin-bottom: 8px;
}

.empty-state-text {
  font-size: 13px;
  color: #9ca3af;
  margin: 0;
}

/* Modal */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

.modal-overlay.active {
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-dialog {
  background: #fff;
  border-radius: 16px;
  max-width: 1000px;
  width: 92vw;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 28px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.modal-body {
  padding: 28px;
  overflow-y: auto;
  flex: 1;
}

.modal-body::-webkit-scrollbar {
  width: 6px;
}

.modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px 28px;
  border-top: 1px solid #e5e7eb;
  justify-content: flex-end;
  flex-shrink: 0;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 28px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #111;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #9ca3af;
}

.modal-body {
  padding: 28px;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px 28px;
  border-top: 1px solid #e5e7eb;
  justify-content: flex-end;
}

.modal-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
}

.modal-btn-primary { background: #000; color: #fff; }
.modal-btn-secondary { background: #f3f4f6; color: #374151; }
.modal-btn-success { background: #000; color: #fff; }

/* Form */
.form-section { margin-bottom: 24px; }

.form-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 6px;
  color: #111;
}

.form-section-subtitle {
  margin: 0 0 20px;
  color: #6b7280;
  font-size: 13px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-weight: 600;
  font-size: 14px;
  color: #374151;
}

.form-label-required { color: #ef4444; }

.form-control {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  background: #fff;
}

.form-control:focus {
  outline: none;
  border-color: #12b3ac;
  box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.1);
}

.form-hint {
  font-size: 12px;
  color: #6b7280;
}

/* Wizard */
.wizard-steps {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  padding: 20px 28px 16px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.step-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  opacity: 0.5;
}

.step-indicator.active { opacity: 1; }
.step-indicator.completed { opacity: 0.7; }

.step-number {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: #6b7280;
}

.step-indicator.active .step-number {
  background: #12b3ac;
  border-color: #12b3ac;
  color: #fff;
}

.step-indicator.completed .step-number {
  background: #10B981;
  border-color: #10B981;
  color: #fff;
  font-size: 0;
}

.step-indicator.completed .step-number::after { content: '✓'; font-size: 14px; }

.step-label {
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
}

.step-indicator.active .step-label {
  color: #12b3ac;
  font-weight: 600;
}

.wizard-step { display: none; }
.wizard-step.active { display: block; }

/* Skills */
.skills-container {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  max-height: 400px;
  display: flex;
  flex-direction: column;
}

.skills-header {
  background: #111;
  color: #fff;
  padding: 12px 14px;
  font-size: 12px;
  font-weight: 700;
  display: grid;
  grid-template-columns: 50px 1fr 150px 100px;
  gap: 12px;
  align-items: center;
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
  border-bottom: 1px solid #e5e7eb;
}

.skills-row:hover { background: #f9fafb; }

.skills-row select {
  padding: 6px 10px;
  font-size: 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 6px;
}

.skills-info {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  padding: 12px;
  background: #f0f9f8;
  border-radius: 8px;
  font-size: 13px;
  color: #6b7280;
}

/* Review */
.review-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.review-group {
  padding: 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.review-group label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.review-value {
  font-size: 14px;
  font-weight: 500;
  color: #111;
}

/* Location */
.location-selector {
  border: 1px solid #e5e7eb;
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
  border: 1.5px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  padding: 10px 14px;
}

.location-select-row.disabled {
  background: #f9fafb;
  opacity: 0.6;
}

.location-label {
  min-width: 70px;
  font-size: 11px;
  font-weight: 700;
  color: #6b7280;
}

.location-select-row select {
  border: none;
  background: transparent;
  padding: 8px 4px;
  font-size: 14px;
  flex: 1;
}

.location-preview {
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: #f0f9f8;
  font-size: 14px;
}

/* Currency */
.currency-input-wrapper {
  display: flex;
  align-items: center;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  padding: 0 14px;
}

.currency-input-wrapper:focus-within {
  border-color: #12b3ac;
  box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.1);
}

.currency-symbol {
  color: #6b7280;
  font-weight: 600;
  margin-right: 8px;
}

.currency-input-wrapper input {
  border: none;
  padding: 12px 8px;
  font-size: 14px;
  flex: 1;
}

.currency-input-wrapper input:focus { outline: none; }

/* Pagination */
.pagination-wrapper {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 20px;
}

.pagination-btn {
  padding: 8px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  color: #374151;
}

.pagination-btn:hover {
  background: #111;
  color: #fff;
  border-color: #111;
}

.pagination-btn-active {
  background: #12b3ac;
  color: #fff;
  border-color: #12b3ac;
  pointer-events: none;
}

.view-toggle {
  display: flex;
  gap: 4px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 4px;
}

.view-toggle-btn {
  padding: 6px 10px;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: 6px;
  color: #6b7280;
  font-size: 14px;
}

.view-toggle-btn.active {
  background: #111;
  color: #fff;
}

/* Section header */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
  margin-bottom: 20px;
}

.section-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #111;
}

.section-header p {
  margin: 0;
  color: #6b7280;
  font-size: 13px;
}

@media (max-width: 768px) {
  .wizard-steps { grid-template-columns: repeat(5, 1fr); }
  .step-label { display: none; }
  .form-row { grid-template-columns: 1fr; }
  .postings-grid { grid-template-columns: 1fr; }
  .skills-header, .skills-row { grid-template-columns: 40px 1fr 120px 80px; }
  .posting-banner { flex-direction: column; text-align: center; }
}
</style>

<div class="posting-page">
  <div class="posting-banner">
    <div class="pb-main">
      <div class="pb-info">
        <div class="pb-date"><?php echo date('l, jS F'); ?></div>
        <div class="pb-title">Good afternoon!</div>
        <div class="pb-desc">Create and manage internship postings to attract qualified student candidates.</div>
      </div>
    </div>
    <button type="button" class="pb-toggle" onclick="togglePostingBanner()" title="Hide banner">
      <i class="fas fa-chevron-up"></i>
    </button>
    <div class="pb-expand-hint" onclick="togglePostingBanner()">
      <i class="fas fa-chevron-down"></i> Show banner
    </div>
  </div>

  <!-- Errors -->
  <?php if (!empty($errors)): ?>
  <div class="error-alert">
    <i class="fa-solid fa-circle-exclamation"></i>
    <div style="flex: 1;">
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?php echo e($err); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <div class="posting-layout">
    <!-- Section Header -->
    <div class="section-header" id="my-postings">
      <div>
        <h2>My Internship Postings</h2>
        <p>Manage and track your active internship listings</p>
      </div>
      <div style="display: flex; align-items: center; gap: 12px;">
        <div class="view-toggle">
          <button type="button" class="view-toggle-btn active" data-view="grid" onclick="switchPostingView('grid')" title="Grid view">
            <i class="fas fa-th"></i>
          </button>
          <button type="button" class="view-toggle-btn" data-view="list" onclick="switchPostingView('list')" title="List view">
            <i class="fas fa-list-ul"></i>
          </button>
        </div>
        <button type="button" class="btn-outline" onclick="openPostingModal()" style="background: #000; color: #fff; border-color: #000;">
          <i class="fa-solid fa-plus"></i> Create New Posting
        </button>
      </div>
    </div>

  <?php if (!empty($myPostings)): ?>
  <div class="postings-grid">
    <?php foreach ($myPostings as $posting): ?>
      <?php
      $postingId = (int)($posting['internship_id'] ?? 0);
      $title = (string)($posting['title'] ?? 'Untitled Internship');
      $status = (string)($posting['status'] ?? 'pending');
      $statusClass = dashboard_status_class($status);
      $location = (string)($posting['location'] ?? 'N/A');
      $applicants = (int)($posting['applicants_count'] ?? 0);
      $slots = (int)($posting['slots_available'] ?? 0);
      $allowance = (float)($posting['allowance'] ?? 0);
      $description = (string)($posting['description'] ?? 'No description provided.');
      $postedAt = (string)($posting['posted_at'] ?? '');
      $workSetup = (string)($posting['work_setup'] ?? 'N/A');
      $durationWeeks = (int)($posting['duration_weeks'] ?? 1);
      ?>
      <div class="posting-card">
        <div class="posting-card-top">
          <div class="posting-card-title-row">
            <span class="posting-card-title"><?php echo e($title); ?></span>
            <span class="posting-card-badge <?php echo $statusClass; ?>"><?php echo e(dashboard_status_label($status)); ?></span>
          </div>
          <div class="posting-card-meta">
            <span class="posting-meta"><i class="fas fa-map-marker-alt"></i><?php echo e($location); ?></span>
            <span class="posting-meta"><i class="fas fa-users"></i><?php echo $applicants; ?> applicant<?php echo $applicants !== 1 ? 's' : ''; ?></span>
            <span class="posting-meta"><i class="fas fa-chair"></i><?php echo $slots; ?> slot<?php echo $slots !== 1 ? 's' : ''; ?></span>
          </div>
        </div>
        <div class="posting-card-bottom">
          <span class="posting-card-time"><i class="fas fa-clock"></i> <?php echo e(dashboard_time_ago($postedAt)); ?></span>
          <div class="posting-card-actions">
            <a href="/SkillHive/layout.php?page=employer/candidates&position=<?php echo $postingId; ?>" class="btn-outline">View Apps</a>
            <button type="button" class="btn-outline" onclick="openEditModal(<?php echo $postingId; ?>, '<?php echo addslashes($title); ?>', '<?php echo addslashes($description); ?>', '<?php echo $workSetup; ?>', <?php echo $slots; ?>, <?php echo $durationWeeks; ?>, <?php echo $allowance; ?>)">Edit</button>
            <form method="post" action="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>" style="margin:0;">
              <input type="hidden" name="edit_posting_id" value="<?php echo $postingId; ?>">
              <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
              <select name="edit_status" style="padding: 6px 10px; border: 1.5px solid #e5e7eb; border-radius: 6px; font-size: 12px; background: #fff; cursor: pointer;">
                <option value="Open" <?php echo (strtolower($status) === 'closed') ? '' : 'selected'; ?>>Open</option>
                <option value="Closed" <?php echo (strtolower($status) === 'closed') ? 'selected' : ''; ?>>Closed</option>
              </select>
            </form>
            <form method="post" action="/SkillHive/layout.php?page=employer/post_internship&postings_page=<?php echo $postingsPage; ?>#my-postings" onsubmit="return confirm('Delete this posting?');" style="margin:0;">
              <input type="hidden" name="delete_posting_id" value="<?php echo $postingId; ?>">
              <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
              <button type="submit" style="background: transparent; color: #ef4444; border: 1.5px solid #ef4444; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
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

  <!-- Expired Postings Section -->
  <?php if (!empty($expiredPostings)): ?>
<div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;" id="expired-postings">
  <div class="section-header">
    <div>
      <h2>Expired Postings</h2>
      <p>These postings have passed their duration. Click "Extend" to re-open them.</p>
    </div>
    <span style="background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;">
      <?php echo count($expiredPostings); ?> expired
    </span>
  </div>

  <div class="postings-grid">
    <?php foreach ($expiredPostings as $posting): ?>
      <?php
        $postingId = (int)($posting['internship_id'] ?? 0);
        $title = htmlspecialchars((string)($posting['title'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars((string)($posting['location'] ?? ''), ENT_QUOTES, 'UTF-8');
        $durationWeeks = max(1, (int)($posting['duration_weeks'] ?? 1));
        $allowance = (float)($posting['allowance'] ?? 0);
        $applicantsCount = max(0, (int)($posting['applicants_count'] ?? 0));
        $slotsAvailable = max(0, (int)($posting['slots_available'] ?? 0));
        $expiresAt = $posting['expires_at'] ?? null;
      ?>
      <div class="posting-card" style="opacity: 0.7;">
        <div class="posting-card-top">
          <div class="posting-card-title-row">
            <span class="posting-card-title"><?php echo $title; ?></span>
            <span class="posting-card-badge badge-pending">Expired</span>
          </div>
          <div class="posting-card-meta">
            <span class="posting-meta"><i class="fas fa-map-marker-alt"></i><?php echo $location ?: 'Location not specified'; ?></span>
            <span class="posting-meta"><i class="fas fa-users"></i><?php echo $applicantsCount; ?> applicants</span>
          </div>
        </div>
        <div class="posting-card-bottom">
          <span class="posting-card-time"><i class="fas fa-clock"></i> Expired on <?php echo date('M d, Y', strtotime($expiresAt)); ?></span>
          <div class="posting-card-actions">
            <form method="post" action="/SkillHive/layout.php?page=employer/post_internship#expired-postings" style="margin:0;">
              <input type="hidden" name="extend_posting_id" value="<?php echo $postingId; ?>">
              <input type="hidden" name="duration_weeks" value="<?php echo $durationWeeks; ?>">
              <input type="hidden" name="postings_page" value="<?php echo $postingsPage; ?>">
              <button type="submit" class="btn-outline">
                <i class="fas fa-redo"></i> Extend
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
</div><!-- .posting-layout -->

  <!-- Create New Posting Modal -->
  <div class="modal-overlay" id="postingModal">
    <div class="modal-dialog">
      <!-- Modal Header -->
      <div class="modal-header">
        <h2>
          <span class="modal-header-icon">
            <i class="fa-solid fa-briefcase"></i>
          </span>
          Create New Internship Posting
        </h2>
        <button type="button" class="modal-close-btn" onclick="closePostingModal()" title="Close modal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- Step Indicator -->
      <div class="wizard-steps">
        <div class="step-indicator" data-step="1">
          <div class="step-number">1</div>
          <div class="step-label">Details</div>
        </div>
        <div class="step-indicator" data-step="2">
          <div class="step-number">2</div>
          <div class="step-label">Compensation</div>
        </div>
        <div class="step-indicator" data-step="3">
          <div class="step-number">3</div>
          <div class="step-label">Location</div>
        </div>
        <div class="step-indicator" data-step="4">
          <div class="step-number">4</div>
          <div class="step-label">Skills</div>
        </div>
        <div class="step-indicator" data-step="5">
          <div class="step-number">5</div>
          <div class="step-label">Review</div>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <form method="post" action="/SkillHive/layout.php?page=employer/post_internship" id="internshipForm">

          <!-- STEP 1: Internship Details -->
          <div class="wizard-step active" data-step="1">
            <div class="form-section">
              <h3 class="form-section-title">
                <i class="fa-solid fa-briefcase"></i>
                Position Details
              </h3>
              <p class="form-section-subtitle">Start by telling us about the internship position</p>

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

              <div class="form-group">
                <label class="form-label">
                  Job Description
                  <span class="form-label-required">*</span>
                </label>
                <textarea class="form-control" name="description" rows="6" placeholder="Describe the role, key responsibilities, and what the intern will learn. Be detailed and engaging!" required><?php echo oldVal($old, 'description'); ?></textarea>
                <span class="form-hint">Well-written descriptions attract more qualified applicants</span>
              </div>
            </div>
          </div>

          <!-- STEP 2: Duration & Compensation -->
          <div class="wizard-step" data-step="2">
            <div class="form-section">
              <h3 class="form-section-title">
                <i class="fa-solid fa-coins"></i>
                Duration & Compensation
              </h3>
              <p class="form-section-subtitle">Set the internship timeline and benefits</p>

              <div class="form-group">
                <label class="form-label">
                  Duration (hours)
                  <span class="form-label-required">*</span>
                </label>
                <input class="form-control" type="number" min="500" step="1" name="duration_hours" placeholder="e.g., 500" value="<?php echo oldVal($old, 'duration_hours', '500'); ?>" required>
                <span class="form-hint">Minimum 500 hours. Typically 40 hours/week = 10 weeks</span>
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
                <span class="form-hint">The monthly stipend offered to interns</span>
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
          </div>

          <!-- STEP 3: Work Location -->
          <div class="wizard-step" data-step="3">
            <div class="form-section">
              <h3 class="form-section-title">
                <i class="fa-solid fa-map-marker-alt"></i>
                Work Location
              </h3>
              <p class="form-section-subtitle">Where will the intern be working?</p>

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
          </div>

          <!-- STEP 4: Required Skills -->
          <div class="wizard-step" data-step="4">
            <div class="form-section">
              <h3 class="form-section-title">
                <i class="fa-solid fa-list-check"></i>
                Required Skills
              </h3>
              <p class="form-section-subtitle">What skills should candidates have?</p>

              <div class="form-group" style="max-width: 100%; margin-bottom: 20px;">
                <input class="form-control" type="text" id="skillSearch" placeholder="🔍  Search skills…" oninput="filterSkills()">
                <span class="form-hint">Type to filter the skills list below</span>
              </div>

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
                    <input type="checkbox" name="skills[]" value="<?php echo $sid; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                    <div style="font-weight: 500; color: var(--text-primary);"><?php echo e($skill['skill_name']); ?></div>
                    <select name="skill_level[<?php echo $sid; ?>]" class="form-control">
                      <?php foreach ($allowedLevels as $lvl): ?>
                        <?php $sel = (($_POST['skill_level'][$sid] ?? 'Beginner') === $lvl) ? 'selected' : ''; ?>
                        <option value="<?php echo e($lvl); ?>" <?php echo $sel; ?>><?php echo e($lvl); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label style="display: flex; align-items: center; justify-content: center; cursor: pointer;">
                      <input type="checkbox" name="skill_mandatory[<?php echo $sid; ?>]" value="1" <?php echo isset($_POST['skill_mandatory'][$sid]) ? 'checked' : ''; ?>>
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
          </div>

          <!-- STEP 5: Review & Submit -->
          <div class="wizard-step" data-step="5">
            <div class="form-section">
              <h3 class="form-section-title">
                <i class="fa-solid fa-check-circle"></i>
                Review Your Posting
              </h3>
              <p class="form-section-subtitle">Verify all details before posting</p>

              <div class="review-summary">
                <div class="review-group">
                  <label>Position Title</label>
                  <div class="review-value" id="reviewTitle">—</div>
                </div>
                <div class="review-group">
                  <label>Work Setup</label>
                  <div class="review-value" id="reviewWorkSetup">—</div>
                </div>
                <div class="review-group">
                  <label>Location</label>
                  <div class="review-value" id="reviewLocation">—</div>
                </div>
                <div class="review-group">
                  <label>Duration & Compensation</label>
                  <div class="review-value" id="reviewCompensation">—</div>
                </div>
                <div class="review-group">
                  <label>Required Skills</label>
                  <div class="review-value" id="reviewSkills">—</div>
                </div>
              </div>

              <div class="initial-status-section">
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
                <span class="form-hint">You can change this status anytime after posting</span>
              </div>
            </div>
          </div>

        </form>
      </div>

      <!-- Modal Footer with Navigation -->
      <div class="modal-footer">
        <div style="display: flex; gap: 12px; justify-content: space-between; width: 100%;">
          <button type="button" class="modal-btn modal-btn-secondary" id="prevBtn" onclick="previousStep()" style="display: none;">
            <i class="fa-solid fa-chevron-left"></i>
            Previous
          </button>
          <div style="flex: 1;"></div>
          <button type="button" class="modal-btn modal-btn-secondary" onclick="closePostingModal()">
            <i class="fa-solid fa-times"></i>
            Cancel
          </button>
          <button type="button" class="modal-btn modal-btn-primary" id="nextBtn" onclick="nextStep()">
            Next
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <button type="submit" form="internshipForm" class="modal-btn modal-btn-success" id="submitBtn" onclick="return validateStep(5)" style="display: none;">
            <i class="fa-solid fa-rocket"></i>
            Post Internship
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden form wrapper for form attribute reference -->
  <form id="internshipForm" method="post" action="/SkillHive/layout.php?page=employer/post_internship" style="display:none;"></form>

  <!-- Alert Notification System -->
  <div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; max-width: 400px;"></div>

  <!-- Edit Details Modal -->
  <div class="modal-overlay" id="editModal" style="display: none;">
    <div class="modal-dialog">
      <!-- Modal Header -->
      <div class="modal-header">
        <h2>
          <span class="modal-header-icon">
            <i class="fa-solid fa-edit"></i>
          </span>
          Edit Internship Posting
        </h2>
        <button type="button" class="modal-close-btn" onclick="closeEditModal()">
          <i class="fa-solid fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <form id="editInternshipForm" method="post" action="/SkillHive/layout.php?page=employer/post_internship">
          <input type="hidden" name="edit_posting_id" id="editPostingId" value="">
          
          <div class="form-section">
            <h3 class="form-section-title">
              <i class="fa-solid fa-briefcase"></i>
              Job Title & Description
            </h3>
            <p class="form-section-subtitle">Update the position details</p>

            <div class="form-group">
              <label class="form-label">
                Position Title
                <span class="form-label-required">*</span>
              </label>
              <input class="form-control" type="text" placeholder="e.g., Junior Web Developer" name="edit_title" id="editTitle" required>
            </div>

            <div class="form-group">
              <label class="form-label">
                Job Description
                <span class="form-label-required">*</span>
              </label>
              <textarea class="form-control" name="edit_description" id="editDescription" rows="5" placeholder="Describe the role, responsibilities, and requirements..." required></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">
                Work Setup
                <span class="form-label-required">*</span>
              </label>
              <select class="form-control" name="edit_work_setup" id="editWorkSetup" required>
                <option value="">Select work setup…</option>
                <option value="Remote">Remote</option>
                <option value="On-site">On-site</option>
                <option value="Hybrid">Hybrid</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">
                Slots Available
                <span class="form-label-required">*</span>
              </label>
              <input class="form-control" type="number" min="1" placeholder="e.g., 3" name="edit_slots_available" id="editSlotsAvailable" required>
            </div>

            <div class="form-group">
              <label class="form-label">
                Duration (Weeks)
                <span class="form-label-required">*</span>
              </label>
              <input class="form-control" type="number" min="1" max="52" placeholder="e.g., 4" name="edit_duration_weeks" id="editDurationWeeks" required>
            </div>

            <div class="form-group">
              <label class="form-label">
                Monthly Allowance
                <span class="form-label-required">*</span>
              </label>
              <input class="form-control" type="number" min="0" step="0.01" placeholder="e.g., 5000.00" name="edit_allowance" id="editAllowance" required>
            </div>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <div style="display: flex; gap: 12px; justify-content: flex-end; width: 100%;">
          <button type="button" class="modal-btn modal-btn-secondary" onclick="closeEditModal()">
            <i class="fa-solid fa-times"></i>
            Cancel
          </button>
          <button type="submit" form="editInternshipForm" class="modal-btn modal-btn-success">
            <i class="fa-solid fa-save"></i>
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </div>

</div><!-- .posting-page -->

<script>
function togglePostingBanner() {
  document.querySelector('.posting-banner').classList.toggle('collapsed');
}

// ═══════════════════════════════════════════════════════════════════════════════
  // ALERT NOTIFICATION SYSTEM
  // ═══════════════════════════════════════════════════════════════════════════════
  
  function showAlert(message, type = 'success', duration = 4000) {
    const container = document.getElementById('alertContainer');
    
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${type}`;
    
    const bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
    const borderColor = type === 'success' ? '#059669' : type === 'error' ? '#dc2626' : '#2563eb';
    
    alertEl.style.cssText = `
      background: ${bgColor};
      color: white;
      padding: 14px 18px;
      border-radius: 12px;
      border-left: 4px solid ${borderColor};
      font-weight: 500;
      font-size: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      animation: slideIn 0.3s ease-out;
      display: flex;
      align-items: center;
      gap: 10px;
    `;
    
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ⓘ';
    alertEl.innerHTML = `<span style="font-weight: bold; font-size: 16px;">${icon}</span> <span>${message}</span>`;
    
    container.appendChild(alertEl);
    
    setTimeout(() => {
      alertEl.style.animation = 'slideOut 0.3s ease-out forwards';
      setTimeout(() => alertEl.remove(), 300);
    }, duration);
  }

  // Add CSS animations
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }
  `;
  document.head.appendChild(style);

  // ═══════════════════════════════════════════════════════════════════════════════
  // EDIT MODAL FUNCTIONS
  // ═══════════════════════════════════════════════════════════════════════════════

  function openEditModal(postingId, title, description, workSetup, slots, duration, allowance) {
    const modal = document.getElementById('editModal');
    if (modal) {
      document.getElementById('editPostingId').value = postingId;
      document.getElementById('editTitle').value = title;
      document.getElementById('editDescription').value = description;
      document.getElementById('editWorkSetup').value = workSetup;
      document.getElementById('editSlotsAvailable').value = slots;
      document.getElementById('editDurationWeeks').value = duration;
      document.getElementById('editAllowance').value = allowance;
      
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
  }

  function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  }

  // Close edit modal when clicking outside
  document.getElementById('editModal').addEventListener('click', (e) => {
    if (e.target.id === 'editModal') {
      closeEditModal();
    }
  });

  // Close edit modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeEditModal();
    }
  });

  // Handle edit form submission
  document.getElementById('editInternshipForm')?.addEventListener('submit', function(e) {
    // Just let the form submit normally - the server will show the alert via SESSION
  });

  let currentStep = 1;
  const totalSteps = 5;

  function openPostingModal() {
    currentStep = 1;
    showStep(1);
    const modal = document.getElementById('postingModal');
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }

  function closePostingModal() {
    const modal = document.getElementById('postingModal');
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = 'auto';
    }
  }

  function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
    // Show current step
    const stepEl = document.querySelector(`.wizard-step[data-step="${step}"]`);
    if (stepEl) stepEl.classList.add('active');

    // Update step indicators
    document.querySelectorAll('.step-indicator').forEach(el => el.classList.remove('active', 'completed'));
    for (let i = 1; i < step; i++) {
      document.querySelector(`.step-indicator[data-step="${i}"]`)?.classList.add('completed');
    }
    document.querySelector(`.step-indicator[data-step="${step}"]`)?.classList.add('active');

    // Update button visibility
    document.getElementById('prevBtn').style.display = step > 1 ? 'flex' : 'none';
    document.getElementById('nextBtn').style.display = step < totalSteps ? 'flex' : 'none';
    document.getElementById('submitBtn').style.display = step === totalSteps ? 'flex' : 'none';

    // Update review if on final step
    if (step === totalSteps) {
      updateReviewSummary();
    }
  }

  function validateStep(step) {
    const form = document.getElementById('internshipForm');
    const stepEl = document.querySelector(`.wizard-step[data-step="${step}"]`);
    
    if (!stepEl) return true;

    const requiredInputs = stepEl.querySelectorAll('[required]');
    for (let input of requiredInputs) {
      if (!input.value) {
        alert('Please fill in all required fields');
        return false;
      }
    }
    return true;
  }

  function nextStep() {
    if (validateStep(currentStep) && currentStep < totalSteps) {
      currentStep++;
      showStep(currentStep);
    }
  }

  function previousStep() {
    if (currentStep > 1) {
      currentStep--;
      showStep(currentStep);
    }
  }

  function updateReviewSummary() {
    const form = document.getElementById('internshipForm');
    
    // Get values
    const title = form.querySelector('input[name="title"]')?.value || '—';
    const workSetup = form.querySelector('select[name="work_setup"]')?.value || '—';
    const location = document.getElementById('postingLocationPreview')?.value || '—';
    const duration = form.querySelector('input[name="duration_hours"]')?.value || '—';
    const allowance = form.querySelector('input[name="allowance"]')?.value || '—';
    const slots = form.querySelector('input[name="slots_available"]')?.value || '—';

    // Get selected skills
    const selectedSkills = Array.from(form.querySelectorAll('input[name="skills[]"]:checked'))
      .map(el => {
        const skillRow = el.closest('.skills-row');
        return skillRow?.querySelector('[style*="font-weight"]')?.textContent || '—';
      })
      .slice(0, 3)
      .join(', ');
    const skillsText = selectedSkills ? selectedSkills + (form.querySelectorAll('input[name="skills[]"]:checked').length > 3 ? '...' : '') : 'Not specified';

    // Update review elements
    document.getElementById('reviewTitle').textContent = title;
    document.getElementById('reviewWorkSetup').textContent = workSetup;
    document.getElementById('reviewLocation').textContent = location;
    document.getElementById('reviewCompensation').textContent = `${duration} hours / ₱${allowance}/month (${slots} slots)`;
    document.getElementById('reviewSkills').textContent = skillsText;
  }

  // Close modal when clicking outside
  const postingModal = document.getElementById('postingModal');
  if (postingModal) {
    postingModal.addEventListener('click', (e) => {
      if (e.target === postingModal) {
        closePostingModal();
      }
    });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closePostingModal();
    }
  });

  // Attach all inputs/selects/textareas inside modal to the internshipForm
  document.querySelectorAll('#postingModal input, #postingModal select, #postingModal textarea').forEach(el => {
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

  // View toggle function
  function switchPostingView(viewType) {
    const buttons = document.querySelectorAll('.view-toggle-btn');
    buttons.forEach(btn => {
      btn.classList.remove('active');
      if (btn.getAttribute('data-view') === viewType) {
        btn.classList.add('active');
      }
    });

    const postingsGrid = document.querySelector('.postings-grid');
    if (postingsGrid) {
      if (viewType === 'list') {
        postingsGrid.style.gridTemplateColumns = '1fr';
      } else {
        postingsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
      }
    }
  }

  // View details function (currently opens the candidates page, you can modify this)
  function viewPostingDetails(postingId) {
    window.location.href = '/SkillHive/layout.php?page=employer/candidates&position=' + postingId;
  }

  // ═══════════════════════════════════════════════════════════════════════════════
  // DISPLAY ALERTS ON PAGE LOAD
  // ═══════════════════════════════════════════════════════════════════════════════
  
  document.addEventListener('DOMContentLoaded', function() {
    const statusMessage = '<?php echo addslashes($_SESSION['status'] ?? ''); ?>';
    const statusType = '<?php echo addslashes($_SESSION['status_type'] ?? 'success'); ?>';
    
    if (statusMessage) {
      showAlert(statusMessage, statusType);
      <?php unset($_SESSION['status']); unset($_SESSION['status_type']); ?>
    }
  });
</script>


