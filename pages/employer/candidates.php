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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export']) && $_GET['export'] === 'excel' && $employerId > 0) {
    try {
        $exportData = getEmployerCandidatesData($pdo, $employerId, [
            'search' => $currentFilters['search'],
            'position' => $currentFilters['position'],
            'status' => $currentFilters['status'],
            'sort' => $currentFilters['sort'],
        ]);

        $statusColors = [
            'Pending' => '#fef3c7',
            'Shortlisted' => '#dbeafe',
            'Interview Scheduled' => '#e9d5ff',
            'Accepted' => '#d1fae5',
            'Rejected' => '#fee2e2',
        ];

        $statusTextColors = [
            'Pending' => '#92400e',
            'Shortlisted' => '#1e40af',
            'Interview Scheduled' => '#6b21a8',
            'Accepted' => '#065f46',
            'Rejected' => '#991b1b',
        ];

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Candidates</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
        $html .= '<body><table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-family:Calibri,sans-serif;font-size:11px;">';
        $html .= '<tr style="background:#f3f4f6;font-weight:bold;border-bottom:2px solid #555;">';
        $html .= '<td style="padding:8px 12px;text-align:center;">Name</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Email</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Program</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Year Level</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Applied Position</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Status</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Match %</td>';
        $html .= '<td style="padding:8px 12px;text-align:center;">Application Date</td>';
        $html .= '</tr>';

        foreach ($exportData['candidates'] as $row) {
            $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $scoreText = is_numeric($row['compatibility_score'] ?? '') ? ((int)round((float)$row['compatibility_score']) . '%') : 'N/A';
            $statusRaw = candidates_normalize_status((string)($row['status'] ?? 'pending'));
            $statusDisplay = candidates_status_display_label($statusRaw);
            $appDate = !empty($row['application_date']) ? date('M d, Y', strtotime($row['application_date'])) : 'N/A';
            
            $bgColor = $statusColors[$statusDisplay] ?? '#ffffff';
            $textColor = $statusTextColors[$statusDisplay] ?? '#374151';

            $html .= '<tr>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars($fullName) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars((string)($row['email'] ?? '')) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars((string)($row['program'] ?? '')) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars((string)($row['year_level'] ?? '')) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars((string)($row['internship_title'] ?? '')) . '</td>';
            $html .= '<td style="padding:6px 12px;background:' . $bgColor . ';color:' . $textColor . ';font-weight:bold;">' . htmlspecialchars($statusDisplay) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars($scoreText) . '</td>';
            $html .= '<td style="padding:6px 12px;">' . htmlspecialchars($appDate) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="candidates_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        echo $html;
        exit;
    } catch (Throwable $e) {
        $errorMessage = 'Export failed. Please try again.';
    }
}

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
    $errorMessage = 'Database error: ' . $e->getMessage();
    error_log('CANDIDATES ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  }
} else {
  $errorMessage = 'Employer ID not resolved. Session: employer_id=' . ($_SESSION['employer_id'] ?? 'N/A') . ', user_id=' . ($_SESSION['user_id'] ?? 'N/A') . ', role=' . ($_SESSION['role'] ?? 'N/A');
}

$candidates = $candidateData['candidates'];
$skillsByStudent = $candidateData['skills_by_student'];
$positions = $candidateData['positions'];
$statuses = $candidateData['statuses'];
$selected = $candidateData['selected'];
$showInterviewSuccessModal = ((int)($_GET['interview_success'] ?? 0) === 1);

$pipelineStatuses = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
?>

<style>
.candidates-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  padding: 16px 20px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.candidates-topbar .filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1;
}

.candidates-topbar .filter-row .filter-select {
  padding: 9px 14px !important;
  border-radius: 50px !important;
  border: 1.5px solid #e5e7eb !important;
  font-size: 14px !important;
  color: #374151 !important;
  background: #fff !important;
  cursor: pointer !important;
  outline: none !important;
  transition: all 0.2s ease !important;
  font-weight: 500 !important;
  min-width: 160px;
}

.candidates-topbar .filter-row .filter-select:hover {
  border-color: #d1d5db !important;
}

