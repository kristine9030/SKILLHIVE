<?php
// Capture any PHP errors/warnings as JSON instead of HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Buffer output so any stray PHP warnings don't corrupt JSON
ob_start();

session_start();

require_once __DIR__ . '/../../../backend/db_connect.php';
require_once __DIR__ . '/ojt_log_helpers.php';
require_once __DIR__ . '/ojt_log_submit.php';

$studentId = 0;
if (isset($_SESSION['user_id'])) {
    $studentId = (int) $_SESSION['user_id'];
}

// Discard any stray output buffered before headers
ob_clean();
header('Content-Type: application/json');

// ── POST: submit a new log entry ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$studentId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not logged in']);
        exit;
    }

    // Block submissions if the student account is Inactive or Archived.
    try {
        $acctStmt = $pdo->prepare(
            "SELECT COALESCE(account_status, 'Active') AS account_status FROM student WHERE student_id = ? LIMIT 1"
        );
        $acctStmt->execute([$studentId]);
        $acctStatus = strtolower((string)($acctStmt->fetchColumn() ?: 'active'));
        if ($acctStatus === 'inactive' || $acctStatus === 'archived') {
            http_response_code(403);
            echo json_encode([
                'ok'      => false,
                'message' => 'Your account has been ' . ucfirst($acctStatus) . ' by your adviser. Timesheet submission is no longer available.',
            ]);
            exit;
        }
    } catch (Throwable $_e) {
        // If column doesn't exist yet (pre-migration), allow submission to continue.
    }

    $ojt = ojt_get_or_create_record($pdo, $studentId);
    ojt_log_handle_submit($pdo, $ojt);
    exit;
}

// ── GET: fetch entries for a date ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';

    if ($action !== 'get_entries') {
        echo json_encode(['ok' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)]);
        exit;
    }

    if (!$studentId) {
        echo json_encode(['ok' => false, 'message' => 'Not logged in - no session user_id']);
        exit;
    }

    $date = isset($_GET['date']) ? trim($_GET['date']) : '';

    if (!$date) {
        echo json_encode(['ok' => false, 'message' => 'No date provided']);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid date format: ' . htmlspecialchars($date)]);
        exit;
    }

    try {
        // Get OJT record
        $stmt = $pdo->prepare(
            'SELECT * FROM ojt_record WHERE student_id = ? ORDER BY record_id DESC LIMIT 1'
        );
        $stmt->execute([$studentId]);
        $ojt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ojt) {
            echo json_encode(['ok' => true, 'entries' => [], 'debug' => 'No OJT record found for student ' . $studentId]);
            exit;
        }

        // Fetch daily log entries for the selected date
        $stmt = $pdo->prepare(
            'SELECT log_id, log_date, start_time, end_time, hours_rendered,
                    accomplishment, mood_tag, task_file AS file_path
            FROM daily_log
            WHERE record_id = ? AND log_date = ?
            ORDER BY log_id ASC'
        );
        $stmt->execute([$ojt['record_id'], $date]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'entries' => $entries]);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'DB error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Method not allowed: ' . $_SERVER['REQUEST_METHOD']]);
exit;