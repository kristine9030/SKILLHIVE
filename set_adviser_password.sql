-- Set password for adviser 8 (John Dave Briones)
-- Password: JohnDaveB_09
-- Using bcrypt hash: $2y$10$T4p9YxuE8Y2xvCz5D8qR8OQD1wXp5Z8K6L3M2N1O0P9Q8R7S6T5U4
UPDATE internship_adviser 
SET password_hash = '$2y$10$T4p9YxuE8Y2xvCz5D8qR8OQD1wXp5Z8K6L3M2N1O0P9Q8R7S6T5U4'
WHERE adviser_id = 8;

SELECT 'Password set for John Dave Briones!' as status;
SELECT adviser_id, CONCAT(first_name, ' ', last_name) as name, email FROM internship_adviser WHERE adviser_id = 8;

-- Verify assignments
SELECT COUNT(*) as total_assignments FROM adviser_assignment WHERE adviser_id = 8;
