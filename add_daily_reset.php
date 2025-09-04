<?php
require_once 'config.php';

echo "Adding is_scanned column and daily reset system...\n";
echo "==================================================\n\n";

try {
    // Add is_scanned column
    echo "1. Adding is_scanned column to students table...\n";
    $pdo->exec('ALTER TABLE students ADD COLUMN is_scanned TINYINT(1) DEFAULT 0 AFTER barcode');
    echo "✅ is_scanned column added successfully!\n\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✅ is_scanned column already exists!\n\n";
    } else {
        echo "❌ Error adding column: " . $e->getMessage() . "\n\n";
    }
}

try {
    // Create daily reset log table
    echo "2. Creating daily reset log table...\n";
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS daily_reset_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reset_date DATE NOT NULL,
            reset_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            students_reset INT DEFAULT 0,
            UNIQUE KEY unique_date (reset_date)
        )
    ');
    echo "✅ daily_reset_log table created!\n\n";
    
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n\n";
}

echo "3. System ready! Here's how it works:\n";
echo "   📊 is_scanned = 0 (not scanned today)\n";
echo "   🎯 First scan: is_scanned = 1, days_present++\n";
echo "   🚫 Second scan: Nothing happens (already scanned)\n";
echo "   🌙 Midnight: All is_scanned reset to 0\n";
echo "   ☀️ Next day: Fresh start!\n\n";

echo "✅ Setup completed!\n";
?>
