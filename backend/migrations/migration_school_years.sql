-- ============================================================================
-- Migration: School Year Lifecycle Management System
-- Purpose: Add school year tracking and archiving functionality
-- ============================================================================

-- 1. Create school_years table
CREATE TABLE IF NOT EXISTS `school_years` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_year` VARCHAR(9) UNIQUE NOT NULL COMMENT '2024-2025 format',
    `status` ENUM('Active', 'Archived') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_school_year` (`school_year`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add school_year_id to student table if not already present
ALTER TABLE `student` ADD COLUMN IF NOT EXISTS `school_year_id` INT DEFAULT NULL AFTER `student_id`;
ALTER TABLE `student` ADD FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`) ON DELETE SET NULL;
ALTER TABLE `student` ADD INDEX IF NOT EXISTS `idx_student_school_year_id` (`school_year_id`);

-- 3. Add archived_at column to student table if not already present
ALTER TABLE `student` ADD COLUMN IF NOT EXISTS `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `student` ADD INDEX IF NOT EXISTS `idx_student_archived_at` (`archived_at`);

-- 4. Create student_archive_history table for tracking historical data
CREATE TABLE IF NOT EXISTS `student_archive_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(10) UNSIGNED NOT NULL,
    `school_year_id` INT NOT NULL,
    `internship_status` VARCHAR(50),
    `hours_completed` DECIMAL(8, 2),
    `completion_status` VARCHAR(50),
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `student`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`) ON DELETE CASCADE,
    INDEX `idx_history_student_id` (`student_id`),
    INDEX `idx_history_school_year_id` (`school_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed initial active school year if no school years exist
INSERT IGNORE INTO `school_years` (`school_year`, `status`) 
VALUES ('2024-2025', 'Active');

-- 6. Update existing students to have current school year if null
UPDATE `student` 
SET `school_year_id` = (SELECT `id` FROM `school_years` WHERE `status` = 'Active' LIMIT 1)
WHERE `school_year_id` IS NULL;

-- ============================================================================
-- End Migration
-- ============================================================================
