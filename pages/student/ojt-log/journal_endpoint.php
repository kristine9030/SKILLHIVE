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

if ($action === 'export_entry_email' || $action === 'export_report_email') {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Email export is disabled. Your assigned adviser can view your journal directly in the system.'
    ]);
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
    if (($result['ok'] ?? false) !== true && !isset($result['message']) && isset($result['error'])) {
        $result['message'] = (string) $result['error'];
    }
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

function journal_email_api_url(): string
{
    $apiUrl = '';

    if (defined('SKILLHIVE_EMAIL_API_URL')) {
        $apiUrl = trim((string) constant('SKILLHIVE_EMAIL_API_URL'));
    }

    if ($apiUrl === '') {
        $apiUrl = trim((string) (getenv('SKILLHIVE_EMAIL_API_URL') ?: ''));
    }

    if ($apiUrl === '') {
        $apiUrl = 'http://127.0.0.1:3100/send-email';
    }

    return $apiUrl;
}

function journal_brevo_smtp_config(): array
{
    $login = '';
    $key = '';
    $fromEmail = '';

    if (defined('BREVO_SMTP_LOGIN')) {
        $login = trim((string) BREVO_SMTP_LOGIN);
    }
    if ($login === '') {
        $login = trim((string) (getenv('BREVO_SMTP_LOGIN') ?: ''));
    }

    if (defined('BREVO_SMTP_KEY')) {
        $key = trim((string) BREVO_SMTP_KEY);
    }
    if ($key === '') {
        $key = trim((string) (getenv('BREVO_SMTP_KEY') ?: ''));
    }

    if (defined('BREVO_FROM_EMAIL')) {
        $fromEmail = trim((string) BREVO_FROM_EMAIL);
    }
    if ($fromEmail === '') {
        $fromEmail = trim((string) (getenv('BREVO_FROM_EMAIL') ?: ''));
    }
    if ($fromEmail === '') {
        $fromEmail = $login;
    }

    return [
        'enabled' => ($login !== '' && $key !== ''),
        'login' => $login,
        'key' => $key,
        'from_email' => $fromEmail,
    ];
}

function journal_send_via_email_api(string $recipientEmail, string $recipientName, string $subject, string $message): array
{
    $payload = [
        'studentEmail' => trim($recipientEmail),
        'studentName' => trim($recipientName) !== '' ? trim($recipientName) : 'Recipient',
        'subject' => trim($subject),
        'message' => trim($message),
    ];

    $brevoConfig = journal_brevo_smtp_config();
    if (!empty($brevoConfig['enabled'])) {
        $payload['provider'] = 'brevo';
        $payload['smtpLogin'] = (string) $brevoConfig['login'];
        $payload['smtpKey'] = (string) $brevoConfig['key'];
        $payload['fromEmail'] = (string) $brevoConfig['from_email'];
    }

    $body = json_encode($payload);
    if (!is_string($body)) {
        return ['ok' => false, 'error' => 'Unable to prepare email payload.'];
    }

    $apiUrl = journal_email_api_url();

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Unable to initialize email API request.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);

        $responseRaw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'error' => 'Email API is unreachable: ' . $curlError];
        }

        $decoded = is_string($responseRaw) ? json_decode($responseRaw, true) : null;
        if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
            return ['ok' => true, 'error' => ''];
        }

        $messageText = is_array($decoded) && !empty($decoded['error'])
            ? (string) $decoded['error']
            : 'Email API returned HTTP ' . $statusCode . '.';

        return ['ok' => false, 'error' => $messageText];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);

    $responseRaw = @file_get_contents($apiUrl, false, $context);
    if (!is_string($responseRaw)) {
        return ['ok' => false, 'error' => 'Email API is unreachable.'];
    }

    $statusCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/[0-9.]+\s+(\d+)/i', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = json_decode($responseRaw, true);
    if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded) && !empty($decoded['ok'])) {
        return ['ok' => true, 'error' => ''];
    }

    $messageText = is_array($decoded) && !empty($decoded['error'])
        ? (string) $decoded['error']
        : 'Email API request failed.';

    return ['ok' => false, 'error' => $messageText];
}

/**
 * Send journal entry as email
 */
