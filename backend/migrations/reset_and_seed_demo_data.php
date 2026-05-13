<?php
/**
 * Reset SkillHive demo data while preserving admin accounts.
 *
 * CLI usage:
 *   php backend/migrations/reset_and_seed_demo_data.php --yes
 *
 * Browser usage:
 *   Log in as admin, then open:
 *   backend/migrations/reset_and_seed_demo_data.php?confirm=RESET_DEMO_DATA
 */

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/runtime_schema_maintenance.php';

const DEMO_PASSWORD = 'Password123!';

$isCli = PHP_SAPI === 'cli';
$confirmed = $isCli
    ? in_array('--yes', $argv ?? [], true)
    : ((string)($_GET['confirm'] ?? '') === 'RESET_DEMO_DATA');

if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role !== 'admin') {
        http_response_code(403);
        echo "Forbidden. Log in as admin first.\n";
        exit;
    }
}

if (!$confirmed) {
    echo "This will delete all non-admin rows and seed demo data.\n";
    echo "Admin accounts are preserved.\n";
    echo $isCli
        ? "Run with --yes to continue.\n"
        : "Add ?confirm=RESET_DEMO_DATA to continue.\n";
    exit($isCli ? 1 : 0);
}

function demo_qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function demo_insert(PDO $pdo, string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = 'INSERT INTO ' . demo_qi($table)
        . ' (' . implode(', ', array_map('demo_qi', $columns)) . ') VALUES ('
        . implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))
        . ')';

    $stmt = $pdo->prepare($sql);
    foreach ($data as $column => $value) {
        $stmt->bindValue(':' . $column, $value);
    }
    $stmt->execute();

    return (int)$pdo->lastInsertId();
}

function demo_table_count(PDO $pdo, string $table): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . demo_qi($table))->fetchColumn();
}

function demo_json(array $value): string
{
    return (string)json_encode($value, JSON_UNESCAPED_SLASHES);
}

function demo_date(int $daysFromToday): string
{
    return date('Y-m-d', strtotime(($daysFromToday >= 0 ? '+' : '') . $daysFromToday . ' days'));
}

function demo_datetime(int $daysFromToday, string $time = '09:00:00'): string
{
    return demo_date($daysFromToday) . ' ' . $time;
}

function demo_skill_ids(array $skillMap, array $names): array
{
    $ids = [];
    foreach ($names as $name) {
        if (isset($skillMap[$name])) {
            $ids[] = $skillMap[$name];
        }
    }
    return $ids;
}

function demo_pipeline_stage(int $index): array
{
    $bucket = $index % 20;

    if ($bucket === 0) {
        return [
            'application_status' => 'Pending',
            'availability_status' => 'Available',
            'endorsement_status' => 'Pending',
            'moa_status' => 'Not Started',
            'interview_status' => null,
            'ojt_status' => null,
        ];
    }

    if ($bucket <= 3) {
        return [
            'application_status' => 'Shortlisted',
            'availability_status' => 'Available',
            'endorsement_status' => 'Pending',
            'moa_status' => 'Not Started',
            'interview_status' => null,
            'ojt_status' => null,
        ];
    }

    if ($bucket <= 7) {
        return [
            'application_status' => 'Interview Scheduled',
            'availability_status' => 'Available',
            'endorsement_status' => 'Approved',
            'moa_status' => 'In Progress',
            'interview_status' => 'Scheduled',
            'ojt_status' => null,
        ];
    }

    if ($bucket === 8) {
        return [
            'application_status' => 'Rejected',
            'availability_status' => 'Available',
            'endorsement_status' => 'Rejected',
            'moa_status' => 'Not Started',
            'interview_status' => null,
            'ojt_status' => null,
        ];
    }

    if ($bucket <= 17) {
        return [
            'application_status' => 'Accepted',
            'availability_status' => 'Currently Interning',
            'endorsement_status' => 'Approved',
            'moa_status' => $bucket % 3 === 0 ? 'In Progress' : 'Signed',
            'interview_status' => 'Done',
            'ojt_status' => 'Ongoing',
        ];
    }

    return [
        'application_status' => 'Accepted',
        'availability_status' => 'Available',
        'endorsement_status' => 'Approved',
        'moa_status' => 'Signed',
        'interview_status' => 'Done',
        'ojt_status' => 'Completed',
    ];
}

