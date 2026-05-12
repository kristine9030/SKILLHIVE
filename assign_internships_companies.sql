-- Create internships with existing companies (using IGNORE to avoid duplicates)
INSERT IGNORE INTO internship (title, employer_id, description, duration_weeks, allowance, work_setup, location, slots_available, status, posted_at) VALUES
('Software Developer Intern', 1, 'Develop web applications using PHP and JavaScript', 12, 15000, 'Remote', 'Manila', 5, 'Open', NOW()),
('Data Analyst Intern', 2, 'Analyze business data and create reports', 12, 12000, 'On-site', 'Makati', 3, 'Open', NOW()),
('UI/UX Design Intern', 3, 'Design user interfaces using Figma and Adobe XD', 10, 10000, 'Remote', 'Manila', 4, 'Open', NOW()),
('Cloud Infrastructure Intern', 4, 'Manage cloud services and infrastructure', 12, 14000, 'Hybrid', 'BGC', 2, 'Open', NOW()),
('IT Support Intern', 5, 'Provide technical support and network administration', 8, 8000, 'On-site', 'Makati', 5, 'Open', NOW()),
('Business Analytics Intern', 6, 'Create business reports and analysis', 12, 13000, 'Hybrid', 'Manila', 3, 'Open', NOW()),
('Database Administrator Intern', 7, 'Manage and maintain databases', 12, 12000, 'On-site', 'BGC', 2, 'Open', NOW()),
('DevOps Intern', 8, 'Deploy and manage applications', 12, 16000, 'Remote', 'Manila', 3, 'Open', NOW()),
('Quality Assurance Intern', 11, 'Test software and ensure quality', 10, 9000, 'Hybrid', 'Makati', 4, 'Open', NOW());

-- Get the internship IDs
SELECT 'Internships ready!' as status;
SELECT @int1 := internship_id FROM internship WHERE employer_id = 1 AND title = 'Software Developer Intern' LIMIT 1;
SELECT @int2 := internship_id FROM internship WHERE employer_id = 2 AND title = 'Data Analyst Intern' LIMIT 1;
SELECT @int3 := internship_id FROM internship WHERE employer_id = 3 AND title = 'UI/UX Design Intern' LIMIT 1;
SELECT @int4 := internship_id FROM internship WHERE employer_id = 4 AND title = 'Cloud Infrastructure Intern' LIMIT 1;
SELECT @int5 := internship_id FROM internship WHERE employer_id = 5 AND title = 'IT Support Intern' LIMIT 1;
SELECT @int6 := internship_id FROM internship WHERE employer_id = 6 AND title = 'Business Analytics Intern' LIMIT 1;
SELECT @int7 := internship_id FROM internship WHERE employer_id = 7 AND title = 'Database Administrator Intern' LIMIT 1;
SELECT @int8 := internship_id FROM internship WHERE employer_id = 8 AND title = 'DevOps Intern' LIMIT 1;
SELECT @int9 := internship_id FROM internship WHERE employer_id = 11 AND title = 'Quality Assurance Intern' LIMIT 1;

-- Now assign students to internships, cycling through the internship types
-- For each student, assign an internship based on their section/track
INSERT INTO ojt_record (student_id, internship_id, hours_required, hours_completed, start_date, end_date, completion_status)
SELECT 
    s.student_id,
    CASE 
        WHEN s.student_id % 9 = 0 THEN (SELECT internship_id FROM internship WHERE employer_id = 1 AND title = 'Software Developer Intern' LIMIT 1)
        WHEN s.student_id % 9 = 1 THEN (SELECT internship_id FROM internship WHERE employer_id = 2 AND title = 'Data Analyst Intern' LIMIT 1)
        WHEN s.student_id % 9 = 2 THEN (SELECT internship_id FROM internship WHERE employer_id = 3 AND title = 'UI/UX Design Intern' LIMIT 1)
        WHEN s.student_id % 9 = 3 THEN (SELECT internship_id FROM internship WHERE employer_id = 4 AND title = 'Cloud Infrastructure Intern' LIMIT 1)
        WHEN s.student_id % 9 = 4 THEN (SELECT internship_id FROM internship WHERE employer_id = 5 AND title = 'IT Support Intern' LIMIT 1)
        WHEN s.student_id % 9 = 5 THEN (SELECT internship_id FROM internship WHERE employer_id = 6 AND title = 'Business Analytics Intern' LIMIT 1)
        WHEN s.student_id % 9 = 6 THEN (SELECT internship_id FROM internship WHERE employer_id = 7 AND title = 'Database Administrator Intern' LIMIT 1)
        WHEN s.student_id % 9 = 7 THEN (SELECT internship_id FROM internship WHERE employer_id = 8 AND title = 'DevOps Intern' LIMIT 1)
        ELSE (SELECT internship_id FROM internship WHERE employer_id = 11 AND title = 'Quality Assurance Intern' LIMIT 1)
    END,
    486,
    CASE 
        WHEN s.availability_status = 'Currently Interning' THEN 350
        WHEN s.availability_status = 'Unavailable' THEN 0
        ELSE FLOOR(RAND() * 486)
    END,
    DATE_SUB(NOW(), INTERVAL 6 MONTH),
    DATE_ADD(NOW(), INTERVAL 6 MONTH),
    CASE 
        WHEN s.availability_status = 'Currently Interning' THEN 'Ongoing'
        WHEN s.availability_status = 'Unavailable' THEN 'Dropped'
        ELSE 'Ongoing'
    END
FROM student s
JOIN adviser_assignment aa ON s.student_id = aa.student_id
WHERE aa.adviser_id = 8 
  AND (s.student_number LIKE '2_-%' OR s.student_number LIKE '20-%')
  AND s.student_id NOT IN (
    SELECT DISTINCT student_id FROM ojt_record
  );

-- Show results
SELECT 'OJT Records assigned!' as status;
SELECT COUNT(*) as total_ojt_records FROM ojt_record;
SELECT sy.school_year, COUNT(o.record_id) as ojt_count
FROM ojt_record o
JOIN student s ON o.student_id = s.student_id
JOIN school_years sy ON s.school_year_id = sy.id
GROUP BY sy.school_year
ORDER BY sy.school_year DESC;

-- Show sample with company names
SELECT '--- Sample Students with Companies ---' as info;
SELECT s.student_number, CONCAT(s.first_name, ' ', s.last_name) as student_name, 
       i.title as internship, e.company_name, o.completion_status, o.hours_completed
FROM ojt_record o
JOIN student s ON o.student_id = s.student_id
JOIN internship i ON o.internship_id = i.internship_id
JOIN employer e ON i.employer_id = e.employer_id
WHERE s.student_number LIKE '25-00%'
LIMIT 7;
