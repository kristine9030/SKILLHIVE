<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/endorsement/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';

$currentFilters = [
  'tab' => trim((string)($_REQUEST['tab'] ?? 'pending')),
  'department' => trim((string)($_REQUEST['department'] ?? '')),
  'search' => trim((string)($_REQUEST['search'] ?? '')),
];

if (!in_array($currentFilters['tab'], ['pending', 'approved', 'all'], true)) {
  $currentFilters['tab'] = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adviserId > 0) {
  $action = trim((string)($_POST['action'] ?? ''));
  $endorsementId = (int)($_POST['endorsement_id'] ?? 0);
  $adviserNotes = trim((string)($_POST['adviser_notes'] ?? ''));

  if (in_array($action, ['approve', 'reject', 'request_docs'], true)) {
    try {
      $result = adviser_endorsement_update_status($pdo, $adviserId, $endorsementId, $action, $adviserNotes);
      if (!empty($result['success'])) {
        if ($action === 'approve') {
          $_SESSION['status'] = 'Endorsement approved.';
        } elseif ($action === 'reject') {
          $_SESSION['status'] = 'Endorsement rejected.';
        } else {
          $_SESSION['status'] = 'Requested additional documents.';
        }
      } else {
        $errorMessage = (string)($result['error'] ?? 'Unable to update endorsement status.');
      }
    } catch (Throwable $e) {
      $errorMessage = 'Action failed. Please try again.';
    }
  }

  if ($errorMessage === '') {
    $query = http_build_query([
      'page' => 'adviser/endorsement',
      'tab' => (string)$currentFilters['tab'],
      'department' => (string)$currentFilters['department'],
      'search' => (string)$currentFilters['search'],
    ]);
    header('Location: ' . $baseUrl . '/layout.php?' . $query);
    exit;
  }
}

$pageData = [
  'selected' => ['tab' => 'pending', 'department' => '', 'search' => ''],
  'filter_options' => ['statuses' => [], 'departments' => []],
  'pending' => [],
  'approved' => [],
  'all' => [],
];

