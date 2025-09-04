<?php
require_once 'config.php';

// Set Philippines timezone
date_default_timezone_set('Asia/Manila');

echo "=== TESTING PHILIPPINES TIMEZONE ATTENDANCE ===\n";
echo "Current Philippines Time: " . date('Y-m-d H:i:s T') . "\n";
echo "Current Date: " . date('Y-m-d') . "\n";
echo "Current Time: " . date('H:i:s') . "\n\n";

// Test student
$student_id = 8;
$barcode = '2500008';

echo "=== CHECKING EXISTING ATTENDANCE FOR TODAY ===\n";
$ph_date = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT * FROM daily_attendance 
    WHERE student_id = ? AND DATE(scan_datetime) = ?
");
$stmt->execute([$student_id, $ph_date]);
$existing = $stmt->fetch();

if ($existing) {
    echo "❌ Already scanned today:\n";
    print_r($existing);
} else {
    echo "✅ No attendance record for today - can scan!\n";
}

echo "\n=== ALL ATTENDANCE RECORDS FOR THIS STUDENT ===\n";
$stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE student_id = ? ORDER BY scan_datetime DESC");
$stmt->execute([$student_id]);
while ($row = $stmt->fetch()) {
    echo "Date: " . $row['scan_date'] . " | Time: " . $row['scan_time'] . " | DateTime: " . $row['scan_datetime'] . " | Status: " . $row['status'] . "\n";
}
?>
