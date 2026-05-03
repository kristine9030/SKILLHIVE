<?php
/**
 * Purpose: Employer settings page - password change, notification preferences, delete account.
 */

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/../dashboard/formatters.php';
require_once __DIR__ . '/../post_internship/auth_helpers.php';

$baseUrl = isset($baseUrl) ? (string)$baseUrl : '/SkillHive';

$employerId = resolveEmployerId($_SESSION, isset($userId) ? (int)$userId : null);
if (!$employerId) {
    header('Location: ' . $baseUrl . '/pages/auth/login.php');
    exit;
}

$errorMessage = '';
$successMessage = '';
$form = [
    'company_name' => '',
    'industry' => '',
    'company_address' => '',
    'email' => '',
    'contact_number' => '',
    'website_url' => '',
    'company_logo' => '',
    'verification_status' => 'Pending',
    'company_badge_status' => 'None',
    'created_at' => '',
];

$actionsUrl = $baseUrl . '/pages/employer/settings/actions.php';
?>

<style>
  .employer-settings-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    animation: epp-fade-in 0.5s ease-out;
  }

  @keyframes epp-fade-in {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .employer-settings-layout {
    display: block;
    max-width: 1100px;
  }

  .employer-settings-grid-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
  }

  .employer-settings-main-col {
    min-width: 0;
  }

  .employer-settings-notifications {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .employer-settings-notif-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid #f3f4f6;
  }

  .employer-settings-notif-item:last-child {
    border-bottom: none;
  }

  .employer-settings-notif-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .employer-settings-notif-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #050505;
  }

  .employer-settings-notif-desc {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.4;
  }

  .employer-settings-toggle {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
  }

  .employer-settings-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
  }

  .employer-settings-toggle-slider {
    position: absolute;
    inset: 0;
    background: #d1d5db;
    border-radius: 26px;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .employer-settings-toggle-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    top: 3px;
    left: 3px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
  }

  .employer-settings-toggle input:checked + .employer-settings-toggle-slider {
    background: linear-gradient(135deg, #12b3ac, #10B981);
  }

  .employer-settings-toggle input:checked + .employer-settings-toggle-slider::before {
    transform: translateX(22px);
  }

  .employer-settings-panel {
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    padding: 28px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .employer-settings-panel:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }

  .employer-settings-title {
    margin: 0 0 24px;
    font-size: 1.25rem;
    font-weight: 700;
    color: #050505;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .employer-settings-title i {
    color: #12b3ac;
    font-size: 1.1rem;
  }

  .employer-settings-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .employer-settings-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .employer-settings-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .employer-settings-label i {
    font-size: 0.75rem;
    color: #12b3ac;
  }

  .employer-settings-input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    background: #fafbfc;
    color: #0f172a;
    font-size: 0.9rem;
    padding: 13px 16px;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
  }

  .employer-settings-input:hover { border-color: #d1d5db; }

  .employer-settings-input:focus {
    border-color: #12b3ac;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(18, 179, 172, 0.1);
  }

  .employer-settings-input[readonly] {
    background: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
    border-style: dashed;
  }

  .employer-settings-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 14px;
    border: none;
    min-height: 48px;
    padding: 0 28px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    background: linear-gradient(135deg, #050505 0%, #1a1a1a 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(5, 5, 5, 0.2);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .employer-settings-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #12b3ac 0%, #10B981 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .employer-settings-btn:hover::before { opacity: 1; }

  .employer-settings-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(18, 179, 172, 0.3);
  }

  .employer-settings-btn span,
  .employer-settings-btn i { position: relative; z-index: 1; }
  .employer-settings-btn:active { transform: translateY(0); }

  .employer-settings-error,
  .employer-settings-success {
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .employer-settings-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.04) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #dc2626;
  }

  .employer-settings-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(34, 197, 94, 0.04) 100%);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: #16a34a;
  }

  .password-strength-bar {
    height: 4px;
    border-radius: 4px;
    background: #e5e7eb;
    margin-top: 8px;
    overflow: hidden;
  }

  .password-strength-fill {
    height: 100%;
    border-radius: 4px;
    width: 0;
    transition: all 0.3s ease;
  }

  .password-strength-text {
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .epp-status-msg {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 8px;
  }

  .epp-status-msg.show { display: flex; }

  .epp-status-msg.success {
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.2);
    color: #16a34a;
  }

  .epp-status-msg.error {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #dc2626;
  }

  .epp-danger-zone {
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 16px;
    padding: 20px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.03) 0%, rgba(239, 68, 68, 0.01) 100%);
    margin-top: 24px;
  }

  .epp-danger-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
  }

  .epp-danger-desc {
    font-size: 0.82rem;
    color: #6b7280;
    margin-bottom: 16px;
    line-height: 1.5;
  }

  .epp-btn-danger {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    border: 1px solid rgba(239, 68, 68, 0.3);
    background: rgba(239, 68, 68, 0.08);
    color: #dc2626;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
  }

  .epp-btn-danger:hover {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  }

  .epp-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .epp-modal-overlay.open {
    display: flex;
    animation: epp-modal-fade 0.2s ease-out;
  }

  @keyframes epp-modal-fade {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .epp-modal-box {
    background: #fff;
    border-radius: 20px;
    padding: 28px;
    max-width: 440px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    animation: epp-modal-slide 0.3s ease-out;
  }

  @keyframes epp-modal-slide {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  .epp-modal-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.12) 0%, rgba(239, 68, 68, 0.06) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #dc2626;
    margin: 0 auto 16px;
  }

  .epp-modal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #050505;
    text-align: center;
    margin-bottom: 8px;
  }

  .epp-modal-desc {
    font-size: 0.85rem;
    color: #6b7280;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 20px;
  }

  .epp-modal-input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
    margin-bottom: 12px;
  }

  .epp-modal-input:focus {
    border-color: #dc2626;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
  }

  .epp-modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 8px;
  }

  .epp-modal-btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 12px;
    border: none;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
  }

  .epp-modal-btn.cancel {
    background: #f3f4f6;
    color: #6b7280;
  }

  .epp-modal-btn.cancel:hover {
    background: #e5e7eb;
    color: #050505;
  }

  .epp-modal-btn.confirm {
    background: #dc2626;
    color: #fff;
  }

  .epp-modal-btn.confirm:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  }

  .epp-modal-btn.confirm:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  @media (max-width: 800px) {
    .employer-settings-grid-row {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 640px) {
    .employer-settings-panel { padding: 20px; }
  }
</style>

<div class="employer-settings-page">
  <div class="employer-settings-layout">
    <div class="employer-settings-main-col">
      <?php if ($errorMessage !== ''): ?>
        <div class="employer-settings-error" style="margin-bottom:18px;"><i class="fas fa-circle-exclamation"></i> <?php echo dashboard_escape($errorMessage); ?></div>
      <?php endif; ?>
      <?php if ($successMessage !== ''): ?>
        <div class="employer-settings-success" style="margin-bottom:18px;"><i class="fas fa-circle-check"></i> <?php echo dashboard_escape($successMessage); ?></div>
      <?php endif; ?>

      <div class="employer-settings-grid-row">
        <section class="employer-settings-panel">
          <h3 class="employer-settings-title"><i class="fas fa-shield-halved"></i> Security Settings</h3>
          <p style="font-size:0.85rem;color:#6b7280;margin-bottom:20px;">Change your password to keep your account secure.</p>

          <form id="settingsPasswordForm" class="employer-settings-form" novalidate>
            <div class="employer-settings-group">
              <label class="employer-settings-label" for="currentPassword"><i class="fas fa-key"></i> Current Password</label>
              <input id="currentPassword" class="employer-settings-input" type="password" name="current_password" placeholder="Enter current password" required>
              <div class="student-settings-error" data-error-for="current_password" style="font-size:0.78rem;color:#dc2626;min-height:16px;margin-top:4px;"></div>
            </div>

            <div class="employer-settings-group">
              <label class="employer-settings-label" for="newPassword"><i class="fas fa-lock"></i> New Password</label>
              <input id="newPassword" class="employer-settings-input" type="password" name="new_password" placeholder="Enter new password (min 8 characters)" required>
              <div class="password-strength-bar"><div class="password-strength-fill" id="strengthFill"></div></div>
              <div class="password-strength-text" id="strengthText"></div>
              <div class="student-settings-error" data-error-for="new_password" style="font-size:0.78rem;color:#dc2626;min-height:16px;margin-top:4px;"></div>
            </div>

            <div class="employer-settings-group">
              <label class="employer-settings-label" for="confirmPassword"><i class="fas fa-lock"></i> Confirm Password</label>
              <input id="confirmPassword" class="employer-settings-input" type="password" name="confirm_password" placeholder="Confirm new password" required>
              <div class="student-settings-error" data-error-for="confirm_password" style="font-size:0.78rem;color:#dc2626;min-height:16px;margin-top:4px;"></div>
            </div>

            <div id="passwordStatus" class="epp-status-msg"></div>

            <div>
              <button type="submit" id="passwordBtn" class="employer-settings-btn"><i class="fas fa-shield-halved"></i> <span>Update Password</span></button>
            </div>
          </form>
        </section>

        <section class="employer-settings-panel">
          <h3 class="employer-settings-title"><i class="fas fa-bell"></i> Notification Preferences</h3>
          <p style="font-size:0.85rem;color:#6b7280;margin-bottom:20px;">Choose what notifications you want to receive.</p>

          <div class="employer-settings-notifications">
            <div class="employer-settings-notif-item">
              <div class="employer-settings-notif-info">
                <span class="employer-settings-notif-label">New Applications</span>
                <span class="employer-settings-notif-desc">Get notified when a student applies to your internship.</span>
              </div>
              <label class="employer-settings-toggle">
                <input type="checkbox" id="notifApplications" checked>
                <span class="employer-settings-toggle-slider"></span>
              </label>
            </div>

            <div class="employer-settings-notif-item">
              <div class="employer-settings-notif-info">
                <span class="employer-settings-notif-label">Interview Reminders</span>
                <span class="employer-settings-notif-desc">Receive reminders before scheduled interviews.</span>
              </div>
              <label class="employer-settings-toggle">
                <input type="checkbox" id="notifInterviews" checked>
                <span class="employer-settings-toggle-slider"></span>
              </label>
            </div>

            <div class="employer-settings-notif-item">
              <div class="employer-settings-notif-info">
                <span class="employer-settings-notif-label">Account Updates</span>
                <span class="employer-settings-notif-desc">Get notified about verification status and account changes.</span>
              </div>
              <label class="employer-settings-toggle">
                <input type="checkbox" id="notifAccount" checked>
                <span class="employer-settings-toggle-slider"></span>
              </label>
            </div>

            <div class="employer-settings-notif-item">
              <div class="employer-settings-notif-info">
                <span class="employer-settings-notif-label">Marketing & Tips</span>
                <span class="employer-settings-notif-desc">Receive tips and updates about SkillHive features.</span>
              </div>
              <label class="employer-settings-toggle">
                <input type="checkbox" id="notifMarketing">
                <span class="employer-settings-toggle-slider"></span>
              </label>
            </div>
          </div>

          <div style="margin-top:20px;">
            <button type="button" id="saveNotifBtn" class="employer-settings-btn"><i class="fas fa-check"></i> <span>Save Preferences</span></button>
          </div>
        </section>
      </div>

      <section class="employer-settings-panel">
        <div class="epp-danger-zone">
          <div class="epp-danger-title"><i class="fas fa-triangle-exclamation"></i> Danger Zone</div>
          <p class="epp-danger-desc">Once you delete your account, all your data including posted internships, applications, and company information will be permanently removed. This action cannot be undone.</p>
          <button class="epp-btn-danger" id="deleteAccountBtn"><i class="fas fa-trash-can"></i> Delete Account</button>
        </div>
      </section>
    </div>
  </div>
</div>

<div class="epp-modal-overlay" id="deleteModal">
  <div class="epp-modal-box">
    <div class="epp-modal-icon"><i class="fas fa-trash-can"></i></div>
    <div class="epp-modal-title">Delete Your Account?</div>
    <p class="epp-modal-desc">This will permanently remove your company account, all internship postings, and associated data. Type your company name to confirm.</p>
    <input class="epp-modal-input" id="deleteConfirmInput" type="text" placeholder="Type your company name" autocomplete="off">
    <div id="deleteStatus" class="epp-status-msg"></div>
    <div class="epp-modal-actions">
      <button class="epp-modal-btn cancel" id="deleteCancelBtn">Cancel</button>
      <button class="epp-modal-btn confirm" id="deleteConfirmBtn" disabled>Delete Account</button>
    </div>
  </div>
</div>

<script>
(function() {
  var actionsUrl = '<?php echo $actionsUrl; ?>';
  var baseUrl = '<?php echo $baseUrl; ?>';

  function showStatus(el, msg, isError) {
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('success', 'error', 'show');
    if (!msg) return;
    el.classList.add('show', isError ? 'error' : 'success');
  }

  function clearErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach(function(el) { el.textContent = ''; });
  }

  function renderErrors(errors) {
    Object.keys(errors || {}).forEach(function(name) {
      var el = document.querySelector('[data-error-for="' + name + '"]');
      if (el) el.textContent = errors[name];
    });
  }

  /* --- Password Form --- */
  var pwForm = document.getElementById('settingsPasswordForm');
  var pwBtn = document.getElementById('passwordBtn');
  var pwStatus = document.getElementById('passwordStatus');
  var newPw = document.getElementById('newPassword');
  var strengthFill = document.getElementById('strengthFill');
  var strengthText = document.getElementById('strengthText');

  if (newPw) {
    newPw.addEventListener('input', function() {
      var v = newPw.value;
      var score = 0;
      if (v.length >= 8) score++;
      if (v.length >= 12) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;

      var pct = (score / 5) * 100;
      var color = '#dc2626';
      var label = 'Weak';
      var icon = 'fa-circle-xmark';

      if (score >= 4) { color = '#16a34a'; label = 'Strong'; icon = 'fa-circle-check'; }
      else if (score >= 3) { color = '#2563eb'; label = 'Good'; icon = 'fa-circle-info'; }
      else if (score >= 2) { color = '#ea580c'; label = 'Fair'; icon = 'fa-triangle-exclamation'; }

      if (strengthFill) { strengthFill.style.width = pct + '%'; strengthFill.style.background = color; }
      if (strengthText) { strengthText.innerHTML = '<i class="fas ' + icon + '"></i> ' + label; strengthText.style.color = color; }
    });
  }

  if (pwForm) {
    pwForm.addEventListener('submit', function(e) {
      e.preventDefault();
      clearErrors(pwForm);
      showStatus(pwStatus, '', false);

      var cur = String(pwForm.current_password ? pwForm.current_password.value : '');
      var n = String(pwForm.new_password ? pwForm.new_password.value : '');
      var c = String(pwForm.confirm_password ? pwForm.confirm_password.value : '');

      if (pwBtn) { pwBtn.disabled = true; pwBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Updating...</span>'; }

      var body = new FormData();
      body.append('action', 'change_password');
      body.append('current_password', cur);
      body.append('new_password', n);
      body.append('confirm_password', c);

      fetch(actionsUrl, {
        method: 'POST',
        body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      })
      .then(function(r) { return r.text().then(function(t) { try { return JSON.parse(t); } catch(e) { throw {message:'Invalid response.'}; } }); })
      .then(function(data) {
        if (!data || !data.ok) throw { message: (data && data.message) ? data.message : 'Failed.', errors: (data && data.errors) ? data.errors : {} };
        showStatus(pwStatus, data.message || 'Password updated.', false);
        pwForm.reset();
        if (strengthFill) { strengthFill.style.width = '0'; }
        if (strengthText) { strengthText.innerHTML = ''; }
      })
      .catch(function(err) {
        renderErrors(err.errors);
        showStatus(pwStatus, err.message || 'Unable to update password.', true);
      })
      .finally(function() {
        if (pwBtn) { pwBtn.disabled = false; pwBtn.innerHTML = '<i class="fas fa-shield-halved"></i> <span>Update Password</span>'; }
      });
    });
  }

  /* --- Notification Preferences --- */
  var notifKeys = ['notifApplications', 'notifInterviews', 'notifAccount', 'notifMarketing'];
  var storageKey = 'skillhive_employer_notifications';

  function loadNotifPrefs() {
    try {
      var saved = localStorage.getItem(storageKey);
      if (saved) {
        var prefs = JSON.parse(saved);
        notifKeys.forEach(function(key) {
          var el = document.getElementById(key);
          if (el && prefs.hasOwnProperty(key)) el.checked = prefs[key];
        });
      }
    } catch(e) {}
  }

  function saveNotifPrefs() {
    var prefs = {};
    notifKeys.forEach(function(key) {
      var el = document.getElementById(key);
      if (el) prefs[key] = el.checked;
    });
    localStorage.setItem(storageKey, JSON.stringify(prefs));
    var saveBtn = document.getElementById('saveNotifBtn');
    if (saveBtn) {
      var orig = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="fas fa-check"></i> <span>Saved!</span>';
      saveBtn.style.background = 'linear-gradient(135deg, #16a34a, #10B981)';
      setTimeout(function() { saveBtn.innerHTML = orig; saveBtn.style.background = ''; }, 1500);
    }
  }

  loadNotifPrefs();

  var saveNotifBtn = document.getElementById('saveNotifBtn');
  if (saveNotifBtn) saveNotifBtn.addEventListener('click', saveNotifPrefs);

  /* --- Delete Account Modal --- */
  var deleteModal = document.getElementById('deleteModal');
  var deleteBtn = document.getElementById('deleteAccountBtn');
  var deleteCancel = document.getElementById('deleteCancelBtn');
  var deleteConfirm = document.getElementById('deleteConfirmBtn');
  var deleteInput = document.getElementById('deleteConfirmInput');
  var deleteStatus = document.getElementById('deleteStatus');

  if (deleteBtn && deleteModal) {
    deleteBtn.addEventListener('click', function() {
      deleteModal.classList.add('open');
      deleteInput.value = '';
      deleteConfirm.disabled = true;
      if (deleteStatus) { deleteStatus.textContent = ''; deleteStatus.classList.remove('show', 'success', 'error'); }
    });
  }

  if (deleteCancel && deleteModal) {
    deleteCancel.addEventListener('click', function() { deleteModal.classList.remove('open'); });
    deleteModal.addEventListener('click', function(e) { if (e.target === deleteModal) deleteModal.classList.remove('open'); });
  }

  if (deleteInput && deleteConfirm) {
    deleteInput.addEventListener('input', function() {
      deleteConfirm.disabled = deleteInput.value.trim() !== '<?php echo dashboard_escape($form["company_name"]); ?>';
    });
  }

  if (deleteConfirm) {
    deleteConfirm.addEventListener('click', function() {
      deleteConfirm.disabled = true;
      deleteConfirm.textContent = 'Deleting...';

      var body = new FormData();
      body.append('action', 'delete_account');
      body.append('confirm', deleteInput.value.trim());

      fetch(actionsUrl, {
        method: 'POST',
        body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      })
      .then(function(r) { return r.text().then(function(t) { try { return JSON.parse(t); } catch(e) { throw {message:'Invalid response.'}; } }); })
      .then(function(data) {
        if (!data || !data.ok) throw { message: (data && data.message) ? data.message : 'Failed.' };
        if (deleteStatus) { deleteStatus.textContent = data.message; deleteStatus.classList.add('show', 'success'); }
        setTimeout(function() { window.location.href = '<?php echo $baseUrl; ?>/pages/auth/login.php'; }, 2000);
      })
      .catch(function(err) {
        if (deleteStatus) { deleteStatus.textContent = err.message; deleteStatus.classList.add('show', 'error'); }
        deleteConfirm.disabled = false;
        deleteConfirm.textContent = 'Delete Account';
      });
    });
  }
})();
</script>