<?php

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

  $sql =
    'SELECT a.application_id, a.application_date, a.status, a.compatibility_score, a.updated_at, a.consented_at, a.consent_version,
        a.resume_link_snapshot, a.profile_link_snapshot,
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
