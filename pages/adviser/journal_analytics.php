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

$students = [];
$selectedStudent = null;
$journalEntries = [];
$studentStats = null;
$entryQualityScores = [];

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
        LEFT JOIN ojt_journal_entries jje ON jje.record_id = o.record_id
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

            $entriesStmt = $pdo->prepare(
                'SELECT
                    journal_id,
                    entry_date,
                    tasks_accomplished,
                    skills_applied_learned,
                    challenges_encountered,
                    solutions_actions_taken,
                    key_learnings_insights
                 FROM ojt_journal_entries
                 WHERE record_id = :record_id
                 ' . $orderBySql . '
                 LIMIT 200'
            );
            $entriesStmt->execute([':record_id' => (int)$ojtRecord['record_id']]);
            $rawEntries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawEntries as $entry) {
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
    .analytics-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 24px;
        margin-top: 24px;
    }

    @media (max-width: 1200px) {
        .analytics-container {
            grid-template-columns: 1fr;
        }
    }

    .student-list {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .student-list-header {
        background: linear-gradient(135deg, #06B6D4, #0891B2);
        color: white;
        padding: 16px;
        font-weight: 600;
    }

    .student-item {
        display: block;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .student-item:hover {
        background: #f9fafb;
    }

    .student-item.active {
        background: #cffafe;
        border-left: 4px solid #06B6D4;
        padding-left: 12px;
    }

    .student-name {
        font-weight: 600;
        color: var(--text1);
    }

    .student-meta {
        font-size: 0.85rem;
        color: var(--text3);
        margin-top: 4px;
    }

    .student-badge {
        display: inline-block;
        background: #f0f9fc;
        color: #0891B2;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-right: 4px;
    }

    .analytics-main {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .stat-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 16px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--border);
        padding: 16px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #06B6D4;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.85rem;
        color: var(--text3);
        margin-top: 8px;
    }

    .panel-card {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--border);
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .panel-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        border-bottom: 2px solid var(--border);
        padding-bottom: 12px;
    }

    .panel-card-header h3 {
        font-size: 1.1rem;
        margin: 0;
        color: var(--text1);
    }

    .sort-select {
        padding: 6px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .journal-entry-preview {
        background: #f9fafb;
        border-left: 3px solid #06B6D4;
        padding: 16px;
        margin-bottom: 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .journal-entry-preview:hover {
        background: #f0f9fc;
        box-shadow: 0 2px 8px rgba(6, 182, 212, 0.1);
    }

    .entry-date {
        font-weight: 700;
        color: #0891B2;
        font-size: 0.95rem;
    }

    .entry-preview-text {
        color: var(--text2);
        font-size: 0.9rem;
        margin-top: 8px;
        line-height: 1.4;
    }

    .quality-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-top: 8px;
    }

    .quality-excellent {
        background: #dcfce7;
        color: #166534;
    }

    .quality-good {
        background: #dbeafe;
        color: #1e40af;
    }

    .quality-fair {
        background: #fef3c7;
        color: #92400e;
    }

    .quality-basic {
        background: #fecdd3;
        color: #9f1239;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text3);
    }

    .empty-state-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .skill-tag {
        display: inline-block;
        background: #f0f9fc;
        color: #0891B2;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        margin-right: 6px;
        margin-bottom: 6px;
    }

    .no-student-message {
        text-align: center;
        padding: 60px 20px;
    }

    .no-student-message-icon {
        font-size: 3rem;
        color: var(--text3);
        margin-bottom: 16px;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #06B6D4, #0891B2);
        border-radius: 4px;
    }
</style>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-chart-bar"></i> Student Journal Analytics</h2>
        <p class="page-subtitle">Monitor student internship journal entries and progress</p>
    </div>
</div>

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
                <h3 style="margin-top: 0;">
                    <?php echo htmlspecialchars(((string)($selectedStudent['first_name'] ?? '') . ' ' . (string)($selectedStudent['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                </h3>

                <?php if ($studentStats): ?>
                    <p style="color: var(--text3); margin: 8px 0 16px 0;">
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
            <?php endif; ?>

            <div class="panel-card">
                <div class="panel-card-header">
                    <h3>Journal Entries</h3>
                    <select
                        class="sort-select"
                        onchange="window.location.href='<?php echo htmlspecialchars($sortChangeBaseUrl, ENT_QUOTES, 'UTF-8'); ?>&sort_by=' + encodeURIComponent(this.value)">
                        <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>

                <?php if (empty($journalEntries)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book empty-state-icon"></i>
                        <p>No journal entries found for this student</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($journalEntries as $entry): ?>
                        <?php
                        $entryTasks = is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [];
                        $entrySkills = is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [];
                        $entryId = (int)($entry['journal_id'] ?? 0);
                        ?>
                        <div class="journal-entry-preview">
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
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
