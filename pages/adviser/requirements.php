<?php
require_once __DIR__ . '/../../backend/db_connect.php';

if (!function_exists('adviser_requirements_e')) {
    function adviser_requirements_e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adviser_requirements_initials')) {
    function adviser_requirements_initials(?string $firstName, ?string $lastName): string
    {
        $firstName = trim((string)($firstName ?? ''));
        $lastName = trim((string)($lastName ?? ''));
        $initials = '';

        if ($firstName !== '') {
            $initials .= strtoupper(substr($firstName, 0, 1));
        }
        if ($lastName !== '') {
            $initials .= strtoupper(substr($lastName, 0, 1));
        }

        return $initials !== '' ? $initials : 'NA';
    }
}

if (!function_exists('adviser_requirements_section_label')) {
    function adviser_requirements_section_label(?string $track, ?string $section): string
    {
        $cleanSection = strtoupper(trim((string)($section ?? '')));
        $cleanSection = preg_replace('/\s+/', '', $cleanSection);
        $cleanSection = $cleanSection !== null ? $cleanSection : '';

        if ($cleanSection === '') {
            return 'Unassigned';
        }

        $track = strtolower(trim((string)($track ?? '')));
        if ($track === 'business analytics') {
            return 'BA ' . $cleanSection;
        }
        if ($track === 'networking') {
            return 'NT ' . $cleanSection;
        }

        return $cleanSection;
    }
}

if (!function_exists('adviser_requirements_format_date')) {
    function adviser_requirements_format_date(?string $rawDate): string
    {
        $value = trim((string)($rawDate ?? ''));
        if ($value === '') {
            return 'Not yet';
        }

        try {
            return (new DateTime($value))->format('M j, Y g:i A');
        } catch (Throwable $e) {
            return 'Not yet';
        }
    }
}

if (!function_exists('adviser_requirements_status_meta')) {
    function adviser_requirements_status_meta(?string $status): array
    {
        $status = trim((string)($status ?? ''));
        if ($status === '') {
            $status = 'Pending';
        }

        $normalized = strtolower($status);
        if ($normalized === 'approved') {
            return ['label' => 'Approved', 'class' => 'approved', 'icon' => 'fa-circle-check'];
        }
        if ($normalized === 'rejected') {
            return ['label' => 'Rejected', 'class' => 'rejected', 'icon' => 'fa-circle-xmark'];
        }
        if ($normalized === 'submitted') {
            return ['label' => 'Submitted', 'class' => 'submitted', 'icon' => 'fa-file-circle-check'];
        }

        return ['label' => 'Pending', 'class' => 'pending', 'icon' => 'fa-clock'];
    }
}

if (!function_exists('adviser_requirements_file_type_label')) {
    function adviser_requirements_file_type_label(string $extension): string
    {
        $extension = strtolower(trim($extension));
        if ($extension === 'pdf') {
            return 'PDF';
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return 'Image';
        }
        if ($extension === 'docx') {
            return 'DOCX';
        }
        if ($extension === 'doc') {
            return 'DOC';
        }

        return $extension !== '' ? strtoupper($extension) : 'File';
    }
}

if (!function_exists('adviser_requirements_assigned_filter_sql')) {
    function adviser_requirements_assigned_filter_sql(): string
    {
        return 'COALESCE(NULLIF(LOWER(TRIM(aa.status)), ""), "active") = "active"';
    }
}

if (!function_exists('adviser_requirements_section_sql')) {
    function adviser_requirements_section_sql(): string
    {
        return 'CASE
            WHEN COALESCE(NULLIF(TRIM(s.section), ""), "") = "" THEN "Unassigned"
            WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "business analytics" THEN CONCAT("BA ", TRIM(s.section))
            WHEN LOWER(TRIM(COALESCE(s.track, ""))) = "networking" THEN CONCAT("NT ", TRIM(s.section))
            ELSE TRIM(s.section)
        END';
    }
}

