-- Update all students to have numeric sections: 02, 03, 04
UPDATE student SET section = '02' WHERE student_id % 3 = 0;
UPDATE student SET section = '03' WHERE student_id % 3 = 1;
UPDATE student SET section = '04' WHERE student_id % 3 = 2;

-- Verify the update
SELECT 'Section update complete!' as status;
SELECT sy.school_year, COUNT(s.student_id) as count, 
       GROUP_CONCAT(DISTINCT s.section) as sections
FROM student s
JOIN school_years sy ON s.school_year_id = sy.id
WHERE s.student_number LIKE '2_-%' OR s.student_number LIKE '20-%'
GROUP BY s.school_year_id
ORDER BY sy.school_year DESC;

-- Also show sample of students
SELECT student_number, first_name, last_name, section, track, availability_status 
FROM student 
WHERE student_number LIKE '2_-%' OR student_number LIKE '20-%'
ORDER BY student_number
LIMIT 15;
