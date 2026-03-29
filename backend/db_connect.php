<?php
// db_connect.php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
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

$host = 'localhost';
$db   = 'skillhive';
$user = 'root'; // change if needed
$pass = '';     // change if you have a password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch as associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // use real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Ensure OJT auto-provision triggers exist. This keeps ojt_record synced when
// application status becomes Accepted from any module.
try {
    $triggerNames = [
        'trg_application_ai_create_ojt',
        'trg_application_au_create_ojt',
    ];

    $inPlaceholders = implode(',', array_fill(0, count($triggerNames), '?'));
    $stmt = $pdo->prepare(
        "SELECT TRIGGER_NAME
         FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND TRIGGER_NAME IN ($inPlaceholders)"
    );
    $stmt->execute($triggerNames);
    $existing = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!in_array('trg_application_ai_create_ojt', $existing, true)) {
        $pdo->exec(
            "CREATE TRIGGER trg_application_ai_create_ojt
             AFTER INSERT ON application
             FOR EACH ROW
             INSERT INTO ojt_record (
                student_id, internship_id, hours_required, hours_completed,
                start_date, end_date, completion_status, created_at, updated_at
             )
             SELECT
                NEW.student_id,
                NEW.internship_id,
                400.00,
                0.00,
                CURDATE(),
                DATE_ADD(CURDATE(), INTERVAL IFNULL(i.duration_weeks, 12) WEEK),
                'Ongoing',
                NOW(),
                NOW()
             FROM internship i
             WHERE NEW.status = 'Accepted'
               AND i.internship_id = NEW.internship_id
               AND NOT EXISTS (
                 SELECT 1
                 FROM ojt_record r
                 WHERE r.student_id = NEW.student_id
                   AND r.internship_id = NEW.internship_id
               )"
        );
    }

    if (!in_array('trg_application_au_create_ojt', $existing, true)) {
        $pdo->exec(
            "CREATE TRIGGER trg_application_au_create_ojt
             AFTER UPDATE ON application
             FOR EACH ROW
             INSERT INTO ojt_record (
                student_id, internship_id, hours_required, hours_completed,
                start_date, end_date, completion_status, created_at, updated_at
             )
             SELECT
                NEW.student_id,
                NEW.internship_id,
                400.00,
                0.00,
                CURDATE(),
                DATE_ADD(CURDATE(), INTERVAL IFNULL(i.duration_weeks, 12) WEEK),
                'Ongoing',
                NOW(),
                NOW()
             FROM internship i
             WHERE NEW.status = 'Accepted'
               AND OLD.status <> 'Accepted'
               AND i.internship_id = NEW.internship_id
               AND NOT EXISTS (
                 SELECT 1
                 FROM ojt_record r
                 WHERE r.student_id = NEW.student_id
                   AND r.internship_id = NEW.internship_id
               )"
        );
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if DB user lacks TRIGGER privilege.
}

// Schema Migration: Ensure interview_time column exists on interview table
try {
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'interview' 
         AND COLUMN_NAME = 'interview_time'"
    );
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        // Column doesn't exist, add it
        $pdo->exec(
            "ALTER TABLE interview 
             ADD COLUMN interview_time TIME NOT NULL DEFAULT '09:00:00' 
             AFTER interview_date"
        );
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails
}

// Schema Migration: Ensure must_change_password exists on student table.
// New adviser-created student accounts are flagged and forced to update password at first login.
try {
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'student'
           AND COLUMN_NAME = 'must_change_password'"
    );
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec(
            "ALTER TABLE student
             ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
             AFTER password_hash"
        );
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails.
}

// Schema Migration: Ensure academic_year exists on student table.
// Adviser add-student form now captures academic year (e.g., 2025-2026).
try {
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'student'
           AND COLUMN_NAME = 'academic_year'"
    );
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec(
            "ALTER TABLE student
             ADD COLUMN academic_year VARCHAR(20) NOT NULL DEFAULT ''
             AFTER year_level"
        );
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails.
}
?>