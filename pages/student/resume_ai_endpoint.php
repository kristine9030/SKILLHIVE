<?php
require_once __DIR__ . '/../../backend/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($role !== 'student' || $userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['analyze', 'chat', 'import_linkedin_cv', 'build_cv', 'load_cv', 'save_cv', 'score_cv'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

function resume_ai_extract_text(string $filePath): string
{
    if (!is_file($filePath)) {
        return '';
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'txt') {
        return trim((string) file_get_contents($filePath));
    }

    if ($ext === 'docx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml !== '') {
                $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml);
                $text = strip_tags((string) $xml);
                return trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
    }

    if ($ext === 'pdf') {
        $raw = (string) file_get_contents($filePath);
        $raw = preg_replace('/\s+/', ' ', $raw);
        $raw = preg_replace('/[^\x20-\x7E]/', ' ', (string) $raw);
        return trim((string) $raw);
    }

    return '';
}

function resume_ai_fallback_analysis(string $resumeText): array
{
    $len = strlen($resumeText);
    $base = 55;
    if ($len > 1500) {
        $base += 20;
    } elseif ($len > 700) {
        $base += 12;
    } elseif ($len > 300) {
        $base += 6;
    }

    $keywords = ['agile', 'git', 'rest', 'api', 'project', 'internship', 'react', 'python', 'sql'];
    $hits = 0;
    $lower = strtolower($resumeText);
    foreach ($keywords as $k) {
        if (strpos($lower, $k) !== false) {
            $hits++;
        }
    }
    $score = max(40, min(95, $base + ($hits * 2)));

    return [
        'score' => $score,
        'breakdown' => [
            'content' => max(35, min(99, $score + 4)),
            'formatting' => max(30, min(99, $score + 1)),
            'keywords' => max(30, min(99, $score - 2)),
            'impact' => max(25, min(99, $score - 6)),
        ],
        'suggestions' => [
            ['type' => 'good', 'title' => 'Clear Resume Structure', 'text' => 'Your resume format is readable and easy to scan.'],
            ['type' => 'warn', 'title' => 'Add More Metrics', 'text' => 'Strengthen impact by adding measurable outcomes and results.'],
            ['type' => 'warn', 'title' => 'Improve Keyword Match', 'text' => 'Include role-specific keywords used in internship job posts.'],
        ],
    ];
}

