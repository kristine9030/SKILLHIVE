<?php
require_once __DIR__ . '/../../backend/db_connect.php';
require_once __DIR__ . '/companies/data.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$currentFilters = [
  'industry' => trim((string)($_GET['industry'] ?? '')),
  'status' => trim((string)($_GET['status'] ?? '')),
  'search' => trim((string)($_GET['search'] ?? '')),
];

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
$filterOptions = $pageData['filter_options'];
$rows = $pageData['rows'];
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Partner Companies</h2>
    <p class="page-subtitle">Manage and verify partner companies for internship placements.</p>
  </div>
</div>

<!-- Filter Row -->
<form method="get" action="<?php echo $baseUrl; ?>/layout.php" class="filter-row" style="margin-bottom:20px">
  <input type="hidden" name="page" value="adviser/companies">

  <select class="filter-select" name="industry">
    <option value="">All Industries</option>
    <?php foreach (($filterOptions['industries'] ?? []) as $industryOption): ?>
      <option value="<?php echo adviser_companies_escape($industryOption); ?>" <?php echo ($selected['industry'] ?? '') === $industryOption ? 'selected' : ''; ?>><?php echo adviser_companies_escape($industryOption); ?></option>
    <?php endforeach; ?>
  </select>

  <select class="filter-select" name="status">
    <option value="">All Status</option>
    <?php foreach (($filterOptions['statuses'] ?? []) as $statusOption): ?>
      <option value="<?php echo adviser_companies_escape($statusOption); ?>" <?php echo ($selected['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo adviser_companies_escape($statusOption); ?></option>
    <?php endforeach; ?>
  </select>

  <div style="flex:1"></div>
  <input type="text" name="search" placeholder="Search companies..." class="search-input" style="max-width:260px" value="<?php echo adviser_companies_escape($selected['search'] ?? ''); ?>">
  <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="company-grid">
  <?php if (!empty($rows)): ?>
    <?php foreach ($rows as $index => $row): ?>
      <?php
      $companyName = trim((string)($row['company_name'] ?? 'Company'));
      $industry = trim((string)($row['industry'] ?? ''));
      $location = trim((string)($row['company_address'] ?? ''));
      $statusLabel = adviser_companies_verification_label((string)($row['verification_status'] ?? 'Pending'));
      $badgeClass = adviser_companies_verification_badge_class((string)($row['verification_status'] ?? 'Pending'));
      $website = trim((string)($row['website_url'] ?? ''));
      $contactEmail = trim((string)($row['email'] ?? ''));
      $ratingText = adviser_companies_rating_text($row['avg_rating'] ?? null);
      ?>
      <div class="company-card">
        <div class="company-card-header" style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
          <div class="avatar-placeholder" style="width:50px;height:50px;border-radius:12px;background:<?php echo adviser_companies_escape(adviser_companies_gradient((int)$index)); ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem"><?php echo adviser_companies_escape(adviser_companies_initial($companyName)); ?></div>
          <div>
            <h4 style="margin:0;font-size:1rem"><?php echo adviser_companies_escape($companyName); ?></h4>
            <span style="font-size:.78rem;color:var(--text-light)"><?php echo adviser_companies_escape($industry !== '' ? $industry : 'Unspecified'); ?> &middot; <?php echo adviser_companies_escape($location !== '' ? $location : 'No address provided'); ?></span>
          </div>
          <span class="status-badge <?php echo adviser_companies_escape($badgeClass); ?>" style="margin-left:auto"><?php echo adviser_companies_escape($statusLabel); ?></span>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:.83rem;color:var(--text-light);margin-bottom:12px">
          <div><i class="fas fa-users" style="width:18px;color:var(--text-lighter)"></i> <?php echo (int)($row['current_interns'] ?? 0); ?> current interns</div>
          <div><i class="fas fa-star" style="width:18px;color:#F59E0B"></i> <?php echo adviser_companies_escape($ratingText); ?> average rating</div>
          <div><i class="fas fa-globe" style="width:18px;color:var(--text-lighter)"></i> <?php echo adviser_companies_escape($website !== '' ? $website : 'N/A'); ?></div>
        </div>
        <div style="display:flex;gap:8px">
          <?php if ($website !== ''): ?>
            <a class="btn-outline" style="flex:1;text-align:center" href="<?php echo adviser_companies_escape(preg_match('/^https?:\/\//i', $website) ? $website : ('https://' . $website)); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-eye"></i> View</a>
          <?php else: ?>
            <button class="btn-outline" style="flex:1" type="button" disabled><i class="fas fa-eye"></i> View</button>
          <?php endif; ?>
          <?php if ($contactEmail !== ''): ?>
            <a class="btn-outline" style="flex:1;text-align:center" href="mailto:<?php echo adviser_companies_escape($contactEmail); ?>"><i class="fas fa-envelope"></i> Contact</a>
          <?php else: ?>
            <button class="btn-outline" style="flex:1" type="button" disabled><i class="fas fa-envelope"></i> Contact</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="company-card" style="grid-column:1 / -1;display:flex;align-items:center;justify-content:center;color:var(--text-light);min-height:120px;">
      No companies found for the selected filters.
    </div>
  <?php endif; ?>
</div>
