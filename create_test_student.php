<?php
require_once 'config.php';

echo "Creating Test Student with Barcode\n";
echo "==================================\n\n";

try {
    // Create a test student if doesn't exist
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_number = ?");
    $stmt->execute(['TEST001']);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo "Creating test student...\n";
        $barcode = '2025000001'; // Simple numeric barcode
        
        $stmt = $pdo->prepare("
            INSERT INTO students (first_name, last_name, middle_name, student_number, barcode, grade_level, section, school_year, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'John', 
            'Doe', 
            'Test', 
            'TEST001', 
            $barcode, 
            'Grade 11', 
            'A', 
            '2024-2025', 
            1
        ]);
        
        echo "✅ Test student created successfully!\n";
        echo "   Name: John Test Doe\n";
        echo "   Student Number: TEST001\n";
        echo "   Barcode: {$barcode}\n";
    } else {
        echo "✅ Test student already exists.\n";
        echo "   Name: {$student['first_name']} {$student['middle_name']} {$student['last_name']}\n";
        echo "   Student Number: {$student['student_number']}\n";
        echo "   Barcode: {$student['barcode']}\n";
        
        // Update barcode if empty
        if (empty($student['barcode'])) {
            $barcode = '2025000001';
            $stmt = $pdo->prepare("UPDATE students SET barcode = ? WHERE id = ?");
            $stmt->execute([$barcode, $student['id']]);
            echo "   ✅ Added barcode: {$barcode}\n";
        }
    }
    
    echo "\n🎯 Next Steps:\n";
    echo "1. Go to: http://localhost/binhs_grading-system_attendance-monitoring/view_grades.php\n";
    echo "2. Click 'View Barcode' for the test student\n";
    echo "3. The modal should display with a scannable barcode\n";
    echo "4. Use the 'Test Scanner' button to open the attendance scanner\n";
    echo "5. Scan the barcode to test attendance recording\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
