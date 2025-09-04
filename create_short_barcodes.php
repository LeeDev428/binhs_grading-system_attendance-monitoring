<?php
require_once 'config.php';

echo "Updating students with shorter barcodes...\n";

try {
    // Get students without proper short barcodes
    $stmt = $pdo->query('SELECT id, student_number, barcode FROM students LIMIT 5');
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        // Create a test student
        $stmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, barcode, grade_level, school_year, created_by) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $barcode = '25000001'; // Short 8-digit barcode like Google examples
        $stmt->execute(['000001', 'John', 'Doe', $barcode, 'Grade 11', '2024-2025']);
        echo "✅ Created test student: John Doe with barcode: {$barcode}\n";
    } else {
        foreach ($students as $student) {
            // Create shorter barcode: YY + 6-digit student number
            $short_barcode = '25' . str_pad($student['student_number'], 6, '0', STR_PAD_LEFT);
            
            // Only update if different
            if ($student['barcode'] != $short_barcode) {
                $update_stmt = $pdo->prepare('UPDATE students SET barcode = ? WHERE id = ?');
                $update_stmt->execute([$short_barcode, $student['id']]);
                echo "✅ Updated student {$student['student_number']} with short barcode: {$short_barcode}\n";
            } else {
                echo "✅ Student {$student['student_number']} already has correct barcode: {$short_barcode}\n";
            }
        }
    }
    
    echo "\n🎯 Test the system:\n";
    echo "1. Go to: http://localhost/binhs_grading-system_attendance-monitoring/view_grades.php\n";
    echo "2. Click 'View Barcode' on any student\n";
    echo "3. Modal should display with short barcode (8 digits)\n";
    echo "4. Scan with your TEKLEAD scanner!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
