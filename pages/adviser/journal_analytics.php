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

if (!function_exists('adviser_journal_analyze_sentiment')) {
    /**
     * Analyzes the sentiment of a journal entry and returns a score, label, and urgency flag.
     * Returns: ['score' => float(-1 to 1), 'label' => string, 'tone' => string, 'needs_action' => bool, 'action_reasons' => array]
     */
    function adviser_journal_analyze_sentiment(array $entry): array
    {
        $positiveWords = [
            'accomplished', 'achieved', 'excited', 'happy', 'great', 'excellent', 'enjoyed',
            'productive', 'motivated', 'confident', 'proud', 'improved', 'successful',
            'learned', 'grateful', 'inspired', 'progress', 'effective', 'efficient',
            'helpful', 'rewarding', 'satisfied', 'positive', 'opportunity', 'growth',
            'innovative', 'creative', 'collaborative', 'teamwork', 'appreciation',
            'breakthrough', 'milestone', 'encouragement', 'gain', 'mastered', 'smooth',
        ];

        $negativeWords = [
            'difficult', 'stressed', 'overwhelmed', 'confused', 'frustrated', 'struggling',
            'failed', 'problem', 'issue', 'error', 'behind', 'unclear', 'lost', 'stuck',
            'worried', 'anxious', 'uncomfortable', 'exhausted', 'burnout', 'misunderstood',
            'conflict', 'unfair', 'difficult', 'neglected', 'pressured', 'demotivated',
            'ignored', 'bored', 'helpless', 'hopeless', 'tension', 'hostile',
        ];

        // Critical/urgent distress signals that warrant immediate adviser action
        $urgentFlags = [
            'harassed',
            'bullied',
            'discriminated',
            'unsafe',
            'threatened',
            'hostile',
            'abuse',
            'abusive',
            'quit',
            'quitting',
            'leave',
            'mental health',
            'breakdown',
            'burnout',
            'hopeless',
            'helpless',
            'crying',
            'cried',
            'panic',
            'anxious',
            'anxiety',
            'depressed',
            'depression',
        ];

        // Combine all text fields for analysis
        $allText = implode(' ', array_filter([
            $entry['reflection'] ?? '',
            implode(' ', is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : []),
            implode(' ', is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : []),
            implode(' ', is_array($entry['solutions_actions_taken'] ?? null) ? $entry['solutions_actions_taken'] : []),
            implode(' ', is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : []),
        ]));

        $textLower = strtolower($allText);

        $posCount = 0;
        $negCount = 0;
        foreach ($positiveWords as $word) {
            $posCount += substr_count($textLower, $word);
        }
        foreach ($negativeWords as $word) {
            $negCount += substr_count($textLower, $word);
        }

        $total = $posCount + $negCount;
        $score = $total > 0 ? ($posCount - $negCount) / $total : 0.0;

        // Determine label and tone
        if ($score >= 0.5) {
            $label = 'Positive';
            $tone  = 'The student appears engaged and optimistic.';
        } elseif ($score >= 0.1) {
            $label = 'Mostly Positive';
            $tone  = 'The student is generally doing well with minor challenges.';
        } elseif ($score >= -0.1) {
            $label = 'Neutral';
            $tone  = 'The student\'s entry reflects a balanced, matter-of-fact tone.';
        } elseif ($score >= -0.5) {
            $label = 'Mostly Negative';
            $tone  = 'The student shows signs of difficulty or frustration.';
        } else {
            $label = 'Negative';
            $tone  = 'The student may be experiencing significant stress or challenges.';
        }

        // Check urgent distress flags
        $actionReasons = [];
        foreach ($urgentFlags as $flag) {
            if (strpos($textLower, $flag) !== false) {
                $actionReasons[] = ucfirst($flag);
            }
        }

        // Auto-escalate if sentiment is very negative even without explicit keywords
        $needsAction = !empty($actionReasons) || $score <= -0.6;
        if ($score <= -0.6 && empty($actionReasons)) {
            $actionReasons[] = 'Highly negative entry detected — please review';
        }

        return [
            'score'          => round($score, 2),
            'label'          => $label,
            'tone'           => $tone,
            'needs_action'   => $needsAction,
            'action_reasons' => array_unique($actionReasons),
            'pos_count'      => $posCount,
            'neg_count'      => $negCount,
        ];
    }
}

