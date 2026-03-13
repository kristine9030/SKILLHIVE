<?php
$pdo = new PDO('mysql:host=localhost;dbname=skillhive;charset=utf8mb4','root','');
$tables = ['student','student_skill','skill'];
foreach ($tables as $t) {
  echo "== $t ==", PHP_EOL;
  foreach ($pdo->query("DESCRIBE $t") as $row) {
    echo $row['Field'], ' | ', $row['Type'], ' | ', $row['Null'], ' | ', $row['Key'], PHP_EOL;
  }
}
?>
