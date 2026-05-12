<?php
require_once __DIR__ . '/backend/db_connect.php';

// Check school years
echo "=== SCHOOL YEARS ===\n";
$stmt = $pdo->prepare('SELECT id, school_year, status FROM school_years ORDER BY school_year DESC');
$stmt->execute();
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($years as $year) {
    echo "ID: {$year['id']}, Year: {$year['school_year']}, Status: {$year['status']}\n";
}

// Check student counts per school year
echo "\n=== STUDENT COUNTS PER SCHOOL YEAR ===\n";
$stmt = $pdo->prepare('
    SELECT sy.id, sy.school_year, sy.status, COUNT(s.student_id) as count
    FROM school_years sy
    LEFT JOIN student s ON s.school_year_id = sy.id
    GROUP BY sy.id
    ORDER BY sy.school_year DESC
');
$stmt->execute();
$counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($counts as $c) {
    echo "Year: {$c['school_year']} ({$c['status']}) - {$c['count']} students\n";
}

// Sample students from 2025-2026 (Active)
echo "\n=== SAMPLE STUDENTS FROM 2025-2026 (Active School Year) ===\n";
$stmt = $pdo->prepare('
    SELECT s.student_number, s.first_name, s.last_name, s.department, s.track, s.availability_status, sy.school_year
    FROM student s
    JOIN school_years sy ON s.school_year_id = sy.id
    WHERE sy.school_year = "2025-2026"
    ORDER BY s.last_name ASC
    LIMIT 10
');
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($students as $student) {
    echo "- {$student['student_number']}: {$student['first_name']} {$student['last_name']} ({$student['department']} - {$student['track']}) - {$student['availability_status']}\n";
}

// Check archived students
echo "\n=== ARCHIVED STUDENTS COUNT ===\n";
$stmt = $pdo->prepare('
    SELECT sy.school_year, COUNT(s.student_id) as count
    FROM school_years sy
    LEFT JOIN student s ON s.school_year_id = sy.id
    WHERE s.archived_at IS NOT NULL
    GROUP BY sy.id
    ORDER BY sy.school_year DESC
');
$stmt->execute();
$archived = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($archived as $a) {
    echo "{$a['school_year']}: {$a['count']} archived students\n";
}

echo "\n✅ Data verification complete!\n";
?>
