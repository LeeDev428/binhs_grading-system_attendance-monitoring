<?php
require_once 'config.php';

echo "Testing Barcode System\n";
echo "=====================\n\n";

try {
    // Check if students table has barcode column
    $stmt = $pdo->query('DESCRIBE students');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('barcode', $columns)) {
        echo "✅ Barcode column exists in students table\n";
    } else {
        echo "❌ Barcode column missing!\n";
        exit;
    }
    
    // Check if any students exist
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students');
    $count = $stmt->fetch()['count'];
    echo "📊 Found {$count} students in database\n";
    
    // Check students with barcodes
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students WHERE barcode IS NOT NULL AND barcode != ""');
    $barcode_count = $stmt->fetch()['count'];
    echo "📊 Students with barcodes: {$barcode_count}\n";
    
    // If no students have barcodes, let's generate some test data
    if ($barcode_count == 0 && $count > 0) {
        echo "\n🔧 Generating test barcodes for existing students...\n";
        
        $stmt = $pdo->query('SELECT id, student_number FROM students WHERE barcode IS NULL OR barcode = "" LIMIT 5');
        $students = $stmt->fetchAll();
        
        foreach ($students as $student) {
            $barcode = date('Y') . str_pad($student['student_number'], 6, '0', STR_PAD_LEFT);
            $update_stmt = $pdo->prepare('UPDATE students SET barcode = ? WHERE id = ?');
            $update_stmt->execute([$barcode, $student['id']]);
            echo "  ✅ Generated barcode {$barcode} for student {$student['student_number']}\n";
        }
    }
    
    // Test daily_attendance table
    $stmt = $pdo->query('SHOW TABLES LIKE "daily_attendance"');
    if ($stmt->rowCount() > 0) {
        echo "✅ daily_attendance table exists\n";
        
        // Check attendance records
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM daily_attendance');
        $attendance_count = $stmt->fetch()['count'];
        echo "📊 Attendance records: {$attendance_count}\n";
    } else {
        echo "❌ daily_attendance table missing!\n";
    }
    
    echo "\n🎯 Test URLs:\n";
    echo "1. View Students: http://localhost/binhs_grading-system_attendance-monitoring/view_grades.php\n";
    echo "2. Scanner Test: http://localhost/binhs_grading-system_attendance-monitoring/scanner_test.php\n";
    echo "3. Attendance Scanner: http://localhost/binhs_grading-system_attendance-monitoring/attendance_scanner.php\n";
    
    echo "\n✅ System check completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