function cv_builder_text_from_payload(array $cv): string
{
    $chunks = [];
    $profile = (array) ($cv['profile'] ?? []);
    foreach (['name', 'contact', 'headline', 'summary'] as $key) {
        $value = trim((string) ($profile[$key] ?? ''));
        if ($value !== '') {
            $chunks[] = $value;
        }
    }

    foreach ((array) ($cv['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }
        $title = trim((string) ($section['title'] ?? ''));
        if ($title !== '') {
            $chunks[] = $title;
        }

        foreach ((array) ($section['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['role', 'company', 'location', 'dates', 'title', 'subtitle', 'meta'] as $key) {
                $value = trim((string) ($item[$key] ?? ''));
                if ($value !== '') {
                    $chunks[] = $value;
                }
            }
            foreach ((array) ($item['bullets'] ?? []) as $bullet) {
                $value = trim((string) $bullet);
                if ($value !== '') {
                    $chunks[] = $value;
                }
            }
        }
    }

    return trim(implode("\n", $chunks));
}

function cv_builder_default_payload(array $student, array $skills, string $linkedinUrl = ''): array
{
    $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
    if ($name === '') {
        $name = 'Student Name';
    }

    $contactParts = [];
    if (!empty($student['email'])) {
        $contactParts[] = (string) $student['email'];
    }
    if (!empty($student['program'])) {
        $contactParts[] = (string) $student['program'];
    }
    if (!empty($student['department'])) {
        $contactParts[] = (string) $student['department'];
    }
    if ($linkedinUrl !== '') {
        $contactParts[] = $linkedinUrl;
    }

    $skillsText = $skills ? implode(', ', array_slice($skills, 0, 8)) : 'Communication, Problem Solving, Teamwork';

    return [
        'profile' => [
            'name' => $name,
            'contact' => implode(' | ', $contactParts),
            'summary' => 'Motivated student seeking internship opportunities and focused on practical project impact.',
            'headline' => trim((string) ($student['preferred_industry'] ?? 'Internship Candidate')),
        ],
        'sections' => [
            [
                'id' => 'experience',
                'title' => 'Experience',
                'type' => 'experience',
                'items' => [
                    [
                        'role' => 'Intern / Project Contributor',
                        'company' => 'Academic and Personal Projects',
                        'location' => 'Philippines',
                        'dates' => '(Recent)',
                        'bullets' => [
                            'Built portfolio projects with measurable outcomes and clear documentation.',
                            'Worked in team settings using iterative feedback and version control.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'education',
                'title' => 'Education',
                'type' => 'education',
                'items' => [
                    [
                        'title' => (string) ($student['program'] ?? 'Bachelor Degree'),
                        'subtitle' => (string) ($student['department'] ?? 'University'),
                        'meta' => 'Expected Graduation: TBD',
                    ],
                ],
            ],
            [
                'id' => 'skills',
                'title' => 'Skills',
                'type' => 'skills',
                'items' => [
                    [
                        'title' => 'Core Skills',
                        'subtitle' => $skillsText,
                    ],
                ],
            ],
        ],
    ];
}

function cv_builder_normalize_payload(array $candidate, array $fallback): array
{
    $profile = (array) ($candidate['profile'] ?? []);
    $normalized = [
        'profile' => [
            'name' => trim((string) ($profile['name'] ?? $fallback['profile']['name'])),
            'contact' => trim((string) ($profile['contact'] ?? $fallback['profile']['contact'])),
            'summary' => trim((string) ($profile['summary'] ?? $fallback['profile']['summary'])),
            'headline' => trim((string) ($profile['headline'] ?? $fallback['profile']['headline'])),
        ],
        'sections' => [],
    ];

    $sections = (array) ($candidate['sections'] ?? []);
    foreach ($sections as $i => $section) {
        if (!is_array($section)) {
            continue;
        }
        $items = [];
        foreach ((array) ($section['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $bullets = [];
            foreach ((array) ($item['bullets'] ?? []) as $bullet) {
                $text = trim((string) $bullet);
                if ($text !== '') {
                    $bullets[] = $text;
                }
            }
            $items[] = [
                'role' => trim((string) ($item['role'] ?? '')),
                'company' => trim((string) ($item['company'] ?? '')),
                'location' => trim((string) ($item['location'] ?? '')),
                'dates' => trim((string) ($item['dates'] ?? '')),
                'bullets' => $bullets,
                'title' => trim((string) ($item['title'] ?? '')),
                'subtitle' => trim((string) ($item['subtitle'] ?? '')),
                'meta' => trim((string) ($item['meta'] ?? '')),
                'highlight' => (bool) ($item['highlight'] ?? false),
            ];
        }

        $normalized['sections'][] = [
            'id' => trim((string) ($section['id'] ?? ('section_' . $i))),
            'title' => trim((string) ($section['title'] ?? 'Section')),
            'type' => trim((string) ($section['type'] ?? 'generic')),
            'items' => $items,
        ];
    }

    if (!$normalized['sections']) {
        $normalized['sections'] = $fallback['sections'];
    }

    return $normalized;
}

function cv_builder_ensure_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS student_cv_builder (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            source_mode VARCHAR(20) NOT NULL DEFAULT "scratch",
            source_type VARCHAR(20) NOT NULL DEFAULT "",
            source_url VARCHAR(255) NOT NULL DEFAULT "",
            cv_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function cv_builder_db_load(PDO $pdo, int $studentId): ?array
{
    cv_builder_ensure_table($pdo);

    $stmt = $pdo->prepare('SELECT source_mode, source_type, source_url, cv_json, updated_at FROM student_cv_builder WHERE student_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    $decoded = json_decode((string) ($row['cv_json'] ?? ''), true);
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'source_mode' => (string) ($row['source_mode'] ?? 'scratch'),
        'source_type' => (string) ($row['source_type'] ?? ''),
        'source_url' => (string) ($row['source_url'] ?? ''),
        'cv' => $decoded,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function cv_builder_db_save(PDO $pdo, int $studentId, string $sourceMode, string $sourceType, string $sourceUrl, array $cv): string
{
    cv_builder_ensure_table($pdo);

    $json = json_encode($cv, JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        throw new RuntimeException('Unable to encode CV data.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO student_cv_builder (student_id, source_mode, source_type, source_url, cv_json, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE source_mode = VALUES(source_mode), source_type = VALUES(source_type), source_url = VALUES(source_url), cv_json = VALUES(cv_json), updated_at = NOW()'
    );
    $stmt->execute([$studentId, $sourceMode, $sourceType, $sourceUrl, $json]);

    $updatedStmt = $pdo->prepare('SELECT DATE_FORMAT(updated_at, "%Y-%m-%d %H:%i:%s") AS updated_at FROM student_cv_builder WHERE student_id = ? LIMIT 1');
    $updatedStmt->execute([$studentId]);
    $row = $updatedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return (string) ($row['updated_at'] ?? '');
}

function resume_ai_extract_json_object(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $slice = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($slice, true);
    return is_array($decoded) ? $decoded : null;
}

function resume_ai_gemini_request(string $apiKey, string $prompt, float $temperature = 0.3, int $maxTokens = 700): ?string
{
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens,
        ],
    ];

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . rawurlencode($apiKey);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $http < 200 || $http >= 300) {
        return null;
    }

    $data = json_decode($raw, true);
    $parts = (array) ($data['candidates'][0]['content']['parts'] ?? []);
    $chunks = [];
    foreach ($parts as $part) {
        $txt = trim((string) ($part['text'] ?? ''));
        if ($txt !== '') {
            $chunks[] = $txt;
        }
    }

    return $chunks ? implode("\n", $chunks) : null;
}

$stmt = $pdo->prepare('SELECT first_name, last_name, email, program, department, preferred_industry, resume_file FROM student WHERE student_id = ? LIMIT 1');
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$resumeFile = trim((string) ($student['resume_file'] ?? ''));
$resumePath = $resumeFile !== '' ? (__DIR__ . '/../../assets/backend/uploads/resumes/' . $resumeFile) : '';
$resumeText = $resumePath !== '' ? resume_ai_extract_text($resumePath) : '';
if (strlen($resumeText) > 6000) {
    $resumeText = substr($resumeText, 0, 6000);
}

$skills = [];
$stmt = $pdo->prepare('SELECT s.skill_name FROM student_skill ss INNER JOIN skill s ON s.skill_id = ss.skill_id WHERE ss.student_id = ? ORDER BY s.skill_name ASC LIMIT 15');
$stmt->execute([$userId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $skills[] = (string) $row['skill_name'];
}

$profileSummary = sprintf(
    "Name: %s %s\nProgram: %s\nDepartment: %s\nPreferred Industry: %s\nSkills: %s",
    (string) ($student['first_name'] ?? ''),
    (string) ($student['last_name'] ?? ''),
    (string) ($student['program'] ?? ''),
    (string) ($student['department'] ?? ''),
    (string) ($student['preferred_industry'] ?? ''),
    implode(', ', $skills)
);

$apiKey = trim((string) getenv('GEMINI_API_KEY'));
if ($apiKey === '') {
    $apiKey = trim((string) getenv('GOOGLE_API_KEY'));
}

if ($action === 'import_linkedin_cv') {
    $action = 'build_cv';
    $_POST['source_mode'] = 'link';
    $_POST['source_type'] = 'linkedin';
    $_POST['source_url'] = (string) ($_POST['linkedin_url'] ?? '');
}

if ($action === 'load_cv') {
    $row = cv_builder_db_load($pdo, $userId);
    if ($row === null) {
        echo json_encode(['ok' => true, 'has_cv' => false]);
        exit;
    }

    $fallbackCv = cv_builder_default_payload($student, $skills, (string) ($row['source_url'] ?? ''));
    echo json_encode([
        'ok' => true,
        'has_cv' => true,
        'source_mode' => (string) ($row['source_mode'] ?? 'scratch'),
        'source_type' => (string) ($row['source_type'] ?? ''),
        'source_url' => (string) ($row['source_url'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'cv' => cv_builder_normalize_payload((array) ($row['cv'] ?? []), $fallbackCv),
    ]);
    exit;
}

if ($action === 'save_cv') {
    $sourceMode = trim((string) ($_POST['source_mode'] ?? 'scratch'));
    $sourceType = trim((string) ($_POST['source_type'] ?? ''));
    $sourceUrl = trim((string) ($_POST['source_url'] ?? ''));
    $cvRaw = trim((string) ($_POST['cv_json'] ?? ''));
    $decoded = json_decode($cvRaw, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid CV payload.']);
        exit;
    }

    if (!in_array($sourceMode, ['link', 'profile', 'scratch'], true)) {
        $sourceMode = 'scratch';
    }
    if (!in_array($sourceType, ['linkedin', 'github', 'portfolio', 'other', ''], true)) {
        $sourceType = 'other';
    }

    $fallbackCv = cv_builder_default_payload($student, $skills, $sourceUrl);
    $normalizedCv = cv_builder_normalize_payload($decoded, $fallbackCv);
    $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $normalizedCv);

    echo json_encode([
        'ok' => true,
        'updated_at' => $updatedAt,
        'cv' => $normalizedCv,
    ]);
    exit;
}

if ($action === 'build_cv') {
    $sourceMode = trim((string) ($_POST['source_mode'] ?? 'link'));
    $sourceType = trim((string) ($_POST['source_type'] ?? 'linkedin'));
    $sourceUrl = trim((string) ($_POST['source_url'] ?? ''));

    if (!in_array($sourceMode, ['link', 'profile', 'scratch'], true)) {
        $sourceMode = 'link';
    }
    if (!in_array($sourceType, ['linkedin', 'github', 'portfolio', 'other'], true)) {
        $sourceType = 'other';
    }

    if ($sourceMode === 'link' && $sourceUrl === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please provide a URL for link import.']);
        exit;
    }

    if ($sourceMode === 'link' && !preg_match('#^https?://#i', $sourceUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Please provide a valid URL.']);
        exit;
    }

    $fallbackCv = cv_builder_default_payload($student, $skills, $sourceMode === 'link' ? $sourceUrl : '');

    if ($sourceMode === 'scratch') {
        $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $fallbackCv);
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'updated_at' => $updatedAt,
            'cv' => $fallbackCv,
            'note' => 'Started from scratch template and saved to database.',
        ]);
        exit;
    }

    if ($apiKey === '') {
        $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $fallbackCv);
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'updated_at' => $updatedAt,
            'cv' => $fallbackCv,
            'note' => 'Gemini API key is not configured. Showing starter CV template.',
        ]);
        exit;
    }

    $sourceHint = $sourceMode === 'profile'
        ? 'Use only SkillHive profile and resume data as source.'
        : ('Use this external link as a hint: [' . $sourceType . '] ' . $sourceUrl);

    $prompt = "You are a CV writer. Transform available data into a polished internship-ready CV JSON. Return strict JSON only with keys: profile and sections.\n\nSchema:\n{\n  \"profile\": {\"name\": string, \"contact\": string, \"summary\": string, \"headline\": string},\n  \"sections\": [\n    {\"id\": \"experience\", \"title\": \"Experience\", \"type\": \"experience\", \"items\": [{\"role\": string, \"company\": string, \"location\": string, \"dates\": string, \"bullets\": [string]}]},\n    {\"id\": \"education\", \"title\": \"Education\", \"type\": \"education\", \"items\": [{\"title\": string, \"subtitle\": string, \"meta\": string}]},\n    {\"id\": \"skills\", \"title\": \"Skills\", \"type\": \"skills\", \"items\": [{\"title\": string, \"subtitle\": string}]}\n  ]\n}\n\nData source hint:\n"
        . $sourceHint
        . "\n\nStudent profile:\n"
        . $profileSummary
        . "\n\nResume text excerpt:\n"
        . $resumeText
        . "\n\nConstraints:\n- Keep summary under 3 sentences.\n- Provide 2 to 4 experience entries.\n- Each experience bullet should be concise and achievement-focused.\n- Do not include markdown or comments.";

    $reply = resume_ai_gemini_request($apiKey, $prompt, 0.35, 1400);
    if ($reply === null) {
        $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $fallbackCv);
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'updated_at' => $updatedAt,
            'cv' => $fallbackCv,
            'note' => 'Gemini request failed. Showing starter CV template.',
        ]);
        exit;
    }

    $parsed = resume_ai_extract_json_object($reply);
    if (!is_array($parsed)) {
        $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $fallbackCv);
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'updated_at' => $updatedAt,
            'cv' => $fallbackCv,
            'note' => 'Gemini response parse failed. Showing starter CV template.',
        ]);
        exit;
    }

    $normalizedCv = cv_builder_normalize_payload($parsed, $fallbackCv);
    $updatedAt = cv_builder_db_save($pdo, $userId, $sourceMode, $sourceType, $sourceUrl, $normalizedCv);
    echo json_encode([
        'ok' => true,
        'source' => 'ai',
        'updated_at' => $updatedAt,
        'cv' => $normalizedCv,
    ]);
    exit;
}

