<?php

function applications_application_columns_map(PDO $pdo): array
{
  static $columns = null;

  if (is_array($columns)) {
    return $columns;
  }

  $columns = [];
  try {
    foreach ($pdo->query('SHOW COLUMNS FROM application') as $column) {
      $field = (string) ($column['Field'] ?? '');
      if ($field !== '') {
        $columns[$field] = true;
      }
    }
  } catch (Throwable $e) {
    $columns = [];
  }

  return $columns;
}

function applications_load_page_data(PDO $pdo, int $userId, string $statusFilter): array
{
  $statsSql = 'SELECT status, COUNT(*) AS total FROM application WHERE student_id = ? GROUP BY status';
  $stmt = $pdo->prepare($statsSql);
  $stmt->execute([$userId]);
  $statusCounts = [
      'Pending' => 0,
      'Shortlisted' => 0,
      'Waitlisted' => 0,
      'Interview Scheduled' => 0,
      'Accepted' => 0,
      'Rejected' => 0,
  ];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $statusCounts[(string) $row['status']] = (int) $row['total'];
  }

  $applicationColumns = applications_application_columns_map($pdo);

  $optionalSelects = [
      isset($applicationColumns['consented_at']) ? 'a.consented_at' : 'NULL AS consented_at',
      isset($applicationColumns['consent_version']) ? 'a.consent_version' : 'NULL AS consent_version',
      isset($applicationColumns['resume_link_snapshot']) ? 'a.resume_link_snapshot' : 'NULL AS resume_link_snapshot',
      isset($applicationColumns['profile_link_snapshot']) ? 'a.profile_link_snapshot' : 'NULL AS profile_link_snapshot',
  ];

  $sql =
    'SELECT a.application_id, a.application_date, a.status, a.compatibility_score, a.updated_at, ' . implode(', ', $optionalSelects) . ',
              i.internship_id, i.title,
              e.company_name, e.company_badge_status
       FROM application a
       INNER JOIN internship i ON i.internship_id = a.internship_id
       INNER JOIN employer e ON e.employer_id = i.employer_id
       WHERE a.student_id = ?';
  $params = [$userId];
  if ($statusFilter !== '') {
      $sql .= ' AND a.status = ?';
      $params[] = $statusFilter;
  }
  $sql .= ' ORDER BY a.application_date DESC, a.application_id DESC';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  return [
    'statusCounts' => $statusCounts,
    'applications' => $applications,
    'recentApplications' => array_slice($applications, 0, 5),
    'totalApplied' => array_sum($statusCounts),
  ];
}
