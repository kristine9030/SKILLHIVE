<?php

function ojt_is_ajax_request(): bool
{
  return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function ojt_get_or_create_record(PDO $pdo, int $studentId): ?array
{
  $stmt = $pdo->prepare('SELECT * FROM ojt_record WHERE student_id = ? ORDER BY record_id DESC LIMIT 1');
  $stmt->execute([$studentId]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($existing) {
    return $existing;
  }

  // Fallback: auto-provision from the student's most recent accepted application.
  $stmt = $pdo->prepare(
    'SELECT a.student_id, a.internship_id, i.duration_weeks, a.application_date
     FROM application a
     INNER JOIN internship i ON i.internship_id = a.internship_id
     WHERE a.student_id = ? AND a.status = ?
     ORDER BY a.updated_at DESC, a.application_id DESC
     LIMIT 1'
  );
  $stmt->execute([$studentId, 'Accepted']);
  $accepted = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$accepted) {
    return null;
  }

  $internshipId = (int) ($accepted['internship_id'] ?? 0);
  $durationWeeks = max(1, (int) ($accepted['duration_weeks'] ?? 12));
  $startDate = date('Y-m-d');
  $endDate = date('Y-m-d', strtotime($startDate . ' +' . $durationWeeks . ' weeks'));

  $stmt = $pdo->prepare(
    'INSERT INTO ojt_record (
        student_id, internship_id, hours_required, hours_completed,
        start_date, end_date, completion_status, created_at, updated_at
      )
      SELECT ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
      WHERE NOT EXISTS (
        SELECT 1 FROM ojt_record WHERE student_id = ? AND internship_id = ?
      )'
  );
  $stmt->execute([
    $studentId,
    $internshipId,
    400.00,
    0.00,
    $startDate,
    $endDate,
    'Ongoing',
    $studentId,
    $internshipId,
  ]);

  $stmt = $pdo->prepare('SELECT * FROM ojt_record WHERE student_id = ? ORDER BY record_id DESC LIMIT 1');
  $stmt->execute([$studentId]);
  $created = $stmt->fetch(PDO::FETCH_ASSOC);

  return $created ?: null;
}