.candidates-topbar .filter-row .filter-select:focus {
  border-color: #000 !important;
  box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08) !important;
}

.candidates-topbar .filter-row .btn-sm {
  padding: 9px 18px !important;
  border-radius: 8px !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  border: 1.5px solid #555 !important;
  transition: all 0.2s ease !important;
}

.candidates-topbar .filter-row .btn-primary {
  background: #000 !important;
  color: #fff !important;
  border-color: #000 !important;
}

.candidates-topbar .filter-row .btn-primary:hover {
  background: #222 !important;
  border-color: #222 !important;
}

.candidates-topbar .filter-row .btn-ghost {
  background: transparent !important;
  color: #6b7280 !important;
  border-color: #d1d5db !important;
}

.candidates-topbar .filter-row .btn-ghost:hover {
  background: #f3f4f6 !important;
  border-color: #9ca3af !important;
}

.candidates-topbar .topbar-search {
  position: relative !important;
  flex: 1 !important;
  max-width: 280px !important;
  display: block !important;
}

.candidates-topbar .topbar-search i {
  position: absolute !important;
  left: 14px !important;
  top: 50% !important;
  transform: translateY(-50%) !important;
  color: #9ca3af !important;
  font-size: 14px !important;
  pointer-events: none !important;
  z-index: 2 !important;
}

.candidates-topbar .topbar-search input {
  width: 100%;
  padding: 9px 14px 9px 40px;
  border: 1.5px solid #e5e7eb;
  border-radius: 50px;
  font-size: 14px;
  color: #374151;
  outline: none;
  transition: all 0.2s ease;
  background: #fff;
  font-weight: 500;
  box-sizing: border-box;
}

.candidates-topbar .topbar-search input::placeholder {
  color: #b0b0b0;
}

.candidates-topbar .topbar-search input:hover {
  border-color: #d1d5db;
}

.candidates-topbar .topbar-search input:focus {
  border-color: #000;
  box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: #000;
  color: #fff;
  border: 1.5px solid #000;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-export:hover {
  background: #222;
  border-color: #222;
}

.alert-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
}

.cards-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)) !important;
  gap: 16px !important;
}

.candidate-card {
  background: #fff !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 12px !important;
  padding: 20px !important;
  transition: all 0.2s ease !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 14px !important;
}

.candidate-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
  border-color: #d1d5db !important;
}

.candidate-header {
  display: flex;
  align-items: center;
  gap: 14px;
}

.candidate-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 700;
  flex-shrink: 0;
}

.candidate-name {
  font-size: 16px;
  font-weight: 700;
  color: #111;
  margin-bottom: 2px;
}

.candidate-program {
  font-size: 13px;
  color: #6b7280;
}

.match-badge {
  margin-left: auto;
  background: linear-gradient(135deg, #059669, #10b981) !important;
  color: #fff !important;
  padding: 6px 12px !important;
  border-radius: 50px !important;
  font-size: 13px !important;
  font-weight: 700 !important;
}

.applied-position {
  font-size: 14px;
  color: #374151;
  padding: 10px 14px;
  background: #f9fafb;
  border-radius: 8px;
  border-left: 3px solid #667eea;
  font-weight: 500;
}

.applied-position i {
  color: #667eea;
  margin-right: 6px;
}

.applied-position {
  font-size: 14px;
  color: #374151;
  padding: 8px 12px;
  background: #f9fafb;
  border-radius: 8px;
  border-left: 3px solid #667eea;
}

.stage-tracker {
}

.stage-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}

.stage-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.stage-chip {
  font-size: 11px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(0,0,0,0.04);
  color: #6b7280;
  border: 1px solid rgba(0,0,0,0.08);
}

.stage-chip.active {
  background: rgba(102, 126, 234, 0.15);
  color: #667eea;
  border-color: rgba(102, 126, 234, 0.3);
  font-weight: 600;
}

.skills-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.skill-tag {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.skill-tag.gap {
  background: #fef3c7;
  color: #92400e;
  border-color: #fde68a;
}

.info-badges {
  display: flex;
  gap: 8px;
}

.info-badge {
  font-size: 12px;
  padding: 5px 12px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  font-weight: 500;
}

.info-badge i {
  color: #059669;
}

.card-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-top: auto;
}