if (!function_exists('adviser_requirements_filter_options')) {
    function adviser_requirements_filter_options(PDO $pdo, int $adviserId): array
    {
        $sectionExpr = adviser_requirements_section_sql();
        $assignedFilter = adviser_requirements_assigned_filter_sql();

        $sectionStmt = $pdo->prepare(
            'SELECT DISTINCT ' . $sectionExpr . ' AS section_label
             FROM adviser_assignment aa
             INNER JOIN student s ON s.student_id = aa.student_id
             WHERE aa.adviser_id = :adviser_id
               AND ' . $assignedFilter . '
             ORDER BY section_label ASC'
        );
        $sectionStmt->execute([':adviser_id' => $adviserId]);
        $sections = array_values(array_filter(array_map('trim', $sectionStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));

        $requirementStmt = $pdo->prepare(
            'SELECT
                requirement_id,
                name,
                COALESCE(NULLIF(TRIM(phase), ""), "Uncategorized") AS phase_label
             FROM requirement
             WHERE applicable_to IN ("Student", "Both")
             ORDER BY FIELD(COALESCE(NULLIF(TRIM(phase), ""), "Uncategorized"), "Pre-OJT", "During OJT", "Post-OJT", "Uncategorized"),
                      COALESCE(NULLIF(TRIM(phase), ""), "Uncategorized") ASC,
                      sort_order ASC,
                      requirement_id ASC'
        );
        $requirementStmt->execute();
        $requirements = $requirementStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'sections' => $sections,
            'requirements' => $requirements,
            'submission_states' => [
                'submitted' => 'Submitted',
                'missing' => 'Not submitted',
            ],
        ];
    }
}

