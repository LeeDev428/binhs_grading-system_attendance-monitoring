<?php
/**
 * TEKLEAD SCANNER WITH CONFIRMATION MODAL
 * Scans → Local Storage → Confirmation Modal → Database Update
 */

require_once 'config.php';

$result_message = '';
$result_type = '';

// Handle confirmation (YES button clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_attendance'])) {
    $student_id = $_POST['student_id'];
    $barcode = $_POST['barcode'];
    $student_name = $_POST['student_name'];
    
    try {
        // Double-check student exists and not already scanned
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND barcode = ? AND is_scanned = 0");
        $stmt->execute([$student_id, $barcode]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Update is_scanned to 1
            $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
            $success = $stmt->execute([$student_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                // Record attendance
                $status = (date('H:i') > '08:00') ? 'late' : 'present';
                $stmt = $pdo->prepare("
                    INSERT INTO daily_attendance (student_id, barcode, scan_date, scan_time, status) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE scan_time = VALUES(scan_time), status = VALUES(status)
                ");
                $stmt->execute([$student_id, $barcode, date('Y-m-d'), date('H:i:s'), $status]);
                
                $result_message = "✅ ATTENDANCE CONFIRMED! {$student_name} marked {$status} at " . date('g:i A');
                $result_type = 'success';
            } else {
                $result_message = "❌ Failed to update attendance for {$student_name}";
                $result_type = 'error';
            }
        } else {
            $result_message = "❌ Student verification failed for {$student_name}";
            $result_type = 'error';
        }
        
    } catch (Exception $e) {
        $result_message = "❌ Database error: " . $e->getMessage();
        $result_type = 'error';
    }
}

// Get student data for JavaScript
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, barcode, is_scanned FROM students WHERE barcode IS NOT NULL");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $students_json = json_encode($students);
} catch (Exception $e) {
    $students_json = '[]';
}

