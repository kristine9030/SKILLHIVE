<?php
/**
 * Journal Processing Endpoint
 * Handles generation of structured journal entries from raw student notes
 */
require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/journal_helper.php';
require_once __DIR__ . '/ojt_log_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify student authentication
$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($role !== 'student' || $userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');

// Available actions
if (!in_array($action, ['generate_entry', 'save_entry', 'load_entries', 'generate_report', 'load_report', 'export_entry_email', 'export_report_email'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

// Load (or auto-create) OJT record
$ojt_basic = ojt_get_or_create_record($pdo, $userId);
$ojt_record = null;
if ($ojt_basic) {
    $stmt = $pdo->prepare('
        SELECT o.* , e.company_name, i.title as internship_title
        FROM ojt_record o
        LEFT JOIN internship i ON i.internship_id = o.internship_id
        LEFT JOIN employer e ON e.employer_id = i.employer_id
        WHERE o.record_id = ?
        LIMIT 1
    ');
    $stmt->execute([(int) $ojt_basic['record_id']]);
    $ojt_record = $stmt->fetch(PDO::FETCH_ASSOC) ?: $ojt_basic;
}

if (!$ojt_record) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No active OJT record found']);
    exit;
}

// ======================== ACTION: GENERATE ENTRY ========================
if ($action === 'generate_entry') {
    $raw_notes = (string) ($_POST['raw_notes'] ?? '');
    
    if (empty($raw_notes)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Raw notes cannot be empty']);
        exit;
    }
    
    $result = journal_process_raw_notes($raw_notes, $ojt_record);
    
    if (!$result['ok']) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Processing failed']);
        exit;
    }
    
    $formatted = journal_format_entry_display($result['entry']);
    echo json_encode(['ok' => true, 'entry' => $formatted]);
    exit;
}

// ======================== ACTION: SAVE ENTRY ========================
if ($action === 'save_entry') {
    $entry_data = json_decode((string) ($_POST['entry_data'] ?? '{}'), true);
    
    if (!is_array($entry_data) || empty($entry_data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid entry data']);
        exit;
    }
    
    // Decode if needed
    if (isset($entry_data['tasks_accomplished']) && is_string($entry_data['tasks_accomplished'])) {
        $entry_data['tasks_accomplished'] = json_decode($entry_data['tasks_accomplished'], true) ?? [];
    }
    if (isset($entry_data['skills_applied_learned']) && is_string($entry_data['skills_applied_learned'])) {
        $entry_data['skills_applied_learned'] = json_decode($entry_data['skills_applied_learned'], true) ?? [];
    }
    if (isset($entry_data['challenges_encountered']) && is_string($entry_data['challenges_encountered'])) {
        $entry_data['challenges_encountered'] = json_decode($entry_data['challenges_encountered'], true) ?? [];
    }
    if (isset($entry_data['solutions_actions_taken']) && is_string($entry_data['solutions_actions_taken'])) {
        $entry_data['solutions_actions_taken'] = json_decode($entry_data['solutions_actions_taken'], true) ?? [];
    }
    if (isset($entry_data['key_learnings_insights']) && is_string($entry_data['key_learnings_insights'])) {
        $entry_data['key_learnings_insights'] = json_decode($entry_data['key_learnings_insights'], true) ?? [];
    }
    
    $log_ids = isset($_POST['log_ids']) ? array_map('intval', (array) $_POST['log_ids']) : [];
    
    $result = journal_save_entry($pdo, (int) $ojt_record['record_id'], $entry_data, $log_ids);
    echo json_encode($result);
    exit;
}

// ======================== ACTION: LOAD ENTRIES ========================
if ($action === 'load_entries') {
    $limit = (int) ($_POST['limit'] ?? 50);
    $limit = min(max($limit, 1), 100); // Limit between 1-100
    
    $entries = journal_load_entries($pdo, (int) $ojt_record['record_id'], $limit);
    
    echo json_encode([
        'ok' => true,
        'entries' => $entries,
        'count' => count($entries)
    ]);
    exit;
}

// ======================== ACTION: GENERATE REPORT ========================
if ($action === 'generate_report') {
    // Load all journal entries
    $stmt = $pdo->prepare('
        SELECT * FROM ojt_journal_entries 
        WHERE record_id = ? 
        ORDER BY entry_date ASC
    ');
    $stmt->execute([(int) $ojt_record['record_id']]);
    $journal_entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($journal_entries)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'No journal entries found to generate report']);
        exit;
    }
    
    // Decode JSON fields
    foreach ($journal_entries as &$entry) {
        $entry['tasks_accomplished'] = json_decode($entry['tasks_accomplished'] ?? '[]', true) ?? [];
        $entry['skills_applied_learned'] = json_decode($entry['skills_applied_learned'] ?? '[]', true) ?? [];
        $entry['challenges_encountered'] = json_decode($entry['challenges_encountered'] ?? '[]', true) ?? [];
        $entry['solutions_actions_taken'] = json_decode($entry['solutions_actions_taken'] ?? '[]', true) ?? [];
        $entry['key_learnings_insights'] = json_decode($entry['key_learnings_insights'] ?? '[]', true) ?? [];
    }
    
    $report = journal_generate_final_report($pdo, $ojt_record, $journal_entries);
    
    // Save report to database
    $stmt = $pdo->prepare('
        INSERT INTO ojt_final_reports 
        (record_id, internship_overview, key_responsibilities, skills_developed, 
         challenges_resolutions, contributions_achievements, personal_professional_growth, 
         conclusion_reflection, total_journal_entries, duration_days)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            internship_overview = VALUES(internship_overview),
            key_responsibilities = VALUES(key_responsibilities),
            skills_developed = VALUES(skills_developed),
            challenges_resolutions = VALUES(challenges_resolutions),
            contributions_achievements = VALUES(contributions_achievements),
            personal_professional_growth = VALUES(personal_professional_growth),
            conclusion_reflection = VALUES(conclusion_reflection),
            updated_at = NOW()
    ');
    
    $stmt->execute([
        (int) $ojt_record['record_id'],
        $report['internship_overview'] ?? '',
        $report['key_responsibilities'] ?? '',
        $report['skills_developed'] ?? '',
        $report['challenges_resolutions'] ?? '',
        $report['contributions_achievements'] ?? '',
        $report['personal_professional_growth'] ?? '',
        $report['conclusion_reflection'] ?? '',
        count($journal_entries),
        $report['duration_days'] ?? 0
    ]);
    
    echo json_encode(['ok' => true, 'report' => $report]);
    exit;
}

// ======================== ACTION: LOAD REPORT ========================
if ($action === 'load_report') {
    $stmt = $pdo->prepare('
        SELECT * FROM ojt_final_reports 
        WHERE record_id = ? 
        ORDER BY generated_at DESC 
        LIMIT 1
    ');
    $stmt->execute([(int) $ojt_record['record_id']]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'No report found']);
        exit;
    }
    
    echo json_encode(['ok' => true, 'report' => $report]);
    exit;
}

// ======================== ACTION: EXPORT ENTRY EMAIL ========================
if ($action === 'export_entry_email') {
    $journal_id = (int) ($_POST['journal_id'] ?? 0);
    $recipient_email = (string) ($_POST['recipient_email'] ?? '');
    
    if ($journal_id <= 0 || empty($recipient_email)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Journal ID and recipient email required']);
        exit;
    }
    
    // Validate email
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Load journal entry
    $stmt = $pdo->prepare('SELECT * FROM ojt_journal_entries WHERE journal_id = ? AND record_id = ?');
    $stmt->execute([$journal_id, (int) $ojt_record['record_id']]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entry) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Journal entry not found']);
        exit;
    }
    
    // Decode JSON
    $entry['tasks_accomplished'] = json_decode($entry['tasks_accomplished'] ?? '[]', true) ?? [];
    $entry['skills_applied_learned'] = json_decode($entry['skills_applied_learned'] ?? '[]', true) ?? [];
    $entry['challenges_encountered'] = json_decode($entry['challenges_encountered'] ?? '[]', true) ?? [];
    $entry['solutions_actions_taken'] = json_decode($entry['solutions_actions_taken'] ?? '[]', true) ?? [];
    $entry['key_learnings_insights'] = json_decode($entry['key_learnings_insights'] ?? '[]', true) ?? [];
    
    $result = journal_send_entry_email($ojt_record, $entry, $recipient_email);
    echo json_encode($result);
    exit;
}

// ======================== ACTION: EXPORT REPORT EMAIL ========================
if ($action === 'export_report_email') {
    $recipient_email = (string) ($_POST['recipient_email'] ?? '');
    
    if (empty($recipient_email)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Recipient email required']);
        exit;
    }
    
    // Validate email
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Load report
    $stmt = $pdo->prepare('
        SELECT * FROM ojt_final_reports 
        WHERE record_id = ? 
        ORDER BY generated_at DESC 
        LIMIT 1
    ');
    $stmt->execute([(int) $ojt_record['record_id']]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'No report found to export']);
        exit;
    }
    
    $result = journal_send_report_email($ojt_record, $report, $recipient_email);
    echo json_encode($result);
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Unknown action']);
exit;


// ======================== EMAIL HELPER FUNCTIONS ========================

/**
 * Send journal entry as email
 */
function journal_send_entry_email(array $ojt_record, array $entry, string $recipient_email): array
{
    // Load student info
    $student_email = $_SESSION['email'] ?? '';
    $student_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
    
    $subject = 'OJT Journal Entry - ' . date('F j, Y', strtotime($entry['entry_date']));
    
    // Build HTML email
    $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
    $html .= "<div style='max-width: 600px; margin: 0 auto;'>";
    $html .= "<h2 style='color: #0891B2; border-bottom: 2px solid #0891B2; padding-bottom: 10px;'>OJT Journal Entry</h2>";
    
    $html .= "<p><strong>Student:</strong> " . htmlspecialchars($student_name) . "</p>";
    $html .= "<p><strong>Date:</strong> " . date('F j, Y', strtotime($entry['entry_date'])) . "</p>";
    $html .= "<p><strong>Company/Department:</strong> " . htmlspecialchars($entry['company_department'] ?? '') . "</p>";
    
    if (!empty($entry['tasks_accomplished'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Tasks Accomplished</h3><ul>";
        foreach ($entry['tasks_accomplished'] as $task) {
            $html .= "<li>" . htmlspecialchars($task) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['skills_applied_learned'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Skills Applied/Learned</h3><ul>";
        foreach ($entry['skills_applied_learned'] as $skill) {
            $html .= "<li>" . htmlspecialchars($skill) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['challenges_encountered'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Challenges Encountered</h3><ul>";
        foreach ($entry['challenges_encountered'] as $challenge) {
            $html .= "<li>" . htmlspecialchars($challenge) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['solutions_actions_taken'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Solutions/Actions Taken</h3><ul>";
        foreach ($entry['solutions_actions_taken'] as $solution) {
            $html .= "<li>" . htmlspecialchars($solution) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['key_learnings_insights'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Key Learnings/Insights</h3><ul>";
        foreach ($entry['key_learnings_insights'] as $insight) {
            $html .= "<li>" . htmlspecialchars($insight) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['reflection'])) {
        $html .= "<h3 style='color: #0891B2; margin-top: 20px;'>Reflection</h3>";
        $html .= "<p>" . nl2br(htmlspecialchars($entry['reflection'])) . "</p>";
    }
    
    $html .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
    $html .= "<p style='font-size: 12px; color: #999;'>";
    $html .= "This email was automatically generated from the SkillHive OJT Journal Assistant.<br>";
    $html .= "Date: " . date('Y-m-d H:i:s') . "";
    $html .= "</p>";
    $html .= "</div></body></html>";
    
    // Send email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $student_email . "\r\n";
    
    $success = mail($recipient_email, $subject, $html, $headers);
    
    if ($success) {
        return [
            'ok' => true,
            'message' => 'Journal entry sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    } else {
        return [
            'ok' => false,
            'error' => 'Failed to send email. Please try again.'
        ];
    }
}

/**
 * Send final report as email
 */
function journal_send_report_email(array $ojt_record, array $report, string $recipient_email): array
{
    $student_email = $_SESSION['email'] ?? '';
    $student_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
    
    $subject = 'OJT Final Report - ' . htmlspecialchars($ojt_record['company_name'] ?? 'Internship');
    
    // Build HTML email
    $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
    $html .= "<div style='max-width: 800px; margin: 0 auto;'>";
    $html .= "<h1 style='color: #0891B2; border-bottom: 3px solid #0891B2; padding-bottom: 15px;'>Internship Final Report</h1>";
    
    $html .= "<p><strong>Student:</strong> " . htmlspecialchars($student_name) . "</p>";
    $html .= "<p><strong>Generated:</strong> " . date('F j, Y H:i:s') . "</p>";
    
    // Overview stats
    $html .= "<div style='background: #f0f9fc; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    $html .= "<h3 style='margin-top: 0;'>Program Statistics</h3>";
    $html .= "<p><strong>Duration:</strong> " . ($report['duration_days'] ?? 0) . " days</p>";
    $html .= "<p><strong>Journal Entries:</strong> " . ($report['total_journal_entries'] ?? 0) . " entries</p>";
    $html .= "<p><strong>Hours Completed:</strong> " . ($report['hours_completed'] ?? 0) . " / " . ($report['hours_required'] ?? 0) . "</p>";
    $html .= "</div>";
    
    // Report sections
    if (!empty($report['internship_overview'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Internship Overview</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['internship_overview'])) . "</p>";
    }
    
    if (!empty($report['key_responsibilities'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Key Responsibilities</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['key_responsibilities'])) . "</p>";
    }
    
    if (!empty($report['skills_developed'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Skills Developed</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['skills_developed'])) . "</p>";
    }
    
    if (!empty($report['challenges_resolutions'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Challenges and Resolutions</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['challenges_resolutions'])) . "</p>";
    }
    
    if (!empty($report['contributions_achievements'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Major Contributions and Achievements</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['contributions_achievements'])) . "</p>";
    }
    
    if (!empty($report['personal_professional_growth'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Personal and Professional Growth</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['personal_professional_growth'])) . "</p>";
    }
    
    if (!empty($report['conclusion_reflection'])) {
        $html .= "<h2 style='color: #0891B2; margin-top: 30px;'>Conclusion and Overall Reflection</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['conclusion_reflection'])) . "</p>";
    }
    
    $html .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
    $html .= "<p style='font-size: 12px; color: #999;'>";
    $html .= "This report was automatically generated from the SkillHive OJT Journal Assistant.<br>";
    $html .= "Date: " . date('Y-m-d H:i:s') . "";
    $html .= "</p>";
    $html .= "</div></body></html>";
    
    // Send email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $student_email . "\r\n";
    
    $success = mail($recipient_email, $subject, $html, $headers);
    
    if ($success) {
        return [
            'ok' => true,
            'message' => 'Report sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    } else {
        return [
            'ok' => false,
            'error' => 'Failed to send email. Please try again.'
        ];
    }
}


// ======================== HELPER FUNCTION ========================

/**
 * Generate final internship summary report
 */
function journal_generate_final_report(PDO $pdo, array $ojt_record, array $journal_entries): array
{
    $defaultRequiredHours = defined('SKILLHIVE_REQUIRED_OJT_HOURS') ? (float) SKILLHIVE_REQUIRED_OJT_HOURS : 500.00;
    $company_name = $ojt_record['company_name'] ?? 'N/A';
    $internship_title = $ojt_record['internship_title'] ?? 'Internship Position';
    $start_date = $ojt_record['start_date'] ?? date('Y-m-d');
    $hours_completed = (float) ($ojt_record['hours_completed'] ?? 0);
    $hours_required = (float) ($ojt_record['hours_required'] ?? $defaultRequiredHours);
    
    // Calculate duration
    $start = new DateTime($start_date);
    $today = new DateTime();
    $duration_days = $today->diff($start)->days;
    
    // Aggregate data from journal entries
    $all_tasks = [];
    $all_skills = [];
    $all_challenges = [];
    $all_solutions = [];
    $all_insights = [];
    
    foreach ($journal_entries as $entry) {
        $all_tasks = array_merge($all_tasks, $entry['tasks_accomplished'] ?? []);
        $all_skills = array_merge($all_skills, $entry['skills_applied_learned'] ?? []);
        $all_challenges = array_merge($all_challenges, $entry['challenges_encountered'] ?? []);
        $all_solutions = array_merge($all_solutions, $entry['solutions_actions_taken'] ?? []);
        $all_insights = array_merge($all_insights, $entry['key_learnings_insights'] ?? []);
    }
    
    // Remove duplicates and trim
    $all_tasks = array_unique(array_filter(array_map('trim', $all_tasks)));
    $all_skills = array_unique(array_filter(array_map('trim', $all_skills)));
    $all_challenges = array_unique(array_filter(array_map('trim', $all_challenges)));
    $all_solutions = array_unique(array_filter(array_map('trim', $all_solutions)));
    $all_insights = array_unique(array_filter(array_map('trim', $all_insights)));
    
    // Build report sections
    $internship_overview = "During my internship at {$company_name} from " . date('F j, Y', strtotime($start_date)) . " to " . date('F j, Y') . 
                          ", I served as a {$internship_title}. This " . round($duration_days / 30, 1) . "-month experience provided me with " .
                          "practical exposure to professional work environments, collaborative teamwork, and real-world problem-solving. " .
                          "I accumulated {$hours_completed} hours of hands-on experience out of the {$hours_required}-hour requirement.";
    
    $key_responsibilities = "My primary responsibilities included:\n";
    foreach (array_slice($all_tasks, 0, 10) as $idx => $task) {
        $key_responsibilities .= "\n• " . ucfirst($task);
    }
    
    $skills_developed = "Technical Skills:\n";
    $tech_skills = array_filter($all_skills, fn($s) => !in_array(strtolower($s), ['communication', 'leadership', 'problem solving', 'time management', 'adaptability']));
    foreach (array_slice(array_values($tech_skills), 0, 5) as $skill) {
        $skills_developed .= "• " . $skill . "\n";
    }
    $skills_developed .= "\nSoft Skills:\n";
    $soft_skills = array_filter($all_skills, fn($s) => in_array(strtolower($s), ['communication', 'leadership', 'problem solving', 'time management', 'adaptability']));
    foreach (array_slice(array_values($soft_skills), 0, 5) as $skill) {
        $skills_developed .= "• " . $skill . "\n";
    }
    
    $challenges_resolutions = "Throughout my internship, I encountered and resolved several challenges:\n";
    foreach (array_slice($all_challenges, 0, 5) as $idx => $challenge) {
        $solution = $all_solutions[$idx] ?? "I employed critical thinking and consulted with mentors.";
        $challenges_resolutions .= "\nChallenge: " . $challenge . "\nResolution: " . $solution . "\n";
    }
    
    $contributions_achievements = "Key contributions and achievements include:\n";
    $achievements = [
        "Successfully completed " . count($journal_entries) . " days of logged internship activities",
        "Accumulated " . $hours_completed . " hours of professional work experience",
        "Developed proficiency in " . count($tech_skills) . " technical areas",
        "Demonstrated strong soft skills in " . ($soft_skills ? implode(', ', array_slice($soft_skills, 0, 3)) : 'key workplace competencies'),
        "Applied learning from " . count($all_insights) . " significant insight points"
    ];
    foreach (array_slice($achievements, 0, 5) as $achievement) {
        $contributions_achievements .= "\n• " . $achievement;
    }
    
    $personal_professional_growth = "This internship has been instrumental in my professional development. " .
                                   "I've evolved from observing workplace operations to actively contributing to meaningful projects. " .
                                   "The collaborative environment fostered my ability to communicate effectively, adapt to new challenges, " .
                                   "and think strategically about problem-solving. I've gained not only technical competencies but also " .
                                   "invaluable insights into workplace dynamics, professional ethics, and personal responsibility.";
    
    $conclusion_reflection = "Reflecting on this internship journey, I recognize the extraordinary value of hands-on learning in a real-world context. " .
                            "The challenges I faced became opportunities for growth, and the accomplishments reinforced my career aspirations. " .
                            "I am profoundly grateful for the mentorship, support, and experiences provided by {$company_name}. " .
                            "Moving forward, I am committed to leveraging these skills and insights to drive continued success in my academic " .
                            "and professional endeavors. This internship has not only strengthened my technical capabilities but has also shaped " .
                            "my professional identity and ethical commitment to excellence.";
    
    return [
        'internship_overview' => $internship_overview,
        'key_responsibilities' => $key_responsibilities,
        'skills_developed' => $skills_developed,
        'challenges_resolutions' => $challenges_resolutions,
        'contributions_achievements' => $contributions_achievements,
        'personal_professional_growth' => $personal_professional_growth,
        'conclusion_reflection' => $conclusion_reflection,
        'duration_days' => $duration_days,
        'total_journal_entries' => count($journal_entries),
        'hours_completed' => $hours_completed,
        'hours_required' => $hours_required
    ];
}
