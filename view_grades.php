<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set Philippines timezone for all date operations
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'view_grades';
$page_title = 'Student Grades Overview';

// ATTENDANCE SCANNER CONFIRMATION PROCESSING
$attendance_message = '';
$attendance_type = '';

// Handle attendance confirmation (YES button clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_attendance'])) {
    $student_id = $_POST['student_id'];
    $barcode = $_POST['barcode'];
    $student_name = $_POST['student_name'];
    
    try {
        // ===== SET PHILIPPINES TIMEZONE =====
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
            
            if ($student) {
                // Determine status based on Philippines time
                $status = ($ph_time > '08:00:00') ? 'late' : 'present';
                
                // Record attendance with Philippines timezone
                $stmt = $pdo->prepare("
                    INSERT INTO daily_attendance (student_id, barcode, scan_date, scan_time, scan_datetime, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $barcode, $ph_date, $ph_time, $ph_datetime, $status]);
                
                // Update is_scanned flag (optional - for daily reset)
                $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
                $stmt->execute([$student_id]);
                
                $attendance_message = "✅ ATTENDANCE CONFIRMED! {$student_name} marked {$status} at " . date('g:i A') . " (Philippines Time)";
                $attendance_type = 'success';
            } else {
                $attendance_message = "❌ Student verification failed for {$student_name}";
                $attendance_type = 'error';
            }
        }
        
    } catch (Exception $e) {
        $attendance_message = "❌ Database error: " . $e->getMessage();
        $attendance_type = 'error';
    }
}

