-- Assign all dummy students to adviser_id = 5
INSERT IGNORE INTO adviser_assignment (adviser_id, student_id, status)
SELECT 5, student_id, 'Active'
FROM student 
WHERE (student_number LIKE '202%' OR student_number LIKE '2020%')
  AND student_id NOT IN (
    SELECT student_id FROM adviser_assignment WHERE adviser_id = 5
  );

-- Show results
SELECT 'Assignment complete!' as status;
SELECT COUNT(*) as total_assignments_adviser_5 FROM adviser_assignment WHERE adviser_id = 5;
