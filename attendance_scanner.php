<?php
require_once 'config.php';

// Set Philippines timezone for all date operations
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'attendance_scanner';
$page_title = 'Barcode Attendance Scanner';

// ATTENDANCE CONFIRMATION PROCESSING
$attendance_message = '';
$attendance_type = '';

// Handle attendance confirmation (YES button clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_attendance'])) {
    $student_id = $_POST['student_id'] ?? null;
    $barcode = $_POST['barcode'] ?? null;
    $student_name = $_POST['student_name'] ?? null;
    
    // Debug: Log the received data
    error_log("DEBUG - Received POST data: student_id=" . var_export($student_id, true) . ", barcode=" . var_export($barcode, true) . ", student_name=" . var_export($student_name, true));
    error_log("DEBUG - POST array: " . print_r($_POST, true));
    
    // Validate required fields
    if (empty($student_id) || empty($barcode) || empty($student_name)) {
        $attendance_message = "❌ Error: Missing required student information! (ID: " . var_export($student_id, true) . ", Barcode: " . var_export($barcode, true) . ")";
        $attendance_type = 'error';
    } else {
        try {
            // Set Philippines timezone
            date_default_timezone_set('Asia/Manila');
            
            // Get current Philippines date and time
            $ph_date = date('Y-m-d');
            $ph_time = date('H:i:s');
            $ph_datetime = date('Y-m-d H:i:s');
        
        // Check if student already scanned TODAY in Philippines timezone
        $stmt = $pdo->prepare("
            SELECT * FROM daily_attendance 
            WHERE student_id = ? AND DATE(scan_datetime) = ?
        ");
        $stmt->execute([$student_id, $ph_date]);
        $existing_attendance = $stmt->fetch();
        
        if ($existing_attendance) {
            $attendance_message = "⚠️ {$student_name} already scanned today ({$ph_date})!";
            $attendance_type = 'error';
        } else {
            // Double-check student exists
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND barcode = ?");
            $stmt->execute([$student_id, $barcode]);
            $student = $stmt->fetch();
            
            error_log("DEBUG - Student lookup: student_id=$student_id, barcode=$barcode, found=" . ($student ? "YES" : "NO"));
            
            if ($student) {
                // Validate student_id is a valid integer
                if (!is_numeric($student_id) || intval($student_id) <= 0) {
                    throw new Exception("Invalid student ID: " . $student_id);
                }
                
                // Determine status based on Philippines time
                $status = ($ph_time > '08:00:00') ? 'late' : 'present';
                
                // Record attendance with Philippines timezone
                $stmt = $pdo->prepare("
                    INSERT INTO daily_attendance (student_id, barcode, scan_date, scan_time, scan_datetime, status) 
                    VALUES (?, ?, ?, ?, NOW(), ?)
                ");
                
                // Execute with proper type casting
                $result = $stmt->execute([
                    intval($student_id), 
                    $barcode, 
                    $ph_date, 
                    $ph_time, 
                    $status
                ]);
                
                if (!$result) {
                    throw new Exception("Failed to insert attendance record: " . implode(", ", $stmt->errorInfo()));
                }
                
                // Update is_scanned flag
                $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
                $stmt->execute([$student_id]);
                
                // Set success message here - main attendance is recorded
                $attendance_message = "✅ ATTENDANCE CONFIRMED! {$student_name} marked {$status} at " . date('g:i A');
                $attendance_type = 'success';
                
                // Try to update SF9 records (non-critical - don't fail if this doesn't work)
                try {
                    // Update SF9 attendance
                    $current_month = date('M');
                    $school_year = $student['school_year'];
                    
                    // Get or create SF9 form
                    $stmt = $pdo->prepare("
                        SELECT id FROM sf9_forms 
                        WHERE student_id = ? AND school_year = ?
                    ");
                    $stmt->execute([$student_id, $school_year]);
                    $sf9_form = $stmt->fetch();
                    
                    if (!$sf9_form) {
                        $stmt = $pdo->prepare("
                            INSERT INTO sf9_forms (student_id, school_year, lrn, grade_level, section) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $result = $stmt->execute([
                            intval($student_id), 
                            $school_year, 
                            $student['student_number'], 
                            $student['grade_level'], 
                            $student['section']
                        ]);
                        
                        if ($result) {
                            $sf9_form_id = $pdo->lastInsertId();
                        } else {
                            throw new Exception("Failed to create SF9 form");
                        }
                    } else {
                        $sf9_form_id = $sf9_form['id'];
                    }
                    
                    // Update SF9 attendance - INCREMENT days_present by 1
                    $stmt = $pdo->prepare("
                        INSERT INTO sf9_attendance (sf9_form_id, month, school_days, days_present, days_absent) 
                        VALUES (?, ?, 1, 1, 0)
                        ON DUPLICATE KEY UPDATE 
                        days_present = days_present + 1,
                        school_days = school_days + 1
                    ");
                    $stmt->execute([$sf9_form_id, $current_month]);
                    
                } catch (Exception $sf9_error) {
                    // SF9 update failed, but main attendance is still recorded
                    error_log("SF9 update failed (non-critical): " . $sf9_error->getMessage());
                    // Don't change the success message - attendance was still recorded successfully
                }
            } else {
                $attendance_message = "❌ Student verification failed for {$student_name}";
                $attendance_type = 'error';
            }
        }
        
    } catch (Exception $e) {
        $attendance_message = "❌ Database error: " . $e->getMessage();
        $attendance_type = 'error';
    }
    } // Close validation block
}

// Get today's attendance stats
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT da.*, s.first_name, s.last_name, s.student_number, s.grade_level, s.section
        FROM daily_attendance da
        JOIN students s ON da.student_id = s.id
        WHERE da.scan_date = ?
        ORDER BY da.scan_time DESC
    ");
    $stmt->execute([$today]);
    $today_attendance = $stmt->fetchAll();
    
    // Get attendance stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scanned,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as on_time,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late
        FROM daily_attendance 
        WHERE scan_date = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();
    
    // Get students data for scanning
    $stmt = $pdo->query("SELECT id, first_name, last_name, barcode, is_scanned FROM students WHERE barcode IS NOT NULL AND barcode != '' ORDER BY first_name, last_name");
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $today_attendance = [];
    $stats = ['total_scanned' => 0, 'on_time' => 0, 'late' => 0];
    $students = [];
}

// Start output buffering for page content
ob_start();
?>

<!-- BARCODE SCANNER WITH CONFIRMATION -->
<div id="scannerContainer" class="content-card fade-in" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; margin-bottom: 30px;">
    <div class="card-header text-center" style="border-bottom: none; background: transparent;">
        <h2 class="card-title" style="color: white;">
            <i class="fas fa-qrcode"></i> Attendance Scanner
        </h2>
        <p class="card-subtitle" style="color: rgba(255,255,255,0.9);">Scan student barcode and confirm attendance</p>
    </div>
    
    <div style="padding: 30px;">
        <?php if ($attendance_message): ?>
        <div class="alert alert-<?php echo $attendance_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
            <strong><?php echo htmlspecialchars($attendance_message); ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Scanner Input -->
        <div class="mb-4">
            <label class="form-label fs-5 fw-bold" style="color: white;">
                <i class="fas fa-barcode"></i> Enter or Scan Student Barcode:
            </label>
            <input type="text" 
                   id="scannerInput" 
                   class="form-control" 
                   placeholder="Enter student barcode here or use scanner..."
                   autocomplete="off"
                   autofocus
                   style="height: 60px; font-size: 1.5rem; text-align: center; border: 3px solid white;">
            <div class="form-text" style="color: rgba(255,255,255,0.8);">
                <i class="fas fa-info-circle"></i> 
                Type or scan student barcode, then click "Process Barcode" button
            </div>
        </div>
        
        <!-- Process Button -->
        <div class="text-center mb-4">
            <button type="button" id="processBtn" class="btn btn-warning btn-lg" onclick="processBarcode()" style="padding: 15px 30px; font-size: 1.2rem;">
                <i class="fas fa-search"></i> Process Barcode
            </button>
        </div>
        
        <!-- Pending Scan Indicator -->
        <div id="pendingIndicator" class="alert alert-warning" style="display: none;">
            <i class="fas fa-clock"></i> 
            <span id="pendingText">Barcode processed - waiting for confirmation...</span>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal" id="confirmationModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(45deg, #17a2b8, #138496); color: white; border-radius: 15px 15px 0 0;">
                <h3 class="modal-title">
                    <i class="fas fa-user-check"></i> Confirm Student Attendance
                </h3>
                <button type="button" class="btn-close" onclick="cancelScan()"></button>
            </div>
            <div class="modal-body" style="padding: 30px; text-align: center;">
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0; border-left: 5px solid #007bff;">
                    <h4 id="modalStudentName" class="text-primary">Student Name</h4>
                    <p class="mb-2"><strong>Barcode:</strong> <code id="modalBarcode">000000</code></p>
                    <p class="mb-0"><strong>Time:</strong> <span id="modalTime">00:00 AM</span></p>
                </div>
                
                <h5 class="text-center mt-3">
                    <i class="fas fa-question-circle"></i> 
                    Confirm attendance for this student?
                </h5>
                
                <div style="margin-top: 30px;">
                    <form method="POST" id="confirmForm" class="d-inline">
                        <input type="hidden" id="confirmStudentId" name="student_id">
                        <input type="hidden" id="confirmBarcode" name="barcode">
                        <input type="hidden" id="confirmStudentName" name="student_name">
                        <input type="hidden" name="confirm_attendance" value="1">
                        
                        <button type="submit" class="btn btn-success btn-lg me-3" style="padding: 15px 30px;">
                            <i class="fas fa-check"></i> YES - RECORD ATTENDANCE
                        </button>
                    </form>
                    <br><br>
                    <button type="button" class="btn btn-danger btn-lg" onclick="cancelScan()" style="padding: 15px 30px;">
                        <i class="fas fa-times"></i> NO - CANCEL
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Stats -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-bar"></i>
            Today's Attendance Summary
        </h3>
        <p class="card-subtitle"><?php echo date('F j, Y'); ?></p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-icon total">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <span class="stat-number"><?php echo $stats['total_scanned']; ?></span>
                <span class="stat-label">Total Scanned</span>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon present">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-number"><?php echo $stats['on_time']; ?></span>
                <span class="stat-label">On Time</span>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon late">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <span class="stat-number"><?php echo $stats['late']; ?></span>
                <span class="stat-label">Late</span>
            </div>
        </div>
    </div>
</div>

<!-- Today's Attendance List -->
<?php if (!empty($today_attendance)): ?>
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list-alt"></i>
            Today's Attendance Log
        </h3>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Student</th>
                    <th>Grade & Section</th>
                    <th>Status</th>
                    <th>Barcode</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($today_attendance as $record): ?>
                <tr>
                    <td>
                        <span class="time-badge">
                            <?php echo date('g:i A', strtotime($record['scan_time'])); ?>
                        </span>
                    </td>
                    <td>
                        <div class="student-info">
                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($record['student_number']); ?></small>
                        </div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($record['grade_level'] . ' - ' . $record['section']); ?>
                    </td>
                    <td>
                        <?php if ($record['status'] === 'present'): ?>
                            <span class="status-badge status-present">
                                <i class="fas fa-check"></i> Present
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-late">
                                <i class="fas fa-clock"></i> Late
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code class="barcode-text"><?php echo htmlspecialchars($record['barcode']); ?></code>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Custom Modal CSS (without Bootstrap conflicts) -->
<style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: flex !important;
        align-items: center;
        justify-content: center;
    }

    .modal-dialog {
        max-width: 500px;
        width: 90%;
        margin: 20px;
    }

    .modal-content {
        background: white;
        border-radius: 15px;
        border: none;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        overflow: hidden;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 20px 25px;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .btn-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.3s ease;
    }

    .btn-close:hover {
        background-color: rgba(255,255,255,0.2);
    }

    .btn-close::before {
        content: "×";
    }

    .modal-body {
        padding: 30px;
        text-align: center;
    }

    /* Alert Styles */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 8px;
        position: relative;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }

    .alert-dismissible .btn-close {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        color: inherit;
        font-size: 18px;
        width: 20px;
        height: 20px;
    }

    /* Form Styles */
    .form-control {
        display: block;
        width: 100%;
        padding: 12px 16px;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        border: 2px solid #ced4da;
        border-radius: 8px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #11998e;
        box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
    }

    .form-label {
        margin-bottom: 8px;
        font-weight: 600;
        display: block;
    }

    .form-text {
        margin-top: 8px;
        font-size: 0.875rem;
    }

    /* Button Styles */
    .btn {
        display: inline-block;
        padding: 12px 24px;
        font-size: 1rem;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 4px;
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40,167,69,0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220,53,69,0.4);
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        color: #212529;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,193,7,0.4);
    }

    .btn-lg {
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    /* Stats Styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .stat-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .stat-icon.total {
        background: linear-gradient(135deg, #11998e, #38ef7d);
    }
    
    .stat-icon.present {
        background: linear-gradient(135deg, #28a745, #20c997);
    }
    
    .stat-icon.late {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
    }
    
    .stat-details {
        flex: 1;
    }
    
    .stat-number {
        display: block;
        font-size: 2rem;
        font-weight: bold;
        color: #333;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .time-badge {
        background: #e9ecef;
        color: #495057;
        padding: 4px 8px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-present {
        background: #d4edda;
        color: #155724;
    }
    
    .status-late {
        background: #fff3cd;
        color: #856404;
    }
    
    .barcode-text {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
    }

    /* Utility classes */
    .text-center { text-align: center; }
    .text-primary { color: #007bff; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .mt-3 { margin-top: 1rem; }
    .me-3 { margin-right: 1rem; }
    .d-inline { display: inline; }
    .fs-5 { font-size: 1.25rem; }
    .fw-bold { font-weight: bold; }
</style>

<script>
    // Student data for lookup
    const studentsData = <?php echo json_encode(array_map(function($student) {
        return [
            'id' => $student['id'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'barcode' => $student['barcode'],
            'is_scanned' => $student['is_scanned']
        ];
    }, $students)); ?>;

    let isProcessing = false;

    // Find student by barcode
    function findStudent(barcode) {
        return studentsData.find(student => student.barcode === barcode);
    }

    // Process barcode function
    function processBarcode() {
        if (isProcessing) return;

        const barcodeInput = document.getElementById('scannerInput');
        const barcode = barcodeInput.value.trim();

        if (!barcode || barcode.length < 6) {
            alert('Please enter a valid barcode (at least 6 characters)');
            barcodeInput.focus();
            return;
        }

        console.log('🔍 Processing barcode:', barcode);

        // Find student
        const student = findStudent(barcode);

        if (student) {
            console.log('✅ Found student:', student.first_name, student.last_name);
            showConfirmationModal(barcode, student);
        } else {
            alert(`❌ No student found with barcode: ${barcode}\n\nPlease check if the barcode is correct.`);
            barcodeInput.focus();
        }
    }

    // Show confirmation modal
    function showConfirmationModal(barcode, student) {
        // Populate modal with student data
        const modalStudentName = document.getElementById('modalStudentName');
        const modalBarcode = document.getElementById('modalBarcode');
        const modalTime = document.getElementById('modalTime');
        const confirmStudentId = document.getElementById('confirmStudentId');
        const confirmBarcode = document.getElementById('confirmBarcode');
        const confirmStudentName = document.getElementById('confirmStudentName');
        
        if (modalStudentName) modalStudentName.textContent = `${student.first_name} ${student.last_name}`;
        if (modalBarcode) modalBarcode.textContent = barcode;
        if (modalTime) modalTime.textContent = new Date().toLocaleTimeString();
        
        // Set hidden form values
        if (confirmStudentId) confirmStudentId.value = student.id;
        if (confirmBarcode) confirmBarcode.value = barcode;
        if (confirmStudentName) confirmStudentName.value = `${student.first_name} ${student.last_name}`;
        
        // Show modal
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
        }
        
        // Show pending indicator
        const pendingIndicator = document.getElementById('pendingIndicator');
        if (pendingIndicator) {
            pendingIndicator.style.display = 'block';
            const pendingText = document.getElementById('pendingText');
            if (pendingText) {
                pendingText.textContent = 
                    `${student.first_name} ${student.last_name} (${barcode}) - waiting for confirmation...`;
            }
        }

        isProcessing = true;
    }

    // Cancel scan
    function cancelScan() {
        console.log('❌ Scan cancelled by user');
        
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
        
        resetScanner();
    }

    // Reset scanner state
    function resetScanner() {
        const barcodeInput = document.getElementById('scannerInput');
        if (barcodeInput) {
            barcodeInput.value = '';
            barcodeInput.focus();
        }
        
        const pendingIndicator = document.getElementById('pendingIndicator');
        if (pendingIndicator) {
            pendingIndicator.style.display = 'none';
        }
        
        isProcessing = false;
        console.log('🔄 Scanner reset and ready');
    }

    // Handle Enter key in barcode input
    document.addEventListener('DOMContentLoaded', function() {
        const barcodeInput = document.getElementById('scannerInput');
        
        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    processBarcode();
                }
            });

            // Focus the input
            barcodeInput.focus();
        }

        // Handle modal backdrop click
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    cancelScan();
                }
            });
        }

        // Handle Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                cancelScan();
            }
        });

        // Alert dismiss functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-close') && e.target.closest('.alert-dismissible')) {
                const alert = e.target.closest('.alert-dismissible');
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        });

        // Reset after successful submission
        <?php if ($attendance_message && $attendance_type === 'success'): ?>
        console.log('✅ SUCCESS: Resetting scanner for next barcode');
        setTimeout(function() {
            resetScanner();
        }, 3000);
        <?php endif; ?>

        // Reset after error
        <?php if ($attendance_message && $attendance_type === 'error'): ?>
        console.log('❌ ERROR: Resetting scanner');
        setTimeout(function() {
            resetScanner();
        }, 5000);
        <?php endif; ?>

        console.log('✅ Professional Attendance Scanner Ready!');
    });
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
