<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

$baseUrl = $baseUrl ?? '/SkillHive';

// Filter params
$filterEvent  = $_GET['event']    ?? '';
$filterSev    = $_GET['severity'] ?? '';
$search       = trim($_GET['q']   ?? '');
$page_num     = max(1,(int)($_GET['p'] ?? 1));
$perPage      = 25;
$offset       = ($page_num-1)*$perPage;

// Build logs from real DB activity (recent verifications, users, applications)
// We synthesise from company_verification, application status changes, and admin actions
$logs = [];

// Company verifications
$stmt = $pdo->query("
    SELECT cv.date_reviewed AS ts, 'Company Verification' AS event,
        CONCAT(a.first_name,' ',a.last_name) AS actor,
        e.company_name AS detail, cv.status AS severity_hint
    FROM company_verification cv
    JOIN employer e ON e.employer_id = cv.employer_id
    LEFT JOIN admin a ON a.admin_id = cv.admin_id
    WHERE cv.date_reviewed IS NOT NULL
    ORDER BY cv.date_reviewed DESC LIMIT 50
");
foreach ($stmt->fetchAll() as $r) {
    $sev = in_array($r['severity_hint'],['Rejected','Flagged']) ? 'warning' : 'info';
    $logs[] = ['ts'=>$r['ts'],'event'=>$r['event'],'actor'=>$r['actor']??'System','detail'=>$r['detail'],'severity'=>$sev];
}

// New user registrations (students)
$stmt = $pdo->query("SELECT created_at AS ts, CONCAT(first_name,' ',last_name) AS name, email FROM student ORDER BY created_at DESC LIMIT 30");
foreach ($stmt->fetchAll() as $r) {
    $logs[] = ['ts'=>$r['ts'],'event'=>'Student Registered','actor'=>$r['name'],'detail'=>$r['email'],'severity'=>'info'];
}

// New employers
$stmt = $pdo->query("SELECT created_at AS ts, company_name AS name, email FROM employer ORDER BY created_at DESC LIMIT 30");
foreach ($stmt->fetchAll() as $r) {
    $logs[] = ['ts'=>$r['ts'],'event'=>'Employer Registered','actor'=>$r['name'],'detail'=>$r['email'],'severity'=>'info'];
}

// Applications accepted
$stmt = $pdo->query("SELECT a.updated_at AS ts, CONCAT(s.first_name,' ',s.last_name) AS sname, i.title, e.company_name, a.status 
    FROM application a
    JOIN student s ON s.student_id=a.student_id
    JOIN internship i ON i.internship_id=a.internship_id
    JOIN employer e ON e.employer_id=i.employer_id
    WHERE a.status IN ('Accepted','Rejected')
    ORDER BY a.updated_at DESC LIMIT 40");
foreach ($stmt->fetchAll() as $r) {
    $sev = $r['status']==='Rejected' ? 'warning' : 'info';
    $logs[] = ['ts'=>$r['ts'],'event'=>'Application '.$r['status'],'actor'=>$r['sname'],'detail'=>$r['title'].' at '.$r['company_name'],'severity'=>$sev];
}

// Sort all by timestamp descending
usort($logs, fn($a,$b)=>strtotime($b['ts'])-strtotime($a['ts']));

// Apply filters
if ($filterEvent) $logs = array_filter($logs, fn($l)=>$l['event']===$filterEvent);
if ($filterSev)   $logs = array_filter($logs, fn($l)=>$l['severity']===$filterSev);
if ($search)      $logs = array_filter($logs, fn($l)=>stripos($l['event'].' '.$l['actor'].' '.$l['detail'],$search)!==false);
$logs = array_values($logs);

$totalLogs = count($logs);
$pagedLogs = array_slice($logs, $offset, $perPage);
$totalPages = (int)ceil($totalLogs/$perPage);

$uniqueEvents = array_unique(array_column($logs,'event'));
sort($uniqueEvents);

$sevColors = ['info'=>'#12b3ac','warning'=>'#12b3ac','critical'=>'#12b3ac'];
$sevBg = ['info'=>'rgba(79,70,229,.08)','warning'=>'rgba(245,158,11,.08)','critical'=>'rgba(18,179,172,.12)'];
$sevIcons = ['info'=>'fa-info-circle','warning'=>'fa-exclamation-triangle','critical'=>'fa-skull'];
?>

<div class="page-header" style="margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <h2 class="page-title" style="font-size:1.25rem;font-weight:800;color:#111;margin-bottom:4px">
      <i class="fas fa-history" style="color:#6B7280;margin-right:8px"></i>Audit Logs
    </h2>
    <p class="page-subtitle" style="color:#999;font-size:.85rem">System event history · <?= number_format($totalLogs) ?> records</p>
  </div>
  <a href="<?= $baseUrl ?>/pages/admin/admin_actions.php?action=export_logs_csv" class="btn btn-ghost" style="font-size:.82rem"><i class="fas fa-download"></i> Export CSV</a>
</div>

<!-- ── Filters ───────────────────────────────────────────────────────────── -->
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
  <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;flex:1;min-width:200px">
    <i class="fas fa-search" style="color:#ccc"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search logs..." style="border:none;outline:none;font-family:inherit;font-size:.86rem;width:100%">
  </div>
  <select name="event" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;color:#111">
    <option value="">All Events</option>
    <?php foreach ($uniqueEvents as $ev): ?>
    <option value="<?= htmlspecialchars($ev) ?>" <?= $filterEvent===$ev?'selected':'' ?>><?= htmlspecialchars($ev) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="severity" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.85rem;background:#fff;color:#111">
    <option value="">All Severity</option>
    <option value="info"     <?= $filterSev==='info'?'selected':'' ?>>Info</option>
    <option value="warning"  <?= $filterSev==='warning'?'selected':'' ?>>Warning</option>
    <option value="critical" <?= $filterSev==='critical'?'selected':'' ?>>Critical</option>
  </select>
  <button type="submit" class="btn btn-primary" style="font-size:.84rem"><i class="fas fa-filter"></i> Filter</button>
  <?php if ($search||$filterEvent||$filterSev): ?>
  <a href="?" class="btn btn-ghost" style="font-size:.84rem">Clear</a>
  <?php endif; ?>
</form>

<!-- ── Log Table ─────────────────────────────────────────────────────────── -->
<div class="panel-card" style="overflow:hidden;padding:0">
  <?php if (empty($pagedLogs)): ?>
  <div style="text-align:center;padding:60px;color:#ccc">
    <i class="fas fa-history" style="font-size:3rem;margin-bottom:12px;display:block"></i>
    <div style="font-weight:600;color:#bbb">No log entries found</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:700px">
    <thead>
      <tr style="border-bottom:1.5px solid var(--border);background:#ffffff">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">TIMESTAMP</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">EVENT</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">ACTOR</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">DETAILS</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#999;font-weight:700;white-space:nowrap">SEVERITY</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pagedLogs as $log):
        $sc = $sevColors[$log['severity']] ?? '#999';
        $sb = $sevBg[$log['severity']] ?? '#eee';
        $si = $sevIcons[$log['severity']] ?? 'fa-circle';
      ?>
      <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='#ffffff'" onmouseout="this.style.background=''">
        <td style="padding:11px 16px;font-size:.78rem;color:#999;white-space:nowrap">
          <?= $log['ts'] ? date('M d, Y H:i', strtotime($log['ts'])) : '—' ?>
        </td>
        <td style="padding:11px 16px">
          <div style="font-size:.83rem;font-weight:600;color:#111"><?= htmlspecialchars($log['event']) ?></div>
        </td>
        <td style="padding:11px 16px;font-size:.82rem;color:#555"><?= htmlspecialchars($log['actor'] ?? '—') ?></td>
        <td style="padding:11px 16px;font-size:.82rem;color:#666;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($log['detail']) ?>"><?= htmlspecialchars($log['detail']) ?></td>
        <td style="padding:11px 16px">
          <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;background:<?= $sb ?>;color:<?= $sc ?>">
            <i class="fas <?= $si ?>"></i> <?= ucfirst($log['severity']) ?>
          </span>
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
      $pUrl='?'.http_build_query(['event'=>$filterEvent,'severity'=>$filterSev,'q'=>$search,'p'=>$pg]);
    ?>
    <a href="<?= htmlspecialchars($pUrl) ?>" style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:600;text-decoration:none;border:1.5px solid <?= $pg===$page_num?'#12b3ac':'var(--border)' ?>;background:<?= $pg===$page_num?'#12b3ac':'#fff' ?>;color:<?= $pg===$page_num?'#fff':'#555' ?>"><?= $pg ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