if ($adviserId > 0) {
  try {
    $pageData = getAdviserEndorsementPageData($pdo, $adviserId, $currentFilters);
  } catch (Throwable $e) {
    $pageData['selected'] = [
      'tab' => (string)$currentFilters['tab'],
      'department' => (string)$currentFilters['department'],
      'search' => (string)$currentFilters['search'],
    ];
  }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$pendingRows = $pageData['pending'];
$approvedRows = $pageData['approved'];
$allRows = $pageData['all'];
$activeTab = (string)($currentFilters['tab'] ?? 'pending');

if (!in_array($activeTab, ['pending', 'approved', 'all'], true)) {
  $activeTab = 'pending';
}

$buildTabUrl = static function (string $tabName) use ($baseUrl, $selected): string {
  $params = [
    'page' => 'adviser/endorsement',
    'tab' => $tabName,
    'department' => (string)($selected['department'] ?? ''),
    'search' => (string)($selected['search'] ?? ''),
  ];
  return $baseUrl . '/layout.php?' . http_build_query($params);
};

$pendingCount = count($pendingRows);
$approvedCount = count($approvedRows);
$rejectedCount = 0;

foreach ($allRows as $row) {
  if (adviser_endorsement_normalize_status((string)($row['status'] ?? '')) === 'Rejected') {
    $rejectedCount++;
  }
}

$totalThisTerm = count($allRows);

$resolveEndorsementFileUrl = static function (?string $file) use ($baseUrl): string {
  $value = trim((string)($file ?? ''));
  if ($value === '') {
    return '';
  }

  if (preg_match('/^https?:\/\//i', $value)) {
    return $value;
  }

  if (strpos($value, '/') === 0) {
    return $baseUrl . $value;
  }

  return $baseUrl . '/assets/backend/uploads/endorsements/' . rawurlencode($value);
};
?>

<style>
  .endorsement-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
  }

  .endorsement-stat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
  }

  .endorsement-stat-label {
    font-size: .78rem;
    color: var(--text3);
    margin-bottom: 6px;
  }

  .endorsement-stat-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--text);
  }

  .endorsement-tabs {
    margin-bottom: 14px;
  }

  .endorsement-tabs .tab-btn {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .endorsement-table-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 8px 10px;
  }

  .endorsement-table-card .app-table th {
    padding: 10px 12px;
  }

  .endorsement-table-card .app-table td {
    padding: 10px 12px;
  }

  .endorsement-empty {
    text-align: center;
    color: var(--text3);
  }

  .docs-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
  }

  .docs-chip.complete {
    background: rgba(16, 185, 129, .12);
    color: #047857;
  }

  .docs-chip.partial {
    background: rgba(245, 158, 11, .14);
    color: #B45309;
  }

  .endorsement-action-btn {
    min-width: 92px;
  }

  .review-modal {
    position: fixed;
    inset: 0;
    background: rgba(17, 24, 39, .55);
    z-index: 1200;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }

  .review-modal.is-open {
    display: flex;
  }

  .review-modal-dialog {
    width: min(760px, 100%);
    max-height: calc(100vh - 32px);
    overflow: auto;
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 18px 40px rgba(15, 23, 42, .25);
    padding: 18px;
  }

  .review-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
  }

  .review-modal-head h4 {
    margin: 0;
    font-size: 1rem;
  }

  .review-modal-subtitle {
    margin-top: 4px;
    font-size: .83rem;
    color: var(--text3);
  }

  .review-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
  }

  .review-card {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    background: #fff;
  }

  .review-label {
    font-size: .72rem;
    color: var(--text3);
    margin-bottom: 4px;
  }

  .review-value {
    font-size: .85rem;
    font-weight: 600;
    color: var(--text);
  }

  .doc-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
  }

  .doc-list li {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 9px 11px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: .84rem;
  }

  .doc-state-ok {
    color: #047857;
    font-weight: 700;
  }

  .doc-state-missing {
    color: #B45309;
    font-weight: 700;
  }

  .review-notes {
    width: 100%;
    min-height: 92px;
    margin-top: 10px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    font-family: 'Inter', sans-serif;
    font-size: .84rem;
    color: var(--text);
    resize: vertical;
    outline: none;
  }

  .review-notes:focus {
    border-color: #111;
  }

  .review-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
  }

  .review-file-link {
    font-size: .78rem;
    color: var(--text2);
    text-decoration: none;
    border-bottom: 1px dashed var(--border-dark);
  }

  .review-file-link:hover {
    color: #111;
    border-bottom-color: #111;
  }

  @media (max-width: 768px) {
    .endorsement-table-card .app-table td,
    .endorsement-table-card .app-table th {
      font-size: .86rem;
    }
  }