.btn-card {
  padding: 8px 14px;
  border: 1.5px solid #000;
  background: #000;
  color: #fff;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-card:hover {
  background: #222;
  border-color: #222;
}

.btn-card-ghost {
  background: transparent;
  color: #555;
  border-color: #d1d5db;
}

.btn-card-ghost:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-card-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.2);
}

.btn-card-danger:hover {
  background: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.4);
}

.status-select {
  flex: 1;
  padding: 8px 12px;
  border: 1.5px solid #d1d5db;
  border-radius: 6px;
  font-size: 12px;
  background: #fff;
  cursor: pointer;
  font-weight: 500;
}

.endorsement-warning {
  margin-top: 12px;
  font-size: 12px;
  color: #B45309;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
  padding: 8px 10px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Profile Modal */
.profile-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 999;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.profile-modal-overlay.active {
  display: flex;
}

.profile-modal {
  background: #fff;
  border-radius: 16px;
  max-width: 700px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.profile-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 28px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.profile-modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #111;
}

.profile-modal-close {
  background: transparent;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #9ca3af;
  padding: 4px;
}

.profile-modal-close:hover {
  color: #374151;
}

.profile-modal-body {
  padding: 28px;
  overflow-y: auto;
  flex: 1;
}

.profile-modal-body::-webkit-scrollbar {
  width: 6px;
}

.profile-modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.profile-modal-body::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.profile-modal-body::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.profile-photo-section {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e5e7eb;
}

.profile-photo-large {
  width: 200px;
  height: 260px;
  border-radius: 16px;
  object-fit: cover;
  object-position: top center;
  border: 4px solid #f3f4f6;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.profile-photo-fallback {
  width: 200px;
  height: 260px;
  border-radius: 16px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 64px;
  font-weight: 700;
  color: #fff;
  border: 4px solid #f3f4f6;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.profile-photo-name {
  margin-top: 14px;
  font-size: 20px;
  font-weight: 700;
  color: #111;
}

.profile-photo-meta {
  font-size: 14px;
  color: #6b7280;
  margin-top: 4px;
}

.profile-resume-section {
  background: #f9fafb;
  border-radius: 12px;
  padding: 20px;
  margin-top: 20px;
  border: 1px solid #e5e7eb;
}

.profile-resume-section .profile-section-title {
  margin-bottom: 12px;
}

.profile-resume-card {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 18px;
  transition: box-shadow 0.2s;
}

.profile-resume-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.profile-resume-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #dc2626, #ef4444);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 20px;
  flex-shrink: 0;
}

.profile-resume-info {
  flex: 1;
  min-width: 0;
}

.profile-resume-filename {
  font-size: 14px;
  font-weight: 600;
  color: #111;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-resume-type {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.profile-resume-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.profile-resume-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: background 0.2s, color 0.2s;
}

.profile-resume-btn-primary {
  background: #000;
  color: #fff;
}

.profile-resume-btn-primary:hover {
  background: #222;
  color: #fff;
}

.profile-resume-btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.profile-resume-btn-secondary:hover {
  background: #e5e7eb;
  color: #111;
}

.profile-no-resume {
  text-align: center;
  padding: 20px;
  color: #9ca3af;
  font-size: 14px;
}

.profile-no-resume i {
  font-size: 28px;
  display: block;
  margin-bottom: 8px;
}

.profile-top {
  display: flex;
  gap: 20px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 700;
  flex-shrink: 0;
}

.profile-main-info {
  flex: 1;
}

.profile-name {
  font-size: 22px;
  font-weight: 700;
  color: #111;
  margin-bottom: 4px;
}

