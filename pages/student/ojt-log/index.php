
<?php
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/ojt_log_helpers.php';
require_once __DIR__ . '/ojt_log_data.php';
require_once __DIR__ . '/ojt_log_submit.php';
require_once __DIR__ . '/ojt_log_job.php';
// Assume $userId is set from session or global
if (!isset($userId) && isset($_SESSION['user_id'])) {
  $userId = $_SESSION['user_id'];
}

$errorMsg = '';
$successMsg = '';

// Get (or auto-create) student's OJT record.
$ojt = ojt_get_or_create_record($pdo, (int) $userId);

$submitResult = ojt_log_handle_submit($pdo, $ojt);
$errorMsg = (string) ($submitResult['errorMsg'] ?? '');
$successMsg = (string) ($submitResult['successMsg'] ?? '');

?>
<?php
$ojtAjaxUrl = (isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : '/Skillhive') . '/pages/student/ojt-log.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">OJT Tracker</h2>
    <p class="page-subtitle">Log your daily accomplishments and track internship hours.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openOjtModal()"><i class="fas fa-plus"></i> Log Entry</button>
</div>

<?php if ($errorMsg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>
<?php if ($successMsg): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>

<!-- Log Entry Modal -->
<div id="logModal" class="modal-overlay" aria-hidden="true" onclick="if(event.target===this){closeOjtModal();}">
  <div class="modal ojt-log-modal" role="dialog" aria-modal="true" aria-labelledby="ojtModalTitle">
    <div class="modal-header ojt-log-modal-header">
      <h3 id="ojtModalTitle" class="modal-title">Log OJT Entry</h3>
      <button type="button" class="modal-close" aria-label="Close" onclick="closeOjtModal()">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="ojt-log-form" data-ajax-url="<?php echo htmlspecialchars($ojtAjaxUrl); ?>">
      <input type="hidden" name="log_entry" value="1">
      <div class="ojt-field">
        <label for="log_date">Date</label>
        <input type="date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div class="ojt-field">
        <label for="accomplishment">Accomplishment / Task</label>
        <textarea name="accomplishment" class="form-control" rows="3" required></textarea>
      </div>
      <div class="ojt-field">
        <label for="hours_rendered">Hours Rendered</label>
        <input type="number" name="hours_rendered" class="form-control" min="0.5" max="12" step="0.25" required>
      </div>
      <div class="ojt-field">
        <label for="mood_tag">Mood</label>
        <select name="mood_tag" class="form-control">
          <option value="Productive">Productive</option>
          <option value="Neutral">Neutral</option>
          <option value="Challenging">Challenging</option>
        </select>
      </div>
      <div class="ojt-field">
        <label for="task_file">Attach File (optional)</label>
        <div class="ojt-file-input">
          <input type="file" id="task_file" name="task_file" class="ojt-file-native">
          <label for="task_file" class="ojt-file-btn">Choose file</label>
          <span id="taskFileName" class="ojt-file-name">No file chosen</span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary ojt-submit-btn">Submit Log</button>
    </form>
  </div>
</div>

<?php
$summary = ojt_load_summary($pdo, $ojt);
$hoursLogged = $summary['hoursLogged'];
$hoursTarget = $summary['hoursTarget'];
$progress = $summary['progress'];
$daysPresent = $summary['daysPresent'];
$tasksCompleted = $summary['tasksCompleted'];
$logs = ojt_log_load_logs($pdo, $ojt);
?>
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(6,182,212,.1)"><i class="fas fa-clock" style="color:#06B6D4"></i></div>
    <div class="stat-card-info"><div class="stat-card-num" id="ojtHoursLogged"><?php echo (float)$hoursLogged; ?></div><div class="stat-card-label">Hours Logged</div></div>
    <div class="stat-card-trend neutral">of <span id="ojtHoursTarget"><?php echo (float)$hoursTarget; ?></span> target</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-calendar-day" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num" id="ojtDaysPresent"><?php echo (int)$daysPresent; ?></div><div class="stat-card-label">Days Present</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(245,158,11,.1)"><i class="fas fa-tasks" style="color:#F59E0B"></i></div>
    <div class="stat-card-info"><div class="stat-card-num" id="ojtTasksCompleted"><?php echo (int)$tasksCompleted; ?></div><div class="stat-card-label">Tasks Completed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-percentage" style="color:#10B981"></i></div>
    <div class="stat-card-info"><div class="stat-card-num" id="ojtProgressPct"><?php echo $progress; ?>%</div><div class="stat-card-label">Progress</div></div>
  </div>
</div>

<!-- Progress Bar -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Overall Progress</h3></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <span style="font-size:.85rem;color:#666"><span id="ojtProgressHours"><?php echo (float) $hoursLogged; ?> of <?php echo (float) $hoursTarget; ?></span> hours completed</span>
    <span id="ojtProgressPctTop" style="font-weight:700;color:#06B6D4"><?php echo $progress; ?>%</span>
  </div>
  <div class="progress-bar">
    <div id="ojtProgressFill" class="progress-fill" style="width:<?php echo $progress; ?>%;background:linear-gradient(90deg,#06B6D4,#10B981)"></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.75rem;color:#999">
    <span>Started: Nov 4, 2024</span>
    <span>Target: Mar 28, 2025</span>
  </div>
</div>

<!-- Recent Logs -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Activity Log</h3></div>
  <div class="app-table-wrap">
    <table class="app-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Task / Activity</th>
          <th>Hours</th>
          <th>Mood</th>
          <th>File</th>
        </tr>
      </thead>
      <tbody id="ojtLogTableBody">
        <?php
        if ($ojt) {
          if ($logs) {
            foreach ($logs as $log) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars(date('M d, Y', strtotime((string) $log['log_date']))) . '</td>';
              echo '<td>' . nl2br(htmlspecialchars((string) $log['accomplishment'])) . '</td>';
              echo '<td>' . (float) ($log['hours_rendered'] ?? 0) . '</td>';
              echo '<td>' . htmlspecialchars((string) ($log['mood_tag'] ?? '')) . '</td>';
              if (!empty($log['task_file'])) {
                $fileUrl = '/Skillhive/assets/backend/uploads/ojt_logs/' . rawurlencode((string) $log['task_file']);
                echo '<td><a href="' . $fileUrl . '" target="_blank">View</a></td>';
              } else {
                echo '<td>-</td>';
              }
              echo '</tr>';
            }
          } else {
            echo '<tr><td colspan="5" style="text-align:center;color:#999">No log entries yet.</td></tr>';
          }
        } else {
          echo '<tr><td colspan="5" style="text-align:center;color:#999">No OJT record found.</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Weekly Chart Placeholder -->
<div class="panel-card">
  <div class="panel-card-header"><h3>Weekly Hours</h3></div>
  <div style="display:flex;align-items:flex-end;gap:8px;height:140px;padding:10px 0">
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:85%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Mon</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:100%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Tue</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:75%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Wed</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:100%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Thu</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:linear-gradient(180deg,#06B6D4,#10B981);height:50%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Fri</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:#e5e5e5;height:0%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Sat</div>
    </div>
    <div style="flex:1;text-align:center">
      <div style="background:#e5e5e5;height:0%;border-radius:6px 6px 0 0"></div>
      <div style="font-size:.7rem;color:#999;margin-top:4px">Sun</div>
    </div>
  </div>
</div>

<script>
function openOjtModal() {
  var modal = document.getElementById('logModal');
  if (!modal) return;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
}

function closeOjtModal() {
  var modal = document.getElementById('logModal');
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
}

(function bindFileInputLabel() {
  var input = document.getElementById('task_file');
  var fileName = document.getElementById('taskFileName');
  var wrapper = input ? input.closest('.ojt-file-input') : null;
  if (!input || !fileName) return;
  input.addEventListener('change', function () {
    var hasFile = !!(input.files && input.files.length > 0);
    fileName.textContent = hasFile ? input.files[0].name : 'No file chosen';
    if (wrapper) {
      wrapper.classList.toggle('has-file', hasFile);
    }
  });
})();

(function bindAjaxLogSubmit() {
  var form = document.querySelector('.ojt-log-form');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var submitBtn = form.querySelector('.ojt-submit-btn');
    var originalBtnText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }

    var endpoint = form.getAttribute('data-ajax-url') || window.location.href;

    fetch(endpoint, {
      method: 'POST',
      body: new FormData(form),
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
      .then(function (response) {
        var contentType = response.headers.get('content-type') || '';
        if (contentType.indexOf('application/json') !== -1) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        }
        return response.text().then(function (text) {
          return {
            ok: false,
            data: {
              ok: false,
              message: 'Server returned non-JSON response. Please refresh and try again.',
              raw: text
            }
          };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          throw new Error((result.data && result.data.message) ? result.data.message : 'Failed to save log entry.');
        }

        updateOjtStats(result.data.stats || {});
        prependOjtLogRow(result.data.entry || {});
        resetOjtFormUi(form);
        closeOjtModal();
      })
      .catch(function (error) {
        alert(error.message || 'Failed to save log entry.');
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText || 'Submit Log';
        }
      });
  });
})();