// Get today's stats
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
    <title>TEKLEAD Scanner with Confirmation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
        }
        
        .scanner-container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .scanner-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .scanner-body {
            padding: 30px;
        }
        
        .scanner-input {
            width: 100%;
            height: 70px;
            font-size: 2rem;
            text-align: center;
            border: 4px solid #28a745;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .scanner-input:focus {
            border-color: #007bff;
            background: #e3f2fd;
            box-shadow: 0 0 20px rgba(0,123,255,0.4);
            outline: none;
        }
        
        .confirmation-modal .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .confirmation-modal .modal-header {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px;
        }
        
        .confirmation-modal .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .student-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid #007bff;
        }
        
        .confirm-buttons {
            margin-top: 30px;
        }
        
        .btn-confirm-yes {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 10px;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-confirm-no {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 10px;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-confirm-yes:hover, .btn-confirm-no:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .stats-display {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .scan-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 25px;
            font-weight: bold;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .status-ready { background: #28a745; color: white; }
        .status-scanning { background: #ffc107; color: #000; }
        .status-pending { background: #17a2b8; color: white; }
        
        .pending-indicator {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="scanner-header">
            <h1><i class="fas fa-qrcode"></i> TEKLEAD SCANNER WITH CONFIRMATION</h1>
            <h4>Scan → Confirm → Attendance Recorded</h4>
            <p class="mb-0">🎯 Your physical scanner stores data locally, then confirms attendance</p>
        </div>
        
        <div class="scanner-body">
            <?php if ($result_message): ?>
            <div class="alert alert-<?php echo $result_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
                <strong><?php echo htmlspecialchars($result_message); ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Scanner Input -->
            <div class="mb-4">
                <label class="form-label fs-5 fw-bold">
                    <i class="fas fa-barcode"></i> Scan Student Barcode:
                </label>
                <input type="text" 
                       id="scannerInput" 
                       class="scanner-input" 
                       placeholder="Ready for barcode scan..."
                       autocomplete="off"
                       autofocus>
                <div class="form-text">
                    <i class="fas fa-info-circle"></i> 
                    Point your TEKLEAD scanner at student barcode and scan
                </div>
            </div>
            
            <!-- Pending Scan Indicator -->
            <div id="pendingIndicator" class="pending-indicator" style="display: none;">
                <i class="fas fa-clock"></i> 
                <span id="pendingText">Barcode scanned - waiting for confirmation...</span>
            </div>
            
            <!-- Statistics -->
            <div class="stats-display">
                <div class="row">
                    <div class="col-md-6">
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                        <div class="fs-6 text-muted">Total Students</div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-number text-success"><?php echo $stats['scanned_today']; ?></div>
                        <div class="fs-6 text-muted">Attendance Confirmed</div>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="alert alert-info">
                <h6><i class="fas fa-list-ol"></i> How it works:</h6>
                <ol class="mb-0">
                    <li><strong>Scan:</strong> Use your TEKLEAD scanner to scan student barcode</li>
                    <li><strong>Confirm:</strong> System shows confirmation modal with student details</li>
                    <li><strong>Decide:</strong> Click YES to record attendance or NO to cancel</li>
                    <li><strong>Complete:</strong> Database updates only after confirmation</li>
                </ol>
            </div>
            
            <div class="text-center">
                <a href="reset_quick.php" class="btn btn-warning">
                    <i class="fas fa-undo"></i> Reset Daily Attendance
                </a>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade confirmation-modal" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-user-check"></i> Confirm Student Entry
                    </h3>
                </div>
                <div class="modal-body">
                    <div class="student-info">
                        <h4 id="modalStudentName" class="text-primary">Student Name</h4>
                        <p class="mb-2"><strong>Barcode:</strong> <code id="modalBarcode">000000</code></p>
                        <p class="mb-0"><strong>Time:</strong> <span id="modalTime">00:00 AM</span></p>
                    </div>
                    
                    <h5 class="text-center mt-3">
                        <i class="fas fa-question-circle"></i> 
                        Confirm attendance for this student?
                    </h5>
                    
                    <div class="confirm-buttons">
                        <form method="POST" id="confirmForm" class="d-inline">
                            <input type="hidden" id="confirmStudentId" name="student_id">
                            <input type="hidden" id="confirmBarcode" name="barcode">
                            <input type="hidden" id="confirmStudentName" name="student_name">
                            <input type="hidden" name="confirm_attendance" value="1">
                            
                            <button type="submit" class="btn-confirm-yes">
                                <i class="fas fa-check"></i> YES - RECORD ATTENDANCE
                            </button>
                        </form>
                        
                        <button type="button" class="btn-confirm-no" onclick="cancelScan()">
                            <i class="fas fa-times"></i> NO - CANCEL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Indicator -->
    <div id="statusIndicator" class="scan-status status-ready">
        <i class="fas fa-check-circle"></i> READY
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // TEKLEAD SCANNER WITH LOCAL STORAGE & CONFIRMATION SYSTEM
        const scannerInput = document.getElementById('scannerInput');
        const statusIndicator = document.getElementById('statusIndicator');
        const pendingIndicator = document.getElementById('pendingIndicator');
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        
        // Student data from PHP
        const studentsData = <?php echo $students_json; ?>;
        
        let isProcessing = false;
        let scanTimeout;
        
        console.log('🔥 TEKLEAD Scanner with Confirmation System Ready!');
        console.log('📋 Loaded', studentsData.length, 'students');
        
        // Update status indicator
        function updateStatus(status, message, icon = 'check-circle') {
            statusIndicator.className = `scan-status status-${status}`;
            statusIndicator.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
        }
        
        // Find student by barcode
        function findStudent(barcode) {
            return studentsData.find(student => student.barcode === barcode);
        }
        
        // Store scanned barcode in localStorage
        function storeScannedBarcode(barcode, studentData) {
            const scanData = {
                barcode: barcode,
                student: studentData,
                scanTime: new Date().toISOString(),
                timestamp: Date.now()
            };
            localStorage.setItem('pendingBarcodeScan', JSON.stringify(scanData));
            console.log('💾 Stored in localStorage:', scanData);
        }
        
        // Show confirmation modal
        function showConfirmationModal(barcode, student) {
            // Populate modal with student data
            document.getElementById('modalStudentName').textContent = `${student.first_name} ${student.last_name}`;
            document.getElementById('modalBarcode').textContent = barcode;
            document.getElementById('modalTime').textContent = new Date().toLocaleTimeString();
            
            // Set hidden form values
            document.getElementById('confirmStudentId').value = student.id;
            document.getElementById('confirmBarcode').value = barcode;
            document.getElementById('confirmStudentName').value = `${student.first_name} ${student.last_name}`;
            
            // Show modal
            confirmationModal.show();
            
            // Update status
            updateStatus('pending', 'AWAITING CONFIRMATION', 'question-circle');
            
            // Show pending indicator
            pendingIndicator.style.display = 'block';
            document.getElementById('pendingText').textContent = 
                `${student.first_name} ${student.last_name} (${barcode}) - waiting for confirmation...`;
        }
        
        // Process scanned barcode
        function processScannedBarcode(barcode) {
            if (isProcessing) return;
            
            isProcessing = true;
            updateStatus('scanning', 'PROCESSING...', 'spinner fa-spin');
            
            console.log('🔍 Processing scanned barcode:', barcode);
            
            // Find student
            const student = findStudent(barcode);
            
            if (student) {
                if (student.is_scanned == 1) {
                    alert(`⚠️ ${student.first_name} ${student.last_name} already scanned today!`);
                    resetScanner();
                    return;
                }
                
                // Store in localStorage
                storeScannedBarcode(barcode, student);
                
                // Show confirmation modal
                showConfirmationModal(barcode, student);
                
            } else {
                alert(`❌ No student found with barcode: ${barcode}`);
                resetScanner();
            }
        }
        
        // Cancel scan
        function cancelScan() {
            console.log('❌ Scan cancelled by user');
            localStorage.removeItem('pendingBarcodeScan');
            confirmationModal.hide();
            resetScanner();
        }
        
        // Reset scanner state
        function resetScanner() {
            scannerInput.value = '';
            scannerInput.style.background = '#f8f9fa';
            scannerInput.style.borderColor = '#28a745';
            isProcessing = false;
            scannerInput.focus();
            updateStatus('ready', 'READY', 'check-circle');
            pendingIndicator.style.display = 'none';
            console.log('🔄 Scanner reset and ready');
        }
        
        // Handle input from TEKLEAD scanner
        scannerInput.addEventListener('input', function(e) {
            const currentValue = e.target.value.trim();
            
            console.log('📥 Scanner input:', currentValue);
            
            // Clear previous timeout
            clearTimeout(scanTimeout);
            
            // Visual feedback
            if (currentValue.length > 0) {
                e.target.style.background = '#fff3cd';
                e.target.style.borderColor = '#ffc107';
            }
            
            // Process when complete barcode detected
            if (currentValue.length >= 6 && !isProcessing) {
                scanTimeout = setTimeout(() => {
                    processScannedBarcode(currentValue);
                }, 200); // Small delay for TEKLEAD scanner
            }
        });
        
        // Handle Enter key from scanner
        scannerInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                const barcode = e.target.value.trim();
                
                console.log('⏎ Enter key detected:', barcode);
                
                if (barcode.length >= 6 && !isProcessing) {
                    processScannedBarcode(barcode);
                }
            }
        });
        
        // Maintain focus on scanner input
        function maintainFocus() {
            if (!isProcessing && document.activeElement !== scannerInput) {
                scannerInput.focus();
            }
        }
        
        document.addEventListener('click', maintainFocus);
        setInterval(maintainFocus, 1000);
        
        // Handle modal close
        document.getElementById('confirmationModal').addEventListener('hidden.bs.modal', function() {
            if (localStorage.getItem('pendingBarcodeScan')) {
                // Modal closed without confirmation
                cancelScan();
            }
        });
        
        // Clear localStorage on successful submission
        <?php if ($result_message && $result_type === 'success'): ?>
        localStorage.removeItem('pendingBarcodeScan');
        setTimeout(resetScanner, 2000);
        <?php endif; ?>
        
        // Check for pending scans on page load
        window.addEventListener('load', function() {
            const pendingScan = localStorage.getItem('pendingBarcodeScan');
            if (pendingScan) {
                console.log('🔄 Found pending scan in localStorage, clearing...');
                localStorage.removeItem('pendingBarcodeScan');
            }
            resetScanner();
        });
        
        console.log('✅ TEKLEAD Scanner with Confirmation ready!');
        console.log('💡 Scan → Store Locally → Confirm → Update Database');
    </script>
</body>
</html>
