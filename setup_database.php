<?php
require_once 'config.php';

echo "BINHS Barcode System Database Setup\n";
echo "===================================\n\n";

try {
    // Check if barcode column exists
    $stmt = $pdo->query('DESCRIBE students');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('barcode', $columns)) {
        echo "Adding barcode column to students table...\n";
        $pdo->exec('ALTER TABLE students ADD COLUMN barcode VARCHAR(50) UNIQUE AFTER student_number');
        echo "✅ Barcode column added successfully!\n";
    } else {
        echo "✅ Barcode column already exists.\n";
    }
    
    // Check if daily_attendance table exists
    $stmt = $pdo->query('SHOW TABLES LIKE "daily_attendance"');
    if ($stmt->rowCount() == 0) {
        echo "Creating daily_attendance table...\n";
        $pdo->exec('
            CREATE TABLE daily_attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                barcode VARCHAR(50) NOT NULL,
                scan_date DATE NOT NULL,
                scan_time TIME NOT NULL,
                scan_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM("present", "late") DEFAULT "present",
                notes TEXT,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE KEY unique_student_date (student_id, scan_date)
            )
        ');
        echo "✅ daily_attendance table created successfully!\n";
    } else {
        echo "✅ daily_attendance table already exists.\n";
    }
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Go to student_grades.php to generate barcodes for students\n";
    echo "2. Use view_grades.php to view and print student barcodes\n";
    echo "3. Test your scanner with scanner_test.php\n";
    echo "4. Use attendance_scanner.php for daily attendance\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>
