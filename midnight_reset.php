<?php
// MIDNIGHT RESET SCRIPT - Reset is_scanned for all students
// This should run every day at 12:00 AM Philippines timezone

require_once 'config.php';

// Set Philippines timezone
date_default_timezone_set('Asia/Manila');

$reset_date = date('Y-m-d');
$reset_time = date('Y-m-d H:i:s');

echo "🌙 MIDNIGHT RESET - Philippines Time: {$reset_time}\n";
echo "================================================\n\n";

try {
    $pdo->beginTransaction();
    
    // Check if already reset today
    $stmt = $pdo->prepare("SELECT * FROM daily_reset_log WHERE reset_date = ?");
    $stmt->execute([$reset_date]);
    $already_reset = $stmt->fetch();
    
    if ($already_reset) {
        echo "⚠️ Already reset today! Skipping...\n";
        echo "Last reset: {$already_reset['reset_time']}\n";
    } else {
        // Count students currently marked as scanned
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE is_scanned = 1");
        $scanned_count = $stmt->fetch()['count'];
        
        echo "📊 Students currently marked as scanned: {$scanned_count}\n";
        
        // Reset all is_scanned to 0
        $stmt = $pdo->prepare("UPDATE students SET is_scanned = 0");
        $stmt->execute();
        
        // Log the reset
        $stmt = $pdo->prepare("
            INSERT INTO daily_reset_log (reset_date, students_reset) 
            VALUES (?, ?)
        ");
        $stmt->execute([$reset_date, $scanned_count]);
        
        $pdo->commit();
        
        echo "✅ Reset completed!\n";
        echo "   - {$scanned_count} students reset to is_scanned = 0\n";
        echo "   - Ready for new day scanning!\n\n";
        
        echo "🎯 System Status:\n";
        echo "   - All students can now scan again\n";
        echo "   - Each first scan will increment days_present\n";
        echo "   - Duplicate scans will be blocked until tomorrow\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error during reset: " . $e->getMessage() . "\n";
}

echo "\n✅ Midnight reset process completed!\n";
?>
