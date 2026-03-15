<?php
require_once __DIR__ . '/../../backend/db_connect.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /SkillHive/layout.php'); exit;
}

// Load current admin info
$adminInfo = $pdo->prepare("SELECT * FROM admin WHERE admin_id = ?");
$adminInfo->execute([$userId]);
$admin = $adminInfo->fetch();
?>

<div class="page-header" style="margin-bottom:24px">
  <h2 class="page-title" style="font-size:1.25rem;font-weight:800;color:#111;margin-bottom:4px">
    <i class="fas fa-gear" style="color:#6B7280;margin-right:8px"></i>System Settings
  </h2>
  <p class="page-subtitle" style="color:#999;font-size:.85rem">Admin account &amp; global platform configuration</p>
</div>

<!-- ── Flash ─────────────────────────────────────────────────────────────── -->
<?php if (isset($_SESSION['admin_msg'])): ?>
<div class="toast toast-success" style="position:relative;animation:none;margin-bottom:20px;opacity:1;transform:none;display:flex;align-items:center;gap:10px">
  <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['admin_msg']) ?>
</div>
<?php unset($_SESSION['admin_msg']); endif; ?>
<?php if (isset($_SESSION['admin_err'])): ?>
<div class="toast" style="position:relative;animation:none;margin-bottom:20px;opacity:1;transform:none;display:flex;align-items:center;gap:10px;background:#FFF0F0;border-color:#EF4444;color:#EF4444">
  <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['admin_err']) ?>
