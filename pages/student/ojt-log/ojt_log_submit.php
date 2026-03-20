<?php

function ojt_log_handle_submit(PDO $pdo, ?array $ojt): array
{
  $errorMsg = '';
  $successMsg = '';

  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['log_entry'])) {
    return ['errorMsg' => $errorMsg, 'successMsg' => $successMsg];
  }

  $isAjax = ojt_is_ajax_request();
  $date = $_POST['log_date'] ?? date('Y-m-d');
  $accomplishment = trim($_POST['accomplishment'] ?? '');
  $hours = (float) ($_POST['hours_rendered'] ?? 0);
  $mood = $_POST['mood_tag'] ?? 'Neutral';
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

  $stmt = $pdo->prepare('INSERT INTO daily_log (record_id, log_date, accomplishment, hours_rendered, mood_tag, task_file, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
  $stmt->execute([(int) $ojt['record_id'], $date, $accomplishment, $hours, $mood, $fileName]);

  $stmt = $pdo->prepare(
    'UPDATE ojt_record
     SET hours_completed = LEAST(hours_required, hours_completed + ?),
         completion_status = CASE
           WHEN LOWER(TRIM(COALESCE(completion_status, ""))) = "completed" THEN "Completed"
           WHEN (hours_completed + ?) >= hours_required THEN "Completed"
           ELSE completion_status
         END,
         updated_at = NOW()
     WHERE record_id = ?'
  );
  $stmt->execute([$hours, $hours, (int) $ojt['record_id']]);

  $stmt = $pdo->prepare('SELECT hours_completed, hours_required FROM ojt_record WHERE record_id = ? LIMIT 1');
  $stmt->execute([(int) $ojt['record_id']]);
  $updatedOjt = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['hours_completed' => 0, 'hours_required' => 400];

  $stmt = $pdo->prepare('SELECT COUNT(DISTINCT log_date) AS days, COUNT(*) AS tasks FROM daily_log WHERE record_id = ?');
  $stmt->execute([(int) $ojt['record_id']]);
  $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['days' => 0, 'tasks' => 0];

  $hoursCompleted = (float) ($updatedOjt['hours_completed'] ?? 0);
  $hoursRequired = (float) ($updatedOjt['hours_required'] ?? 400);
  $progressPct = $hoursRequired > 0 ? (int) round(($hoursCompleted / $hoursRequired) * 100) : 0;

  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
      'ok' => true,
      'message' => 'Log entry added successfully.',
      'entry' => [
        'date_display' => date('M d, Y', strtotime($date)),
        'accomplishment' => $accomplishment,
        'hours' => $hours,
        'mood' => $mood,
        'file_url' => $fileName ? ('/Skillhive/assets/backend/uploads/ojt_logs/' . rawurlencode($fileName)) : '',
      ],
      'stats' => [
        'hours_logged' => $hoursCompleted,
        'hours_target' => $hoursRequired,
        'days_present' => (int) ($counts['days'] ?? 0),
        'tasks_completed' => (int) ($counts['tasks'] ?? 0),
        'progress' => $progressPct,
      ],
    ]);
    exit;
  }

  header('Location: ' . $_SERVER['REQUEST_URI']);
  exit;
}
