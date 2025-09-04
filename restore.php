<?php
$pdo = new PDO('mysql:host=localhost;dbname=binhs_grading-system_attendance-monitoring_db', 'root', '');
$pdo->exec("UPDATE users SET full_name = 'Admin Testing', email = 'admin@gmail.com' WHERE id = 2");
echo 'Data restored';
?>