function demo_insert_student_requirements(PDO $pdo, int $studentId, int $internshipId, array $requirements, int $adviserId, bool $completed): void
{
    $index = 0;
    foreach ($requirements as $requirement) {
        if ((string)$requirement['applicable_to'] !== 'Student') {
            continue;
        }

        $index++;
        $status = $completed || $index <= 4 ? 'Approved' : ($index <= 6 ? 'Submitted' : 'Pending');
        $submittedAt = $status !== 'Pending' ? demo_datetime(-20 + $index, '10:00:00') : null;
        $reviewedAt = $status === 'Approved' ? demo_datetime(-18 + $index, '15:00:00') : null;

        demo_insert($pdo, 'student_requirement', [
            'student_id' => $studentId,
            'internship_id' => $internshipId,
            'requirement_id' => (int)$requirement['requirement_id'],
            'status' => $status,
            'file_data' => null,
            'file_name' => null,
            'file_mime' => null,
            'file_size' => null,
            'submitted_at' => $submittedAt,
            'reviewed_at' => $reviewedAt,
            'reviewed_by' => $status === 'Approved' ? $adviserId : null,
            'notes' => $status === 'Pending' ? 'For follow-up submission.' : 'Demo requirement record.',
            'deadline' => demo_date(20 + $index),
        ]);
    }
}

function demo_insert_logs_and_journals(PDO $pdo, int $recordId, string $companyName, bool $completed): void
{
    $logIds = [];
    $logCount = $completed ? 8 : 5;
    for ($i = 0; $i < $logCount; $i++) {
        $logIds[] = demo_insert($pdo, 'daily_log', [
            'record_id' => $recordId,
            'log_date' => demo_date(-($logCount - $i)),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'accomplishment' => 'Completed assigned internship tasks, coordinated with the supervisor, and documented progress for the day.',
            'hours_rendered' => 8.00,
            'mood_tag' => $i % 3 === 0 ? 'Productive' : ($i % 3 === 1 ? 'Neutral' : 'Challenging'),
            'task_file' => null,
        ]);
    }

    for ($i = 0; $i < 2; $i++) {
        demo_insert($pdo, 'ojt_journal_entries', [
            'record_id' => $recordId,
            'log_ids' => implode(',', array_slice($logIds, $i * 2, 2)),
            'entry_date' => demo_date(-($i + 2)),
            'company_department' => $companyName . ' - Internship Team',
            'tasks_accomplished' => 'Prepared task updates, reviewed requirements, and assisted the team with assigned deliverables.',
            'skills_applied_learned' => 'Applied communication, documentation, technical problem solving, and workflow coordination.',
            'challenges_encountered' => 'Needed to clarify unfamiliar tools and align output with the company standard.',
            'solutions_actions_taken' => 'Asked the supervisor for guidance, reviewed examples, and revised the output.',
            'key_learnings_insights' => 'Learned the value of clear communication and consistent documentation.',
            'reflection' => 'The internship task helped connect classroom knowledge with actual workplace expectations.',
            'quality_score' => 82 + $i,
            'sentiment_analysis' => 'positive',
            'productivity_score' => 84 + $i,
            'is_structured' => 1,
            'is_visible_to_adviser' => 1,
        ]);
    }
}

function demo_insert_final_report(PDO $pdo, int $recordId): void
{
    demo_insert($pdo, 'ojt_final_reports', [
        'record_id' => $recordId,
        'internship_overview' => 'Completed a structured internship focused on applied information technology work.',
        'key_responsibilities' => 'Supported daily operations, created documentation, completed assigned technical tasks, and reported progress.',
        'skills_developed' => 'Improved technical execution, communication, analysis, and professional accountability.',
        'challenges_resolutions' => 'Resolved unfamiliar process issues by researching, asking for feedback, and validating outputs.',
        'contributions_achievements' => 'Delivered assigned outputs on schedule and contributed to team documentation.',
        'personal_professional_growth' => 'Built confidence in workplace communication and independent problem solving.',
        'conclusion_reflection' => 'The internship provided practical exposure and strengthened career readiness.',
        'total_journal_entries' => 2,
        'duration_days' => 70,
    ]);
}

