<?php
require_once 'config.php';

try {
    echo "=== DAILY_ATTENDANCE TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE daily_attendance');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM daily_attendance LIMIT 5');
    while ($row = $stmt->fetch()) {
        print_r($row);
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
