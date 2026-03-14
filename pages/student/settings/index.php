<div class="page-header">
  <div>
    <h2 class="page-title">Settings</h2>
    <p class="page-subtitle">Manage your account preferences and notifications.</p>
  </div>
</div>

<div class="feed-layout">
  <div class="feed-main">
    <!-- Account Settings -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Account Information</h3></div>
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
      <div class="panel-card-header"><h3>Change Password</h3></div>
      <form style="display:flex;flex-direction:column;gap:16px">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" class="form-input" placeholder="Enter current password">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" class="form-input" placeholder="Enter new password">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" class="form-input" placeholder="Confirm new password">
          </div>
        </div>
        <div><button type="button" class="btn btn-primary btn-sm">Update Password</button></div>
      </form>
    </div>

    <!-- Notification Preferences -->
    <div class="panel-card">
      <div class="panel-card-header"><h3>Notifications</h3></div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="mini-row">
          <span><strong>Email Notifications</strong><br><span style="font-size:.78rem;color:#999">Receive updates about applications via email</span></span>
          <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>New Match Alerts</strong><br><span style="font-size:.78rem;color:#999">Get notified when AI finds new matches</span></span>
          <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>Application Updates</strong><br><span style="font-size:.78rem;color:#999">Status changes on your applications</span></span>
          <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
        </div>
        <div class="mini-row">
          <span><strong>OJT Reminders</strong><br><span style="font-size:.78rem;color:#999">Reminders for daily log entries</span></span>
          <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
        </div>
      </div>
    </div>
  </div>

  <div class="feed-side">
    <!-- Danger Zone -->
    <div class="panel-card" style="border:1px solid rgba(239,68,68,.2)">
      <div class="panel-card-header"><h3 style="color:#EF4444">Danger Zone</h3></div>
      <p style="font-size:.82rem;color:#999;margin-bottom:14px">Once you delete your account, there is no going back.</p>
      <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:#EF4444;border:1px solid rgba(239,68,68,.2);width:100%;justify-content:center"><i class="fas fa-trash"></i> Delete Account</button>
    </div>
  </div>
</div>