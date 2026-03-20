<?php

function applications_render_view(array $ctx): void
{
  $baseUrl = (string) ($ctx['baseUrl'] ?? '');
  $validStatuses = $ctx['validStatuses'] ?? applications_valid_statuses();
  $statusFilter = (string) ($ctx['statusFilter'] ?? '');
  $statusCounts = $ctx['statusCounts'] ?? [];
  $applications = $ctx['applications'] ?? [];
  $recentApplications = $ctx['recentApplications'] ?? [];
  $totalApplied = (int) ($ctx['totalApplied'] ?? 0);
  ?>

<style>
.app-progress-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.42);
  z-index: 10030;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.app-progress-overlay.open {
  display: flex;
}

.app-progress-modal {
  width: 620px;
  max-width: 96vw;
  background: #ffffff;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 56px rgba(15, 23, 42, 0.22);
  overflow: hidden;
}

.app-progress-head {
  padding: 16px 18px;
  border-bottom: 1px solid #eef2f7;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.app-progress-title {
  font-size: 1.02rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: .01em;
}

.app-progress-sub {
  margin-top: 3px;
  font-size: .77rem;
  color: #64748b;
}

.app-progress-close {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  cursor: pointer;
  transition: all .2s ease;
}

.app-progress-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.app-progress-body {
  padding: 14px 16px 16px;
}

.app-progress-current {
  margin-bottom: 12px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.app-progress-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: .73rem;
  border: 1px solid rgba(14, 165, 233, .35);
  background: rgba(14, 165, 233, .08);
  color: #0369a1;
  font-weight: 700;
}

.app-progress-meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: .73rem;
  border: 1px solid #dbe3ee;
  color: #475569;
  background: #f8fafc;
}

.app-steps {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.app-step {
  display: grid;
  grid-template-columns: 30px 1fr;
  gap: 10px;
  align-items: start;
}

.app-step-dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .72rem;
  font-weight: 700;
  border: 1px solid #cbd5e1;
  color: #64748b;
  background: #ffffff;
}

.app-step-card {
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  padding: 10px 12px;
  border-left: 3px solid #cbd5e1;
}

.app-step.done .app-step-dot {
  border-color: rgba(16, 185, 129, .45);
  background: rgba(16, 185, 129, .1);
  color: #047857;
}

.app-step.done .app-step-card {
  border-left-color: rgba(16, 185, 129, .75);
}

.app-step.active .app-step-dot {
  border-color: rgba(14, 165, 233, .65);
  background: rgba(14, 165, 233, .14);
  color: #0369a1;
}

.app-step.active .app-step-card {
  border-left-color: rgba(14, 165, 233, .9);
  box-shadow: 0 0 0 1px rgba(14, 165, 233, .2);
}

.app-step-title-row {
  display: flex;
  align-items: center;
  gap: 7px;
}

.app-step-icon {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .58rem;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #475569;
  flex-shrink: 0;
}

.app-step.done .app-step-icon {
  border-color: rgba(16, 185, 129, .45);
  background: rgba(16, 185, 129, .12);
  color: #047857;
}

.app-step.active .app-step-icon {
  border-color: rgba(14, 165, 233, .55);
  background: rgba(14, 165, 233, .12);
  color: #0369a1;
}

.app-step-title {
  font-size: .84rem;
  font-weight: 700;
  color: #0f172a;
}

.app-step-desc {
  margin-top: 2px;
  font-size: .76rem;
  color: #64748b;
  line-height: 1.5;
}

.app-step-next {
  margin-top: 8px;
  font-size: .75rem;
  color: #334155;
  line-height: 1.5;
  padding-top: 8px;
  border-top: 1px dashed #cbd5e1;
}

.app-step-next strong {
  color: #0f172a;
}

.app-progress-foot {
  padding: 12px 16px 16px;
  border-top: 1px solid #eef2f7;
  display: flex;
  justify-content: flex-end;
}

.app-progress-foot .btn.btn-ghost {
  background: #ffffff;
  color: #0f172a;
  border-color: #dbe3ee;
}

.app-progress-foot .btn.btn-ghost:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

