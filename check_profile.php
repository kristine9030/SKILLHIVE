<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=skillhive;charset=utf8mb4", "root", "");
    
    // Check if profile_picture column exists
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'internship_adviser'
         AND COLUMN_NAME = 'profile_picture'"
    );
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "RESULT: profile_picture column EXISTS\n";
    } else {
        echo "RESULT: profile_picture column DOES NOT EXIST\n";
        
        // Show all columns in internship_adviser table
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'internship_adviser'
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "\nCurrent columns in internship_adviser table:\n";
        foreach ($columns as $col) {
            echo "- " . $col['COLUMN_NAME'] . " (" . $col['COLUMN_TYPE'] . ")\n";
        }
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