</style>
  <div class="page-header">
    <div>
      <h2 class="page-title">Endorsements</h2>
      <p class="page-subtitle">Review and process adviser-assigned internship endorsements.</p>
    </div>
  </div>

  <?php if ($errorMessage !== ''): ?>
    <div class="error-msg" style="margin-bottom:14px;">
      <?php echo adviser_endorsement_escape($errorMessage); ?>
    </div>
  <?php endif; ?>

  <div class="endorsement-summary-grid">
    <div class="endorsement-stat-card">
      <div class="endorsement-stat-label">Pending Review</div>
      <div class="endorsement-stat-value"><?php echo $pendingCount; ?></div>
    </div>
    <div class="endorsement-stat-card">
      <div class="endorsement-stat-label">Approved</div>
      <div class="endorsement-stat-value"><?php echo $approvedCount; ?></div>
    </div>
    <div class="endorsement-stat-card">
      <div class="endorsement-stat-label">Rejected</div>
      <div class="endorsement-stat-value"><?php echo $rejectedCount; ?></div>
    </div>
    <div class="endorsement-stat-card">
      <div class="endorsement-stat-label">Total Endorsements</div>
      <div class="endorsement-stat-value"><?php echo $totalThisTerm; ?></div>
    </div>
  </div>

  <div class="tab-nav endorsement-tabs">
    <a class="tab-btn <?php echo $activeTab === 'pending' ? 'active' : ''; ?>" href="<?php echo adviser_endorsement_escape($buildTabUrl('pending')); ?>">Pending (<?php echo $pendingCount; ?>)</a>
    <a class="tab-btn <?php echo $activeTab === 'approved' ? 'active' : ''; ?>" href="<?php echo adviser_endorsement_escape($buildTabUrl('approved')); ?>">Approved</a>
    <a class="tab-btn <?php echo $activeTab === 'all' ? 'active' : ''; ?>" href="<?php echo adviser_endorsement_escape($buildTabUrl('all')); ?>">All Endorsements</a>
  </div>

  <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row" style="margin-bottom:18px;">
    <input type="hidden" name="page" value="adviser/endorsement">
    <input type="hidden" name="tab" value="<?php echo adviser_endorsement_escape($activeTab); ?>">

    <select class="filter-select" name="department">
      <option value="">All Departments</option>
      <?php foreach (($filterOptions['departments'] ?? []) as $deptOption): ?>
        <option value="<?php echo adviser_endorsement_escape($deptOption); ?>" <?php echo ($selected['department'] ?? '') === $deptOption ? 'selected' : ''; ?>><?php echo adviser_endorsement_escape($deptOption); ?></option>
      <?php endforeach; ?>
    </select>

    <input type="text" name="search" class="search-input" style="max-width:260px" placeholder="Search student, company, or position" value="<?php echo adviser_endorsement_escape((string)($selected['search'] ?? '')); ?>">
    <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
  </form>

  <?php if ($activeTab === 'pending'): ?>
    <div class="endorsement-table-card">
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Target Company</th>
              <th>Position</th>
              <th>Submitted Date</th>
              <th>Documents Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($pendingRows)): ?>
              <?php foreach ($pendingRows as $row): ?>
                <?php
                $endorsementId = (int)($row['endorsement_id'] ?? 0);
                $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                $submittedDate = adviser_endorsement_format_date((string)($row['created_at'] ?? ''));
                $docsStatus = (string)($row['documents_status'] ?? 'Partial');
                $docsClass = $docsStatus === 'Complete' ? 'complete' : 'partial';
                ?>
                <tr>
                  <td><?php echo adviser_endorsement_escape($studentName !== '' ? $studentName : 'N/A'); ?></td>
                  <td><?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></td>
                  <td><?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?></td>
                  <td><?php echo adviser_endorsement_escape($submittedDate); ?></td>
                  <td>
                    <span class="docs-chip <?php echo adviser_endorsement_escape($docsClass); ?>">
                      <?php echo adviser_endorsement_escape($docsStatus); ?>
                      (<?php echo (int)($row['documents_uploaded'] ?? 0); ?>/<?php echo (int)($row['documents_total'] ?? 5); ?>)
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-primary btn-sm endorsement-action-btn" type="button" data-open-review-modal="review-modal-<?php echo $endorsementId; ?>">
                      <i class="fas fa-search"></i> Review
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="endorsement-empty">No pending endorsement requests match your current filters.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php foreach ($pendingRows as $row): ?>
      <?php
      $endorsementId = (int)($row['endorsement_id'] ?? 0);
      $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
      $department = trim((string)($row['department'] ?? ''));
      $submittedDate = adviser_endorsement_format_date((string)($row['created_at'] ?? ''));
      $hasEndorsementLetter = (int)($row['has_endorsement_letter'] ?? 0) > 0;
      $hasResume = (int)($row['has_resume'] ?? 0) > 0;
      $hasApplicationForm = (int)($row['has_application_form'] ?? 0) > 0;
      $hasParentConsent = (int)($row['has_parent_consent'] ?? 0) > 0;
      $hasMedicalCertificate = (int)($row['has_medical_certificate'] ?? 0) > 0;
      $endorsementFileUrl = $resolveEndorsementFileUrl((string)($row['endorsement_file'] ?? ''));
      ?>
      <div class="review-modal" id="review-modal-<?php echo $endorsementId; ?>" aria-hidden="true">
        <div class="review-modal-dialog" role="dialog" aria-modal="true">
          <div class="review-modal-head">
            <div>
              <h4>Endorsement: <?php echo adviser_endorsement_escape($studentName !== '' ? $studentName : 'N/A'); ?></h4>
              <div class="review-modal-subtitle">
                <?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?> &middot;
                <?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?>
              </div>
            </div>
            <button class="btn btn-ghost btn-sm review-modal-close" type="button">Close</button>
          </div>

          <div class="review-grid">
            <div class="review-card"><div class="review-label">Student</div><div class="review-value"><?php echo adviser_endorsement_escape($studentName !== '' ? $studentName : 'N/A'); ?></div></div>
            <div class="review-card"><div class="review-label">Department</div><div class="review-value"><?php echo adviser_endorsement_escape($department !== '' ? $department : 'Unassigned'); ?></div></div>
            <div class="review-card"><div class="review-label">Company</div><div class="review-value"><?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></div></div>
            <div class="review-card"><div class="review-label">Position</div><div class="review-value"><?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?></div></div>
            <div class="review-card"><div class="review-label">Submitted Date</div><div class="review-value"><?php echo adviser_endorsement_escape($submittedDate); ?></div></div>
            <div class="review-card"><div class="review-label">Documents Status</div><div class="review-value"><?php echo adviser_endorsement_escape((string)($row['documents_status'] ?? 'Partial')); ?></div></div>
          </div>

          <div style="font-size:.86rem;font-weight:700;margin:12px 0 8px">Submitted Documents</div>
          <ul class="doc-list">
            <li>
              <span>Endorsement Letter</span>
              <span class="<?php echo $hasEndorsementLetter ? 'doc-state-ok' : 'doc-state-missing'; ?>"><?php echo $hasEndorsementLetter ? 'Uploaded' : 'Missing'; ?></span>
            </li>
            <li>
              <span>Resume / CV</span>
              <span class="<?php echo $hasResume ? 'doc-state-ok' : 'doc-state-missing'; ?>"><?php echo $hasResume ? 'Uploaded' : 'Missing'; ?></span>
            </li>
            <li>
              <span>Application Form</span>
              <span class="<?php echo $hasApplicationForm ? 'doc-state-ok' : 'doc-state-missing'; ?>"><?php echo $hasApplicationForm ? 'Uploaded' : 'Missing'; ?></span>
            </li>
            <li>
              <span>Parent Consent</span>
              <span class="<?php echo $hasParentConsent ? 'doc-state-ok' : 'doc-state-missing'; ?>"><?php echo $hasParentConsent ? 'Uploaded' : 'Missing'; ?></span>
            </li>
            <li>
              <span>Medical Certificate</span>
              <span class="<?php echo $hasMedicalCertificate ? 'doc-state-ok' : 'doc-state-missing'; ?>"><?php echo $hasMedicalCertificate ? 'Uploaded' : 'Missing'; ?></span>
            </li>
          </ul>

          <?php if ($endorsementFileUrl !== ''): ?>
            <div style="margin-top:8px;">
              <a class="review-file-link" href="<?php echo adviser_endorsement_escape($endorsementFileUrl); ?>" target="_blank" rel="noopener noreferrer">View uploaded endorsement letter</a>
            </div>
          <?php endif; ?>

          <form method="post" style="margin-top:10px;">
            <input type="hidden" name="endorsement_id" value="<?php echo $endorsementId; ?>">
            <input type="hidden" name="tab" value="pending">
            <input type="hidden" name="department" value="<?php echo adviser_endorsement_escape((string)($selected['department'] ?? '')); ?>">
            <input type="hidden" name="search" value="<?php echo adviser_endorsement_escape((string)($selected['search'] ?? '')); ?>">

            <label class="form-label" style="margin-bottom:6px">Adviser Notes</label>
            <textarea class="review-notes" name="adviser_notes" placeholder="Write notes or required documents..."><?php echo adviser_endorsement_escape((string)($row['notes'] ?? '')); ?></textarea>

            <div class="review-actions">
              <button class="btn btn-sm" type="submit" name="action" value="request_docs">Request More Docs</button>
              <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#B91C1C" type="submit" name="action" value="reject">Reject</button>
              <button class="btn btn-primary btn-sm" type="submit" name="action" value="approve">Approve</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($activeTab === 'approved'): ?>
    <div class="endorsement-table-card">
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Company</th>
              <th>Position</th>
              <th>Date Approved</th>
              <th>Start Date</th>
              <th>Endorsement Letter</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($approvedRows)): ?>
              <?php foreach ($approvedRows as $row): ?>
                <?php
                $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                $approvedDate = adviser_endorsement_format_date((string)($row['reviewed_at'] ?? ''));
                $startDate = adviser_endorsement_format_date((string)($row['start_date'] ?? ''));
                $fileUrl = $resolveEndorsementFileUrl((string)($row['endorsement_file'] ?? ''));
                ?>
                <tr>
                  <td><?php echo adviser_endorsement_escape($studentName !== '' ? $studentName : 'N/A'); ?></td>
                  <td><?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></td>
                  <td><?php echo adviser_endorsement_escape((string)($row['internship_title'] ?? 'N/A')); ?></td>
                  <td><?php echo adviser_endorsement_escape($approvedDate); ?></td>
                  <td><?php echo adviser_endorsement_escape($startDate); ?></td>
                  <td>
                    <?php if ($fileUrl !== ''): ?>
                      <a class="btn-outline btn-sm" href="<?php echo adviser_endorsement_escape($fileUrl); ?>" target="_blank" rel="noopener noreferrer">View</a>
                    <?php else: ?>
                      <span class="text-muted">No file</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="endorsement-empty">No approved endorsements found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($activeTab === 'all'): ?>
    <div class="endorsement-table-card">
      <div class="app-table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Company</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($allRows)): ?>
              <?php foreach ($allRows as $row): ?>
                <?php
                $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                $statusLabel = adviser_endorsement_normalize_status((string)($row['status'] ?? ''));
                $statusClass = adviser_endorsement_status_class((string)($row['status'] ?? ''));
                $statusDate = adviser_endorsement_format_date((string)($row['reviewed_at'] ?? $row['created_at'] ?? ''));
                ?>
                <tr>
                  <td><?php echo adviser_endorsement_escape($studentName !== '' ? $studentName : 'N/A'); ?></td>
                  <td><?php echo adviser_endorsement_escape((string)($row['company_name'] ?? 'N/A')); ?></td>
                  <td><span class="status-pill <?php echo adviser_endorsement_escape($statusClass); ?>"><?php echo adviser_endorsement_escape($statusLabel); ?></span></td>
                  <td><?php echo adviser_endorsement_escape($statusDate); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="endorsement-empty">No endorsements found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<script>
  (function () {
    var openButtons = document.querySelectorAll('[data-open-review-modal]');

    function closeModal(modal) {
      if (!modal) {
        return;
      }
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    function openModal(modal) {
      if (!modal) {
        return;
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    openButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-open-review-modal');
        if (!targetId) {
          return;
        }
        openModal(document.getElementById(targetId));
      });
    });

    document.querySelectorAll('.review-modal').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });

      modal.querySelectorAll('.review-modal-close').forEach(function (closeBtn) {
        closeBtn.addEventListener('click', function () {
          closeModal(modal);
        });
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }
      var openedModal = document.querySelector('.review-modal.is-open');
      if (openedModal) {
        closeModal(openedModal);
      }
    });
  })();
</script>
