<?php
/**
 * PHYSICAL TEKLEAD SCANNER ONLY - AUTO ATTENDANCE
 * This page works ONLY with your physical USB barcode scanner
 * NO manual typing, NO clicking - SCAN ONLY!
 */

require_once 'config.php';

$result_message = '';
$result_type = '';

// Handle barcode scan from physical scanner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode_data'])) {
    $scanned_barcode = trim($_POST['barcode_data']);
    
    if (!empty($scanned_barcode)) {
        try {
            // Find student by barcode
            $stmt = $pdo->prepare("SELECT * FROM students WHERE barcode = ?");
            $stmt->execute([$scanned_barcode]);
            $student = $stmt->fetch();
            
            if ($student) {
                if ($student['is_scanned'] == 1) {
                    $result_message = "⚠️ {$student['first_name']} {$student['last_name']} already scanned today! Cannot scan twice.";
                    $result_type = 'warning';
                } else {
                    // Mark as scanned
                    $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
                    $success = $stmt->execute([$student['id']]);
                    
                    if ($success && $stmt->rowCount() > 0) {
                        // Record attendance
                        $status = (date('H:i') > '08:00') ? 'late' : 'present';
                        $stmt = $pdo->prepare("
                            INSERT INTO daily_attendance (student_id, barcode, scan_date, scan_time, status) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE scan_time = VALUES(scan_time), status = VALUES(status)
                        ");
                        $stmt->execute([$student['id'], $scanned_barcode, date('Y-m-d'), date('H:i:s'), $status]);
                        
                        $result_message = "✅ ATTENDANCE RECORDED! {$student['first_name']} {$student['last_name']} marked {$status} at " . date('g:i A');
                        $result_type = 'success';
                    } else {
                        $result_message = "❌ Failed to update attendance for {$student['first_name']} {$student['last_name']}";
                        $result_type = 'error';
                    }
                }
            } else {
                $result_message = "❌ Invalid barcode: {$scanned_barcode} - Student not found";
                $result_type = 'error';
            }
            
        } catch (Exception $e) {
            $result_message = "❌ System error: " . $e->getMessage();
            $result_type = 'error';
        }
    }
}

