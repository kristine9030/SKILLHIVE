<?php
// db_connect.php
// Starts the session, loads local config, and opens the shared PDO connection.
// Schema maintenance lives in backend/migrations/runtime_schema_maintenance.php
// and should be run intentionally, not on every page load.

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

$localConfigPath = __DIR__ . '/local_config.php';
if (file_exists($localConfigPath)) {
    require_once $localConfigPath;
}

if (!defined('RAPIDAPI_ACTIVE_JOBS_KEY')) {
    define('RAPIDAPI_ACTIVE_JOBS_KEY', '');
}
if (!defined('RAPIDAPI_ACTIVE_JOBS_HOST')) {
    define('RAPIDAPI_ACTIVE_JOBS_HOST', 'active-jobs-search-api.p.rapidapi.com');
}
if (!defined('SKILLHIVE_REQUIRED_OJT_HOURS')) {
    define('SKILLHIVE_REQUIRED_OJT_HOURS', 500.00);
}
if (!defined('SKILLHIVE_ENABLE_RUNTIME_SCHEMA_MAINTENANCE')) {
    define('SKILLHIVE_ENABLE_RUNTIME_SCHEMA_MAINTENANCE', false);
}

$host = 'localhost';
$db = 'skillhive';
$user = 'root'; // change if needed
$pass = '';     // change if you have a password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Emergency opt-in for local/dev repair work. Keep this false in production;
// use backend/migrations/run_runtime_schema_maintenance.php for planned runs.
if (SKILLHIVE_ENABLE_RUNTIME_SCHEMA_MAINTENANCE) {
    require_once __DIR__ . '/migrations/runtime_schema_maintenance.php';
    skillhive_run_runtime_schema_maintenance($pdo);
}
