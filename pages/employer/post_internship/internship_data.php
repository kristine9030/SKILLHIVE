<?php
/**
 * Purpose: Post-internship loader that wires together auth, skill lookup, validation, create, postings, dashboard summary, and applicant query helpers.
 * Tables/columns used: Delegates to modules that use skill(skill_id, skill_name), internship(internship_id, employer_id, title, description, duration_weeks, allowance, work_setup, location, slots_available, status, posted_at, created_at), internship_skill(internship_id, skill_id, required_level, is_mandatory), application(application_id, internship_id, student_id, status, compatibility_score, application_date), student(student_id, first_name, last_name), interview(application_id).
 */

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/skill_queries.php';
require_once __DIR__ . '/posting_validation.php';
require_once __DIR__ . '/posting_create.php';
require_once __DIR__ . '/posting_delete.php';
require_once __DIR__ . '/posting_update.php';
require_once __DIR__ . '/postings_query.php';
require_once __DIR__ . '/dashboard_query.php';
require_once __DIR__ . '/applicants_query.php';
