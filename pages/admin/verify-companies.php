<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

// Filter params
$filterStatus = $_GET['status'] ?? '';
$filterIndustry = $_GET['industry'] ?? '';
$search = trim($_GET['q'] ?? '');
$currentUri = $_SERVER['REQUEST_URI'] ?? '/SkillHive/layout.php?page=admin/verify-companies';

// Build query
$where = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[] = 'e.verification_status = ?';
    $params[] = $filterStatus;
}
if ($filterIndustry) {
    $where[] = 'e.industry = ?';
    $params[] = $filterIndustry;
}
if ($search) {
    $where[] = '(e.company_name LIKE ? OR e.email LIKE ? OR e.industry LIKE ?)';
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$sql = "SELECT e.*,
        cv.verification_id, cv.status AS cv_status, cv.date_submitted, cv.date_reviewed, cv.risk_assessment_notes, cv.document_file,
        a.first_name AS admin_fn, a.last_name AS admin_ln,
        COUNT(DISTINCT i.internship_id) AS posting_count,
        COUNT(DISTINCT app.application_id) AS applicant_count
        FROM employer e
        LEFT JOIN company_verification cv ON cv.employer_id = e.employer_id
        LEFT JOIN admin a ON a.admin_id = cv.admin_id
        LEFT JOIN internship i ON i.employer_id = e.employer_id
        LEFT JOIN application app ON app.internship_id = i.internship_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY e.employer_id
        ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

// Get industries for filter
$industries = $pdo->query("SELECT DISTINCT industry FROM employer ORDER BY industry")->fetchAll(PDO::FETCH_COLUMN);

// Summary counts
$summary = $pdo->query("SELECT verification_status, COUNT(*) cnt FROM employer GROUP BY verification_status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header" style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h2 class="page-title" style="font-size:1.25rem;font-weight:800;color:#111;margin-bottom:4px">
      <i class="fas fa-building" style="color:#10B981;margin-right:8px"></i>Company Registry
    </h2>
    <p class="page-subtitle" style="color:#999;font-size:.85rem">Verify and manage all partner companies</p>
  </div>
</div>

<!-- ── Summary Pills ───────────────────────────────────────────────────────── -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
  <?php
  $pills = [''=>'All','Pending'=>'Pending','Approved'=>'Approved','Rejected'=>'Rejected','Flagged'=>'Flagged'];
  $pillColors = [''=>'#111','Pending'=>'#F59E0B','Approved'=>'#10B981','Rejected'=>'#EF4444','Flagged'=>'#EF4444'];
  foreach ($pills as $val => $lbl):
    $cnt = $val === '' ? array_sum($summary) : ($summary[$val] ?? 0);
    $active = ($filterStatus === $val);
    $url = '?' . http_build_query(['status'=>$val,'industry'=>$filterIndustry,'q'=>$search]);
  ?>
  <a href="<?= htmlspecialchars($url) ?>" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:50px;font-size:.82rem;font-weight:600;border:1.5px solid <?= $active ? $pillColors[$val] : 'var(--border)' ?>;background:<?= $active ? $pillColors[$val].'18' : '#fff' ?>;color:<?= $active ? $pillColors[$val] : '#555' ?>">
    <?= $lbl ?> <span style="background:<?= $active ? $pillColors[$val] : '#eee' ?>;color:<?= $active ? '#fff' : '#555' ?>;<?= $active ? 'background:'.$pillColors[$val] : '' ?>;padding:1px 7px;border-radius:50px;font-size:.72rem"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Filters ───────────────────────────────────────────────────────────── -->
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
  <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;flex:1;min-width:200px">
    <i class="fas fa-search" style="color:#ccc"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search companies..." style="border:none;outline:none;font-family:inherit;font-size:.86rem;width:100%">
  </div>
  <select name="industry" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;color:#111">
    <option value="">All Industries</option>
    <?php foreach ($industries as $ind): ?>
    <option value="<?= htmlspecialchars($ind) ?>" <?= $filterIndustry===$ind?'selected':'' ?>><?= htmlspecialchars($ind) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary" style="font-size:.84rem"><i class="fas fa-filter"></i> Filter</button>
  <?php if ($search || $filterIndustry || $filterStatus): ?>
  <a href="?" class="btn btn-ghost" style="font-size:.84rem">Clear</a>
  <?php endif; ?>
</form>

<!-- ── Flash Message ─────────────────────────────────────────────────────── -->
<?php if (isset($_SESSION['admin_msg'])): ?>
<div class="toast toast-success" style="position:relative;animation:none;margin-bottom:14px;opacity:1;transform:none;display:flex;align-items:center;gap:10px">
  <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['admin_msg']) ?>
</div>
<?php unset($_SESSION['admin_msg']); endif; ?>

<!-- ── Company Cards ─────────────────────────────────────────────────────── -->
<?php if (empty($companies)): ?>
<div style="text-align:center;padding:60px;color:#ccc">
  <i class="fas fa-building" style="font-size:3rem;margin-bottom:12px;display:block"></i>
  <div style="font-weight:600;color:#bbb">No companies found</div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px">
  <?php foreach ($companies as $co):
    $vsColors=['Pending'=>'#F59E0B','Approved'=>'#10B981','Rejected'=>'#EF4444','Flagged'=>'#EF4444'];
    $vc = $vsColors[$co['verification_status']] ?? '#999';
    $abbr = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$co['company_name']),0,2));
    $gradColors = ['#10B981','#4F46E5','#06B6D4','#F59E0B','#EF4444','#8B5CF6'];
    $grad = $gradColors[crc32($co['company_name']) % count($gradColors)];
  ?>
  <div class="panel-card" style="padding:0;overflow:hidden">
    <!-- Header bar -->
    <div style="height:56px;background:linear-gradient(135deg,<?= $grad ?>,<?= $grad ?>aa);position:relative;overflow:hidden">
      <div style="position:absolute;inset:0;background:rgba(0,0,0,.08)"></div>
      <div style="position:absolute;top:10px;left:14px;display:flex;align-items:center;gap:10px">
        <?php if ($co['company_logo']): ?>
        <img src="<?= htmlspecialchars($co['company_logo']) ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;border:2px solid rgba(255,255,255,.4)">
        <?php else: ?>
        <div style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.78rem;color:#fff"><?= $abbr ?></div>
        <?php endif; ?>
        <div style="color:#fff">
          <div style="font-weight:700;font-size:.84rem;text-shadow:0 1px 4px rgba(0,0,0,.2)"><?= htmlspecialchars($co['company_name']) ?></div>
          <div style="font-size:.7rem;opacity:.75"><?= htmlspecialchars($co['industry']) ?></div>
        </div>
      </div>
      <span style="position:absolute;top:10px;right:12px;padding:3px 10px;border-radius:50px;font-size:.65rem;font-weight:700;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)"><?= $co['verification_status'] ?></span>
    </div>

    <!-- Body -->
    <div style="padding:14px 16px">
      <!-- Stats -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
        <div style="text-align:center;padding:8px;background:#FAFAFA;border-radius:8px">
          <div style="font-weight:800;font-size:.95rem;color:<?= $grad ?>"><?= $co['posting_count'] ?></div>
          <div style="font-size:.65rem;color:#999">Postings</div>
        </div>
        <div style="text-align:center;padding:8px;background:#FAFAFA;border-radius:8px">
          <div style="font-weight:800;font-size:.95rem;color:<?= $grad ?>"><?= $co['applicant_count'] ?></div>
          <div style="font-size:.65rem;color:#999">Applicants</div>
        </div>
        <div style="text-align:center;padding:8px;background:#FAFAFA;border-radius:8px">
          <div style="font-weight:700;font-size:.72rem;color:<?= $vc ?>;padding:2px 0"><?= $co['verification_status'] ?></div>
          <div style="font-size:.65rem;color:#999">Status</div>
        </div>
      </div>

      <!-- Info -->
      <div style="font-size:.78rem;color:#999;margin-bottom:12px;display:flex;flex-direction:column;gap:3px">
        <div><i class="fas fa-envelope" style="width:14px"></i> <?= htmlspecialchars($co['email']) ?></div>
        <?php if ($co['contact_number']): ?>
        <div><i class="fas fa-phone" style="width:14px"></i> <?= htmlspecialchars($co['contact_number']) ?></div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:6px">
          <?php if ($co['company_badge_status'] !== 'None'): ?>
          <span style="background:rgba(245,158,11,.1);color:#B45309;padding:2px 8px;border-radius:50px;font-size:.68rem;font-weight:700"><i class="fas fa-star"></i> <?= $co['company_badge_status'] ?></span>
          <?php endif; ?>
          <span style="color:#ccc;font-size:.72rem">Joined <?= date('M Y', strtotime($co['created_at'])) ?></span>
        </div>
      </div>

      <!-- Risk notes if any -->
      <?php if ($co['risk_assessment_notes']): ?>
      <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-radius:8px;padding:8px 10px;font-size:.78rem;color:#EF4444;margin-bottom:12px">
        <i class="fas fa-exclamation-triangle" style="margin-right:6px"></i><?= htmlspecialchars($co['risk_assessment_notes']) ?>
      </div>
      <?php endif; ?>

      <!-- Actions -->
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($co['verification_status'] === 'Pending'): ?>
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:contents">
          <input type="hidden" name="action" value="verify_company">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="decision" value="Approved">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <button type="submit" class="btn btn-primary" style="font-size:.78rem;padding:6px 14px"><i class="fas fa-check"></i> Approve</button>
        </form>
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:contents">
          <input type="hidden" name="action" value="verify_company">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="decision" value="Rejected">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <button type="submit" class="btn btn-ghost" style="font-size:.78rem;padding:6px 14px;color:#EF4444;border-color:#EF4444"><i class="fas fa-times"></i> Reject</button>
        </form>
        <?php elseif ($co['verification_status'] === 'Approved'): ?>
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:contents">
          <input type="hidden" name="action" value="verify_company">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="decision" value="Flagged">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <button type="submit" class="btn btn-ghost" style="font-size:.78rem;padding:6px 14px;color:#F59E0B;border-color:#F59E0B"><i class="fas fa-flag"></i> Flag</button>
        </form>
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:contents">
          <input type="hidden" name="action" value="award_badge">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="badge" value="Verified Partner">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <button type="submit" class="btn btn-ghost" style="font-size:.78rem;padding:6px 14px"><i class="fas fa-star"></i> Badge</button>
        </form>
        <?php elseif ($co['verification_status'] === 'Rejected' || $co['verification_status'] === 'Flagged'): ?>
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:contents">
          <input type="hidden" name="action" value="verify_company">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="decision" value="Approved">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <button type="submit" class="btn btn-primary" style="font-size:.78rem;padding:6px 14px"><i class="fas fa-check"></i> Re-approve</button>
        </form>
        <?php endif; ?>

        <!-- Notes button opens inline form -->
        <button type="button" class="btn btn-ghost" style="font-size:.78rem;padding:6px 10px" onclick="toggleNotes(<?= $co['employer_id'] ?>)" title="Add notes"><i class="fas fa-sticky-note"></i></button>
        <?php if ($co['document_file']): ?>
        <a href="<?= htmlspecialchars($co['document_file']) ?>" target="_blank" class="btn btn-ghost" style="font-size:.78rem;padding:6px 10px" title="View document"><i class="fas fa-file-alt"></i></a>
        <?php endif; ?>
      </div>

      <!-- Notes form (hidden by default) -->
      <div id="notes-form-<?= $co['employer_id'] ?>" style="display:none;margin-top:10px">
        <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php">
          <input type="hidden" name="action" value="add_notes">
          <input type="hidden" name="employer_id" value="<?= $co['employer_id'] ?>">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
          <textarea name="notes" style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:8px 10px;font-family:inherit;font-size:.82rem;resize:vertical;min-height:60px" placeholder="Risk assessment notes..."><?= htmlspecialchars($co['risk_assessment_notes'] ?? '') ?></textarea>
          <button type="submit" class="btn btn-primary" style="font-size:.78rem;width:100%;justify-content:center;margin-top:6px"><i class="fas fa-save"></i> Save Notes</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function toggleNotes(id) {
  const el = document.getElementById('notes-form-' + id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
