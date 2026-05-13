<?php
/**
 * One-time schema maintenance tasks for SkillHive.
 *
 * These checks used to run from backend/db_connect.php on every request. Keep
 * them here so production page loads only open the database connection, while
 * schema repairs can still be run intentionally after deploys.
 */

if (!function_exists('skillhive_schema_column_exists')) {
    function skillhive_schema_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('skillhive_schema_table_exists')) {
    function skillhive_schema_table_exists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1'
        );
        $stmt->execute([':table_name' => $tableName]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('skillhive_schema_run_step')) {
    function skillhive_schema_run_step(array &$results, string $name, callable $callback): void
    {
        try {
            $callback();
            $results[$name] = ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            $results[$name] = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('skillhive_schema_sync_application_ojt_triggers')) {
    function skillhive_schema_sync_application_ojt_triggers(PDO $pdo): void
    {
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

        $triggerActions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string)($row['TRIGGER_NAME'] ?? '');
            if ($name !== '') {
                $triggerActions[$name] = (string)($row['ACTION_STATEMENT'] ?? '');
            }
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
    }
}

if (!function_exists('skillhive_schema_create_journal_tables')) {
    function skillhive_schema_create_journal_tables(PDO $pdo): void
    {
        if (!skillhive_schema_table_exists($pdo, 'ojt_journal_entries')) {
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
                    is_visible_to_adviser TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (journal_id),
                    KEY idx_record_id (record_id),
                    KEY idx_entry_date (entry_date),
                    FOREIGN KEY (record_id) REFERENCES ojt_record(record_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            $columns = [
                'quality_score' => 'ALTER TABLE ojt_journal_entries ADD COLUMN quality_score TINYINT UNSIGNED NOT NULL DEFAULT 0',
                'sentiment_analysis' => "ALTER TABLE ojt_journal_entries ADD COLUMN sentiment_analysis VARCHAR(50) NOT NULL DEFAULT 'neutral'",
                'productivity_score' => 'ALTER TABLE ojt_journal_entries ADD COLUMN productivity_score TINYINT UNSIGNED NOT NULL DEFAULT 0',
                'is_visible_to_adviser' => 'ALTER TABLE ojt_journal_entries ADD COLUMN is_visible_to_adviser TINYINT(1) NOT NULL DEFAULT 1 AFTER is_structured',
            ];

            foreach ($columns as $columnName => $alterSql) {
                if (!skillhive_schema_column_exists($pdo, 'ojt_journal_entries', $columnName)) {
                    $pdo->exec($alterSql);
                }
            }
        }

        if (!skillhive_schema_table_exists($pdo, 'ojt_final_reports')) {
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
    }
}

if (!function_exists('skillhive_run_runtime_schema_maintenance')) {
    function skillhive_run_runtime_schema_maintenance(PDO $pdo): array
    {
        $results = [];

        skillhive_schema_run_step($results, 'application_ojt_triggers', function () use ($pdo): void {
            skillhive_schema_sync_application_ojt_triggers($pdo);
        });

        skillhive_schema_run_step($results, 'ojt_required_hours_policy', function () use ($pdo): void {
            $requiredOjtHours = (float)SKILLHIVE_REQUIRED_OJT_HOURS;
            $stmt = $pdo->prepare(
                'UPDATE ojt_record
                 SET hours_required = ?, updated_at = NOW()
                 WHERE COALESCE(hours_required, 0) < ?'
            );
            $stmt->execute([$requiredOjtHours, $requiredOjtHours]);
        });

        skillhive_schema_run_step($results, 'interview_time_column', function () use ($pdo): void {
            if (!skillhive_schema_column_exists($pdo, 'interview', 'interview_time')) {
                $pdo->exec(
                    "ALTER TABLE interview
                     ADD COLUMN interview_time TIME NOT NULL DEFAULT '09:00:00'
                     AFTER interview_date"
                );
            }
        });

        skillhive_schema_run_step($results, 'student_required_columns', function () use ($pdo): void {
            if (!skillhive_schema_column_exists($pdo, 'student', 'must_change_password')) {
                $pdo->exec(
                    'ALTER TABLE student
                     ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
                     AFTER password_hash'
                );
            }

            if (!skillhive_schema_column_exists($pdo, 'student', 'email_notifications_enabled')) {
                $pdo->exec(
                    'ALTER TABLE student
                     ADD COLUMN email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1
                     AFTER must_change_password'
                );
            }

            if (!skillhive_schema_column_exists($pdo, 'student', 'academic_year')) {
                $pdo->exec(
                    "ALTER TABLE student
                     ADD COLUMN academic_year VARCHAR(20) NOT NULL DEFAULT ''
                     AFTER year_level"
                );
            }
        });

        skillhive_schema_run_step($results, 'employer_contact_person_column', function () use ($pdo): void {
            if (!skillhive_schema_column_exists($pdo, 'employer', 'contact_person_name')) {
                $pdo->exec(
                    "ALTER TABLE employer
                     ADD COLUMN contact_person_name VARCHAR(160) NOT NULL DEFAULT ''
                     AFTER company_name"
                );
            }
        });

        skillhive_schema_run_step($results, 'journal_tables', function () use ($pdo): void {
            skillhive_schema_create_journal_tables($pdo);
        });

        skillhive_schema_run_step($results, 'adviser_profile_picture_column', function () use ($pdo): void {
            if (!skillhive_schema_column_exists($pdo, 'internship_adviser', 'profile_picture')) {
                $pdo->exec('ALTER TABLE internship_adviser ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL');
            }
        });

        return $results;
    }
}
