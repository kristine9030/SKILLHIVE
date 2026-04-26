<?php

function analytics_load_data(PDO $pdo, int $studentId): array
{
  $stmt = $pdo->prepare('SELECT internship_readiness_score, resume_file FROM student WHERE student_id = ? LIMIT 1');
  $stmt->execute([$studentId]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $readinessScore = round((float) ($student['internship_readiness_score'] ?? 0), 2);

  $stmt = $pdo->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN internship_readiness_score >= ? THEN 1 ELSE 0 END) AS rank_pos FROM student WHERE internship_readiness_score IS NOT NULL');
  $stmt->execute([$readinessScore]);
  $rankingData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'rank_pos' => 0];
  $classTotal = (int) ($rankingData['total'] ?? 0);
  $rankPos = (int) ($rankingData['rank_pos'] ?? 0);
  $topPercent = $classTotal > 0 ? max(1, (int) ceil(($rankPos / $classTotal) * 100)) : 0;

  $stmt = $pdo->prepare('SELECT sk.skill_name, ss.skill_level, ss.verified FROM student_skill ss INNER JOIN skill sk ON sk.skill_id = ss.skill_id WHERE ss.student_id = ? ORDER BY ss.verified DESC, sk.skill_name ASC');
  $stmt->execute([$studentId]);
  $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $skillScoreMap = ['Beginner' => 45, 'Intermediate' => 70, 'Advanced' => 90];
  $verifiedCount = 0;
  $skillsForBars = [];
  foreach ($skills as $row) {
    $level = (string) ($row['skill_level'] ?? 'Beginner');
    $verified = (int) ($row['verified'] ?? 0) === 1;
    if ($verified) {
      $verifiedCount++;
    }
    $base = $skillScoreMap[$level] ?? 45;
    $score = min(100, $base + ($verified ? 10 : 0));
    $delta = $verified ? '+10%' : ($level === 'Advanced' ? '+12%' : ($level === 'Intermediate' ? '+8%' : '+5%'));
    $skillsForBars[] = [
      'name' => (string) ($row['skill_name'] ?? 'Skill'),
      'score' => $score,
      'delta' => $delta,
      'verified' => $verified,
    ];
  }

  $stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM application WHERE student_id = ? GROUP BY status');
  $stmt->execute([$studentId]);
  $statusCounts = [
    'Applied' => 0,
    'Shortlisted' => 0,
    'Interview' => 0,
    'Accepted' => 0,
    'Rejected' => 0,
  ];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['total'] ?? 0);
    $statusCounts['Applied'] += $count;
    if ($status === 'Shortlisted') {
      $statusCounts['Shortlisted'] += $count;
    } elseif ($status === 'Interview Scheduled') {
      $statusCounts['Interview'] += $count;
    } elseif ($status === 'Accepted') {
      $statusCounts['Accepted'] += $count;
    } elseif ($status === 'Rejected') {
      $statusCounts['Rejected'] += $count;
    }
  }

  $totalApplied = max(1, $statusCounts['Applied']);

  $monday = date('Y-m-d', strtotime('monday this week'));
  $sunday = date('Y-m-d', strtotime($monday . ' +6 days'));

  $stmt = $pdo->prepare('SELECT COUNT(*) FROM application WHERE student_id = ? AND application_date BETWEEN ? AND ?');
  $stmt->execute([$studentId, $monday, $sunday]);
  $applicationsThisWeek = (int) $stmt->fetchColumn();

  $stmt = $pdo->prepare('SELECT COALESCE(SUM(d.hours_rendered), 0) AS hrs, COUNT(*) AS tasks
                         FROM daily_log d
                         INNER JOIN ojt_record r ON r.record_id = d.record_id
                         WHERE r.student_id = ? AND d.log_date BETWEEN ? AND ?');
  $stmt->execute([$studentId, $monday, $sunday]);
  $weekLog = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['hrs' => 0, 'tasks' => 0];
  $hoursThisWeek = (float) ($weekLog['hrs'] ?? 0);
  $tasksThisWeek = (int) ($weekLog['tasks'] ?? 0);

  $stmt = $pdo->prepare("SELECT COUNT(*)
                         FROM daily_log d
                         INNER JOIN ojt_record r ON r.record_id = d.record_id
                         WHERE r.student_id = ?
                           AND d.log_date BETWEEN ? AND ?
                           AND d.accomplishment REGEXP '(learn|improv|study|practice|optimi|debug|research)'");
  $stmt->execute([$studentId, $monday, $sunday]);
  $skillsImprovedThisWeek = (int) $stmt->fetchColumn();

  $stmt = $pdo->prepare('SELECT COALESCE(SUM(hours_completed), 0) FROM ojt_record WHERE student_id = ?');
  $stmt->execute([$studentId]);
  $totalOjtHours = (float) $stmt->fetchColumn();

  $stmt = $pdo->prepare('SELECT COALESCE(AVG(compatibility_score), 0) FROM application WHERE student_id = ?');
  $stmt->execute([$studentId]);
  $avgCompatibility = round((float) $stmt->fetchColumn(), 2);

  $achievements = [
    [
      'label' => 'First Application',
      'earned' => $statusCounts['Applied'] >= 1,
      'icon' => 'fas fa-medal',
      'color' => '#12b3ac',
    ],
    [
      'label' => 'Resume Pro',
      'earned' => !empty($student['resume_file']),
      'icon' => 'fas fa-medal',
      'color' => '#12b3ac',
    ],
    [
      'label' => '10 Applications',
      'earned' => $statusCounts['Applied'] >= 10,
      'icon' => 'fas fa-medal',
      'color' => '#12b3ac',
    ],
    [
      'label' => '100 OJT Hours',
      'earned' => $totalOjtHours >= 100,
      'icon' => 'fas fa-medal',
      'color' => '#12b3ac',
      'progress' => $totalOjtHours,
    ],
    [
      'label' => 'AI Match Master',
      'earned' => $avgCompatibility >= 75,
      'icon' => 'fas fa-medal',
      'color' => '#12b3ac',
    ],
  ];

  return [
    'student' => $student,
    'readinessScore' => $readinessScore,
    'classTotal' => $classTotal,
    'rankPos' => $rankPos,
    'topPercent' => $topPercent,
    'verifiedCount' => $verifiedCount,
    'skillsForBars' => $skillsForBars,
    'statusCounts' => $statusCounts,
    'totalApplied' => $totalApplied,
    'applicationsThisWeek' => $applicationsThisWeek,
    'hoursThisWeek' => $hoursThisWeek,
    'tasksThisWeek' => $tasksThisWeek,
    'skillsImprovedThisWeek' => $skillsImprovedThisWeek,
    'totalOjtHours' => $totalOjtHours,
    'avgCompatibility' => $avgCompatibility,
    'achievements' => $achievements,
  ];
}
