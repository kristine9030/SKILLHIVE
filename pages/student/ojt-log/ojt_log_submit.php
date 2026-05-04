<?php

function ojt_log_handle_submit(PDO $pdo, ?array $ojt): array
{
  $errorMsg = '';
  $successMsg = '';
  $defaultRequiredHours = defined('SKILLHIVE_REQUIRED_OJT_HOURS') ? (float) SKILLHIVE_REQUIRED_OJT_HOURS : 500.00;

  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['log_entry'])) {
    return ['errorMsg' => $errorMsg, 'successMsg' => $successMsg];
  }

  $isAjax = ojt_is_ajax_request();
  $date = $_POST['log_date'] ?? date('Y-m-d');
  $accomplishment = trim($_POST['accomplishment'] ?? '');
  $hours = (float) ($_POST['hours_rendered'] ?? 0);
  $mood = $_POST['mood_tag'] ?? 'Neutral';
  $startTime = trim($_POST['start_time'] ?? '') ?: null;
  $endTime   = trim($_POST['end_time'] ?? '') ?: null;
  $fileName = null;

  if (!$ojt) {
    if ($isAjax) {
      http_response_code(422);
      header('Content-Type: application/json');
      echo json_encode([
        'ok' => false,
        'message' => 'No OJT record found for this student.',
      ]);
      exit;
    }
    return ['errorMsg' => 'No OJT record found for this student.', 'successMsg' => ''];
  }

  if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../../assets/backend/uploads/ojt_logs/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }
    $fileName = uniqid('ojt_') . '_' . basename((string) $_FILES['task_file']['name']);
    move_uploaded_file((string) $_FILES['task_file']['tmp_name'], $uploadDir . $fileName);
  }

  if (!($accomplishment !== '' && $hours > 0)) {
    if ($isAjax) {
      http_response_code(422);
      header('Content-Type: application/json');
      echo json_encode([
        'ok' => false,
        'message' => 'Please fill in all required fields.',
      ]);
      exit;
    }
    return ['errorMsg' => 'Please fill in all required fields.', 'successMsg' => ''];
  }

  // Fetch existing log for this date so we can correctly adjust hours_completed on edit
  $stmtExisting = $pdo->prepare(
    'SELECT log_id, hours_rendered FROM daily_log WHERE record_id = ? AND log_date = ? LIMIT 1'
  );
  $stmtExisting->execute([(int) $ojt['record_id'], $date]);
  $existingLog   = $stmtExisting->fetch(PDO::FETCH_ASSOC);
  $previousHours = $existingLog ? (float) $existingLog['hours_rendered'] : 0.0;
  $isEdit        = (bool) $existingLog;

  try {
    // INSERT for new dates; UPDATE in place if unique key (record_id, log_date) already exists.
    // ON DUPLICATE KEY UPDATE handles both "add new log" and "edit past log" in one statement,
    // eliminating the 500 caused by the unique constraint violation on past dates.
    $stmt = $pdo->prepare(
      'INSERT INTO daily_log
         (record_id, log_date, start_time, end_time, accomplishment, hours_rendered, mood_tag, task_file, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
       ON DUPLICATE KEY UPDATE
         start_time     = VALUES(start_time),
         end_time       = VALUES(end_time),
         accomplishment = VALUES(accomplishment),
         hours_rendered = VALUES(hours_rendered),
         mood_tag       = VALUES(mood_tag),
         task_file      = IF(VALUES(task_file) IS NOT NULL, VALUES(task_file), task_file)'
    );
    $stmt->execute([
      (int) $ojt['record_id'],
      $date,
      $startTime,
      $endTime,
      $accomplishment,
      $hours,
      $mood,
      $fileName,
    ]);
  } catch (PDOException $e) {
    if ($isAjax) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
      exit;
    }
    return ['errorMsg' => 'Database error: ' . $e->getMessage(), 'successMsg' => ''];
  }

  // On edit, subtract old hours first so hours_completed stays accurate.
  // On new entry, $previousHours is 0.0 so $hoursDelta == $hours (no behaviour change).
  $hoursDelta = $hours - $previousHours;

  $stmt = $pdo->prepare(
    'UPDATE ojt_record
     SET hours_completed = LEAST(hours_required, GREATEST(0, hours_completed + ?)),
         completion_status = CASE
           WHEN LOWER(TRIM(COALESCE(completion_status, ""))) = "completed" THEN "Completed"
           WHEN (hours_completed + ?) >= hours_required THEN "Completed"
           ELSE completion_status
         END,
         updated_at = NOW()
     WHERE record_id = ?'
  );
  $stmt->execute([$hoursDelta, $hoursDelta, (int) $ojt['record_id']]);

  $stmt = $pdo->prepare('SELECT hours_completed, hours_required FROM ojt_record WHERE record_id = ? LIMIT 1');
  $stmt->execute([(int) $ojt['record_id']]);
  $updatedOjt = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['hours_completed' => 0, 'hours_required' => $defaultRequiredHours];

  $stmt = $pdo->prepare('SELECT COUNT(DISTINCT log_date) AS days, COUNT(*) AS tasks FROM daily_log WHERE record_id = ?');
  $stmt->execute([(int) $ojt['record_id']]);
  $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['days' => 0, 'tasks' => 0];

  $hoursCompleted = (float) ($updatedOjt['hours_completed'] ?? 0);
  $hoursRequired  = (float) ($updatedOjt['hours_required'] ?? $defaultRequiredHours);
  $progressPct    = $hoursRequired > 0 ? (int) round(($hoursCompleted / $hoursRequired) * 100) : 0;

  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
      'ok'      => true,
      'message' => $isEdit ? 'Log entry updated successfully.' : 'Log entry added successfully.',
      'entry'   => [
        'date_iso'       => $date,
        'date_display'   => date('M d, Y', strtotime($date)),
        'accomplishment' => $accomplishment,
        'hours'          => $hours,
        'mood'           => $mood,
        'file_url'       => $fileName ? ('/Skillhive/assets/backend/uploads/ojt_logs/' . rawurlencode($fileName)) : '',
      ],
      'stats' => [
        'hours_logged'    => $hoursCompleted,
        'hours_target'    => $hoursRequired,
        'days_present'    => (int) ($counts['days'] ?? 0),
        'tasks_completed' => (int) ($counts['tasks'] ?? 0),
        'progress'        => $progressPct,
      ],
    ]);
    exit;
  }

  header('Location: ' . $_SERVER['REQUEST_URI']);
  exit;
}