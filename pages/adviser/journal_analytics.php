<?php
/**
 * Adviser Journal Analytics Dashboard
 * Allows advisers to monitor and view student journal entries and progress.
 */
require_once __DIR__ . '/../../backend/db_connect.php';

$role = (string)($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$adviserId = (int)($_SESSION['adviser_id'] ?? $userId);

if ($role !== 'adviser' || $adviserId <= 0) {
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

$studentId = max(0, (int)($_GET['student_id'] ?? 0));
$sortByRaw = trim((string)($_GET['sort_by'] ?? 'date_desc'));
$allowedSorts = ['date_asc', 'date_desc'];
$sortBy = in_array($sortByRaw, $allowedSorts, true) ? $sortByRaw : 'date_desc';

if (!isset($baseUrl)) {
    $baseUrl = '/SkillHive';
}

if (!function_exists('adviser_journal_analytics_url')) {
    function adviser_journal_analytics_url(string $baseUrl, int $studentId = 0, string $sortBy = 'date_desc'): string
    {
        $sortValue = in_array($sortBy, ['date_asc', 'date_desc'], true) ? $sortBy : 'date_desc';

        $params = [
            'page' => 'adviser/journal_analytics',
            'sort_by' => $sortValue,
        ];

        if ($studentId > 0) {
            $params['student_id'] = $studentId;
        }

        return $baseUrl . '/layout.php?' . http_build_query($params);
    }
}

if (!function_exists('adviser_journal_decode_array_field')) {
    function adviser_journal_decode_array_field(?string $raw): array
    {
        $value = trim((string)($raw ?? ''));
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('adviser_analyze_sentiment')) {
    function adviser_analyze_sentiment(string $text): array
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return [
                'score' => 0.0,
                'label' => 'Neutral',
                'tone' => 'No reflection content provided.',
                'needs_action' => false,
                'action_reasons' => [],
            ];
        }

        $positiveWords = [
            'good', 'great', 'improve', 'improved', 'progress', 'learned', 'confident',
            'happy', 'productive', 'success', 'accomplished', 'helpful', 'excited',
            'smooth', 'clear', 'supported', 'mentor', 'teamwork', 'cooperative',
            'on time', 'resolved', 'naintindihan', 'natuto', 'maayos', 'nakatulong',
        ];
        $positivePhrases = [
            'did well', 'went well', 'feeling better', 'more confident', 'well guided',
            'learned a lot', 'handled it well', 'got positive feedback',
        ];

        $negativeWords = [
            'bad', 'difficult', 'hard', 'stress', 'stressed', 'anxious', 'overwhelmed',
            'problem', 'failed', 'confused', 'frustrated', 'burnout', 'worried',
            'toxic', 'delay', 'delayed', 'late', 'mistake', 'errors', 'pagod',
            'nahirapan', 'kulang', 'pressure', 'exhausted',
        ];
        $negativePhrases = [
            'not good', 'did not go well', 'no guidance', 'walang guidance',
            'hindi ko alam', 'hindi ko kaya', 'too much workload', 'i feel unsafe',
            'i cannot focus', 'drained every day',
        ];

        $urgentFlags = [
            'depressed' => 'Possible depression signal',
            'hopeless' => 'Hopeless wording detected',
            'panic' => 'Panic-related wording detected',
            'suicidal' => 'Potential self-harm signal detected',
            'self-harm' => 'Potential self-harm signal detected',
            'abuse' => 'Abuse-related wording detected',
            'harassed' => 'Harassment wording detected',
            'sexual harassment' => 'Possible sexual harassment report',
            'unsafe' => 'Safety concern detected',
            'threat' => 'Threat-related wording detected',
            'trauma' => 'Trauma-related wording detected',
            'mental health' => 'Mental health concern detected',
            'breakdown' => 'Possible emotional breakdown wording detected',
            'gusto ko sumuko' => 'Student may be expressing intent to give up',
            'ayoko na' => 'Student may be expressing acute distress',
            'cannot cope' => 'Student may be unable to cope',
            "can't cope" => 'Student may be unable to cope',
        ];

        $countMatches = static function (string $haystack, array $needles): int {
            $count = 0;
            foreach ($needles as $needle) {
                $count += substr_count($haystack, (string)$needle);
            }
            return $count;
        };

        $posCount = $countMatches($normalized, $positiveWords) + (2 * $countMatches($normalized, $positivePhrases));
        $negCount = $countMatches($normalized, $negativeWords) + (2 * $countMatches($normalized, $negativePhrases));

        $denominator = max(1, $posCount + $negCount);
        $score = ($posCount - $negCount) / $denominator;

        if ($score >= 0.6) {
            $label = 'Positive';
            $tone = 'The student entry is optimistic and constructive.';
        } elseif ($score >= 0.2) {
            $label = 'Mostly Positive';
            $tone = 'The student shows generally positive momentum.';
        } elseif ($score > -0.2) {
            $label = 'Neutral';
            $tone = 'The student entry reflects a balanced, matter-of-fact tone.';
        } elseif ($score > -0.6) {
            $label = 'Mostly Negative';
            $tone = 'The student shows signs of difficulty or frustration.';
        } else {
            $label = 'Negative';
            $tone = 'The student may be experiencing significant stress or challenges.';
        }

        $actionReasons = [];
        foreach ($urgentFlags as $flag => $reason) {
            if (strpos($normalized, $flag) !== false) {
                $actionReasons[] = $reason;
            }
        }

        $needsAction = !empty($actionReasons) || $score <= -0.6;
        if ($score <= -0.6 && empty($actionReasons)) {
            $actionReasons[] = 'Highly negative entry detected - please review';
        }

        return [
            'score' => round($score, 2),
            'label' => $label,
            'tone' => $tone,
            'needs_action' => $needsAction,
            'action_reasons' => array_values(array_unique($actionReasons)),
        ];
    }
}

$students = [];
$selectedStudent = null;
$journalEntries = [];
$studentStats = null;
$entryQualityScores = [];
$entrySentiments = [];
$overallSentimentSummary = null;
$journalVisibilityColumnExists = false;
$entryTrendData = [];
$entryCategoryData = [];

try {
    $visibilityCheckStmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'ojt_journal_entries'
           AND COLUMN_NAME = 'is_visible_to_adviser'"
    );
    $visibilityCheckStmt->execute();
    $journalVisibilityColumnExists = $visibilityCheckStmt->rowCount() > 0;
} catch (Throwable $e) {
    $journalVisibilityColumnExists = false;
}