@media (max-width: 640px) {
  .app-progress-overlay {
    padding: 12px;
  }

  .app-progress-head,
  .app-progress-body,
  .app-progress-foot {
    padding-left: 12px;
    padding-right: 12px;
  }

  .app-step-card {
    padding: 9px 10px;
  }
}
</style>

<div class="page-header">
  <div>
    <h2 class="page-title">My Applications</h2>
    <p class="page-subtitle">Track all your internship applications in one place.</p>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-paper-plane" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo $totalApplied; ?></div><div class="stat-card-label">Total Applied</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-hourglass-half" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int) ($statusCounts['Pending'] ?? 0); ?></div><div class="stat-card-label">Pending</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-list-check" style="color:#0891B2"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int) ($statusCounts['Waitlisted'] ?? 0); ?></div><div class="stat-card-label">Waitlisted</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-check-double" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num"><?php echo (int) ($statusCounts['Accepted'] ?? 0); ?></div><div class="stat-card-label">Accepted</div></div>
  </div>
</div>

<div class="panel-card">
  <div class="panel-card-header">
    <h3>Application History</h3>
    <form method="get" action="<?php echo applications_e($baseUrl); ?>/layout.php" class="filter-row" style="gap:8px">
      <input type="hidden" name="page" value="student/applications">
      <select class="filter-select" style="min-width:180px" name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <?php foreach ($validStatuses as $status): ?>
          <option value="<?php echo applications_e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo applications_e($status); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="app-table-wrap">
    <table class="app-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Position</th>
          <th>Date Applied</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$applications): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:#94a3b8;padding:28px">No applications found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($applications as $application): ?>
            <?php $companyName = (string) $application['company_name']; ?>
            <tr data-app-id="<?php echo (int) $application['application_id']; ?>" data-status="<?php echo applications_e((string) $application['status']); ?>"><?php // Marking for polling ?>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="co-logo" style="background:<?php echo applications_company_gradient($companyName); ?>;width:32px;height:32px;font-size:.7rem"><?php echo applications_e(strtoupper(substr($companyName, 0, 1))); ?></div>
                  <div>
                    <div><?php echo applications_e($companyName); ?></div>
                    <?php if ((string) ($application['company_badge_status'] ?? '') !== 'None'): ?>
                      <div style="font-size:.72rem;color:#94a3b8"><?php echo applications_e((string) $application['company_badge_status']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight:600;color:#111"><?php echo applications_e((string) $application['title']); ?></div>
                <?php if ($application['compatibility_score'] !== null): ?>
                  <div style="font-size:.72rem;color:#94a3b8">Compatibility: <?php echo number_format((float) $application['compatibility_score'], 0); ?>%</div>
                <?php endif; ?>
                <?php if (!empty($application['consented_at']) && !empty($application['consent_version'])): ?>
                  <div style="font-size:.72rem;color:#94a3b8">Consent: <?php echo applications_e((string) $application['consent_version']); ?> · <?php echo applications_e(date('M j, Y g:i A', strtotime((string) $application['consented_at']))); ?></div>
                <?php endif; ?>
                <?php if (!empty($application['resume_link_snapshot']) || !empty($application['profile_link_snapshot'])): ?>
                  <div style="font-size:.72rem;color:#94a3b8;display:flex;gap:8px;flex-wrap:wrap;margin-top:2px">
                    <?php if (!empty($application['resume_link_snapshot'])): ?>
                      <a href="<?php echo applications_e((string) $application['resume_link_snapshot']); ?>" target="_blank" rel="noopener noreferrer">Resume Shared</a>
                    <?php endif; ?>
                    <?php if (!empty($application['profile_link_snapshot'])): ?>
                      <a href="<?php echo applications_e((string) $application['profile_link_snapshot']); ?>" target="_blank" rel="noopener noreferrer">Profile Shared</a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo applications_e(date('M j, Y', strtotime((string) $application['application_date']))); ?></td>
              <td>
                <?php $appStatus = (string) $application['status']; ?>
                <span class="status-pill <?php echo applications_status_class($appStatus); ?>"><?php echo applications_e($appStatus); ?></span>
                <div style="margin-top:6px;font-size:.72rem;color:#64748b">Next: <?php echo applications_e(applications_next_step($appStatus)); ?></div>
              </td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <?php
                  $progressStep = applications_progress_step($appStatus);
                  $progressTitle = (string) $application['title'];
                  $progressCompany = (string) $application['company_name'];
                  $progressDate = date('M j, Y', strtotime((string) $application['application_date']));
                ?>
                <button
                  class="btn btn-ghost btn-sm"
                  type="button"
                  onclick="openApplicationProgressModal(this)"
                  data-title="<?php echo applications_e($progressTitle); ?>"
                  data-company="<?php echo applications_e($progressCompany); ?>"
                  data-status="<?php echo applications_e($appStatus); ?>"
                  data-step="<?php echo (int) $progressStep; ?>"
                  data-date="<?php echo applications_e($progressDate); ?>"
                  data-next="<?php echo applications_e(applications_next_step($appStatus)); ?>"
                >View</button>
                <a class="btn btn-ghost btn-sm" href="<?php echo applications_e($baseUrl); ?>/layout.php?page=student/marketplace&amp;detail=<?php echo (int) $application['internship_id']; ?>">Job</a>
                <?php if (in_array((string) $application['status'], ['Pending', 'Shortlisted', 'Waitlisted', 'Interview Scheduled'], true)): ?>
                  <form method="post" onsubmit="return confirm('Cancel this application?');" style="margin:0">
                    <input type="hidden" name="action" value="cancel_application">
                    <input type="hidden" name="application_id" value="<?php echo (int) $application['application_id']; ?>">
                    <input type="hidden" name="status_filter" value="<?php echo applications_e($statusFilter); ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" style="color:#dc2626;border-color:#fecaca">Cancel</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel-card">
  <div class="panel-card-header"><h3>Recent Activity</h3></div>
  <div class="timeline">
    <?php if (!$recentApplications): ?>
      <div style="color:#94a3b8">No application activity yet.</div>
    <?php else: ?>
      <?php foreach ($recentApplications as $application): ?>
        <?php
          $status = (string) $application['status'];
          $dotColor = match ($status) {
              'Accepted' => '#10B981',
              'Interview Scheduled' => '#4F46E5',
              'Shortlisted' => '#06B6D4',
              'Waitlisted' => '#0891B2',
              'Rejected' => '#EF4444',
              default => '#F59E0B',
          };
          $activityTitle = match ($status) {
              'Accepted' => 'Accepted by ' . (string) $application['company_name'],
              'Interview Scheduled' => 'Interview scheduled by ' . (string) $application['company_name'],
              'Shortlisted' => 'Shortlisted by ' . (string) $application['company_name'],
              'Waitlisted' => 'Waitlisted by ' . (string) $application['company_name'],
              'Rejected' => 'Not selected by ' . (string) $application['company_name'],
              default => 'Application sent to ' . (string) $application['company_name'],
          };
        ?>
        <div class="timeline-item">
          <div class="timeline-dot" style="background:<?php echo $dotColor; ?>"></div>
          <div class="timeline-content">
            <div style="font-weight:600;font-size:.85rem"><?php echo applications_e($activityTitle); ?></div>
            <div style="font-size:.75rem;color:#999"><?php echo applications_e((string) $application['title']); ?> — <?php echo applications_e(date('M j, Y', strtotime((string) $application['updated_at']))); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="app-progress-overlay" id="applicationProgressModal" onclick="if(event.target===this)closeApplicationProgressModal()">
  <div class="app-progress-modal">
    <div class="app-progress-head">
      <div>
        <div class="app-progress-title" id="appProgressTitle">Application Progress</div>
        <div class="app-progress-sub" id="appProgressSub">Track your application stages.</div>
      </div>
      <button type="button" class="app-progress-close" onclick="closeApplicationProgressModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="app-progress-body">
      <div class="app-progress-current">
        <span class="app-progress-chip">Status: <span id="appProgressStatus">Pending</span></span>
        <span class="app-progress-meta">Applied: <span id="appProgressDate">-</span></span>
      </div>
      <div class="app-steps" id="appProgressSteps"></div>
    </div>
    <div class="app-progress-foot">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeApplicationProgressModal()">Close</button>
    </div>
  </div>
</div>

<script>
function closeApplicationProgressModal() {
  var modal = document.getElementById('applicationProgressModal');
  if (modal) {
    modal.classList.remove('open');
  }
}

function buildApplicationProgressSteps(currentStep, currentStatus, nextStepText) {
  var steps = [
    {
      title: 'Application Submitted',
      icon: 'fa-paper-plane',
      desc: 'Your internship application was successfully sent to the employer.'
    },
    {
      title: 'Initial Review',
      icon: 'fa-magnifying-glass',
      desc: 'The employer is reviewing your profile, resume, and requirements fit.'
    },
    {
      title: 'Selection Queue',
      icon: 'fa-layer-group',
      desc: 'Your application may move to shortlisted or waitlisted based on available slots.'
    },
    {
      title: 'Interview Stage',
      icon: 'fa-calendar-check',
      desc: 'If required, the employer schedules an interview or further assessment.'
    },
    {
      title: 'Final Decision',
      icon: 'fa-flag-checkered',
      desc: 'The employer marks your application as accepted or rejected.'
    }
  ];

  return steps.map(function (step, index) {
    var stepNumber = index + 1;
    var state = '';
    if (stepNumber < currentStep) {
      state = 'done';
    } else if (stepNumber === currentStep) {
      state = 'active';
    }

    var extra = '';
    if (stepNumber === currentStep) {
      extra = '<div class="app-step-next"><strong>Current:</strong> ' + escapeHtml(currentStatus) + '<br><strong>Next:</strong> ' + escapeHtml(nextStepText) + '</div>';
    }

    return (
      '<div class="app-step ' + state + '">' +
        '<span class="app-step-dot">' + stepNumber + '</span>' +
        '<div class="app-step-card">' +
          '<div class="app-step-title-row">' +
            '<span class="app-step-icon"><i class="fas ' + step.icon + '"></i></span>' +
            '<div class="app-step-title">' + step.title + '</div>' +
          '</div>' +
          '<div class="app-step-desc">' + step.desc + '</div>' +
          extra +
        '</div>' +
      '</div>'
    );
  }).join('');
}

function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function openApplicationProgressModal(button) {
  var modal = document.getElementById('applicationProgressModal');
  if (!modal || !button) return;

  var title = button.getAttribute('data-title') || 'Internship Application';
  var company = button.getAttribute('data-company') || 'Company';
  var status = button.getAttribute('data-status') || 'Pending';
  var step = parseInt(button.getAttribute('data-step') || '1', 10);
  var date = button.getAttribute('data-date') || '-';
  var next = button.getAttribute('data-next') || 'Monitor this application for the next update.';

  var titleEl = document.getElementById('appProgressTitle');
  var subEl = document.getElementById('appProgressSub');
  var statusEl = document.getElementById('appProgressStatus');
  var dateEl = document.getElementById('appProgressDate');
  var stepsEl = document.getElementById('appProgressSteps');

  if (titleEl) titleEl.textContent = title;
  if (subEl) subEl.textContent = company + ' application progress';
  if (statusEl) statusEl.textContent = status;
  if (dateEl) dateEl.textContent = date;
  if (stepsEl) stepsEl.innerHTML = buildApplicationProgressSteps(step, status, next);

  modal.classList.add('open');
}

// ════════════════════════════════════════════════════════════════════════════
// Real-time polling for application status and interview details
// ════════════════════════════════════════════════════════════════════════════

var applicationPolling = {
  enabled: true,
  pollInterval: 5000, // 5 seconds
  pollTimer: null,
  lastData: {},

  start: function() {
    if (!this.enabled) return;
    this.poll();
    this.pollTimer = setInterval(this.poll.bind(this), this.pollInterval);
  },

  stop: function() {
    if (this.pollTimer) {
      clearInterval(this.pollTimer);
      this.pollTimer = null;
    }
  },

  poll: function() {
    var self = applicationPolling;
    fetch('<?php echo applications_e($baseUrl); ?>/pages/student/applications/applications_api.php?action=fetch_applications')
      .then(function(response) {
        if (!response.ok) throw new Error('API error');
        return response.json();
      })
      .then(function(data) {
        if (data.ok && Array.isArray(data.applications)) {
          self.updateApplicationsTable(data.applications);
        }
      })
      .catch(function(error) {
        console.log('Application poll error:', error);
      });
  },

  updateApplicationsTable: function(applications) {
    var self = this;
    var table = document.querySelector('.app-table tbody');
    if (!table) return;

    applications.forEach(function(app) {
      var key = 'app_' + app.application_id;
      var lastStatus = self.lastData[key] ? self.lastData[key].status : null;

      // Status changed
      if (lastStatus && lastStatus !== app.status) {
        console.log('Status updated:', app.application_id, lastStatus, '->', app.status);

        // Flash highlight row
        var row = table.querySelector('tr[data-app-id="' + app.application_id + '"]');
        if (row) {
          row.style.background = '#ecfdf5';
          setTimeout(function() {
            row.style.background = '';
          }, 2000);

          // Show toast notification
          self.showStatusNotification(app);

          // Reload page to show updates
          setTimeout(function() {
            location.reload();
          }, 1500);
        }
      }

      // Interview details added (status is "Interview Scheduled")
      if (app.status === 'Interview Scheduled' && app.interview_date) {
        var row = table.querySelector('tr[data-app-id="' + app.application_id + '"]');
        if (row && !row.querySelector('.interview-details')) {
          self.insertInterviewDetails(row, app);
        }
      }

      self.lastData[key] = app;
    });
  },

  showStatusNotification: function(app) {
    var message = '';
    var bgColor = '#10B981';

    if (app.status === 'Shortlisted') {
      message = '✓ Shortlisted! ' + app.company_name + ' wants to move forward.';
      bgColor = '#06B6D4';
    } else if (app.status === 'Interview Scheduled') {
      message = '📅 Interview scheduled! ' + app.company_name + ' sent you details.';
      bgColor = '#4F46E5';
    } else if (app.status === 'Accepted') {
      message = '🎉 Accepted! Congratulations on your internship!';
      bgColor = '#10B981';
    } else if (app.status === 'Rejected') {
      message = '❌ Application not selected. Check other opportunities!';
      bgColor = '#EF4444';
    }

    if (message) {
      var toast = document.createElement('div');
      toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:' + bgColor + ';color:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.2);z-index:9999;font-weight:600;max-width:320px';
      toast.textContent = message;
      document.body.appendChild(toast);

      setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(function() {
          document.body.removeChild(toast);
        }, 300);
      }, 4000);
    }
  },

  insertInterviewDetails: function(row, app) {
    if (!app.interview_date || !app.interview_time) return;

    var detailsHtml = '<tr class="interview-details" data-app-id="' + app.application_id + '">' +
      '<td colspan="5" style="background:#f0fdf4;padding:12px;border-top:2px solid #86efac">' +
      '<div style="display:flex;gap:16px;align-items:flex-start">' +
      '<div style="flex:1">' +
      '<div style="font-weight:700;color:#059669;margin-bottom:8px;display:flex;gap:6px;align-items:center">' +
      '<i class="fas fa-calendar-check"></i> Interview Scheduled' +
      '</div>' +
      '<div style="display:grid;gap:6px;font-size:.82rem;color:#374151">' +
      '<div><strong>Date:</strong> ' + applicationPolling.formatDate(app.interview_date) + '</div>' +
      '<div><strong>Time:</strong> ' + app.interview_time + '</div>' +
      '<div><strong>Mode:</strong> ' + app.interview_mode + '</div>' +
      (app.venue ? '<div><strong>Venue:</strong> ' + app.venue + '</div>' : '') +
      (app.meeting_link ? '<div><strong>Link:</strong> <a href="' + app.meeting_link + '" target="_blank" rel="noopener">Join Interview</a></div>' : '') +
      '</div>' +
      '</div>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
      '<button class="btn btn-primary btn-sm" onclick="alert(\'Interview RSVP: Confirm your attendance with the employer.\')">RSVP</button>' +
      '<button class="btn btn-ghost btn-sm" onclick="alert(\'Reschedule: Contact employer to propose new time.\')">Reschedule</button>' +
      '</div>' +
      '</div>' +
      '</td></tr>';

    row.insertAdjacentHTML('afterend', detailsHtml);
  },

  formatDate: function(dateStr) {
    try {
      var date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) {
      return dateStr;
    }
  }
};

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
  if (document.querySelector('.app-table tbody')) {
    applicationPolling.start();
  }
});

// Stop polling when user leaves
window.addEventListener('beforeunload', function() {
  applicationPolling.stop();
});
</script>

<?php
}
