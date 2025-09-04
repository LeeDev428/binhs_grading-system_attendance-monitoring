<?php
$host = 'localhost';
$dbname = 'binhs_grading-system_attendance-monitoring_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Users Table Structure:\n";
    $result = $pdo->query('DESCRIBE users');
    
    if ($result) {
        while ($row = $result->fetch()) {
            echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . " | " . $row['Default'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