try {
    skillhive_run_runtime_schema_maintenance($pdo);

    $adminId = (int)($pdo->query('SELECT admin_id FROM admin ORDER BY admin_id ASC LIMIT 1')->fetchColumn() ?: 0);
    $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

    $baseTables = [];
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
        $tableName = (string)array_values($row)[0];
        if ($tableName !== '' && strtolower($tableName) !== 'admin') {
            $baseTables[] = $tableName;
        }
    }

    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($baseTables as $tableName) {
        $pdo->exec('DELETE FROM ' . demo_qi($tableName));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $schoolYearId = demo_insert($pdo, 'school_years', [
        'school_year' => '2025-2026',
        'status' => 'Active',
    ]);

    $advisers = [
        ['John Dave', 'Briones'],
        ['Mikaela', 'Santos'],
        ['Ramon', 'Dela Cruz'],
        ['Clarisse', 'Reyes'],
        ['Patrick', 'Garcia'],
        ['Aileen', 'Villanueva'],
        ['Marco', 'Torres'],
        ['Bianca', 'Lopez'],
        ['Nathaniel', 'Rivera'],
        ['Camille', 'Mendoza'],
    ];
    $adviserIds = [];
    foreach ($advisers as $i => $name) {
        $adviserIds[] = demo_insert($pdo, 'internship_adviser', [
            'first_name' => $name[0],
            'last_name' => $name[1],
            'department' => 'College of Informatics and Computing Sciences',
            'email' => sprintf('adviser%02d@skillhive.test', $i + 1),
            'password_hash' => $passwordHash,
            'profile_picture' => null,
        ]);
    }
    $demoAdviserId = $adviserIds[0];

    $skills = [
        ['PHP', 'Technical', 'Web Development'],
        ['Laravel', 'Technical', 'Web Development'],
        ['JavaScript', 'Technical', 'Web Development'],
        ['React', 'Technical', 'Web Development'],
        ['HTML/CSS', 'Technical', 'Web Development'],
        ['Python', 'Technical', 'Programming'],
        ['Java', 'Technical', 'Programming'],
        ['SQL', 'Technical', 'Database'],
        ['MySQL', 'Technical', 'Database'],
        ['Data Analysis', 'Technical', 'Business Analytics'],
        ['Power BI', 'Technical', 'Business Analytics'],
        ['Excel', 'Technical', 'Business Analytics'],
        ['Business Process Mapping', 'Technical', 'Business Analytics'],
        ['UI/UX Design', 'Technical', 'Design'],
        ['Figma', 'Technical', 'Design'],
        ['Quality Assurance', 'Technical', 'Software Testing'],
        ['Git', 'Technical', 'Development Tools'],
        ['Networking Fundamentals', 'Technical', 'Networking'],
        ['Cisco Routing', 'Technical', 'Networking'],
        ['Network Security', 'Technical', 'Cybersecurity'],
        ['Linux Administration', 'Technical', 'Systems'],
        ['Cloud Computing', 'Technical', 'Cloud'],
        ['Technical Support', 'Technical', 'IT Support'],
        ['Troubleshooting', 'Technical', 'IT Support'],
        ['Communication', 'Soft', 'Professional'],
        ['Teamwork', 'Soft', 'Professional'],
        ['Problem Solving', 'Soft', 'Professional'],
        ['Time Management', 'Soft', 'Professional'],
        ['Critical Thinking', 'Soft', 'Professional'],
        ['Documentation', 'Soft', 'Professional'],
    ];
    $skillMap = [];
    foreach ($skills as $skill) {
        $skillMap[$skill[0]] = demo_insert($pdo, 'skill', [
            'skill_name' => $skill[0],
            'skill_type' => $skill[1],
            'skill_category' => $skill[2],
        ]);
    }

    $requirementRows = [
        ['Endorsement Letter', 'Official endorsement from the university.', 'Pre-OJT', 1, 'Student', 1],
        ['Resume or CV', 'Updated resume or curriculum vitae.', 'Pre-OJT', 1, 'Student', 2],
        ['Parent Consent Form', 'Signed consent form for internship participation.', 'Pre-OJT', 1, 'Student', 3],
        ['Medical Certificate', 'Basic health clearance for internship deployment.', 'Pre-OJT', 1, 'Student', 4],
        ['Daily Time Record', 'Rendered hours documentation.', 'During OJT', 1, 'Student', 5],
        ['Weekly Accomplishment Report', 'Weekly summary of internship tasks.', 'During OJT', 1, 'Student', 6],
        ['Final Narrative Report', 'Post-OJT final report.', 'Post-OJT', 1, 'Student', 7],
        ['Employer Evaluation', 'Evaluation form completed by company supervisor.', 'Post-OJT', 1, 'Both', 8],
    ];
    $requirements = [];
    foreach ($requirementRows as $row) {
        $id = demo_insert($pdo, 'requirement', [
            'name' => $row[0],
            'description' => $row[1],
            'phase' => $row[2],
            'is_mandatory' => $row[3],
            'applicable_to' => $row[4],
            'sort_order' => $row[5],
        ]);
        $requirements[] = [
            'requirement_id' => $id,
            'name' => $row[0],
            'applicable_to' => $row[4],
        ];
    }

    $employers = [
        ['NexaSoft Solutions', 'Software Development', 'Arielle Cruz'],
        ['DataNest Analytics', 'Business Analytics', 'Miguel Salcedo'],
        ['CloudBridge PH', 'Cloud Services', 'Rhea Marquez'],
        ['NetSecure Systems', 'Cybersecurity', 'Jonas Valdez'],
        ['ByteWorks Digital', 'Web Development', 'Dianne Mercado'],
        ['InsightGrid Consulting', 'Business Analytics', 'Paolo Robles'],
        ['LinkCore Networks', 'Network Infrastructure', 'Hazel Tan'],
        ['AppForge Labs', 'Mobile Development', 'Kevin Yu'],
        ['QA Harbor Technologies', 'Software Testing', 'Leah Bautista'],
        ['TechAid Support Center', 'IT Support', 'Carlo Lim'],
        ['FinSight BI', 'Financial Technology', 'Erika Santos'],
        ['EduTech Hive', 'Education Technology', 'Nico Reyes'],
        ['RetailLogic Systems', 'Retail Technology', 'Grace Villamor'],
        ['HealthSync Digital', 'Healthcare Technology', 'Marvin Ong'],
        ['AgileOps Manila', 'DevOps', 'Patricia Ramos'],
        ['DesignPilot Studio', 'UX Design', 'Sam Villarin'],
        ['MetroCloud Data Center', 'Cloud Services', 'Victor Cruz'],
        ['SecurePath Networks', 'Network Infrastructure', 'Angela Lao'],
        ['ProcessWise Analytics', 'Business Analytics', 'Lorenzo Dee'],
        ['CodeCraft Innovations', 'Software Development', 'Ivy Fernandez'],
    ];
    $employerIds = [];
    foreach ($employers as $i => $employer) {
        $verification = $i < 16 ? 'Approved' : ($i % 2 === 0 ? 'Pending' : 'Flagged');
        $badge = $i < 8 ? 'Verified Partner' : ($i < 12 ? 'Top Employer' : 'None');
        $employerId = demo_insert($pdo, 'employer', [
            'company_name' => $employer[0],
            'industry' => $employer[1],
            'company_address' => (101 + $i) . ' Demo Business Center, Batangas City, Philippines',
            'email' => sprintf('employer%02d@skillhive.test', $i + 1),
            'contact_number' => '0917' . str_pad((string)(8000000 + $i), 7, '0', STR_PAD_LEFT),
            'password_hash' => $passwordHash,
            'verification_status' => $verification,
            'company_badge_status' => $badge,
            'company_logo' => null,
            'website_url' => 'https://demo-company-' . ($i + 1) . '.example.com',
            'contact_person_name' => $employer[2],
        ]);
        $employerIds[] = $employerId;

        demo_insert($pdo, 'company_verification', [
            'employer_id' => $employerId,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'status' => $verification,
            'risk_assessment_notes' => $verification === 'Approved' ? 'Demo company verified for internship deployment.' : 'Demo company awaiting final document review.',
            'document_file' => 'demo_company_' . ($i + 1) . '_documents.pdf',
            'date_reviewed' => $verification === 'Approved' ? demo_datetime(-25, '14:00:00') : null,
        ]);
    }

    $postingTemplates = [
        'Business Analytics' => [
            ['Business Analytics Intern', ['SQL', 'Excel', 'Power BI', 'Data Analysis'], 'Assist with dashboards, reporting, and business process insights.'],
            ['Data Reporting Intern', ['Data Analysis', 'Python', 'SQL', 'Documentation'], 'Prepare reports, validate data, and present operational insights.'],
        ],
        'Software Development' => [
            ['Web Developer Intern', ['PHP', 'JavaScript', 'HTML/CSS', 'Git'], 'Build and maintain internal web modules with the development team.'],
            ['Frontend Developer Intern', ['React', 'JavaScript', 'UI/UX Design', 'Git'], 'Implement user interfaces and improve web application interactions.'],
        ],
        'Web Development' => [
            ['Full Stack Intern', ['PHP', 'Laravel', 'JavaScript', 'MySQL'], 'Support full stack feature development and bug fixing.'],
            ['QA Automation Intern', ['Quality Assurance', 'JavaScript', 'Documentation', 'Problem Solving'], 'Test web features and document defects clearly.'],
        ],
        'Network Infrastructure' => [
            ['Network Operations Intern', ['Networking Fundamentals', 'Cisco Routing', 'Troubleshooting', 'Documentation'], 'Assist with network monitoring, configuration, and support tickets.'],
            ['Infrastructure Support Intern', ['Linux Administration', 'Network Security', 'Technical Support', 'Communication'], 'Support infrastructure maintenance and incident documentation.'],
        ],
        'Cybersecurity' => [
            ['Cybersecurity Intern', ['Network Security', 'Linux Administration', 'Troubleshooting', 'Documentation'], 'Assist with vulnerability checks, access reviews, and security reports.'],
            ['Security Operations Intern', ['Network Security', 'Networking Fundamentals', 'Critical Thinking', 'Communication'], 'Monitor alerts and document incident response tasks.'],
        ],
        'Cloud Services' => [
            ['Cloud Support Intern', ['Cloud Computing', 'Linux Administration', 'Networking Fundamentals', 'Troubleshooting'], 'Support cloud environments and deployment documentation.'],
            ['Systems Intern', ['Linux Administration', 'Cloud Computing', 'Technical Support', 'Documentation'], 'Assist with systems administration and cloud monitoring.'],
        ],
        'DevOps' => [
            ['DevOps Intern', ['Git', 'Linux Administration', 'Cloud Computing', 'Problem Solving'], 'Assist with CI/CD routines, deployment checks, and release notes.'],
            ['Platform Operations Intern', ['Linux Administration', 'Cloud Computing', 'Documentation', 'Communication'], 'Support platform monitoring and operational runbooks.'],
        ],
        'UX Design' => [
            ['UI/UX Design Intern', ['UI/UX Design', 'Figma', 'Communication', 'Critical Thinking'], 'Create wireframes, gather feedback, and improve user flows.'],
            ['Product Design Intern', ['Figma', 'UI/UX Design', 'Documentation', 'Teamwork'], 'Support product design documentation and usability reviews.'],
        ],
        'default' => [
            ['IT Support Intern', ['Technical Support', 'Troubleshooting', 'Communication', 'Documentation'], 'Support day-to-day IT operations and user assistance.'],
            ['Systems Documentation Intern', ['Documentation', 'Problem Solving', 'Excel', 'Communication'], 'Prepare process guides and assist with technical reports.'],
        ],
    ];

    $internshipIdsByTrack = [
        'Business Analytics' => [],
        'Networking' => [],
    ];
    $internshipCompany = [];
    foreach ($employers as $i => $employer) {
        $industry = $employer[1];
        $templates = $postingTemplates[$industry] ?? $postingTemplates['default'];
        foreach ($templates as $j => $template) {
            $isOpen = !($i >= 16 && $j === 1);
            $internshipId = demo_insert($pdo, 'internship', [
                'employer_id' => $employerIds[$i],
                'title' => $template[0],
                'description' => $template[2] . ' This demo posting is aligned with SkillHive student skills and OJT requirements.',
                'duration_weeks' => 12 + (($i + $j) % 5),
                'allowance' => (float)(2000 + (($i + $j) % 5) * 1000),
                'work_setup' => ['On-site', 'Hybrid', 'Remote'][($i + $j) % 3],
                'location' => ['Batangas City', 'Lipa City', 'Tanauan City', 'Malvar'][($i + $j) % 4] . ', Batangas',
                'slots_available' => 3 + (($i + $j) % 4),
                'status' => $isOpen ? 'Open' : 'Draft',
                'posted_at' => demo_datetime(-($i + $j + 1), '08:30:00'),
            ]);
            $internshipCompany[$internshipId] = $employer[0];

            $skillIds = demo_skill_ids($skillMap, $template[1]);
            foreach ($skillIds as $k => $skillId) {
                demo_insert($pdo, 'internship_skill', [
                    'internship_id' => $internshipId,
                    'skill_id' => $skillId,
                    'required_level' => $k < 2 ? 'Intermediate' : 'Beginner',
                    'is_mandatory' => $k < 3 ? 1 : 0,
                ]);
            }

            $titleLower = strtolower($template[0] . ' ' . $industry);
            if (str_contains($titleLower, 'analytics') || str_contains($titleLower, 'data') || str_contains($titleLower, 'report') || str_contains($titleLower, 'design')) {
                $internshipIdsByTrack['Business Analytics'][] = $internshipId;
            }
            if (str_contains($titleLower, 'network') || str_contains($titleLower, 'security') || str_contains($titleLower, 'cloud') || str_contains($titleLower, 'support') || str_contains($titleLower, 'systems') || str_contains($titleLower, 'devops')) {
                $internshipIdsByTrack['Networking'][] = $internshipId;
            }
            if (str_contains($titleLower, 'developer') || str_contains($titleLower, 'full stack') || str_contains($titleLower, 'qa')) {
                $internshipIdsByTrack['Business Analytics'][] = $internshipId;
                $internshipIdsByTrack['Networking'][] = $internshipId;
            }
        }
    }

    $firstNames = ['Andrea','Miguel','Bianca','Joshua','Camille','Gabriel','Denise','Karl','Elaine','Francis','Hannah','Ivan','Jasmine','Kevin','Lara','Mark','Nicole','Owen','Patricia','Quinn'];
    $lastNames = ['Santos','Reyes','Garcia','Cruz','Mendoza','Torres','Flores','Ramos','Dela Cruz','Bautista','Villanueva','Castillo','Rivera','Gonzales','Aquino','Navarro','Morales','Domingo','Salazar','Valdez'];
    $sections = [
        ['Business Analytics', '01'],
        ['Business Analytics', '02'],
        ['Business Analytics', '03'],
        ['Networking', '01'],
        ['Networking', '02'],
    ];
    $commonStudentSkills = ['Communication', 'Teamwork', 'Problem Solving', 'Time Management', 'Documentation'];
    $trackSkills = [
        'Business Analytics' => ['SQL', 'Excel', 'Power BI', 'Data Analysis', 'Business Process Mapping', 'Python'],
        'Networking' => ['Networking Fundamentals', 'Cisco Routing', 'Network Security', 'Linux Administration', 'Cloud Computing', 'Troubleshooting'],
    ];

    $students = [];
    for ($i = 0; $i < 100; $i++) {
        $adviserId = $demoAdviserId;
        $stage = demo_pipeline_stage($i);
        $sectionInfo = $sections[$i % count($sections)];
        $track = $sectionInfo[0];
        $section = $sectionInfo[1];
        $studentNumber = '25-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT);
        $firstName = $firstNames[$i % count($firstNames)];
        $lastName = $lastNames[(intdiv($i, count($firstNames)) + $i) % count($lastNames)];
        $readiness = 72 + ($i % 25);

        $studentId = demo_insert($pdo, 'student', [
            'school_year_id' => $schoolYearId,
            'student_number' => $studentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($studentNumber) . '@g.batstate-u.edu.ph',
            'program' => 'BS Information Technology',
            'department' => 'IT',
            'track' => $track,
            'section' => $section,
            'year_level' => 4,
            'academic_year' => '2025-2026',
            'password_hash' => $passwordHash,
            'must_change_password' => 0,
            'email_notifications_enabled' => 1,
            'availability_status' => (string)$stage['availability_status'],
            'account_status' => 'Active',
            'preferred_industry' => $track === 'Business Analytics' ? 'Business Analytics' : 'Network Infrastructure',
            'resume_file' => null,
            'internship_readiness_score' => $readiness,
            'profile_picture' => null,
            'cover_photo' => null,
            'cover_gradient' => 'linear-gradient(135deg,#12b3ac,#111827)',
            'avatar_preset' => 'initials',
            'google_url' => 'https://profiles.example.com/' . strtolower(str_replace('-', '', $studentNumber)),
            'gmail_url' => 'https://mail.google.com/mail/?view=cm&to=' . strtolower($studentNumber) . '@g.batstate-u.edu.ph',
            'discord_url' => 'https://discord.gg/demo-' . ($i + 1),
            'dribbble_url' => 'https://dribbble.com/demo_student_' . ($i + 1),
            'behance_url' => 'https://behance.net/demo_student_' . ($i + 1),
            'portfolio_url' => 'https://portfolio-demo-' . ($i + 1) . '.example.com',
            'linkedin_url' => 'https://linkedin.com/in/demo-student-' . ($i + 1),
            'github_url' => 'https://github.com/demo-student-' . ($i + 1),
            'about_me_intro' => 'Fourth-year BSIT student focused on ' . strtolower($track) . ' and practical internship readiness.',
            'about_me_points' => demo_json([
                'Interested in applied IT work and continuous learning.',
                'Comfortable working with team tasks, documentation, and supervisor feedback.',
                'Prepared to contribute to real workplace projects during OJT.',
            ]),
            'experience_entries' => demo_json([
                ['title' => 'Capstone Team Member', 'company' => 'BatStateU Demo Project', 'period' => '2025', 'description' => 'Worked with classmates to design, document, and present an IT solution.'],
            ]),
            'portfolio_entries' => demo_json([
                ['title' => $track . ' Portfolio', 'url' => 'https://portfolio-demo-' . ($i + 1) . '.example.com', 'description' => 'Demo portfolio with coursework, project samples, and OJT goals.'],
            ]),
        ]);

        demo_insert($pdo, 'adviser_assignment', [
            'adviser_id' => $adviserId,
            'student_id' => $studentId,
            'assigned_date' => demo_date(-45),
            'status' => 'Active',
        ]);

        $skillNames = array_slice($trackSkills[$track], $i % 2, 4);
        $skillNames = array_merge($skillNames, array_slice($commonStudentSkills, $i % 2, 3));
        foreach (array_unique($skillNames) as $skillIndex => $skillName) {
            demo_insert($pdo, 'student_skill', [
                'student_id' => $studentId,
                'skill_id' => $skillMap[$skillName],
                'skill_level' => $skillIndex < 2 ? 'Advanced' : 'Intermediate',
                'verified' => $skillIndex < 4 ? 1 : 0,
            ]);
        }

        $students[] = [
            'student_id' => $studentId,
            'adviser_id' => $adviserId,
            'track' => $track,
            'stage' => $stage,
            'student_number' => $studentNumber,
            'name' => $firstName . ' ' . $lastName,
            'skill_names' => $skillNames,
        ];
    }

    foreach ($students as $i => $student) {
        $track = $student['track'];
        $trackInternships = $internshipIdsByTrack[$track] ?: array_merge(...array_values($internshipIdsByTrack));
        $internshipId = $trackInternships[$i % count($trackInternships)];
        $companyName = $internshipCompany[$internshipId] ?? 'Demo Company';
        $stage = is_array($student['stage'] ?? null) ? $student['stage'] : demo_pipeline_stage($i);
        $applicationStatus = (string)$stage['application_status'];
        $hasOjt = $applicationStatus === 'Accepted';
        $completed = (string)($stage['ojt_status'] ?? '') === 'Completed';
        $score = 82 + ($i % 17);

        $applicationId = demo_insert($pdo, 'application', [
            'student_id' => $student['student_id'],
            'internship_id' => $internshipId,
            'application_date' => demo_date(-35 + ($i % 8)),
            'status' => $applicationStatus,
            'compatibility_score' => $score,
            'cover_letter' => 'I am interested in this internship because my skills in ' . implode(', ', array_slice($student['skill_names'], 0, 3)) . ' match the role requirements. I am ready to contribute and learn from your team.',
            'consented_at' => demo_datetime(-35 + ($i % 8), '09:30:00'),
            'consent_version' => '2026-demo',
            'compliance_snapshot' => demo_json(['source' => 'demo_seed', 'student_number' => $student['student_number']]),
            'resume_link_snapshot' => 'https://documents.example.com/resumes/' . $student['student_number'] . '.pdf',
            'profile_link_snapshot' => 'https://skillhive.example.com/profile/' . $student['student_id'],
        ]);

        demo_insert($pdo, 'endorsement', [
            'application_id' => $applicationId,
            'adviser_id' => $student['adviser_id'],
            'status' => (string)$stage['endorsement_status'],
            'endorsement_file' => in_array((string)$stage['endorsement_status'], ['Approved', 'Rejected'], true) ? 'endorsement_' . $student['student_number'] . '.pdf' : null,
            'moa_status' => (string)$stage['moa_status'],
            'notes' => $applicationStatus === 'Rejected'
                ? 'Demo endorsement rejected after review.'
                : 'Demo endorsement record for matched internship placement.',
            'reviewed_at' => (string)$stage['endorsement_status'] === 'Pending' ? null : demo_datetime(-30 + ($i % 5), '13:00:00'),
        ]);

        demo_insert($pdo, 'ai_match_log', [
            'student_id' => $student['student_id'],
            'internship_id' => $internshipId,
            'match_score' => $score,
            'skill_gap_summary' => demo_json([
                'matched_skills' => array_slice($student['skill_names'], 0, 4),
                'recommendation' => 'Strong fit for demo internship placement.',
            ]),
        ]);

        if (!empty($stage['interview_status'])) {
            demo_insert($pdo, 'interview', [
                'application_id' => $applicationId,
                'interview_date' => (string)$stage['interview_status'] === 'Scheduled'
                    ? demo_datetime(3 + ($i % 7), '10:00:00')
                    : demo_datetime(-28 + ($i % 6), '10:00:00'),
                'interview_time' => '10:00:00',
                'interview_mode' => $i % 2 === 0 ? 'Online' : 'In-Person',
                'interview_status' => (string)$stage['interview_status'],
                'meeting_link' => 'https://meet.example.com/skillhive-demo-' . $applicationId,
                'venue' => $i % 2 === 0 ? null : $companyName . ' Office',
                'notes' => (string)$stage['interview_status'] === 'Scheduled'
                    ? 'Demo interview is scheduled and awaiting completion.'
                    : 'Demo interview completed successfully.',
            ]);
        }

        if (!$hasOjt) {
            continue;
        }

        $recordStmt = $pdo->prepare('SELECT record_id FROM ojt_record WHERE student_id = ? AND internship_id = ? ORDER BY record_id DESC LIMIT 1');
        $recordStmt->execute([$student['student_id'], $internshipId]);
        $recordId = (int)($recordStmt->fetchColumn() ?: 0);
        if ($recordId <= 0) {
            $recordId = demo_insert($pdo, 'ojt_record', [
                'student_id' => $student['student_id'],
                'internship_id' => $internshipId,
                'hours_required' => 500.00,
                'hours_completed' => 0.00,
                'start_date' => demo_date(-25),
                'end_date' => demo_date(50),
                'completion_status' => 'Ongoing',
            ]);
        }

        $hoursCompleted = $completed ? 500.00 : (80 + (($i % 8) * 32));
        if (($student['student_number'] ?? '') === '25-00091') {
            $hoursCompleted = 492.00;
        }
        $updateRecord = $pdo->prepare(
            'UPDATE ojt_record
             SET hours_required = 500.00,
                 hours_completed = ?,
                 start_date = ?,
                 end_date = ?,
                 completion_status = ?
             WHERE record_id = ?'
        );
        $updateRecord->execute([
            $hoursCompleted,
            demo_date(-25 - ($i % 5)),
            $completed ? demo_date(-2) : demo_date(50 + ($i % 10)),
            $completed ? 'Completed' : 'Ongoing',
            $recordId,
        ]);

        demo_insert_logs_and_journals($pdo, $recordId, $companyName, $completed);
        demo_insert_student_requirements($pdo, $student['student_id'], $internshipId, $requirements, $student['adviser_id'], $completed);

        if ($completed) {
            demo_insert($pdo, 'employer_evaluation', [
                'student_id' => $student['student_id'],
                'internship_id' => $internshipId,
                'employer_id' => (int)$pdo->query('SELECT employer_id FROM internship WHERE internship_id = ' . (int)$internshipId)->fetchColumn(),
                'technical_score' => 4.40,
                'behavioral_score' => 4.60,
                'comments' => 'Demo completed intern performed reliably and communicated well.',
                'recommendation_status' => 'Strongly Recommend',
            ]);

            demo_insert($pdo, 'adviser_evaluation', [
                'adviser_id' => $student['adviser_id'],
                'student_id' => $student['student_id'],
                'internship_id' => $internshipId,
                'final_grade' => 1.25,
                'comments' => 'Demo adviser evaluation: student completed internship requirements successfully.',
            ]);

            demo_insert($pdo, 'certificate', [
                'student_id' => $student['student_id'],
                'internship_id' => $internshipId,
                'date_issued' => demo_date(-1),
                'certificate_file' => 'certificate_' . $student['student_number'] . '.pdf',
                'verification_code' => 'CERT-' . str_replace('-', '', $student['student_number']),
            ]);

            demo_insert_final_report($pdo, $recordId);
        }
    }

    for ($i = 0; $i < 20; $i++) {
        demo_insert($pdo, 'external_internship', [
            'title' => 'External IT Internship ' . ($i + 1),
            'company_name' => 'External Partner ' . ($i + 1),
            'source_platform' => ['Findwork', 'Job Board', 'Partner Feed'][$i % 3],
            'redirect_link' => 'https://external-demo.example.com/jobs/' . ($i + 1),
            'industry' => ['Software Development', 'Business Analytics', 'Network Infrastructure'][$i % 3],
            'location' => ['Remote', 'Batangas City', 'Lipa City'][$i % 3],
            'work_setup' => ['Remote', 'On-site', 'Hybrid'][$i % 3],
            'is_active' => 1,
        ]);
    }

    for ($i = 0; $i < 10; $i++) {
        demo_insert($pdo, 'direct_message', [
            'sender_role' => 'adviser',
            'sender_id' => $demoAdviserId,
            'receiver_role' => 'student',
            'receiver_id' => $students[$i * 10]['student_id'],
            'message_text' => 'Welcome to your demo OJT monitoring group. Keep your logs and requirements updated.',
            'is_read' => 0,
            'created_at' => demo_datetime(-2, '11:00:00'),
        ]);
    }

    $pdo->commit();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $counts = [
        'admin_preserved' => demo_table_count($pdo, 'admin'),
        'advisers' => demo_table_count($pdo, 'internship_adviser'),
        'students' => demo_table_count($pdo, 'student'),
        'employers' => demo_table_count($pdo, 'employer'),
        'internships' => demo_table_count($pdo, 'internship'),
        'applications' => demo_table_count($pdo, 'application'),
        'ojt_records' => demo_table_count($pdo, 'ojt_record'),
        'skills' => demo_table_count($pdo, 'skill'),
        'requirements' => demo_table_count($pdo, 'requirement'),
    ];

    echo "SkillHive demo reset completed.\n";
    echo "Admin table preserved.\n";
    echo "Demo password for adviser/student/employer accounts: " . DEMO_PASSWORD . "\n";
    foreach ($counts as $label => $count) {
        echo str_pad($label, 18) . ': ' . $count . "\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'Demo reset failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
