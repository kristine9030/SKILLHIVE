<?php
require_once __DIR__ . '/../../../backend/db_connect.php';

$studentSettingsUserId = (int)($_SESSION['student_id'] ?? ($userId ?? ($_SESSION['user_id'] ?? 0)));
$emailNotificationsEnabled = true;

if ($studentSettingsUserId > 0) {
  try {
    $stmt = $pdo->prepare(
      'SELECT email_notifications_enabled
             FROM student
             WHERE student_id = :student_id
             LIMIT 1'
    );
    $stmt->execute([':student_id' => $studentSettingsUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) {
      $emailNotificationsEnabled = (int)($row['email_notifications_enabled'] ?? 1) === 1;
    }
  } catch (Throwable $e) {
    // Non-fatal: keep default checked state when DB read fails.
  }
}

$settingsActionUrl = (isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : '/SkillHive') . '/pages/student/settings/actions.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Settings</h2>
    <p class="page-subtitle">Manage your account preferences and notifications.</p>
  </div>
</div>

<style>
  .student-settings-error {
    min-height: 16px;
    margin-top: 6px;
    font-size: .78rem;
    color: #dc2626;
  }

  .student-settings-status {
    display: none;
    margin-top: 12px;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: .82rem;
    border: 1px solid transparent;
  }

  .student-settings-status.is-success {
    display: block;
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
  }

  .student-settings-status.is-error {
    display: block;
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
  }

  /* Fallback toggle visuals for this page to avoid stale-cache/override issues. */
  .feed-main .toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    cursor: pointer;
  }

  .feed-main .toggle-switch input {
    position: absolute;
    inset: 0;
    margin: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
  }

  .feed-main .toggle-switch .toggle-slider {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: #e5e7eb;
    border: 1px solid #d1d5db;
    transition: background-color .2s ease, border-color .2s ease;
    pointer-events: none;
  }

  .feed-main .toggle-switch .toggle-slider::before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
    transition: transform .2s ease;
  }

  .feed-main .toggle-switch.is-on .toggle-slider {
    background: #06b6d4;
    border-color: #0891b2;
  }

  .feed-main .toggle-switch.is-on .toggle-slider::before {
    transform: translateX(20px);
  }

  .feed-main .toggle-switch.is-disabled {
    opacity: .6;
    cursor: not-allowed;
  }
</style>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Account Settings -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Account Information</h3>
      </div>
      <form style="display:flex;flex-direction:column;gap:16px">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-input" value="<?php echo htmlspecialchars($userName); ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-input" value="<?php echo htmlspecialchars($userEmail); ?>" readonly>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role</label>
            <input type="text" class="form-input" value="<?php echo ucfirst(htmlspecialchars($role)); ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Member Since</label>
            <input type="text" class="form-input" value="January 2025" readonly>
          </div>
        </div>
      </form>
    </div>

    <!-- Change Password -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Change Password</h3>
      </div>
      <form id="studentChangePasswordForm" style="display:flex;flex-direction:column;gap:16px" novalidate>
        <div class="form-group">
          <label class="form-label" for="studentCurrentPassword">Current Password</label>
          <input type="password" id="studentCurrentPassword" name="current_password" class="form-input" placeholder="Enter current password" required>
          <div class="student-settings-error" data-error-for="current_password"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="studentNewPassword">New Password</label>
            <input type="password" id="studentNewPassword" name="new_password" class="form-input" placeholder="Enter new password" required>
            <div class="student-settings-error" data-error-for="new_password"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="studentConfirmPassword">Confirm Password</label>
            <input type="password" id="studentConfirmPassword" name="confirm_password" class="form-input" placeholder="Confirm new password" required>
            <div class="student-settings-error" data-error-for="confirm_password"></div>
          </div>
        </div>
        <div><button type="submit" id="studentChangePasswordBtn" class="btn btn-primary btn-sm">Update Password</button></div>
        <div id="studentPasswordStatus" class="student-settings-status" role="status" aria-live="polite"></div>
      </form>
    </div>

    <!-- Notification Preferences -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h3>Notifications</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="mini-row">
          <span><strong>Email Notifications</strong><br><span style="font-size:.78rem;color:#999">Receive updates about applications via email</span></span>
          <label class="toggle-switch"><input type="checkbox" id="studentEmailNotificationsToggle" <?php echo $emailNotificationsEnabled ? 'checked' : ''; ?>><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>New Match Alerts</strong><br><span style="font-size:.78rem;color:#999">Get notified when AI finds new matches</span></span>
          <label class="toggle-switch"><input type="checkbox" class="student-ui-toggle" data-ui-key="new_match_alerts" checked><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>Application Updates</strong><br><span style="font-size:.78rem;color:#999">Status changes on your applications</span></span>
          <label class="toggle-switch"><input type="checkbox" class="student-ui-toggle" data-ui-key="application_updates" checked><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>OJT Reminders</strong><br><span style="font-size:.78rem;color:#999">Reminders for daily log entries</span></span>
          <label class="toggle-switch"><input type="checkbox" class="student-ui-toggle" data-ui-key="ojt_reminders"><span class="toggle-slider"></span></label>
        </div>
        <div id="studentNotificationStatus" class="student-settings-status" role="status" aria-live="polite"></div>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Danger Zone -->
    <div class="panel-card" style="border:1px solid rgba(239,68,68,.2)">
      <div class="panel-card-header">
        <h3 style="color:#EF4444">Danger Zone</h3>
      </div>
      <p style="font-size:.82rem;color:#999;margin-bottom:14px">Once you delete your account, there is no going back.</p>
      <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.2);width:100%;justify-content:center"><i class="fas fa-trash"></i> Delete Account</button>
    </div>
  </div>