$journalVisibilityJoinSql = $journalVisibilityColumnExists
    ? 'LEFT JOIN ojt_journal_entries jje ON jje.record_id = o.record_id AND COALESCE(jje.is_visible_to_adviser, 1) = 1'
    : 'LEFT JOIN ojt_journal_entries jje ON jje.record_id = o.record_id';

$studentsStmt = $pdo->prepare(
    'SELECT
        s.student_id,
        s.first_name,
        s.last_name,
        s.email,
        s.program,
        COALESCE(jstats.journal_count, 0) AS journal_count,
        jstats.last_entry_date
     FROM adviser_assignment aa
     INNER JOIN student s ON s.student_id = aa.student_id
     LEFT JOIN (
        SELECT
            o.student_id,
            COUNT(DISTINCT jje.journal_id) AS journal_count,
            MAX(jje.entry_date) AS last_entry_date
        FROM ojt_record o
                ' . $journalVisibilityJoinSql . '
        GROUP BY o.student_id
     ) jstats ON jstats.student_id = s.student_id
     WHERE aa.adviser_id = :adviser_id
       AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
     GROUP BY s.student_id, s.first_name, s.last_name, s.email, s.program, jstats.journal_count, jstats.last_entry_date
     ORDER BY s.first_name ASC, s.last_name ASC'
);
$studentsStmt->execute([':adviser_id' => $adviserId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($studentId > 0) {
    $selectedStmt = $pdo->prepare(
        'SELECT
            s.student_id,
            s.first_name,
            s.last_name,
            s.email,
            s.program,
            s.department
         FROM student s
         INNER JOIN adviser_assignment aa ON aa.student_id = s.student_id
         WHERE s.student_id = :student_id
           AND aa.adviser_id = :adviser_id
           AND COALESCE(NULLIF(TRIM(aa.status), ""), "Active") = "Active"
         LIMIT 1'
    );
    $selectedStmt->execute([
        ':student_id' => $studentId,
        ':adviser_id' => $adviserId,
    ]);
    $selectedStudent = $selectedStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selectedStudent) {
        $recordStmt = $pdo->prepare(
            'SELECT
                o.record_id,
                o.hours_completed,
                o.hours_required,
                e.company_name,
                i.title
             FROM ojt_record o
             LEFT JOIN internship i ON i.internship_id = o.internship_id
             LEFT JOIN employer e ON e.employer_id = i.employer_id
             WHERE o.student_id = :student_id
             ORDER BY o.record_id DESC
             LIMIT 1'
        );
        $recordStmt->execute([':student_id' => $studentId]);
        $ojtRecord = $recordStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($ojtRecord) {
            $orderBySql = $sortBy === 'date_asc'
                ? 'ORDER BY entry_date ASC, journal_id ASC'
                : 'ORDER BY entry_date DESC, journal_id DESC';

            $journalVisibilityWhereSql = $journalVisibilityColumnExists
                ? 'AND COALESCE(is_visible_to_adviser, 1) = 1'
                : '';

            $entriesStmt = $pdo->prepare(
                'SELECT
                    journal_id,
                    entry_date,
                    company_department,
                    reflection,
                    tasks_accomplished,
                    skills_applied_learned,
                    challenges_encountered,
                    solutions_actions_taken,
                    key_learnings_insights
                 FROM ojt_journal_entries
                 WHERE record_id = :record_id
                      ' . $journalVisibilityWhereSql . '
                 ' . $orderBySql . '
                 LIMIT 200'
            );
            $entriesStmt->execute([':record_id' => (int)$ojtRecord['record_id']]);
            $rawEntries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawEntries as $entry) {
                $entry['company_department'] = trim((string)($entry['company_department'] ?? ''));
                $entry['reflection'] = trim((string)($entry['reflection'] ?? ''));
                $entry['tasks_accomplished'] = adviser_journal_decode_array_field($entry['tasks_accomplished'] ?? null);
                $entry['skills_applied_learned'] = adviser_journal_decode_array_field($entry['skills_applied_learned'] ?? null);
                $entry['challenges_encountered'] = adviser_journal_decode_array_field($entry['challenges_encountered'] ?? null);
                $entry['solutions_actions_taken'] = adviser_journal_decode_array_field($entry['solutions_actions_taken'] ?? null);
                $entry['key_learnings_insights'] = adviser_journal_decode_array_field($entry['key_learnings_insights'] ?? null);
                $journalEntries[] = $entry;
            }

            $totalEntries = count($journalEntries);
            $allSkills = [];
            $totalChallenges = 0;
            $totalSolutions = 0;
            $totalTasks = 0;
            $totalInsights = 0;
            $totalReflections = 0;
            $entriesPerWeek = [];

            foreach ($journalEntries as $entry) {
                $skills = is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [];
                $challenges = is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : [];
                $solutions = is_array($entry['solutions_actions_taken'] ?? null) ? $entry['solutions_actions_taken'] : [];
                $tasks = is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [];
                $insights = is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : [];
                $reflection = trim((string)($entry['reflection'] ?? ''));
                $entryDateRaw = trim((string)($entry['entry_date'] ?? ''));
                $entryTs = $entryDateRaw !== '' ? strtotime($entryDateRaw) : false;
                if ($entryTs !== false) {
                    $weekKey = date('Y-m-d', strtotime('monday this week', $entryTs));
                    $entriesPerWeek[$weekKey] = (int)($entriesPerWeek[$weekKey] ?? 0) + 1;
                }

                $allSkills = array_merge($allSkills, $skills);
                $totalChallenges += count($challenges);
                $totalSolutions += count($solutions);
                $totalTasks += count($tasks);
                $totalInsights += count($insights);
                if ($reflection !== '') {
                    $totalReflections++;
                }
            }

            if (!empty($entriesPerWeek)) {
                ksort($entriesPerWeek);
                $entriesPerWeek = array_slice($entriesPerWeek, -8, null, true);
                foreach ($entriesPerWeek as $weekDate => $count) {
                    $entryTrendData[] = [
                        'label' => date('M j', strtotime((string)$weekDate)),
                        'value' => (int)$count,
                    ];
                }
            }

            $entryCategoryData = [
                ['label' => 'Tasks', 'value' => $totalTasks],
                ['label' => 'Skills', 'value' => count($allSkills)],
                ['label' => 'Challenges', 'value' => $totalChallenges],
                ['label' => 'Solutions', 'value' => $totalSolutions],
                ['label' => 'Insights', 'value' => $totalInsights],
                ['label' => 'Reflections', 'value' => $totalReflections],
            ];

            $uniqueSkills = count(array_unique(array_filter(array_map('strval', $allSkills))));
            $avgDailyTasks = $totalEntries > 0 ? round($totalTasks / $totalEntries, 1) : 0.0;

            if (!function_exists('journal_calculate_entry_quality')) {
                $journalHelperPath = __DIR__ . '/../student/ojt-log/journal_helper.php';
                if (file_exists($journalHelperPath)) {
                    require_once $journalHelperPath;
                }
            }

            if (function_exists('journal_calculate_entry_quality')) {
                foreach ($journalEntries as $entry) {
                    $summaryText = implode(' ', $entry['tasks_accomplished']) . ' ' . implode(' ', $entry['key_learnings_insights']);
                    $entryQualityScores[(int)$entry['journal_id']] = journal_calculate_entry_quality($entry, $summaryText);
                }
            }

            $sentimentTotal = 0.0;
            $positiveEntries = 0;
            $negativeEntries = 0;
            $needsActionCount = 0;

            foreach ($journalEntries as $entry) {
                $entryId = (int)($entry['journal_id'] ?? 0);
                $sentimentText = trim(
                    implode(' ', is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [])
                    . ' ' . implode(' ', is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : [])
                    . ' ' . (string)($entry['reflection'] ?? '')
                );
                $analysis = adviser_analyze_sentiment($sentimentText);
                $entrySentiments[$entryId] = $analysis;

                $score = (float)($analysis['score'] ?? 0.0);
                $sentimentTotal += $score;

                if ($score >= 0.2) {
                    $positiveEntries++;
                } elseif ($score <= -0.2) {
                    $negativeEntries++;
                }

                if (!empty($analysis['needs_action'])) {
                    $needsActionCount++;
                }
            }

            if ($totalEntries > 0) {
                $averageScore = $sentimentTotal / $totalEntries;
                if ($averageScore >= 0.6) {
                    $overallLabel = 'Generally Positive';
                } elseif ($averageScore >= 0.2) {
                    $overallLabel = 'Mostly Positive';
                } elseif ($averageScore > -0.2) {
                    $overallLabel = 'Mixed / Neutral';
                } elseif ($averageScore > -0.6) {
                    $overallLabel = 'Mostly Negative';
                } else {
                    $overallLabel = 'Generally Negative';
                }

                $overallSentimentSummary = [
                    'avg_score' => round($averageScore, 2),
                    'label' => $overallLabel,
                    'positive_entries' => $positiveEntries,
                    'negative_entries' => $negativeEntries,
                    'needs_action_count' => $needsActionCount,
                ];
            }

            $studentStats = [
                'total_entries' => $totalEntries,
                'unique_skills' => $uniqueSkills,
                'total_challenges' => $totalChallenges,
                'total_solutions' => $totalSolutions,
                'avg_daily_tasks' => $avgDailyTasks,
                'hours_completed' => (float)($ojtRecord['hours_completed'] ?? 0),
                'hours_required' => (float)($ojtRecord['hours_required'] ?? 0),
                'company' => (string)($ojtRecord['company_name'] ?? 'N/A'),
                'internship_title' => (string)($ojtRecord['title'] ?? 'N/A'),
            ];
        }
    }
}

