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
if (!in_array($action, ['analyze', 'chat'], true)) {
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

$stmt = $pdo->prepare('SELECT first_name, last_name, program, department, preferred_industry, resume_file FROM student WHERE student_id = ? LIMIT 1');
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