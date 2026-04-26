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
$ojtAjaxUrl = (isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : '/SkillHive') . '/pages/student/ojt-log/submit.php';
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
$calendarLogs = ojt_log_load_logs($pdo, $ojt, 500);

$calendarEntriesByDate = [];
foreach ($calendarLogs as $calendarLog) {
  $dateKey = trim((string) ($calendarLog['log_date'] ?? ''));
  if ($dateKey === '') {
    continue;
  }

  if (!isset($calendarEntriesByDate[$dateKey])) {
    $calendarEntriesByDate[$dateKey] = [
      'count' => 0,
      'hours' => 0,
    ];
  }

  $calendarEntriesByDate[$dateKey]['count']++;
  $calendarEntriesByDate[$dateKey]['hours'] += (float) ($calendarLog['hours_rendered'] ?? 0);
}

$calendarEntriesJson = json_encode($calendarEntriesByDate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($calendarEntriesJson) || $calendarEntriesJson === '') {
  $calendarEntriesJson = '{}';
}
?>
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(18,179,172,.12)"><i class="fas fa-clock" style="color:#12b3ac"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num" id="ojtHoursLogged"><?php echo (float)$hoursLogged; ?></div>
      <div class="stat-card-label">Hours Logged</div>
    </div>
    <div class="stat-card-trend neutral">of <span id="ojtHoursTarget"><?php echo (float)$hoursTarget; ?></span> target</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-calendar-day" style="color:#12b3ac"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num" id="ojtDaysPresent"><?php echo (int)$daysPresent; ?></div>
      <div class="stat-card-label">Days Present</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(18,179,172,.12)"><i class="fas fa-tasks" style="color:#12b3ac"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num" id="ojtTasksCompleted"><?php echo (int)$tasksCompleted; ?></div>
      <div class="stat-card-label">Tasks Completed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon" style="background:rgba(16,185,129,.1)"><i class="fas fa-percentage" style="color:#12b3ac"></i></div>
    <div class="stat-card-info">
      <div class="stat-card-num" id="ojtProgressPct"><?php echo $progress; ?>%</div>
      <div class="stat-card-label">Progress</div>
    </div>
  </div>
</div>

<!-- Progress Bar -->
<div class="panel-card">
  <div class="panel-card-header">
    <h3>Overall Progress</h3>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <span style="font-size:.85rem;color:#666"><span id="ojtProgressHours"><?php echo (float) $hoursLogged; ?> of <?php echo (float) $hoursTarget; ?></span> hours completed</span>
    <span id="ojtProgressPctTop" style="font-weight:700;color:#12b3ac"><?php echo $progress; ?>%</span>
  </div>
  <div class="progress-bar">
    <div id="ojtProgressFill" class="progress-fill" style="width:<?php echo $progress; ?>%;background:linear-gradient(90deg,#12b3ac,#12b3ac)"></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.75rem;color:#999">
    <span>Started: Nov 4, 2024</span>
    <span>Target: Mar 28, 2025</span>
  </div>
</div>