if (!function_exists('adviser_requirements_rows')) {
    function adviser_requirements_rows(PDO $pdo, int $adviserId, array $filters, string $baseUrl): array
    {
        $sectionExpr = adviser_requirements_section_sql();
        $assignedFilter = adviser_requirements_assigned_filter_sql();
        $requirementId = (int)($filters['requirement_id'] ?? 0);
        if ($requirementId <= 0) {
            return [];
        }

        $statusExpr = 'CASE
            WHEN LOWER(TRIM(COALESCE(sr.status, ""))) = "approved" THEN "Approved"
            WHEN LOWER(TRIM(COALESCE(sr.status, ""))) = "rejected" THEN "Rejected"
            WHEN LOWER(TRIM(COALESCE(sr.status, ""))) = "submitted" THEN "Submitted"
            ELSE "Pending"
        END';
        $phaseExpr = 'COALESCE(NULLIF(TRIM(r.phase), ""), "Uncategorized")';

        $sql = '
            SELECT
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.email,
                s.program,
                s.track,
                s.section,
                ' . $sectionExpr . ' AS section_label,
                r.requirement_id,
                r.name AS requirement_name,
                r.description AS requirement_description,
                ' . $phaseExpr . ' AS phase_label,
                r.is_mandatory,
                sr.req_submission_id,
                ' . $statusExpr . ' AS status_label,
                sr.file_path,
                sr.submitted_at,
                sr.reviewed_at,
                sr.deadline,
                sr.notes
            FROM (
                SELECT DISTINCT aa.student_id
                FROM adviser_assignment aa
                WHERE aa.adviser_id = :adviser_id
                  AND ' . $assignedFilter . '
            ) aa
            INNER JOIN student s ON s.student_id = aa.student_id
            INNER JOIN requirement r ON r.requirement_id = :requirement_id AND r.applicable_to IN ("Student", "Both")
            LEFT JOIN (
                SELECT sr1.*
                FROM student_requirement sr1
                INNER JOIN (
                    SELECT student_id, requirement_id, MAX(req_submission_id) AS latest_submission_id
                    FROM student_requirement
                    GROUP BY student_id, requirement_id
                ) latest_sr ON latest_sr.latest_submission_id = sr1.req_submission_id
            ) sr ON sr.student_id = s.student_id AND sr.requirement_id = r.requirement_id
            WHERE 1=1';

        $params = [
            ':adviser_id' => $adviserId,
            ':requirement_id' => $requirementId,
        ];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= '
              AND (
                CONCAT(COALESCE(s.first_name, ""), " ", COALESCE(s.last_name, "")) LIKE :search
                OR COALESCE(s.student_number, "") LIKE :search
                OR COALESCE(s.email, "") LIKE :search
                OR COALESCE(s.program, "") LIKE :search
                OR COALESCE(s.track, "") LIKE :search
                OR COALESCE(s.section, "") LIKE :search
                OR ' . $sectionExpr . ' LIKE :search
                OR COALESCE(r.name, "") LIKE :search
                OR COALESCE(r.description, "") LIKE :search
              )';
            $params[':search'] = '%' . $search . '%';
        }

        $section = trim((string)($filters['section'] ?? ''));
        if ($section !== '') {
            $sql .= ' AND ' . $sectionExpr . ' = :section';
            $params[':section'] = $section;
        }

        $submissionState = trim((string)($filters['status'] ?? ''));
        if ($submissionState === 'submitted') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(sr.file_path), ""), "") <> ""';
        } elseif ($submissionState === 'missing') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(sr.file_path), ""), "") = ""';
        }

        $sql .= '
            ORDER BY
                s.last_name ASC,
                s.first_name ASC,
                CASE WHEN COALESCE(NULLIF(TRIM(sr.file_path), ""), "") <> "" THEN 0 ELSE 1 END,
                sr.submitted_at DESC,
                FIELD(' . $phaseExpr . ', "Pre-OJT", "During OJT", "Post-OJT", "Uncategorized"),
                r.sort_order ASC,
                r.requirement_id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $filePath = str_replace('\\', '/', trim((string)($row['file_path'] ?? '')));
            $submissionId = (int)($row['req_submission_id'] ?? 0);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $hasFile = $submissionId > 0 && $filePath !== '';
            $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));

            $row['student_name'] = $studentName !== '' ? $studentName : 'Unnamed Student';
            $row['initials'] = adviser_requirements_initials((string)($row['first_name'] ?? ''), (string)($row['last_name'] ?? ''));
            $row['file_path_clean'] = $filePath;
            $row['file_name'] = $filePath !== '' ? basename($filePath) : '';
            $row['file_extension'] = $extension;
            $row['file_type_label'] = adviser_requirements_file_type_label($extension);
            $row['has_file'] = $hasFile;
            $row['preview_url'] = $hasFile ? rtrim($baseUrl, '/') . '/pages/adviser/requirements_preview.php?id=' . $submissionId : '';
            $row['preview_src'] = $row['preview_url'] . ($extension === 'pdf' ? '#toolbar=0&navpanes=0&scrollbar=1' : '');
            $row['status_meta'] = adviser_requirements_status_meta((string)($row['status_label'] ?? ''));
            $row['submitted_label'] = adviser_requirements_format_date((string)($row['submitted_at'] ?? ''));
            $row['reviewed_label'] = adviser_requirements_format_date((string)($row['reviewed_at'] ?? ''));
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('adviser_requirements_stats')) {
    function adviser_requirements_stats(array $rows): array
    {
        $stats = [
            'total' => count($rows),
            'with_file' => 0,
            'pending' => 0,
            'submitted' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($rows as $row) {
            if (!empty($row['has_file'])) {
                $stats['with_file']++;
            }

            $status = strtolower((string)($row['status_meta']['label'] ?? 'Pending'));
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }
}

$baseUrl = isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : '/SkillHive';
$adviserId = (int)($_SESSION['adviser_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));

$selected = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'section' => trim((string)($_GET['section'] ?? '')),
    'requirement_id' => (int)($_GET['requirement_id'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
];

$filterOptions = [
    'sections' => [],
    'requirements' => [],
    'submission_states' => [
        'submitted' => 'Submitted',
        'missing' => 'Not submitted',
    ],
];
$rows = [];
$loadError = '';

if ($adviserId > 0) {
    try {
        $filterOptions = adviser_requirements_filter_options($pdo, $adviserId);
        if ($selected['requirement_id'] <= 0 && !empty($filterOptions['requirements'])) {
            $selected['requirement_id'] = (int)($filterOptions['requirements'][0]['requirement_id'] ?? 0);
        }
        $rows = adviser_requirements_rows($pdo, $adviserId, $selected, $baseUrl);
    } catch (Throwable $e) {
        $loadError = 'Unable to load student requirements right now.';
    }
}

$stats = adviser_requirements_stats($rows);
$selectedRequirementName = 'No requirement selected';
$selectedRequirementPhase = '';
foreach (($filterOptions['requirements'] ?? []) as $requirementOption) {
    if ((int)($requirementOption['requirement_id'] ?? 0) === (int)$selected['requirement_id']) {
        $selectedRequirementName = trim((string)($requirementOption['name'] ?? 'Requirement'));
        $selectedRequirementPhase = trim((string)($requirementOption['phase_label'] ?? ''));
        break;
    }
}

$selectedPreviewId = (int)($_GET['preview'] ?? 0);
$previewRow = null;

foreach ($rows as $row) {
    if (empty($row['has_file'])) {
        continue;
    }
    if ($selectedPreviewId > 0 && (int)($row['req_submission_id'] ?? 0) === $selectedPreviewId) {
        $previewRow = $row;
        break;
    }
    if ($previewRow === null) {
        $previewRow = $row;
    }
}
?>

<style>
  .adv-req-page { display:flex; flex-direction:column; gap:18px; color:var(--text); min-width:0; }
  .adv-req-header { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; flex-wrap:wrap; }
  .adv-req-title { margin:0; font-size:1.55rem; font-weight:900; color:#050505; letter-spacing:0; }
  .adv-req-subtitle { margin:6px 0 0; color:var(--text3); font-size:.94rem; line-height:1.5; }
  .adv-req-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:14px; border:1px solid var(--border); border-radius:16px; background:#fff; box-shadow:var(--card-shadow); min-width:0; }
  .adv-req-filters { display:flex; align-items:center; gap:10px; flex-wrap:wrap; flex:1 1 auto; min-width:0; }
  .adv-req-search { height:42px; min-width:260px; flex:1 1 280px; display:flex; align-items:center; gap:9px; padding:0 14px; border:1px solid var(--border); border-radius:14px; background:#fff; color:var(--text3); }
  .adv-req-search input { width:100%; border:0; outline:0; background:transparent; color:var(--text); font-size:.9rem; }
  .adv-req-select { height:42px; min-width:0; border:1px solid var(--border); border-radius:14px; background:#fff; color:var(--text); padding:0 36px 0 12px; font-size:.88rem; outline:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .adv-req-select[name="section"] { flex:0 0 170px; width:170px; }
  .adv-req-select[name="requirement_id"] { flex:0 0 360px; width:360px; max-width:100%; }
  .adv-req-select[name="status"] { flex:0 0 150px; width:150px; }
  .adv-req-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:42px; padding:0 16px; border-radius:14px; border:1px solid var(--border); background:#fff; color:#111; text-decoration:none; font-size:.86rem; font-weight:800; cursor:pointer; }
  .adv-req-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
  .adv-req-stat { min-height:86px; padding:14px; border:1px solid var(--border); border-radius:16px; background:#fff; box-shadow:var(--card-shadow); display:flex; flex-direction:column; justify-content:center; gap:4px; }
  .adv-req-stat-value { font-size:1.35rem; font-weight:900; color:#050505; }
  .adv-req-stat-label { font-size:.78rem; color:var(--text3); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .adv-req-workspace { display:grid; grid-template-columns:minmax(0,1fr) 400px; gap:16px; align-items:start; min-width:0; }
  .adv-req-card { border:1px solid var(--border); border-radius:16px; background:#fff; box-shadow:var(--card-shadow); overflow:hidden; min-width:0; }
  .adv-req-card-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .adv-req-card-head-main { display:flex; flex-direction:column; gap:4px; flex:1 1 auto; min-width:0; }
  .adv-req-card-head-main .adv-req-muted { max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .adv-req-card-title { margin:0; font-size:.98rem; font-weight:900; color:#050505; }
  .adv-req-card-tools { display:flex; align-items:center; justify-content:flex-end; gap:10px; flex:0 0 auto; flex-wrap:nowrap; }
  .adv-req-count { color:var(--text3); font-size:.82rem; font-weight:700; min-width:88px; text-align:right; white-space:nowrap; }
  .adv-req-live-search { height:38px; flex:0 0 230px; width:230px; display:flex; align-items:center; gap:8px; padding:0 12px; border:1px solid var(--border); border-radius:12px; background:#fff; color:var(--text3); }
  .adv-req-live-search input { width:100%; border:0; outline:0; background:transparent; color:var(--text); font-size:.84rem; }
  .adv-req-table-wrap { overflow:auto; max-height:690px; }
  .adv-req-table { width:100%; min-width:820px; table-layout:fixed; border-collapse:separate; border-spacing:0; }
  .adv-req-table th { position:sticky; top:0; z-index:1; padding:12px 14px; text-align:left; border-bottom:1px solid var(--border); background:#fbfbfb; color:var(--text3); font-size:.72rem; font-weight:900; text-transform:uppercase; letter-spacing:.05em; }
  .adv-req-table td { padding:13px 14px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:.86rem; background:#fff; }
  .adv-req-table tr:last-child td { border-bottom:0; }
  .adv-req-table tr.is-active td { background:#f0fdfa; }
  .adv-req-table tr.is-hidden { display:none; }
  .adv-req-student { display:flex; align-items:center; gap:10px; min-width:0; }
  .adv-req-avatar { width:38px; height:38px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#111,#12b3ac); color:#fff; font-size:.82rem; font-weight:900; flex:0 0 auto; }
  .adv-req-name { margin:0; font-size:.9rem; font-weight:900; color:#050505; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .adv-req-muted { margin:3px 0 0; color:var(--text3); font-size:.78rem; overflow:hidden; text-overflow:ellipsis; }
  .adv-req-requirement { max-width:260px; }
  .adv-req-reqname { margin:0; color:#111; font-size:.88rem; font-weight:850; line-height:1.3; }
  .adv-req-badge { display:inline-flex; align-items:center; gap:6px; min-height:26px; padding:0 9px; border-radius:999px; font-size:.75rem; font-weight:850; white-space:nowrap; }
  .adv-req-badge.phase { background:#f8fafc; color:#334155; border:1px solid #e2e8f0; }
  .adv-req-badge.submitted { background:#ecfeff; color:#0f766e; }
  .adv-req-badge.approved { background:#ecfdf5; color:#15803d; }
  .adv-req-badge.rejected { background:#fff1f2; color:#be123c; }
  .adv-req-badge.pending { background:#f8fafc; color:#64748b; }
  .adv-req-file { display:flex; flex-direction:column; gap:3px; min-width:130px; }
  .adv-req-file strong { color:#111; font-size:.82rem; }
  .adv-req-file span { color:var(--text3); font-size:.76rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .adv-req-preview-btn { width:92px; display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:34px; padding:0 12px; border:1px solid #111; border-radius:12px; background:#111; color:#fff; font-size:.78rem; font-weight:850; cursor:pointer; }
  .adv-req-preview-btn:disabled { border-color:#e5e7eb; background:#f8fafc; color:#94a3b8; cursor:default; }
  .adv-req-preview { position:sticky; top:18px; min-height:560px; }
  .adv-req-preview-head { padding:16px; border-bottom:1px solid var(--border); }
  .adv-req-preview-title { margin:0; font-size:1rem; font-weight:900; color:#050505; line-height:1.35; }
  .adv-req-preview-meta { margin:6px 0 0; color:var(--text3); font-size:.82rem; line-height:1.45; }
  .adv-req-preview-frame-wrap { min-height:520px; background:#f8fafc; }
  .adv-req-preview-frame { width:100%; height:620px; border:0; background:#fff; display:block; }
  .adv-req-preview-empty { min-height:520px; display:flex; align-items:center; justify-content:center; text-align:center; padding:24px; color:#64748b; }
  .adv-req-preview-empty i { display:block; margin-bottom:12px; color:#cbd5e1; font-size:2.4rem; }
  .adv-req-alert { padding:14px 16px; border:1px solid #fecaca; background:#fff1f2; color:#991b1b; border-radius:16px; font-size:.9rem; }
  .adv-req-empty { padding:34px 18px; text-align:center; color:var(--text3); }
  @media (max-width:1180px) { .adv-req-workspace { grid-template-columns:minmax(0,1fr); } .adv-req-preview { position:static; } }
  @media (max-width:900px) { .adv-req-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .adv-req-toolbar { align-items:stretch; } .adv-req-btn, .adv-req-select, .adv-req-search, .adv-req-live-search { width:100%; min-width:0; } .adv-req-card-tools { width:100%; justify-content:stretch; } }
</style>

<div class="adv-req-page">
  <?php if ($loadError !== ''): ?>
    <div class="adv-req-alert"><?php echo adviser_requirements_e($loadError); ?></div>
  <?php endif; ?>

  <form class="adv-req-toolbar" method="get" action="<?php echo adviser_requirements_e($baseUrl); ?>/layout.php">
    <input type="hidden" name="page" value="adviser/requirements">

    <div class="adv-req-filters">
      <select class="adv-req-select" name="section" aria-label="Filter by section">
        <option value="">All sections</option>
        <?php foreach (($filterOptions['sections'] ?? []) as $sectionOption): ?>
          <option value="<?php echo adviser_requirements_e($sectionOption); ?>" <?php echo $selected['section'] === $sectionOption ? 'selected' : ''; ?>><?php echo adviser_requirements_e($sectionOption); ?></option>
        <?php endforeach; ?>
      </select>

      <select class="adv-req-select" name="requirement_id" aria-label="Filter by requirement">
        <?php if (empty($filterOptions['requirements'])): ?>
          <option value="">No requirements configured</option>
        <?php endif; ?>
        <?php foreach (($filterOptions['requirements'] ?? []) as $requirementOption): ?>
          <?php
            $requirementOptionId = (int)($requirementOption['requirement_id'] ?? 0);
            $requirementOptionPhase = trim((string)($requirementOption['phase_label'] ?? ''));
            $requirementOptionLabel = trim((string)($requirementOption['name'] ?? 'Requirement'));
            if ($requirementOptionPhase !== '') {
                $requirementOptionLabel .= ' - ' . $requirementOptionPhase;
            }
          ?>
          <option value="<?php echo $requirementOptionId; ?>" <?php echo (int)$selected['requirement_id'] === $requirementOptionId ? 'selected' : ''; ?>><?php echo adviser_requirements_e($requirementOptionLabel); ?></option>
        <?php endforeach; ?>
      </select>

      <select class="adv-req-select" name="status" aria-label="Filter by status">
        <option value="">All status</option>
        <?php foreach (($filterOptions['submission_states'] ?? []) as $statusValue => $statusLabel): ?>
          <option value="<?php echo adviser_requirements_e($statusValue); ?>" <?php echo $selected['status'] === $statusValue ? 'selected' : ''; ?>><?php echo adviser_requirements_e($statusLabel); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <a class="adv-req-btn" href="<?php echo adviser_requirements_e($baseUrl); ?>/layout.php?page=adviser/requirements"><i class="fas fa-rotate-left"></i>Reset</a>
  </form>

  <div class="adv-req-workspace">
    <section class="adv-req-card">
      <div class="adv-req-card-head">
        <div class="adv-req-card-head-main">
          <h3 class="adv-req-card-title">Requirement Tracker</h3>
          <p class="adv-req-muted">
            <?php echo adviser_requirements_e($selectedRequirementName); ?><?php echo $selectedRequirementPhase !== '' ? ' - ' . adviser_requirements_e($selectedRequirementPhase) : ''; ?>
          </p>
        </div>
        <div class="adv-req-card-tools">
          <span id="advReqVisibleCount" class="adv-req-count"><?php echo (int)count($rows); ?> student<?php echo count($rows) === 1 ? '' : 's'; ?></span>
          <label class="adv-req-live-search" aria-label="Search student name">
            <i class="fas fa-search"></i>
            <input id="advReqLiveSearch" type="text" placeholder="Search name">
          </label>
        </div>
      </div>

      <div class="adv-req-table-wrap">
        <table class="adv-req-table">
          <colgroup>
            <col style="width:30%">
            <col style="width:14%">
            <col style="width:14%">
            <col style="width:18%">
            <col style="width:14%">
            <col style="width:10%">
          </colgroup>
          <thead>
            <tr>
              <th>Student</th>
              <th>Section</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>File</th>
              <th>Preview</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $statusMeta = $row['status_meta'];
                  $hasFile = !empty($row['has_file']);
                  $isSelected = $previewRow !== null && (int)($previewRow['req_submission_id'] ?? 0) === (int)($row['req_submission_id'] ?? 0) && $hasFile;
                  $previewMeta = trim((string)($row['student_name'] ?? '') . ' - ' . (string)($row['section_label'] ?? '') . ' - ' . (string)($row['file_type_label'] ?? 'File'));
                  $studentSearchText = strtolower(trim(implode(' ', [
                    (string)($row['student_name'] ?? ''),
                    (string)($row['student_number'] ?? ''),
                    (string)($row['email'] ?? ''),
                    (string)($row['program'] ?? ''),
                    (string)($row['section_label'] ?? ''),
                  ])));
                ?>
                <tr class="<?php echo $isSelected ? 'is-active' : ''; ?>" data-student-search="<?php echo adviser_requirements_e($studentSearchText); ?>">
                  <td>
                    <div class="adv-req-student">
                      <span class="adv-req-avatar"><?php echo adviser_requirements_e($row['initials']); ?></span>
                      <div>
                        <p class="adv-req-name"><?php echo adviser_requirements_e($row['student_name']); ?></p>
                        <p class="adv-req-muted"><?php echo adviser_requirements_e($row['student_number'] ?? ''); ?><?php echo !empty($row['program']) ? ' - ' . adviser_requirements_e($row['program']) : ''; ?></p>
                      </div>
                    </div>
                  </td>
                  <td><?php echo adviser_requirements_e($row['section_label'] ?? 'Unassigned'); ?></td>
                  <td><span class="adv-req-badge <?php echo adviser_requirements_e($statusMeta['class']); ?>"><i class="fas <?php echo adviser_requirements_e($statusMeta['icon']); ?>"></i><?php echo adviser_requirements_e($statusMeta['label']); ?></span></td>
                  <td><?php echo adviser_requirements_e($row['submitted_label']); ?></td>
                  <td>
                    <div class="adv-req-file">
                      <?php if ($hasFile): ?>
                        <strong><?php echo adviser_requirements_e($row['file_type_label']); ?></strong>
                        <span><?php echo adviser_requirements_e($row['file_name']); ?></span>
                      <?php else: ?>
                        <strong>No file</strong>
                        <span>Waiting for upload</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <button
                      class="adv-req-preview-btn"
                      type="button"
                      <?php echo $hasFile ? '' : 'disabled'; ?>
                      data-preview-src="<?php echo adviser_requirements_e($row['preview_src'] ?? ''); ?>"
                      data-preview-id="<?php echo (int)($row['req_submission_id'] ?? 0); ?>"
                      data-preview-title="<?php echo adviser_requirements_e($row['requirement_name'] ?? 'Requirement'); ?>"
                      data-preview-meta="<?php echo adviser_requirements_e($previewMeta); ?>"
                    >
                      <i class="fas fa-eye"></i><?php echo $hasFile ? 'Preview' : 'No file'; ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6">
                  <div class="adv-req-empty">No assigned student requirements match the current filters.</div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="adv-req-card adv-req-preview">
      <div class="adv-req-preview-head">
        <h3 id="advReqPreviewTitle" class="adv-req-preview-title">
          <?php echo $previewRow !== null ? adviser_requirements_e($previewRow['requirement_name'] ?? 'Requirement Preview') : 'Requirement Preview'; ?>
        </h3>
        <p id="advReqPreviewMeta" class="adv-req-preview-meta">
          <?php
            if ($previewRow !== null) {
                echo adviser_requirements_e(trim((string)$previewRow['student_name'] . ' - ' . (string)$previewRow['section_label'] . ' - ' . (string)$previewRow['file_type_label']));
            } else {
                echo 'Select a submitted file to preview it here.';
            }
          ?>
        </p>
      </div>
      <div class="adv-req-preview-frame-wrap">
        <div id="advReqPreviewEmpty" class="adv-req-preview-empty" style="<?php echo $previewRow !== null ? 'display:none;' : ''; ?>">
          <div>
            <i class="fas fa-file-circle-question"></i>
            <strong>No preview selected</strong>
            <p class="adv-req-muted">Files appear here after a student uploads a requirement.</p>
          </div>
        </div>
        <iframe
          id="advReqPreviewFrame"
          class="adv-req-preview-frame"
          title="Requirement preview"
          src="<?php echo $previewRow !== null ? adviser_requirements_e($previewRow['preview_src'] ?? '') : ''; ?>"
          style="<?php echo $previewRow !== null ? '' : 'display:none;'; ?>"
        ></iframe>
      </div>
    </aside>
  </div>
</div>

<script>
(function () {
  var filterForm = document.querySelector('.adv-req-toolbar');
  var filterControls = filterForm ? filterForm.querySelectorAll('select') : [];
  var buttons = document.querySelectorAll('.adv-req-preview-btn[data-preview-src]');
  var frame = document.getElementById('advReqPreviewFrame');
  var empty = document.getElementById('advReqPreviewEmpty');
  var title = document.getElementById('advReqPreviewTitle');
  var meta = document.getElementById('advReqPreviewMeta');
  var liveSearch = document.getElementById('advReqLiveSearch');
  var visibleCount = document.getElementById('advReqVisibleCount');
  var studentRows = document.querySelectorAll('.adv-req-table tbody tr[data-student-search]');

  function clearActiveRows() {
    document.querySelectorAll('.adv-req-table tr.is-active').forEach(function (row) {
      row.classList.remove('is-active');
    });
  }

  function updateVisibleCount(total) {
    if (!visibleCount) {
      return;
    }

    visibleCount.textContent = total + ' student' + (total === 1 ? '' : 's');
  }

  function applyLiveSearch() {
    var query = liveSearch ? String(liveSearch.value || '').trim().toLowerCase() : '';
    var total = 0;

    studentRows.forEach(function (row) {
      var haystack = String(row.getAttribute('data-student-search') || '');
      var isMatch = query === '' || haystack.indexOf(query) !== -1;
      row.classList.toggle('is-hidden', !isMatch);
      if (isMatch) {
        total++;
      }
    });

    updateVisibleCount(total);
  }

  if (liveSearch) {
    liveSearch.addEventListener('input', applyLiveSearch);
  }

  filterControls.forEach(function (control) {
    control.addEventListener('change', function () {
      if (filterForm) {
        filterForm.submit();
      }
    });
  });

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var src = button.getAttribute('data-preview-src') || '';
      if (!src || button.disabled) {
        return;
      }

      if (frame) {
        frame.src = src;
        frame.style.display = '';
      }
      if (empty) {
        empty.style.display = 'none';
      }
      if (title) {
        title.textContent = button.getAttribute('data-preview-title') || 'Requirement Preview';
      }
      if (meta) {
        meta.textContent = button.getAttribute('data-preview-meta') || '';
      }

      clearActiveRows();
      var row = button.closest('tr');
      if (row) {
        row.classList.add('is-active');
      }
    });
  });
})();
</script>