// Get all students with their grades
try {
    // First, let's get all students
    $stmt = $pdo->query("SELECT * FROM students ORDER BY last_name, first_name");
    $all_students = $stmt->fetchAll();
    
    $students = [];
    
    foreach ($all_students as $student) {
        // Get student grades if they exist
        $grade_stmt = $pdo->prepare("
            SELECT 
                AVG(final_grade) as general_average,
                COUNT(*) as subject_count,
                COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as failed_subjects
            FROM student_grades 
            WHERE student_id = ?
        ");
        $grade_stmt->execute([$student['id']]);
        $grade_data = $grade_stmt->fetch();
        
        // Get honor type if exists
        $honor_stmt = $pdo->prepare("
            SELECT honor_type 
            FROM student_honors 
            WHERE student_id = ? AND school_year = ?
        ");
        $honor_stmt->execute([$student['id'], $student['school_year']]);
        $honor_data = $honor_stmt->fetch();
        
        // Combine student data with grades and honors
        $student_info = array_merge($student, [
            'general_average' => $grade_data['general_average'] ?? null,
            'subject_count' => $grade_data['subject_count'] ?? 0,
            'failed_subjects' => $grade_data['failed_subjects'] ?? 0,
            'honor_type' => $honor_data['honor_type'] ?? null
        ]);
        
        $students[] = $student_info;
    }
    
} catch (PDOException $e) {
    $students = [];
    $error = 'Could not load student data: ' . $e->getMessage();
}

// Start output buffering for page content
ob_start();
?>

<!-- TEKLEAD BARCODE SCANNER WITH CONFIRMATION -->
<div id="scannerContainer" class="content-card fade-in" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; margin-bottom: 30px;">
    <div class="card-header text-center" style="border-bottom: none; background: transparent;">
        <h2 class="card-title" style="color: white;">
            <i class="fas fa-qrcode"></i> TEKLEAD Barcode Scanner
        </h2>
        <p class="card-subtitle" style="color: rgba(255,255,255,0.9);">Physical scanner will trigger attendance confirmation</p>
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
                <i class="fas fa-barcode"></i> Scan Student Barcode:
            </label>
            <input type="text" 
                   id="scannerInput" 
                   class="form-control" 
                   placeholder="Use your TEKLEAD scanner to scan student barcode here for attendance..."
                   autocomplete="off"
                   autofocus
                   style="height: 60px; font-size: 1.5rem; text-align: center; border: 3px solid white;">
            <div class="form-text" style="color: rgba(255,255,255,0.8);">
                <i class="fas fa-info-circle"></i> 
                Click here, then physically scan student barcode to trigger attendance confirmation
            </div>
        </div>
        
        <!-- Pending Scan Indicator -->
        <div id="pendingIndicator" class="alert alert-warning" style="display: none;">
            <i class="fas fa-clock"></i> 
            <span id="pendingText">Barcode scanned - waiting for confirmation...</span>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(45deg, #17a2b8, #138496); color: white; border-radius: 15px 15px 0 0;">
                <h3 class="modal-title">
                    <i class="fas fa-user-check"></i> Confirm Student Entry
                </h3>
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
                    <br>
                    <br>
                    <button type="button" class="btn btn-danger btn-lg" onclick="cancelScan()" style="padding: 15px 30px;">
                        <i class="fas fa-times"></i> NO - CANCEL
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="content-card fade-in" style="border-left: 4px solid #dc3545;">
        <div style="padding: 20px; background-color: #f8d7da; color: #721c24; border-radius: 4px;">
            <h4><i class="fas fa-exclamation-triangle"></i> Error</h4>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($students)): ?>
    <!-- Empty State -->
    <div class="content-card fade-in">
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 4rem; color: #ccc; margin-bottom: 20px;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h3 style="color: #666; margin-bottom: 10px;">No Students Found</h3>
            <p style="color: #999; margin-bottom: 30px;">Start by adding student grades using the form below.</p>
            <a href="student_grades.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add First Student
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Statistics Overview -->
    <div class="content-card fade-in">
        <div class="card-header">
            <h2 class="card-title">Academic Overview</h2>
            <p class="card-subtitle">Summary of all student academic performance</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo count($students); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $honor_students = array_filter($students, function($s) { return !empty($s['honor_type']); });
                    echo count($honor_students);
                    ?>
                </div>
                <div class="stat-label">Honor Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $failing_students = array_filter($students, function($s) { return $s['failed_subjects'] > 0; });
                    echo count($failing_students);
                    ?>
                </div>
                <div class="stat-label">Students with Failed Subjects</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $all_averages = array_column($students, 'general_average');
                    $valid_averages = array_filter($all_averages, function($avg) { 
                        return !is_null($avg) && $avg > 0; 
                    });
                    
                    if (count($valid_averages) > 0) {
                        $avg_grade = array_sum($valid_averages) / count($valid_averages);
                        echo number_format($avg_grade, 2);
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Overall Average</div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="content-card fade-in">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
            <div style="display: flex; gap: 10px;">
                <a href="student_grades.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Student
                </a>
                <button class="btn btn-info" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Print Report
                </button>
                <button class="btn btn-success" onclick="exportData()">
                    <i class="fas fa-download"></i>
                    Export Data
                </button>
            </div>
        </div>
    </div>
    
    <!-- Students List -->
    <div class="content-card fade-in">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users"></i>
                All Students
            </h3>
            <p class="card-subtitle">Click "View" to see complete student details and grades</p>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Number</th>
                        <th>Student Name</th>
                        <th>Grade & Section</th>
                        <th>School Year</th>
                        <th>General Average</th>
                        <th>Status</th>
                        <th>Honors</th>
                        <th>Barcode</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <span class="student-number"><?php echo htmlspecialchars($student['student_number']); ?></span>
                        </td>
                        <td>
                            <div class="student-info">
                                <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                                <?php if ($student['middle_name']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($student['middle_name']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="grade-section">
                                <?php echo htmlspecialchars($student['grade_level']); ?>
                                <?php if ($student['section']): ?>
                                    <br><small class="text-muted">Section: <?php echo htmlspecialchars($student['section']); ?></small>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($student['school_year']); ?></td>
                        <td>
                            <?php if ($student['general_average']): ?>
                                <?php 
                                $avg = $student['general_average'];
                                $class = '';
                                $icon = '';
                                if ($avg >= 95) {
                                    $class = 'badge-excellent';
                                    $icon = 'fas fa-star';
                                } elseif ($avg >= 85) {
                                    $class = 'badge-good';
                                    $icon = 'fas fa-thumbs-up';
                                } elseif ($avg >= 75) {
                                    $class = 'badge-average';
                                    $icon = 'fas fa-check';
                                } else {
                                    $class = 'badge-poor';
                                    $icon = 'fas fa-exclamation';
                                }
                                ?>
                                <span class="grade-badge <?php echo $class; ?>">
                                    <i class="<?php echo $icon; ?>"></i>
                                    <?php echo number_format($avg, 2); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-grades">No grades yet</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['failed_subjects'] > 0): ?>
                                <span class="status-badge status-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo $student['failed_subjects']; ?> Failed
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-success">
                                    <i class="fas fa-check-circle"></i>
                                    All Passed
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['honor_type']): ?>
                                <span class="honor-badge">
                                    <i class="fas fa-medal"></i>
                                    <?php echo htmlspecialchars($student['honor_type']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($student['barcode'])): ?>
                                <div class="barcode-cell">
                                    <button type="button" class="btn btn-outline-primary btn-sm view-barcode-btn" 
                                            data-barcode="<?php echo htmlspecialchars($student['barcode']); ?>"
                                            data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                            title="View barcode">
                                        <i class="fas fa-barcode"></i> View Barcode
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No barcode</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" 
                                   class="btn btn-primary btn-view" 
                                   title="View complete student details and grades">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                <a href="student_grades.php?edit=<?php echo $student['id']; ?>" 
                                   class="btn btn-sm btn-success" 
                                   title="Edit student grades">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
    .student-number {
        font-weight: 600;
        color: #11998e;
        font-size: 0.95rem;
    }
    
    .student-info strong {
        color: #333;
        font-size: 1rem;
    }
    
    .grade-section {
        font-weight: 500;
        color: #333;
    }
    
    .no-grades {
        color: #999;
        font-style: italic;
        font-size: 0.9rem;
    }
    
    .grade-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .badge-excellent {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-good {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-average {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-poor {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-success {
        background: #d4edda;
        color: #155724;
    }
    
    .status-warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .honor-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
        color: #333;
        box-shadow: 0 2px 5px rgba(255, 215, 0, 0.3);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
    }
    
    .btn-view {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 8px 16px;
        font-size: 0.9rem;
        font-weight: 600;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
        border: none;
    }
    
    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .btn-sm {
        padding: 6px 10px;
        font-size: 0.8rem;
        border-radius: 4px;
    }
    
    .text-muted {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 8px;
    }
    
    .data-table tbody tr:hover {
        background-color: #f8f9fa;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    
    .data-table th {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        font-weight: 600;
        text-align: center;
        padding: 15px 12px;
        border: none;
    }
    
    .data-table td {
        text-align: center;
        vertical-align: middle;
        padding: 15px 12px;
        border-bottom: 1px solid #e9ecef;
    }

      .modal-backdrop {
        backdrop-filter: blur(5px);
    }
    
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white;
        border-radius: 15px 15px 0 0;
        border-bottom: none;
    }
    
    .barcode-container {
        background: white;
        padding: 30px;
        border: 3px solid #ddd;
        border-radius: 10px;
        display: inline-block;
        margin: 20px auto;
    }
    
    #barcodeCanvas {
        max-width: 100%;
        height: auto;
    }
    
    .barcode-text {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        font-weight: bold;
        letter-spacing: 2px;
    }
    
    /* Print styles */
    @media print {
        .sidebar,
        .top-header,
        .btn,
        .action-buttons {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
        
        .content-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        .data-table {
            font-size: 12px;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 4px;
            font-size: 0.8rem;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 4px;
        }
        
        .btn-view {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
    }
</style>

<script>
    function exportData() {
        // Simple CSV export functionality
        const table = document.querySelector('.data-table');
        const rows = Array.from(table.querySelectorAll('tr'));
        
        const csvContent = rows.map(row => {
            const cells = Array.from(row.querySelectorAll('th, td'));
            return cells.map(cell => {
                // Clean up cell content for CSV
                const text = cell.textContent.trim().replace(/\s+/g, ' ');
                return `"${text.replace(/"/g, '""')}"`;
            }).join(',');
        }).join('\n');
        
        // Create download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'student_grades_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<!-- Custom Modal CSS (replacing Bootstrap) -->
<style>
    /* Modal Backdrop */
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

    .modal-dialog.modal-lg {
        max-width: 800px;
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
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white;
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

    /* Custom Alert Styles */
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

    .btn-primary {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,123,255,0.4);
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

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #545b62);
        color: white;
    }

    .btn-lg {
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    .btn-outline-primary {
        background: transparent;
        color: #007bff;
        border: 2px solid #007bff;
    }

    .btn-outline-primary:hover {
        background: #007bff;
        color: white;
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 0.875rem;
    }

    /* Utility classes */
    .text-center { text-align: center; }
    .text-primary { color: #007bff; }
    .text-success { color: #28a745; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .mt-3 { margin-top: 1rem; }
    .me-3 { margin-right: 1rem; }
    .d-flex { display: flex; }
    .d-inline { display: inline; }
    .gap-2 { gap: 0.5rem; }
    .justify-content-center { justify-content: center; }
    .font-monospace { font-family: 'Courier New', monospace; }

    /* Barcode specific styles */
    .barcode-container {
        background: white;
        padding: 30px;
        border: 3px solid #ddd;
        border-radius: 10px;
        display: inline-block;
        margin: 20px auto;
    }

    #barcodeCanvas {
        max-width: 100%;
        height: auto;
    }

    .barcode-text {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        font-weight: bold;
        letter-spacing: 2px;
    }
</style>

<!-- Custom Modal JavaScript (replacing Bootstrap) -->
<script>
    // Custom Modal Implementation
    class CustomModal {
        constructor(element) {
            this.element = element;
            this.backdrop = null;
        }

        show() {
            this.element.classList.add('show');
            this.element.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Close on backdrop click
            this.element.addEventListener('click', (e) => {
                if (e.target === this.element) {
                    this.hide();
                }
            });

            // Close on close button click
            const closeBtn = this.element.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hide());
            }

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.hide();
                }
            });
        }

        hide() {
            this.element.classList.remove('show');
            this.element.style.display = 'none';
            document.body.style.overflow = '';
            this.element.dispatchEvent(new Event('hidden.bs.modal'));
        }

        static getInstance(element) {
            if (!element._customModal) {
                element._customModal = new CustomModal(element);
            }
            return element._customModal;
        }
    }

    // Replace Bootstrap Modal with Custom Modal
    window.bootstrap = {
        Modal: CustomModal
    };

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
</script>



<!-- Barcode Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="barcodeModalLabel">
                    <i class="fas fa-barcode"></i>
                    Student Barcode
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="barcode-display">
                    <h4 id="studentNameDisplay" class="mb-4 text-primary"></h4>
                    <div class="barcode-container mb-4" style="background: white; padding: 20px; border: 2px solid #ddd; border-radius: 8px;">
                        <canvas id="barcodeCanvas"></canvas>
                    </div>
                    <div class="barcode-text mb-4 p-3" style="background: #f8f9fa; border-radius: 8px;">
                        <strong>Barcode: </strong>
                        <span id="barcodeTextDisplay" class="font-monospace text-success"></span>
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="printBarcode()">
                            <i class="fas fa-print"></i> Print Barcode
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testScan()">
                            <i class="fas fa-qrcode"></i> Test Scanner
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
<script>
    // Barcode modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Handle view barcode button clicks - ONLY for viewing/printing, NOT for attendance
        document.querySelectorAll('.view-barcode-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const barcode = this.getAttribute('data-barcode');
                const studentName = this.getAttribute('data-student-name');
                
                // Update modal content for viewing only
                document.getElementById('studentNameDisplay').textContent = studentName;
                document.getElementById('barcodeTextDisplay').textContent = barcode;
                
                // Generate barcode for viewing/printing
                try {
                    JsBarcode("#barcodeCanvas", barcode, {
                        format: "CODE128",
                        width: 2,
                        height: 60,
                        displayValue: true,
                        fontSize: 14,
                        margin: 10,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                    console.log('📊 Barcode generated for viewing/printing');
                } catch (error) {
                    console.error('Error generating barcode:', error);
                    alert('Error generating barcode: ' + error.message);
                }
                
                // Show barcode modal for viewing/printing ONLY
                const modalElement = document.getElementById('barcodeModal');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                
                console.log('👁️ Showing barcode for viewing - NO automatic attendance action');
            });
        });
    });
    
    function printBarcode() {
        const canvas = document.getElementById('barcodeCanvas');
        const studentName = document.getElementById('studentNameDisplay').textContent;
        const barcodeText = document.getElementById('barcodeTextDisplay').textContent;
        
        // Create print window
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Student Barcode - ${studentName}</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        text-align: center; 
                        margin: 50px; 
                    }
                    .barcode-print { 
                        border: 2px solid #333; 
                        padding: 20px; 
                        display: inline-block; 
                        margin: 20px;
                    }
                    h2 { margin-bottom: 20px; }
                    .barcode-text { 
                        margin-top: 10px; 
                        font-weight: bold; 
                        font-family: monospace;
                    }
                </style>
            </head>
            <body>
                <div class="barcode-print">
                    <h2>${studentName}</h2>
                    <img src="${canvas.toDataURL()}" />
                    <div class="barcode-text">${barcodeText}</div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    function testScan() {
        const barcode = document.getElementById('barcodeTextDisplay').textContent;
        
        // Close the current modal first
        const currentModal = bootstrap.Modal.getInstance(document.getElementById('barcodeModal'));
        if (currentModal) {
            currentModal.hide();
        }
        
        // Open scanner page in new tab for testing
        const scannerUrl = 'attendance_scanner.php';
        window.open(scannerUrl, '_blank');
        
        // Show instructions
        setTimeout(function() {
            alert(`📱 Scanner Test Ready!\n\nBarcode: ${barcode}\n\n1. The attendance scanner opened in a new tab\n2. Click in the scan input field\n3. Scan this barcode with your TEKLEAD scanner\n4. Attendance will be recorded in sf9_attendance table!`);
        }, 500);
    }
    
    // ===== TEKLEAD BARCODE SCANNER WITH CONFIRMATION SYSTEM =====
    const scannerInput = document.getElementById('scannerInput');
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    const pendingIndicator = document.getElementById('pendingIndicator');
    
    let isProcessing = false;
    let scanTimeout;
    let scannedData = '';
    
    console.log('🔥 TEKLEAD Scanner integrated into view_grades.php!');
    
    // Student data for quick lookup
    const studentsData = <?php echo json_encode(array_map(function($student) {
        return [
            'id' => $student['id'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'barcode' => $student['barcode'],
            'is_scanned' => $student['is_scanned']
        ];
    }, $students)); ?>;
    
    // Find student by barcode
    function findStudent(barcode) {
        return studentsData.find(student => student.barcode === barcode);
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
        
        // Show pending indicator
        pendingIndicator.style.display = 'block';
        document.getElementById('pendingText').textContent = 
            `${student.first_name} ${student.last_name} (${barcode}) - waiting for confirmation...`;
    }
    
    // Process scanned barcode
    function processScannedBarcode(barcode) {
        if (isProcessing) return;
        
        isProcessing = true;
        console.log('🔍 Processing scanned barcode:', barcode);
        
        // *** CLOSE "Student Barcode" modal if open before showing confirmation ***
        const barcodeModal = document.getElementById('barcodeModal');
        const barcodeModalInstance = bootstrap.Modal.getInstance(barcodeModal);
        if (barcodeModalInstance && barcodeModal.classList.contains('show')) {
            console.log('📤 Closing "Student Barcode" modal before confirmation');
            barcodeModalInstance.hide();
            
            // Wait for modal to close completely before proceeding
            barcodeModal.addEventListener('hidden.bs.modal', function() {
                proceedWithConfirmation();
            }, { once: true });
            
            return;
        }
        
        // If no modal is open, proceed directly
        proceedWithConfirmation();
        
        function proceedWithConfirmation() {
            // Find student
            const student = findStudent(barcode);
            
            if (student) {
                // Note: We'll let the server-side check for today's attendance
                // Client-side is_scanned flag might not be accurate for daily attendance
                console.log('🔍 Found student:', student.first_name, student.last_name);
                
                // Store in localStorage for confirmation
                const scanData = {
                    barcode: barcode,
                    student: student,
                    scanTime: new Date().toISOString(),
                    timestamp: Date.now()
                };
                localStorage.setItem('pendingBarcodeScan', JSON.stringify(scanData));
                
                // Show confirmation modal (server will check if already scanned today)
                showConfirmationModal(barcode, student);
                
            } else {
                alert(`❌ No student found with barcode: ${barcode}`);
                resetScanner();
            }
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
        if (scannerInput) {
            scannerInput.value = '';
            scannerInput.style.background = '';
            scannerInput.style.borderColor = '';
            scannerInput.focus();
        }
        isProcessing = false;
        scannedData = '';
        pendingIndicator.style.display = 'none';
        console.log('🔄 Scanner reset and ready');
    }
    
    // ===== GLOBAL TEKLEAD SCANNER CAPTURE =====
    // Capture ALL keyboard input globally for TEKLEAD scanner
    document.addEventListener('keypress', function(e) {
        // Skip if typing in input fields, textareas, or content editable elements
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.contentEditable === 'true') {
            return;
        }
        
        const char = String.fromCharCode(e.which || e.keyCode);
        
        // If it's a printable character, add to scanned data
        if (char && char.match(/[0-9a-zA-Z-]/)) {
            scannedData += char;
            console.log('🔍 TEKLEAD Scanner detected character:', char, 'Building:', scannedData);
            
            // Clear previous timeout
            clearTimeout(scanTimeout);
            
            // Set timeout to process complete barcode
            scanTimeout = setTimeout(() => {
                if (scannedData.length >= 6 && !isProcessing) {
                    console.log('✅ Complete barcode captured from TEKLEAD:', scannedData);
                    
                    // AUTO-POPULATE the scanner input field
                    if (scannerInput) {
                        scannerInput.value = scannedData;
                        scannerInput.focus();
                        
                        // Visual feedback
                        scannerInput.style.background = '#d4edda';
                        scannerInput.style.borderColor = '#28a745';
                        
                        console.log('🎯 AUTO-POPULATED scannerInput with:', scannedData);
                        
                        // Trigger the confirmation modal
                        processScannedBarcode(scannedData);
                    }
                }
                
                // Reset scanned data
                scannedData = '';
            }, 150); // Short timeout for complete barcode
        }
    });
    
    // Handle Enter key from TEKLEAD scanner
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            // Skip if typing in input fields or if modal is open
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || document.querySelector('.modal.show')) {
                return;
            }
            
            if (scannedData.length >= 6 && !isProcessing) {
                console.log('⏎ TEKLEAD Scanner Enter detected, processing:', scannedData);
                
                // AUTO-POPULATE the scanner input field
                if (scannerInput) {
                    scannerInput.value = scannedData;
                    scannerInput.focus();
                    
                    // Visual feedback
                    scannerInput.style.background = '#d4edda';
                    scannerInput.style.borderColor = '#28a745';
                    
                    console.log('🎯 AUTO-POPULATED scannerInput with:', scannedData);
                    
                    // Trigger the confirmation modal
                    processScannedBarcode(scannedData);
                }
                
                // Reset scanned data
                scannedData = '';
                clearTimeout(scanTimeout);
            }
        }
    });
    
    // ===== SCANNER INPUT BACKUP METHOD =====
    if (scannerInput) {
        // Ensure scanner input is ready
        scannerInput.focus();
        console.log('🎯 Scanner input field ready for TEKLEAD scanner');
        
        scannerInput.addEventListener('input', function(e) {
            const currentValue = e.target.value.trim();
            
            console.log('📥 Direct input to scanner field:', currentValue);
            
            // Clear previous timeout
            clearTimeout(scanTimeout);
            
            // Visual feedback
            if (currentValue.length > 0) {
                e.target.style.background = '#fff3cd';
                e.target.style.borderColor = '#ffc107';
            }
            
            // Process when complete barcode detected
            if (currentValue.length >= 6 && !isProcessing) {
                console.log('🔥 Complete barcode in scanner input:', currentValue);
                scanTimeout = setTimeout(() => {
                    processScannedBarcode(currentValue);
                }, 300);
            }
        });
        
        // Handle Enter key in scanner input
        scannerInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                const barcode = e.target.value.trim();
                
                console.log('⏎ Enter in scanner input:', barcode);
                
                if (barcode.length >= 6 && !isProcessing) {
                    clearTimeout(scanTimeout);
                    processScannedBarcode(barcode);
                }
            }
        });
        
        // Maintain focus for scanner
        setInterval(() => {
            if (!isProcessing && !document.querySelector('.modal.show') && document.activeElement !== scannerInput) {
                scannerInput.focus();
            }
        }, 2000);
        
        // Handle modal close
        document.getElementById('confirmationModal').addEventListener('hidden.bs.modal', function() {
            if (localStorage.getItem('pendingBarcodeScan')) {
                cancelScan();
            }
        });
        
        // Clear localStorage on successful submission
        <?php if ($attendance_message && $attendance_type === 'success'): ?>
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
        
        console.log('✅ TEKLEAD Scanner with auto-population ready!');
    }
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
