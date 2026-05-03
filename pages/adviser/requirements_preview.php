<?php
require_once __DIR__ . '/../../backend/db_connect.php';

if (!function_exists('adviser_requirements_preview_html')) {
    function adviser_requirements_preview_html(string $title, string $message, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}.box{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px;box-sizing:border-box}.card{max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 12px 34px rgba(15,23,42,.08)}h1{margin:0 0 10px;font-size:20px}p{margin:0;color:#64748b;line-height:1.55}</style>';
        echo '<title>' . $safeTitle . '</title></head><body><div class="box"><div class="card"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p></div></div></body></html>';
        exit;
    }
}

if (!function_exists('adviser_requirements_preview_inside_path')) {
    function adviser_requirements_preview_inside_path(string $path, string $baseDir): bool
    {
        $path = str_replace('\\', '/', $path);
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';

        return strpos($path, $baseDir) === 0;
    }
}

if (!function_exists('adviser_requirements_preview_docx_text')) {
    function adviser_requirements_preview_docx_text(string $filePath): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xml = (string)$zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === '') {
            return '';
        }

        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
        $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml);
        $xml = preg_replace('/<\/w:p>/', "\n\n", $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", (string)$text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string)$text);

        return trim((string)$text);
    }
}

if (!function_exists('adviser_requirements_preview_docx_html')) {
    function adviser_requirements_preview_docx_html(string $filePath, string $title): void
    {
        $text = adviser_requirements_preview_docx_text($filePath);

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>body{margin:0;background:#f8fafc;color:#111827;font-family:Arial,sans-serif}.doc{max-width:860px;margin:0 auto;padding:30px 22px}.paper{background:#fff;border:1px solid #e5e7eb;box-shadow:0 16px 44px rgba(15,23,42,.08);min-height:780px;padding:44px 48px;box-sizing:border-box}.meta{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:700;margin-bottom:18px}h1{font-size:20px;margin:0 0 22px;color:#0f172a}.paper p{font-size:15px;line-height:1.7;margin:0 0 14px;white-space:pre-wrap}.empty{color:#64748b;line-height:1.55}@media(max-width:680px){.paper{padding:28px 22px}.doc{padding:14px}}</style>';
        echo '<title>' . $safeTitle . '</title></head><body><main class="doc"><section class="paper"><div class="meta">DOCX Preview</div><h1>' . $safeTitle . '</h1>';

        if ($text === '') {
            echo '<p class="empty">This DOCX file could not be rendered as text preview on this server.</p>';
        } else {
            $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim((string)$paragraph);
                if ($paragraph === '') {
                    continue;
                }
                echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'), false) . '</p>';
            }
        }

        echo '</section></main></body></html>';
        exit;
    }
}

$role = (string)($_SESSION['role'] ?? '');
$adviserId = (int)($_SESSION['adviser_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($role !== 'adviser' || $adviserId <= 0) {
    adviser_requirements_preview_html('Preview unavailable', 'You are not allowed to view this file.', 403);
}

$submissionId = (int)($_GET['id'] ?? 0);
if ($submissionId <= 0) {
    adviser_requirements_preview_html('Preview unavailable', 'Invalid requirement submission.', 422);
}

$stmt = $pdo->prepare(
    'SELECT
        sr.req_submission_id,
        sr.student_id,
        sr.file_path,
        r.name AS requirement_name,
        s.first_name,
        s.last_name
     FROM student_requirement sr
     INNER JOIN requirement r ON r.requirement_id = sr.requirement_id
     INNER JOIN student s ON s.student_id = sr.student_id
     INNER JOIN adviser_assignment aa ON aa.student_id = sr.student_id
     WHERE sr.req_submission_id = :submission_id
       AND aa.adviser_id = :adviser_id
       AND COALESCE(NULLIF(LOWER(TRIM(aa.status)), ""), "active") = "active"
       AND COALESCE(NULLIF(TRIM(sr.file_path), ""), "") <> ""
     LIMIT 1'
);
$stmt->execute([
    ':submission_id' => $submissionId,
    ':adviser_id' => $adviserId,
]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if ($submission === null) {
    adviser_requirements_preview_html('Preview unavailable', 'The file was not found or is outside your assigned students.', 404);
}

$relativePath = str_replace('\\', '/', trim((string)($submission['file_path'] ?? '')));
$relativePath = ltrim($relativePath, '/');
if ($relativePath === '' || strpos($relativePath, '..') !== false) {
    adviser_requirements_preview_html('Preview unavailable', 'The saved file path is invalid.', 422);
}

$uploadBaseDir = realpath(__DIR__ . '/../../assets/backend/uploads');
if ($uploadBaseDir === false) {
    adviser_requirements_preview_html('Preview unavailable', 'Upload storage is not available.', 500);
}

$fullPath = realpath($uploadBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($fullPath === false || !is_file($fullPath) || !adviser_requirements_preview_inside_path($fullPath, $uploadBaseDir)) {
    adviser_requirements_preview_html('Preview unavailable', 'The uploaded file is missing from storage.', 404);
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$studentName = trim((string)($submission['first_name'] ?? '') . ' ' . (string)($submission['last_name'] ?? ''));
$requirementName = trim((string)($submission['requirement_name'] ?? 'Requirement'));
$previewTitle = ($requirementName !== '' ? $requirementName : 'Requirement') . ($studentName !== '' ? ' - ' . $studentName : '');

if ($extension === 'docx') {
    adviser_requirements_preview_docx_html($fullPath, $previewTitle);
}

if ($extension === 'doc') {
    adviser_requirements_preview_html(
        'Legacy Word Preview',
        'This legacy Word document cannot be rendered inline by the browser. Ask the student to upload PDF, image, or DOCX for an inline preview.',
        200
    );
}

$mimeMap = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
];

if (!isset($mimeMap[$extension])) {
    adviser_requirements_preview_html('Preview unavailable', 'This file type is not supported for inline preview.', 415);
}

$displayName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($relativePath));
if ($displayName === null || trim($displayName) === '') {
    $displayName = 'requirement.' . $extension;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeMap[$extension]);
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $displayName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($fullPath);
exit;