</div>

<script>
  (function initStudentSettings() {
    var settingsActionsUrl = <?php echo json_encode($settingsActionUrl, JSON_UNESCAPED_SLASHES); ?>;

    function syncToggleVisual(inputEl) {
      if (!inputEl) {
        return;
      }

      var switchEl = inputEl.closest('.toggle-switch');
      if (!switchEl) {
        return;
      }

      switchEl.classList.toggle('is-on', !!inputEl.checked);
      switchEl.classList.toggle('is-disabled', !!inputEl.disabled);
    }

    function showStatus(element, message, isError) {
      if (!element) {
        return;
      }

      element.textContent = String(message || '');
      element.classList.remove('is-success', 'is-error');

      if (!message) {
        element.style.display = 'none';
        return;
      }

      element.classList.add(isError ? 'is-error' : 'is-success');
      element.style.display = 'block';
    }

    function postSettingsAction(action, payload) {
      var body = new FormData();
      body.append('action', String(action || ''));

      Object.keys(payload || {}).forEach(function(key) {
        body.append(key, payload[key]);
      });

      return fetch(settingsActionsUrl, {
        method: 'POST',
        body: body,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      }).then(function(response) {
        return response.text().then(function(text) {
          var data = null;

          try {
            data = JSON.parse(text);
          } catch (error) {
            throw {
              message: 'Server returned an invalid response.'
            };
          }

          if (!response.ok || !data || !data.ok) {
            throw {
              message: (data && data.message) ? data.message : 'Request failed.',
              errors: (data && data.errors) ? data.errors : {}
            };
          }

          return data;
        });
      });
    }

    var emailToggle = document.getElementById('studentEmailNotificationsToggle');
    var notificationStatus = document.getElementById('studentNotificationStatus');

    if (emailToggle) {
      syncToggleVisual(emailToggle);

      emailToggle.addEventListener('change', function() {
        var requestedState = emailToggle.checked;
        emailToggle.disabled = true;
        syncToggleVisual(emailToggle);

        postSettingsAction('toggle_email_notifications', {
            enabled: requestedState ? '1' : '0'
          })
          .then(function(data) {
            emailToggle.checked = !!data.enabled;
            syncToggleVisual(emailToggle);
            showStatus(notificationStatus, data.message || 'Notification preference saved.', false);
          })
          .catch(function(error) {
            emailToggle.checked = !requestedState;
            syncToggleVisual(emailToggle);
            showStatus(notificationStatus, error && error.message ? error.message : 'Unable to save notification preference.', true);
          })
          .finally(function() {
            emailToggle.disabled = false;
            syncToggleVisual(emailToggle);
          });
      });
    }

    var uiOnlyToggles = document.querySelectorAll('.student-ui-toggle');
    uiOnlyToggles.forEach(function(toggle) {
      var key = toggle.getAttribute('data-ui-key');
      if (!key) {
        return;
      }

      var storageKey = 'student_settings_ui_' + key;
      try {
        var savedState = window.localStorage.getItem(storageKey);
        if (savedState === '1' || savedState === '0') {
          toggle.checked = savedState === '1';
        } else {
          window.localStorage.setItem(storageKey, toggle.checked ? '1' : '0');
        }
      } catch (error) {
        // Ignore localStorage availability errors.
      }

      syncToggleVisual(toggle);

      toggle.addEventListener('change', function() {
        syncToggleVisual(toggle);

        try {
          window.localStorage.setItem(storageKey, toggle.checked ? '1' : '0');
        } catch (error) {
          // Ignore localStorage availability errors.
        }
      });
    });

    var passwordForm = document.getElementById('studentChangePasswordForm');
    var passwordStatus = document.getElementById('studentPasswordStatus');
    var passwordBtn = document.getElementById('studentChangePasswordBtn');

    function clearPasswordErrors() {
      if (!passwordForm) {
        return;
      }

      var errorEls = passwordForm.querySelectorAll('[data-error-for]');
      errorEls.forEach(function(el) {
        el.textContent = '';
      });
    }

    function renderPasswordErrors(errors) {
      if (!passwordForm || !errors) {
        return;
      }

      Object.keys(errors).forEach(function(name) {
        var fieldError = passwordForm.querySelector('[data-error-for="' + name + '"]');
        if (fieldError) {
          fieldError.textContent = String(errors[name] || '');
        }
      });
    }

    if (passwordForm) {
      passwordForm.addEventListener('submit', function(event) {
        event.preventDefault();

        clearPasswordErrors();
        showStatus(passwordStatus, '', false);

        var currentPassword = String(passwordForm.current_password ? passwordForm.current_password.value : '');
        var newPassword = String(passwordForm.new_password ? passwordForm.new_password.value : '');
        var confirmPassword = String(passwordForm.confirm_password ? passwordForm.confirm_password.value : '');

        if (passwordBtn) {
          passwordBtn.disabled = true;
          passwordBtn.textContent = 'Updating...';
        }

        postSettingsAction('change_password', {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
          })
          .then(function(data) {
            showStatus(passwordStatus, data.message || 'Password updated successfully.', false);
            passwordForm.reset();
          })
          .catch(function(error) {
            renderPasswordErrors(error && error.errors ? error.errors : {});
            showStatus(passwordStatus, error && error.message ? error.message : 'Unable to update password right now.', true);
          })
          .finally(function() {
            if (passwordBtn) {
              passwordBtn.disabled = false;
              passwordBtn.textContent = 'Update Password';
            }
          });
      });
    }
  })();
</script>