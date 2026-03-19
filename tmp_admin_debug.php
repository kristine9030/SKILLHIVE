<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'System Administrator';
$_SESSION['user_email'] = 'admin@skillhive.com';
$baseUrl = '/SkillHive';

require __DIR__ . '/backend/db_connect.php';

$tables = ['internship', 'ojt_record', 'application', 'student', 'employer'];
foreach ($tables as $table) {
    echo "TABLE: {$table}\n";
    $stmt = $pdo->query("DESCRIBE `{$table}`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Field'] . '|' . $row['Type'] . "\n";
    }
    echo "\n";
}

echo "---- DASHBOARD ----\n";
try {
    ob_start();
    include __DIR__ . '/pages/admin/dashboard.php';
    ob_end_clean();
    echo "dashboard ok\n";
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo 'dashboard error: ' . $e->getMessage() . "\n";
}

echo "---- REPORTS ----\n";
try {
    ob_start();
    include __DIR__ . '/pages/admin/reports.php';
    ob_end_clean();
    echo "reports ok\n";
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo 'reports error: ' . $e->getMessage() . "\n";
}
