<?php
session_start();
require_once __DIR__ . '/backend/db_connect.php';

$adviserId = (int)($_SESSION['adviser_id'] ?? 0);

echo "Current Adviser ID: $adviserId\n";

if ($adviserId > 0) {
    // Check current students assigned to this adviser
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM adviser_assignment 
        WHERE adviser_id = :adviser_id
    ');
    $stmt->execute([':adviser_id' => $adviserId]);
    $currentCount = $stmt->fetchColumn();
    echo "Current assignments for adviser $adviserId: $currentCount\n\n";

    // Get adviser name
    $stmt = $pdo->prepare('SELECT adviser_id, adviser_name FROM internship_adviser WHERE adviser_id = :id');
    $stmt->execute([':id' => $adviserId]);
    $adviser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adviser) {
        echo "Adviser Name: {$adviser['adviser_name']}\n";
    }
    
    // Assign all dummy students (2024001-2024006, 2025001-2025007, etc.) to this adviser
    $dummyStudentNumbers = [];
    for ($year = 2020; $year <= 2028; $year++) {
        for ($i = 1; $i <= 7; $i++) {
            $dummyStudentNumbers[] = sprintf("%04d%03d", $year, $i);
        }
    }
    
    echo "\nAssigning dummy students to adviser $adviserId...\n";
    
    $stmt = $pdo->prepare('
        SELECT student_id FROM student WHERE student_number = :student_number LIMIT 1
    ');
    
    $checkStmt = $pdo->prepare('
        SELECT COUNT(*) FROM adviser_assignment 
        WHERE adviser_id = :adviser_id AND student_id = :student_id
    ');
    
    $insertStmt = $pdo->prepare('
        INSERT INTO adviser_assignment (adviser_id, student_id, status) 
        VALUES (:adviser_id, :student_id, "Active")
    ');
    
    $assigned = 0;
    $skipped = 0;
    
    foreach ($dummyStudentNumbers as $studentNum) {
        $stmt->execute([':student_number' => $studentNum]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) continue;
        
        $studentId = (int)$row['student_id'];
        
        // Check if already assigned
        $checkStmt->execute([
            ':adviser_id' => $adviserId,
            ':student_id' => $studentId
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        
        try {
            $insertStmt->execute([
                ':adviser_id' => $adviserId,
                ':student_id' => $studentId
            ]);
            $assigned++;
        } catch (Exception $e) {
            // Skip on duplicate or other errors
        }
    }
    
    echo "✅ Assigned: $assigned students\n";
    echo "⏭️  Skipped: $skipped (already assigned)\n";
    
    // Show new count
    $stmt->execute([':adviser_id' => $adviserId]);
    $newCount = $stmt->fetchColumn();
    echo "\nNew total assignments: $newCount\n";
    
} else {
    echo "❌ No adviser logged in!\n";
}
?>
