<?php
require_once 'config.php';

echo "=== SUBJECTS DATABASE CHECK ===\n";

try {
    $stmt = $pdo->query("SELECT id, subject_name, subject_code, subject_type, is_first_sem, is_second_sem FROM subjects ORDER BY id");
    $subjects = $stmt->fetchAll();
    
    echo "Total subjects found: " . count($subjects) . "\n\n";
    
    $first_sem_count = 0;
    $second_sem_count = 0;
    
    foreach ($subjects as $subject) {
        echo sprintf("ID: %2d | %-30s | %-10s | %-12s | 1st: %s | 2nd: %s\n", 
            $subject['id'],
            $subject['subject_name'],
            $subject['subject_code'],
            $subject['subject_type'],
            $subject['is_first_sem'] ? 'Yes' : 'No',
            $subject['is_second_sem'] ? 'Yes' : 'No'
        );
        
        if ($subject['is_first_sem']) $first_sem_count++;
        if ($subject['is_second_sem']) $second_sem_count++;
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "First semester subjects: $first_sem_count\n";
    echo "Second semester subjects: $second_sem_count\n";
    
    if ($second_sem_count == 0) {
        echo "\n*** PROBLEM IDENTIFIED ***\n";
        echo "NO SUBJECTS ARE ASSIGNED TO SECOND SEMESTER!\n";
        echo "All subjects have is_second_sem = 0\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>