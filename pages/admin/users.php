<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

// Filter params
$filterRole   = $_GET['role']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page_num     = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 20;
$offset       = ($page_num - 1) * $perPage;
$currentUri   = $_SERVER['REQUEST_URI'] ?? '/SkillHive/layout.php?page=admin/users';

// Build combined UNION for all user types
$bindings = [];
function buildUserQuery($search, $filterRole, $filterStatus) {
    global $bindings;
    // We'll pull from all tables and unify
    return '';
}

// Fetch students
$sWhere = ['1=1'];
$sParams = [];
if ($search) { $sWhere[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_number LIKE ?)'; $sl="%$search%"; $sParams=array_merge($sParams,[$sl,$sl,$sl,$sl]); }

// Fetch employers
$eWhere = ['1=1'];
$eParams = [];
if ($search) { $eWhere[] = '(company_name LIKE ? OR email LIKE ?)'; $el="%$search%"; $eParams=array_merge($eParams,[$el,$el]); }

// We'll render all as a flat list
// For simplicity we do separate queries per role unless filtered
$users = [];

if (!$filterRole || $filterRole === 'student') {
    $sw = $sWhere;
    $sp = $sParams;
    if ($filterStatus) { $sw[] = '? = ?'; $sp[] = ''; $sp[] = ''; } // students have no status col here
    $stmt = $pdo->prepare("SELECT student_id AS uid, CONCAT(first_name,' ',last_name) AS name, email, 'student' AS role, 'active' AS status, created_at FROM student WHERE ".implode(' AND ',$sWhere)." ORDER BY created_at DESC LIMIT 200");
    $stmt->execute($sParams);
    foreach ($stmt->fetchAll() as $r) $users[] = $r;
}
if (!$filterRole || $filterRole === 'employer') {
    $stmt = $pdo->prepare("SELECT employer_id AS uid, company_name AS name, email, 'employer' AS role, verification_status AS status, created_at FROM employer WHERE ".implode(' AND ',$eWhere)." ORDER BY created_at DESC LIMIT 200");
    $stmt->execute($eParams);
    foreach ($stmt->fetchAll() as $r) $users[] = $r;
}
if (!$filterRole || $filterRole === 'admin') {
    $aWhere = ['1=1'];
    $aParams = [];
    if ($search) { $aWhere[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $al="%$search%"; $aParams=array_merge($aParams,[$al,$al,$al]); }
    $stmt = $pdo->prepare("SELECT admin_id AS uid, CONCAT(first_name,' ',last_name) AS name, email, 'admin' AS role, 'active' AS status, created_at FROM admin WHERE ".implode(' AND ',$aWhere)." ORDER BY created_at DESC LIMIT 200");
    $stmt->execute($aParams);
    foreach ($stmt->fetchAll() as $r) $users[] = $r;
}

// Sort by created_at desc
usort($users, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Filter by status if needed
if ($filterStatus) {
    $users = array_filter($users, fn($u) => strtolower($u['status']) === strtolower($filterStatus));
}

$totalUsers = count($users);
$pagedUsers = array_slice($users, $offset, $perPage);
$totalPages = (int)ceil($totalUsers / $perPage);

// Role colors
$roleColors = ['student'=>'#12b3ac','employer'=>'#12b3ac','adviser'=>'#12b3ac','admin'=>'#12b3ac'];
$roleBg     = ['student'=>'rgba(18,179,172,.12)','employer'=>'rgba(16,185,129,.1)','adviser'=>'rgba(18,179,172,.12)','admin'=>'rgba(239,68,68,.1)'];
?>

<div class="page-header" style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h2 class="page-title" style="font-size:1.25rem;font-weight:800;color:#111;margin-bottom:4px">
      <i class="fas fa-users" style="color:#12b3ac;margin-right:8px"></i>User Management
    </h2>
    <p class="page-subtitle" style="color:#999;font-size:.85rem">Manage all platform users · <?= number_format($totalUsers) ?> total</p>
  </div>
</div>

<!-- ── Filters ───────────────────────────────────────────────────────────── -->
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
  <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;flex:1;min-width:200px">
    <i class="fas fa-search" style="color:#ccc"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email..." style="border:none;outline:none;font-family:inherit;font-size:.86rem;width:100%">
  </div>
  <select name="role" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;color:#111">
    <option value="">All Roles</option>
    <option value="student"  <?= $filterRole==='student'?'selected':'' ?>>Student</option>
    <option value="employer" <?= $filterRole==='employer'?'selected':'' ?>>Employer</option>
    <option value="admin"    <?= $filterRole==='admin'?'selected':'' ?>>Admin</option>
  </select>
  <select name="status" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;color:#111">
    <option value="">All Status</option>
    <option value="active"    <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
    <option value="Pending"   <?= $filterStatus==='Pending'?'selected':'' ?>>Pending</option>
    <option value="Approved"  <?= $filterStatus==='Approved'?'selected':'' ?>>Approved</option>
    <option value="Rejected"  <?= $filterStatus==='Rejected'?'selected':'' ?>>Rejected</option>
    <option value="Flagged"   <?= $filterStatus==='Flagged'?'selected':'' ?>>Flagged</option>
  </select>
  <button type="submit" class="btn btn-primary" style="font-size:.84rem"><i class="fas fa-filter"></i> Filter</button>
  <?php if ($search || $filterRole || $filterStatus): ?>
  <a href="?" class="btn btn-ghost" style="font-size:.84rem">Clear</a>
  <?php endif; ?>
</form>

<!-- ── Flash ─────────────────────────────────────────────────────────────── -->
<?php if (isset($_SESSION['admin_msg'])): ?>
<div class="toast toast-success" style="position:relative;animation:none;margin-bottom:14px;opacity:1;transform:none;display:flex;align-items:center;gap:10px">
  <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['admin_msg']) ?>
</div>
<?php unset($_SESSION['admin_msg']); endif; ?>

<!-- ── Table ─────────────────────────────────────────────────────────────── -->
<div class="panel-card" style="overflow:hidden;padding:0">
  <?php if (empty($pagedUsers)): ?>
  <div style="text-align:center;padding:60px;color:#ccc">
    <i class="fas fa-users" style="font-size:3rem;margin-bottom:12px;display:block"></i>
    <div style="font-weight:600;color:#bbb">No users found</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:700px">
    <thead>
      <tr style="border-bottom:1.5px solid var(--border);background:#ffffff">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">#</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">USER</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">EMAIL</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">ROLE</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">STATUS</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">JOINED</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">ACTIONS</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pagedUsers as $i => $u):
        $rc = $roleColors[$u['role']] ?? '#999';
        $rb = $roleBg[$u['role']] ?? '#eee';
        // Status badge
        $statusLower = strtolower($u['status']);
        $stColors=['active'=>'#12b3ac','pending'=>'#12b3ac','approved'=>'#12b3ac','rejected'=>'#12b3ac','flagged'=>'#12b3ac'];
        $stBg=['active'=>'rgba(16,185,129,.1)','pending'=>'rgba(18,179,172,.12)','approved'=>'rgba(16,185,129,.1)','rejected'=>'rgba(239,68,68,.1)','flagged'=>'rgba(239,68,68,.1)'];
        $sc = $stColors[$statusLower] ?? '#999';
        $sb = $stBg[$statusLower] ?? '#eee';
        $initials = strtoupper(substr($u['name'],0,1).(strpos($u['name'],' ')!==false ? substr($u['name'],strpos($u['name'],' ')+1,1) : ''));
      ?>
      <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='#ffffff'" onmouseout="this.style.background=''">
        <td style="padding:12px 16px;font-size:.8rem;color:#ccc"><?= $offset + $i + 1 ?></td>
        <td style="padding:12px 16px">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:<?= $rc ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.72rem;color:#fff;flex-shrink:0"><?= htmlspecialchars($initials) ?></div>
            <div style="font-weight:600;font-size:.86rem"><?= htmlspecialchars($u['name']) ?></div>
          </div>
        </td>
        <td style="padding:12px 16px;font-size:.82rem;color:#666"><?= htmlspecialchars($u['email']) ?></td>
        <td style="padding:12px 16px">
          <span style="padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:<?= $rb ?>;color:<?= $rc ?>"><?= ucfirst($u['role']) ?></span>
        </td>
        <td style="padding:12px 16px">
          <span style="padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:<?= $sb ?>;color:<?= $sc ?>"><?= ucfirst($u['status']) ?></span>
        </td>
        <td style="padding:12px 16px;font-size:.8rem;color:#999"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
        <td style="padding:12px 16px">
          <div style="display:flex;gap:6px">
            <?php if ($u['role'] === 'employer'): ?>
            <a href="<?= $baseUrl ?>/layout.php?page=admin/verify-companies&q=<?= urlencode($u['email']) ?>" class="btn btn-ghost" style="font-size:.72rem;padding:4px 10px"><i class="fas fa-building"></i> Company</a>
            <?php endif; ?>
            <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" style="display:inline">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= $u['uid'] ?>">
              <input type="hidden" name="user_role" value="<?= $u['role'] ?>">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
              <button type="submit" class="btn btn-ghost" style="font-size:.72rem;padding:4px 10px;color:#12b3ac;border-color:#12b3ac" onclick="return confirm('Delete this user permanently?')"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="padding:16px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap">
    <?php for ($pg=1;$pg<=$totalPages;$pg++): 
      $pUrl='?'.http_build_query(['role'=>$filterRole,'status'=>$filterStatus,'q'=>$search,'p'=>$pg]);
    ?>
    <a href="<?= htmlspecialchars($pUrl) ?>" style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid <?= $pg===$page_num?'#12b3ac':'var(--border)' ?>;background:<?= $pg===$page_num?'#12b3ac':'#fff' ?>;color:<?= $pg===$page_num?'#fff':'#555' ?>"><?= $pg ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
