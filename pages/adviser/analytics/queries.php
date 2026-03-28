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
                s.department,
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(ojt.completion_status)) = \'completed\' THEN s.student_id END) as completed,
                ROUND((COUNT(DISTINCT CASE WHEN LOWER(TRIM(ojt.completion_status)) = \'completed\' THEN s.student_id END) / 
                       COUNT(DISTINCT s.student_id)) * 100) as placement_rate
            FROM student s
            INNER JOIN adviser_assignment aa ON s.student_id = aa.student_id
            LEFT JOIN ojt_record ojt ON s.student_id = ojt.student_id
            WHERE aa.adviser_id = ?
            GROUP BY s.department
            ORDER BY placement_rate DESC
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
