<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../../backend/db_connect.php';

$studentId = 0;
if (isset($_SESSION['user_id'])) {
    $studentId = (int)$_SESSION['user_id'];
}

// Log for debugging
$log = date('Y-m-d H:i:s') . " - studentId=$studentId, method=" . $_SERVER['REQUEST_METHOD'] . ", action=" . (isset($_GET['action']) ? $_GET['action'] : 'none') . "\n";
file_put_contents(__DIR__ . '/debug.log', $log, FILE_APPEND);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(array('ok' => false, 'message' => 'Invalid request method'));
    exit;
}

$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}
if ($action !== 'get_entries') {
    echo json_encode(array('ok' => false, 'message' => 'Invalid action'));
    exit;
}

if (!$studentId) {
    echo json_encode(array('ok' => false, 'message' => 'Not logged in. user_id=' . $studentId));
    exit;
}

$date = '';
if (isset($_GET['date'])) {
    $date = trim($_GET['date']);
}
if (!$date) {
    echo json_encode(array('ok' => false, 'message' => 'No date provided'));
    exit;
}

// Get OJT record
$stmt = $pdo->prepare('SELECT * FROM ojt_record WHERE student_id = ? ORDER BY record_id DESC LIMIT 1');
$stmt->execute(array($studentId));
$ojt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ojt) {
    echo json_encode(array('ok' => true, 'entries' => array()));
    exit;
}

// Get entries
$sql = 'SELECT log_id, log_date, start_time, end_time, hours_rendered, accomplishment, mood_tag, file_path FROM daily_log WHERE record_id = ? AND log_date = ? ORDER BY start_time ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($ojt['record_id'], $date));
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log success
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Success, entries=" . count($entries) . "\n", FILE_APPEND);

echo json_encode(array('ok' => true, 'entries' => $entries));
exit;
}

$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}
if ($action !== 'get_entries') {
    echo json_encode(array('ok' => false, 'message' => 'Invalid action'));
    exit;
}

if (!$studentId) {
    echo json_encode(array('ok' => false, 'message' => 'Not logged in. user_id=' . $studentId));
    exit;
}

$date = '';
if (isset($_GET['date'])) {
    $date = trim($_GET['date']);
}
if (!$date) {
    echo json_encode(array('ok' => false, 'message' => 'No date provided'));
    exit;
}

// Get OJT record
$stmt = $pdo->prepare('SELECT * FROM ojt_record WHERE student_id = ? ORDER BY record_id DESC LIMIT 1');
$stmt->execute(array($studentId));
$ojt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ojt) {
    echo json_encode(array('ok' => true, 'entries' => array()));
    exit;
}

// Get entries
$sql = 'SELECT log_id, log_date, start_time, end_time, hours_rendered, accomplishment, mood_tag, file_path FROM daily_log WHERE record_id = ? AND log_date = ? ORDER BY start_time ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute(array($ojt['record_id'], $date));
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array('ok' => true, 'entries' => $entries));
exit;
