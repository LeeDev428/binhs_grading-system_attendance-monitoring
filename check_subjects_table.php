<?php
require_once 'config.php';

try {
    // Check the current structure of the subjects table
    $stmt = $pdo->query("DESCRIBE subjects");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Subjects Table Structure:</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check if is_first_sem and is_second_sem columns exist
    $hasFirstSem = false;
    $hasSecondSem = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] == 'is_first_sem') {
            $hasFirstSem = true;
        }
        if ($column['Field'] == 'is_second_sem') {
            $hasSecondSem = true;
        }
    }
    
    echo "<h3>Column Status:</h3>\n";
    echo "<p>is_first_sem column exists: " . ($hasFirstSem ? "YES" : "NO") . "</p>\n";
    echo "<p>is_second_sem column exists: " . ($hasSecondSem ? "YES" : "NO") . "</p>\n";
    
    if (!$hasFirstSem || !$hasSecondSem) {
        echo "<h3>Action Needed:</h3>\n";
        echo "<p style='color: red;'>The semester columns are missing from the database table!</p>\n";
        echo "<p>The manage_subjects.php code expects these columns but they don't exist in the database.</p>\n";
    } else {
        echo "<h3>Status:</h3>\n";
        echo "<p style='color: green;'>Both semester columns exist in the database!</p>\n";
    }
    
} catch (PDOException $e) {
    echo "Error checking table structure: " . $e->getMessage();
}
?>