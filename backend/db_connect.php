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
if (!defined('SKILLHIVE_REQUIRED_OJT_HOURS')) {
    define('SKILLHIVE_REQUIRED_OJT_HOURS', 500.00);
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
    $requiredOjtHours = (float)SKILLHIVE_REQUIRED_OJT_HOURS;
    $requiredOjtHoursLiteral = number_format($requiredOjtHours, 2, '.', '');

    $triggerNames = [
        'trg_application_ai_create_ojt',
        'trg_application_au_create_ojt',
    ];

    $inPlaceholders = implode(',', array_fill(0, count($triggerNames), '?'));
    $stmt = $pdo->prepare(
        "SELECT TRIGGER_NAME, ACTION_STATEMENT
         FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND TRIGGER_NAME IN ($inPlaceholders)"
    );
    $stmt->execute($triggerNames);
    $triggerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $triggerActions = [];
    foreach ($triggerRows as $row) {
        $name = (string)($row['TRIGGER_NAME'] ?? '');
        if ($name === '') {
            continue;
        }
        $triggerActions[$name] = (string)($row['ACTION_STATEMENT'] ?? '');
    }

    $aiTriggerSql = (string)($triggerActions['trg_application_ai_create_ojt'] ?? '');
    $needsAiTriggerRefresh = $aiTriggerSql === ''
        || stripos($aiTriggerSql, $requiredOjtHoursLiteral) === false
        || stripos($aiTriggerSql, 'GREATEST') === false;
    if ($needsAiTriggerRefresh) {
        if (isset($triggerActions['trg_application_ai_create_ojt'])) {
            $pdo->exec('DROP TRIGGER IF EXISTS trg_application_ai_create_ojt');
        }

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
                     GREATEST({$requiredOjtHoursLiteral}, COALESCE(i.duration_weeks, 0) * 40.00),
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

    $auTriggerSql = (string)($triggerActions['trg_application_au_create_ojt'] ?? '');
    $needsAuTriggerRefresh = $auTriggerSql === ''
        || stripos($auTriggerSql, $requiredOjtHoursLiteral) === false
        || stripos($auTriggerSql, 'GREATEST') === false;
    if ($needsAuTriggerRefresh) {
        if (isset($triggerActions['trg_application_au_create_ojt'])) {
            $pdo->exec('DROP TRIGGER IF EXISTS trg_application_au_create_ojt');
        }

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
                     GREATEST({$requiredOjtHoursLiteral}, COALESCE(i.duration_weeks, 0) * 40.00),
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

// Schema migration: keep required OJT hours aligned with current policy.
try {
    $requiredOjtHours = (float)SKILLHIVE_REQUIRED_OJT_HOURS;
    $stmt = $pdo->prepare(
        'UPDATE ojt_record
         SET hours_required = ?, updated_at = NOW()
         WHERE COALESCE(hours_required, 0) < ?'
    );
    $stmt->execute([$requiredOjtHours, $requiredOjtHours]);
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails.
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

// Schema Migration: Create ojt_journal_entries table for structured journal entries
try {
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'ojt_journal_entries'"
    );
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec(
            "CREATE TABLE ojt_journal_entries (
                journal_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                record_id INT UNSIGNED NOT NULL,
                log_ids VARCHAR(255) NOT NULL DEFAULT '',
                entry_date DATE NOT NULL,
                company_department VARCHAR(255) NOT NULL DEFAULT '',
                tasks_accomplished LONGTEXT NOT NULL DEFAULT '',
                skills_applied_learned LONGTEXT NOT NULL DEFAULT '',
                challenges_encountered LONGTEXT NOT NULL DEFAULT '',
                solutions_actions_taken LONGTEXT NOT NULL DEFAULT '',
                key_learnings_insights LONGTEXT NOT NULL DEFAULT '',
                reflection LONGTEXT NOT NULL DEFAULT '',
                quality_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                sentiment_analysis VARCHAR(50) NOT NULL DEFAULT 'neutral',
                productivity_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                is_structured TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (journal_id),
                KEY idx_record_id (record_id),
                KEY idx_entry_date (entry_date),
                FOREIGN KEY (record_id) REFERENCES ojt_record(record_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        // Add missing columns if they don't exist (backward compatibility)
        $check_quality = $pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'ojt_journal_entries' 
             AND COLUMN_NAME = 'quality_score'"
        );
        $check_quality->execute();
        if ($check_quality->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ojt_journal_entries ADD COLUMN quality_score TINYINT UNSIGNED NOT NULL DEFAULT 0");
        }
        
        $check_sentiment = $pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'ojt_journal_entries' 
             AND COLUMN_NAME = 'sentiment_analysis'"
        );
        $check_sentiment->execute();
        if ($check_sentiment->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ojt_journal_entries ADD COLUMN sentiment_analysis VARCHAR(50) NOT NULL DEFAULT 'neutral'");
        }
        
        $check_productivity = $pdo->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'ojt_journal_entries' 
             AND COLUMN_NAME = 'productivity_score'"
        );
        $check_productivity->execute();
        if ($check_productivity->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ojt_journal_entries ADD COLUMN productivity_score TINYINT UNSIGNED NOT NULL DEFAULT 0");
        }
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails.
}

// Schema Migration: Create ojt_final_reports table for internship summary reports
try {
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'ojt_final_reports'"
    );
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $pdo->exec(
            "CREATE TABLE ojt_final_reports (
                report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                record_id INT UNSIGNED NOT NULL,
                internship_overview LONGTEXT NOT NULL DEFAULT '',
                key_responsibilities LONGTEXT NOT NULL DEFAULT '',
                skills_developed LONGTEXT NOT NULL DEFAULT '',
                challenges_resolutions LONGTEXT NOT NULL DEFAULT '',
                contributions_achievements LONGTEXT NOT NULL DEFAULT '',
                personal_professional_growth LONGTEXT NOT NULL DEFAULT '',
                conclusion_reflection LONGTEXT NOT NULL DEFAULT '',
                total_journal_entries INT UNSIGNED NOT NULL DEFAULT 0,
                duration_days INT UNSIGNED NOT NULL DEFAULT 0,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (report_id),
                KEY idx_record_id (record_id),
                FOREIGN KEY (record_id) REFERENCES ojt_record(record_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
} catch (Throwable $e) {
    // Non-fatal: app should continue even if migration fails.
}
?>