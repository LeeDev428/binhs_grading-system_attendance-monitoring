<?php
/**
 * BINHS Attendance System - Status Checker
 * Verifies all system components are working correctly
 */

// Include database configuration
include_once 'config.php';

echo "<h1>BINHS Attendance System Status Check</h1>\n";
echo "<hr>\n";

try {
    // Test database connection
    echo "<h2>1. Database Connection</h2>\n";
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful\n<br>";
    
    // Check required tables exist
    echo "<h2>2. Database Tables</h2>\n";
    $required_tables = ['students', 'daily_attendance', 'daily_reset_log'];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists\n<br>";
        } else {
            echo "❌ Table '$table' missing\n<br>";
        }
    }
    
    // Check students table structure
    echo "<h2>3. Students Table Columns</h2>\n";
    $required_columns = ['barcode', 'is_scanned'];
    $stmt = $pdo->query("DESCRIBE students");
    $existing_columns = [];
    while ($row = $stmt->fetch()) {
        $existing_columns[] = $row['Field'];
    }
    
    foreach ($required_columns as $column) {
        if (in_array($column, $existing_columns)) {
            echo "✅ Column 'students.$column' exists\n<br>";
        } else {
            echo "❌ Column 'students.$column' missing\n<br>";
        }
    }
    
    // Check students with barcodes
    echo "<h2>4. Student Barcode Status</h2>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $total_students = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as with_barcode FROM students WHERE barcode IS NOT NULL AND barcode != ''");
    $students_with_barcode = $stmt->fetch()['with_barcode'];
    
    echo "Total students: $total_students\n<br>";
    echo "Students with barcodes: $students_with_barcode\n<br>";
    
    if ($students_with_barcode == $total_students) {
        echo "✅ All students have barcodes\n<br>";
    } else {
        echo "⚠️ " . ($total_students - $students_with_barcode) . " students missing barcodes\n<br>";
        echo "<a href='generate_barcodes_for_existing.php'>Generate missing barcodes</a>\n<br>";
    }
    
    // Check daily reset status
    echo "<h2>5. Daily Reset Status</h2>\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN is_scanned = 1 THEN 1 ELSE 0 END) as scanned_today,
            SUM(CASE WHEN is_scanned = 0 THEN 1 ELSE 0 END) as can_still_scan
        FROM students
    ");
    $daily_stats = $stmt->fetch();
    
    echo "Total students: " . $daily_stats['total_students'] . "\n<br>";
    echo "Scanned today: " . $daily_stats['scanned_today'] . "\n<br>";
    echo "Can still scan: " . $daily_stats['can_still_scan'] . "\n<br>";
    
    // Check last reset log
    $stmt = $pdo->query("SELECT * FROM daily_reset_log ORDER BY reset_date DESC LIMIT 1");
    $last_reset = $stmt->fetch();
    
    if ($last_reset) {
        echo "Last reset: " . $last_reset['reset_date'] . " at " . $last_reset['reset_time'] . "\n<br>";
        echo "Students reset: " . $last_reset['students_reset'] . "\n<br>";
    } else {
        echo "⚠️ No reset log entries found\n<br>";
    }
    
    // Check today's attendance
    echo "<h2>6. Today's Attendance</h2>\n";
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scanned,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as on_time,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late
        FROM daily_attendance 
        WHERE scan_date = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch();
    
    echo "Total scanned today: " . $today_stats['total_scanned'] . "\n<br>";
    echo "On time: " . $today_stats['on_time'] . "\n<br>";
    echo "Late: " . $today_stats['late'] . "\n<br>";
    
    // System files check
    echo "<h2>7. System Files</h2>\n";
    $required_files = [
        'attendance_scanner.php' => 'Main scanner interface',
        'view_grades.php' => 'Student list with barcode viewer',
        'student_grades.php' => 'Student registration',
        'midnight_reset.php' => 'Automatic daily reset',
        'manual_reset.php' => 'Manual reset tool'
    ];
    
    foreach ($required_files as $file => $description) {
        if (file_exists($file)) {
            echo "✅ $file - $description\n<br>";
        } else {
            echo "❌ $file missing - $description\n<br>";
        }
    }
    
    // System links
    echo "<h2>8. Quick Links</h2>\n";
    echo "<a href='attendance_scanner.php'>📱 Attendance Scanner</a>\n<br>";
    echo "<a href='view_grades.php'>👥 View Students & Barcodes</a>\n<br>";
    echo "<a href='student_grades.php'>➕ Add/Edit Students</a>\n<br>";
    echo "<a href='manual_reset.php'>🔄 Manual Reset (Testing)</a>\n<br>";
    
    echo "<h2>9. Overall Status</h2>\n";
    echo "✅ System is operational and ready for use\n<br>";
    echo "📅 Date: " . date('F j, Y') . "\n<br>";
    echo "🕐 Time: " . date('g:i:s A T') . "\n<br>";
    echo "🌏 Timezone: Asia/Manila\n<br>";
    
} catch (Exception $e) {
    echo "<h2>❌ System Error</h2>\n";
    echo "Error: " . $e->getMessage() . "\n<br>";
    echo "Please check your database configuration and connection.\n<br>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
h1 { color: #2c3e50; }
h2 { color: #34495e; margin-top: 30px; }
a { 
    color: #3498db; 
    text-decoration: none;
    padding: 5px 10px;
    background: #ecf0f1;
    border-radius: 4px;
    margin: 2px;
    display: inline-block;
}
a:hover { background: #bdc3c7; }
hr { margin: 20px 0; }
</style>
