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
    <h2 class="page-title" style="background:linear-gradient(135deg,#0f172a 0%,#12b3ac 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;font-weight:800">OJT Tracker</h2>
    <p class="page-subtitle">Log your daily accomplishments and track internship hours.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openOjtModal()"><i class="fas fa-plus"></i> Log Entry</button>
</div>

<?php if ($errorMsg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>
<?php if ($successMsg): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>

<!-- Log Entry Modal -->
<div id="logModal" class="modal-overlay" inert hidden onclick="if(event.target===this){closeOjtModal();}" role="presentation">
  <div class="modal ojt-log-modal" role="dialog" aria-modal="true" aria-labelledby="ojtModalTitle">
    <div class="modal-header ojt-log-modal-header">
      <h3 id="ojtModalTitle" class="modal-title">Log OJT Entry</h3>
      <button type="button" class="modal-close" aria-label="Close" onclick="closeOjtModal()">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="ojt-log-form" data-ajax-url="<?php echo htmlspecialchars($ojtAjaxUrl); ?>">
      <input type="hidden" name="log_entry" value="1">
      <div class="ojt-field ojt-field-date">
        <label for="log_date">Date</label>
        <input type="date" name="log_date" id="ojtLogDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div class="ojt-field ojt-field-date-warning" id="ojtDateWarning" style="display:none;">
        <div class="ojt-warning-banner">
          <i class="fas fa-exclamation-triangle"></i>
          <span id="ojtDateWarningText"></span>
        </div>
      </div>
      <div class="ojt-field">
        <label for="accomplishment">Journal Log</label>
        <textarea name="accomplishment" class="form-control" rows="3" placeholder="Describe what you did, learned, or experienced today..." required></textarea>
      </div>
      <div class="ojt-field">
        <label>Time Rendered</label>
        <div class="ojt-time-row">
          <div class="ojt-time-group">
            <span class="ojt-time-label">Start</span>
            <input type="time" name="start_time" id="ojtStartTime" class="form-control ojt-time-input" required>
          </div>
          <div class="ojt-time-sep">&#8594;</div>
          <div class="ojt-time-group">
            <span class="ojt-time-label">End</span>
            <input type="time" name="end_time" id="ojtEndTime" class="form-control ojt-time-input" required>
          </div>
        </div>
        <div class="ojt-break-row">
          <label class="ojt-break-label" for="ojtBreakMinutes">Break deduction</label>
          <select name="break_minutes" id="ojtBreakMinutes" class="form-control ojt-break-select">
            <option value="0">No break</option>
            <option value="15">15 min</option>
            <option value="30" selected>30 min</option>
            <option value="45">45 min</option>
            <option value="60">1 hour</option>
            <option value="90">1.5 hours</option>
          </select>
        </div>
        <div class="ojt-hours-computed" id="ojtHoursComputed">
          <span class="ojt-hours-computed-label">Hours rendered:</span>
          <span class="ojt-hours-computed-val" id="ojtHoursComputedVal">—</span>
        </div>
        <input type="hidden" name="hours_rendered" id="ojtHoursRenderedHidden">
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
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/OJT Hours.png" alt="Hours Logged"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral">of <?php echo (float)$hoursTarget; ?> target</div>
        <div class="stat-card-num" id="ojtHoursLogged"><?php echo (float)$hoursLogged; ?></div>
      </div>
      <div class="stat-card-label">Hours Logged</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Applications.png" alt="Days Present"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$daysPresent; ?> days</div>
        <div class="stat-card-num" id="ojtDaysPresent"><?php echo (int)$daysPresent; ?></div>
      </div>
      <div class="stat-card-label">Days Present</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Total Evaluation.png" alt="Tasks Completed"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo (int)$tasksCompleted; ?> completed</div>
        <div class="stat-card-num" id="ojtTasksCompleted"><?php echo (int)$tasksCompleted; ?></div>
      </div>
      <div class="stat-card-label">Tasks Completed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon"><img src="/SkillHive/assets/media/Rating.png" alt="Progress"></div>
    <div class="stat-card-info">
      <div class="stat-card-num-row">
        <div class="stat-card-trend neutral"><?php echo $progress; ?>% complete</div>
        <div class="stat-card-num" id="ojtProgressPct"><?php echo $progress; ?>%</div>
      </div>
      <div class="stat-card-label">Progress</div>
    </div>
  </div>
</div>

<style>
  .ojt-file-btn {
    color: #fff !important;
  }

  .ojt-log-modal {
    max-width: 800px;
    width: 95vw;
  }

  .ojt-calendar-panel .panel-card-header {
    flex-wrap: wrap;
    gap: 12px;
  }

  .ojt-calendar-nav {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: #f8fafc;
    padding: 6px 10px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
  }

  .ojt-calendar-nav-btn {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 10px;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  }

  .ojt-calendar-nav-btn:hover {
    background: #12b3ac;
    color: #fff;
    box-shadow: 0 4px 12px rgba(18, 179, 172, 0.3);
    transform: translateY(-1px);
  }

  .ojt-calendar-nav-btn:active {
    transform: translateY(0);
  }

  .ojt-calendar-month-label {
    min-width: 180px;
    text-align: center;
    font-weight: 700;
    font-size: 1.05rem;
    color: #0f172a;
    letter-spacing: -0.01em;
  }

.ojt-calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    margin-bottom: 4px;
    width: 100%;
  }

  .ojt-calendar-weekday {
    text-align: center;
    font-size: .7rem;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 8px 0;
  }

  .ojt-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
    width: 100%;
  }