.profile-program-text {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.profile-stats {
  display: flex;
  gap: 10px;
}

.profile-stat-badge {
  font-size: 12px;
  padding: 5px 12px;
  border-radius: 999px;
  font-weight: 600;
}

.profile-stat-badge.match {
  background: rgba(102, 126, 234, 0.15);
  color: #667eea;
}

.profile-section {
  margin-bottom: 20px;
}

.profile-section-title {
  font-size: 14px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.profile-section-title i {
  color: #667eea;
}

.profile-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.profile-field {
  font-size: 14px;
}

.profile-field-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 2px;
}

.profile-field-value {
  color: #111;
  font-weight: 500;
}

.profile-skills {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.profile-skill-tag {
  font-size: 13px;
  padding: 6px 14px;
  border-radius: 8px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.loading-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #6b7280;
}

.loading-spinner i {
  animation: spin 1s linear infinite;
  margin-right: 10px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.candidates-banner {
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

.candidates-banner::before {
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

.candidates-banner::after {
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

.candidates-banner.collapsed {
  padding: 8px 16px;
  min-height: 0;
}

.candidates-banner.collapsed .cb-main {
  display: none;
}

.candidates-banner.collapsed .cb-toggle {
  display: none;
}

.cb-main {
  display: flex;
  align-items: center;
  gap: 24px;
  position: relative;
  z-index: 1;
  flex: 1;
}

.cb-info {
  flex: 1;
}

.cb-date {
  font-size: 12px;
  font-weight: 100;
  color: #9ca3af;
  margin-bottom: 4px;
  letter-spacing: 1px;
}

.cb-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 2px;
  text-transform: capitalize;
  display: inline;
}

.cb-desc {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
  max-width: 450px;
}

.cb-toggle {
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

.cb-toggle:hover {
  background: #fff;
  border-color: rgba(20, 184, 166, 0.3);
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(20, 184, 166, 0.1);
}

.cb-expand-hint {
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

.cb-expand-hint:hover {
  opacity: 1;
}

.candidates-banner.collapsed .cb-expand-hint {
  display: block;
}

.candidates-banner:not(.collapsed) .cb-expand-hint {
  display: none !important;
}

.cb-info {
  flex: 1;
  border-left: 1.5px solid rgba(255, 255, 255, 0.25);
  padding-left: 16px;
}

@media (max-width: 768px) {
  .candidates-topbar {
    flex-direction: column;
    align-items: stretch;
  }
  
  .candidates-topbar .filter-row {
    flex-direction: column;
  }
  
  .profile-top {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  
  .profile-stats {
    justify-content: center;
  }
  
  .profile-grid {
    grid-template-columns: 1fr;
  }
}

.view-toggle {
  display: flex;
  gap: 4px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 4px;
  flex-shrink: 0;
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

.cards-grid.list-view {
  grid-template-columns: 1fr !important;
  gap: 10px !important;
}

.cards-grid.list-view .candidate-card {
  flex-direction: row !important;
  align-items: center !important;
  padding: 14px 20px !important;
  gap: 16px !important;
}

.cards-grid.list-view .candidate-header {
  min-width: 220px;
  flex-shrink: 0;
}

.cards-grid.list-view .candidate-avatar {
  width: 40px;
  height: 40px;
  font-size: 14px;
}

.cards-grid.list-view .candidate-name {
  font-size: 14px;
}

.cards-grid.list-view .candidate-program {
  font-size: 12px;
}

.cards-grid.list-view .match-score {
  flex-shrink: 0;
  min-width: 80px;
  text-align: center;
}

.cards-grid.list-view .skills-row {
  flex-shrink: 0;
  max-width: 200px;
  overflow: hidden;
}

.cards-grid.list-view .info-badges {
  flex-shrink: 0;
}

.cards-grid.list-view .card-actions {
  flex-shrink: 0;
}

.cards-grid.list-view .applied-position,
.cards-grid.list-view .stage-tracker,
.cards-grid.list-view .endorsement-warning {
  display: none !important;
}
</style>

<script>
function toggleCandidatesBanner() {
  document.querySelector('.candidates-banner').classList.toggle('collapsed');
}

function switchCandidateView(viewType) {
  const buttons = document.querySelectorAll('.view-toggle-btn');
  buttons.forEach(btn => {
    btn.classList.remove('active');
    if (btn.getAttribute('data-view') === viewType) {
      btn.classList.add('active');
    }
  });

  const grid = document.getElementById('candidatesGrid');
  if (grid) {
    if (viewType === 'list') {
      grid.classList.add('list-view');
    } else {
      grid.classList.remove('list-view');
    }
  }
}
</script>

<div class="candidates-page">
  <div class="candidates-banner">
    <div class="cb-main">
      <div class="cb-info">
        <div class="cb-date"><?php echo date('l, jS F'); ?></div>
        <div class="cb-title">Good afternoon!</div>
        <div class="cb-desc">Review, rank, and manage internship applicants for your posted positions.</div>
      </div>
    </div>
    <button type="button" class="cb-toggle" onclick="toggleCandidatesBanner()" title="Hide banner">
      <i class="fas fa-chevron-up"></i>
    </button>
    <div class="cb-expand-hint" onclick="toggleCandidatesBanner()">
      <i class="fas fa-chevron-down"></i> Show banner
    </div>
  </div>

  <?php if ($errorMessage !== ''): ?>
    <div class="alert alert-error" style="margin-bottom: 20px;">
      <i class="fas fa-circle-exclamation"></i>
      <?php echo dashboard_escape($errorMessage); ?>
    </div>
  <?php endif; ?>

  <div class="candidates-topbar">
    <div class="topbar-left">
      <div>
        <h2 style="margin: 0 0 4px; font-size: 1.25rem; font-weight: 600;">Candidates</h2>
        <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">Manage and track your active internship listings</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="view-toggle">
        <button type="button" class="view-toggle-btn active" data-view="grid" onclick="switchCandidateView('grid')" title="Grid view">
          <i class="fas fa-th"></i>
        </button>
        <button type="button" class="view-toggle-btn" data-view="list" onclick="switchCandidateView('list')" title="List view">
          <i class="fas fa-list-ul"></i>
        </button>
      </div>
      <a href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates&export=excel&search=<?php echo urlencode($currentFilters['search']); ?>&position=<?php echo urlencode($currentFilters['position']); ?>&status=<?php echo urlencode($currentFilters['status']); ?>&sort=<?php echo urlencode($currentFilters['sort']); ?>" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Export Excel
      </a>
    </div>
  </div>
    <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row">
      <input type="hidden" name="page" value="employer/candidates">

      <div class="topbar-search">
        <i class="fas fa-search"></i>
        <input type="text" name="search" placeholder="Search candidates..." value="<?php echo dashboard_escape($currentFilters['search']); ?>">
      </div>

      <select class="filter-select" name="position">
        <option value="">Posted Applications</option>
        <?php foreach ($positions as $position): ?>
          <?php
          $positionId = (string)($position['internship_id'] ?? '');
          $positionTitle = (string)($position['title'] ?? 'Untitled Internship');
          ?>
          <option value="<?php echo dashboard_escape($positionId); ?>" <?php echo (string)$currentFilters['position'] === $positionId ? 'selected' : ''; ?>><?php echo dashboard_escape($positionTitle); ?></option>
        <?php endforeach; ?>
      </select>

      <select class="filter-select" name="status">
        <option value="">All Application Stages</option>
        <?php foreach ($statuses as $status): ?>
          <?php
          $normalizedFilterStatus = candidates_normalize_status((string)$status);
          $filterStatusLabel = candidates_status_display_label($normalizedFilterStatus);
          ?>
          <option value="<?php echo dashboard_escape($status); ?>" <?php echo $currentFilters['status'] === $status ? 'selected' : ''; ?>><?php echo dashboard_escape($filterStatusLabel); ?></option>
        <?php endforeach; ?>
      </select>

      <select class="filter-select" name="sort">
        <option value="match" <?php echo $currentFilters['sort'] === 'match' ? 'selected' : ''; ?>>Sort by Match %</option>
        <option value="date" <?php echo $currentFilters['sort'] === 'date' ? 'selected' : ''; ?>>Sort by Date</option>
        <option value="name" <?php echo $currentFilters['sort'] === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
      </select>

      <button class="btn btn-primary btn-sm" type="submit">Apply</button>
      <a class="btn btn-ghost btn-sm" href="<?php echo $baseUrl; ?>/layout.php?page=employer/candidates">Reset</a>
    </form>
  </div>

  <div class="cards-grid" id="candidatesGrid" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))">
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
        $statusRaw = (string)($candidate['status'] ?? 'pending');
        $statusCanonical = candidates_normalize_status($statusRaw);
        $statusDisplay = candidates_status_display_label($statusCanonical);
        $pipelineFlow = ['Pending', 'Shortlisted', 'Interview Scheduled', 'Accepted', 'Rejected'];
        $isEndorsementApproved = !empty($candidate['endorsement_approved']);
        $chipSkills = $skillsByStudent[$studentId] ?? [];
        $resumeFile = (string)($candidate['resume_file'] ?? '');
        ?>

        <div class="candidate-card">
          <div class="candidate-header">
            <div class="candidate-avatar"><?php echo dashboard_escape($initialsText); ?></div>
            <div style="flex:1">
              <div class="candidate-name"><?php echo dashboard_escape($fullName); ?></div>
              <div class="candidate-program"><?php echo dashboard_escape($program); ?> — <?php echo dashboard_escape($yearLevel); ?></div>
            </div>
            <div class="match-badge"><?php echo dashboard_escape($scoreText); ?></div>
          </div>

          <div class="applied-position">
            <i class="fas fa-briefcase"></i>
            <?php echo dashboard_escape($candidate['internship_title'] ?? 'N/A'); ?>
          </div>

          <div class="stage-tracker">
            <div class="stage-label">Current Stage: <strong><?php echo dashboard_escape($statusDisplay); ?></strong></div>
            <div class="stage-chips">
              <?php foreach ($pipelineFlow as $stage): ?>
                <?php
                $isActiveStage = $stage === $statusCanonical;
                $stageDisplay = candidates_status_display_label($stage);
                ?>
                <span class="stage-chip <?php echo $isActiveStage ? 'active' : ''; ?>"><?php echo dashboard_escape($stageDisplay); ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="skills-row">
            <?php if (!empty($chipSkills)): ?>
              <?php foreach ($chipSkills as $chip): ?>
                <?php if (!empty($chip['verified'])): ?>
                  <span class="skill-tag"><?php echo dashboard_escape($chip['skill_name']); ?> <i class="fas fa-check" style="font-size: 10px;"></i></span>
                <?php else: ?>
                  <span class="skill-tag gap"><?php echo dashboard_escape($chip['skill_name']); ?> <i class="fas fa-arrow-up" style="font-size: 10px;"></i></span>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="skill-tag gap">No skills listed</span>
            <?php endif; ?>
          </div>

          <div class="info-badges">
            <span class="info-badge"><i class="fas fa-graduation-cap" style="margin-right: 4px;"></i>Year: <?php echo dashboard_escape($yearLevel); ?></span>
          </div>

          <div class="card-actions">
            <form method="post" class="candidate-status-form" style="flex:2" data-application-id="<?php echo (int)($candidate['application_id'] ?? 0); ?>" data-current-status="<?php echo dashboard_escape($statusCanonical); ?>">
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="application_id" value="<?php echo (int)($candidate['application_id'] ?? 0); ?>">
              <input type="hidden" name="search" value="<?php echo dashboard_escape($selected['search']); ?>">
              <input type="hidden" name="position" value="<?php echo dashboard_escape($selected['position']); ?>">
              <input type="hidden" name="status" value="<?php echo dashboard_escape($selected['status']); ?>">
              <input type="hidden" name="sort" value="<?php echo dashboard_escape($selected['sort']); ?>">
              <select name="next_status" class="status-select" onchange="handleStatusChange(this)">
                <?php foreach ($pipelineStatuses as $pipelineStatus): ?>
                  <?php
                  $pipelineDisplay = candidates_status_display_label($pipelineStatus);
                  $isInterviewOptionLocked = !$isEndorsementApproved && $pipelineStatus === 'Interview Scheduled' && $statusCanonical !== 'Interview Scheduled';
                  ?>
                  <option value="<?php echo dashboard_escape($pipelineStatus); ?>" <?php echo $statusCanonical === $pipelineStatus ? 'selected' : ''; ?> <?php echo $isInterviewOptionLocked ? 'disabled' : ''; ?>><?php echo dashboard_escape($pipelineDisplay); ?><?php echo $isInterviewOptionLocked ? ' (Locked)' : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </form>

            <button class="btn-card" type="button" onclick="openProfileModal(<?php echo $studentId; ?>, <?php echo (int)($candidate['application_id'] ?? 0); ?>)">
              <i class="fas fa-user"></i> Profile
            </button>

            <?php if ($resumeFile !== ''): ?>
              <a href="<?php echo $baseUrl; ?>/assets/backend/uploads/resumes/<?php echo urlencode($resumeFile); ?>" target="_blank" class="btn-card btn-card-ghost">
                <i class="fas fa-file-pdf"></i> Resume
              </a>
            <?php endif; ?>

            <button class="btn-card btn-card-danger" type="button" onclick="quickRejectCandidate(<?php echo (int)($candidate['application_id'] ?? 0); ?>, '<?php echo dashboard_escape($selected['search']); ?>', '<?php echo dashboard_escape($selected['position']); ?>', '<?php echo dashboard_escape($selected['status']); ?>', '<?php echo dashboard_escape($selected['sort']); ?>')">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <?php if (!$isEndorsementApproved && in_array($statusCanonical, ['Pending', 'Shortlisted'], true)): ?>
            <div class="endorsement-warning">
              <i class="fas fa-lock"></i>
              Interview is locked until adviser endorsement is approved.
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="candidate-card" style="grid-column:1/-1;text-align:center;padding:40px;">
        <i class="fas fa-users" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px;"></i>
        <div style="font-weight:700;font-size:1.1rem;margin-bottom:8px">No candidates found</div>
        <div style="font-size:.9rem;color:#6b7280;">Try adjusting your filters or wait for new applications.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Profile Modal -->
<div class="profile-modal-overlay" id="profileModal">
  <div class="profile-modal">
    <div class="profile-modal-header">
      <h2><i class="fas fa-user" style="margin-right: 10px; color: #667eea;"></i>Applicant Profile</h2>
      <button type="button" class="profile-modal-close" onclick="closeProfileModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="profile-modal-body" id="profileModalBody">
      <div class="loading-spinner">
        <i class="fas fa-spinner"></i> Loading profile...
      </div>
    </div>
  </div>
</div>

<!-- Interview Modal (existing) -->
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

function openProfileModal(studentId, applicationId) {
  const modal = document.getElementById('profileModal');
  const body = document.getElementById('profileModalBody');
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  
  body.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner"></i> Loading profile...</div>';
  
  fetch('<?php echo $baseUrl; ?>/pages/employer/candidates/candidates_api.php?action=get_profile&student_id=' + studentId + '&application_id=' + applicationId)
    .then(res => res.json())
    .then(data => {
      if (data.ok && data.profile) {
        renderProfile(data.profile, data.skills, data.baseUrl);
      } else {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;"><i class="fas fa-exclamation-circle" style="font-size:32px;margin-bottom:12px;display:block;"></i>Unable to load profile</div>';
      }
    })
    .catch(() => {
      body.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;"><i class="fas fa-exclamation-triangle" style="font-size:32px;margin-bottom:12px;display:block;"></i>Error loading profile</div>';
    });
}

function renderProfile(profile, skills, baseUrl) {
  const body = document.getElementById('profileModalBody');
  const fullName = (profile.first_name || '') + ' ' + (profile.last_name || '');
  const initials = ((profile.first_name || '')[0] || '') + ((profile.last_name || '')[0] || '');
  const matchText = profile.compatibility_score ? Math.round(parseFloat(profile.compatibility_score)) + '%' : 'N/A';
  const email = profile.email || 'N/A';
  
  const hasPhoto = profile.profile_picture && profile.profile_picture.trim() !== '';
  const photoUrl = hasPhoto ? baseUrl + '/assets/backend/uploads/profiles/' + encodeURIComponent(profile.profile_picture) : '';
  
  const hasResume = profile.resume_file && profile.resume_file.trim() !== '';
  const resumeUrl = hasResume ? baseUrl + '/assets/backend/uploads/resumes/' + encodeURIComponent(profile.resume_file) : '';
  const resumeExt = hasResume ? profile.resume_file.split('.').pop().toUpperCase() : '';
  
  let skillsHtml = '';
  if (skills && skills.length > 0) {
    skillsHtml = '<div class="profile-skills">' + skills.map(s => 
      `<span class="profile-skill-tag">${s.skill_name || ''} ${s.verified ? '<i class="fas fa-check-circle" style="color:#166534;font-size:12px;"></i>' : '<i class="fas fa-clock" style="color:#92400e;font-size:12px;"></i>'}</span>`
    ).join('') + '</div>';
  } else {
    skillsHtml = '<div style="color:#9ca3af;font-size:14px;">No skills listed</div>';
  }
  
  let photoHtml = '';
  if (hasPhoto) {
    photoHtml = `<img src="${photoUrl}" alt="${fullName}" class="profile-photo-large" onerror="this.outerHTML='<div class=\\'profile-photo-fallback\\'>${initials.toUpperCase()}</div>'">`;
  } else {
    photoHtml = `<div class="profile-photo-fallback">${initials.toUpperCase()}</div>`;
  }
  
  let resumeHtml = '';
  if (hasResume) {
    resumeHtml = `
    <div class="profile-resume-section">
      <div class="profile-section-title"><i class="fas fa-file-pdf"></i> Attached Resume</div>
      <div class="profile-resume-card">
        <div class="profile-resume-icon"><i class="fas fa-file-alt"></i></div>
        <div class="profile-resume-info">
          <div class="profile-resume-filename">${profile.resume_file}</div>
          <div class="profile-resume-type">${resumeExt} Document</div>
        </div>
        <div class="profile-resume-actions">
          <a href="${resumeUrl}" target="_blank" class="profile-resume-btn profile-resume-btn-primary"><i class="fas fa-eye"></i> View</a>
          <a href="${resumeUrl}" download class="profile-resume-btn profile-resume-btn-secondary"><i class="fas fa-download"></i> Download</a>
        </div>
      </div>
    </div>`;
  } else {
    resumeHtml = `
    <div class="profile-resume-section">
      <div class="profile-section-title"><i class="fas fa-file-pdf"></i> Attached Resume</div>
      <div class="profile-no-resume"><i class="fas fa-file-slash"></i>No resume attached</div>
    </div>`;
  }
  
  body.innerHTML = `
    <div class="profile-photo-section">
      ${photoHtml}
      <div class="profile-photo-name">${fullName || 'N/A'}</div>
      <div class="profile-photo-meta">${profile.program || 'N/A'} — Year ${profile.year_level || 'N/A'}</div>
      <div style="margin-top: 8px;"><span class="profile-stat-badge match"><i class="fas fa-bullseye" style="margin-right:4px;"></i>${matchText} Match</span></div>
    </div>
    
    <div class="profile-section">
      <div class="profile-section-title"><i class="fas fa-id-card"></i> Contact Information</div>
      <div class="profile-grid">
        <div class="profile-field">
          <div class="profile-field-label">Email</div>
          <div class="profile-field-value">${email}</div>
        </div>
      </div>
    </div>
    
    <div class="profile-section">
      <div class="profile-section-title"><i class="fas fa-graduation-cap"></i> Academic Information</div>
      <div class="profile-grid">
        <div class="profile-field">
          <div class="profile-field-label">Program</div>
          <div class="profile-field-value">${profile.program || 'N/A'}</div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">Year Level</div>
          <div class="profile-field-value">${profile.year_level || 'N/A'}</div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">Applied Position</div>
          <div class="profile-field-value">${profile.internship_title || 'N/A'}</div>
        </div>
        <div class="profile-field">
          <div class="profile-field-label">Application Date</div>
          <div class="profile-field-value">${profile.application_date ? new Date(profile.application_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</div>
        </div>
      </div>
    </div>
    
    <div class="profile-section">
      <div class="profile-section-title"><i class="fas fa-code"></i> Skills</div>
      ${skillsHtml}
    </div>
    
    ${resumeHtml}
  `;
}

function closeProfileModal() {
  const modal = document.getElementById('profileModal');
  modal.classList.remove('active');
  document.body.style.overflow = 'auto';
}

document.getElementById('profileModal').addEventListener('click', (e) => {
  if (e.target.id === 'profileModal') {
    closeProfileModal();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeProfileModal();
  }
});

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
