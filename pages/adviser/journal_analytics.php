<?php
/**
 * Adviser Journal Analytics Dashboard
 * Allows advisers to monitor and view student journal entries and progress
 */
require_once __DIR__ . '/../../backend/db_connect.php';

// Verify adviser authentication
$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$adviserId = (int) ($_SESSION['adviser_id'] ?? $userId);

if ($role !== 'adviser' || $adviserId <= 0) {
    header('Location: /SkillHive/pages/auth/login.php');
    exit;
}

// Get filter parameters
$student_id = (int) ($_GET['student_id'] ?? 0);
$sort_by = (string) ($_GET['sort_by'] ?? 'date_desc');

// Load adviser's assigned students
$stmt = $pdo->prepare('
    SELECT DISTINCT
        s.student_id,
        s.first_name,
        s.last_name,
        s.email,
        s.program,
        COUNT(DISTINCT jje.journal_id) as journal_count,
        MAX(jje.entry_date) as last_entry_date
    FROM student s
    LEFT JOIN ojt_record o ON o.student_id = s.student_id
    LEFT JOIN ojt_journal_entries jje ON jje.record_id = o.record_id
    WHERE s.student_id IN (
        SELECT aa.student_id 
        FROM adviser_assignment aa 
        WHERE aa.adviser_id = ?
    )
    GROUP BY s.student_id, s.first_name, s.last_name, s.email, s.program
    ORDER BY s.first_name, s.last_name
');
$stmt->execute([$adviserId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// If student selected, load their journal entries
$selected_student = null;
$journal_entries = [];
$student_stats = null;
$entry_quality_scores = [];

if ($student_id > 0) {
    $stmt = $pdo->prepare('
        SELECT s.* FROM student s
        INNER JOIN adviser_assignment aa ON aa.student_id = s.student_id
        WHERE s.student_id = ? AND aa.adviser_id = ?
    ');
    $stmt->execute([$student_id, $adviserId]);
    $selected_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_student) {
        // Load OJT record
        $stmt = $pdo->prepare('
            SELECT o.*, e.company_name, i.title
            FROM ojt_record o
            LEFT JOIN internship i ON i.internship_id = o.internship_id
            LEFT JOIN employer e ON e.employer_id = i.employer_id
            WHERE o.student_id = ?
            ORDER BY o.record_id DESC
            LIMIT 1
        ');
        $stmt->execute([$student_id]);
        $ojt_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ojt_record) {
            // Load journal entries
            $order_by = match($sort_by) {
                'date_asc' => 'ORDER BY entry_date ASC',
                'date_desc' => 'ORDER BY entry_date DESC',
                default => 'ORDER BY entry_date DESC'
            };
            
            $stmt = $pdo->prepare("
                SELECT * FROM ojt_journal_entries 
                WHERE record_id = ? 
                $order_by
            ");
            $stmt->execute([(int) $ojt_record['record_id']]);
            $raw_entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Decode JSON fields
            foreach ($raw_entries as $entry) {
                $entry['tasks_accomplished'] = json_decode($entry['tasks_accomplished'] ?? '[]', true) ?? [];
                $entry['skills_applied_learned'] = json_decode($entry['skills_applied_learned'] ?? '[]', true) ?? [];
                $entry['challenges_encountered'] = json_decode($entry['challenges_encountered'] ?? '[]', true) ?? [];
                $entry['solutions_actions_taken'] = json_decode($entry['solutions_actions_taken'] ?? '[]', true) ?? [];
                $entry['key_learnings_insights'] = json_decode($entry['key_learnings_insights'] ?? '[]', true) ?? [];
                $journal_entries[] = $entry;
            }
            
            // Calculate student statistics
            $total_entries = count($journal_entries);
            $total_skills = [];
            $total_challenges = 0;
            $total_solutions = 0;
            
            foreach ($journal_entries as $entry) {
                $total_skills = array_merge($total_skills, $entry['skills_applied_learned'] ?? []);
                $total_challenges += count($entry['challenges_encountered'] ?? []);
                $total_solutions += count($entry['solutions_actions_taken'] ?? []);
            }
            
            $unique_skills = count(array_unique($total_skills));
            $avg_daily_tasks = $total_entries > 0 ? array_reduce($journal_entries, function($carry, $entry) {
                return $carry + count($entry['tasks_accomplished'] ?? []);
            }, 0) / $total_entries : 0;
            
            // Calculate entry quality scores
            if (!function_exists('journal_calculate_entry_quality')) {
                require_once __DIR__ . '/../student/ojt-log/journal_helper.php';
            }
            
            foreach ($journal_entries as $entry) {
                $quality = journal_calculate_entry_quality($entry, implode(' ', $entry['tasks_accomplished'] ?? []) . ' ' . implode(' ', $entry['key_learnings_insights'] ?? []));
                $entry_quality_scores[$entry['journal_id']] = $quality;
            }
            
            $student_stats = [
                'total_entries' => $total_entries,
                'unique_skills' => $unique_skills,
                'total_challenges' => $total_challenges,
                'total_solutions' => $total_solutions,
                'avg_daily_tasks' => round($avg_daily_tasks, 1),
                'hours_completed' => (float) ($ojt_record['hours_completed'] ?? 0),
                'hours_required' => (float) ($ojt_record['hours_required'] ?? 0),
                'company' => $ojt_record['company_name'] ?? 'N/A',
                'internship_title' => $ojt_record['title'] ?? 'N/A'
            ];
        }
    }
}

// Set base URL
if (!isset($baseUrl)) {
    $baseUrl = '/SkillHive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Journal Analytics - SkillHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/skillhive.css">
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
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
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
            cursor: pointer;
            transition: all 0.3s ease;
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
</head>
<body>
    <?php include(__DIR__ . '/../../layout.php'); ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h2 class="page-title"><i class="fas fa-chart-bar"></i> Student Journal Analytics</h2>
                <p class="page-subtitle">Monitor student internship journal entries and progress</p>
            </div>
        </div>

        <div class="analytics-container">
            <!-- Student List -->
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
                        <div class="student-item <?php echo ($student['student_id'] === $student_id) ? 'active' : ''; ?>"
                             onclick="window.location.href='?student_id=<?php echo $student['student_id']; ?>'">
                            <div class="student-name">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </div>
                            <div class="student-meta">
                                <span class="student-badge"><?php echo $student['journal_count']; ?> entries</span>
                                <?php if ($student['last_entry_date']): ?>
                                    <div style="margin-top: 4px; color: var(--text3); font-size: 0.8rem;">
                                        Last: <?php echo date('M d', strtotime($student['last_entry_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Analytics Main -->
            <div class="analytics-main">
                <?php if (!$selected_student): ?>
                    <div class="panel-card">
                        <div class="no-student-message">
                            <div class="no-student-message-icon">
                                <i class="fas fa-hand-pointer"></i>
                            </div>
                            <h3>Select a Student</h3>
                            <p>Choose a student from the list to view their journal analytics and entries</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Student Header -->
                    <div class="panel-card">
                        <h3 style="margin-top: 0;">
                            <?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?>
                        </h3>
                        <?php if ($student_stats): ?>
                            <p style="color: var(--text3); margin: 8px 0 16px 0;">
                                <strong><?php echo htmlspecialchars($student_stats['company']); ?></strong> — 
                                <?php echo htmlspecialchars($student_stats['internship_title']); ?>
                            </p>
                            
                            <!-- Hours Progress -->
                            <div style="margin-top: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-weight: 600;">Hours Progress</span>
                                    <span style="color: var(--text3);">
                                        <?php echo number_format($student_stats['hours_completed'], 1); ?> / 
                                        <?php echo number_format($student_stats['hours_required'], 0); ?>
                                    </span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min(100, ($student_stats['hours_completed'] / max(1, $student_stats['hours_required'])) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistics Grid -->
                    <?php if ($student_stats): ?>
                        <div class="stat-card-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $student_stats['total_entries']; ?></div>
                                <div class="stat-label">Total Entries</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $student_stats['unique_skills']; ?></div>
                                <div class="stat-label">Unique Skills</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $student_stats['total_challenges']; ?></div>
                                <div class="stat-label">Challenges</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $student_stats['avg_daily_tasks']; ?></div>
                                <div class="stat-label">Avg Daily Tasks</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Journal Entries -->
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h3>Journal Entries</h3>
                            <select class="sort-select" onchange="window.location.href='?student_id=<?php echo $student_id; ?>&sort_by=' + this.value">
                                <option value="date_desc" <?php echo ($sort_by === 'date_desc') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="date_asc" <?php echo ($sort_by === 'date_asc') ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>

                        <?php if (empty($journal_entries)): ?>
                            <div class="empty-state">
                                <i class="fas fa-book empty-state-icon"></i>
                                <p>No journal entries found for this student</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($journal_entries as $entry): ?>
                                <div class="journal-entry-preview">
                                    <div class="entry-date">
                                        <i class="fas fa-calendar"></i> <?php echo date('l, F j, Y', strtotime($entry['entry_date'])); ?>
                                    </div>
                                    
                                    <div class="entry-preview-text">
                                        <?php if (!empty($entry['tasks_accomplished'])): ?>
                                            <strong>Tasks:</strong> <?php echo htmlspecialchars(implode(', ', array_slice($entry['tasks_accomplished'], 0, 2))); ?>
                                            <?php if (count($entry['tasks_accomplished']) > 2): ?>
                                                <em>and <?php echo count($entry['tasks_accomplished']) - 2; ?> more</em>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($entry['skills_applied_learned'])): ?>
                                        <div style="margin-top: 8px;">
                                            <?php foreach (array_slice($entry['skills_applied_learned'], 0, 3) as $skill): ?>
                                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($entry['skills_applied_learned']) > 3): ?>
                                                <span class="skill-tag">+<?php echo count($entry['skills_applied_learned']) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($entry_quality_scores[$entry['journal_id']])): ?>
                                        <?php $quality = $entry_quality_scores[$entry['journal_id']]; ?>
                                        <div class="quality-badge quality-<?php echo strtolower($quality['level']); ?>">
                                            <i class="fas fa-star"></i>
                                            <?php echo $quality['level']; ?> (<?php echo $quality['overall']; ?>%)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
