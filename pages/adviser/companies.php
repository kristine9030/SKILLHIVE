<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/companies/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$errorMessage = '';

$currentFilters = [
    'industry' => trim((string)($_GET['industry'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
];

if (!function_exists('adviser_companies_sample_moa_meta')) {
  function adviser_companies_sample_moa_meta(int $employerId, string $createdAt = ''): array
  {
    $safeId = max(1, $employerId);
    $statusSet = ['Draft', 'Under Review', 'Ready for Signature'];
    $status = $statusSet[$safeId % count($statusSet)];

    $baseDate = strtotime($createdAt);
    if ($baseDate === false) {
      $baseDate = time();
    }

    $preparedDate = strtotime('+7 days', $baseDate);
    $targetSignDate = strtotime('+14 days', $baseDate);

    return [
      'status' => $status,
      'reference' => 'MOA-2026-' . str_pad((string)$safeId, 4, '0', STR_PAD_LEFT),
      'prepared_date' => date('M j, Y', $preparedDate),
      'target_sign_date' => date('M j, Y', $targetSignDate),
      'document_name' => 'sample_moa_company_' . $safeId . '.pdf',
      'notes' => 'Sample MOA data only. Real MOA upload will replace this once integrated.',
    ];
  }
}

$pageData = [
    'selected' => ['industry' => '', 'status' => '', 'search' => ''],
    'filter_options' => ['industries' => [], 'statuses' => []],
    'rows' => [],
];

if ($adviserId > 0) {
    try {
        $pageData = getAdviserCompaniesPageData($pdo, $adviserId, $currentFilters);
    } catch (Throwable $e) {
    $errorMessage = 'Unable to load partner companies right now. Please try again.';
    }
}

$selected = $pageData['selected'];
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
$moduleSettings = is_array($_SESSION['adviser_module_settings'] ?? null) ? $_SESSION['adviser_module_settings'] : [];
$showCompaniesBanner = array_key_exists('show_companies_banner', $moduleSettings)
  ? (bool)$moduleSettings['show_companies_banner']
  : true;

?>

<style>
  .adviser-companies-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
    color: var(--text);
    font-size: var(--font-size-body);
  }

  .adviser-companies-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--card-shadow);
    padding: 22px;
  }

  .adviser-companies-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  .adviser-companies-export-actions {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .adviser-companies-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
  }

  .adviser-companies-subtitle {
    margin: 6px 0 0;
    font-size: 0.8rem;
    color: var(--text3);
  }

  .adviser-companies-export {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 36px;
    padding: 8px 18px;
    border-radius: 999px;
    background: #111;
    color: #fff;
    font-size: 0.84rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
  }

  .adviser-companies-export:hover {
    color: #fff;
    transform: translateY(-1px);
  }

  .adviser-companies-export.secondary {
    background: #fff;
    color: #111;
    border: 1px solid var(--border);
  }

  .adviser-companies-export.secondary:hover {
    color: #111;
    border-color: #111;
  }

  .adviser-companies-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }

  .adviser-companies-search-control {
    position: relative;
    flex: 1 1 280px;
    min-width: 240px;
  }

  .adviser-companies-search-control i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text3);
    font-size: 0.82rem;
    pointer-events: none;
  }

  .adviser-companies-search-input {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 9px 12px 9px 36px;
    font-size: 0.86rem;
    color: var(--text);
    background: #fff;
    outline: none;
  }

  .adviser-companies-filter-select {
    min-width: 180px;
    min-height: 40px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0 38px 0 12px;
    font-size: 0.86rem;
    color: var(--text);
    background-color: #fff;
    outline: none;
    appearance: none;
    background-image:
      linear-gradient(45deg, transparent 50%, #111 50%),
      linear-gradient(135deg, #111 50%, transparent 50%);
    background-position:
      calc(100% - 18px) calc(50% - 3px),
      calc(100% - 12px) calc(50% - 3px);
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
  }

  .adviser-companies-search-input:focus,
  .adviser-companies-filter-select:focus {
    border-color: #111;
  }

  .adviser-companies-clear-filter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    color: var(--text2);
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
  }

  .adviser-companies-clear-filter:hover {
    color: #111;
    border-color: #111;
  }

  .adviser-companies-no-results td {
    text-align: center;
    color: var(--text3);
    font-size: 0.82rem;
    padding: 16px 14px;
    border-bottom: 0;
  }

  .adviser-companies-table-wrap {
    overflow-x: auto;
  }

  .adviser-companies-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .adviser-companies-table th {
    padding: 10px 14px 12px;
    text-align: left;
    font-size: 0.75rem;
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text3);
    font-weight: 600;
    border-bottom: 1px solid var(--border);
  }

  .adviser-companies-table td {
    padding: 14px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border);
    font-size: 0.86rem;
    color: var(--text);
  }

  .adviser-companies-table tbody tr:last-child td {
    border-bottom: 0;
  }

  .adviser-companies-company {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 280px;
  }

  .adviser-companies-avatar {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.86rem;
    font-weight: 700;
    flex-shrink: 0;
  }

  .adviser-companies-company-name {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text);
  }

  .adviser-companies-company-link {
    display: inline-flex;
    padding: 0;
    background: transparent;
    border: 0;
    text-align: left;
    cursor: pointer;
  }

  .adviser-companies-company-link:hover .adviser-companies-company-name {
    color: var(--text2);
  }

  .adviser-companies-meta {
    margin: 4px 0 0;
    font-size: 0.76rem;
    color: var(--text3);
  }

  .adviser-companies-status-cell {
    min-width: 168px;
  }

  .adviser-companies-status-detail {
    margin-top: 6px;
    max-width: 230px;
    font-size: 0.72rem;
    line-height: 1.35;
    color: var(--text3);
  }

  .adviser-companies-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1;
  }

  .adviser-companies-badge.is-success {
    background: #e1f8ee;
    color: #10a56f;
  }

  .adviser-companies-badge.is-warning {
    background: #fff2dd;
    color: #ef9a17;
  }

  .adviser-companies-badge.is-danger {
    background: #ffe8e6;
    color: #ff4d4f;
  }

  .adviser-companies-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    padding: 6px 16px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: border-color .18s ease, color .18s ease, background .18s ease, transform .18s ease;
  }

  .adviser-companies-action:hover {
    border-color: #111;
    color: #111;
    transform: translateY(-1px);
  }

  .adviser-companies-action.primary {
    background: #111;
    border-color: #111;
    color: #fff;
  }

  .adviser-companies-action.secondary {
    background: #fff;
    color: #4b5563;
  }

  .adviser-companies-action.danger {
    border-color: #ff4d4f;
    color: #ff4d4f;
    background: #fff;
  }

  .adviser-companies-empty {
    padding: 22px;
    border: 1px dashed var(--border);
    border-radius: 14px;
    background: #ffffff;
    color: #6b7280;
    font-size: 0.82rem;
  }

  .adviser-companies-error {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #12b3ac;
    font-size: 0.82rem;
    font-weight: 500;
  }

  .company-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .28);
    backdrop-filter: blur(3px);
    z-index: 1200;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .24s ease, visibility .24s ease;
  }

  .company-modal.is-open {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
  }

  .company-modal-dialog {
    width: min(620px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: 0 18px 40px rgba(15, 23, 42, .22);
    padding: 18px;
    transform: translateY(8px) scale(.986);
    opacity: 0;
    transition: transform .26s cubic-bezier(.2,.8,.2,1), opacity .22s ease;
  }

  .company-modal.is-open .company-modal-dialog {
    transform: translateY(0) scale(1);
    opacity: 1;
  }

  .company-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
  }

  .company-modal-title {
    margin: 0;
    font-size: 1.04rem;
    font-weight: 800;
    color: var(--text);
  }

  .company-modal-subtitle {
    margin-top: 5px;
    font-size: .84rem;
    color: var(--text3);
  }

  .company-modal-close {
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    font-size: .82rem;
    line-height: 1;
    cursor: pointer;
    padding: 8px 10px;
    border-radius: 10px;
    transition: border-color .18s ease, color .18s ease;
  }

  .company-modal-close:hover {
    border-color: #111;
    color: #111;
  }

  .company-modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
  }

  .company-modal-item {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 11px;
    background: #fff;
  }

  .company-modal-item.full {
    grid-column: 1 / -1;
  }

  .company-modal-label {
    font-size: .74rem;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 3px;
  }

  .company-modal-value {
    font-size: .88rem;
    color: var(--text2);
    font-weight: 700;
    line-height: 1.35;
    word-break: break-word;
  }

  .company-modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 14px;
  }

  .company-modal-section-title {
    margin: 4px 0 10px;
    font-size: .8rem;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 800;
  }

  .company-student-list {
    display: grid;
    gap: 8px;
  }

  .company-student-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 11px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
  }

  .company-student-avatar {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #111;
    color: #fff;
    font-size: .78rem;
    font-weight: 800;
  }

  .company-student-name {
    margin: 0;
    font-size: .86rem;
    font-weight: 800;
    color: var(--text);
  }

  .company-student-meta {
    margin: 3px 0 0;
    font-size: .75rem;
    color: var(--text3);
    line-height: 1.4;
  }

  .company-student-empty {
    padding: 12px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--text3);
    font-size: .8rem;
  }

  .company-modal-btn {
    flex: 1;
    min-height: 40px;
    border-radius: 999px;
    font-size: .92rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text2);
    cursor: pointer;
    transition: border-color .18s ease, color .18s ease, transform .18s ease;
  }

  .company-modal-btn:hover {
    border-color: #111;
    color: #111;
    transform: translateY(-1px);
  }

  @media (max-width: 900px) {
    .adviser-companies-panel-head {
      flex-direction: column;
      align-items: stretch;
    }

    .adviser-companies-export {
      width: 100%;
    }

    .adviser-companies-filters {
      flex-direction: column;
      align-items: stretch;
    }
  }

  @media (max-width: 700px) {
    .adviser-companies-panel {
      padding: 18px;
      border-radius: 16px;
    }

    .adviser-companies-table th,
    .adviser-companies-table td {
      padding-left: 10px;
      padding-right: 10px;
    }

    .company-modal-grid {
      grid-template-columns: 1fr;
    }

    .company-modal-actions {
      flex-direction: column;
    }
  }
