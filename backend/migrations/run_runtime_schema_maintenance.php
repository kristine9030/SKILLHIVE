<?php
/**
 * Run SkillHive schema maintenance intentionally after deploys.
 *
 * CLI usage:
 *   php backend/migrations/run_runtime_schema_maintenance.php
 *
 * Browser usage:
 *   Log in as an admin first, then open this file. Non-admin web requests are
 *   rejected so this cannot be run publicly by accident.
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/runtime_schema_maintenance.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Forbidden. Log in as an admin before running schema maintenance.\n";
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
}

$results = skillhive_run_runtime_schema_maintenance($pdo);
$failed = 0;

echo "SkillHive schema maintenance\n";
echo "============================\n";

foreach ($results as $name => $result) {
    $ok = !empty($result['ok']);
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name;
    if (!$ok) {
        $failed++;
        echo ' - ' . (string)($result['error'] ?? 'Unknown error');
    }
    echo "\n";
}

echo "============================\n";
echo $failed === 0 ? "Completed successfully.\n" : "Completed with {$failed} failure(s).\n";

if ($isCli && $failed > 0) {
    exit(1);
}