.ojt-calendar-cell {
    border: none;
    border-radius: 10px;
    background: transparent;
    padding:0;
    position: relative;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 1;
  }

  .ojt-calendar-cell:hover {
    background: #f1f5f9;
  }

  .ojt-calendar-cell.is-other-month {
    opacity: 0.3;
    background: transparent;
  }

  .ojt-calendar-cell.is-other-month:hover {
    opacity: 0.5;
    background: transparent;
  }

  .ojt-calendar-cell.is-today {
    background: #e0f2fe;
  }

  .ojt-calendar-cell.is-today .ojt-calendar-day-number {
    color: #0284c7;
    font-weight: 700;
  }

  .ojt-calendar-cell.is-selected {
    background: #12b3ac;
    box-shadow: 0 2px 8px rgba(18, 179, 172, 0.3);
  }

  .ojt-calendar-cell.is-selected .ojt-calendar-day-number {
    color: #fff;
    font-weight: 700;
  }

  .ojt-calendar-cell.has-entry {
    background: #ccfbf1;
  }

  .ojt-calendar-cell.has-entry .ojt-calendar-day-number {
    color: #0d9488;
    font-weight: 700;
  }

  .ojt-calendar-cell.has-entry.is-selected {
    background: #12b3ac;
  }

  .ojt-calendar-cell.has-entry.is-selected .ojt-calendar-day-number {
    color: #fff;
  }

  .ojt-calendar-cell-btn {
    width: 100%;
    height: 100%;
    border:0;
    background: transparent;
    padding:0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
  }

  .ojt-calendar-day-number {
    font-size: .82rem;
    font-weight: 600;
    color: #334155;
    line-height: 1;
    user-select: none;
  }

  .ojt-calendar-cell.is-other-month .ojt-calendar-day-number {
    color: #cbd5e1;
  }

  .ojt-calendar-entry-dot {
    display: none;
  }

  .ojt-calendar-cell::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: transparent;
    transition: background 0.2s ease;
    border-radius: 12px 12px 0 0;
  }

  .ojt-calendar-cell:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
  }

  .ojt-calendar-cell.is-other-month {
    opacity: 0.35;
    background: #f8fafc;
  }

  .ojt-calendar-cell.is-other-month:hover {
    opacity: 0.5;
    transform: none;
    box-shadow: none;
  }

  .ojt-calendar-cell.is-today {
    border-color: #12b3ac;
    background: #f0fdfa;
    box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.1);
  }

  .ojt-calendar-cell.is-today::before {
    background: linear-gradient(90deg, #12b3ac, #0d9488);
  }

  .ojt-calendar-cell.is-selected {
    border-color: #12b3ac;
    background: #f0fdfa;
    box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.15), 0 4px 16px rgba(18, 179, 172, 0.15);
  }

  .ojt-calendar-cell.is-selected::before {
    background: linear-gradient(90deg, #12b3ac, #0d9488);
  }

  .ojt-calendar-cell.has-entry {
    background: linear-gradient(135deg, #f0fdfa 0%, #e0f2fe 100%);
    border-color: #99f6e4;
  }

  .ojt-calendar-cell.has-entry::before {
    background: linear-gradient(90deg, #12b3ac, #0ea5e9);
  }

  .ojt-calendar-cell-btn {
    width: 100%;
    min-height: 60px;
    border: 0;
    background: transparent;
    padding: 0;
    text-align: left;
    cursor: pointer;
    position: relative;
    z-index: 1;
  }

  .ojt-calendar-day-number {
    font-size: .85rem;
    font-weight: 600;
    color: #334155;
    line-height: 1;
  }

  .ojt-calendar-cell.is-today .ojt-calendar-day-number {
    color: #12b3ac;
    font-weight: 700;
  }

  .ojt-calendar-cell.is-other-month .ojt-calendar-day-number {
    color: #cbd5e1;
  }

  .ojt-calendar-entry-dot {
    position: absolute;
    right: 6px;
    bottom: 6px;
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    background: linear-gradient(135deg, #12b3ac, #0d9488);
    color: #fff;
    font-size: .68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(18, 179, 172, 0.3);
    animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  @keyframes slideIn {
    from {
      transform: scale(0) translateY(10px);
      opacity: 0;
    }
    to {
      transform: scale(1) translateY(0);
      opacity: 1;
    }
  }

  .ojt-calendar-selected-info {
    margin-top: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }

  .ojt-calendar-selected-meta {
    font-size: .86rem;
    color: #64748b;
    line-height: 1.5;
  }

  .ojt-calendar-selected-meta strong {
    color: #0f172a;
    font-weight: 600;
  }

  .ojt-calendar-log-btn {
    border: none;
    border-radius: 10px;
    padding: 10px 18px;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #fff;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
    letter-spacing: 0.01em;
  }

  .ojt-calendar-log-btn:hover {
    background: linear-gradient(135deg, #12b3ac, #0d9488);
    box-shadow: 0 4px 16px rgba(18, 179, 172, 0.35);
    transform: translateY(-1px);
  }

  .ojt-calendar-log-btn:active {
    transform: translateY(0);
  }

  .ojt-calendar-log-btn.is-already-logged {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
  }

  .ojt-calendar-log-btn.is-already-logged:hover {
    background: linear-gradient(135deg, #12b3ac, #0d9488);
    box-shadow: 0 4px 16px rgba(18, 179, 172, 0.35);
  }

  .ojt-calendar-cell.is-future {
    opacity: 0.35;
    pointer-events: none;
    background: #f8fafc;
  }

  @media (max-width: 700px) {
    .ojt-calendar-cell {
      min-height: 64px;
      padding: 8px 6px;
      border-radius: 10px;
    }

    .ojt-calendar-month-label {
      min-width: 140px;
      font-size: .95rem;
    }

    .ojt-calendar-weekday {
      font-size: .65rem;
    }

    .ojt-calendar-nav {
      padding: 4px 8px;
      gap: 8px;
    }

    .ojt-calendar-nav-btn {
      width: 32px;
      height: 32px;
    }
  }

  .ojt-warning-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border: 1px solid #fed7aa;
    border-radius: 12px;
    padding: 11px 14px;
    font-size: .82rem;
    color: #92400e;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
    animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  @keyframes slideDown {
    from {
      transform: translateY(-10px);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  .ojt-warning-banner i {
    color: #f59e0b;
    flex-shrink: 0;
    font-size: .95rem;
  }

  .ojt-time-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
  }

  .ojt-time-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
  }

  .ojt-time-label {
    font-size: .75rem;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.01em;
  }

  .ojt-time-input {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: .88rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: #fff;
  }

  .ojt-time-input:focus {
    outline: none;
    border-color: #12b3ac;
    box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.12);
  }

  .ojt-time-sep {
    font-size: 1.2rem;
    color: #94a3b8;
    margin-top: 20px;
    flex-shrink: 0;
    font-weight: 600;
  }

  .ojt-break-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    padding: 8px 0;
  }

  .ojt-break-label {
    font-size: .78rem;
    color: #64748b;
    white-space: nowrap;
    font-weight: 600;
  }

  .ojt-break-select {
    flex: 1;
    padding: 8px 12px;
    font-size: .82rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
  }

  .ojt-break-select:focus {
    outline: none;
    border-color: #12b3ac;
    box-shadow: 0 0 0 3px rgba(18, 179, 172, 0.12);
  }

  .ojt-hours-computed {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 10px 14px;
    margin-top: 6px;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .ojt-hours-computed-label {
    font-size: .8rem;
    color: #166534;
    font-weight: 600;
  }

  .ojt-hours-computed-val {
    font-size: 1.05rem;
    font-weight: 700;
    color: #15803d;
  }

  .ojt-hours-computed.is-error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-color: #fecaca;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08);
  }

  .ojt-hours-computed.is-error .ojt-hours-computed-label,
  .ojt-hours-computed.is-error .ojt-hours-computed-val {
    color: #991b1b;
  }

  .ojt-hours-computed.is-warning {
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    border-color: #fed7aa;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.08);
  }

  .ojt-hours-computed.is-warning .ojt-hours-computed-label,
  .ojt-hours-computed.is-warning .ojt-hours-computed-val {
    color: #92400e;
  }

.ojt-calendar-progress-layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 20px;
    align-items: start;
  }

  .ojt-calendar-journal-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
  }

  .ojt-journal-panel {
    min-height: 300px;
  }

  .ojt-journal-date {
    font-size: .85rem;
    color: #64748b;
    font-weight: 500;
  }

  .ojt-journal-content {
    padding: 8px 0;
  }

  .ojt-journal-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
  }

  .ojt-journal-empty i {
    font-size: 2rem;
    color: #cbd5e1;
    margin-bottom: 12px;
  }

  .ojt-journal-empty p {
    color: #94a3b8;
    font-size: .85rem;
    margin: 0;
  }

  .ojt-journal-entries {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .ojt-journal-entry {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.2s ease;
  }

  .ojt-journal-entry:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .ojt-journal-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .ojt-journal-time {
    font-size: .75rem;
    color: #64748b;
    font-weight: 500;
  }

  .ojt-journal-mood {
    font-size: .7rem;
    padding: 3px 8px;
    border-radius: 999px;
    font-weight: 600;
  }

  .ojt-journal-mood.Productive {
    background: #dcfce7;
    color: #166534;
  }

  .ojt-journal-mood.Neutral {
    background: #f1f5f9;
    color: #475569;
  }

  .ojt-journal-mood.Challenging {
    background: #fee2e2;
    color: #991b1b;
  }

  .ojt-journal-accomplishment {
    font-size: .85rem;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 8px;
  }

  .ojt-journal-entry-footer {
    display: flex;
    gap: 12px;
    font-size: .75rem;
    color: #94a3b8;
  }

  .ojt-journal-entry-footer i {
    color: #12b3ac;
    margin-right: 4px;
  }

  .ojt-journal-no-entries {
    padding: 20px;
    text-align: center;
    color: #94a3b8;
    font-size: .85rem;
  }

  .ojt-ring-chart-container {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
  }

  .ojt-ring-chart {
    position: relative;
    width: 200px;
    height: 200px;
  }

  .ojt-ring-chart canvas {
    width: 200px;
    height: 200px;
  }

  .ojt-ring-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
  }

  .ojt-ring-percentage {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
  }

  .ojt-ring-label {
    font-size: .7rem;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: 2px;
  }

  .ojt-progress-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
  }

  .ojt-progress-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
  }

  .ojt-progress-stat-label {
    font-size: .78rem;
    color: #64748b;
    font-weight: 500;
  }

  .ojt-progress-stat-value {
    font-size: .85rem;
    color: #0f172a;
    font-weight: 700;
  }

  .ojt-progress-bar-section {
    width: 100%;
  }

  .ojt-progress-bar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .ojt-progress-bar-title {
    font-size: .82rem;
    color: #475569;
    font-weight: 600;
  }

  .ojt-progress-bar-percentage {
    font-size: .85rem;
    color: #12b3ac;
    font-weight: 700;
  }

  .ojt-progress-bar-track {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
  }

  .ojt-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #12b3ac, #0d9488);
    border-radius: 999px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .ojt-progress-dates {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: .7rem;
    color: #94a3b8;
  }

  .ojt-calendar-journal-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
  }

  .panel-card-header h3 {
    font-size: 1.1rem;
    font-weight: 800;
    background: linear-gradient(135deg, #0f172a 0%, #12b3ac 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.02em;
  }

  .ojt-journal-panel {
    min-height: 300px;
  }

  .ojt-journal-date {
    font-size: .85rem;
    color: #64748b;
    font-weight: 500;
  }

  .ojt-journal-content {
    padding: 8px 0;
  }

  .ojt-journal-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
  }

  .ojt-journal-empty i {
    font-size: 2rem;
    color: #cbd5e1;
    margin-bottom: 12px;
  }

  .ojt-journal-empty p {
    color: #94a3b8;
    font-size: .85rem;
    margin: 0;
  }

  .ojt-journal-entries {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .ojt-journal-entry {
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.2s ease;
  }

  .ojt-journal-entry:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .ojt-journal-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .ojt-journal-time {
    font-size: .75rem;
    color: #64748b;
    font-weight: 500;
  }

  .ojt-journal-mood {
    font-size: .7rem;
    padding: 3px 8px;
    border-radius: 999px;
    font-weight: 600;
  }

  .ojt-journal-mood.Productive {
    background: #dcfce7;
    color: #166534;
  }

  .ojt-journal-mood.Neutral {
    background: #f1f5f9;
    color: #475569;
  }

  .ojt-journal-mood.Challenging {
    background: #fee2e2;
    color: #991b1b;
  }

  .ojt-journal-accomplishment {
    font-size: .85rem;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 8px;
  }

  .ojt-journal-entry-footer {
    display: flex;
    gap: 12px;
    font-size: .75rem;
    color: #94a3b8;
  }

  .ojt-journal-entry-footer i {
    color: #12b3ac;
    margin-right: 4px;
  }

  .ojt-journal-no-entries {
    padding: 20px;
    text-align: center;
    color: #94a3b8;
    font-size: .85rem;
  }

  @media (max-width: 700px) {
    .ojt-calendar-cell {
      min-height: 64px;
      padding: 8px 6px;
      border-radius: 10px;
    }

    .ojt-calendar-month-label {
      min-width: 140px;
      font-size: .95rem;
    }

    .ojt-calendar-weekday {
      font-size: .65rem;
    }

    .ojt-time-row {
      flex-direction: column;
      gap: 8px;
    }

    .ojt-time-sep {
      margin-top:0;
      transform: rotate(90deg);
    }

    .ojt-break-row {
      flex-direction: column;
      align-items: flex-start;
      gap: 6px;
    }

    .ojt-break-select {
      width: 100%;
    }

    .ojt-calendar-progress-layout {
      grid-template-columns: 1fr;
      gap: 16px;
    }

    .ojt-ring-chart {
      width: 160px;
      height: 160px;
    }

    .ojt-ring-chart canvas {
      width: 160px;
      height: 160px;
    }

    .ojt-ring-percentage {
      font-size: 1.5rem;
    }

    .ojt-calendar-journal-layout {
      grid-template-columns: 1fr;
      gap: 16px;
    }
  }
</style>

<!-- Calendar and Progress Layout -->
<div class="ojt-calendar-progress-layout">
  <!-- Left: Calendar and Journal Side by Side -->
  <div class="ojt-calendar-journal-layout">
    <!-- Calendar Panel (Left) -->
    <div class="panel-card ojt-calendar-panel" style="min-width:0">
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

    <!-- Journal Panel (Right, beside calendar) -->
    <div class="panel-card ojt-journal-panel" style="min-width:0">
      <div class="panel-card-header">
        <h3>Daily Journal</h3>
        <span class="ojt-journal-date" id="ojtJournalDate" style="font-size:.85rem;color:#64748b;font-weight:500">Select a date</span>
      </div>
      <div class="ojt-journal-content" id="ojtJournalContent">
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center;min-height:200px">
          <i class="fas fa-book-open" style="font-size:3rem;color:#cbd5e1;margin-bottom:16px;display:block"></i>
          <div style="font-size:.9rem;font-weight:600;color:#64748b;margin-bottom:4px">No journal yet</div>
          <div style="font-size:.8rem;color:#94a3b8">Select a date from the calendar to view journal entries.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Progress Tracking with Ring Chart -->
  <div class="panel-card">
    <div class="panel-card-header">
      <h3>Progress Tracking</h3>
    </div>

    <div class="ojt-ring-chart-container">
      <div class="ojt-ring-chart">
        <canvas id="ojtRingChart" width="200" height="200"></canvas>
        <div class="ojt-ring-center">
          <div class="ojt-ring-percentage" id="ojtRingPercentage"><?php echo $progress; ?>%</div>
          <div class="ojt-ring-label">Complete</div>
        </div>
      </div>

      <div class="ojt-progress-stats">
        <div class="ojt-progress-stat">
          <span class="ojt-progress-stat-label">Hours Logged</span>
          <span class="ojt-progress-stat-value"><span id="ojtHoursLogged"><?php echo (float)$hoursLogged; ?></span> / <span id="ojtHoursTarget"><?php echo (float)$hoursTarget; ?></span></span>
        </div>
        <div class="ojt-progress-stat">
          <span class="ojt-progress-stat-label">Days Present</span>
          <span class="ojt-progress-stat-value" id="ojtDaysPresent"><?php echo (int)$daysPresent; ?></span>
        </div>
        <div class="ojt-progress-stat">
          <span class="ojt-progress-stat-label">Tasks Completed</span>
          <span class="ojt-progress-stat-value" id="ojtTasksCompleted"><?php echo (int)$tasksCompleted; ?></span>
        </div>
      </div>

      <div class="ojt-progress-bar-section">
        <div class="ojt-progress-bar-header">
          <span class="ojt-progress-bar-title">Overall Progress</span>
          <span class="ojt-progress-bar-percentage" id="ojtProgressPct"><?php echo $progress; ?>%</span>
        </div>
        <div class="ojt-progress-bar-track">
          <div class="ojt-progress-bar-fill" id="ojtProgressFill" style="width:<?php echo $progress; ?>%"></div>
        </div>
        <div class="ojt-progress-dates">
          <span>Started: Nov 4, 2024</span>
          <span>Target: Mar 28, 2025</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  var ojtCalendarEntries = <?php echo $calendarEntriesJson; ?>;
  var ojtCalendarState = {
    year: (new Date()).getFullYear(),
    month: (new Date()).getMonth(),
    selectedDate: null
  };

  function openOjtModal(source) {
    var modal = document.getElementById('logModal');
    if (!modal) return;

    var today = new Date();
    var todayKey = formatOjtCalendarDateKey(today.getFullYear(), today.getMonth(), today.getDate());
    var dateInput = modal.querySelector('.ojt-log-form input[name="log_date"]');
    var dateField = modal.querySelector('.ojt-field-date');
    var warningEl = document.getElementById('ojtDateWarning');
    var warningTextEl = document.getElementById('ojtDateWarningText');

    if (dateField) {
      dateField.style.display = (source === 'calendar') ? 'none' : '';
    }

    // Always enforce max = today
    if (dateInput) {
      dateInput.max = todayKey;
    }

    // If opening from calendar, set the selected date and validate it
    if (source === 'calendar' && ojtCalendarState.selectedDate) {
      var selectedDate = ojtCalendarState.selectedDate;

      // Block future dates — should not be reachable via calendar, but guard anyway
      if (selectedDate > todayKey) {
        alert('You cannot log entries for future dates.');
        return;
      }

      if (dateInput) {
        dateInput.value = selectedDate;
      }

      // Show past-date warning
      if (selectedDate < todayKey) {
        if (warningEl && warningTextEl) {
          warningTextEl.textContent = 'You are logging an entry for a past date (' + formatDateDisplay(selectedDate) + '). Please make sure the information is accurate.';
          warningEl.style.display = '';
        }
      } else {
        if (warningEl) warningEl.style.display = 'none';
      }
    } else if (dateInput) {
      // Manual date input — check on change
      if (warningEl) warningEl.style.display = 'none';
      dateInput.addEventListener('change', function() {
        var val = this.value;
        if (!val) return;
        if (val > todayKey) {
          this.value = todayKey;
          val = todayKey;
        }
        if (val < todayKey) {
          if (warningEl && warningTextEl) {
            warningTextEl.textContent = 'You are logging an entry for a past date (' + formatDateDisplay(val) + '). Please make sure the information is accurate.';
            warningEl.style.display = '';
          }
        } else {
          if (warningEl) warningEl.style.display = 'none';
        }
      }, { once: true });
    }

    modal.classList.add('open');
    modal.removeAttribute('inert');
    modal.removeAttribute('hidden');
    // Move focus to the close button for accessibility
    var closeBtn = modal.querySelector('.modal-close');
    if (closeBtn) { closeBtn.focus(); }
  }

  function formatDateDisplay(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  }

  function closeOjtModal() {
    var modal = document.getElementById('logModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('inert', '');
    modal.setAttribute('hidden', '');
    // Return focus to the trigger button
    var trigger = document.querySelector('[onclick*="openOjtModal"]');
    if (trigger) { trigger.focus(); }
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

  (function bindOjtHoursComputed() {
  var startInput  = document.getElementById('ojtStartTime');
  var endInput    = document.getElementById('ojtEndTime');
  var breakSelect = document.getElementById('ojtBreakMinutes');
  var display     = document.getElementById('ojtHoursComputed');
  var valEl       = document.getElementById('ojtHoursComputedVal');
  var hiddenInput = document.getElementById('ojtHoursRenderedHidden');

  // Pull live stats from the DOM (already rendered by PHP)
  function getHoursLogged()  { return parseFloat(document.getElementById('ojtHoursLogged')?.textContent  || 0); }
  function getHoursTarget()  { return parseFloat(document.getElementById('ojtHoursTarget')?.textContent  || 0); }

  // Per-day hours already logged — read from calendar entries for selected date
  function getDayHoursLogged() {
    var selected = (typeof ojtCalendarState !== 'undefined' && ojtCalendarState.selectedDate)
      ? ojtCalendarState.selectedDate
      : (document.querySelector('.ojt-log-form input[name="log_date"]')?.value || '');
    if (!selected || typeof ojtCalendarEntries === 'undefined') return 0;
    return parseFloat((ojtCalendarEntries[selected] || {}).hours || 0);
  }

  var warningEl = document.getElementById('ojtDateWarning');
  var warningTextEl = document.getElementById('ojtDateWarningText');

  function showWarning(msg) {
    if (!warningEl || !warningTextEl) return;
    warningTextEl.textContent = msg;
    warningEl.style.display = '';
  }

  function clearWarning() {
    if (!warningEl) return;
    warningEl.style.display = 'none';
  }

  function computeHours() {
    var start = startInput.value;
    var end   = endInput.value;

    clearWarning();
    display.classList.remove('is-error', 'is-warning');

    if (!start || !end) {
      valEl.textContent = '—';
      if (hiddenInput) hiddenInput.value = '';
      return;
    }

    var startParts = start.split(':').map(Number);
    var endParts   = end.split(':').map(Number);
    var startMins  = startParts[0] * 60 + startParts[1];
    var endMins    = endParts[0]   * 60 + endParts[1];
    var breakMins  = parseInt(breakSelect.value, 10) || 0;
    var totalMins  = endMins - startMins - breakMins;

    if (totalMins <= 0) {
      valEl.textContent = 'Invalid range';
      display.classList.add('is-error');
      if (hiddenInput) hiddenInput.value = '';
      return;
    }

    var hours        = totalMins / 60;
    var hoursLogged  = getHoursLogged();
    var hoursTarget  = getHoursTarget();
    var dayLogged    = getDayHoursLogged();
    var warnings     = [];

    // --- Check 1: total target hours ---
    var remaining = hoursTarget - hoursLogged;
    if (hoursTarget > 0 && hoursLogged >= hoursTarget) {
      warnings.push('You have already reached your target of ' + hoursTarget + ' hrs. This entry will still be saved.');
    } else if (hoursTarget > 0 && (hoursLogged + hours) > hoursTarget) {
      var over = ((hoursLogged + hours) - hoursTarget).toFixed(2).replace(/\.00$/, '');
      warnings.push('This entry will exceed your target by ' + over + ' hr(s). Remaining: ' + remaining.toFixed(2).replace(/\.00$/, '') + ' hr(s).');
    }

    // --- Check 2: 24-hour daily cap ---
    var dayTotal = dayLogged + hours;
    if (dayTotal > 24) {
      var dayOver = (dayTotal - 24).toFixed(2).replace(/\.00$/, '');
      warnings.push('Adding this entry will exceed 24 hrs for this date by ' + dayOver + ' hr(s). Already logged today: ' + dayLogged.toFixed(2).replace(/\.00$/, '') + ' hr(s).');
    }

    if (warnings.length > 0) {
      showWarning(warnings.join(' '));
      display.classList.add('is-warning');
    }

    valEl.textContent = hours.toFixed(2).replace(/\.00$/, '') + ' hr' + (hours !== 1 ? 's' : '');
    if (hiddenInput) hiddenInput.value = hours.toFixed(4);
  }

  // Re-run when the date changes (calendar selection updates day hours)
  document.addEventListener('ojtDateSelected', computeHours);

  startInput.addEventListener('change',  computeHours);
  startInput.addEventListener('input',   computeHours);
  endInput.addEventListener('change',    computeHours);
  endInput.addEventListener('input',     computeHours);
  breakSelect.addEventListener('change', computeHours);
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

    var today = new Date();
    var todayKey = formatOjtCalendarDateKey(today.getFullYear(), today.getMonth(), today.getDate());

    // Block future dates from being selected
    if (dateKey > todayKey) {
      return;
    }

    ojtCalendarState.selectedDate = dateKey;
    ojtCalendarState.year = parsed.year;
    ojtCalendarState.month = parsed.month;

    var dateInput = document.querySelector('.ojt-log-form input[name="log_date"]');
    if (dateInput) {
      dateInput.value = dateKey;
    }

    renderOjtCalendar();
    updateOjtCalendarSelectedInfo();
    document.dispatchEvent(new Event('ojtDateSelected'));
  }

  function updateOjtCalendarSelectedInfo() {
    var metaEl = document.getElementById('ojtCalendarSelectedMeta');
    var dateEl = document.getElementById('ojtJournalDate');
    var journalEl = document.getElementById('ojtJournalContent');
    if (!metaEl) return;

    var selected = ojtCalendarState.selectedDate;
    if (!selected) {
      metaEl.innerHTML = 'Select a date to prepare your log entry.';
      if (dateEl) dateEl.textContent = 'Select a date';
      if (journalEl) journalEl.innerHTML = '<div class="ojt-journal-empty"><i class="fas fa-book-open" style="font-size:2rem;color:#cbd5e1;margin-bottom:12px"></i><p style="color:#94a3b8;font-size:.85rem">Select a date from the calendar to view journal entries.</p></div>';
      return;
    }

    var parsed = parseOjtCalendarDateKey(selected);
    if (!parsed) {
      metaEl.innerHTML = 'Select a date to prepare your log entry.';
      if (dateEl) dateEl.textContent = 'Invalid date';
      return;
    }

    var dateObj = new Date(parsed.year, parsed.month, parsed.day);
    var label = dateObj.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    });

    if (dateEl) dateEl.textContent = label;

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

    // Load journal entries via AJAX
    if (journalEl) {
      journalEl.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#94a3b8"><i class="fas fa-book-open" style="font-size:3rem;color:#cbd5e1;margin-bottom:16px;display:block"></i><div style="font-size:.9rem;font-weight:600;color:#64748b;margin-bottom:4px">No journal yet</div><div style="font-size:.8rem">Select a date with logged entries to view journal.</div></div>';

      fetch('<?php echo $ojtAjaxUrl; ?>?action=get_entries&date=' + encodeURIComponent(selected))
        .then(function(response) {
          return response.text().then(function(txt) {
            try {
              var data = JSON.parse(txt);
              return { ok: response.ok, data: data };
            } catch(e) {
              throw new Error('Server returned non-JSON (HTTP ' + response.status + '): ' + txt.substring(0, 300));
            }
          });
        })
        .then(function(result) {
          var data = result.data;
          if (data.ok && data.entries && data.entries.length > 0) {
            renderJournalEntries(journalEl, data.entries);
          } else {
            var dateLabel2 = label || 'this date';
            journalEl.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center;min-height:160px">'
              + '<i class="fas fa-book-open" style="font-size:2.2rem;color:#cbd5e1;margin-bottom:14px;display:block"></i>'
              + '<div style="font-size:.9rem;font-weight:600;color:#64748b;margin-bottom:6px">No journal log for this day</div>'
              + '<div style="font-size:.8rem;color:#94a3b8;margin-bottom:14px">There are no entries recorded for <strong style="color:#475569">' + escapeHtml(dateLabel2) + '</strong>.</div>'
              + '<button type="button" onclick="openOjtModal(\'calendar\')" style="border:none;border-radius:10px;padding:9px 18px;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;font-size:.8rem;font-weight:600;cursor:pointer;">+ Add a log entry</button>'
              + '</div>';
          }
        })
        .catch(function(error) {
          journalEl.innerHTML = '<div style="text-align:center;padding:32px 16px;color:#94a3b8">'
            + '<i class="fas fa-exclamation-circle" style="font-size:2rem;color:#fca5a5;margin-bottom:12px;display:block"></i>'
            + '<div style="font-size:.9rem;font-weight:600;color:#64748b;margin-bottom:6px">Failed to load entries</div>'
            + '<div style="font-size:.72rem;color:#94a3b8;word-break:break-all;max-height:120px;overflow:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px;text-align:left;margin-top:6px">'
            + escapeHtml(error.message || 'Unknown error') + '</div>'
            + '</div>';
        });
    }
  }

  function renderJournalEntries(container, entries) {
    var html = '<div class="ojt-journal-entries">';
    entries.forEach(function(entry) {
      var moodClass = (entry.mood_tag || 'Neutral').replace(/\s+/g, '');
      var timeStr = (entry.start_time || '') + ' - ' + (entry.end_time || '');
      html += '<div class="ojt-journal-entry">';
      html += '<div class="ojt-journal-entry-header">';
      html += '<span class="ojt-journal-time"><i class="fas fa-clock"></i> ' + timeStr + ' (' + parseFloat(entry.hours_rendered || 0).toFixed(2) + ' hrs)</span>';
      html += '<span class="ojt-journal-mood ' + moodClass + '">' + (entry.mood_tag || 'Neutral') + '</span>';
      html += '</div>';
      html += '<div class="ojt-journal-accomplishment">' + escapeHtml(entry.accomplishment || '') + '</div>';
      html += '<div class="ojt-journal-entry-footer">';
      if (entry.file_path) {
        html += '<span><i class="fas fa-paperclip"></i> File attached</span>';
      }
      html += '</div>';
      html += '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
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

    // Animate month label change
    monthLabel.style.opacity = '0';
    monthLabel.style.transform = 'translateY(-5px)';
    setTimeout(function() {
      monthLabel.textContent = new Date(year, month, 1).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric'
      });
      monthLabel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      monthLabel.style.opacity = '1';
      monthLabel.style.transform = 'translateY(0)';
    }, 150);

    grid.innerHTML = '';

    for (var i = 0; i < firstDayWeekIndex; i++) {
      var prevDay = daysInPrevMonth - firstDayWeekIndex + i + 1;
      var prevCell = document.createElement('div');
      prevCell.className = 'ojt-calendar-cell is-other-month';
      prevCell.innerHTML = '<div class="ojt-calendar-day-number">' + prevDay + '</div>';
      prevCell.style.opacity = '0';
      prevCell.style.transform = 'scale(0.95)';
      grid.appendChild(prevCell);
      animateCell(prevCell, i * 15);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var dateKey = formatOjtCalendarDateKey(year, month, day);
      var dayEntry = ojtCalendarEntries[dateKey] || null;
      var hasEntry = !!dayEntry;
      var isFuture = dateKey > todayKey;

      var cell = document.createElement('div');
      var classNames = ['ojt-calendar-cell'];
      if (hasEntry) classNames.push('has-entry');
      if (dateKey === todayKey) classNames.push('is-today');
      if (dateKey === ojtCalendarState.selectedDate) classNames.push('is-selected');
      if (isFuture) classNames.push('is-future');
      cell.className = classNames.join(' ');

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ojt-calendar-cell-btn';
      btn.setAttribute('data-date', dateKey);
      btn.innerHTML = '<div class="ojt-calendar-day-number">' + day + '</div>';
      if (!isFuture) {
        btn.addEventListener('click', function(event) {
          var targetDate = event.currentTarget.getAttribute('data-date');
          if (targetDate) {
            ojtCalendarSelectDate(targetDate);
          }
        });
      } else {
        btn.disabled = true;
        btn.style.cursor = 'not-allowed';
      }

      cell.appendChild(btn);

      cell.style.opacity = '0';
      cell.style.transform = 'scale(0.95) translateY(5px)';
      grid.appendChild(cell);
      animateCell(cell, (firstDayWeekIndex + day - 1) * 15);
    }

    var totalCells = firstDayWeekIndex + daysInMonth;
    var nextCellCount = (7 - (totalCells % 7)) % 7;

    for (var n = 1; n <= nextCellCount; n++) {
      var nextCell = document.createElement('div');
      nextCell.className = 'ojt-calendar-cell is-other-month';
      nextCell.innerHTML = '<div class="ojt-calendar-day-number">' + n + '</div>';
      nextCell.style.opacity = '0';
      nextCell.style.transform = 'scale(0.95)';
      grid.appendChild(nextCell);
      animateCell(nextCell, (firstDayWeekIndex + daysInMonth + n - 1) * 15);
    }
  }

  function animateCell(cell, delay) {
    setTimeout(function() {
      cell.style.transition = 'opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
      cell.style.opacity = '1';
      cell.style.transform = 'scale(1) translateY(0)';
    }, delay);
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
        openOjtModal('calendar');
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
    var daysPresent = document.getElementById('ojtDaysPresent');
    var tasksCompleted = document.getElementById('ojtTasksCompleted');
    var progressPct = document.getElementById('ojtProgressPct');
    var progressFill = document.getElementById('ojtProgressFill');

    if (hoursLogged) {
      hoursLogged.textContent = Number(stats.hours_logged || 0).toFixed(2).replace(/\.00$/, '');
      var hoursRow = hoursLogged.closest('.stat-card-num-row');
      if (hoursRow) {
        var trendEl = hoursRow.querySelector('.stat-card-trend');
        if (trendEl) {
          trendEl.textContent = 'of ' + Number(stats.hours_target || 0).toFixed(2).replace(/\.00$/, '') + ' target';
        }
      }
    }

    if (daysPresent) {
      daysPresent.textContent = String(stats.days_present || 0);
      var daysRow = daysPresent.closest('.stat-card-num-row');
      if (daysRow) {
        var trendEl = daysRow.querySelector('.stat-card-trend');
        if (trendEl) {
          trendEl.textContent = String(stats.days_present || 0) + ' days';
        }
      }
    }

    if (tasksCompleted) {
      tasksCompleted.textContent = String(stats.tasks_completed || 0);
      var tasksRow = tasksCompleted.closest('.stat-card-num-row');
      if (tasksRow) {
        var trendEl = tasksRow.querySelector('.stat-card-trend');
        if (trendEl) {
          trendEl.textContent = String(stats.tasks_completed || 0) + ' completed';
        }
      }
    }

    if (progressPct) {
      progressPct.textContent = String(stats.progress || 0) + '%';
      var progressRow = progressPct.closest('.stat-card-num-row');
      if (progressRow) {
        var trendEl = progressRow.querySelector('.stat-card-trend');
        if (trendEl) {
          trendEl.textContent = String(stats.progress || 0) + '% complete';
        }
      }
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

  // Ring Chart Drawing
  function drawOjtRingChart(progress) {
    var canvas = document.getElementById('ojtRingChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var displayWidth = 200;
    var displayHeight = 200;

    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;
    canvas.style.width = displayWidth + 'px';
    canvas.style.height = displayHeight + 'px';
    ctx.scale(dpr, dpr);

    var centerX = displayWidth / 2;
    var centerY = displayHeight / 2;
    var radius = 80;
    var lineWidth = 12;
    var startAngle = -Math.PI / 2;
    var endAngle = startAngle + (Math.PI * 2 * Math.min(progress / 100, 1));

    ctx.clearRect(0, 0, displayWidth, displayHeight);

    // Background ring
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = lineWidth;
    ctx.lineCap = 'round';
    ctx.stroke();

    // Progress ring
    if (progress > 0) {
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius, startAngle, endAngle);
      ctx.strokeStyle = '#12b3ac';
      ctx.lineWidth = lineWidth;
      ctx.lineCap = 'round';
      ctx.stroke();
    }
  }

  // Initialize ring chart on load
  drawOjtRingChart(<?php echo $progress; ?>);

  // Update ring chart in updateOjtStats
  var originalUpdateOjtStats = updateOjtStats;
  updateOjtStats = function(stats) {
    originalUpdateOjtStats(stats);

    var progress = Number(stats.progress || 0);
    drawOjtRingChart(progress);

    var ringPct = document.getElementById('ojtRingPercentage');
    if (ringPct) {
      ringPct.textContent = progress + '%';
    }
  };
</script>