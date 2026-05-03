<?php

function ojt_load_summary($pdo, $ojt) {
  $defaultRequiredHours = defined('SKILLHIVE_REQUIRED_OJT_HOURS') ? (float) SKILLHIVE_REQUIRED_OJT_HOURS : 500.00;
  $hoursLogged = (float) ($ojt['hours_completed'] ?? 0);
  $hoursTarget = (float) ($ojt['hours_required'] ?? $defaultRequiredHours);
  $progress = $hoursTarget > 0 ? (int) round(($hoursLogged / $hoursTarget) * 100) : 0;
  $daysPresent = 0;
  $tasksCompleted = 0;

  if ($ojt) {
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT log_date) AS days, COUNT(*) AS tasks FROM daily_log WHERE record_id = ?');
    $stmt->execute([$ojt['record_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['days' => 0, 'tasks' => 0];
    $daysPresent = (int) ($row['days'] ?? 0);
    $tasksCompleted = (int) ($row['tasks'] ?? 0);
  }

  return [
    'hoursLogged' => $hoursLogged,
    'hoursTarget' => $hoursTarget,
    'progress' => $progress,
    'daysPresent' => $daysPresent,
    'tasksCompleted' => $tasksCompleted,
  ];
}

function ojt_load_entries_by_date($pdo, $ojt, $date) {
  if (!$ojt) {
    return [];
  }

  $stmt = $pdo->prepare('SELECT log_id, log_date, start_time, end_time, hours_rendered, accomplishment, mood_tag, file_path FROM daily_log WHERE record_id = ? AND log_date = ? ORDER BY start_time ASC');
  $stmt->execute([$ojt['record_id'], $date]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