$students = [];
$selectedStudent = null;
$journalEntries = [];
$studentStats = null;
$entryQualityScores = [];
$entrySentiments = [];
$journalVisibilityColumnExists = false;
$overallSentimentSummary = null;

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

            foreach ($journalEntries as $entry) {
                $skills = is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [];
                $challenges = is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : [];
                $solutions = is_array($entry['solutions_actions_taken'] ?? null) ? $entry['solutions_actions_taken'] : [];
                $tasks = is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [];

                $allSkills = array_merge($allSkills, $skills);
                $totalChallenges += count($challenges);
                $totalSolutions += count($solutions);
                $totalTasks += count($tasks);
            }

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

            // Compute per-entry sentiment
            if (function_exists('adviser_journal_analyze_sentiment')) {
                $totalSentimentScore = 0.0;
                $positiveCount = 0;
                $negativeCount = 0;
                $needsActionCount = 0;

                foreach ($journalEntries as $entry) {
                    $sentiment = adviser_journal_analyze_sentiment($entry);
                    $entrySentiments[(int)$entry['journal_id']] = $sentiment;

                    $totalSentimentScore += $sentiment['score'];
                    if ($sentiment['score'] >= 0.1) {
                        $positiveCount++;
                    } elseif ($sentiment['score'] <= -0.1) {
                        $negativeCount++;
                    }
                    if ($sentiment['needs_action']) {
                        $needsActionCount++;
                    }
                }

                $avgScore = $totalEntries > 0 ? $totalSentimentScore / $totalEntries : 0.0;
                $overallLabel = match (true) {
                    $avgScore >= 0.4   => 'Generally Positive',
                    $avgScore >= 0.05  => 'Mostly Positive',
                    $avgScore >= -0.05 => 'Mixed',
                    $avgScore >= -0.4  => 'Mostly Negative',
                    default            => 'Predominantly Negative',
                };

                $overallSentimentSummary = [
                    'avg_score'          => round($avgScore, 2),
                    'label'              => $overallLabel,
                    'positive_entries'   => $positiveCount,
                    'negative_entries'   => $negativeCount,
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
    /* ═══════════════════════════════════════════════
       LAYOUT
    ═══════════════════════════════════════════════ */
    .ja-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 20px;
        margin-top: 20px;
        align-items: start;
    }
    @media (max-width: 1200px) {
        .ja-container { grid-template-columns: 1fr; }
    }

    /* ═══════════════════════════════════════════════
       STUDENT SIDEBAR
    ═══════════════════════════════════════════════ */
    .ja-sidebar {
        background: var(--card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        overflow: hidden;
        position: sticky;
        top: 20px;
    }
    .ja-sidebar-head {
        background: var(--primary);
        color: #fff;
        padding: 14px 18px;
        font-size: .82rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ja-student-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        text-decoration: none;
        color: var(--text);
        transition: background var(--transition);
    }
    .ja-student-item:last-child { border-bottom: none; }
    .ja-student-item:hover { background: var(--bg-soft); }
    .ja-student-item.active {
        background: rgba(6,182,212,.07);
        border-left: 3px solid var(--secondary);
        padding-left: 13px;
    }
    .ja-student-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        font-size: .78rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .ja-student-item.active .ja-student-avatar {
        background: var(--secondary);
    }
    .ja-student-name {
        font-weight: 600;
        font-size: .88rem;
        color: var(--text);
        line-height: 1.3;
    }
    .ja-student-meta {
        font-size: .75rem;
        color: var(--text3);
        margin-top: 2px;
    }
    .ja-entry-badge {
        margin-left: auto;
        background: var(--bg-soft);
        border: 1px solid var(--border);
        color: var(--text2);
        padding: 2px 8px;
        border-radius: 50px;
        font-size: .72rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .ja-student-item.active .ja-entry-badge {
        background: rgba(6,182,212,.12);
        border-color: rgba(6,182,212,.3);
        color: var(--secondary);
    }

    /* ═══════════════════════════════════════════════
       MAIN AREA
    ═══════════════════════════════════════════════ */
    .ja-main { display: flex; flex-direction: column; gap: 16px; }

    /* ═══════════════════════════════════════════════
       STUDENT HEADER CARD
    ═══════════════════════════════════════════════ */
    .ja-student-card {
        background: var(--card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        padding: 20px 24px;
    }
    .ja-student-card-name {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 4px 0;
    }
    .ja-student-card-sub {
        font-size: .83rem;
        color: var(--text3);
    }
    .ja-progress-wrap { margin-top: 16px; }
    .ja-progress-label {
        display: flex;
        justify-content: space-between;
        font-size: .82rem;
        font-weight: 600;
        color: var(--text2);
        margin-bottom: 6px;
    }
    .ja-progress-bar {
        width: 100%;
        height: 6px;
        background: var(--border);
        border-radius: 99px;
        overflow: hidden;
    }
    .ja-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--secondary), #0284c7);
        border-radius: 99px;
        transition: width .5s ease;
    }

    /* ═══════════════════════════════════════════════
       STAT TILES
    ═══════════════════════════════════════════════ */
    .ja-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }
    @media (max-width: 900px) { .ja-stats-grid { grid-template-columns: repeat(2, 1fr); } }
    .ja-stat-tile {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 16px 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        transition: box-shadow var(--transition), transform var(--transition);
    }
    .ja-stat-tile:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,.08);
        transform: translateY(-2px);
    }
    .ja-stat-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .9rem;
        margin-bottom: 4px;
    }
    .ja-stat-icon.cyan    { background: rgba(6,182,212,.1);  color: var(--secondary); }
    .ja-stat-icon.amber   { background: rgba(245,158,11,.1); color: var(--accent); }
    .ja-stat-icon.green   { background: rgba(16,185,129,.1); color: var(--accent2); }
    .ja-stat-icon.slate   { background: rgba(100,116,139,.1);color: #64748b; }
    .ja-stat-num {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }
    .ja-stat-lbl {
        font-size: .75rem;
        color: var(--text3);
        font-weight: 500;
    }

    /* ═══════════════════════════════════════════════
       GLOBAL ACTION BANNER
    ═══════════════════════════════════════════════ */
    .ja-action-banner {
        background: #fff1f2;
        border: 1px solid #fecaca;
        border-left: 4px solid var(--danger);
        border-radius: var(--radius-sm);
        padding: 14px 18px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .ja-action-banner-icon {
        width: 36px;
        height: 36px;
        background: rgba(239,68,68,.12);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--danger);
        font-size: 1rem;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .ja-action-banner h4 {
        margin: 0 0 3px 0;
        font-size: .9rem;
        font-weight: 700;
        color: #7f1d1d;
    }
    .ja-action-banner p {
        margin: 0;
        font-size: .82rem;
        color: #991b1b;
        line-height: 1.5;
    }

    /* ═══════════════════════════════════════════════
       SENTIMENT OVERVIEW CARD
    ═══════════════════════════════════════════════ */
    .ja-sentiment-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 22px;
    }
    .ja-sentiment-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .ja-sentiment-card-head h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ja-sentiment-card-head h3 i { color: var(--secondary); }

    /* overall label */
    .ja-overall-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 50px;
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .01em;
        border: 1.5px solid transparent;
    }
    .ja-overall-label.pos  { background: rgba(16,185,129,.1);  color: #065f46; border-color: rgba(16,185,129,.25); }
    .ja-overall-label.mpos { background: rgba(6,182,212,.08);  color: #0369a1; border-color: rgba(6,182,212,.2); }
    .ja-overall-label.neu  { background: var(--bg-soft);       color: var(--text2); border-color: var(--border); }
    .ja-overall-label.mneg { background: rgba(245,158,11,.1);  color: #92400e; border-color: rgba(245,158,11,.25); }
    .ja-overall-label.neg  { background: rgba(239,68,68,.1);   color: #991b1b; border-color: rgba(239,68,68,.25); }

    /* meter */
    .ja-meter-wrap { margin: 4px 0 2px 0; }
    .ja-meter-track {
        width: 100%;
        height: 8px;
        border-radius: 99px;
        background: linear-gradient(90deg,
            var(--danger) 0%,
            var(--accent) 40%,
            var(--accent2) 100%);
        position: relative;
    }
    .ja-meter-needle {
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 16px;
        height: 16px;
        background: #fff;
        border: 2px solid var(--primary);
        border-radius: 50%;
        box-shadow: 0 1px 4px rgba(0,0,0,.18);
        transition: left .4s ease;
    }
    .ja-meter-labels {
        display: flex;
        justify-content: space-between;
        font-size: .7rem;
        color: var(--text3);
        margin-top: 4px;
    }

    /* filter pills */
    .ja-filter-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }
    .ja-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 50px;
        font-size: .78rem;
        font-weight: 700;
        cursor: pointer;
        border: 1.5px solid transparent;
        transition: all var(--transition);
        user-select: none;
        outline: none;
        background: var(--bg-soft);
        color: var(--text2);
        border-color: var(--border);
    }
    .ja-filter-pill .pill-x {
        display: none;
        font-size: .7rem;
        opacity: .7;
        margin-left: 2px;
    }
    .ja-filter-pill.active .pill-x { display: inline; }
    .ja-filter-pill.active .pill-default-icon { display: none; }

    /* pill colour themes */
    .ja-filter-pill.pill-positive { background: rgba(16,185,129,.08); color: #065f46; border-color: rgba(16,185,129,.22); }
    .ja-filter-pill.pill-positive:hover,
    .ja-filter-pill.pill-positive.active {
        background: var(--accent2); color: #fff; border-color: var(--accent2);
        box-shadow: 0 3px 10px rgba(16,185,129,.3);
    }
    .ja-filter-pill.pill-neutral { background: var(--bg-soft); color: var(--text2); border-color: var(--border); }
    .ja-filter-pill.pill-neutral:hover,
    .ja-filter-pill.pill-neutral.active {
        background: #64748b; color: #fff; border-color: #64748b;
        box-shadow: 0 3px 10px rgba(100,116,139,.3);
    }
    .ja-filter-pill.pill-negative { background: rgba(239,68,68,.08); color: #991b1b; border-color: rgba(239,68,68,.22); }
    .ja-filter-pill.pill-negative:hover,
    .ja-filter-pill.pill-negative.active {
        background: var(--danger); color: #fff; border-color: var(--danger);
        box-shadow: 0 3px 10px rgba(239,68,68,.3);
    }
    .ja-filter-pill.pill-action { background: rgba(245,158,11,.1); color: #92400e; border-color: rgba(245,158,11,.3); }
    .ja-filter-pill.pill-action:hover,
    .ja-filter-pill.pill-action.active {
        background: var(--accent); color: #fff; border-color: var(--accent);
        box-shadow: 0 3px 10px rgba(245,158,11,.3);
    }

    /* filter empty notice */
    .ja-filter-empty {
        text-align: center;
        padding: 32px 20px;
        color: var(--text3);
        display: none;
    }
    .ja-filter-empty i { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: .4; }

    /* ═══════════════════════════════════════════════
       JOURNAL ENTRIES PANEL
    ═══════════════════════════════════════════════ */
    .ja-panel {
        background: var(--card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        padding: 20px;
    }
    .ja-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
    }
    .ja-panel-head h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ja-panel-head h3 i { color: var(--secondary); }
    .ja-sort-select {
        padding: 6px 12px;
        border: 1px solid var(--border);
        border-radius: 50px;
        font-size: .8rem;
        font-weight: 600;
        font-family: var(--font-family-base);
        color: var(--text);
        background: var(--bg-soft);
        cursor: pointer;
        outline: none;
        transition: border-color var(--transition);
    }
    .ja-sort-select:focus { border-color: var(--secondary); }

    /* ═══════════════════════════════════════════════
       JOURNAL ENTRY CARD
    ═══════════════════════════════════════════════ */
    .ja-entry {
        background: var(--bg-soft);
        border: 1px solid var(--border);
        border-left: 3px solid var(--secondary);
        border-radius: var(--radius-sm);
        padding: 16px;
        margin-bottom: 10px;
        transition: box-shadow var(--transition), border-color var(--transition);
    }
    .ja-entry:last-child { margin-bottom: 0; }
    .ja-entry:hover {
        box-shadow: 0 4px 16px rgba(6,182,212,.1);
        border-color: rgba(6,182,212,.3);
    }
    .ja-entry[data-needs-action="1"] {
        border-left-color: var(--danger);
    }
    .ja-entry-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .ja-entry-date {
        font-size: .9rem;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .ja-entry-date i { color: var(--secondary); }
    .ja-entry-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .ja-entry-preview {
        font-size: .85rem;
        color: var(--text2);
        margin-top: 10px;
        line-height: 1.5;
    }
    .ja-skill-chips { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px; }
    .ja-skill-chip {
        background: rgba(6,182,212,.08);
        color: var(--secondary);
        border: 1px solid rgba(6,182,212,.2);
        padding: 2px 10px;
        border-radius: 50px;
        font-size: .73rem;
        font-weight: 600;
    }

    /* quality badge */
    .ja-quality {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: .72rem;
        font-weight: 700;
    }
    .ja-quality.excellent { background: rgba(16,185,129,.1);  color: #065f46; }
    .ja-quality.good      { background: rgba(6,182,212,.1);   color: #0369a1; }
    .ja-quality.fair      { background: rgba(245,158,11,.1);  color: #92400e; }
    .ja-quality.basic     { background: rgba(239,68,68,.08);  color: #991b1b; }
    .ja-quality.minimal   { background: var(--bg-soft);       color: var(--text3); }

    /* sentiment badge */
    .ja-sent-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: .72rem;
        font-weight: 700;
    }
    .ja-sent-badge.pos  { background: rgba(16,185,129,.1);  color: #065f46; }
    .ja-sent-badge.mpos { background: rgba(6,182,212,.1);   color: #0369a1; }
    .ja-sent-badge.neu  { background: var(--bg-soft); border: 1px solid var(--border); color: var(--text2); }
    .ja-sent-badge.mneg { background: rgba(245,158,11,.1);  color: #92400e; }
    .ja-sent-badge.neg  { background: rgba(239,68,68,.1);   color: #991b1b; }

    /* action alert */
    .ja-alert {
        background: #fff1f2;
        border: 1px solid #fecaca;
        border-left: 3px solid var(--danger);
        border-radius: var(--radius-sm);
        padding: 11px 14px;
        margin-top: 10px;
    }
    .ja-alert-head {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: .82rem;
        font-weight: 700;
        color: #b91c1c;
    }
    .ja-alert-body {
        font-size: .8rem;
        color: #7f1d1d;
        margin-top: 5px;
        line-height: 1.5;
    }
    .ja-alert-reasons {
        margin: 5px 0 0 0;
        padding-left: 14px;
        font-size: .78rem;
        color: #991b1b;
    }
    .ja-alert-reasons li { margin-bottom: 2px; }

    /* details expand */
    .ja-details {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed var(--border);
    }
    .ja-details summary {
        list-style: none;
        cursor: pointer;
        font-size: .82rem;
        font-weight: 700;
        color: var(--text2);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: color var(--transition);
    }
    .ja-details summary:hover { color: var(--secondary); }
    .ja-details summary::-webkit-details-marker { display: none; }
    .ja-details-grid { display: grid; gap: 10px; margin-top: 10px; }
    .ja-detail-block h5 {
        margin: 0 0 5px 0;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--secondary);
        font-weight: 700;
    }
    .ja-detail-block ul {
        margin: 0; padding-left: 16px;
        color: var(--text2); font-size: .84rem;
    }
    .ja-detail-block p {
        margin: 0; color: var(--text2);
        font-size: .84rem; white-space: pre-wrap; line-height: 1.5;
    }

    /* empty / no-student states */
    .ja-empty {
        text-align: center;
        padding: 40px 20px;
        color: var(--text3);
    }
    .ja-empty i { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .35; }
    .ja-no-student {
        text-align: center;
        padding: 56px 20px;
    }
    .ja-no-student i { font-size: 2.8rem; color: var(--text3); opacity: .4; display: block; margin-bottom: 12px; }
    .ja-no-student h3 { font-size: 1.05rem; color: var(--text); margin-bottom: 6px; }
    .ja-no-student p  { font-size: .85rem; color: var(--text3); }
</style>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-book-open" style="color:var(--secondary);"></i> Student Journal Analytics</h2>
        <p class="page-subtitle">Monitor internship journal entries and progress for your assigned students.</p>
    </div>
</div>

<div class="ja-container">
    <!-- ── Student Sidebar ── -->
    <div class="ja-sidebar">
        <div class="ja-sidebar-head">
            <i class="fas fa-users"></i> Your Students
        </div>

        <?php if (empty($students)): ?>
            <div class="ja-empty">
                <i class="fas fa-inbox"></i>
                <p>No assigned students found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <?php
                $studentItemId = (int)($student['student_id'] ?? 0);
                $isActive = $studentItemId === $studentId;
                $studentHref = adviser_journal_analytics_url($baseUrl, $studentItemId, $sortBy);
                $initials = strtoupper(
                    substr((string)($student['first_name'] ?? 'U'), 0, 1) .
                    substr((string)($student['last_name'] ?? ''), 0, 1)
                );
                ?>
                <a class="ja-student-item <?php echo $isActive ? 'active' : ''; ?>"
                   href="<?php echo htmlspecialchars($studentHref, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="ja-student-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="ja-student-name">
                            <?php echo htmlspecialchars(trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php if (!empty($student['last_entry_date'])): ?>
                            <div class="ja-student-meta">Last: <?php echo date('M d, Y', strtotime((string)$student['last_entry_date'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="ja-entry-badge"><?php echo (int)($student['journal_count'] ?? 0); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── Main Area ── -->
    <div class="ja-main">
        <?php if (!$selectedStudent): ?>
            <div class="ja-panel">
                <div class="ja-no-student">
                    <i class="fas fa-hand-pointer"></i>
                    <h3>Select a Student</h3>
                    <p>Choose a student from the list on the left to view their journal analytics and entries.</p>
                </div>
            </div>
        <?php else: ?>

            <!-- Student Header Card -->
            <div class="ja-student-card">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                        <p class="ja-student-card-name">
                            <?php echo htmlspecialchars(trim((string)($selectedStudent['first_name'] ?? '') . ' ' . (string)($selectedStudent['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php if ($studentStats): ?>
                            <p class="ja-student-card-sub">
                                <i class="fas fa-building" style="color:var(--secondary);margin-right:4px;"></i>
                                <?php echo htmlspecialchars((string)$studentStats['company'], ENT_QUOTES, 'UTF-8'); ?>
                                &nbsp;·&nbsp;
                                <?php echo htmlspecialchars((string)$studentStats['internship_title'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <span style="background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);color:var(--secondary);padding:4px 14px;border-radius:50px;font-size:.75rem;font-weight:700;">
                        <?php echo htmlspecialchars((string)($selectedStudent['program'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <?php if ($studentStats): ?>
                    <?php
                    $hoursCompleted = (float)($studentStats['hours_completed'] ?? 0);
                    $hoursRequired  = max(1.0, (float)($studentStats['hours_required'] ?? 0));
                    $progressPercent = min(100, ($hoursCompleted / $hoursRequired) * 100);
                    ?>
                    <div class="ja-progress-wrap">
                        <div class="ja-progress-label">
                            <span>OJT Hours Progress</span>
                            <span><?php echo number_format($hoursCompleted, 1); ?> / <?php echo number_format((float)($studentStats['hours_required'] ?? 0), 0); ?> hrs</span>
                        </div>
                        <div class="ja-progress-bar">
                            <div class="ja-progress-fill" style="width:<?php echo (float)$progressPercent; ?>%;"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stat Tiles -->
            <?php if ($studentStats): ?>
                <div class="ja-stats-grid">
                    <div class="ja-stat-tile">
                        <div class="ja-stat-icon cyan"><i class="fas fa-book"></i></div>
                        <div class="ja-stat-num"><?php echo (int)$studentStats['total_entries']; ?></div>
                        <div class="ja-stat-lbl">Total Entries</div>
                    </div>
                    <div class="ja-stat-tile">
                        <div class="ja-stat-icon green"><i class="fas fa-lightbulb"></i></div>
                        <div class="ja-stat-num"><?php echo (int)$studentStats['unique_skills']; ?></div>
                        <div class="ja-stat-lbl">Unique Skills</div>
                    </div>
                    <div class="ja-stat-tile">
                        <div class="ja-stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="ja-stat-num"><?php echo (int)$studentStats['total_challenges']; ?></div>
                        <div class="ja-stat-lbl">Challenges</div>
                    </div>
                    <div class="ja-stat-tile">
                        <div class="ja-stat-icon slate"><i class="fas fa-list-check"></i></div>
                        <div class="ja-stat-num"><?php echo number_format((float)$studentStats['avg_daily_tasks'], 1); ?></div>
                        <div class="ja-stat-lbl">Avg Daily Tasks</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Global Action Banner -->
            <?php if ($overallSentimentSummary && $overallSentimentSummary['needs_action_count'] > 0): ?>
                <div class="ja-action-banner" role="alert">
                    <div class="ja-action-banner-icon"><i class="fas fa-bell"></i></div>
                    <div>
                        <h4>Immediate Adviser Action Required</h4>
                        <p>
                            <?php echo (int)$overallSentimentSummary['needs_action_count']; ?>
                            <?php echo $overallSentimentSummary['needs_action_count'] === 1 ? 'journal entry' : 'journal entries'; ?>
                            contain distress signals or highly negative sentiment. Please review the flagged entries below and consider reaching out to this student promptly.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sentiment Overview Card -->
            <?php if ($overallSentimentSummary): ?>
                <?php
                $needlePercent  = (($overallSentimentSummary['avg_score'] + 1) / 2) * 100;
                $needlePercent  = max(2, min(98, $needlePercent));
                $overallLabel   = $overallSentimentSummary['label'];
                $overallClass   = match (true) {
                    str_contains($overallLabel, 'Generally Positive') => 'pos',
                    str_contains($overallLabel, 'Mostly Positive')    => 'mpos',
                    str_contains($overallLabel, 'Mixed')              => 'neu',
                    str_contains($overallLabel, 'Mostly Negative')    => 'mneg',
                    default                                            => 'neg',
                };
                $neutralCount = (int)$studentStats['total_entries']
                    - (int)$overallSentimentSummary['positive_entries']
                    - (int)$overallSentimentSummary['negative_entries'];
                ?>
                <div class="ja-sentiment-card">
                    <div class="ja-sentiment-card-head">
                        <h3><i class="fas fa-chart-line"></i> Journal Sentiment Overview</h3>
                        <span class="ja-overall-label <?php echo $overallClass; ?>">
                            <?php echo htmlspecialchars($overallLabel, ENT_QUOTES, 'UTF-8'); ?>
                            &nbsp;<span style="opacity:.6;">( <?php echo number_format($overallSentimentSummary['avg_score'], 2); ?> )</span>
                        </span>
                    </div>

                    <!-- Sentiment Meter -->
                    <div class="ja-meter-wrap">
                        <div class="ja-meter-track">
                            <div class="ja-meter-needle" style="left:<?php echo $needlePercent; ?>%;"></div>
                        </div>
                        <div class="ja-meter-labels">
                            <span>Negative</span><span>Neutral</span><span>Positive</span>
                        </div>
                    </div>

                    <!-- Filter Pills -->
                    <div class="ja-filter-row" id="sentimentFilterRow" style="align-items:center;justify-content:space-between;flex-wrap:wrap;">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button type="button"
                                    class="ja-filter-pill pill-positive"
                                    data-filter="positive"
                                    onclick="jaToggleFilter(this)">
                                <i class="fas fa-face-smile pill-default-icon"></i>
                                <i class="fas fa-xmark pill-x"></i>
                                <?php echo (int)$overallSentimentSummary['positive_entries']; ?> Positive
                            </button>
                            <button type="button"
                                    class="ja-filter-pill pill-neutral"
                                    data-filter="neutral"
                                    onclick="jaToggleFilter(this)">
                                <i class="fas fa-face-meh pill-default-icon"></i>
                                <i class="fas fa-xmark pill-x"></i>
                                <?php echo max(0, $neutralCount); ?> Neutral
                            </button>
                            <button type="button"
                                    class="ja-filter-pill pill-negative"
                                    data-filter="negative"
                                    onclick="jaToggleFilter(this)">
                                <i class="fas fa-face-frown pill-default-icon"></i>
                                <i class="fas fa-xmark pill-x"></i>
                                <?php echo (int)$overallSentimentSummary['negative_entries']; ?> Negative
                            </button>
                            <?php if ($overallSentimentSummary['needs_action_count'] > 0): ?>
                                <button type="button"
                                        class="ja-filter-pill pill-action"
                                        data-filter="action"
                                        onclick="jaToggleFilter(this)">
                                    <i class="fas fa-flag pill-default-icon"></i>
                                    <i class="fas fa-xmark pill-x"></i>
                                    <?php echo (int)$overallSentimentSummary['needs_action_count']; ?> Need Action
                                </button>
                            <?php endif; ?>
                        </div>
                        <button type="button"
                                id="jaClearFilters"
                                onclick="jaClearAllFilters()"
                                style="display:none;align-items:center;gap:6px;padding:5px 14px;border-radius:50px;font-size:.78rem;font-weight:700;cursor:pointer;background:transparent;color:var(--text2);border:1.5px solid var(--border);transition:all var(--transition);white-space:nowrap;">
                            <i class="fas fa-xmark"></i> Clear filters
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Journal Entries Panel -->
            <div class="ja-panel">
                <div class="ja-panel-head">
                    <h3><i class="fas fa-book-open"></i> Journal Entries</h3>
                    <select class="ja-sort-select"
                            onchange="window.location.href='<?php echo htmlspecialchars($sortChangeBaseUrl, ENT_QUOTES, 'UTF-8'); ?>&sort_by=' + encodeURIComponent(this.value)">
                        <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc"  <?php echo $sortBy === 'date_asc'  ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>

                <?php if (empty($journalEntries)): ?>
                    <div class="ja-empty">
                        <i class="fas fa-book"></i>
                        <p>No journal entries found for this student.</p>
                    </div>
                <?php else: ?>
                    <div id="ja-filter-empty" class="ja-filter-empty">
                        <i class="fas fa-filter"></i>
                        <p>No entries match the selected filter.</p>
                    </div>

                    <?php foreach ($journalEntries as $entry): ?>
                        <?php
                        $entryTasks      = is_array($entry['tasks_accomplished']     ?? null) ? $entry['tasks_accomplished']     : [];
                        $entrySkills     = is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [];
                        $entryChallenges = is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : [];
                        $entrySolutions  = is_array($entry['solutions_actions_taken']?? null) ? $entry['solutions_actions_taken'] : [];
                        $entryInsights   = is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : [];
                        $entryDept       = trim((string)($entry['company_department'] ?? ''));
                        $entryReflection = trim((string)($entry['reflection'] ?? ''));
                        $entryId         = (int)($entry['journal_id'] ?? 0);

                        $hasFullDetails = !empty($entryTasks) || !empty($entrySkills) || !empty($entryChallenges)
                            || !empty($entrySolutions) || !empty($entryInsights)
                            || $entryDept !== '' || $entryReflection !== '';

                        // Sentiment info for this entry
                        $esSentiment  = $entrySentiments[$entryId] ?? null;
                        $esLabel      = $esSentiment ? ($esSentiment['label'] ?? 'Neutral') : 'Neutral';
                        $esScore      = $esSentiment ? ($esSentiment['score'] ?? 0.0) : 0.0;
                        $esNeedsAction= $esSentiment ? (bool)($esSentiment['needs_action'] ?? false) : false;
                        $esSentClass  = match ($esLabel) {
                            'Positive'        => 'pos',
                            'Mostly Positive' => 'mpos',
                            'Mostly Negative' => 'mneg',
                            'Negative'        => 'neg',
                            default           => 'neu',
                        };
                        $esSentIcon   = in_array($esLabel, ['Positive', 'Mostly Positive']) ? 'fa-face-smile'
                            : (in_array($esLabel, ['Negative', 'Mostly Negative']) ? 'fa-face-frown' : 'fa-face-meh');

                        // data-sentiment attribute for JS filtering
                        $dsSentiment = match ($esLabel) {
                            'Positive', 'Mostly Positive' => 'positive',
                            'Negative', 'Mostly Negative' => 'negative',
                            default                       => 'neutral',
                        };

                        // Quality
                        $quality      = $entryQualityScores[$entryId] ?? null;
                        $qLevel       = $quality ? strtolower((string)($quality['level'] ?? 'minimal')) : null;
                        ?>
                        <div class="ja-entry"
                             data-sentiment="<?php echo htmlspecialchars($dsSentiment, ENT_QUOTES, 'UTF-8'); ?>"
                             data-needs-action="<?php echo $esNeedsAction ? '1' : '0'; ?>">

                            <div class="ja-entry-header">
                                <div class="ja-entry-date">
                                    <i class="fas fa-calendar-days"></i>
                                    <?php echo date('l, F j, Y', strtotime((string)$entry['entry_date'])); ?>
                                </div>
                                <div class="ja-entry-badges">
                                    <?php if ($quality): ?>
                                        <span class="ja-quality <?php echo $qLevel; ?>">
                                            <i class="fas fa-star"></i>
                                            <?php echo htmlspecialchars((string)($quality['level'] ?? 'Minimal'), ENT_QUOTES, 'UTF-8'); ?>
                                            (<?php echo (int)($quality['overall'] ?? 0); ?>%)
                                        </span>
                                    <?php endif; ?>
                                    <span class="ja-sent-badge <?php echo $esSentClass; ?>">
                                        <i class="fas <?php echo $esSentIcon; ?>"></i>
                                        <?php echo htmlspecialchars($esLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        <span style="opacity:.65;">(<?php echo number_format($esScore, 2); ?>)</span>
                                    </span>
                                </div>
                            </div>

                            <div class="ja-entry-preview">
                                <?php if (!empty($entryTasks)): ?>
                                    <strong>Tasks:</strong>
                                    <?php echo htmlspecialchars(implode(', ', array_slice($entryTasks, 0, 2)), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (count($entryTasks) > 2): ?>
                                        <em style="color:var(--text3);">+<?php echo count($entryTasks) - 2; ?> more</em>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text3);">No tasks listed for this date.</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($entrySkills)): ?>
                                <div class="ja-skill-chips">
                                    <?php foreach (array_slice($entrySkills, 0, 4) as $skill): ?>
                                        <span class="ja-skill-chip"><?php echo htmlspecialchars((string)$skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($entrySkills) > 4): ?>
                                        <span class="ja-skill-chip">+<?php echo count($entrySkills) - 4; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Immediate Action Alert -->
                            <?php if ($esNeedsAction): ?>
                                <div class="ja-alert">
                                    <div class="ja-alert-head">
                                        <i class="fas fa-triangle-exclamation"></i>
                                        Immediate Adviser Attention Required
                                    </div>
                                    <div class="ja-alert-body">
                                        This entry contains signals that may require follow-up. Consider reaching out to the student.
                                        <?php if (!empty($esSentiment['action_reasons'])): ?>
                                            <ul class="ja-alert-reasons">
                                                <?php foreach ($esSentiment['action_reasons'] as $reason): ?>
                                                    <li><?php echo htmlspecialchars((string)$reason, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Full Details -->
                            <?php if ($hasFullDetails): ?>
                                <details class="ja-details">
                                    <summary><i class="fas fa-chevron-right" style="font-size:.7rem;transition:transform .2s;"></i> View full entry details</summary>
                                    <div class="ja-details-grid">
                                        <?php if ($entryDept !== ''): ?>
                                            <div class="ja-detail-block">
                                                <h5>Company / Department</h5>
                                                <p><?php echo htmlspecialchars($entryDept, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entryTasks)): ?>
                                            <div class="ja-detail-block">
                                                <h5>Tasks Accomplished</h5>
                                                <ul><?php foreach ($entryTasks as $item): ?><li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entrySkills)): ?>
                                            <div class="ja-detail-block">
                                                <h5>Skills Applied / Learned</h5>
                                                <ul><?php foreach ($entrySkills as $item): ?><li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entryChallenges)): ?>
                                            <div class="ja-detail-block">
                                                <h5>Challenges Encountered</h5>
                                                <ul><?php foreach ($entryChallenges as $item): ?><li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entrySolutions)): ?>
                                            <div class="ja-detail-block">
                                                <h5>Solutions / Actions Taken</h5>
                                                <ul><?php foreach ($entrySolutions as $item): ?><li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($entryInsights)): ?>
                                            <div class="ja-detail-block">
                                                <h5>Key Learnings / Insights</h5>
                                                <ul><?php foreach ($entryInsights as $item): ?><li><?php echo htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($entryReflection !== ''): ?>
                                            <div class="ja-detail-block">
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

<script>
(function () {
    'use strict';

    /* active filters — now a Set, supports multiple selections */
    const activeFilters = new Set();

    window.jaToggleFilter = function (btn) {
        const filter = btn.dataset.filter;

        if (activeFilters.has(filter)) {
            activeFilters.delete(filter);
            btn.classList.remove('active');
        } else {
            activeFilters.add(filter);
            btn.classList.add('active');
        }

        updateClearButton();
        applyFilter();
    };

    window.jaClearAllFilters = function () {
        activeFilters.clear();
        document.querySelectorAll('.ja-filter-pill').forEach(function (p) {
            p.classList.remove('active');
        });
        updateClearButton();
        applyFilter();
    };

    function updateClearButton () {
        const btn = document.getElementById('jaClearFilters');
        if (!btn) return;
        btn.style.display = activeFilters.size > 0 ? 'inline-flex' : 'none';
    }

    function applyFilter () {
        const entries  = document.querySelectorAll('.ja-entry');
        const emptyMsg = document.getElementById('ja-filter-empty');
        let   visCount = 0;

        entries.forEach(function (entry) {
            let show = true;

            if (activeFilters.size > 0) {
                /* entry is shown if it matches ANY active filter */
                let matchesAny = false;
                activeFilters.forEach(function (filter) {
                    if (filter === 'action') {
                        if (entry.dataset.needsAction === '1') matchesAny = true;
                    } else {
                        if (entry.dataset.sentiment === filter) matchesAny = true;
                    }
                });
                show = matchesAny;
            }

            entry.style.display = show ? '' : 'none';
            if (show) visCount++;
        });

        if (emptyMsg) {
            emptyMsg.style.display = (visCount === 0 && activeFilters.size > 0) ? 'block' : 'none';
        }
    }

    /* chevron rotation on details open */
    document.addEventListener('toggle', function (e) {
        if (e.target && e.target.classList.contains('ja-details')) {
            const chevron = e.target.querySelector('summary i.fa-chevron-right');
            if (chevron) {
                chevron.style.transform = e.target.open ? 'rotate(90deg)' : '';
            }
        }
    }, true);
})();
</script>