function updateOjtStats(stats) {
  var hoursLogged = document.getElementById('ojtHoursLogged');
  var hoursTarget = document.getElementById('ojtHoursTarget');
  var daysPresent = document.getElementById('ojtDaysPresent');
  var tasksCompleted = document.getElementById('ojtTasksCompleted');
  var progressPct = document.getElementById('ojtProgressPct');
  var progressPctTop = document.getElementById('ojtProgressPctTop');
  var progressHours = document.getElementById('ojtProgressHours');
  var progressFill = document.getElementById('ojtProgressFill');

  if (hoursLogged) hoursLogged.textContent = Number(stats.hours_logged || 0).toFixed(2).replace(/\.00$/, '');
  if (hoursTarget) hoursTarget.textContent = Number(stats.hours_target || 0).toFixed(2).replace(/\.00$/, '');
  if (daysPresent) daysPresent.textContent = String(stats.days_present || 0);
  if (tasksCompleted) tasksCompleted.textContent = String(stats.tasks_completed || 0);
  if (progressPct) progressPct.textContent = String(stats.progress || 0) + '%';
  if (progressPctTop) progressPctTop.textContent = String(stats.progress || 0) + '%';
  if (progressHours) {
    var hLogged = Number(stats.hours_logged || 0).toFixed(2).replace(/\.00$/, '');
    var hTarget = Number(stats.hours_target || 0).toFixed(2).replace(/\.00$/, '');
    progressHours.textContent = hLogged + ' of ' + hTarget;
  }
  if (progressFill) progressFill.style.width = String(stats.progress || 0) + '%';
}

function prependOjtLogRow(entry) {
  var tbody = document.getElementById('ojtLogTableBody');
  if (!tbody) return;

  var placeholder = tbody.querySelector('td[colspan="5"]');
  if (placeholder) {
    var row = placeholder.closest('tr');
    if (row) row.remove();
  }

  var tr = document.createElement('tr');
  var safeText = function (value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] || char;
    });
  };

  tr.innerHTML = ''
    + '<td>' + safeText(entry.date_display || '') + '</td>'
    + '<td>' + safeText(entry.accomplishment || '') + '</td>'
    + '<td>' + safeText(entry.hours || '') + '</td>'
    + '<td>' + safeText(entry.mood || '') + '</td>'
    + '<td>' + (entry.file_url ? ('<a href="' + safeText(entry.file_url) + '" target="_blank">View</a>') : '-') + '</td>';

  tbody.insertBefore(tr, tbody.firstChild);
}

function resetOjtFormUi(form) {
  form.reset();
  var fileName = document.getElementById('taskFileName');
  var input = document.getElementById('task_file');
  var wrapper = input ? input.closest('.ojt-file-input') : null;
  if (fileName) fileName.textContent = 'No file chosen';
  if (wrapper) wrapper.classList.remove('has-file');
}
</script>