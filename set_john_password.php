<?php
// Generate bcrypt hash for password "JohnDaveB_09"
$password = "JohnDaveB_09";
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Password: " . $password . "\n";
echo "Bcrypt Hash: " . $hash . "\n";

// Also update database
require_once __DIR__ . '/backend/db_connect.php';

$stmt = $pdo->prepare('UPDATE internship_adviser SET password_hash = :hash WHERE adviser_id = 8');
$stmt->execute([':hash' => $hash]);

echo "\n✅ Password updated for adviser 8 (John Dave Briones)!\n";

// Verify
$stmt = $pdo->prepare('SELECT adviser_id, CONCAT(first_name, " ", last_name) as name, email FROM internship_adviser WHERE adviser_id = 8');
$stmt->execute();
$adviser = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Adviser: {$adviser['name']} ({$adviser['email']})\n";

$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM adviser_assignment WHERE adviser_id = 8');
$stmt->execute();
$count = $stmt->fetchColumn();

echo "Students assigned: {$count}\n";
?>
