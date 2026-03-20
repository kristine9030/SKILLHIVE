<?php
ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('ojt_submit_json')) {
    function ojt_submit_json(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode($payload);
        exit;
    }
}

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/ojt_log_helpers.php';
require_once __DIR__ . '/ojt_log_submit.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['log_entry'])) {
        ojt_submit_json(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    $studentId = (int)($_SESSION['user_id'] ?? 0);
    if ($studentId <= 0) {
        ojt_submit_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
    }

    $ojt = ojt_get_or_create_record($pdo, $studentId);

    ojt_log_handle_submit($pdo, $ojt);

    ojt_submit_json(['ok' => false, 'message' => 'Unexpected response from log submit handler.'], 500);
} catch (Throwable $e) {
    ojt_submit_json(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