if ($action === 'score_cv') {
    $cvRaw = trim((string) ($_POST['cv_json'] ?? ''));
    $decodedCv = json_decode($cvRaw, true);
    if (!is_array($decodedCv)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid CV payload for scoring.']);
        exit;
    }

    $fallbackCv = cv_builder_default_payload($student, $skills, '');
    $normalizedCv = cv_builder_normalize_payload($decodedCv, $fallbackCv);
    $cvText = cv_builder_text_from_payload($normalizedCv);
    $fallback = resume_ai_fallback_analysis($cvText);

    if ($apiKey === '' || $cvText === '') {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'note' => $apiKey === '' ? 'Gemini key is not set. Showing fallback score.' : 'CV content is too short. Showing fallback score.',
        ]);
        exit;
    }

    $prompt = "You are a resume reviewer for internship applicants. Evaluate this CV and return strict JSON only with keys: score (0-100 int), breakdown (object with content, formatting, keywords, impact ints), note (string). Keep the note concise.\n\nStudent profile:\n"
        . $profileSummary
        . "\n\nCV text:\n"
        . $cvText;

    $reply = resume_ai_gemini_request($apiKey, $prompt, 0.2, 800);
    if ($reply === null) {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'note' => 'Gemini request failed. Showing fallback score.',
        ]);
        exit;
    }

    $json = resume_ai_extract_json_object($reply);
    if (!is_array($json)) {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'note' => 'Gemini response parse failed. Showing fallback score.',
        ]);
        exit;
    }

    $score = max(0, min(100, (int) ($json['score'] ?? $fallback['score'])));
    $breakdown = (array) ($json['breakdown'] ?? []);
    $normalizedBreakdown = [
        'content' => max(0, min(100, (int) ($breakdown['content'] ?? $fallback['breakdown']['content']))),
        'formatting' => max(0, min(100, (int) ($breakdown['formatting'] ?? $fallback['breakdown']['formatting']))),
        'keywords' => max(0, min(100, (int) ($breakdown['keywords'] ?? $fallback['breakdown']['keywords']))),
        'impact' => max(0, min(100, (int) ($breakdown['impact'] ?? $fallback['breakdown']['impact']))),
    ];

    echo json_encode([
        'ok' => true,
        'source' => 'ai',
        'score' => $score,
        'breakdown' => $normalizedBreakdown,
        'note' => trim((string) ($json['note'] ?? 'Score generated from your current CV.')),
    ]);
    exit;
}