</div>
<?php unset($_SESSION['admin_err']); endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

  <!-- LEFT: Account Settings -->
  <div>
    <!-- Profile -->
    <div class="panel-card" style="margin-bottom:20px">
      <div class="panel-card-header" style="margin-bottom:18px">
        <h3><i class="fas fa-user-circle" style="margin-right:8px;color:#4F46E5"></i>Admin Profile</h3>
      </div>
      <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">First Name</label>
            <input name="first_name" value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">Last Name</label>
            <input name="last_name" value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
          </div>
        </div>
        <div style="margin-bottom:14px">
          <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">Email Address</label>
          <input name="email" type="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Profile</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="panel-card">
      <div class="panel-card-header" style="margin-bottom:18px">
        <h3><i class="fas fa-lock" style="margin-right:8px;color:#EF4444"></i>Change Password</h3>
      </div>
      <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
        <div style="margin-bottom:14px">
          <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">Current Password</label>
          <input name="current_password" type="password" placeholder="••••••••" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
        </div>
        <div style="margin-bottom:14px">
          <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">New Password</label>
          <input name="new_password" type="password" placeholder="Min. 8 characters" id="new-pwd" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
          <!-- Strength bar -->
          <div style="margin-top:6px;height:4px;background:#eee;border-radius:4px;overflow:hidden">
            <div id="pwd-strength-fill" style="height:100%;width:0%;border-radius:4px;transition:all .3s"></div>
          </div>
          <div id="pwd-hint" style="font-size:.72rem;margin-top:3px;color:#999"></div>
        </div>
        <div style="margin-bottom:16px">
          <label style="font-size:.8rem;font-weight:600;color:#666;display:block;margin-bottom:5px">Confirm New Password</label>
          <input name="confirm_password" type="password" placeholder="Re-enter new password" style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.88rem" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;background:#EF4444 !important"><i class="fas fa-key"></i> Change Password</button>
      </form>
    </div>
  </div>

  <!-- RIGHT: Platform Config -->
  <div>
    <!-- Platform Settings -->
    <div class="panel-card" style="margin-bottom:20px">
      <div class="panel-card-header" style="margin-bottom:18px">
        <h3><i class="fas fa-sliders-h" style="margin-right:8px;color:#10B981"></i>Platform Settings</h3>
      </div>
      <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php">
        <input type="hidden" name="action" value="platform_settings">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
        <div style="display:flex;flex-direction:column;gap:14px">

          <!-- Toggle: New Registrations -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#FAFAFA;border-radius:10px">
            <div>
              <div style="font-weight:600;font-size:.86rem">Allow New Registrations</div>
              <div style="font-size:.75rem;color:#999">Students &amp; employers can register</div>
            </div>
            <label style="position:relative;width:44px;height:24px;cursor:pointer">
              <input type="checkbox" name="allow_registration" value="1" checked style="opacity:0;width:0;height:0;position:absolute">
              <span id="toggle-reg" style="position:absolute;inset:0;background:#10B981;border-radius:50px;cursor:pointer;transition:.3s" onclick="toggleSwitch(this)">
                <span style="position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;right:3px;transition:.3s"></span>
              </span>
            </label>
          </div>

          <!-- Toggle: Marketplace -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#FAFAFA;border-radius:10px">
            <div>
              <div style="font-weight:600;font-size:.86rem">Marketplace Active</div>
              <div style="font-size:.75rem;color:#999">Students can browse &amp; apply</div>
            </div>
            <label style="position:relative;width:44px;height:24px;cursor:pointer">
              <input type="checkbox" name="marketplace_active" value="1" checked style="opacity:0;width:0;height:0;position:absolute">
              <span style="position:absolute;inset:0;background:#10B981;border-radius:50px;cursor:pointer;transition:.3s" onclick="toggleSwitch(this)">
                <span style="position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;right:3px;transition:.3s"></span>
              </span>
            </label>
          </div>

          <!-- Toggle: AI Matching -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#FAFAFA;border-radius:10px">
            <div>
              <div style="font-weight:600;font-size:.86rem">AI Matching Engine</div>
              <div style="font-size:.75rem;color:#999">Enable AI skill-based matching</div>
            </div>
            <label style="position:relative;width:44px;height:24px;cursor:pointer">
              <input type="checkbox" name="ai_matching" value="1" checked style="opacity:0;width:0;height:0;position:absolute">
              <span style="position:absolute;inset:0;background:#10B981;border-radius:50px;cursor:pointer;transition:.3s" onclick="toggleSwitch(this)">
                <span style="position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;right:3px;transition:.3s"></span>
              </span>
            </label>
          </div>

          <button type="submit" class="btn btn-primary" style="justify-content:center"><i class="fas fa-save"></i> Save Settings</button>
        </div>
      </form>
    </div>

    <!-- Data Management -->
    <div class="panel-card">
      <div class="panel-card-header" style="margin-bottom:16px">
        <h3><i class="fas fa-database" style="margin-right:8px;color:#06B6D4"></i>Data Management</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px">

        <!-- Reports download -->
        <a href="<?= $baseUrl ?>/pages/admin/admin_actions.php?action=export_users_csv" class="btn btn-ghost" style="justify-content:flex-start;font-size:.84rem">
          <i class="fas fa-file-csv" style="width:20px;color:#10B981"></i> Export Users CSV
        </a>
        <a href="<?= $baseUrl ?>/pages/admin/admin_actions.php?action=export_companies_csv" class="btn btn-ghost" style="justify-content:flex-start;font-size:.84rem">
          <i class="fas fa-file-csv" style="width:20px;color:#4F46E5"></i> Export Companies CSV
        </a>
        <a href="<?= $baseUrl ?>/pages/admin/admin_actions.php?action=export_applications_csv" class="btn btn-ghost" style="justify-content:flex-start;font-size:.84rem">
          <i class="fas fa-file-csv" style="width:20px;color:#F59E0B"></i> Export Applications CSV
        </a>

        <!-- Danger zone -->
        <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
          <div style="font-size:.8rem;font-weight:700;color:#EF4444;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Danger Zone</div>
          <form method="post" action="<?= $baseUrl ?>/pages/admin/admin_actions.php" onsubmit="return confirm('Purge ALL rejected/flagged company applications? This cannot be undone.')">
            <input type="hidden" name="action" value="purge_rejected">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <button type="submit" class="btn btn-ghost" style="justify-content:flex-start;font-size:.84rem;color:#EF4444;border-color:#EF4444;width:100%">
              <i class="fas fa-trash" style="width:20px"></i> Purge Rejected Records
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Password strength meter
document.getElementById('new-pwd')?.addEventListener('input', function() {
  const val = this.value;
  const fill = document.getElementById('pwd-strength-fill');
  const hint = document.getElementById('pwd-hint');
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    {w:'0%',bg:'#eee',txt:''},
    {w:'25%',bg:'#EF4444',txt:'Weak'},
    {w:'50%',bg:'#F59E0B',txt:'Fair'},
    {w:'75%',bg:'#3B82F6',txt:'Good'},
    {w:'100%',bg:'#10B981',txt:'Strong ✓'},
  ];
  const l = levels[val.length === 0 ? 0 : score];
  if (fill) { fill.style.width = l.w; fill.style.background = l.bg; }
  if (hint) { hint.textContent = l.txt; hint.style.color = l.bg; }
});

function toggleSwitch(el) {
  const isOn = el.style.background !== 'rgb(209, 213, 219)' && el.style.background !== '#D1D5DB';
  el.style.background = isOn ? '#D1D5DB' : '#10B981';
  const dot = el.querySelector('span');
  if (dot) dot.style.right = isOn ? 'auto' : '3px', dot.style.left = isOn ? '3px' : 'auto';
  const inp = el.previousElementSibling;
  if (inp) inp.checked = !isOn;
}
</script>