// Get today's attendance count
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN is_scanned = 1 THEN 1 ELSE 0 END) as scanned_today
        FROM students WHERE barcode IS NOT NULL
    ");
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['total_students' => 0, 'scanned_today' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TEKLEAD Physical Scanner - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
        }
        
        .scanner-interface {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .scanner-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .scanner-body {
            padding: 40px;
        }
        
        .scanner-input {
            width: 100%;
            height: 80px;
            font-size: 2.5rem;
            text-align: center;
            border: 5px solid #28a745;
            border-radius: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .scanner-input:focus {
            border-color: #007bff;
            background: #e3f2fd;
            box-shadow: 0 0 30px rgba(0,123,255,0.5);
            outline: none;
            transform: scale(1.02);
        }
        
        .result-display {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            border-radius: 15px;
            margin: 30px 0;
            transition: all 0.5s ease;
        }
        
        .success { 
            background: #d4edda; 
            color: #155724; 
            border: 3px solid #28a745;
        }
        
        .warning { 
            background: #fff3cd; 
            color: #856404; 
            border: 3px solid #ffc107;
        }
        
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 3px solid #dc3545;
        }
        
        .idle {
            background: #e2e3e5;
            color: #495057;
            border: 3px solid #6c757d;
        }
        
        .stats-display {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .scanning-animation {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .status-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 25px;
            font-weight: bold;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .ready { background: #28a745; color: white; }
        .processing { background: #ffc107; color: #000; }
        .complete { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="scanner-interface">
        <div class="scanner-header">
            <h1><i class="fas fa-barcode"></i> TEKLEAD PHYSICAL SCANNER</h1>
            <h3>USB Barcode Scanner Attendance System</h3>
            <p class="mb-0">🎯 Scan student barcodes with your physical scanner</p>
        </div>
        
        <div class="scanner-body">
            <!-- Hidden form for auto-submission -->
            <form method="POST" action="" id="scannerForm" style="display: none;">
                <input type="hidden" id="barcodeData" name="barcode_data" value="">
            </form>
            
            <!-- Visible scanner input -->
            <div class="mb-4">
                <label class="form-label fs-4 fw-bold text-center d-block">
                    <i class="fas fa-qrcode"></i> Position scanner and scan student barcode:
                </label>
                <input type="text" 
                       id="scannerInput" 
                       class="scanner-input" 
                       placeholder="Ready for barcode scan..."
                       autocomplete="off"
                       readonly>
            </div>
            
            <!-- Result display -->
            <div id="resultDisplay" class="result-display idle">
                <div>
                    <i class="fas fa-info-circle"></i>
                    System ready - Scan a barcode with your TEKLEAD scanner
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-display">
                <div class="row">
                    <div class="col-md-6">
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                        <div class="fs-5 text-muted">Total Students</div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-number text-success"><?php echo $stats['scanned_today']; ?></div>
                        <div class="fs-5 text-muted">Scanned Today</div>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> How to use your TEKLEAD scanner:</h5>
                <ul class="mb-0">
                    <li><strong>Step 1:</strong> Make sure your TEKLEAD USB scanner is connected</li>
                    <li><strong>Step 2:</strong> Point scanner at student barcode</li>
                    <li><strong>Step 3:</strong> Press scanner trigger - system auto-processes</li>
                    <li><strong>No clicking required!</strong> Scanner automatically submits data</li>
                </ul>
            </div>
            
            <div class="text-center mt-4">
                <a href="reset_quick.php" class="btn btn-warning btn-lg">
                    <i class="fas fa-undo"></i> Reset Daily Attendance (Testing)
                </a>
            </div>
        </div>
    </div>
    
    <!-- Status indicator -->
    <div id="statusIndicator" class="status-indicator ready">
        <i class="fas fa-check-circle"></i> SCANNER READY
    </div>

    <script>
        // PHYSICAL TEKLEAD SCANNER AUTO-PROCESSING SYSTEM
        const scannerInput = document.getElementById('scannerInput');
        const scannerForm = document.getElementById('scannerForm');
        const barcodeData = document.getElementById('barcodeData');
        const resultDisplay = document.getElementById('resultDisplay');
        const statusIndicator = document.getElementById('statusIndicator');
        
        let isProcessing = false;
        let scanBuffer = '';
        let scanTimeout;
        
        console.log('🔥 TEKLEAD Physical Scanner System Active!');
        
        // Make input focusable and auto-focus
        scannerInput.removeAttribute('readonly');
        scannerInput.focus();
        
        // Update status
        function updateStatus(status, message, icon = 'check-circle') {
            statusIndicator.className = `status-indicator ${status}`;
            statusIndicator.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
        }
        
        // Show result
        function showResult(message, type) {
            resultDisplay.className = `result-display ${type}`;
            resultDisplay.innerHTML = `<div>${message}</div>`;
            
            if (type === 'success' || type === 'warning') {
                setTimeout(() => {
                    resultDisplay.className = 'result-display idle';
                    resultDisplay.innerHTML = '<div><i class="fas fa-info-circle"></i> Ready for next scan...</div>';
                }, 4000);
            }
        }
        
        // Process scanned barcode
        function processBarcode(barcode) {
            if (isProcessing) return;
            
            isProcessing = true;
            updateStatus('processing', 'PROCESSING SCAN...', 'spinner fa-spin');
            
            console.log('🚀 Processing barcode from physical scanner:', barcode);
            
            // Disable input temporarily
            scannerInput.style.background = '#fff3cd';
            scannerInput.style.borderColor = '#ffc107';
            
            // Set barcode data and submit
            barcodeData.value = barcode;
            
            setTimeout(() => {
                scannerForm.submit();
            }, 300);
        }
        
        // Handle input from TEKLEAD scanner
        scannerInput.addEventListener('input', function(e) {
            const currentValue = e.target.value.trim();
            
            console.log('📥 Scanner input detected:', currentValue);
            
            // Clear previous timeout
            clearTimeout(scanTimeout);
            
            // TEKLEAD scanners send complete barcode very quickly
            if (currentValue.length >= 6 && !isProcessing) {
                // Add visual feedback
                scannerInput.classList.add('scanning-animation');
                updateStatus('processing', 'BARCODE DETECTED', 'barcode');
                
                // Process after very short delay (TEKLEAD is fast)
                scanTimeout = setTimeout(() => {
                    processBarcode(currentValue);
                }, 150);
            }
        });
        
        // Handle Enter key from scanner
        scannerInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                const barcode = e.target.value.trim();
                
                console.log('⏎ Enter key from scanner:', barcode);
                
                if (barcode.length >= 6 && !isProcessing) {
                    processBarcode(barcode);
                }
            }
        });
        
        // Maintain focus on scanner input
        function maintainScannerFocus() {
            if (!isProcessing && document.activeElement !== scannerInput) {
                scannerInput.focus();
            }
        }
        
        // Focus management
        document.addEventListener('click', maintainScannerFocus);
        document.addEventListener('keydown', maintainScannerFocus);
        setInterval(maintainScannerFocus, 1000);
        
        // Handle page visibility
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && !isProcessing) {
                setTimeout(() => scannerInput.focus(), 200);
            }
        });
        
        // Reset after scan processing
        <?php if ($result_message): ?>
        updateStatus('complete', 'SCAN COMPLETE', 'check');
        showResult('<?php echo addslashes($result_message); ?>', '<?php echo $result_type; ?>');
        
        setTimeout(function() {
            scannerInput.value = '';
            scannerInput.style.background = '#f8f9fa';
            scannerInput.style.borderColor = '#28a745';
            scannerInput.classList.remove('scanning-animation');
            isProcessing = false;
            scannerInput.focus();
            updateStatus('ready', 'SCANNER READY', 'check-circle');
            console.log('🔄 Scanner reset and ready for next barcode');
        }, 3000);
        <?php endif; ?>
        
        // Initial setup
        updateStatus('ready', 'SCANNER READY', 'check-circle');
        console.log('✅ Physical TEKLEAD scanner system ready!');
        console.log('💡 Just scan barcodes - no clicking needed!');
    </script>
</body>
</html>