<style>
  .ojt-calendar-panel .panel-card-header {
    flex-wrap: wrap;
    gap: 10px;
  }

  .ojt-calendar-nav {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .ojt-calendar-nav-btn {
    width: 30px;
    height: 30px;
    border: 1px solid var(--border, #ddd);
    border-radius: 8px;
    background: #fff;
    color: var(--text, #333);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .18s ease;
  }

  .ojt-calendar-nav-btn:hover {
    border-color: #12b3ac;
    color: #12b3ac;
  }

  .ojt-calendar-month-label {
    min-width: 160px;
    text-align: center;
    font-weight: 700;
    color: var(--text, #111);
  }

  .ojt-calendar-weekdays,
  .ojt-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
  }

  .ojt-calendar-weekday {
    text-align: center;
    font-size: .72rem;
    color: #6b7280;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .ojt-calendar-cell {
    min-height: 72px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    background: #fff;
    padding: 8px;
    position: relative;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
  }

  .ojt-calendar-cell.is-other-month {
    opacity: .48;
    background: #ffffff;
  }

  .ojt-calendar-cell.is-today {
    border-color: #12b3ac;
  }

  .ojt-calendar-cell.is-selected {
    border-color: #12b3ac;
    box-shadow: 0 0 0 3px rgba(6, 182, 212, .12);
  }

  .ojt-calendar-cell.has-entry {
    background: #f0f9ff;
  }

  .ojt-calendar-cell-btn {
    width: 100%;
    min-height: 56px;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    cursor: pointer;
  }

  .ojt-calendar-day-number {
    font-size: .85rem;
    font-weight: 700;
    color: var(--text, #111);
  }

  .ojt-calendar-entry-dot {
    position: absolute;
    right: 8px;
    bottom: 8px;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: linear-gradient(135deg, #12b3ac, #12b3ac);
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .ojt-calendar-selected-info {
    margin-top: 12px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 10px 12px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }

  .ojt-calendar-selected-meta {
    font-size: .86rem;
    color: #4b5563;
  }

  .ojt-calendar-selected-meta strong {
    color: #050505;
  }

  .ojt-calendar-log-btn {
    border: 0;
    border-radius: 999px;
    padding: 8px 12px;
    background: #050505;
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
  }

  @media (max-width: 700px) {
    .ojt-calendar-cell {
      min-height: 62px;
      padding: 6px;
    }

    .ojt-calendar-month-label {
      min-width: 130px;
      font-size: .85rem;
    }

    .ojt-calendar-weekday {
      font-size: .66rem;
    }
  }
</style>

<!-- Monthly Calendar -->
<div class="panel-card ojt-calendar-panel">
  <div class="panel-card-header">
    <h3>Monthly Log Calendar</h3>
    <div class="ojt-calendar-nav">
      <button type="button" class="ojt-calendar-nav-btn" id="ojtCalendarPrevBtn" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
      <div class="ojt-calendar-month-label" id="ojtCalendarMonthLabel">Month Year</div>
      <button type="button" class="ojt-calendar-nav-btn" id="ojtCalendarNextBtn" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
    </div>
  </div>

  <div class="ojt-calendar-weekdays">
    <div class="ojt-calendar-weekday">Sun</div>
    <div class="ojt-calendar-weekday">Mon</div>
    <div class="ojt-calendar-weekday">Tue</div>
    <div class="ojt-calendar-weekday">Wed</div>
    <div class="ojt-calendar-weekday">Thu</div>
    <div class="ojt-calendar-weekday">Fri</div>
    <div class="ojt-calendar-weekday">Sat</div>
  </div>

  <div class="ojt-calendar-grid" id="ojtCalendarGrid"></div>

  <div class="ojt-calendar-selected-info" id="ojtCalendarSelectedInfo">
    <div class="ojt-calendar-selected-meta" id="ojtCalendarSelectedMeta">Select a date to prepare your log entry.</div>
    <button type="button" class="ojt-calendar-log-btn" id="ojtCalendarLogBtn">Log for selected date</button>
  </div>
</div>

<script>
  var ojtCalendarEntries = <?php echo $calendarEntriesJson; ?>;
  var ojtCalendarState = {
    year: (new Date()).getFullYear(),
    month: (new Date()).getMonth(),
    selectedDate: null
  };

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
    input.addEventListener('change', function() {
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

    form.addEventListener('submit', function(event) {
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
        .then(function(response) {
          var contentType = response.headers.get('content-type') || '';
          if (contentType.indexOf('application/json') !== -1) {
            return response.json().then(function(data) {
              return {
                ok: response.ok,
                data: data
              };
            });
          }
          return response.text().then(function(text) {
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
        .then(function(result) {
          if (!result.ok || !result.data || !result.data.ok) {
            throw new Error((result.data && result.data.message) ? result.data.message : 'Failed to save log entry.');
          }

          updateOjtStats(result.data.stats || {});
          updateOjtCalendarFromEntry(result.data.entry || {});
          resetOjtFormUi(form);
          closeOjtModal();
        })
        .catch(function(error) {
          alert(error.message || 'Failed to save log entry.');
        })
        .finally(function() {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText || 'Submit Log';
          }
        });
    });
  })();

  function padOjtCalendarPart(value) {
    return String(value).padStart(2, '0');
  }

  function formatOjtCalendarDateKey(year, monthZeroBased, day) {
    return year + '-' + padOjtCalendarPart(monthZeroBased + 1) + '-' + padOjtCalendarPart(day);
  }

  function parseOjtCalendarDateKey(dateKey) {
    var parts = String(dateKey || '').split('-');
    if (parts.length !== 3) return null;
    var y = Number(parts[0]);
    var m = Number(parts[1]);
    var d = Number(parts[2]);
    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) return null;
    if (m < 1 || m > 12 || d < 1 || d > 31) return null;
    return {
      year: y,
      month: m - 1,
      day: d
    };
  }

  function ojtCalendarSelectDate(dateKey) {
    var parsed = parseOjtCalendarDateKey(dateKey);
    if (!parsed) return;

    ojtCalendarState.selectedDate = dateKey;
    ojtCalendarState.year = parsed.year;
    ojtCalendarState.month = parsed.month;

    var dateInput = document.querySelector('.ojt-log-form input[name="log_date"]');
    if (dateInput) {
      dateInput.value = dateKey;
    }

    renderOjtCalendar();
    updateOjtCalendarSelectedInfo();
  }

  function updateOjtCalendarSelectedInfo() {
    var metaEl = document.getElementById('ojtCalendarSelectedMeta');
    if (!metaEl) return;

    var selected = ojtCalendarState.selectedDate;
    if (!selected) {
      metaEl.innerHTML = 'Select a date to prepare your log entry.';
      return;
    }

    var parsed = parseOjtCalendarDateKey(selected);
    if (!parsed) {
      metaEl.innerHTML = 'Select a date to prepare your log entry.';
      return;
    }

    var dateObj = new Date(parsed.year, parsed.month, parsed.day);
    var label = dateObj.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    });
    var details = ojtCalendarEntries[selected] || {
      count: 0,
      hours: 0
    };
    var count = Number(details.count || 0);
    var hours = Number(details.hours || 0);

    if (count > 0) {
      metaEl.innerHTML = '<strong>' + label + '</strong> - ' + count + ' log entr' + (count === 1 ? 'y' : 'ies') + ', ' + hours.toFixed(2).replace(/\.00$/, '') + ' hour' + (hours === 1 ? '' : 's') + ' recorded.';
    } else {
      metaEl.innerHTML = '<strong>' + label + '</strong> - No logs yet. You can add one for this date.';
    }
  }

  function renderOjtCalendar() {
    var grid = document.getElementById('ojtCalendarGrid');
    var monthLabel = document.getElementById('ojtCalendarMonthLabel');
    if (!grid || !monthLabel) return;

    var year = ojtCalendarState.year;
    var month = ojtCalendarState.month;
    var firstDayWeekIndex = new Date(year, month, 1).getDay();
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var daysInPrevMonth = new Date(year, month, 0).getDate();
    var today = new Date();
    var todayKey = formatOjtCalendarDateKey(today.getFullYear(), today.getMonth(), today.getDate());

    monthLabel.textContent = new Date(year, month, 1).toLocaleDateString('en-US', {
      month: 'long',
      year: 'numeric'
    });
    grid.innerHTML = '';

    for (var i = 0; i < firstDayWeekIndex; i++) {
      var prevDay = daysInPrevMonth - firstDayWeekIndex + i + 1;
      var prevCell = document.createElement('div');
      prevCell.className = 'ojt-calendar-cell is-other-month';
      prevCell.innerHTML = '<div class="ojt-calendar-day-number">' + prevDay + '</div>';
      grid.appendChild(prevCell);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var dateKey = formatOjtCalendarDateKey(year, month, day);
      var dayEntry = ojtCalendarEntries[dateKey] || null;
      var hasEntry = !!dayEntry;

      var cell = document.createElement('div');
      var classNames = ['ojt-calendar-cell'];
      if (hasEntry) classNames.push('has-entry');
      if (dateKey === todayKey) classNames.push('is-today');
      if (dateKey === ojtCalendarState.selectedDate) classNames.push('is-selected');
      cell.className = classNames.join(' ');

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ojt-calendar-cell-btn';
      btn.setAttribute('data-date', dateKey);
      btn.innerHTML = '<div class="ojt-calendar-day-number">' + day + '</div>';
      btn.addEventListener('click', function(event) {
        var targetDate = event.currentTarget.getAttribute('data-date');
        if (targetDate) {
          ojtCalendarSelectDate(targetDate);
        }
      });

      cell.appendChild(btn);

      if (hasEntry) {
        var dot = document.createElement('span');
        dot.className = 'ojt-calendar-entry-dot';
        dot.textContent = String(dayEntry.count || 0);
        cell.appendChild(dot);
      }

      grid.appendChild(cell);
    }

    var totalCells = firstDayWeekIndex + daysInMonth;
    var nextCellCount = (7 - (totalCells % 7)) % 7;

    for (var n = 1; n <= nextCellCount; n++) {
      var nextCell = document.createElement('div');
      nextCell.className = 'ojt-calendar-cell is-other-month';
      nextCell.innerHTML = '<div class="ojt-calendar-day-number">' + n + '</div>';
      grid.appendChild(nextCell);
    }
  }

  function updateOjtCalendarFromEntry(entry) {
    var rawDate = String((entry && entry.date_iso) || '').trim();
    if (!rawDate) {
      return;
    }

    if (!ojtCalendarEntries[rawDate]) {
      ojtCalendarEntries[rawDate] = {
        count: 0,
        hours: 0
      };
    }

    ojtCalendarEntries[rawDate].count = Number(ojtCalendarEntries[rawDate].count || 0) + 1;
    ojtCalendarEntries[rawDate].hours = Number(ojtCalendarEntries[rawDate].hours || 0) + Number((entry && entry.hours) || 0);

    ojtCalendarSelectDate(rawDate);
  }

  (function initOjtCalendar() {
    var prevBtn = document.getElementById('ojtCalendarPrevBtn');
    var nextBtn = document.getElementById('ojtCalendarNextBtn');
    var logBtn = document.getElementById('ojtCalendarLogBtn');

    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        ojtCalendarState.month -= 1;
        if (ojtCalendarState.month < 0) {
          ojtCalendarState.month = 11;
          ojtCalendarState.year -= 1;
        }
        renderOjtCalendar();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        ojtCalendarState.month += 1;
        if (ojtCalendarState.month > 11) {
          ojtCalendarState.month = 0;
          ojtCalendarState.year += 1;
        }
        renderOjtCalendar();
      });
    }

    if (logBtn) {
      logBtn.addEventListener('click', function() {
        if (!ojtCalendarState.selectedDate) {
          var today = new Date();
          ojtCalendarSelectDate(formatOjtCalendarDateKey(today.getFullYear(), today.getMonth(), today.getDate()));
        }
        openOjtModal();
      });
    }

    var defaultDateInput = document.querySelector('.ojt-log-form input[name="log_date"]');
    var defaultDate = (defaultDateInput && defaultDateInput.value) ? defaultDateInput.value : '';
    var parsedDefault = parseOjtCalendarDateKey(defaultDate);

    if (parsedDefault) {
      ojtCalendarState.year = parsedDefault.year;
      ojtCalendarState.month = parsedDefault.month;
      ojtCalendarState.selectedDate = defaultDate;
    }

    renderOjtCalendar();
    updateOjtCalendarSelectedInfo();
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

  function resetOjtFormUi(form) {
    form.reset();
    var fileName = document.getElementById('taskFileName');
    var input = document.getElementById('task_file');
    var wrapper = input ? input.closest('.ojt-file-input') : null;
    if (fileName) fileName.textContent = 'No file chosen';
    if (wrapper) wrapper.classList.remove('has-file');
  }
</script>