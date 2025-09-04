<?php
require_once 'config.php';

echo "Manual Reset Tool for Testing\n";
echo "============================\n\n";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_confirm'])) {
    try {
        $pdo->beginTransaction();
        
        // Count current scanned students
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE is_scanned = 1");
        $scanned_count = $stmt->fetch()['count'];
        
        // Reset all is_scanned to 0
        $pdo->exec("UPDATE students SET is_scanned = 0");
        
        // Log the manual reset
        $stmt = $pdo->prepare("
            INSERT INTO daily_reset_log (reset_date, students_reset) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
            students_reset = students_reset + VALUES(students_reset),
            reset_time = CURRENT_TIMESTAMP
        ");
        $stmt->execute([date('Y-m-d'), $scanned_count]);
        
        $pdo->commit();
        
        echo "✅ Manual reset completed!\n";
        echo "   - {$scanned_count} students reset\n";
        echo "   - All students can scan again\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Show current status
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN is_scanned = 1 THEN 1 ELSE 0 END) as scanned_today,
            SUM(CASE WHEN is_scanned = 0 THEN 1 ELSE 0 END) as not_scanned
        FROM students
    ");
    $stats = $stmt->fetch();
    
    echo "📊 Current Status:\n";
    echo "   - Total Students: {$stats['total_students']}\n";
    echo "   - Scanned Today: {$stats['scanned_today']}\n";
    echo "   - Can Still Scan: {$stats['not_scanned']}\n\n";
    
    if ($stats['scanned_today'] > 0) {
        echo "🔧 Reset Available: Click button below to reset all is_scanned to 0\n\n";
    } else {
        echo "✅ All students ready to scan!\n\n";
    }
    
} catch (Exception $e) {
    echo "Error checking status: " . $e->getMessage() . "\n";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manual Reset Tool</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .reset-btn { background: #dc3545; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .reset-btn:hover { background: #c82333; }
        .info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h2>🔧 Manual Reset Tool</h2>
    
    <div class="info">
        <strong>How it works:</strong><br>
        • Students scan once per day (is_scanned = 1)<br>
        • Second scan same day = blocked<br>
        • Midnight reset = all back to 0<br>
        • Use this tool for testing or manual reset
    </div>
    
    <?php if ($stats['scanned_today'] > 0): ?>
    <form method="POST">
        <button type="submit" name="reset_confirm" class="reset-btn" 
                onclick="return confirm('Reset all scanned students? This will allow them to scan again.')">
            🔄 Reset All Students (<?php echo $stats['scanned_today']; ?> scanned)
        </button>
    </form>
    <?php else: ?>
    <p>✅ No students need reset. All ready to scan!</p>
    <?php endif; ?>
    
    <div class="info">
        <strong>Automatic Reset:</strong><br>
        Run this command daily at midnight:<br>
        <code>php <?php echo __DIR__; ?>/midnight_reset.php</code>
    </div>
    
    <a href="attendance_scanner.php">← Back to Scanner</a>
</body>
</html>
