<?php
$host = 'localhost';
$db   = 'skillhive';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if profile_picture column exists in internship_adviser table
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
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'internship_adviser'
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "\n\nCurrent columns in internship_adviser table:\n";
        echo "=============================================\n";
        foreach ($columns as $col) {
            $nullable = $col['IS_NULLABLE'] == 'NO' ? " NOT NULL" : " NULLABLE";
            $default = $col['COLUMN_DEFAULT'] ? " DEFAULT " . $col['COLUMN_DEFAULT'] : "";
            echo "- " . $col['COLUMN_NAME'] . " (" . $col['COLUMN_TYPE'] . ")" . $nullable . $default . "\n";
        }
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
