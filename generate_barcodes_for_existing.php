<?php
/**
 * Generate barcodes for existing students
 */

require_once 'config.php';

try {
    // Get students without barcodes
    $stmt = $pdo->query("SELECT * FROM students WHERE barcode IS NULL OR barcode = '' ORDER BY id");
    $students = $stmt->fetchAll();
    
    echo "Found " . count($students) . " students without barcodes.\n";
    
    $updated = 0;
    foreach ($students as $student) {
        // Generate barcode: "25" + padded student ID
        $barcode = "25" . str_pad($student['id'], 5, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("UPDATE students SET barcode = ? WHERE id = ?");
        if ($stmt->execute([$barcode, $student['id']])) {
            echo "✅ Generated barcode $barcode for {$student['first_name']} {$student['last_name']}\n";
            $updated++;
        } else {
            echo "❌ Failed to update {$student['first_name']} {$student['last_name']}\n";
        }
    }
    
    echo "\n🎉 Successfully generated $updated barcodes!\n";
    
    // Show first 5 barcodes for testing
    echo "\n📋 First 5 test barcodes:\n";
    $stmt = $pdo->query("SELECT first_name, last_name, barcode FROM students WHERE barcode IS NOT NULL LIMIT 5");
    $test_students = $stmt->fetchAll();
    
    foreach ($test_students as $student) {
        echo "- {$student['first_name']} {$student['last_name']}: {$student['barcode']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
