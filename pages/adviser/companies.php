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
        $pageData = $pageData;
    }
}

$selected = $pageData['selected'];
$rows = $pageData['rows'];

if (($adviserId > 0) && (($_GET['export'] ?? '') === 'csv')) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="adviser-company-verification-queue.csv"');

    $output = fopen('php://output', 'w');
    if ($output !== false) {
        fputcsv($output, ['Company', 'Industry', 'Submitted', 'Documents', 'Risk', 'Suggested Action']);
        foreach ($rows as $row) {
            $documents = adviser_companies_documents_meta($row);
            $risk = adviser_companies_risk_meta($row);
            $actionMeta = adviser_companies_action_meta($row);

            fputcsv($output, [
                trim((string)($row['company_name'] ?? 'Company')),
                trim((string)($row['industry'] ?? 'Unspecified')),
                adviser_companies_format_date((string)($row['created_at'] ?? '')),
                $documents['label'],
                $risk['label'],
                $actionMeta['label'],
            ]);
        }
        fclose($output);
    }
    exit;
}
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

  .adviser-companies-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
  }

  .adviser-companies-search-input {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 0.86rem;
    color: var(--text);
    background: #fff;
    outline: none;
  }

  .adviser-companies-search-input:focus {
    border-color: #111;
  }

  .adviser-companies-search-button {
    min-height: 40px;
    padding: 9px 16px;
    border: 1px solid #111;
    border-radius: 10px;
    background: #111;
    color: #fff;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
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
    background: #fafafa;
    color: #6b7280;
    font-size: 0.82rem;
  }

  .adviser-companies-error {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
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

    .adviser-companies-search-button {
      width: 100%;
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
  <?php if ($errorMessage !== ''): ?>
    <div class="adviser-companies-error">
      <?php echo adviser_companies_escape($errorMessage); ?>
    </div>
  <?php endif; ?>

  <section class="adviser-companies-panel">
    <div class="adviser-companies-panel-head">
      <div>
        <h2 class="adviser-companies-title">Company Verification Queue</h2>
        <p class="adviser-companies-subtitle">View partner company details and current MOA progress.</p>
      </div>

      <a
        class="adviser-companies-export"
        href="<?php echo $baseUrl; ?>/layout.php?<?php echo adviser_companies_escape(http_build_query([
            'page' => 'adviser/companies',
            'industry' => $selected['industry'] ?? '',
            'status' => $selected['status'] ?? '',
            'search' => $selected['search'] ?? '',
            'export' => 'csv',
        ])); ?>"
      >
        <i class="fas fa-download"></i>
        Export Report
      </a>
    </div>

    <form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="adviser-companies-filters">
      <input type="hidden" name="page" value="adviser/companies">
      <input type="hidden" name="industry" value="<?php echo adviser_companies_escape((string)($selected['industry'] ?? '')); ?>">
      <input type="hidden" name="status" value="<?php echo adviser_companies_escape((string)($selected['status'] ?? '')); ?>">
      <input
        type="text"
        name="search"
        class="adviser-companies-search-input"
        placeholder="Search company name, email, or industry"
        value="<?php echo adviser_companies_escape((string)($selected['search'] ?? '')); ?>"
      >
      <button type="submit" class="adviser-companies-search-button">Search</button>
    </form>

    <?php if (!empty($rows)): ?>
      <div class="adviser-companies-table-wrap">
        <table class="adviser-companies-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Industry</th>
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
              $createdAtLabel = adviser_companies_format_date((string)($row['created_at'] ?? ''));
              $documentsMeta = adviser_companies_documents_meta($row);
              $modalId = 'company-review-modal-' . $index;
              ?>
              <tr>
                <td>
                  <div class="adviser-companies-company">
                    <span class="adviser-companies-avatar" style="background:<?php echo adviser_companies_escape(adviser_companies_gradient((int)$index)); ?>;">
                      <?php echo adviser_companies_escape(adviser_companies_initial($companyName)); ?>
                    </span>
                    <div>
                      <button class="adviser-companies-company-link" type="button" data-open-company-modal="<?php echo adviser_companies_escape($modalId); ?>">
                        <span class="adviser-companies-company-name"><?php echo adviser_companies_escape($companyName); ?></span>
                      </button>
                      <p class="adviser-companies-meta"><?php echo adviser_companies_escape((string)($row['current_interns'] ?? 0)); ?> active interns</p>
                    </div>
                  </div>
                </td>
                <td><?php echo adviser_companies_escape($industry); ?></td>
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
                <div class="company-modal-subtitle">MOA preview for this company (sample data).</div>
              </div>
              <button class="company-modal-close" type="button" data-close-company-modal aria-label="Close">Close</button>
            </div>

            <div class="company-modal-grid">
              <div class="company-modal-item">
                <div class="company-modal-label">Industry</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($industry); ?></div>
              </div>
              <div class="company-modal-item">
                <div class="company-modal-label">Current Status</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($statusLabel); ?></div>
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
                <div class="company-modal-label">Submitted</div>
                <div class="company-modal-value"><?php echo adviser_companies_escape($createdAtLabel); ?></div>
              </div>
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
