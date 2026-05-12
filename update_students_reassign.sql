-- Update student numbers to proper format: YY-NNNNN
UPDATE student 
SET student_number = CONCAT(
    SUBSTR(CAST(YEAR(NOW()) AS CHAR), 3, 2) - (YEAR(NOW()) - SUBSTR(school_year_id, 1, 4)),
    '-',
    LPAD(student_id, 5, '0')
)
WHERE student_number LIKE '202%' OR student_number LIKE '2020%';

-- Update sections to alternate between BA and NT
UPDATE student s
SET s.section = IF(s.student_id % 2 = 0, 'BA', 'NT')
WHERE s.student_number LIKE '2_-%' OR s.student_number LIKE '20-%';

-- Update tracks to match sections
UPDATE student s
SET s.track = IF(s.section = 'BA', 'Business Analytics', 'Networking')
WHERE s.student_number LIKE '2_-%' OR s.student_number LIKE '20-%';

-- Remove old assignments from adviser 5
DELETE FROM adviser_assignment 
WHERE adviser_id = 5 
  AND student_id IN (
    SELECT student_id FROM student 
    WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%'
  );

-- Assign all students to adviser 8 (John Dave Briones)
INSERT INTO adviser_assignment (adviser_id, student_id, status)
SELECT 8, student_id, 'Active'
FROM student 
WHERE (student_number LIKE '2_-%' OR student_number LIKE '20-%')
  AND student_id NOT IN (
    SELECT student_id FROM adviser_assignment WHERE adviser_id = 8
  );

SELECT 'Update complete!' as status;
