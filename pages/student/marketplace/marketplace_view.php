<?php

function marketplace_render(array $data): void
{
    extract($data);
    // $baseUrl, $userId, $currentFilters, $selectedDetailId, $openApplyModal,
    // $consentVersion, $applicationSuccessModal, $studentSkillNames, $listings,
    // $industries, $locations, $appliedInternshipIds, $detailListing,
    // $detailRequirements, $detailMatchCount, $detailRequiredCount,
    // $studentHasResume, $resumeRow, $externalListingsCount, $externalListingsNotice
    ?>

<style>
.market-results-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:14px 0 10px; }
.market-results-copy { font-size:.84rem; color:#6b7280; }
.market-empty { background:#fff; border:1px dashed #dbe2ea; border-radius:16px; padding:28px; text-align:center; color:#6b7280; }
.market-empty i { font-size:1.8rem; color:#9ca3af; margin-bottom:10px; display:block; }
.market-card-desc { font-size:.82rem; color:#666; margin:8px 0 12px; min-height:58px; }
.market-form-inline { margin:0; }
.market-detail-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:18px; margin-bottom:14px; }
.market-detail-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
.market-detail-title { font-size:1.05rem; font-weight:800; color:#111827; margin-bottom:5px; }
.market-detail-sub { color:#6b7280; font-size:.83rem; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.market-detail-grid { display:grid; grid-template-columns:2fr 1.1fr; gap:14px; margin-top:12px; }
.market-detail-box { border:1px solid #eef2f7; border-radius:12px; padding:12px; background:#fcfcfd; }
.market-req-list { display:flex; flex-direction:column; gap:8px; margin-top:8px; }
.market-req-item { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:.8rem; color:#374151; }
.market-process { margin-top:8px; display:flex; flex-direction:column; gap:6px; font-size:.8rem; color:#374151; }
.market-process-step { display:flex; gap:8px; align-items:flex-start; }
.market-process-bullet { width:18px; height:18px; border-radius:50%; background:#111827; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.68rem; flex-shrink:0; margin-top:1px; }
.market-legal { display:flex; flex-direction:column; gap:8px; margin-top:10px; font-size:.79rem; color:#374151; }
.market-legal label { display:flex; align-items:flex-start; gap:8px; }
.market-legal input[type="checkbox"] { margin-top:2px; }
.market-cover { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 12px; font-size:.83rem; font-family:inherit; resize:vertical; min-height:110px; background:#fff; }
.market-company-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden; }
.market-company-head { padding:14px 14px 10px; border-bottom:1px solid #f1f5f9; }
.market-company-row { display:flex; align-items:center; gap:10px; }
.market-company-logo { width:44px; height:44px; border-radius:10px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; font-weight:700; color:#111827; overflow:hidden; flex-shrink:0; }
.market-company-logo img { width:100%; height:100%; object-fit:cover; }
.market-company-body { padding:12px 14px 14px; color:#4b5563; font-size:.82rem; line-height:1.65; }
.market-company-foot { border-top:1px solid #f1f5f9; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.market-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.market-overlay.open { display:flex; }
.market-modal { width:860px; max-width:96vw; max-height:92vh; background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 30px 70px rgba(2,6,23,.35); display:flex; flex-direction:column; }
.market-modal-head { padding:14px 18px; border-bottom:1px solid #eef2f7; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.market-modal-title { font-size:1rem; font-weight:800; color:#111827; }
.market-modal-sub { font-size:.76rem; color:#64748b; margin-top:2px; }
.market-modal-body { padding:14px 18px; overflow-y:auto; display:flex; flex-direction:column; gap:12px; }
.market-modal-close { width:32px; height:32px; border:none; border-radius:999px; background:#f1f5f9; color:#334155; cursor:pointer; }
.market-modal-grid { display:grid; grid-template-columns:1.4fr 1fr; gap:12px; }
.market-modal-box { border:1px solid #e2e8f0; border-radius:12px; background:#fcfdff; padding:12px; }
.market-req-checks { display:flex; flex-direction:column; gap:8px; font-size:.8rem; color:#374151; }
.market-req-checks label { display:flex; align-items:flex-start; gap:8px; }
.market-field { margin-bottom:10px; }
.market-field label { display:block; font-size:.75rem; color:#475569; margin-bottom:4px; font-weight:600; }
.market-field input { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 11px; font-size:.82rem; background:#fff; }
.market-modal-foot { padding:12px 18px 16px; border-top:1px solid #eef2f7; display:flex; justify-content:flex-end; gap:8px; }
.market-success-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:10020; align-items:center; justify-content:center; }
.market-success-overlay.open { display:flex; }
.market-success-card { width:520px; max-width:94vw; background:#fff; border-radius:20px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:0 24px 70px rgba(2,6,23,.3); }
.market-success-top { padding:28px 24px 20px; text-align:center; background:radial-gradient(circle at top,#ecfeff,#ffffff 70%); }
.market-success-icon { width:64px; height:64px; border-radius:50%; margin:0 auto 12px; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
.market-success-title { font-size:2rem; font-weight:800; color:#0f172a; }
.market-success-sub { margin-top:8px; color:#64748b; font-size:.92rem; line-height:1.5; }
.market-success-box { margin:0 24px 16px; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
.market-success-box-head { padding:12px 14px; background:#f8fafc; font-weight:700; color:#0f172a; }
.market-success-box-body { padding:14px; }
.market-success-actions { padding:0 24px 24px; display:flex; flex-direction:column; gap:10px; }
.market-source-chip { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; color:#0369a1; background:rgba(14,165,233,.12); border:1px solid rgba(14,165,233,.24); border-radius:999px; padding:2px 8px; }
.market-source-note { margin:6px 0 2px; font-size:.77rem; color:#475569; display:flex; align-items:center; gap:6px; }
@media (max-width: 980px) {
  .market-detail-grid { grid-template-columns:1fr; }
  .market-modal-grid { grid-template-columns:1fr; }
}
</style>

<div class="page-header">
  <div>
    <h2 class="page-title">Internship Marketplace</h2>
    <p class="page-subtitle">Browse verified internship opportunities from partner companies.</p>
  </div>
</div>

<form method="get" action="<?php echo marketplace_e($baseUrl); ?>/layout.php">
  <input type="hidden" name="page" value="student/marketplace">
  <div class="filter-row">
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="topbar-search" style="flex:1;max-width:300px">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search internships..." value="<?php echo marketplace_e($currentFilters['q']); ?>">
      </div>
      <select class="filter-select" name="industry" onchange="this.form.submit()">
        <option value="">All Industries</option>
        <?php foreach ($industries as $industry): ?>
          <option value="<?php echo marketplace_e($industry); ?>" <?php echo $currentFilters['industry'] === $industry ? 'selected' : ''; ?>><?php echo marketplace_e($industry); ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" name="location" onchange="this.form.submit()">
        <option value="">All Locations</option>
        <?php foreach ($locations as $location): ?>
          <option value="<?php echo marketplace_e($location); ?>" <?php echo $currentFilters['location'] === $location ? 'selected' : ''; ?>><?php echo marketplace_e($location); ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" name="work_setup" onchange="this.form.submit()">
        <option value="">Work Setup</option>
        <?php foreach (['On-site', 'Remote', 'Hybrid'] as $setup): ?>
          <option value="<?php echo marketplace_e($setup); ?>" <?php echo $currentFilters['work_setup'] === $setup ? 'selected' : ''; ?>><?php echo marketplace_e($setup); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
    </div>
    <input type="hidden" name="source" id="marketSourceInput" value="<?php echo isset($_GET['source']) ? marketplace_e($_GET['source']) : ''; ?>">
    <input type="hidden" name="sort" id="marketSortInput" value="<?php echo isset($_GET['sort']) ? marketplace_e($_GET['sort']) : ''; ?>">
    <div class="market-listing-filters" style="margin-top:10px;display:flex;gap:8px;">
      <button type="button" class="btn btn-ghost btn-sm market-listing-btn" id="btnAllListings">All listings</button>
      <button type="button" class="btn btn-ghost btn-sm market-listing-btn" id="btnLocalOnly">Local only</button>
      <button type="button" class="btn btn-ghost btn-sm market-listing-btn" id="btnExternalOnly">External (Findwork)</button>
      <button type="button" class="btn btn-ghost btn-sm market-listing-btn" id="btnBestMatch">Best match</button>
    </div>
</form>

<style>
.market-listing-filters {
  margin-bottom: 8px;
}
.market-listing-btn.active, .market-listing-btn:active {
  background: #111 !important;
  color: #fff !important;
  border-color: #111 !important;
}
</style>

<script>
// Activate the correct button based on URL params
const urlParams = new URLSearchParams(window.location.search);
const source = urlParams.get('source') || '';
const sort = urlParams.get('sort') || '';
function setActiveBtn(btnId) {
  document.querySelectorAll('.market-listing-btn').forEach(btn => btn.classList.remove('active'));
  if (btnId) document.getElementById(btnId).classList.add('active');
}
if (source === 'local') setActiveBtn('btnLocalOnly');
else if (source === 'external') setActiveBtn('btnExternalOnly');
else if (sort === 'bestmatch') setActiveBtn('btnBestMatch');
else setActiveBtn('btnAllListings');

// Button click handlers (use this.form to always submit the correct form)
document.getElementById('btnAllListings').onclick = function() {
  document.getElementById('marketSourceInput').value = '';
  document.getElementById('marketSortInput').value = '';
  this.form.submit();
};
document.getElementById('btnLocalOnly').onclick = function() {
  document.getElementById('marketSourceInput').value = 'local';
  document.getElementById('marketSortInput').value = '';
  this.form.submit();
};
document.getElementById('btnExternalOnly').onclick = function() {
  document.getElementById('marketSourceInput').value = 'external';
  document.getElementById('marketSortInput').value = '';
  this.form.submit();
};
document.getElementById('btnBestMatch').onclick = function() {
  document.getElementById('marketSourceInput').value = '';
  document.getElementById('marketSortInput').value = 'bestmatch';
  this.form.submit();
};
</script>

<?php if (!empty($externalListingsNotice)): ?>
  <div class="market-source-note"><i class="fas fa-globe"></i> <?php echo marketplace_e((string) $externalListingsNotice); ?></div>
<?php endif; ?>

<?php if ($detailListing !== null): ?>
  <?php
    $detailInternshipId  = (int) $detailListing['internship_id'];
    $detailCompany       = (string) $detailListing['company_name'];
    $detailApplied       = isset($appliedInternshipIds[$detailInternshipId]);
    $detailCompatibility = $detailRequiredCount > 0 ? round(($detailMatchCount / $detailRequiredCount) * 100, 1) : null;
    $detailCompanyLogo   = trim((string) ($detailListing['company_logo'] ?? ''));
    $detailCompanyLogoUrl = $detailCompanyLogo !== '' ? ($baseUrl . '/assets/backend/uploads/company/' . rawurlencode($detailCompanyLogo)) : '';
    $detailCompanyAddress = trim((string) ($detailListing['company_address'] ?? ''));
    $detailCompanyWebsite = trim((string) ($detailListing['website_url'] ?? ''));
    $detailVerification   = trim((string) ($detailListing['verification_status'] ?? ''));
  ?>
  <div class="market-detail-card">
    <div class="market-detail-head">
      <div>
        <div class="market-detail-title"><?php echo marketplace_e((string) $detailListing['title']); ?></div>
        <div class="market-detail-sub">
          <span><?php echo marketplace_e($detailCompany); ?></span>
          <?php echo marketplace_status_badge((string) ($detailListing['company_badge_status'] ?? '')); ?>
          <span><i class="fas fa-map-marker-alt"></i> <?php echo marketplace_e((string) ($detailListing['location'] ?: 'Location not set')); ?></span>
          <span><i class="fas fa-clock"></i> <?php echo marketplace_e(marketplace_duration_label((int) $detailListing['duration_weeks'])); ?></span>
          <span style="<?php echo marketplace_work_setup_style((string) $detailListing['work_setup']); ?>"><?php echo marketplace_e((string) $detailListing['work_setup']); ?></span>
        </div>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/marketplace&amp;<?php echo marketplace_e(http_build_query(array_merge($currentFilters, ['detail' => 0]))); ?>">Close Details</a>
    </div>

    <div class="market-detail-box" style="margin-top:12px">
      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px">About the Job</div>
      <p style="font-size:.84rem;color:#475569;line-height:1.7;margin:0"><?php echo nl2br(marketplace_e((string) $detailListing['description'])); ?></p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;color:#64748b;font-size:.79rem">
        <span>Industry: <strong style="color:#111827"><?php echo marketplace_e((string) $detailListing['industry']); ?></strong></span>
        <span>Allowance: <strong style="color:#111827">P<?php echo number_format((float) ($detailListing['allowance'] ?? 0), 0); ?>/mo</strong></span>
        <span>Open Slots: <strong style="color:#111827"><?php echo (int) $detailListing['slots_available']; ?></strong></span>
      </div>
    </div>

    <div class="market-detail-grid">
      <div class="market-detail-box">
        <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px">Requirements</div>
        <div style="font-size:.78rem;color:#64748b;">
          <?php if ($detailCompatibility !== null): ?>
            You match <strong style="color:#111827"><?php echo marketplace_e((string) $detailMatchCount); ?>/<?php echo marketplace_e((string) $detailRequiredCount); ?></strong> required skills (<?php echo marketplace_e((string) $detailCompatibility); ?>%).
          <?php else: ?>
            No strict skill requirements were set by the company.
          <?php endif; ?>
        </div>
        <div class="market-req-list">
          <?php if (!$detailRequirements): ?>
            <div class="market-req-item"><span>No specific skills listed.</span></div>
          <?php else: ?>
            <?php foreach ($detailRequirements as $req): ?>
              <?php
                $reqSkill     = trim((string) ($req['skill_name'] ?? ''));
                $reqLevel     = (string) ($req['required_level'] ?? 'Beginner');
                $reqMandatory = (int) ($req['is_mandatory'] ?? 0) === 1;
                $reqMatched   = isset($studentSkillNames[strtolower($reqSkill)]);
              ?>
              <div class="market-req-item">
                <span><?php echo marketplace_e($reqSkill); ?> <?php if ($reqMandatory): ?><span style="color:#EF4444">(Required)</span><?php endif; ?></span>
                <span style="display:flex;gap:6px;align-items:center;white-space:nowrap">
                  <span style="font-size:.7rem;padding:2px 8px;border-radius:50px;background:#f1f5f9;color:#475569"><?php echo marketplace_e($reqLevel); ?></span>
                  <span style="font-size:.7rem;padding:2px 8px;border-radius:50px;<?php echo $reqMatched ? 'background:rgba(16,185,129,.1);color:#10B981' : 'background:rgba(148,163,184,.15);color:#64748B'; ?>"><?php echo $reqMatched ? 'You have this' : 'Not in profile'; ?></span>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="market-detail-box">
        <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px">Application Checklist</div>
        <div class="market-process">
          <div class="market-process-step"><span class="market-process-bullet">1</span><span>Read job details and verify requirements fit your profile.</span></div>
          <div class="market-process-step"><span class="market-process-bullet">2</span><span>Prepare your resume and write a role-specific cover letter.</span></div>
          <div class="market-process-step"><span class="market-process-bullet">3</span><span>Confirm legal consent and submit for employer review.</span></div>
        </div>
      </div>
    </div>

    <div class="market-detail-box" style="margin-top:12px">
      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px">Internship Application</div>
      <?php if ($detailApplied): ?>
        <div style="font-size:.83rem;color:#10B981;margin-bottom:8px">You already applied for this internship.</div>
        <a class="btn btn-ghost btn-sm" href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/applications">Go to Applications</a>
      <?php else: ?>
        <div style="font-size:.8rem;color:#64748b;margin-bottom:10px">Open the complete application modal to review university requirements and submit your internship application.</div>
        <button class="btn btn-primary btn-sm" type="button" onclick="openApplyModal()" <?php echo $studentHasResume ? '' : 'disabled'; ?>>Start Application</button>
        <?php if (!$studentHasResume): ?>
          <div style="font-size:.78rem;color:#EF4444;margin-top:8px">Resume required before you can continue.</div>
          <a class="btn btn-ghost btn-sm" style="margin-top:8px" href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/profile">Go to Profile</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="market-detail-box" style="margin-top:12px">
      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px">Application Process</div>
      <div class="market-process">
        <div class="market-process-step"><span class="market-process-bullet">1</span><span>Review role details and requirements.</span></div>
        <div class="market-process-step"><span class="market-process-bullet">2</span><span>Open application modal, complete university requirements checklist, and confirm MOA signing process.</span></div>
        <div class="market-process-step"><span class="market-process-bullet">3</span><span>Submit application. SkillHive stores consent version, timestamp, and compliance snapshot for audit trail.</span></div>
      </div>
    </div>

    <div class="market-detail-box" style="margin-top:12px">
      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">About the Company</div>
      <div class="market-company-card">
        <div class="market-company-head">
          <div class="market-company-row">
            <div class="market-company-logo">
              <?php if ($detailCompanyLogoUrl !== ''): ?>
                <img src="<?php echo marketplace_e($detailCompanyLogoUrl); ?>" alt="Company logo">
              <?php else: ?>
                <?php echo marketplace_e(strtoupper(substr($detailCompany, 0, 1))); ?>
              <?php endif; ?>
            </div>
            <div style="min-width:0;flex:1">
              <div style="font-weight:700;font-size:.94rem;color:#111827"><?php echo marketplace_e($detailCompany); ?></div>
              <div style="font-size:.75rem;color:#6b7280;margin-top:2px"><?php echo marketplace_e((string) $detailListing['industry']); ?><?php echo $detailVerification !== '' ? ' · ' . marketplace_e($detailVerification) : ''; ?></div>
            </div>
          </div>
        </div>
        <div class="market-company-body">
          <?php if ($detailCompanyAddress !== ''): ?>
            <div style="margin-bottom:6px"><strong style="color:#111827">Address:</strong> <?php echo marketplace_e($detailCompanyAddress); ?></div>
          <?php endif; ?>
          <div>This employer posted this opportunity in SkillHive and will review your submitted profile, resume, and cover letter based on the listed requirements.</div>
        </div>
        <div class="market-company-foot">
          <?php if ($detailCompanyWebsite !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?php echo marketplace_e($detailCompanyWebsite); ?>" target="_blank" rel="noopener noreferrer">Visit Website</a>
          <?php else: ?>
            <span style="font-size:.75rem;color:#94a3b8">Website not provided</span>
          <?php endif; ?>
          <span style="font-size:.74rem;color:#94a3b8">Posted in SkillHive Marketplace</span>
        </div>
      </div>
    </div>

    <?php if (!$detailApplied): ?>
      <div class="market-overlay" id="applyModal" onclick="if(event.target===this)closeApplyModal()">
        <div class="market-modal">
          <div class="market-modal-head">
            <div>
              <div class="market-modal-title">Internship Application Details</div>
              <div class="market-modal-sub"><?php echo marketplace_e((string) $detailListing['title']); ?> · <?php echo marketplace_e($detailCompany); ?></div>
            </div>
            <button class="market-modal-close" type="button" onclick="closeApplyModal()"><i class="fas fa-times"></i></button>
          </div>

          <form id="applyInternshipForm" method="post" action="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/marketplace&amp;<?php echo marketplace_e(http_build_query(array_merge($currentFilters, ['detail' => $detailInternshipId, 'open_apply' => 1]))); ?>">
            <input type="hidden" name="action" value="apply_internship">
            <input type="hidden" name="internship_id" value="<?php echo $detailInternshipId; ?>">
            <input type="hidden" name="detail" value="<?php echo $detailInternshipId; ?>">
            <input type="hidden" name="open_apply" value="1">
            <input type="hidden" name="q" value="<?php echo marketplace_e($currentFilters['q']); ?>">
            <input type="hidden" name="industry" value="<?php echo marketplace_e($currentFilters['industry']); ?>">
            <input type="hidden" name="location" value="<?php echo marketplace_e($currentFilters['location']); ?>">
            <input type="hidden" name="work_setup" value="<?php echo marketplace_e($currentFilters['work_setup']); ?>">

            <div class="market-modal-body">
              <div class="market-modal-grid">
                <div class="market-modal-box">
                  <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">University Internship Requirements</div>
                  <div class="market-req-checks">
                    <label><input type="checkbox" name="confirm_endorsement" value="1" required> <span>I will submit my university/internship adviser endorsement before deployment.</span></label>
                    <label><input type="checkbox" name="confirm_moa" value="1" required> <span>I understand MOA signing is required between university and company before internship start.</span></label>
                    <label><input type="checkbox" name="confirm_medical" value="1" required> <span>I will provide required medical/fit-to-work clearance if requested by school/company.</span></label>
                    <label><input type="checkbox" name="confirm_insurance" value="1" required> <span>I confirm internship insurance/accident coverage requirements will be complied with.</span></label>
                    <label><input type="checkbox" name="confirm_university_policy" value="1" required> <span>I will follow my university OJT policies, required hours, and documentation rules.</span></label>
                  </div>
                </div>

                <div class="market-modal-box">
                  <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">Application Profile</div>
                  <div class="market-field">
                    <label>Preferred Start Date</label>
                    <input type="date" name="preferred_start_date" required>
                  </div>
                  <div class="market-field">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" maxlength="120" required>
                  </div>
                  <div class="market-field" style="margin-bottom:0">
                    <label>Emergency Contact Number</label>
                    <input type="text" name="emergency_contact_phone" maxlength="40" required>
                  </div>
                </div>
              </div>

              <div class="market-modal-box">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">Cover Letter</div>
                <textarea class="market-cover" name="cover_letter" placeholder="Introduce yourself, explain your fit for this internship, and mention relevant coursework/projects." required></textarea>
              </div>

              <div class="market-modal-box">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:8px">Legal Consent</div>
                <div class="market-legal" style="margin-top:0">
                  <label><input type="checkbox" name="attest_accuracy" value="1" required> <span>I confirm that all information submitted in this application is accurate and truthful.</span></label>
                  <label><input type="checkbox" name="consent_privacy" value="1" required> <span>I consent to share my profile, resume, and application data with this employer for internship evaluation and recruitment.</span></label>
                </div>
                <div style="margin-top:10px;padding:10px;border:1px dashed #d1d5db;border-radius:10px;background:#fff;display:flex;flex-direction:column;gap:6px">
                  <div style="font-size:.74rem;color:#64748b;font-weight:700">Data shared with employer</div>
                  <div style="font-size:.76rem;color:#475569">Resume file: <strong><?php echo marketplace_e((string) ($resumeRow['resume_file'] ?? '')); ?></strong></div>
                  <div style="font-size:.76rem;color:#475569">Profile link: <strong><?php echo marketplace_e($baseUrl . '/layout.php?page=student/profile&student_id=' . (int) $userId); ?></strong></div>
                </div>
                <div style="font-size:.73rem;color:#64748b;margin-top:8px">Application Consent Record will be saved as version <?php echo marketplace_e($consentVersion); ?> with server timestamp.</div>
              </div>
            </div>

            <div class="market-modal-foot">
              <button class="btn btn-ghost btn-sm" type="button" onclick="closeApplyModal()">Cancel</button>
              <button class="btn btn-primary btn-sm" id="submitApplicationBtn" type="submit">Submit Application</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php elseif ($selectedDetailId > 0): ?>
  <div class="market-empty" style="margin-bottom:14px">
    <i class="fas fa-exclamation-circle"></i>
    <div style="font-weight:700;color:#111;margin-bottom:6px">Internship details unavailable.</div>
    <div>The internship may already be closed or removed.</div>
  </div>
<?php endif; ?>

<div class="market-results-row">
  <div class="market-results-copy"><?php echo count($listings); ?> internship<?php echo count($listings) === 1 ? '' : 's'; ?> found</div>
  <?php if ($currentFilters['q'] !== '' || $currentFilters['industry'] !== '' || $currentFilters['location'] !== '' || $currentFilters['work_setup'] !== '' || $selectedDetailId > 0): ?>
    <a href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/marketplace" class="btn btn-ghost btn-sm">Clear Filters</a>
  <?php endif; ?>
</div>

<?php if (!$listings): ?>
  <div class="market-empty">
    <i class="fas fa-store-slash"></i>
    <div style="font-weight:700;color:#111;margin-bottom:6px">No internships match your filters.</div>
    <div>Try a broader search or clear the filters.</div>
  </div>
<?php else: ?>
  <div class="cards-grid">
    <?php
      // Filter logic for source and sort
      $source = isset($_GET['source']) ? $_GET['source'] : '';
      $sort = isset($_GET['sort']) ? $_GET['sort'] : '';
      $filteredListings = $listings;
      if ($source === 'local') {
        $filteredListings = array_filter($filteredListings, function($l) { return empty($l['is_external']) || !$l['is_external']; });
      } elseif ($source === 'external') {
        $filteredListings = array_filter($filteredListings, function($l) { return !empty($l['is_external']); });
      }
      if ($sort === 'bestmatch') {
        usort($filteredListings, function($a, $b) {
          return (int)($b['compatibility_score'] ?? 0) <=> (int)($a['compatibility_score'] ?? 0);
        });
      }
    ?>
    <?php foreach ($filteredListings as $listing): ?>
      <?php
        $companyName    = (string) $listing['company_name'];
        $companyInitial = strtoupper(substr($companyName, 0, 1));
        $skills         = array_values(array_filter(array_map('trim', explode(',', (string) ($listing['required_skills'] ?? '')))));
        $isApplied      = isset($appliedInternshipIds[(int) $listing['internship_id']]);
      ?>
      <div class="job-card">
        <div class="job-card-header">
          <div class="co-logo" style="background:<?php echo marketplace_company_gradient($companyName); ?>"><?php echo marketplace_e($companyInitial !== '' ? $companyInitial : 'C'); ?></div>
          <div class="job-card-info">
            <div class="job-card-title"><?php echo marketplace_e((string) $listing['title']); ?></div>
            <div class="job-card-company"><?php echo marketplace_e($companyName); ?> <?php echo marketplace_status_badge((string) ($listing['company_badge_status'] ?? '')); ?></div>
          </div>
        </div>
        <div class="job-card-meta">
          <span><i class="fas fa-map-marker-alt"></i> <?php echo marketplace_e((string) ($listing['location'] ?: 'Location not set')); ?></span>
          <span><i class="fas fa-clock"></i> <?php echo marketplace_e(marketplace_duration_label((int) $listing['duration_weeks'])); ?></span>
          <span style="<?php echo marketplace_work_setup_style((string) $listing['work_setup']); ?>"><?php echo marketplace_e((string) $listing['work_setup']); ?></span>
        </div>
        <?php
          $desc = (string) $listing['description'];
          $descFull = $desc;
          $descTrunc = $desc;
          $descLimit = 220;
          $showViewMore = false;
          if (mb_strlen($desc) > $descLimit) {
            $descTrunc = mb_substr($desc, 0, $descLimit - 3) . '...';
            $showViewMore = true;
          }
          $descId = 'desc_' . md5($listing['title'] . $listing['company_name'] . $listing['internship_id']);
        ?>
        <div class="market-card-desc-wrap" id="wrap_<?php echo $descId; ?>">
          <p class="market-card-desc" id="<?php echo $descId; ?>">
            <span class="desc-short"><?php echo marketplace_e($descTrunc); ?></span>
            <?php if ($showViewMore): ?>
              <span class="desc-full" style="display:none;"><?php echo marketplace_e($descFull); ?></span>
              <a href="#" class="market-view-more" onclick="event.preventDefault();toggleDesc('<?php echo $descId; ?>');">View more</a>
            <?php endif; ?>
          </p>
        </div>
<style>
.market-view-more {
  color: #0f172a;
  font-weight: 600;
  margin-left: 6px;
  cursor: pointer;
  text-decoration: underline;
  font-size: .85em;
}
.market-card-desc-wrap {
  transition: all .25s cubic-bezier(.4,0,.2,1);
}
</style>
<script>
function toggleDesc(descId) {
  var wrap = document.getElementById('wrap_' + descId);
  var p = document.getElementById(descId);
  if (!wrap || !p) return;
  var shortSpan = p.querySelector('.desc-short');
  var fullSpan = p.querySelector('.desc-full');
  var viewMore = p.querySelector('.market-view-more');
  if (shortSpan && fullSpan && viewMore) {
    shortSpan.style.display = 'none';
    fullSpan.style.display = 'inline';
    viewMore.style.display = 'none';
    wrap.style.maxHeight = '1000px';
    wrap.style.background = '#f9fafb';
    wrap.style.borderRadius = '10px';
    wrap.style.padding = '6px 0 6px 0';
  }
}
</script>
        <div class="job-card-skills">
          <?php if (!$skills): ?>
            <span class="skill-chip">No skills listed</span>
          <?php else: ?>
            <?php foreach (array_slice($skills, 0, 4) as $skillName): ?>
              <?php $isMatch = isset($studentSkillNames[strtolower($skillName)]); ?>
              <span class="skill-chip<?php echo $isMatch ? ' match' : ''; ?>"><?php echo marketplace_e($skillName); ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:10px;flex-wrap:wrap">
          <div>
            <span style="font-weight:700;color:#111">&#8369;<?php echo number_format((float) ($listing['allowance'] ?? 0), 0); ?><span style="font-weight:400;color:#999;font-size:.78rem">/mo</span></span>
            <div style="font-size:.74rem;color:#94a3b8;margin-top:3px"><?php echo marketplace_e((string) $listing['industry']); ?> &middot; <?php echo (int) $listing['slots_available']; ?> slot<?php echo (int) $listing['slots_available'] === 1 ? '' : 's'; ?></div>
          </div>
          <?php if (!empty($listing['is_external']) && !empty($listing['external_url'])): ?>
            <a class="btn btn-primary btn-sm" href="<?php echo marketplace_e($listing['external_url']); ?>" target="_blank" rel="noopener noreferrer">View in Findwork</a>
          <?php elseif ($isApplied): ?>
            <a class="btn btn-ghost btn-sm" href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/applications">Applied</a>
          <?php else: ?>
            <a class="btn btn-primary btn-sm" href="<?php echo marketplace_e(marketplace_detail_url($baseUrl, $currentFilters, (int) $listing['internship_id'])); ?>">View Details</a>
          <?php endif; ?>
        </div>
        <div style="margin-top:10px;">
          <div style="height:8px;background:#f1f5f9;border-radius:6px;overflow:hidden;">
            <div style="width:<?php echo (int) ($listing['compatibility_score'] ?? 0); ?>%;background:#06b6d4;height:8px;border-radius:6px;"></div>
          </div>
          <div style="font-size:.72rem;color:#64748b;margin-top:2px;">Compatibility score: <strong><?php echo (int) ($listing['compatibility_score'] ?? 0); ?>%</strong></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (is_array($applicationSuccessModal)): ?>
  <div class="market-success-overlay" id="applicationSuccessModal" onclick="if(event.target===this)closeApplicationSuccessModal()">
    <div class="market-success-card">
      <div class="market-success-top">
        <div class="market-success-icon"><i class="fas fa-check"></i></div>
        <div class="market-success-title">Application Successful</div>
        <div class="market-success-sub">Your internship application has been received and is now waiting for employer review.</div>
      </div>

      <div class="market-success-box">
        <div class="market-success-box-head"><?php echo marketplace_e((string) ($applicationSuccessModal['title'] ?? 'Internship Application')); ?></div>
        <div class="market-success-box-body">
          <div style="font-size:.84rem;color:#475569;line-height:1.6;"><?php echo marketplace_e((string) ($applicationSuccessModal['company'] ?? 'Company')); ?> is actively reviewing applicants. Please monitor your application status updates.</div>
          <div style="margin-top:8px;color:#92400e;font-size:.84rem;font-weight:700"><i class="far fa-clock"></i> Typical review wait: 3 to 7 business days</div>
        </div>
      </div>

      <div class="market-success-actions">
        <a href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/applications" class="btn btn-primary btn-sm" style="justify-content:center">Go to My Applications</a>
        <button type="button" class="btn btn-ghost btn-sm" style="justify-content:center" onclick="closeApplicationSuccessModal()">Back to Marketplace</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
function openApplyModal() {
  var modal = document.getElementById('applyModal');
  if (modal) modal.classList.add('open');
}

function closeApplyModal() {
  var modal = document.getElementById('applyModal');
  if (modal) modal.classList.remove('open');
}

function closeApplicationSuccessModal() {
  var modal = document.getElementById('applicationSuccessModal');
  if (modal) modal.classList.remove('open');
}

var applyInternshipForm = document.getElementById('applyInternshipForm');
if (applyInternshipForm) {
  applyInternshipForm.addEventListener('submit', function () {
    closeApplyModal();
    var submitBtn = document.getElementById('submitApplicationBtn');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    }
  });
}

<?php if ($detailListing !== null && !$detailApplied && $openApplyModal): ?>
openApplyModal();
<?php endif; ?>

<?php if (is_array($applicationSuccessModal)): ?>
var successModal = document.getElementById('applicationSuccessModal');
if (successModal) {
  closeApplyModal();
  successModal.classList.add('open');
}
<?php endif; ?>
</script>

    <?php
}
