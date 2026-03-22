<?php

function marketplace_render(array $data): void
{
    extract($data);
    // $baseUrl, $userId, $currentFilters, $selectedDetailId, $openApplyModal,
    // $consentVersion, $applicationSuccessModal, $studentSkillNames, $listings,
    // $industries, $locations, $appliedInternshipIds, $detailListing,
    // $detailRequirements, $detailMatchCount, $detailRequiredCount,
    // $studentHasResume, $resumeRow, $externalListingsCount, $externalListingsNotice
    $showExternal = (int) ($currentFilters['include_external'] ?? 0) === 1;

    $sortFilter = strtolower(trim((string) ($currentFilters['sort'] ?? 'latest')));
    if (!in_array($sortFilter, ['latest', 'bestmatch', 'allowance_high', 'allowance_low', 'title_az', 'title_za'], true)) {
        $sortFilter = 'latest';
    }

    $localListings = array_values(array_filter($listings, static fn(array $item): bool => empty($item['is_external'])));
    $externalListings = array_values(array_filter($listings, static fn(array $item): bool => !empty($item['is_external'])));

    $compatibilityScore = static function (array $listing) use ($studentSkillNames): int {
        $rawSkills = (string) ($listing['required_skills'] ?? '');
        $skills = array_values(array_filter(array_map('trim', explode(',', $rawSkills))));
        if (!$skills) {
            return 0;
        }

        $matched = 0;
        foreach ($skills as $skillName) {
            if (isset($studentSkillNames[strtolower($skillName)])) {
                $matched++;
            }
        }

        return (int) round(($matched / max(1, count($skills))) * 100);
    };

    $sortListings = static function (array &$items) use ($sortFilter, $compatibilityScore): void {
        usort($items, static function (array $a, array $b) use ($sortFilter, $compatibilityScore): int {
            if ($sortFilter === 'bestmatch') {
                $aScore = $compatibilityScore($a);
                $bScore = $compatibilityScore($b);
                if ($aScore !== $bScore) {
                    return $bScore <=> $aScore;
                }
        } elseif ($sortFilter === 'allowance_high') {
          return ((float) ($b['allowance'] ?? 0)) <=> ((float) ($a['allowance'] ?? 0));
        } elseif ($sortFilter === 'allowance_low') {
          return ((float) ($a['allowance'] ?? 0)) <=> ((float) ($b['allowance'] ?? 0));
        } elseif ($sortFilter === 'title_az') {
          return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        } elseif ($sortFilter === 'title_za') {
          return strcasecmp((string) ($b['title'] ?? ''), (string) ($a['title'] ?? ''));
            }

            return strcmp((string) ($b['posted_at'] ?? ''), (string) ($a['posted_at'] ?? ''));
        });
    };

    $sortListings($localListings);
    $sortListings($externalListings);

    $externalPerPage = 10;
    $externalTotalCount = count($externalListings);
    $externalPage = max(1, (int) ($currentFilters['external_page'] ?? 1));
    $externalTotalPages = max(1, (int) ceil($externalTotalCount / $externalPerPage));
    if ($externalPage > $externalTotalPages) {
      $externalPage = $externalTotalPages;
    }
    $externalOffset = ($externalPage - 1) * $externalPerPage;
    $externalListingsPage = array_slice($externalListings, $externalOffset, $externalPerPage);

    $topPicks = $localListings;
    usort($topPicks, static function (array $a, array $b) use ($compatibilityScore): int {
      $aScore = $compatibilityScore($a);
      $bScore = $compatibilityScore($b);
      if ($aScore !== $bScore) {
        return $bScore <=> $aScore;
      }

      $allowanceOrder = ((float) ($b['allowance'] ?? 0)) <=> ((float) ($a['allowance'] ?? 0));
      if ($allowanceOrder !== 0) {
        return $allowanceOrder;
      }

      return strcmp((string) ($b['posted_at'] ?? ''), (string) ($a['posted_at'] ?? ''));
    });
    $topPicks = array_slice($topPicks, 0, 4);

    if (!$showExternal) {
      $externalListings = [];
      $externalListingsPage = [];
      $externalPage = 1;
      $externalTotalPages = 1;
      $externalTotalCount = 0;
    }

    $toggleExternalQuery = [
      'page' => 'student/marketplace',
      'q' => $currentFilters['q'],
      'industry' => $currentFilters['industry'],
      'location' => $currentFilters['location'],
      'work_setup' => $currentFilters['work_setup'],
      'duration' => $currentFilters['duration'] ?? '',
      'allowance_range' => $currentFilters['allowance_range'] ?? '',
      'sort' => $sortFilter,
      'include_external' => $showExternal ? 0 : 1,
      'external_page' => 1,
    ];
    $toggleExternalUrl = $baseUrl . '/layout.php?' . http_build_query($toggleExternalQuery);

    $buildExternalPageUrl = static function (int $targetPage) use ($baseUrl, $currentFilters, $sortFilter): string {
      $query = [
        'page' => 'student/marketplace',
        'q' => $currentFilters['q'] ?? '',
        'industry' => $currentFilters['industry'] ?? '',
        'location' => $currentFilters['location'] ?? '',
        'work_setup' => $currentFilters['work_setup'] ?? '',
        'duration' => $currentFilters['duration'] ?? '',
        'allowance_range' => $currentFilters['allowance_range'] ?? '',
        'sort' => $sortFilter,
        'include_external' => 1,
        'external_page' => max(1, $targetPage),
      ];

      return $baseUrl . '/layout.php?' . http_build_query($query);
    };

    $renderListings = static function (array $items, bool $isExternalSection) use ($appliedInternshipIds, $baseUrl, $currentFilters, $studentSkillNames, $compatibilityScore): void {
      if (!$items) {
        echo '<div class="market-empty"><i class="fas fa-box-open"></i><div style="font-weight:700;color:#111;margin-bottom:6px">No listings in this section yet.</div><div>Try adjusting your filters.</div></div>';
        return;
      }

      echo '<div class="market-grid">';
      foreach ($items as $listing) {
        $companyName = (string) $listing['company_name'];
        $companyInitial = strtoupper(substr($companyName, 0, 1));
        $isApplied = isset($appliedInternshipIds[(int) $listing['internship_id']]);
        $skills = array_values(array_filter(array_map('trim', explode(',', (string) ($listing['required_skills'] ?? '')))));
        $score = $compatibilityScore($listing);
        $desc = trim((string) ($listing['description'] ?? ''));
        $descId = 'desc_' . md5($listing['title'] . $listing['company_name'] . $listing['internship_id']);
        $workSetup = trim((string) ($listing['work_setup'] ?? ''));

        echo '<article class="market-card">';
        echo '<div class="market-card-head">';
        echo '<div class="market-card-row">';
        echo '<div class="market-logo" style="background:' . marketplace_e(marketplace_company_gradient($companyName)) . '">' . marketplace_e($companyInitial !== '' ? $companyInitial : 'C') . '</div>';
        echo '<div style="min-width:0">';
        echo '<div class="market-card-title">' . marketplace_e((string) ($listing['title'] ?? 'Internship')) . '</div>';
        echo '<div class="market-card-company">' . marketplace_e($companyName) . ' ' . marketplace_status_badge((string) ($listing['company_badge_status'] ?? '')) . '</div>';
        echo '</div>';
        echo '</div>';
        if ($isExternalSection) {
          echo '<span class="market-section-pill">External API</span>';
        }
        echo '</div>';

        echo '<div class="market-card-meta">';
        echo '<span><i class="fas fa-map-marker-alt"></i> ' . marketplace_e((string) (($listing['location'] ?? '') !== '' ? $listing['location'] : 'Location not set')) . '</span>';
        echo '<span><i class="fas fa-clock"></i> ' . marketplace_e(marketplace_duration_label((int) ($listing['duration_weeks'] ?? 0))) . '</span>';
        if ($workSetup !== '') {
          echo '<span>' . marketplace_e($workSetup) . '</span>';
        }
        echo '</div>';

        if ($desc !== '') {
          echo '<p class="market-card-desc clamped" id="' . marketplace_e($descId) . '">' . marketplace_e($desc) . '</p>';
          if (mb_strlen($desc) > 140) {
            echo '<button type="button" class="market-link-btn" data-desc-target="' . marketplace_e($descId) . '">View more</button>';
          }
        }

        echo '<div class="market-skill-row">';
        if ($skills) {
          foreach (array_slice($skills, 0, 4) as $skillName) {
            $isMatch = isset($studentSkillNames[strtolower($skillName)]);
            echo '<span class="market-skill-chip' . ($isMatch ? ' match' : '') . '">' . marketplace_e($skillName) . '</span>';
          }
        } else {
          echo '<span class="market-skill-chip">No skills listed</span>';
        }
        echo '</div>';

        echo '<div class="market-card-foot">';
        echo '<div>';
        echo '<div class="market-allowance">&#8369;' . number_format((float) ($listing['allowance'] ?? 0), 0) . ' <small>/month</small></div>';
        echo '<div style="font-size:.73rem;color:#8a93a8;margin-top:2px">' . marketplace_e((string) ($listing['industry'] ?? 'General')) . ' · Match ' . (int) $score . '%</div>';
        echo '</div>';

        if ($isExternalSection && !empty($listing['external_url'])) {
          echo '<a class="btn btn-primary btn-sm" href="' . marketplace_e((string) $listing['external_url']) . '" target="_blank" rel="noopener noreferrer">View Source</a>';
        } elseif ($isApplied) {
          echo '<a class="btn btn-ghost btn-sm" href="' . marketplace_e($baseUrl) . '/layout.php?page=student/applications">Applied</a>';
        } else {
          echo '<a class="btn btn-primary btn-sm" href="' . marketplace_e(marketplace_detail_url($baseUrl, $currentFilters, (int) ($listing['internship_id'] ?? 0))) . '">View Details</a>';
        }
        echo '</div>';

        echo '</article>';
      }
      echo '</div>';
    };
    ?>

<style>
:root {
  --mk-bg:#f4f5f8;
  --mk-border:#e4e7ee;
  --mk-muted:#71768a;
  --mk-title:#1f2433;
  --mk-accent:#111111;
  --mk-accent-soft:#f1f1f1;
}
.market-shell { background:var(--mk-bg); border:1px solid #ebedf2; border-radius:22px; padding:16px; margin-bottom:16px; }
.market-layout { display:grid; grid-template-columns:280px 1fr; gap:16px; align-items:start; }
.market-filter-panel { background:#fff; border:1px solid var(--mk-border); border-radius:16px; padding:16px; position:sticky; top:14px; }
.market-filter-head { font-size:1.18rem; font-weight:800; color:var(--mk-title); margin-bottom:12px; }
.market-filter-group { border-top:1px solid #f1f2f6; padding-top:12px; margin-top:12px; }
.market-filter-title { font-size:.8rem; text-transform:uppercase; letter-spacing:.08em; color:#858aa1; margin-bottom:8px; font-weight:800; }
.market-input, .market-select { width:100%; border:1px solid var(--mk-border); border-radius:10px; padding:10px 12px; font-size:.85rem; background:#fff; color:#1f2937; }
.market-radio-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.market-radio-tile { border:1px solid var(--mk-border); background:#f8f9fc; color:#555d73; border-radius:10px; padding:9px 10px; font-size:.78rem; font-weight:700; text-align:center; cursor:pointer; transition:all .18s ease; }
.market-radio-tile.active { border-color:#111111; background:#111111; color:#ffffff; }
.market-main-panel { min-width:0; }
.market-hero { background:linear-gradient(110deg,#eceffd 0%,#e5eefb 46%,#cabdff 100%); border:1px solid #dbe0f4; border-radius:16px; padding:20px; margin-bottom:12px; position:relative; overflow:hidden; }
.market-hero::after { content:""; position:absolute; inset:0; background-image:radial-gradient(circle at 84% 28%, rgba(255,255,255,.95) 0 2px, transparent 3px), radial-gradient(circle at 76% 40%, rgba(255,255,255,.75) 0 2px, transparent 3px), radial-gradient(circle at 90% 44%, rgba(255,255,255,.72) 0 2px, transparent 3px); pointer-events:none; }
.market-hero h2 { margin:0; font-size:2rem; line-height:1.05; color:#212638; font-weight:800; }
.market-hero p { margin:8px 0 0; max-width:620px; color:#4f5871; font-size:.92rem; }
.market-search-row { display:grid; grid-template-columns:1fr 110px; gap:10px; margin-bottom:10px; }
.market-search-wrap { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--mk-border); border-radius:12px; padding:0 12px; }
.market-search-wrap i { color:#8d93a7; }
.market-search-wrap input { border:none; outline:none; width:100%; padding:11px 0; background:transparent; font-size:.88rem; }
.market-action-btn { border:none; border-radius:12px; background:#111111; color:#ffffff; font-weight:700; font-size:.86rem; cursor:pointer; }
.market-top-picks { border:1px solid #e4e6ef; border-radius:16px; background:linear-gradient(120deg,#fffaf5 0%,#f7f7ff 50%,#f2fbf6 100%); padding:14px; margin:8px 0 12px; }
.market-top-picks-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:8px; }
.market-top-picks-title { font-size:1.05rem; color:#1f2937; font-weight:700; line-height:1.25; letter-spacing:.01em; }
.market-top-picks-sub { font-size:.8rem; color:#6b7280; margin-top:3px; font-weight:500; }
.market-top-picks-refresh { width:34px; height:34px; border-radius:999px; border:1px solid #d4dae7; background:#fff; color:#111827; cursor:pointer; }
.market-picks-list { display:flex; flex-direction:column; gap:0; }
.market-pick-item { display:grid; grid-template-columns:42px 1fr auto; gap:10px; align-items:center; padding:10px 10px; border:1px solid #e5e7ee; border-left:4px solid var(--pick-accent, #94a3b8); border-radius:12px; margin-bottom:8px; background:#ffffff; }
.market-pick-item:first-child { border-top:none; }
.market-pick-logo { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:.95rem; }
.market-pick-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.market-pick-title { font-size:.88rem; font-weight:700; color:#111827; line-height:1.2; }
.market-pick-fit { font-size:.74rem; border-radius:999px; padding:3px 8px; background:#10b981; color:#fff; font-weight:700; white-space:nowrap; }
.market-pick-meta { color:#4b5563; font-size:.8rem; margin-top:2px; }
.market-pick-hint { color:#0f766e; font-size:.76rem; margin-top:4px; font-weight:600; }
.market-pick-reasons { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.market-pick-reason { font-size:.68rem; border:1px solid #dce1ea; background:#ffffff; color:#4b5563; border-radius:999px; padding:2px 8px; font-weight:600; }
.market-pick-actions { display:flex; align-items:center; gap:8px; }
.market-pick-link { font-size:.72rem; text-decoration:none; color:#111827; border:1px solid #d1d5db; border-radius:999px; padding:4px 10px; background:#fff; font-weight:700; }
.market-pick-dismiss { width:26px; height:26px; border-radius:999px; border:1px solid #d5d9e2; background:#fff; color:#111; cursor:pointer; }
.market-pick-item.hidden { display:none; }
.market-top-picks-empty { display:none; font-size:.8rem; color:#6b7280; padding:8px 0 2px; }
.market-top-picks-empty.show { display:block; }
.market-subhead { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin:6px 0 12px; }
.market-subhead-copy { color:#72788c; font-size:.83rem; }
.market-section { margin-top:14px; }
.market-section-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
.market-section-title { font-size:1rem; font-weight:800; color:#1d2437; }
.market-section-pill { font-size:.72rem; border-radius:999px; padding:3px 10px; border:1px solid #d8deef; color:#55607d; background:#fff; }
.market-toggle-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:10px 14px; border-radius:10px; border:1px solid #111111; background:#111111; color:#ffffff; font-size:.82rem; font-weight:800; text-decoration:none; box-shadow:none; }
.market-toggle-btn:hover { filter:brightness(1.05); }
.market-toggle-btn.off { background:#ffffff; border-color:#cfd4dc; color:#111111; box-shadow:none; }
.market-external-hint { margin-top:8px; font-size:.74rem; color:#5f6a84; }
.market-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
.market-card { background:#fff; border:1px solid var(--mk-border); border-radius:14px; padding:14px; display:flex; flex-direction:column; gap:9px; }
.market-card-head { display:flex; justify-content:space-between; gap:10px; }
.market-card-row { display:flex; gap:10px; min-width:0; }
.market-logo { width:42px; height:42px; border-radius:10px; color:#fff; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.market-card-title { font-size:1.05rem; font-weight:800; color:#1d2437; line-height:1.25; }
.market-card-company { font-size:.84rem; color:#70788e; margin-top:2px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.market-card-meta { display:flex; flex-wrap:wrap; gap:8px; font-size:.74rem; color:#6a738b; }
.market-card-meta span { background:#f6f7fb; border:1px solid #eceff6; border-radius:999px; padding:4px 8px; }
.market-card-desc { color:#4f596f; font-size:.82rem; line-height:1.5; margin:0; }
.market-card-desc.clamped { display:-webkit-box; line-clamp:2; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.market-link-btn { border:none; background:none; color:#111111; font-size:.78rem; font-weight:700; padding:0; cursor:pointer; }
.market-skill-row { display:flex; flex-wrap:wrap; gap:6px; }
.market-skill-chip { font-size:.69rem; border-radius:999px; padding:4px 8px; background:#f6f8fd; border:1px solid #e4e9f4; color:#526080; }
.market-skill-chip.match { background:#ecfdf5; border-color:#bbf7d0; color:#15803d; }
.market-card-foot { margin-top:auto; display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.market-allowance { font-size:.92rem; font-weight:800; color:#1d2437; }
.market-allowance small { font-size:.72rem; color:#8890a5; font-weight:600; }
.market-empty { background:#fff; border:1px dashed #dbe2ea; border-radius:16px; padding:28px; text-align:center; color:#6b7280; }
.market-empty i { font-size:1.8rem; color:#9ca3af; margin-bottom:10px; display:block; }
.market-results-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:14px 0 10px; }
.market-results-copy { font-size:.84rem; color:#6b7280; }
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
.market-detail-overlay .market-modal { width:980px; }
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
.market-source-note { margin:6px 0 2px; font-size:.77rem; color:#475569; display:flex; align-items:center; gap:6px; }
.market-pagination { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:12px; }
.market-pagination-copy { font-size:.76rem; color:#64748b; margin-right:2px; }
.market-page-btn { text-decoration:none; border:1px solid #d1d5db; border-radius:10px; padding:6px 10px; font-size:.76rem; font-weight:700; color:#111827; background:#fff; }
.market-page-btn.disabled { opacity:.45; pointer-events:none; }
.market-shell .btn.btn-primary { background:#111111 !important; border-color:#111111 !important; color:#ffffff !important; }
.market-shell .btn.btn-primary:hover { background:#000000 !important; border-color:#000000 !important; }
.market-shell .btn.btn-ghost { color:#111111 !important; border-color:#d1d5db !important; background:#ffffff !important; }
@media (max-width: 1100px) {
  .market-layout { grid-template-columns:1fr; }
  .market-filter-panel { position:static; }
}
@media (max-width: 900px) {
  .market-grid { grid-template-columns:1fr; }
  .market-detail-grid { grid-template-columns:1fr; }
  .market-modal-grid { grid-template-columns:1fr; }
}
</style>

<div class="market-shell">
  <form method="get" action="<?php echo marketplace_e($baseUrl); ?>/layout.php" id="marketFilterForm">
    <input type="hidden" name="page" value="student/marketplace">

    <div class="market-layout">
      <aside class="market-filter-panel">
        <div class="market-filter-head">Filter</div>

        <div class="market-filter-group" style="margin-top:0;padding-top:0;border-top:none">
          <div class="market-filter-title">Category</div>
          <input class="market-input" list="industryOptions" name="industry" placeholder="Type or choose industry" value="<?php echo marketplace_e($currentFilters['industry']); ?>">
          <datalist id="industryOptions">
            <?php foreach ($industries as $industry): ?>
              <option value="<?php echo marketplace_e($industry); ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="market-filter-group">
          <div class="market-filter-title">Work Setup</div>
          <div class="market-radio-grid" data-radio-group="work_setup">
            <?php foreach (['', 'On-site', 'Remote', 'Hybrid'] as $setup): ?>
              <?php
                $label = $setup === '' ? 'Any' : $setup;
                $isActive = ($currentFilters['work_setup'] ?? '') === $setup;
              ?>
              <label class="market-radio-tile<?php echo $isActive ? ' active' : ''; ?>">
                <input type="radio" name="work_setup" value="<?php echo marketplace_e($setup); ?>" <?php echo $isActive ? 'checked' : ''; ?> style="display:none">
                <?php echo marketplace_e($label); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="market-filter-group">
          <div class="market-filter-title">Location</div>
          <input class="market-input" list="locationOptions" name="location" placeholder="Type or choose location" value="<?php echo marketplace_e($currentFilters['location']); ?>">
          <datalist id="locationOptions">
            <?php foreach ($locations as $location): ?>
              <option value="<?php echo marketplace_e($location); ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="market-filter-group">
          <div class="market-filter-title">Duration</div>
          <select class="market-select" name="duration">
            <option value="" <?php echo ($currentFilters['duration'] ?? '') === '' ? 'selected' : ''; ?>>Any length</option>
            <option value="short" <?php echo ($currentFilters['duration'] ?? '') === 'short' ? 'selected' : ''; ?>>Short (1-8 weeks)</option>
            <option value="medium" <?php echo ($currentFilters['duration'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium (9-16 weeks)</option>
            <option value="long" <?php echo ($currentFilters['duration'] ?? '') === 'long' ? 'selected' : ''; ?>>Long (17+ weeks)</option>
          </select>
        </div>

        <div class="market-filter-group">
          <div class="market-filter-title">Allowance</div>
          <select class="market-select" name="allowance_range">
            <option value="" <?php echo ($currentFilters['allowance_range'] ?? '') === '' ? 'selected' : ''; ?>>Any allowance</option>
            <option value="unpaid" <?php echo ($currentFilters['allowance_range'] ?? '') === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            <option value="low" <?php echo ($currentFilters['allowance_range'] ?? '') === 'low' ? 'selected' : ''; ?>>Low (P1 - P3,000)</option>
            <option value="mid" <?php echo ($currentFilters['allowance_range'] ?? '') === 'mid' ? 'selected' : ''; ?>>Mid (P3,001 - P6,000)</option>
            <option value="high" <?php echo ($currentFilters['allowance_range'] ?? '') === 'high' ? 'selected' : ''; ?>>High (Above P6,000)</option>
          </select>
        </div>

        <input type="hidden" name="include_external" value="<?php echo $showExternal ? '1' : '0'; ?>">
  <input type="hidden" name="external_page" value="1">

        <div class="market-filter-group">
          <div class="market-filter-title">Sort</div>
          <select class="market-select" name="sort">
            <option value="latest" <?php echo $sortFilter === 'latest' ? 'selected' : ''; ?>>Latest first</option>
            <option value="bestmatch" <?php echo $sortFilter === 'bestmatch' ? 'selected' : ''; ?>>Best match</option>
            <option value="allowance_high" <?php echo $sortFilter === 'allowance_high' ? 'selected' : ''; ?>>Allowance: High to Low</option>
            <option value="allowance_low" <?php echo $sortFilter === 'allowance_low' ? 'selected' : ''; ?>>Allowance: Low to High</option>
            <option value="title_az" <?php echo $sortFilter === 'title_az' ? 'selected' : ''; ?>>Title: A to Z</option>
            <option value="title_za" <?php echo $sortFilter === 'title_za' ? 'selected' : ''; ?>>Title: Z to A</option>
          </select>
        </div>

        <div class="market-filter-group" style="border-top:none;padding-top:8px;margin-top:6px;">
          <a href="<?php echo marketplace_e($toggleExternalUrl); ?>" class="market-toggle-btn<?php echo $showExternal ? '' : ' off'; ?>" style="width:100%;">
            <?php if ($showExternal): ?>
              <i class="fas fa-globe"></i> External Module: ON
            <?php else: ?>
              <i class="fas fa-globe"></i> External Module: OFF
            <?php endif; ?>
          </a>
          <div class="market-external-hint">Toggle to include opportunities from your external listings module.</div>
        </div>
      </aside>

      <div class="market-main-panel">
        <div class="market-hero">
          <h2>Find your dream internship here</h2>
          <p>Explore opportunities from SkillHive partner employers and verified external API listings in one place.</p>
        </div>

        <div class="market-search-row">
          <div class="market-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="Search your internship" value="<?php echo marketplace_e($currentFilters['q']); ?>">
          </div>
          <button type="submit" class="market-action-btn">Search</button>
        </div>

        <?php if ($topPicks): ?>
          <section class="market-top-picks" id="topPicksCard">
            <div class="market-top-picks-head">
              <div>
                <div class="market-top-picks-title">Top job picks for you</div>
                <div class="market-top-picks-sub">Based on your profile, skills, and recent marketplace activity.</div>
              </div>
              <button type="button" class="market-top-picks-refresh" id="refreshTopPicks" title="Refresh picks"><i class="fas fa-redo"></i></button>
            </div>

            <div class="market-picks-list" id="topPicksList">
              <?php foreach ($topPicks as $pick): ?>
                <?php
                  $pickCompany = (string) ($pick['company_name'] ?? 'Company');
                  $pickInitial = strtoupper(substr($pickCompany, 0, 1));
                  $pickTitle = (string) ($pick['title'] ?? 'Internship');
                  $pickLocation = trim((string) ($pick['location'] ?? 'Location not set'));
                  $pickSetup = trim((string) ($pick['work_setup'] ?? 'On-site'));
                  $pickScore = $compatibilityScore($pick);
                  $pickDetailUrl = marketplace_detail_url($baseUrl, $currentFilters, (int) ($pick['internship_id'] ?? 0));
                  $pickAccentPalette = ['#10b981', '#38bdf8', '#f59e0b', '#a78bfa', '#14b8a6', '#f97316'];
                  $pickAccent = $pickAccentPalette[abs(crc32(strtolower($pickCompany))) % count($pickAccentPalette)];
                  $pickSkills = array_values(array_filter(array_map('trim', explode(',', (string) ($pick['required_skills'] ?? '')))));
                  $pickRequiredCount = count($pickSkills);
                  $pickMatchedCount = 0;
                  foreach ($pickSkills as $skillName) {
                    if (isset($studentSkillNames[strtolower($skillName)])) {
                      $pickMatchedCount++;
                    }
                  }

                  $pickReasons = [];
                  if ($pickRequiredCount > 0) {
                    $pickReasons[] = 'Matched ' . $pickMatchedCount . '/' . $pickRequiredCount . ' skills';
                  } else {
                    $pickReasons[] = 'No strict skills required';
                  }

                  $pickAllowance = (float) ($pick['allowance'] ?? 0);
                  if ($pickAllowance >= 6000) {
                    $pickReasons[] = 'High allowance';
                  }

                  $postedTs = strtotime((string) ($pick['posted_at'] ?? ''));
                  if ($postedTs !== false && $postedTs >= strtotime('-7 days')) {
                    $pickReasons[] = 'Fresh posting';
                  }

                  if (in_array($pickSetup, ['Remote', 'Hybrid'], true)) {
                    $pickReasons[] = $pickSetup . ' setup';
                  }
                ?>
                <article class="market-pick-item" style="--pick-accent: <?php echo marketplace_e($pickAccent); ?>;">
                  <div class="market-pick-logo" style="background:<?php echo marketplace_e(marketplace_company_gradient($pickCompany)); ?>"><?php echo marketplace_e($pickInitial !== '' ? $pickInitial : 'C'); ?></div>
                  <div>
                    <div class="market-pick-top">
                      <div class="market-pick-title"><?php echo marketplace_e($pickTitle); ?></div>
                      <span class="market-pick-fit"><?php echo (int) $pickScore; ?>% fit</span>
                    </div>
                    <div class="market-pick-meta"><?php echo marketplace_e($pickCompany); ?> · <?php echo marketplace_e($pickLocation); ?> (<?php echo marketplace_e($pickSetup); ?>)</div>
                    <div class="market-pick-hint">Match strength: <?php echo (int) $pickScore; ?>%</div>
                    <div class="market-pick-reasons">
                      <?php foreach (array_slice($pickReasons, 0, 3) as $reason): ?>
                        <span class="market-pick-reason"><?php echo marketplace_e((string) $reason); ?></span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="market-pick-actions">
                    <a class="market-pick-link" href="<?php echo marketplace_e($pickDetailUrl); ?>">View</a>
                    <button type="button" class="market-pick-dismiss" title="Hide this pick"><i class="fas fa-times"></i></button>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            <div class="market-top-picks-empty" id="topPicksEmpty">All picks were hidden. Click refresh to show them again.</div>
          </section>
        <?php endif; ?>

        <div class="market-subhead">
          <div class="market-subhead-copy">Showing <strong><?php echo count($localListings) + count($externalListingsPage); ?></strong> result<?php echo (count($localListings) + count($externalListingsPage)) === 1 ? '' : 's'; ?>.</div>
          <div style="display:flex;align-items:center;gap:8px;">
            <a href="<?php echo marketplace_e($toggleExternalUrl); ?>" class="market-toggle-btn<?php echo $showExternal ? '' : ' off'; ?>">
              <?php echo $showExternal ? 'External Module: ON' : 'Enable External Module'; ?>
            </a>
            <?php if ($currentFilters['q'] !== '' || $currentFilters['industry'] !== '' || $currentFilters['location'] !== '' || ($currentFilters['work_setup'] ?? '') !== '' || ($currentFilters['duration'] ?? '') !== '' || ($currentFilters['allowance_range'] ?? '') !== '' || $showExternal || $sortFilter !== 'latest' || $selectedDetailId > 0): ?>
              <a href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/marketplace" class="btn btn-ghost btn-sm">Clear Filters</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$localListings && !$externalListings): ?>
          <div class="market-empty">
            <i class="fas fa-store-slash"></i>
            <div style="font-weight:700;color:#111;margin-bottom:6px">No internships match your filters.</div>
            <div>Try a broader search or clear the filters.</div>
          </div>
        <?php else: ?>
          <section class="market-section">
            <div class="market-section-head">
              <div class="market-section-title">Local System Opportunities</div>
              <span class="market-section-pill"><?php echo count($localListings); ?> listing<?php echo count($localListings) === 1 ? '' : 's'; ?></span>
            </div>
            <?php $renderListings($localListings, false); ?>
          </section>

          <?php if ($showExternal): ?>
            <section class="market-section">
              <div class="market-section-head">
                <div class="market-section-title">External API Opportunities</div>
                <span class="market-section-pill"><?php echo count($externalListingsPage); ?> of <?php echo $externalTotalCount; ?> listing<?php echo $externalTotalCount === 1 ? '' : 's'; ?></span>
              </div>
              <?php $renderListings($externalListingsPage, true); ?>

              <?php if ($externalTotalCount > $externalPerPage): ?>
                <div class="market-pagination">
                  <span class="market-pagination-copy">Page <?php echo $externalPage; ?> of <?php echo $externalTotalPages; ?></span>
                  <?php $prevUrl = $buildExternalPageUrl($externalPage - 1); ?>
                  <?php $nextUrl = $buildExternalPageUrl($externalPage + 1); ?>
                  <a class="market-page-btn<?php echo $externalPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo marketplace_e($prevUrl); ?>">Prev</a>
                  <a class="market-page-btn<?php echo $externalPage >= $externalTotalPages ? ' disabled' : ''; ?>" href="<?php echo marketplace_e($nextUrl); ?>">Next</a>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

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
  <div class="market-overlay open market-detail-overlay" id="detailModal" onclick="if(event.target===this)closeDetailModal()">
    <div class="market-modal">
      <div class="market-modal-head">
        <div>
          <div class="market-modal-title">Internship Details</div>
          <div class="market-modal-sub"><?php echo marketplace_e((string) $detailListing['title']); ?> · <?php echo marketplace_e($detailCompany); ?></div>
        </div>
        <button class="market-modal-close" type="button" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="market-modal-body">
  <div class="market-detail-card" style="margin-bottom:0; border:none; padding:0;">
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
            <input type="hidden" name="work_setup" value="<?php echo marketplace_e((string) ($currentFilters['work_setup'] ?? '')); ?>">
            <input type="hidden" name="duration" value="<?php echo marketplace_e((string) ($currentFilters['duration'] ?? '')); ?>">
            <input type="hidden" name="allowance_range" value="<?php echo marketplace_e((string) ($currentFilters['allowance_range'] ?? '')); ?>">
            <input type="hidden" name="sort" value="<?php echo marketplace_e($sortFilter); ?>">
            <input type="hidden" name="include_external" value="<?php echo $showExternal ? '1' : '0'; ?>">
            <input type="hidden" name="external_page" value="<?php echo (int) ($currentFilters['external_page'] ?? 1); ?>">

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
      </div>
    </div>
  </div>
<?php elseif ($selectedDetailId > 0): ?>
  <div class="market-overlay open market-detail-overlay" id="detailUnavailableModal" onclick="if(event.target===this)closeDetailModal()">
    <div class="market-modal" style="max-width:560px;">
      <div class="market-modal-head">
        <div>
          <div class="market-modal-title">Internship details unavailable</div>
          <div class="market-modal-sub">The internship may already be closed or removed.</div>
        </div>
        <button class="market-modal-close" type="button" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="market-modal-body">
        <div class="market-empty" style="margin:0;">
          <i class="fas fa-exclamation-circle"></i>
          <div style="font-weight:700;color:#111;margin-bottom:6px">Internship details unavailable.</div>
          <div>The internship may already be closed or removed.</div>
        </div>
        <div style="display:flex;justify-content:flex-end;">
          <a class="btn btn-ghost btn-sm" href="<?php echo marketplace_e($baseUrl); ?>/layout.php?page=student/marketplace">Back to listings</a>
        </div>
      </div>
    </div>
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
document.querySelectorAll('[data-radio-group]').forEach(function (group) {
  var tiles = group.querySelectorAll('.market-radio-tile');
  tiles.forEach(function (tile) {
    tile.addEventListener('click', function () {
      tiles.forEach(function (item) { item.classList.remove('active'); });
      tile.classList.add('active');
      var input = tile.querySelector('input[type="radio"]');
      if (input) {
        input.checked = true;
      }
    });
  });
});

function closeDetailModal() {
  var query = new URLSearchParams(window.location.search);
  query.delete('detail');
  query.delete('open_apply');
  window.location.href = window.location.pathname + (query.toString() ? '?' + query.toString() : '');
}

document.querySelectorAll('[data-desc-target]').forEach(function (button) {
  button.addEventListener('click', function () {
    var targetId = button.getAttribute('data-desc-target');
    var desc = targetId ? document.getElementById(targetId) : null;
    if (!desc) {
      return;
    }

    desc.classList.remove('clamped');
    button.remove();
  });
});

(function () {
  var topList = document.getElementById('topPicksList');
  var refreshBtn = document.getElementById('refreshTopPicks');
  var emptyState = document.getElementById('topPicksEmpty');
  if (!topList) {
    return;
  }

  function updateTopPickEmptyState() {
    var visibleItems = Array.prototype.slice.call(topList.querySelectorAll('.market-pick-item')).filter(function (item) {
      return !item.classList.contains('hidden');
    });

    if (emptyState) {
      emptyState.classList.toggle('show', visibleItems.length === 0);
    }
  }

  topList.querySelectorAll('.market-pick-dismiss').forEach(function (button) {
    button.addEventListener('click', function () {
      var item = button.closest('.market-pick-item');
      if (item) {
        item.classList.add('hidden');
      }
      updateTopPickEmptyState();
    });
  });

  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      var items = Array.prototype.slice.call(topList.querySelectorAll('.market-pick-item'));
      items.forEach(function (item) { item.classList.remove('hidden'); });
      items.sort(function () { return Math.random() - 0.5; }).forEach(function (item) { topList.appendChild(item); });
      updateTopPickEmptyState();
    });
  }

  updateTopPickEmptyState();
})();

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
