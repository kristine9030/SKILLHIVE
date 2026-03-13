<?php
$pdo = new PDO('mysql:host=localhost;dbname=skillhive;charset=utf8mb4','root','');
foreach ($pdo->query('SHOW TABLES') as $r) {
  echo $r[0], PHP_EOL;
}
?>
