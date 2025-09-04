<?php
/**
 * DEBUG: What is the scanner sending?
 */

require_once 'config.php';

if ($_POST) {
    echo "<h2>🔍 POST DATA RECEIVED:</h2>";
    echo "<pre style='background: #f8f9fa; padding: 20px; border: 1px solid #ddd;'>";
    echo "RAW POST DATA:\n";
    print_r($_POST);
    echo "\n\nRAW INPUT STREAM:\n";
    echo file_get_contents('php://input');
    echo "</pre>";
    
    if (isset($_POST['scanner_input'])) {
        $input = $_POST['scanner_input'];
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Scanner Input Detected:</h4>";
        echo "<strong>Value:</strong> '$input'<br>";
        echo "<strong>Length:</strong> " . strlen($input) . "<br>";
        echo "<strong>Type:</strong> " . gettype($input) . "<br>";
        echo "</div>";
        
        // Try to find student
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE barcode = ?");
            $stmt->execute([$input]);
            $student = $stmt->fetch();
            
            if ($student) {
                echo "<div class='alert alert-info'>";
                echo "<h4>👤 Student Found:</h4>";
                echo "<strong>Name:</strong> {$student['first_name']} {$student['last_name']}<br>";
                echo "<strong>Barcode:</strong> {$student['barcode']}<br>";
                echo "<strong>is_scanned:</strong> {$student['is_scanned']}<br>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>";
                echo "<h4>❌ No student found with barcode: '$input'</h4>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scanner Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-input {
            font-size: 2rem;
            padding: 20px;
            text-align: center;
            border: 5px solid #dc3545;
            background: #fff3cd;
        }
        .debug-input:focus {
            border-color: #28a745;
            background: #d4edda;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🔧 SCANNER DEBUG MODE</h1>
        <div class="alert alert-warning">
            <strong>This page shows exactly what your TEKLEAD scanner is sending:</strong><br>
            1. Click in the input field<br>
            2. Scan your barcode<br>
            3. Check what data appears above
        </div>
        
        <form method="POST" id="debugForm">
            <div class="mb-3">
                <label for="scanner_input" class="form-label"><h3>🎯 SCAN HERE:</h3></label>
                <input type="text" 
                       id="scanner_input" 
                       name="scanner_input" 
                       class="form-control debug-input" 
                       placeholder="CLICK HERE AND SCAN BARCODE"
                       autofocus
                       autocomplete="off">
            </div>
            <button type="submit" class="btn btn-danger btn-lg">
                <i class="fas fa-search"></i> DEBUG SCAN
            </button>
        </form>
        
        <div class="mt-4">
            <h4>📊 Available Barcodes in Database:</h4>
            <?php
            try {
                $stmt = $pdo->query("SELECT first_name, last_name, barcode FROM students WHERE barcode IS NOT NULL LIMIT 5");
                $students = $stmt->fetchAll();
                
                echo "<ul>";
                foreach ($students as $student) {
                    echo "<li><strong>{$student['first_name']} {$student['last_name']}</strong>: <code>{$student['barcode']}</code></li>";
                }
                echo "</ul>";
            } catch (Exception $e) {
                echo "<p class='text-danger'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>
    </div>
    
    <script>
        const input = document.getElementById('scanner_input');
        const form = document.getElementById('debugForm');
        
        // Log every single character
        input.addEventListener('input', function(e) {
            console.log('🔥 INPUT EVENT:', {
                value: e.target.value,
                length: e.target.value.length,
                charCodes: Array.from(e.target.value).map(c => c.charCodeAt(0))
            });
        });
        
        // Log key events
        input.addEventListener('keydown', function(e) {
            console.log('⌨️ KEY DOWN:', {
                key: e.key,
                keyCode: e.keyCode,
                which: e.which,
                code: e.code
            });
            
            if (e.key === 'Enter') {
                e.preventDefault();
                console.log('🚀 ENTER PRESSED - SUBMITTING');
                form.submit();
            }
        });
        
        // Auto-submit after delay
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const value = this.value.trim();
            
            if (value.length >= 4) {
                timeout = setTimeout(() => {
                    console.log('⏰ AUTO-SUBMIT TRIGGERED');
                    form.submit();
                }, 1000);
            }
        });
        
        console.log('🔧 DEBUG MODE READY - Scan your barcode!');
    </script>
</body>
</html>
