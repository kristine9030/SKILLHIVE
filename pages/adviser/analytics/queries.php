<?php
// queries.php — Analytics data collection for adviser dashboard

function adviser_analytics_get_stats($pdo, $adviser_id) {
    $stats = [
        'placement_rate' => 0,
        'avg_eval_rating' => 0,
        'avg_ojt_hours' => 0,
        'completion_rate' => 0,
        'total_students' => 0,
        'placed' => 0,
        'searching' => 0,
        'completed_ojt' => 0,
        'in_progress' => 0,
        'hiring_companies' => 0
    ];

    try {
        // Total assigned students
        $stmtTotal = $pdo->prepare('
            SELECT COUNT(DISTINCT aa.student_id) as count
            FROM adviser_assignment aa
            WHERE aa.adviser_id = ?
        ');
        $stmtTotal->execute([$adviser_id]);
        $stats['total_students'] = (int)$stmtTotal->fetchColumn();

        $stmtCompanies = $pdo->prepare('
            SELECT COUNT(DISTINCT i.employer_id) as count
            FROM adviser_assignment aa
            INNER JOIN ojt_record ojt ON ojt.student_id = aa.student_id
            INNER JOIN internship i ON i.internship_id = ojt.internship_id
            WHERE aa.adviser_id = ?
        ');
        $stmtCompanies->execute([$adviser_id]);
        $stats['hiring_companies'] = (int)$stmtCompanies->fetchColumn();

        // Students with completed OJT
        $stmtCompleted = $pdo->prepare('
            SELECT COUNT(DISTINCT ojt.student_id) as count
            FROM ojt_record ojt
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) = \'completed\'
        ');
        $stmtCompleted->execute([$adviser_id]);
        $stats['completed_ojt'] = (int)$stmtCompleted->fetchColumn();

        // In progress OJT
        $stmtInProgress = $pdo->prepare('
            SELECT COUNT(DISTINCT ojt.student_id) as count
            FROM ojt_record ojt
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) IN (\'in progress\', \'on track\', \'progressing\', \'behind\')
        ');
        $stmtInProgress->execute([$adviser_id]);
        $stats['in_progress'] = (int)$stmtInProgress->fetchColumn();

        // Placement rate: students with completed OJT / total students
        if ($stats['total_students'] > 0) {
            $stats['placement_rate'] = round(($stats['completed_ojt'] / $stats['total_students']) * 100);
        }

        // Completion rate: (completed + in progress) / total
        if ($stats['total_students'] > 0) {
            $stats['completion_rate'] = round((($stats['completed_ojt'] + $stats['in_progress']) / $stats['total_students']) * 100);
        }

        // Average OJT hours (from completed OJT records)
        $stmtAvgHours = $pdo->prepare('
            SELECT AVG(ojt.hours_completed) as avg_hours
            FROM ojt_record ojt
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) = \'completed\'
        ');
        $stmtAvgHours->execute([$adviser_id]);
        $avgHours = $stmtAvgHours->fetchColumn();
        $stats['avg_ojt_hours'] = $avgHours ? (int)$avgHours : 0;

        // Average evaluation rating: (adviser_eval + employer_eval) / 2 avg
        $stmtAvgRating = $pdo->prepare('
            SELECT AVG((COALESCE(ae.final_grade, 0) + COALESCE((ee.technical_score + ee.behavioral_score) / 2, 0)) / 2) as avg_rating
            FROM adviser_assignment aa
            LEFT JOIN ojt_record ojt ON aa.student_id = ojt.student_id
            LEFT JOIN adviser_evaluation ae ON ae.student_id = aa.student_id AND ae.internship_id = ojt.internship_id
            LEFT JOIN employer_evaluation ee ON ee.student_id = aa.student_id AND ee.internship_id = ojt.internship_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) = \'completed\'
        ');
        $stmtAvgRating->execute([$adviser_id]);
        $avgRating = $stmtAvgRating->fetchColumn();
        $stats['avg_eval_rating'] = $avgRating ? round($avgRating, 1) : 0;

        // Placed: (completed OJT students) for this user
        $stats['placed'] = $stats['completed_ojt'];

        // Searching: total - (completed + in progress)
        $stats['searching'] = max(0, $stats['total_students'] - $stats['completed_ojt'] - $stats['in_progress']);

    } catch (Exception $e) {
        error_log("adviser_analytics_get_stats error: " . $e->getMessage());
    }

    return $stats;
}

function adviser_analytics_get_placement_by_dept($pdo, $adviser_id) {
    $depts = [];

    try {
        $stmt = $pdo->prepare('
            SELECT 
                COALESCE(NULLIF(TRIM(s.track), \'\'), \'\') as track,
                COALESCE(NULLIF(TRIM(s.section), \'\'), \'\') as raw_section,
                CASE
                    WHEN COALESCE(NULLIF(TRIM(s.section), \'\'), \'\') = \'\' THEN \'Unassigned\'
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' AND UPPER(TRIM(s.section)) LIKE \'BA%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' THEN CONCAT(\'BA \', TRIM(s.section))
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' AND UPPER(TRIM(s.section)) LIKE \'NT%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' THEN CONCAT(\'NT \', TRIM(s.section))
                    ELSE TRIM(s.section)
                END as section,
                CASE
                    WHEN COALESCE(NULLIF(TRIM(s.section), \'\'), \'\') = \'\' THEN \'Unassigned\'
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' AND UPPER(TRIM(s.section)) LIKE \'BA%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' THEN CONCAT(\'BA \', TRIM(s.section))
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' AND UPPER(TRIM(s.section)) LIKE \'NT%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' THEN CONCAT(\'NT \', TRIM(s.section))
                    ELSE TRIM(s.section)
                END as department,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(ojt.completion_status)) = \'completed\' THEN s.student_id END) as completed,
                ROUND((COUNT(DISTINCT CASE WHEN LOWER(TRIM(ojt.completion_status)) = \'completed\' THEN s.student_id END) / 
                       COUNT(DISTINCT s.student_id)) * 100) as placement_rate
            FROM student s
            INNER JOIN adviser_assignment aa ON s.student_id = aa.student_id
            LEFT JOIN ojt_record ojt ON s.student_id = ojt.student_id
            WHERE aa.adviser_id = ?
            GROUP BY
                COALESCE(NULLIF(TRIM(s.track), \'\'), \'\'),
                COALESCE(NULLIF(TRIM(s.section), \'\'), \'\'),
                CASE
                    WHEN COALESCE(NULLIF(TRIM(s.section), \'\'), \'\') = \'\' THEN \'Unassigned\'
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' AND UPPER(TRIM(s.section)) LIKE \'BA%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'business analytics\' THEN CONCAT(\'BA \', TRIM(s.section))
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' AND UPPER(TRIM(s.section)) LIKE \'NT%\' THEN TRIM(s.section)
                    WHEN LOWER(TRIM(COALESCE(s.track, \'\'))) = \'networking\' THEN CONCAT(\'NT \', TRIM(s.section))
                    ELSE TRIM(s.section)
                END
            ORDER BY placement_rate DESC, section ASC
        ');
        $stmt->execute([$adviser_id]);
        $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("adviser_analytics_get_placement_by_dept error: " . $e->getMessage());
    }

    return $depts;
}

function adviser_analytics_get_top_companies($pdo, $adviser_id) {
    $companies = [];

    try {
        $stmt = $pdo->prepare('
            SELECT 
                e.employer_id,
                e.company_name,
                COALESCE(NULLIF(TRIM(e.industry), \'\'), \'General\') as industry,
                COALESCE(NULLIF(TRIM(e.verification_status), \'\'), \'Pending\') as verification_status,
                COALESCE(NULLIF(TRIM(e.company_badge_status), \'\'), \'None\') as company_badge_status,
                COUNT(DISTINCT ojt.student_id) as intern_count,
                ROUND(AVG((COALESCE(ae.final_grade, 0) + COALESCE((ee.technical_score + ee.behavioral_score) / 2, 0)) / 2), 1) as avg_rating,
                ROUND((COUNT(DISTINCT CASE WHEN LOWER(TRIM(ojt.completion_status)) = \'completed\' THEN ojt.student_id END) / 
                       COUNT(DISTINCT ojt.student_id)) * 100) as completion_rate
            FROM employer e
            INNER JOIN internship i ON e.employer_id = i.employer_id
            INNER JOIN ojt_record ojt ON i.internship_id = ojt.internship_id
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            LEFT JOIN adviser_evaluation ae ON ae.student_id = ojt.student_id AND ae.internship_id = ojt.internship_id
            LEFT JOIN employer_evaluation ee ON ee.student_id = ojt.student_id AND ee.internship_id = ojt.internship_id
            WHERE aa.adviser_id = ?
            GROUP BY e.employer_id, e.company_name, e.industry, e.verification_status, e.company_badge_status
            ORDER BY intern_count DESC
            LIMIT 8
        ');
        $stmt->execute([$adviser_id]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("adviser_analytics_get_top_companies error: " . $e->getMessage());
    }

    return $companies;
}

function adviser_analytics_employer_has_column($pdo, $column_name) {
    try {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'employer\'
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $stmt->execute([$column_name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function adviser_analytics_get_company_activity_report($pdo, $adviser_id) {
    $report = [
        'summary' => [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'pending' => 0,
            'not_active' => 0,
        ],
        'rows' => [],
    ];

    try {
        $hasContactPersonColumn = adviser_analytics_employer_has_column($pdo, 'contact_person_name');
        $contactPersonSelect = $hasContactPersonColumn
            ? 'e.contact_person_name'
            : '\'\' AS contact_person_name';

        $stmt = $pdo->prepare('
            SELECT
                e.employer_id,
                e.company_name,
                ' . $contactPersonSelect . ',
                COALESCE(NULLIF(TRIM(e.industry), \'\'), \'General\') AS industry,
                COALESCE(NULLIF(TRIM(e.verification_status), \'\'), \'Pending\') AS verification_status,
                e.email,
                e.contact_number,
                COALESCE(postings.total_postings, 0) AS total_postings,
                COALESCE(postings.open_postings, 0) AS open_postings,
                COALESCE(postings.open_slots, 0) AS open_slots,
                COALESCE(placements.student_count, 0) AS student_count,
                COALESCE(placements.active_interns, 0) AS active_interns,
                COALESCE(placements.completed_interns, 0) AS completed_interns,
                placements.latest_placement_date
            FROM employer e
            LEFT JOIN (
                SELECT
                    employer_id,
                    COUNT(*) AS total_postings,
                    SUM(CASE WHEN LOWER(COALESCE(status, \'\')) = \'open\' THEN 1 ELSE 0 END) AS open_postings,
                    SUM(CASE WHEN LOWER(COALESCE(status, \'\')) = \'open\' THEN COALESCE(slots_available, 0) ELSE 0 END) AS open_slots
                FROM internship
                GROUP BY employer_id
            ) postings ON postings.employer_id = e.employer_id
            LEFT JOIN (
                SELECT
                    i.employer_id,
                    COUNT(DISTINCT ojt.student_id) AS student_count,
                    COUNT(DISTINCT CASE
                        WHEN LOWER(COALESCE(ojt.completion_status, \'\')) NOT IN (\'completed\', \'complete\')
                        THEN ojt.student_id
                    END) AS active_interns,
                    COUNT(DISTINCT CASE
                        WHEN LOWER(COALESCE(ojt.completion_status, \'\')) IN (\'completed\', \'complete\')
                        THEN ojt.student_id
                    END) AS completed_interns,
                    MAX(COALESCE(ojt.start_date, ojt.created_at)) AS latest_placement_date
                FROM adviser_assignment aa
                INNER JOIN ojt_record ojt ON ojt.student_id = aa.student_id
                INNER JOIN internship i ON i.internship_id = ojt.internship_id
                WHERE aa.adviser_id = ?
                  AND COALESCE(NULLIF(TRIM(aa.status), \'\'), \'Active\') = \'Active\'
                GROUP BY i.employer_id
            ) placements ON placements.employer_id = e.employer_id
            ORDER BY e.company_name ASC
        ');
        $stmt->execute([$adviser_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $activity = adviser_analytics_company_activity_meta($row);
            $row['activity_status'] = $activity['label'];
            $row['activity_detail'] = $activity['detail'];
            $row['activity_class'] = $activity['class'];

            $statusKey = strtolower((string)$activity['label']);
            if (isset($report['summary'][$statusKey])) {
                $report['summary'][$statusKey]++;
            }

            $report['rows'][] = $row;
        }

        usort($report['rows'], static function ($a, $b) {
            $rank = ['Active' => 0, 'Pending' => 1, 'Inactive' => 2];
            $aRank = $rank[(string)($a['activity_status'] ?? '')] ?? 3;
            $bRank = $rank[(string)($b['activity_status'] ?? '')] ?? 3;
            if ($aRank === $bRank) {
                return strcmp((string)($a['company_name'] ?? ''), (string)($b['company_name'] ?? ''));
            }
            return $aRank <=> $bRank;
        });

        $report['summary']['total'] = count($report['rows']);
        $report['summary']['not_active'] = (int)$report['summary']['inactive'] + (int)$report['summary']['pending'];
    } catch (Exception $e) {
        error_log("adviser_analytics_get_company_activity_report error: " . $e->getMessage());
    }

    return $report;
}

function adviser_analytics_get_student_performance_report($pdo, $adviser_id) {
    $report = [
        'summary' => [
            'early_finishers' => 0,
            'punctual_students' => 0,
            'needs_attention' => 0,
            'evaluated_students' => 0,
        ],
        'early_finishers' => [],
        'punctual_students' => [],
        'needs_attention' => [],
    ];

    try {
        $placementsStmt = $pdo->prepare('
            SELECT
                ojt.record_id,
                ojt.student_id,
                ojt.internship_id,
                ojt.hours_required,
                ojt.hours_completed,
                ojt.start_date,
                ojt.end_date,
                ojt.completion_status,
                ojt.updated_at,
                s.student_number,
                s.first_name,
                s.last_name,
                s.program,
                s.department,
                i.title AS internship_title,
                e.company_name
            FROM adviser_assignment aa
            INNER JOIN student s ON s.student_id = aa.student_id
            INNER JOIN ojt_record ojt ON ojt.student_id = s.student_id
            INNER JOIN internship i ON i.internship_id = ojt.internship_id
            INNER JOIN employer e ON e.employer_id = i.employer_id
            WHERE aa.adviser_id = ?
              AND COALESCE(NULLIF(TRIM(aa.status), \'\'), \'Active\') = \'Active\'
            ORDER BY s.last_name ASC, s.first_name ASC, ojt.record_id DESC
        ');
        $placementsStmt->execute([$adviser_id]);
        $placements = $placementsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $recordIds = [];
        $placementByRecord = [];
        foreach ($placements as $placement) {
            $recordId = (int)($placement['record_id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }

            $recordIds[$recordId] = $recordId;
            $placementByRecord[$recordId] = $placement;
        }

        $logStatsByRecord = adviser_analytics_get_log_stats_by_record($pdo, array_values($recordIds));

        foreach ($placementByRecord as $recordId => $placement) {
            $stats = $logStatsByRecord[$recordId] ?? adviser_analytics_empty_log_stats();
            $hoursRequired = (float)($placement['hours_required'] ?? 0);
            $hoursCompleted = (float)($placement['hours_completed'] ?? 0);
            $endDate = trim((string)($placement['end_date'] ?? ''));
            $completionStatus = strtolower(trim((string)($placement['completion_status'] ?? '')));
            $isCompleted = in_array($completionStatus, ['completed', 'complete', 'done'], true)
                || ($hoursRequired > 0 && $hoursCompleted >= $hoursRequired);

            $completionDate = (string)($stats['completion_date'] ?? '');
            if ($completionDate === '' && $isCompleted) {
                $completionDate = (string)($stats['last_log_date'] ?? '');
            }

            if ($isCompleted && $completionDate !== '' && $endDate !== '') {
                $completionTs = strtotime($completionDate);
                $endTs = strtotime($endDate);
                if ($completionTs !== false && $endTs !== false && $completionTs < $endTs) {
                    $daysEarly = (int)floor(($endTs - $completionTs) / 86400);
                    if ($daysEarly > 0) {
                        $report['early_finishers'][] = adviser_analytics_build_student_performance_row(
                            $placement,
                            $stats,
                            [
                                'completion_date' => $completionDate,
                                'days_early' => $daysEarly,
                                'hours_text' => (int)round($hoursCompleted) . '/' . (int)round($hoursRequired) . ' hrs',
                            ]
                        );
                    }
                }
            }
        }

        usort($report['early_finishers'], static function ($a, $b) {
            return ((int)($b['days_early'] ?? 0)) <=> ((int)($a['days_early'] ?? 0));
        });
        $report['early_finishers'] = array_slice($report['early_finishers'], 0, 8);

        foreach ($placementByRecord as $recordId => $placement) {
            $stats = $logStatsByRecord[$recordId] ?? adviser_analytics_empty_log_stats();
            $totalLogs = (int)($stats['total_logs'] ?? 0);
            if ($totalLogs <= 0) {
                continue;
            }

            $onTimeRate = (int)($stats['on_time_rate'] ?? 0);
            $row = adviser_analytics_build_student_performance_row(
                $placement,
                $stats,
                [
                    'on_time_rate' => $onTimeRate,
                    'on_time_logs' => (int)($stats['on_time_logs'] ?? 0),
                    'late_logs' => (int)($stats['late_logs'] ?? 0),
                    'total_logs' => $totalLogs,
                ]
            );
            $report['punctual_students'][] = $row;
        }

        usort($report['punctual_students'], static function ($a, $b) {
            $rateCompare = ((int)($b['on_time_rate'] ?? 0)) <=> ((int)($a['on_time_rate'] ?? 0));
            if ($rateCompare !== 0) {
                return $rateCompare;
            }
            return ((int)($b['total_logs'] ?? 0)) <=> ((int)($a['total_logs'] ?? 0));
        });
        $report['punctual_students'] = array_slice($report['punctual_students'], 0, 8);

        $attentionRows = adviser_analytics_get_attention_rows($pdo, $adviser_id, $logStatsByRecord);
        $report['needs_attention'] = $attentionRows;

        $seenEvaluated = [];
        foreach ($attentionRows as $row) {
            $key = (int)($row['student_id'] ?? 0) . ':' . (int)($row['internship_id'] ?? 0);
            $seenEvaluated[$key] = true;
        }

        $evaluatedStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT CONCAT(ev.student_id, ":", ev.internship_id))
            FROM employer_evaluation ev
            INNER JOIN adviser_assignment aa ON aa.student_id = ev.student_id
            WHERE aa.adviser_id = ?
              AND COALESCE(NULLIF(TRIM(aa.status), \'\'), \'Active\') = \'Active\'
        ');
        $evaluatedStmt->execute([$adviser_id]);
        $report['summary']['evaluated_students'] = (int)$evaluatedStmt->fetchColumn();

        $report['summary']['early_finishers'] = count($report['early_finishers']);
        $report['summary']['punctual_students'] = count($report['punctual_students']);
        $report['summary']['needs_attention'] = count($report['needs_attention']);
    } catch (Exception $e) {
        error_log("adviser_analytics_get_student_performance_report error: " . $e->getMessage());
    }

    return $report;
}

function adviser_analytics_empty_log_stats() {
    return [
        'total_logs' => 0,
        'on_time_logs' => 0,
        'late_logs' => 0,
        'on_time_rate' => 0,
        'late_rate' => 0,
        'logged_days' => 0,
        'total_hours' => 0.0,
        'last_log_date' => '',
        'completion_date' => '',
    ];
}

function adviser_analytics_get_log_stats_by_record($pdo, array $record_ids) {
    $cleanIds = [];
    foreach ($record_ids as $recordId) {
        $id = (int)$recordId;
        if ($id > 0) {
            $cleanIds[$id] = $id;
        }
    }

    if (empty($cleanIds)) {
        return [];
    }

    $ids = array_values($cleanIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('
        SELECT
            d.record_id,
            d.log_date,
            d.hours_rendered,
            d.created_at,
            o.hours_required
        FROM daily_log d
        INNER JOIN ojt_record o ON o.record_id = d.record_id
        WHERE d.record_id IN (' . $placeholders . ')
        ORDER BY d.record_id ASC, d.log_date ASC, d.log_id ASC
    ');
    $stmt->execute($ids);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $statsByRecord = [];
    $seenDays = [];
    $runningHours = [];
    foreach ($logs as $log) {
        $recordId = (int)($log['record_id'] ?? 0);
        if ($recordId <= 0) {
            continue;
        }

        if (!isset($statsByRecord[$recordId])) {
            $statsByRecord[$recordId] = adviser_analytics_empty_log_stats();
            $seenDays[$recordId] = [];
            $runningHours[$recordId] = 0.0;
        }

        $logDate = trim((string)($log['log_date'] ?? ''));
        $createdAt = trim((string)($log['created_at'] ?? ''));
        $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';
        $hours = (float)($log['hours_rendered'] ?? 0);
        $hoursRequired = (float)($log['hours_required'] ?? 0);

        $statsByRecord[$recordId]['total_logs']++;
        $statsByRecord[$recordId]['total_hours'] += $hours;
        $runningHours[$recordId] += $hours;

        if ($logDate !== '') {
            $seenDays[$recordId][$logDate] = true;
            if ((string)$statsByRecord[$recordId]['last_log_date'] === '' || strcmp($logDate, (string)$statsByRecord[$recordId]['last_log_date']) > 0) {
                $statsByRecord[$recordId]['last_log_date'] = $logDate;
            }
        }

        if ($createdDate !== '' && $logDate !== '' && strcmp($createdDate, $logDate) <= 0) {
            $statsByRecord[$recordId]['on_time_logs']++;
        } else {
            $statsByRecord[$recordId]['late_logs']++;
        }

        if ($hoursRequired > 0 && $runningHours[$recordId] >= $hoursRequired && (string)$statsByRecord[$recordId]['completion_date'] === '') {
            $statsByRecord[$recordId]['completion_date'] = $logDate;
        }
    }

    foreach ($statsByRecord as $recordId => &$stats) {
        $totalLogs = (int)($stats['total_logs'] ?? 0);
        $stats['logged_days'] = count($seenDays[$recordId] ?? []);
        if ($totalLogs > 0) {
            $stats['on_time_rate'] = (int)round(((int)$stats['on_time_logs'] / $totalLogs) * 100);
            $stats['late_rate'] = (int)round(((int)$stats['late_logs'] / $totalLogs) * 100);
        }
    }
    unset($stats);

    return $statsByRecord;
}

function adviser_analytics_build_student_performance_row(array $placement, array $stats, array $extra = []) {
    $studentName = trim((string)($placement['first_name'] ?? '') . ' ' . (string)($placement['last_name'] ?? ''));

    return array_merge([
        'record_id' => (int)($placement['record_id'] ?? 0),
        'student_id' => (int)($placement['student_id'] ?? 0),
        'internship_id' => (int)($placement['internship_id'] ?? 0),
        'student_name' => $studentName !== '' ? $studentName : 'Unnamed Student',
        'student_number' => trim((string)($placement['student_number'] ?? '')),
        'program' => trim((string)($placement['program'] ?? '')),
        'department' => trim((string)($placement['department'] ?? '')),
        'internship_title' => trim((string)($placement['internship_title'] ?? 'Internship')),
        'company_name' => trim((string)($placement['company_name'] ?? 'Company')),
        'start_date' => (string)($placement['start_date'] ?? ''),
        'end_date' => (string)($placement['end_date'] ?? ''),
        'total_logs' => (int)($stats['total_logs'] ?? 0),
        'on_time_rate' => (int)($stats['on_time_rate'] ?? 0),
        'late_rate' => (int)($stats['late_rate'] ?? 0),
        'late_logs' => (int)($stats['late_logs'] ?? 0),
        'on_time_logs' => (int)($stats['on_time_logs'] ?? 0),
        'logged_days' => (int)($stats['logged_days'] ?? 0),
        'last_log_date' => (string)($stats['last_log_date'] ?? ''),
    ], $extra);
}

function adviser_analytics_get_attention_rows($pdo, $adviser_id, array $log_stats_by_record) {
    $stmt = $pdo->prepare('
        SELECT
            ev.evaluation_id,
            ev.student_id,
            ev.internship_id,
            ev.technical_score,
            ev.behavioral_score,
            ev.comments,
            ev.recommendation_status,
            ev.evaluation_date,
            ojt.record_id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.program,
            s.department,
            i.title AS internship_title,
            e.company_name
        FROM employer_evaluation ev
        INNER JOIN adviser_assignment aa ON aa.student_id = ev.student_id
        INNER JOIN student s ON s.student_id = ev.student_id
        INNER JOIN internship i ON i.internship_id = ev.internship_id
        INNER JOIN employer e ON e.employer_id = i.employer_id
        LEFT JOIN ojt_record ojt ON ojt.student_id = ev.student_id
            AND ojt.internship_id = ev.internship_id
        INNER JOIN (
            SELECT student_id, internship_id, MAX(evaluation_id) AS max_evaluation_id
            FROM employer_evaluation
            GROUP BY student_id, internship_id
        ) latest ON latest.max_evaluation_id = ev.evaluation_id
        WHERE aa.adviser_id = ?
          AND COALESCE(NULLIF(TRIM(aa.status), \'\'), \'Active\') = \'Active\'
        ORDER BY ev.evaluation_date DESC, ev.evaluation_id DESC
    ');
    $stmt->execute([$adviser_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    foreach ($evaluations as $evaluation) {
        $recordId = (int)($evaluation['record_id'] ?? 0);
        $stats = $recordId > 0 ? ($log_stats_by_record[$recordId] ?? adviser_analytics_empty_log_stats()) : adviser_analytics_empty_log_stats();
        $technical = (float)($evaluation['technical_score'] ?? 0);
        $behavioral = (float)($evaluation['behavioral_score'] ?? 0);
        $overall = round(($technical + $behavioral) / 2, 1);
        $recommendation = trim((string)($evaluation['recommendation_status'] ?? ''));
        $lateRate = (int)($stats['late_rate'] ?? 0);
        $reasons = [];
        $severity = 0;

        if (strtolower($recommendation) === 'not recommended') {
            $reasons[] = 'Employer marked Not Recommended';
            $severity += 5;
        }
        if ($behavioral > 0 && $behavioral < 3.0) {
            $reasons[] = 'Low behavioral score (' . number_format($behavioral, 1) . '/5)';
            $severity += 4;
        }
        if ($technical > 0 && $technical < 3.0) {
            $reasons[] = 'Low technical score (' . number_format($technical, 1) . '/5)';
            $severity += 3;
        }
        if ($overall > 0 && $overall < 3.0 && empty($reasons)) {
            $reasons[] = 'Low overall evaluation (' . number_format($overall, 1) . '/5)';
            $severity += 3;
        }
        if ($lateRate >= 40) {
            $reasons[] = 'High late-log rate (' . $lateRate . '%)';
            $severity += 2;
        }

        if (empty($reasons)) {
            continue;
        }

        $studentName = trim((string)($evaluation['first_name'] ?? '') . ' ' . (string)($evaluation['last_name'] ?? ''));
        $rows[] = [
            'student_id' => (int)($evaluation['student_id'] ?? 0),
            'internship_id' => (int)($evaluation['internship_id'] ?? 0),
            'student_name' => $studentName !== '' ? $studentName : 'Unnamed Student',
            'student_number' => trim((string)($evaluation['student_number'] ?? '')),
            'program' => trim((string)($evaluation['program'] ?? '')),
            'department' => trim((string)($evaluation['department'] ?? '')),
            'internship_title' => trim((string)($evaluation['internship_title'] ?? 'Internship')),
            'company_name' => trim((string)($evaluation['company_name'] ?? 'Company')),
            'technical_score' => $technical,
            'behavioral_score' => $behavioral,
            'overall_score' => $overall,
            'recommendation_status' => $recommendation !== '' ? $recommendation : 'Final',
            'evaluation_date' => (string)($evaluation['evaluation_date'] ?? ''),
            'comment' => (string)($evaluation['comments'] ?? ''),
            'reasons' => $reasons,
            'reason_text' => implode('; ', $reasons),
            'late_rate' => $lateRate,
            'late_logs' => (int)($stats['late_logs'] ?? 0),
            'total_logs' => (int)($stats['total_logs'] ?? 0),
            'severity' => $severity,
        ];
    }

    usort($rows, static function ($a, $b) {
        $severityCompare = ((int)($b['severity'] ?? 0)) <=> ((int)($a['severity'] ?? 0));
        if ($severityCompare !== 0) {
            return $severityCompare;
        }
        return ((float)($a['overall_score'] ?? 0)) <=> ((float)($b['overall_score'] ?? 0));
    });

    return array_slice($rows, 0, 10);
}

function adviser_analytics_get_top_skills($pdo, $adviser_id) {
    $skills = [];

    try {
        $stmt = $pdo->prepare('
            SELECT 
                i.skills,
                COUNT(DISTINCT ojt.student_id) as postings
            FROM internship i
            INNER JOIN ojt_record ojt ON i.internship_id = ojt.internship_id
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND i.skills IS NOT NULL
            AND i.skills != \'\'
            GROUP BY i.skills
            ORDER BY postings DESC
            LIMIT 5
        ');
        $stmt->execute([$adviser_id]);
        $rawSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse comma-separated skills and aggregate
        $skillCounts = [];
        foreach ($rawSkills as $row) {
            $skillList = array_map('trim', explode(',', $row['skills']));
            foreach ($skillList as $skill) {
                if (!empty($skill)) {
                    $skillCounts[$skill] = ($skillCounts[$skill] ?? 0) + $row['postings'];
                }
            }
        }

        // Sort and limit to top 5
        arsort($skillCounts);
        foreach (array_slice($skillCounts, 0, 5) as $skill => $count) {
            $skills[] = ['skill' => $skill, 'postings' => $count];
        }
    } catch (Exception $e) {
        error_log("adviser_analytics_get_top_skills error: " . $e->getMessage());
    }

    return $skills;
}

function adviser_analytics_get_trends($pdo, $adviser_id) {
    $trends = [];

    try {
        // Trend 1: Placement rate trend (vs last month or general upward)
        $stmtPlacement = $pdo->prepare('
            SELECT COUNT(DISTINCT ojt.student_id) as completed_count
            FROM ojt_record ojt
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) = \'completed\'
        ');
        $stmtPlacement->execute([$adviser_id]);
        $completedCount = (int)$stmtPlacement->fetchColumn();

        $trends[] = [
            'type' => 'positive',
            'text' => 'Total students completed OJT: ' . $completedCount,
            'icon' => 'fa-check-circle'
        ];

        // Trend 2: Average rating insight
        $stmtRating = $pdo->prepare('
            SELECT AVG((COALESCE(ae.final_grade, 0) + COALESCE((ee.technical_score + ee.behavioral_score) / 2, 0)) / 2) as avg_rating
            FROM adviser_assignment aa
            LEFT JOIN ojt_record ojt ON aa.student_id = ojt.student_id
            LEFT JOIN adviser_evaluation ae ON ae.student_id = aa.student_id AND ae.internship_id = ojt.internship_id
            LEFT JOIN employer_evaluation ee ON ee.student_id = aa.student_id AND ee.internship_id = ojt.internship_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) = \'completed\'
        ');
        $stmtRating->execute([$adviser_id]);
        $avgRating = $stmtRating->fetchColumn();

        if ($avgRating && $avgRating >= 4.0) {
            $trends[] = [
                'type' => 'positive',
                'text' => 'Strong average evaluation rating: ' . number_format($avgRating, 1),
                'icon' => 'fa-arrow-up'
            ];
        } elseif ($avgRating && $avgRating < 3.0) {
            $trends[] = [
                'type' => 'warning',
                'text' => 'Evaluation rating below 3.0, review student performance',
                'icon' => 'fa-alert-circle'
            ];
        }

        // Trend 3: Student progression insight
        $stmtProgress = $pdo->prepare('
            SELECT COUNT(DISTINCT ojt.student_id) as in_progress_count
            FROM ojt_record ojt
            INNER JOIN adviser_assignment aa ON ojt.student_id = aa.student_id
            WHERE aa.adviser_id = ?
            AND LOWER(TRIM(ojt.completion_status)) IN (\'in progress\', \'on track\', \'progressing\', \'behind\')
        ');
        $stmtProgress->execute([$adviser_id]);
        $inProgressCount = (int)$stmtProgress->fetchColumn();

        if ($inProgressCount > 0) {
            $trends[] = [
                'type' => 'neutral',
                'text' => 'Monitor ' . $inProgressCount . ' students currently in progress',
                'icon' => 'fa-hourglass'
            ];
        }

    } catch (Exception $e) {
        error_log("adviser_analytics_get_trends error: " . $e->getMessage());
    }

    return $trends;
}
