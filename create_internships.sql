-- First, let's create internships for the companies
-- Check if internships exist
SELECT 'Existing internships:' as info;
SELECT internship_id, title, employer_id FROM internship LIMIT 10;

-- Create new internships if needed (assign to different employers)
-- We'll create 5 internships with different companies
INSERT IGNORE INTO internship (title, description, employer_id, skills_required, internship_type, posted_date, application_deadline) VALUES
('Software Developer Intern', 'Develop web applications', 1, 'PHP, JavaScript, MySQL', 'Remote', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('Data Analyst Intern', 'Analyze business data', 2, 'SQL, Python, Excel', 'On-site', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('UI/UX Design Intern', 'Design user interfaces', 3, 'Figma, Adobe XD, CSS', 'Remote', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('Cloud Infrastructure Intern', 'Manage cloud services', 4, 'AWS, Linux, Docker', 'Hybrid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('IT Support Intern', 'Provide technical support', 5, 'Networking, Windows Server', 'On-site', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('Business Analytics Intern', 'Create business reports', 6, 'Tableau, SQL, Python', 'Hybrid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('Database Administrator Intern', 'Manage databases', 7, 'MySQL, PostgreSQL, Backup', 'On-site', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('DevOps Intern', 'Deploy applications', 8, 'Git, Jenkins, Kubernetes', 'Remote', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('Quality Assurance Intern', 'Test software', 11, 'Testing frameworks, Automation', 'Hybrid', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY));

SELECT 'Internships created!' as status;
SELECT internship_id, title, employer_id FROM internship ORDER BY internship_id DESC LIMIT 10;