if ($action === 'analyze') {
    $fallback = resume_ai_fallback_analysis($resumeText);

    if ($apiKey === '' || $resumeText === '') {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'suggestions' => $fallback['suggestions'],
            'note' => $apiKey === '' ? 'GEMINI_API_KEY is not set.' : 'Upload a resume for richer analysis.',
        ]);
        exit;
    }

    $prompt = "You are a resume reviewer for internship applicants. Return strict JSON only (no markdown, no extra text) with keys: score (0-100 int), breakdown (object with content, formatting, keywords, impact ints), suggestions (array of exactly 4 objects with type in [good,warn,risk], title, text). Keep suggestions concise and actionable.\n\nStudent profile:\n" . $profileSummary . "\n\nResume text:\n" . $resumeText;

    $reply = resume_ai_gemini_request($apiKey, $prompt, 0.2, 900);

    if ($reply === null) {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'suggestions' => $fallback['suggestions'],
            'note' => 'AI request failed. Showing fallback analysis.',
        ]);
        exit;
    }

    $json = resume_ai_extract_json_object($reply);
    if (!is_array($json)) {
        echo json_encode([
            'ok' => true,
            'source' => 'fallback',
            'score' => $fallback['score'],
            'breakdown' => $fallback['breakdown'],
            'suggestions' => $fallback['suggestions'],
            'note' => 'AI response parse failed. Showing fallback analysis.',
        ]);
        exit;
    }

    $score = max(0, min(100, (int) ($json['score'] ?? $fallback['score'])));
    $breakdown = $json['breakdown'] ?? [];
    $normalizedBreakdown = [
        'content' => max(0, min(100, (int) ($breakdown['content'] ?? $fallback['breakdown']['content']))),
        'formatting' => max(0, min(100, (int) ($breakdown['formatting'] ?? $fallback['breakdown']['formatting']))),
        'keywords' => max(0, min(100, (int) ($breakdown['keywords'] ?? $fallback['breakdown']['keywords']))),
        'impact' => max(0, min(100, (int) ($breakdown['impact'] ?? $fallback['breakdown']['impact']))),
    ];

    $suggestions = [];
    foreach ((array) ($json['suggestions'] ?? []) as $s) {
        $type = (string) ($s['type'] ?? 'warn');
        if (!in_array($type, ['good', 'warn', 'risk'], true)) {
            $type = 'warn';
        }
        $suggestions[] = [
            'type' => $type,
            'title' => trim((string) ($s['title'] ?? 'Suggestion')),
            'text' => trim((string) ($s['text'] ?? '')),
        ];
        if (count($suggestions) >= 4) {
            break;
        }
    }

    if (!$suggestions) {
        $suggestions = $fallback['suggestions'];
    }

    echo json_encode([
        'ok' => true,
        'source' => 'ai',
        'score' => $score,
        'breakdown' => $normalizedBreakdown,
        'suggestions' => $suggestions,
    ]);
    exit;
}

$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '') {
    echo json_encode(['ok' => false, 'message' => 'Message is required.']);
    exit;
}

if ($apiKey === '') {
    $fallbackReply = 'AI chat is not configured yet. Set GEMINI_API_KEY in your server environment to enable live responses.';
    echo json_encode(['ok' => true, 'reply' => $fallbackReply, 'source' => 'fallback']);
    exit;
}

$chatPrompt = "You are an internship interview coach. Keep responses concise, practical, and supportive. Use the student's profile and resume when useful.\n\nStudent profile:\n" . $profileSummary . "\n\nResume excerpt:\n" . $resumeText . "\n\nUser question:\n" . $message;

$chatReply = resume_ai_gemini_request($apiKey, $chatPrompt, 0.5, 500);

if ($chatReply === null || $chatReply === '') {
    echo json_encode(['ok' => false, 'message' => 'Unable to generate AI response right now.']);
    exit;
}

echo json_encode(['ok' => true, 'reply' => $chatReply, 'source' => 'ai']);