</style>

<div class="adviser-companies-page">
  <?php if ($showCompaniesBanner): ?>
    <div style="background:linear-gradient(90deg, #050505 0%, #12b3ac 40%, rgba(0, 0, 0, 0.38) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:20px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(0, 0, 0, 0.44);">
      <div style="z-index:2;flex:1;">
        <h2 style="font-size:1.8rem;font-weight:900;margin:0 0 12px 0;line-height:1.2;color:white;">Partner Companies</h2>
        <p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Review partner company profiles, track MOA progress, and keep placement partners aligned with student internship requirements.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage !== ''): ?>
    <div class="adviser-companies-error">
      <?php echo adviser_companies_escape($errorMessage); ?>
    </div>
  <?php endif; ?>

  <section class="adviser-companies-panel">
    <div class="adviser-companies-panel-head">
      <div>
        <h2 class="adviser-companies-title">Company Verification Queue</h2>
        <p class="adviser-companies-subtitle">View company contacts, assigned BSU students, and internship accepting status.</p>
      </div>

      <div class="adviser-companies-export-actions">
        <a
          id="adviserCompaniesExportDocLink"
          data-export-link="companies"
          class="adviser-companies-export"
          href="<?php echo $baseUrl; ?>/pages/adviser/companies/export.php?<?php echo adviser_companies_escape(http_build_query([
              'format' => 'doc',
              'industry' => $selected['industry'] ?? '',
              'status' => $selected['status'] ?? '',
              'search' => $selected['search'] ?? '',
          ])); ?>"
        >
          <i class="fas fa-file-lines"></i>
          Export Document
        </a>

        <a
          id="adviserCompaniesExportCsvLink"
          data-export-link="companies"
          class="adviser-companies-export secondary"
          href="<?php echo $baseUrl; ?>/pages/adviser/companies/export.php?<?php echo adviser_companies_escape(http_build_query([
              'format' => 'csv',
              'industry' => $selected['industry'] ?? '',
              'status' => $selected['status'] ?? '',
              'search' => $selected['search'] ?? '',
          ])); ?>"
        >
          <i class="fas fa-file-csv"></i>
          Export CSV
        </a>
      </div>
    </div>

    <form class="adviser-companies-filters" method="get" action="<?php echo $baseUrl; ?>/layout.php" role="search" aria-label="Filter companies">
      <input type="hidden" name="page" value="adviser/companies">

      <label class="adviser-companies-search-control" aria-label="Search companies">
        <i class="fas fa-search"></i>
        <input
          id="adviserCompaniesSearchInput"
          name="search"
          type="text"
          class="adviser-companies-search-input"
          placeholder="Search company name, email, or industry"
          value="<?php echo adviser_companies_escape((string)($selected['search'] ?? '')); ?>"
        >
      </label>

      <select id="adviserCompaniesIndustryFilter" class="adviser-companies-filter-select" name="industry" aria-label="Filter by industry">
        <option value="">All Industries</option>
        <?php foreach (($filterOptions['industries'] ?? []) as $industryOption): ?>
          <option value="<?php echo adviser_companies_escape($industryOption); ?>" <?php echo ($selected['industry'] ?? '') === $industryOption ? 'selected' : ''; ?>>
            <?php echo adviser_companies_escape($industryOption); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select id="adviserCompaniesStatusFilter" class="adviser-companies-filter-select" name="status" aria-label="Filter by verification status">
        <option value="">All Verification Status</option>
        <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
          <option value="<?php echo adviser_companies_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>>
            <?php echo adviser_companies_escape(adviser_companies_verification_label($statusOption)); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if (($selected['search'] ?? '') !== '' || ($selected['industry'] ?? '') !== '' || ($selected['status'] ?? '') !== ''): ?>
        <a class="adviser-companies-clear-filter" href="<?php echo $baseUrl; ?>/layout.php?page=adviser/companies">
          <i class="fas fa-rotate-left"></i>
          Clear
        </a>
      <?php endif; ?>
    </form>

    <?php if (!empty($rows)): ?>
      <div class="adviser-companies-table-wrap">
        <table class="adviser-companies-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Contact Person</th>
              <th>Industry</th>
              <th>Verification</th>
              <th>Students</th>
              <th>Submitted</th>
              <th>Documents</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $index => $row): ?>
              <?php
              $companyName = trim((string)($row['company_name'] ?? 'Company'));
              $industry = trim((string)($row['industry'] ?? '')) ?: 'Unspecified';
              $verificationStatus = adviser_companies_verification_label((string)($row['verification_status'] ?? ''));
              $contactPerson = adviser_companies_contact_person_label($row);
              $acceptingMeta = adviser_companies_accepting_status_meta($row);
              $students = is_array($row['students'] ?? null) ? $row['students'] : [];
              $createdAtLabel = adviser_companies_format_date((string)($row['created_at'] ?? ''));
              $documentsMeta = adviser_companies_documents_meta($row);
              $studentSearchParts = [];
              foreach ($students as $student) {
                  $studentSearchParts[] = (string)($student['student_name'] ?? '');
                  $studentSearchParts[] = (string)($student['student_number'] ?? '');
                  $studentSearchParts[] = (string)($student['internship_title'] ?? '');
              }
              $searchRow = strtolower(trim((string)preg_replace(
                  '/\s+/',
                  ' ',
                  implode(' ', [
                      $companyName,
                      $industry,
                      $contactPerson,
                      (string)($acceptingMeta['label'] ?? ''),
                      (string)($acceptingMeta['detail'] ?? ''),
                      (string)($row['email'] ?? ''),
                      (string)($row['website_url'] ?? ''),
                      (string)($row['company_address'] ?? ''),
                      (string)($row['contact_number'] ?? ''),
                      implode(' ', $studentSearchParts),
                  ])
              )));
              $modalId = 'company-review-modal-' . $index;
              ?>
              <tr data-search-row="<?php echo adviser_companies_escape($searchRow); ?>" data-industry="<?php echo adviser_companies_escape($industry); ?>" data-verification-status="<?php echo adviser_companies_escape($verificationStatus); ?>">
                <td>
                  <div class="adviser-companies-company">
                    <span class="adviser-companies-avatar" style="background:<?php echo adviser_companies_escape(adviser_companies_gradient((int)$index)); ?>;">
                      <?php echo adviser_companies_escape(adviser_companies_initial($companyName)); ?>
                    </span>
                    <div>
                      <button class="adviser-companies-company-link" type="button" data-open-company-modal="<?php echo adviser_companies_escape($modalId); ?>">
                        <span class="adviser-companies-company-name"><?php echo adviser_companies_escape($companyName); ?></span>
                      </button>
                      <p class="adviser-companies-meta"><?php echo (int)($row['current_interns'] ?? 0); ?> current interns</p>
                    </div>
                  </div>
                </td>
                <td><?php echo adviser_companies_escape($contactPerson); ?></td>
                <td><?php echo adviser_companies_escape($industry); ?></td>
                <td class="adviser-companies-status-cell">
                  <span class="adviser-companies-badge <?php echo adviser_companies_escape((string)$acceptingMeta['class']); ?>">
                    <?php echo adviser_companies_escape((string)$acceptingMeta['label']); ?>
                  </span>
                  <div class="adviser-companies-status-detail"><?php echo adviser_companies_escape((string)$acceptingMeta['detail']); ?></div>
                </td>
                <td><?php echo count($students); ?> BSU student<?php echo count($students) === 1 ? '' : 's'; ?></td>
                <td><?php echo adviser_companies_escape($createdAtLabel); ?></td>
                <td>
                  <span class="adviser-companies-badge <?php echo adviser_companies_escape($documentsMeta['class']); ?>">
                    <?php echo adviser_companies_escape($documentsMeta['label']); ?>
                  </span>
                </td>
                <td>
                  <button class="adviser-companies-action secondary" type="button" data-open-company-modal="<?php echo adviser_companies_escape($modalId); ?>">
                    View
                  </button>
                </td>
              </tr>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php foreach ($rows as $index => $row): ?>
        <?php
        $companyName = trim((string)($row['company_name'] ?? 'Company'));
        $industry = trim((string)($row['industry'] ?? '')) ?: 'Unspecified';
        $contactPerson = adviser_companies_contact_person_label($row);
        $acceptingMeta = adviser_companies_accepting_status_meta($row);
        $students = is_array($row['students'] ?? null) ? $row['students'] : [];
        $createdAtLabel = adviser_companies_format_date((string)($row['created_at'] ?? ''));
        $documentsMeta = adviser_companies_documents_meta($row);
        $riskMeta = adviser_companies_risk_meta($row);
        $contactEmail = trim((string)($row['email'] ?? ''));
        $ratingText = adviser_companies_rating_text($row['avg_rating'] ?? null);
        $website = trim((string)($row['website_url'] ?? ''));
        $location = trim((string)($row['company_address'] ?? ''));
        $contactNumber = trim((string)($row['contact_number'] ?? ''));
        $statusLabel = adviser_companies_verification_label((string)($row['verification_status'] ?? 'Pending'));
        $moaMeta = adviser_companies_sample_moa_meta((int)($row['employer_id'] ?? 0), (string)($row['created_at'] ?? ''));
        $modalId = 'company-review-modal-' . $index;
        ?>
        <div class="company-modal" id="<?php echo adviser_companies_escape($modalId); ?>" aria-hidden="true">
          <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo adviser_companies_escape($modalId); ?>-title">
            <div class="company-modal-head">
              <div>
                <h3 class="company-modal-title" id="<?php echo adviser_companies_escape($modalId); ?>-title"><?php echo adviser_companies_escape($companyName); ?></h3>
                <div class="company-modal-subtitle">Company contacts, BSU internship status, and assigned students.</div>
              </div>
              <button class="company-modal-close" type="button" data-close-company-modal aria-label="Close">Close</button>
            </div>

            <div class="company-modal-grid">
              <div class="company-modal-item">
                <div class="company-modal-label">Industry</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($industry); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Verification Status</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($statusLabel); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Contact Person</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($contactPerson); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Verification Summary</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$acceptingMeta['label']); ?></div>
              </div>
              <div class="company-modal-item full">
                <div class="company-modal-label">Status Detail</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$acceptingMeta['detail']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Documents</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($documentsMeta['label']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Risk Level</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($riskMeta['label']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">MOA Status</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['status']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">MOA Reference</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['reference']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">MOA Prepared Date</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['prepared_date']); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Target Sign Date</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['target_sign_date']); ?></div>
              </div>
              <div class="company-modal-item full">
                <div class="company-modal-label">MOA Document</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['document_name']); ?></div>
              </div>
              <div class="company-modal-item full">
                <div class="company-modal-label">MOA Note</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape((string)$moaMeta['notes']); ?></div>
              </div>
              <div class="company-modal-item full">
                <div class="company-modal-label">Address</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($location !== '' ? $location : 'No address provided'); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Website</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($website !== '' ? $website : 'N/A'); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Contact Number</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($contactNumber !== '' ? $contactNumber : 'N/A'); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Email</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($contactEmail !== '' ? $contactEmail : 'N/A'); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Average Rating</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($ratingText); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Current Interns</div>
                <div class="company-modal-value"><?php echo (int)($row['current_interns'] ?? 0); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Open Postings</div>
                <div class="company-modal-value"><?php echo (int)($row['open_postings'] ?? 0); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Listed Slots</div>
                <div class="company-modal-value"><?php echo (int)($row['open_slots'] ?? 0); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Assigned Students</div>
                <div class="company-modal-value"><?php echo count($students); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Submitted</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($createdAtLabel); ?></div>
              </div>
            </div>

            <div>
              <div class="company-modal-section-title">Students in This Company</div>
              <?php if (!empty($students)): ?>
                <div class="company-student-list">
                  <?php foreach ($students as $studentIndex => $student): ?>
                    <?php
                    $studentName = trim((string)($student['student_name'] ?? 'Student')) ?: 'Student';
                    $studentNumber = trim((string)($student['student_number'] ?? ''));
                    $studentProgram = trim((string)($student['program'] ?? ''));
                    $studentYear = (int)($student['year_level'] ?? 0);
                    $internshipTitle = trim((string)($student['internship_title'] ?? 'Internship'));
                    $placementStatus = trim((string)($student['completion_status'] ?? 'Assigned'));
                    $hoursText = adviser_companies_student_hours_text($student);
                    $studentMetaParts = array_filter([
                        $studentNumber !== '' ? $studentNumber : null,
                        $studentProgram !== '' ? $studentProgram : null,
                        $studentYear > 0 ? ('Year ' . $studentYear) : null,
                    ]);
                    ?>
                    <div class="company-student-row">
                      <span class="company-student-avatar" style="background:<?php echo adviser_companies_escape(adviser_companies_gradient((int)$studentIndex)); ?>;">
                        <?php echo adviser_companies_escape(adviser_companies_initial($studentName)); ?>
                      </span>
                      <div>
                        <p class="company-student-name"><?php echo adviser_companies_escape($studentName); ?></p>
                        <p class="company-student-meta"><?php echo adviser_companies_escape(implode(' | ', $studentMetaParts)); ?></p>
                        <p class="company-student-meta"><?php echo adviser_companies_escape($internshipTitle); ?> | <?php echo adviser_companies_escape($placementStatus); ?> | <?php echo adviser_companies_escape($hoursText); ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="company-student-empty">No assigned BSU students found for this company.</div>
              <?php endif; ?>
            </div>

            <div class="company-modal-actions">
              <button class="company-modal-btn" type="button" data-close-company-modal>
                <i class="fas fa-times"></i> Close
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="adviser-companies-empty">
        No companies found for the current adviser queue yet.
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
  (function () {
    var filterForm = document.querySelector('.adviser-companies-filters');
    var searchInput = document.getElementById('adviserCompaniesSearchInput');
    var industryFilter = document.getElementById('adviserCompaniesIndustryFilter');
    var statusFilter = document.getElementById('adviserCompaniesStatusFilter');
    var exportLinks = Array.prototype.slice.call(document.querySelectorAll('[data-export-link="companies"]'));
    var tableBody = document.querySelector('.adviser-companies-table tbody');
    var searchableRows = tableBody
      ? Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-search-row]'))
      : [];

    function normalizeSearchText(value) {
      return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function getNoResultRow() {
      if (!tableBody) {
        return null;
      }
      return tableBody.querySelector('tr.adviser-companies-no-results');
    }

    function renderNoResultRow() {
      if (!tableBody || getNoResultRow()) {
        return;
      }
      var row = document.createElement('tr');
      row.className = 'adviser-companies-no-results';
      row.innerHTML = '<td colspan="8">No matching companies found.</td>';
      tableBody.appendChild(row);
    }

    function removeNoResultRow() {
      var row = getNoResultRow();
      if (row && row.parentNode) {
        row.parentNode.removeChild(row);
      }
    }

    function filterCompanyRows() {
      if (!searchInput || !tableBody || !searchableRows.length) {
        return;
      }

      var query = normalizeSearchText(searchInput ? searchInput.value : '');
      var selectedIndustry = normalizeSearchText(industryFilter ? industryFilter.value : '');
      var selectedStatus = normalizeSearchText(statusFilter ? statusFilter.value : '');
      var visibleCount = 0;

      searchableRows.forEach(function (row) {
        var haystack = normalizeSearchText(row.getAttribute('data-search-row'));
        var rowIndustry = normalizeSearchText(row.getAttribute('data-industry'));
        var rowStatus = normalizeSearchText(row.getAttribute('data-verification-status'));
        var matchesSearch = query === '' || haystack.indexOf(query) !== -1;
        var matchesIndustry = selectedIndustry === '' || rowIndustry === selectedIndustry;
        var matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;
        var isMatch = matchesSearch && matchesIndustry && matchesStatus;
        row.style.display = isMatch ? '' : 'none';
        if (isMatch) {
          visibleCount += 1;
        }
      });

      if (visibleCount === 0) {
        renderNoResultRow();
      } else {
        removeNoResultRow();
      }
    }

    if (filterForm) {
      filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        filterCompanyRows();
        syncExportLinksWithFilters();
      });
    }

    if (searchInput && searchableRows.length) {
      searchInput.addEventListener('input', filterCompanyRows);
    }

    [industryFilter, statusFilter].forEach(function (filter) {
      if (!filter || !searchableRows.length) {
        return;
      }
      filter.addEventListener('change', filterCompanyRows);
    });

    filterCompanyRows();

    function syncExportLinksWithFilters() {
      if (!exportLinks.length) {
        return;
      }

      var filters = {
        search: String(searchInput ? searchInput.value : '').replace(/\s+/g, ' ').trim(),
        industry: String(industryFilter ? industryFilter.value : '').replace(/\s+/g, ' ').trim(),
        status: String(statusFilter ? statusFilter.value : '').replace(/\s+/g, ' ').trim()
      };

      exportLinks.forEach(function (link) {
        try {
          var exportUrl = new URL(link.getAttribute('href'), window.location.origin);

          Object.keys(filters).forEach(function (key) {
            if (filters[key]) {
              exportUrl.searchParams.set(key, filters[key]);
            } else {
              exportUrl.searchParams.delete(key);
            }
          });

          link.setAttribute('href', exportUrl.pathname + exportUrl.search);
        } catch (error) {
          // If URL parsing fails, keep the existing export href.
        }
      });
    }

    syncExportLinksWithFilters();

    [searchInput, industryFilter, statusFilter].forEach(function (control) {
      if (!control) {
        return;
      }
      control.addEventListener('input', syncExportLinksWithFilters);
      control.addEventListener('change', syncExportLinksWithFilters);
    });

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

    document.querySelectorAll('[data-open-company-modal]').forEach(function (button) {
      button.addEventListener('click', function () {
        var modalId = button.getAttribute('data-open-company-modal');
        if (!modalId) {
          return;
        }
        openModal(document.getElementById(modalId));
      });
    });

    document.querySelectorAll('.company-modal').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });

      modal.querySelectorAll('[data-close-company-modal]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
          closeModal(modal);
        });
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }
      var openModalElement = document.querySelector('.company-modal.is-open');
      if (openModalElement) {
        closeModal(openModalElement);
      }
    });
  })();
</script>
