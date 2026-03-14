<?php

function ojt_load_summary(PDO $pdo, ?array $ojt): array
{
  $hoursLogged = (float) ($ojt['hours_completed'] ?? 0);
  $hoursTarget = (float) ($ojt['hours_required'] ?? 400);
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