$sortChangeBaseUrl = $baseUrl . '/layout.php?' . http_build_query([
    'page' => 'adviser/journal_analytics',
    'student_id' => $studentId,
]);
?>

<style>
    .journal-hero-banner,
    .analytics-container {
        --ja-banner-dark: #050505;
        --ja-banner-mid: #12b3ac;
        --ja-ink-strong: #102238;
        --ja-ink: #25384d;
        --ja-muted: #627489;
        --ja-surface: #f6f9fc;
        --ja-border: #d7e0eb;
        --ja-accent: #1a6678;
        --ja-accent-soft: #deeff1;
    }

    .analytics-container {
        display: grid;
        grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
        gap: 20px;
        margin-top: 20px;
    }

    .student-list {
        background: #ffffff;
        border: 1px solid var(--ja-border);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
        max-height: 78vh;
        position: sticky;
        top: 18px;
        overflow-y: auto;
    }

    .student-list-header {
        position: sticky;
        top: 0;
        z-index: 1;
        background: linear-gradient(135deg, var(--ja-banner-dark) 0%, var(--ja-banner-mid) 100%);
        color: #ffffff;
        padding: 14px 16px;
        font-weight: 700;
        font-size: 0.92rem;
        letter-spacing: 0.02em;
    }

    .student-item {
        display: block;
        padding: 12px 14px;
        border-bottom: 1px solid #ecf1f6;
        text-decoration: none;
        color: inherit;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .student-item:hover {
        background: var(--ja-surface);
    }

    .student-item.active {
        background: #edf3fa;
        border-left: 4px solid var(--ja-accent);
        padding-left: 10px;
    }

    .student-name {
        font-weight: 700;
        color: var(--ja-ink-strong);
        font-size: 0.93rem;
    }

    .student-meta {
        font-size: 0.81rem;
        color: var(--ja-muted);
        margin-top: 4px;
    }

    .student-badge {
        display: inline-block;
        background: var(--ja-accent-soft);
        color: var(--ja-accent);
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        margin-right: 4px;
    }

    .analytics-main {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .panel-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid var(--ja-border);
        padding: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }

    .student-profile-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--ja-ink-strong);
    }

    .student-profile-subtitle {
        color: var(--ja-muted);
        margin: 8px 0 16px 0;
        font-size: 0.9rem;
    }

    .progress-bar {
        width: 100%;
        height: 10px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--ja-banner-mid), #335d89);
        border-radius: 999px;
    }

    .stat-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
        gap: 12px;
    }

    .stat-card {
        background: var(--ja-surface);
        border: 1px solid var(--ja-border);
        border-radius: 12px;
        padding: 14px;
        text-align: left;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--ja-banner-mid);
        line-height: 1;
    }

    .stat-label {
        font-size: 0.78rem;
        color: var(--ja-muted);
        margin-top: 6px;
        font-weight: 600;
    }

    .panel-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
    }

    .panel-card-header h3 {
        font-size: 1.05rem;
        margin: 0;
        color: var(--ja-ink-strong);
        font-weight: 800;
    }

    .journal-entry-controls {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .sort-select,
    .print-btn {
        border: 1px solid var(--ja-border);
        border-radius: 999px;
        min-height: 34px;
        padding: 0 12px;
        font-size: 0.83rem;
        font-weight: 700;
        color: var(--ja-ink);
        background: #ffffff;
    }

    .print-btn {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .print-btn:hover,
    .sort-select:hover {
        border-color: var(--ja-accent);
        color: var(--ja-accent);
    }

    .analytics-graph-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .analytics-graph-card {
        background: #ffffff;
        border: 1px solid var(--ja-border);
        border-radius: 12px;
        padding: 12px;
    }

    .analytics-graph-title {
        margin: 0 0 10px 0;
        font-size: 0.86rem;
        font-weight: 800;
        color: var(--ja-banner-mid);
    }

    .graph-row {
        display: grid;
        grid-template-columns: 66px 1fr 28px;
        gap: 8px;
        align-items: center;
        margin-bottom: 7px;
    }

    .graph-label {
        font-size: 0.73rem;
        color: var(--ja-muted);
        font-weight: 700;
        white-space: nowrap;
    }

    .graph-track {
        height: 9px;
        border-radius: 999px;
        background: #edf2f8;
        overflow: hidden;
    }

    .graph-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--ja-banner-dark), var(--ja-banner-mid));
    }

    .graph-value {
        font-size: 0.73rem;
        color: var(--ja-ink);
        font-weight: 700;
        text-align: right;
    }

    .sentiment-card {
        background: #ffffff;
        border: 1px solid var(--ja-border);
        border-radius: 12px;
        padding: 14px;
    }

    .sentiment-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .sentiment-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--ja-ink-strong);
    }

    .sentiment-overall-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 800;
        color: #ffffff;
        background: linear-gradient(135deg, var(--ja-banner-dark), var(--ja-banner-mid));
        white-space: nowrap;
    }

    .sentiment-meter-track {
        width: 100%;
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, #d32f2f 0%, #f59e0b 45%, #16a34a 100%);
        position: relative;
        margin: 8px 0 6px;
    }

    .sentiment-meter-needle {
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #ffffff;
        border: 2px solid #102238;
    }

    .sentiment-meter-labels {
        display: flex;
        justify-content: space-between;
        color: var(--ja-muted);
        font-size: 0.72rem;
        margin-bottom: 10px;
    }

    .sentiment-filter-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .sentiment-pill {
        border: 1px solid var(--ja-border);
        border-radius: 999px;
        background: #ffffff;
        color: var(--ja-ink);
        padding: 5px 12px;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
    }

    .sentiment-pill.active {
        color: #ffffff;
        border-color: transparent;
        background: linear-gradient(135deg, var(--ja-banner-dark), var(--ja-banner-mid));
    }

    .sentiment-alert {
        background: #fff1f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #dc2626;
        border-radius: 12px;
        padding: 12px;
        color: #7f1d1d;
        font-size: 0.82rem;
    }

    .sentiment-alert-title {
        margin: 0 0 4px;
        font-weight: 800;
    }

    .entry-sentiment-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        margin-top: 8px;
        border: 1px solid transparent;
    }

    .entry-sentiment-badge.pos,
    .entry-sentiment-badge.mpos {
        background: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .entry-sentiment-badge.neu {
        background: #eef2f7;
        color: #334155;
        border-color: #cbd5e1;
    }

    .entry-sentiment-badge.mneg,
    .entry-sentiment-badge.neg {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fca5a5;
    }

    .entry-action-alert {
        margin-top: 10px;
        border: 1px solid #fecaca;
        border-left: 3px solid #dc2626;
        border-radius: 10px;
        background: #fff6f6;
        padding: 9px 10px;
        color: #7f1d1d;
        font-size: 0.8rem;
    }

    .entry-action-alert ul {
        margin: 6px 0 0;
        padding-left: 18px;
    }

    .journal-entry-preview {
        background: #ffffff;
        border: 1px solid #dbe3ef;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .journal-entry-preview:hover {
        border-color: #c3d4ea;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
    }

    .entry-date {
        font-weight: 800;
        color: var(--ja-banner-mid);
        font-size: 0.92rem;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .entry-preview-text {
        color: var(--ja-ink);
        font-size: 0.88rem;
        margin-top: 8px;
        line-height: 1.55;
    }

    .quality-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 8px;
    }

    .quality-excellent {
        background: #dcfce7;
        color: #166534;
    }

    .quality-good {
        background: #dbeafe;
        color: #12b3ac;
    }

    .quality-fair {
        background: #fef3c7;
        color: #92400e;
    }

    .quality-basic {
        background: #fee2e2;
        color: #9f1239;
    }

    .skill-tag {
        display: inline-block;
        background: var(--ja-accent-soft);
        color: var(--ja-accent);
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-right: 6px;
        margin-bottom: 6px;
    }

    .entry-details {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed #cbd5e1;
    }

    .entry-details summary {
        list-style: none;
        cursor: pointer;
        font-size: 0.84rem;
        font-weight: 700;
        color: var(--ja-ink-strong);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .entry-details summary::-webkit-details-marker {
        display: none;
    }

    .entry-details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .entry-detail-block {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px;
        background: #fafcff;
    }

    .entry-detail-block h5 {
        margin: 0 0 6px 0;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ja-accent);
        font-weight: 800;
    }

    .entry-detail-block ul {
        margin: 0;
        padding-left: 18px;
        color: var(--ja-ink);
        font-size: 0.84rem;
        line-height: 1.45;
    }

    .entry-detail-block p {
        margin: 0;
        color: var(--ja-ink);
        font-size: 0.84rem;
        white-space: pre-wrap;
        line-height: 1.45;
    }

    .empty-state {
        text-align: center;
        padding: 36px 20px;
        color: #64748b;
    }

    .empty-state-icon {
        font-size: 2.2rem;
        margin-bottom: 10px;
        opacity: 0.45;
    }

    .no-student-message {
        text-align: center;
        padding: 48px 20px;
    }

    .no-student-message-icon {
        font-size: 2.5rem;
        color: #64748b;
        margin-bottom: 14px;
    }

    .print-sheet-header {
        display: none;
    }

    @media (max-width: 1100px) {
        .analytics-container {
            grid-template-columns: 1fr;
        }

        .student-list {
            position: static;
            max-height: none;
        }
    }

    @media (max-width: 768px) {
        .analytics-graph-grid {
            grid-template-columns: 1fr;
        }

        .entry-details-grid {
            grid-template-columns: 1fr;
        }

        .panel-card-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media print {
        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            background: #ffffff !important;
            color: #000000 !important;
        }

        .dashboard-shell,
        .sidebar,
        .navbar,
        .journal-hero-banner,
        .student-list,
        .print-btn,
        .sort-select,
        .panel-card-header,
        .page-header,
        .tab-nav,
        .filter-row {
            display: none !important;
        }

        .analytics-container,
        .analytics-main {
            display: block !important;
            margin: 0 !important;
            gap: 0 !important;
        }

        .panel-card,
        .journal-entry-preview {
            border: 1px solid #d1d5db !important;
            box-shadow: none !important;
            background: #ffffff !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .panel-card {
            padding: 0 !important;
            border: 0 !important;
        }

        .print-sheet-header {
            display: block !important;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .print-sheet-header h2 {
            margin: 0 0 4px 0;
            font-size: 16pt;
            color: #0f172a;
        }

        .print-sheet-header p {
            margin: 0;
            font-size: 9.5pt;
            color: #334155;
            line-height: 1.45;
        }

        .journal-entry-preview {
            margin-bottom: 8px !important;
            padding: 10px !important;
        }

        .entry-details {
            display: block !important;
            border-top: 1px solid #d1d5db !important;
        }

        .entry-details summary {
            display: none !important;
        }

        details.entry-details > * {
            display: block !important;
        }

        details.entry-details > summary {
            display: none !important;
        }

        .entry-details-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 6px !important;
        }

        .entry-detail-block {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
        }
    }
</style>

<div class="journal-hero-banner" style="background:linear-gradient(90deg, #050505 0%, #12b3ac 40%, rgba(0, 0, 0, 0.38) 100%), url('/Skillhive/assets/media/element%203.png') right center / auto 100% no-repeat;border-radius:16px;padding:28px;margin-bottom:20px;color:white;display:flex;justify-content:space-between;align-items:center;gap:32px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(0, 0, 0, 0.44);">
    <div style="z-index:2;flex:1;">
        <h2 style="font-size:1.8rem;font-weight:900;margin:0 0 12px 0;line-height:1.2;color:white;">Student Journal Analytics</h2>
        <p style="font-size:0.95rem;margin:0;line-height:1.6;color:#e0e0e0;">Monitor student internship journal entries and progress. Only your assigned students are visible.</p>
    </div>
</div>

<script>
(function () {
    var activeSentimentFilters = new Set();

    function updateClearButton() {
        var clearButton = document.getElementById('clearSentimentFilters');
        if (!clearButton) {
            return;
        }
        clearButton.style.display = activeSentimentFilters.size > 0 ? 'inline-flex' : 'none';
    }

    function applySentimentFilters() {
        var entries = document.querySelectorAll('.journal-entry-preview[data-sentiment]');
        var emptyNode = document.getElementById('sentimentFilterEmpty');
        var visibleCount = 0;

        entries.forEach(function (entry) {
            var shouldShow = true;

            if (activeSentimentFilters.size > 0) {
                var sentiment = String(entry.getAttribute('data-sentiment') || 'neutral');
                var needsAction = String(entry.getAttribute('data-needs-action') || '0') === '1';
                var matches = false;

                activeSentimentFilters.forEach(function (filterKey) {
                    if (filterKey === 'action' && needsAction) {
                        matches = true;
                    }
                    if (filterKey === sentiment) {
                        matches = true;
                    }
                });

                shouldShow = matches;
            }

            entry.style.display = shouldShow ? '' : 'none';
            if (shouldShow) {
                visibleCount++;
            }
        });

        if (emptyNode) {
            emptyNode.style.display = (entries.length > 0 && visibleCount === 0) ? 'block' : 'none';
        }
    }

    window.toggleSentimentFilter = function (button) {
        if (!button) {
            return;
        }

        var filterKey = String(button.getAttribute('data-filter') || '');
        if (!filterKey) {
            return;
        }

        if (activeSentimentFilters.has(filterKey)) {
            activeSentimentFilters.delete(filterKey);
            button.classList.remove('active');
        } else {
            activeSentimentFilters.add(filterKey);
            button.classList.add('active');
        }

        updateClearButton();
        applySentimentFilters();
    };

    window.clearSentimentFilters = function () {
        activeSentimentFilters.clear();
        document.querySelectorAll('.sentiment-pill[data-filter]').forEach(function (button) {
            button.classList.remove('active');
        });

        updateClearButton();
        applySentimentFilters();
    };
})();
</script>

<div class="analytics-container">
    <div class="student-list">
        <div class="student-list-header">
            <i class="fas fa-users"></i> Your Students
        </div>

        <?php if (empty($students)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No assigned students found</p>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <?php
                $studentItemId = (int)($student['student_id'] ?? 0);
                $isActive = $studentItemId === $studentId;
                $studentHref = adviser_journal_analytics_url($baseUrl, $studentItemId, $sortBy);
                ?>
                <a class="student-item <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($studentHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="student-name">
                        <?php echo htmlspecialchars(((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="student-meta">
                        <span class="student-badge"><?php echo (int)($student['journal_count'] ?? 0); ?> entries</span>
                        <?php if (!empty($student['last_entry_date'])): ?>
                            <div style="margin-top: 4px; color: var(--text3); font-size: 0.8rem;">
                                Last: <?php echo date('M d', strtotime((string)$student['last_entry_date'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="analytics-main">
        <?php if (!$selectedStudent): ?>
            <div class="panel-card">
                <div class="no-student-message">
                    <div class="no-student-message-icon">
                        <i class="fas fa-hand-pointer"></i>
                    </div>
                    <h3>Select a Student</h3>
                    <p>Choose a student from the list to view journal analytics and entries.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="panel-card">
                <h3 class="student-profile-title">
                    <?php echo htmlspecialchars(((string)($selectedStudent['first_name'] ?? '') . ' ' . (string)($selectedStudent['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                </h3>

                <?php if ($studentStats): ?>
                    <p class="student-profile-subtitle">
                        <strong><?php echo htmlspecialchars((string)$studentStats['company'], ENT_QUOTES, 'UTF-8'); ?></strong> -
                        <?php echo htmlspecialchars((string)$studentStats['internship_title'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <?php
                    $hoursCompleted = (float)($studentStats['hours_completed'] ?? 0);
                    $hoursRequired = max(1.0, (float)($studentStats['hours_required'] ?? 0));
                    $progressPercent = min(100, ($hoursCompleted / $hoursRequired) * 100);
                    ?>
                    <div style="margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600;">Hours Progress</span>
                            <span style="color: var(--text3);">
                                <?php echo number_format($hoursCompleted, 1); ?> /
                                <?php echo number_format((float)($studentStats['hours_required'] ?? 0), 0); ?>
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo (float)$progressPercent; ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($studentStats): ?>
                <div class="stat-card-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (int)$studentStats['total_entries']; ?></div>
                        <div class="stat-label">Total Entries</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (int)$studentStats['unique_skills']; ?></div>
                        <div class="stat-label">Unique Skills</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (int)$studentStats['total_challenges']; ?></div>
                        <div class="stat-label">Challenges</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo (float)$studentStats['avg_daily_tasks']; ?></div>
                        <div class="stat-label">Avg Daily Tasks</div>
                    </div>
                </div>

                <?php
                $trendMax = 1;
                foreach ($entryTrendData as $trendPoint) {
                    $trendMax = max($trendMax, (int)($trendPoint['value'] ?? 0));
                }

                $categoryMax = 1;
                foreach ($entryCategoryData as $categoryPoint) {
                    $categoryMax = max($categoryMax, (int)($categoryPoint['value'] ?? 0));
                }
                ?>

                <div class="analytics-graph-grid">
                    <div class="analytics-graph-card">
                        <h4 class="analytics-graph-title">Entry Trend (Last 8 Weeks)</h4>
                        <?php if (empty($entryTrendData)): ?>
                            <div class="empty-state" style="padding: 14px 10px;">No trend data available yet.</div>
                        <?php else: ?>
                            <?php foreach ($entryTrendData as $trendPoint): ?>
                                <?php
                                $trendValue = (int)($trendPoint['value'] ?? 0);
                                $trendWidth = $trendMax > 0 ? ($trendValue / $trendMax) * 100 : 0;
                                ?>
                                <div class="graph-row">
                                    <div class="graph-label"><?php echo htmlspecialchars((string)($trendPoint['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="graph-track"><div class="graph-fill" style="width: <?php echo (float)$trendWidth; ?>%;"></div></div>
                                    <div class="graph-value"><?php echo $trendValue; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="analytics-graph-card">
                        <h4 class="analytics-graph-title">Activity Breakdown</h4>
                        <?php foreach ($entryCategoryData as $categoryPoint): ?>
                            <?php
                            $categoryValue = (int)($categoryPoint['value'] ?? 0);
                            $categoryWidth = $categoryMax > 0 ? ($categoryValue / $categoryMax) * 100 : 0;
                            ?>
                            <div class="graph-row">
                                <div class="graph-label"><?php echo htmlspecialchars((string)($categoryPoint['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="graph-track"><div class="graph-fill" style="width: <?php echo (float)$categoryWidth; ?>%;"></div></div>
                                <div class="graph-value"><?php echo $categoryValue; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($overallSentimentSummary): ?>
                <?php
                $needlePercent = (($overallSentimentSummary['avg_score'] + 1) / 2) * 100;
                $needlePercent = max(2, min(98, $needlePercent));
                $neutralCount = max(
                    0,
                    (int)($studentStats['total_entries'] ?? 0)
                    - (int)($overallSentimentSummary['positive_entries'] ?? 0)
                    - (int)($overallSentimentSummary['negative_entries'] ?? 0)
                );
                ?>
                <div class="sentiment-card">
                    <div class="sentiment-card-header">
                        <h4 class="sentiment-title">Journal Sentiment Overview</h4>
                        <span class="sentiment-overall-label">
                            <?php echo htmlspecialchars((string)$overallSentimentSummary['label'], ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo number_format((float)$overallSentimentSummary['avg_score'], 2); ?>)
                        </span>
                    </div>

                    <div class="sentiment-meter-track">
                        <div class="sentiment-meter-needle" style="left:<?php echo (float)$needlePercent; ?>%;"></div>
                    </div>
                    <div class="sentiment-meter-labels">
                        <span>Negative</span><span>Neutral</span><span>Positive</span>
                    </div>

                    <div class="sentiment-filter-row">
                        <button type="button" class="sentiment-pill" data-filter="positive" onclick="toggleSentimentFilter(this)">
                            <?php echo (int)$overallSentimentSummary['positive_entries']; ?> Positive
                        </button>
                        <button type="button" class="sentiment-pill" data-filter="neutral" onclick="toggleSentimentFilter(this)">
                            <?php echo $neutralCount; ?> Neutral
                        </button>
                        <button type="button" class="sentiment-pill" data-filter="negative" onclick="toggleSentimentFilter(this)">
                            <?php echo (int)$overallSentimentSummary['negative_entries']; ?> Negative
                        </button>
                        <?php if ((int)$overallSentimentSummary['needs_action_count'] > 0): ?>
                            <button type="button" class="sentiment-pill" data-filter="action" onclick="toggleSentimentFilter(this)">
                                <?php echo (int)$overallSentimentSummary['needs_action_count']; ?> Need Action
                            </button>
                        <?php endif; ?>
                        <button type="button" class="sentiment-pill" id="clearSentimentFilters" style="display:none;" onclick="clearSentimentFilters()">
                            Clear filters
                        </button>
                    </div>
                </div>

                <?php if ((int)$overallSentimentSummary['needs_action_count'] > 0): ?>
                    <div class="sentiment-alert">
                        <p class="sentiment-alert-title">Immediate Adviser Action Recommended</p>
                        <p>
                            <?php echo (int)$overallSentimentSummary['needs_action_count']; ?>
                            <?php echo (int)$overallSentimentSummary['needs_action_count'] === 1 ? 'entry requires attention.' : 'entries require attention.'; ?>
                            Please review flagged journals and follow up with the student.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="panel-card">
                <?php if ($studentStats): ?>
                    <div class="print-sheet-header">
                        <h2>Student Journal Record</h2>
                        <p>
                            Student: <?php echo htmlspecialchars(((string)($selectedStudent['first_name'] ?? '') . ' ' . (string)($selectedStudent['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> |
                            Company: <?php echo htmlspecialchars((string)$studentStats['company'], ENT_QUOTES, 'UTF-8'); ?> |
                            Position: <?php echo htmlspecialchars((string)$studentStats['internship_title'], ENT_QUOTES, 'UTF-8'); ?> |
                            Entries: <?php echo (int)$studentStats['total_entries']; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="panel-card-header">
                    <h3>Journal Entries</h3>
                    <div class="journal-entry-controls">
                        <select
                            class="sort-select"
                            onchange="window.location.href='<?php echo htmlspecialchars($sortChangeBaseUrl, ENT_QUOTES, 'UTF-8'); ?>&sort_by=' + encodeURIComponent(this.value)">
                            <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                        <button class="print-btn" type="button" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Print Journal
                        </button>
                    </div>
                </div>

                <?php if (empty($journalEntries)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book empty-state-icon"></i>
                        <p>No journal entries found for this student</p>
                    </div>
                <?php else: ?>
                    <div id="sentimentFilterEmpty" class="empty-state" style="display:none;padding:14px 10px;">
                        No entries match the selected sentiment filter.
                    </div>
                    <?php foreach ($journalEntries as $entry): ?>
                        <?php
                        $entryTasks = is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [];
                        $entrySkills = is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [];
                        $entryChallenges = is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : [];
                        $entrySolutions = is_array($entry['solutions_actions_taken'] ?? null) ? $entry['solutions_actions_taken'] : [];
                        $entryInsights = is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : [];
                        $entryCompanyDepartment = trim((string)($entry['company_department'] ?? ''));
                        $entryReflection = trim((string)($entry['reflection'] ?? ''));
                        $entryId = (int)($entry['journal_id'] ?? 0);
                        $hasFullDetails =
                            !empty($entryTasks) ||
                            !empty($entrySkills) ||
                            !empty($entryChallenges) ||
                            !empty($entrySolutions) ||
                            !empty($entryInsights) ||
                            $entryCompanyDepartment !== '' ||
                            $entryReflection !== '';

                        $entrySentiment = $entrySentiments[$entryId] ?? null;
                        $entrySentLabel = (string)($entrySentiment['label'] ?? 'Neutral');
                        $entrySentScore = (float)($entrySentiment['score'] ?? 0.0);
                        $entryNeedsAction = (bool)($entrySentiment['needs_action'] ?? false);
                        $entrySentClass = match ($entrySentLabel) {
                            'Positive' => 'pos',
                            'Mostly Positive' => 'mpos',
                            'Mostly Negative' => 'mneg',
                            'Negative' => 'neg',
                            default => 'neu',
                        };
                        $entrySentIcon = in_array($entrySentLabel, ['Positive', 'Mostly Positive'], true)
                            ? 'fa-face-smile'
                            : (in_array($entrySentLabel, ['Negative', 'Mostly Negative'], true) ? 'fa-face-frown' : 'fa-face-meh');
                        $entrySentFilter = in_array($entrySentLabel, ['Positive', 'Mostly Positive'], true)
                            ? 'positive'
                            : (in_array($entrySentLabel, ['Negative', 'Mostly Negative'], true) ? 'negative' : 'neutral');
                        ?>
                        <div class="journal-entry-preview" data-sentiment="<?php echo htmlspecialchars($entrySentFilter, ENT_QUOTES, 'UTF-8'); ?>" data-needs-action="<?php echo $entryNeedsAction ? '1' : '0'; ?>">
                            <div class="entry-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('l, F j, Y', strtotime((string)$entry['entry_date'])); ?>
                            </div>

                            <div class="entry-preview-text">
                                <?php if (!empty($entryTasks)): ?>
                                    <strong>Tasks:</strong>
                                    <?php echo htmlspecialchars(implode(', ', array_slice($entryTasks, 0, 2)), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (count($entryTasks) > 2): ?>
                                        <em>and <?php echo count($entryTasks) - 2; ?> more</em>
                                    <?php endif; ?>
                                <?php else: ?>
                                    No tasks listed for this date.
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($entrySkills)): ?>
                                <div style="margin-top: 8px;">
                                    <?php foreach (array_slice($entrySkills, 0, 3) as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars((string)$skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($entrySkills) > 3): ?>
                                        <span class="skill-tag">+<?php echo count($entrySkills) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($entryQualityScores[$entryId]) && is_array($entryQualityScores[$entryId])): ?>
                                <?php $quality = $entryQualityScores[$entryId]; ?>
                                <div class="quality-badge quality-<?php echo strtolower((string)($quality['level'] ?? 'basic')); ?>">
                                    <i class="fas fa-star"></i>
                                    <?php echo htmlspecialchars((string)($quality['level'] ?? 'Basic'), ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo (int)($quality['overall'] ?? 0); ?>%)
                                </div>
                            <?php endif; ?>

                            <div class="entry-sentiment-badge <?php echo $entrySentClass; ?>">
                                <i class="fas <?php echo $entrySentIcon; ?>"></i>
                                <?php echo htmlspecialchars($entrySentLabel, ENT_QUOTES, 'UTF-8'); ?>
                                (<?php echo number_format($entrySentScore, 2); ?>)
                            </div>

                            <?php if ($entryNeedsAction): ?>
                                <div class="entry-action-alert">
                                    This entry includes negative or sensitive signals that may need follow-up.
                                    <?php if (!empty($entrySentiment['action_reasons']) && is_array($entrySentiment['action_reasons'])): ?>
                                        <ul>
                                            <?php foreach ($entrySentiment['action_reasons'] as $reason): ?>
                                                <li><?php echo htmlspecialchars((string)$reason, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasFullDetails): ?>
                                <details class="entry-details">
                                    <summary><i class="fas fa-eye"></i> View full journal details</summary>
                                    <div class="entry-details-grid">
                                        <?php if ($entryCompanyDepartment !== ''): ?>
                                            <div class="entry-detail-block">
                                                <h5>Company/Department</h5>
                                                <p><?php echo htmlspecialchars($entryCompanyDepartment, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entryTasks)): ?>
                                            <div class="entry-detail-block">
                                                <h5>Tasks Accomplished</h5>
                                                <ul>
                                                    <?php foreach ($entryTasks as $item): ?>
                                                        <li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entrySkills)): ?>
                                            <div class="entry-detail-block">
                                                <h5>Skills Applied/Learned</h5>
                                                <ul>
                                                    <?php foreach ($entrySkills as $item): ?>
                                                        <li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entryChallenges)): ?>
                                            <div class="entry-detail-block">
                                                <h5>Challenges Encountered</h5>
                                                <ul>
                                                    <?php foreach ($entryChallenges as $item): ?>
                                                        <li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entrySolutions)): ?>
                                            <div class="entry-detail-block">
                                                <h5>Solutions/Actions Taken</h5>
                                                <ul>
                                                    <?php foreach ($entrySolutions as $item): ?>
                                                        <li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entryInsights)): ?>
                                            <div class="entry-detail-block">
                                                <h5>Key Learnings/Insights</h5>
                                                <ul>
                                                    <?php foreach ($entryInsights as $item): ?>
                                                        <li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($entryReflection !== ''): ?>
                                            <div class="entry-detail-block">
                                                <h5>Reflection</h5>
                                                <p><?php echo htmlspecialchars($entryReflection, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
