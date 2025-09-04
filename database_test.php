<?php
require_once 'config.php';

echo "🔧 DATABASE TEST\n";
echo "================\n\n";

try {
    // Test connection
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM students');
    $result = $stmt->fetch();
    echo "✅ Database connected. Students count: " . $result['count'] . "\n\n";
    
    // Show students with barcodes
    echo "📋 Students with barcodes:\n";
    $stmt = $pdo->query('SELECT id, first_name, last_name, barcode, is_scanned FROM students WHERE barcode IS NOT NULL');
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Name: {$row['first_name']} {$row['last_name']}, Barcode: {$row['barcode']}, is_scanned: {$row['is_scanned']}\n";
    }
    
    echo "\n🧪 Testing UPDATE query:\n";
    $test_barcode = '2500008';
    
    // Show before
    $stmt = $pdo->prepare('SELECT is_scanned FROM students WHERE barcode = ?');
    $stmt->execute([$test_barcode]);
    $before = $stmt->fetch();
    echo "BEFORE UPDATE - is_scanned: " . ($before ? $before['is_scanned'] : 'NOT FOUND') . "\n";
    
    // Do update
    $stmt = $pdo->prepare('UPDATE students SET is_scanned = 1 WHERE barcode = ?');
    $result = $stmt->execute([$test_barcode]);
    echo "UPDATE result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Rows affected: " . $stmt->rowCount() . "\n";
    
    // Show after
    $stmt = $pdo->prepare('SELECT is_scanned FROM students WHERE barcode = ?');
    $stmt->execute([$test_barcode]);
    $after = $stmt->fetch();
    echo "AFTER UPDATE - is_scanned: " . ($after ? $after['is_scanned'] : 'NOT FOUND') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