function journal_send_entry_email(array $ojt_record, array $entry, string $recipient_email): array
{
    // Load student info
    $student_email = $_SESSION['email'] ?? '';
    $student_name = trim((($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
    if ($student_name === '') {
        $student_name = 'Student';
    }
    $recipient_name = trim((string) strstr($recipient_email, '@', true));
    if ($recipient_name === '') {
        $recipient_name = 'Recipient';
    }
    
    $subject = 'OJT Journal Entry - ' . date('F j, Y', strtotime($entry['entry_date']));

    $apiMessageLines = [
        'OJT Journal Entry',
        'Student: ' . $student_name,
        'Date: ' . date('F j, Y', strtotime($entry['entry_date'])),
        'Company/Department: ' . (string) ($entry['company_department'] ?? ''),
    ];

    $entrySections = [
        'Tasks Accomplished' => is_array($entry['tasks_accomplished'] ?? null) ? $entry['tasks_accomplished'] : [],
        'Skills Applied/Learned' => is_array($entry['skills_applied_learned'] ?? null) ? $entry['skills_applied_learned'] : [],
        'Challenges Encountered' => is_array($entry['challenges_encountered'] ?? null) ? $entry['challenges_encountered'] : [],
        'Solutions/Actions Taken' => is_array($entry['solutions_actions_taken'] ?? null) ? $entry['solutions_actions_taken'] : [],
        'Key Learnings/Insights' => is_array($entry['key_learnings_insights'] ?? null) ? $entry['key_learnings_insights'] : [],
    ];

    foreach ($entrySections as $label => $items) {
        if (empty($items)) {
            continue;
        }
        $apiMessageLines[] = '';
        $apiMessageLines[] = $label . ':';
        foreach ($items as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $apiMessageLines[] = '- ' . $line;
            }
        }
    }

    $reflectionText = trim((string) ($entry['reflection'] ?? ''));
    if ($reflectionText !== '') {
        $apiMessageLines[] = '';
        $apiMessageLines[] = 'Reflection:';
        $apiMessageLines[] = $reflectionText;
    }

    $apiResult = journal_send_via_email_api($recipient_email, $recipient_name, $subject, implode("\n", $apiMessageLines));
    if (($apiResult['ok'] ?? false) === true) {
        return [
            'ok' => true,
            'message' => 'Journal entry sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    }
    
    // Build HTML email
    $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
    $html .= "<div style='max-width: 600px; margin: 0 auto;'>";
    $html .= "<h2 style='color: #12b3ac; border-bottom: 2px solid #12b3ac; padding-bottom: 10px;'>OJT Journal Entry</h2>";
    
    $html .= "<p><strong>Student:</strong> " . htmlspecialchars($student_name) . "</p>";
    $html .= "<p><strong>Date:</strong> " . date('F j, Y', strtotime($entry['entry_date'])) . "</p>";
    $html .= "<p><strong>Company/Department:</strong> " . htmlspecialchars($entry['company_department'] ?? '') . "</p>";
    
    if (!empty($entry['tasks_accomplished'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Tasks Accomplished</h3><ul>";
        foreach ($entry['tasks_accomplished'] as $task) {
            $html .= "<li>" . htmlspecialchars($task) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['skills_applied_learned'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Skills Applied/Learned</h3><ul>";
        foreach ($entry['skills_applied_learned'] as $skill) {
            $html .= "<li>" . htmlspecialchars($skill) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['challenges_encountered'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Challenges Encountered</h3><ul>";
        foreach ($entry['challenges_encountered'] as $challenge) {
            $html .= "<li>" . htmlspecialchars($challenge) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['solutions_actions_taken'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Solutions/Actions Taken</h3><ul>";
        foreach ($entry['solutions_actions_taken'] as $solution) {
            $html .= "<li>" . htmlspecialchars($solution) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['key_learnings_insights'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Key Learnings/Insights</h3><ul>";
        foreach ($entry['key_learnings_insights'] as $insight) {
            $html .= "<li>" . htmlspecialchars($insight) . "</li>";
        }
        $html .= "</ul>";
    }
    
    if (!empty($entry['reflection'])) {
        $html .= "<h3 style='color: #12b3ac; margin-top: 20px;'>Reflection</h3>";
        $html .= "<p>" . nl2br(htmlspecialchars($entry['reflection'])) . "</p>";
    }
    
    $html .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
    $html .= "<p style='font-size: 12px; color: #999;'>";
    $html .= "This email was automatically generated from the SkillHive OJT Journal Assistant.<br>";
    $html .= "Date: " . date('Y-m-d H:i:s') . "";
    $html .= "</p>";
    $html .= "</div></body></html>";
    
    // Send email
    $fallbackFrom = trim((string) $student_email);
    if (!filter_var($fallbackFrom, FILTER_VALIDATE_EMAIL)) {
        $brevoConfig = journal_brevo_smtp_config();
        $fallbackFrom = trim((string) ($brevoConfig['from_email'] ?? ''));
    }
    if (!filter_var($fallbackFrom, FILTER_VALIDATE_EMAIL)) {
        $fallbackFrom = 'no-reply@skillhive.local';
    }

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $fallbackFrom . "\r\n";
    
    $success = @mail($recipient_email, $subject, $html, $headers);
    
    if ($success) {
        return [
            'ok' => true,
            'message' => 'Journal entry sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    } else {
        $apiError = trim((string) ($apiResult['error'] ?? ''));
        $errorMessage = $apiError;
        if ($errorMessage === '') {
            $errorMessage = 'Please try again.';
        }
        if (stripos($errorMessage, 'failed to send email') === false) {
            $errorMessage = 'Failed to send email. ' . $errorMessage;
        }
        return [
            'ok' => false,
            'error' => $errorMessage
        ];
    }
}

/**
 * Send final report as email
 */
function journal_send_report_email(array $ojt_record, array $report, string $recipient_email): array
{
    $student_email = $_SESSION['email'] ?? '';
    $student_name = trim((($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
    if ($student_name === '') {
        $student_name = 'Student';
    }
    $recipient_name = trim((string) strstr($recipient_email, '@', true));
    if ($recipient_name === '') {
        $recipient_name = 'Recipient';
    }
    
    $subject = 'OJT Final Report - ' . htmlspecialchars($ojt_record['company_name'] ?? 'Internship');

    $apiMessageLines = [
        'Internship Final Report',
        'Student: ' . $student_name,
        'Generated: ' . date('F j, Y H:i:s'),
        'Company: ' . (string) ($ojt_record['company_name'] ?? ''),
        'Duration (days): ' . (string) ($report['duration_days'] ?? 0),
        'Journal Entries: ' . (string) ($report['total_journal_entries'] ?? 0),
        'Hours Completed: ' . (string) ($report['hours_completed'] ?? 0) . ' / ' . (string) ($report['hours_required'] ?? 0),
    ];

    $reportSections = [
        'Internship Overview' => (string) ($report['internship_overview'] ?? ''),
        'Key Responsibilities' => (string) ($report['key_responsibilities'] ?? ''),
        'Skills Developed' => (string) ($report['skills_developed'] ?? ''),
        'Challenges and Resolutions' => (string) ($report['challenges_resolutions'] ?? ''),
        'Major Contributions and Achievements' => (string) ($report['contributions_achievements'] ?? ''),
        'Personal and Professional Growth' => (string) ($report['personal_professional_growth'] ?? ''),
        'Conclusion and Overall Reflection' => (string) ($report['conclusion_reflection'] ?? ''),
    ];

    foreach ($reportSections as $label => $text) {
        $cleanText = trim($text);
        if ($cleanText === '') {
            continue;
        }
        $apiMessageLines[] = '';
        $apiMessageLines[] = $label . ':';
        $apiMessageLines[] = $cleanText;
    }

    $apiResult = journal_send_via_email_api($recipient_email, $recipient_name, $subject, implode("\n", $apiMessageLines));
    if (($apiResult['ok'] ?? false) === true) {
        return [
            'ok' => true,
            'message' => 'Report sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    }
    
    // Build HTML email
    $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
    $html .= "<div style='max-width: 800px; margin: 0 auto;'>";
    $html .= "<h1 style='color: #12b3ac; border-bottom: 3px solid #12b3ac; padding-bottom: 15px;'>Internship Final Report</h1>";
    
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
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Internship Overview</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['internship_overview'])) . "</p>";
    }
    
    if (!empty($report['key_responsibilities'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Key Responsibilities</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['key_responsibilities'])) . "</p>";
    }
    
    if (!empty($report['skills_developed'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Skills Developed</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['skills_developed'])) . "</p>";
    }
    
    if (!empty($report['challenges_resolutions'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Challenges and Resolutions</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['challenges_resolutions'])) . "</p>";
    }
    
    if (!empty($report['contributions_achievements'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Major Contributions and Achievements</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['contributions_achievements'])) . "</p>";
    }
    
    if (!empty($report['personal_professional_growth'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Personal and Professional Growth</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['personal_professional_growth'])) . "</p>";
    }
    
    if (!empty($report['conclusion_reflection'])) {
        $html .= "<h2 style='color: #12b3ac; margin-top: 30px;'>Conclusion and Overall Reflection</h2>";
        $html .= "<p>" . nl2br(htmlspecialchars($report['conclusion_reflection'])) . "</p>";
    }
    
    $html .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
    $html .= "<p style='font-size: 12px; color: #999;'>";
    $html .= "This report was automatically generated from the SkillHive OJT Journal Assistant.<br>";
    $html .= "Date: " . date('Y-m-d H:i:s') . "";
    $html .= "</p>";
    $html .= "</div></body></html>";
    
    // Send email
    $fallbackFrom = trim((string) $student_email);
    if (!filter_var($fallbackFrom, FILTER_VALIDATE_EMAIL)) {
        $brevoConfig = journal_brevo_smtp_config();
        $fallbackFrom = trim((string) ($brevoConfig['from_email'] ?? ''));
    }
    if (!filter_var($fallbackFrom, FILTER_VALIDATE_EMAIL)) {
        $fallbackFrom = 'no-reply@skillhive.local';
    }

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $fallbackFrom . "\r\n";
    
    $success = @mail($recipient_email, $subject, $html, $headers);
    
    if ($success) {
        return [
            'ok' => true,
            'message' => 'Report sent successfully to ' . htmlspecialchars($recipient_email)
        ];
    } else {
        $apiError = trim((string) ($apiResult['error'] ?? ''));
        $errorMessage = $apiError;
        if ($errorMessage === '') {
            $errorMessage = 'Please try again.';
        }
        if (stripos($errorMessage, 'failed to send email') === false) {
            $errorMessage = 'Failed to send email. ' . $errorMessage;
        }
        return [
            'ok' => false,
            'error' => $errorMessage
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
