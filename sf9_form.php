<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get student ID from URL
$student_id = $_GET['student_id'] ?? null;
if (!$student_id) {
    redirect('view_grades.php');
}

// Set page variables for layout
$current_page = 'sf9_form';
$page_title = 'SF9 Report Card';

// Get student information
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        redirect('view_grades.php');
    }
    
    // Get first semester subjects (filtered by grade level and semester)
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(sg.quarter_1, '') as quarter_1,
               COALESCE(sg.quarter_2, '') as quarter_2,
               COALESCE(sg.quarter_3, '') as quarter_3,
               COALESCE(sg.quarter_4, '') as quarter_4,
               COALESCE(sg.final_grade, '') as final_grade,
               COALESCE(sg.status, '') as remarks
        FROM subjects s
        LEFT JOIN student_grades sg ON s.id = sg.subject_id AND sg.student_id = ?
        WHERE s.grade_level = ? AND s.is_first_sem = 1
        ORDER BY s.subject_type, s.subject_name
    ");
    $stmt->execute([$student_id, $student['grade_level']]);
    $first_sem_subjects = $stmt->fetchAll();
    
    // Get second semester subjects (filtered by grade level and semester)
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(sg.quarter_1, '') as quarter_1,
               COALESCE(sg.quarter_2, '') as quarter_2,
               COALESCE(sg.quarter_3, '') as quarter_3,
               COALESCE(sg.quarter_4, '') as quarter_4,
               COALESCE(sg.final_grade, '') as final_grade,
               COALESCE(sg.status, '') as remarks
        FROM subjects s
        LEFT JOIN student_grades sg ON s.id = sg.subject_id AND sg.student_id = ?
        WHERE s.grade_level = ? AND s.is_second_sem = 1
        ORDER BY s.subject_type, s.subject_name
    ");
    $stmt->execute([$student_id, $student['grade_level']]);
    $second_sem_subjects = $stmt->fetchAll();
    
    // Organize first semester subjects by type
    $first_sem_core = [];
    $first_sem_applied = [];
    $first_sem_specialized = [];
    
    foreach ($first_sem_subjects as $subject) {
        $subject_data = [
            'name' => $subject['subject_name'],
            'quarter_1' => $subject['quarter_1'],
            'quarter_2' => $subject['quarter_2'],
            'quarter_3' => $subject['quarter_3'],
            'quarter_4' => $subject['quarter_4'],
            'final_grade' => $subject['final_grade'],
            'remarks' => $subject['remarks']
        ];
        
        switch ($subject['subject_type']) {
            case 'CORE':
                $first_sem_core[] = $subject_data;
                break;
            case 'APPLIED':
                $first_sem_applied[] = $subject_data;
                break;
            case 'SPECIALIZED':
                $first_sem_specialized[] = $subject_data;
                break;
        }
    }
    
    // Organize second semester subjects by type
    $second_sem_core = [];
    $second_sem_applied = [];
    $second_sem_specialized = [];
    
    foreach ($second_sem_subjects as $subject) {
        $subject_data = [
            'name' => $subject['subject_name'],
            'quarter_1' => $subject['quarter_1'],
            'quarter_2' => $subject['quarter_2'],
            'quarter_3' => $subject['quarter_3'],
            'quarter_4' => $subject['quarter_4'],
            'final_grade' => $subject['final_grade'],
            'remarks' => $subject['remarks']
        ];
        
        switch ($subject['subject_type']) {
            case 'CORE':
                $second_sem_core[] = $subject_data;
                break;
            case 'APPLIED':
                $second_sem_applied[] = $subject_data;
                break;
            case 'SPECIALIZED':
                $second_sem_specialized[] = $subject_data;
                break;
        }
    }
    
    // Get or create SF9 form data
    $stmt = $pdo->prepare("SELECT * FROM sf9_forms WHERE student_id = ? AND school_year = ?");
    $stmt->execute([$student_id, $student['school_year']]);
    $sf9_data = $stmt->fetch();
    
    if (!$sf9_data) {
        // Create new SF9 form
        $stmt = $pdo->prepare("
            INSERT INTO sf9_forms (student_id, school_year, lrn, grade_level, section, track_strand)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student_id,
            $student['school_year'],
            $student['student_number'],
            $student['grade_level'],
            $student['section'],
            'Academic - Accountancy, Business and Management'
        ]);
        $sf9_form_id = $pdo->lastInsertId();
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM sf9_forms WHERE id = ?");
        $stmt->execute([$sf9_form_id]);
        $sf9_data = $stmt->fetch();
    }
    
    // ===== AUTOMATIC ATTENDANCE COUNTING FROM DAILY_ATTENDANCE TABLE =====
    // Get monthly attendance counts from daily_attendance table
    $monthly_attendance = [];
    
    // Define school year months with their corresponding calendar months
    $school_year_months = [
        'Sep' => ['month' => 9, 'year_offset' => 0],   // September (current year)
        'Oct' => ['month' => 10, 'year_offset' => 0],  // October
        'Nov' => ['month' => 11, 'year_offset' => 0],  // November
        'Dec' => ['month' => 12, 'year_offset' => 0],  // December
        'Jan' => ['month' => 1, 'year_offset' => 1],   // January (next year)
        'Feb' => ['month' => 2, 'year_offset' => 1],   // February
        'Mar' => ['month' => 3, 'year_offset' => 1],   // March
        'Apr' => ['month' => 4, 'year_offset' => 1],   // April
        'May' => ['month' => 5, 'year_offset' => 1],   // May
        'Jun' => ['month' => 6, 'year_offset' => 1],   // June
        'Jul' => ['month' => 7, 'year_offset' => 1]    // July
    ];
    
    // Extract year from school year (e.g., "2024-2025" -> 2024)
    $base_year = (int)substr($student['school_year'], 0, 4);
    
    foreach ($school_year_months as $month_abbr => $month_info) {
        $target_year = $base_year + $month_info['year_offset'];
        $target_month = $month_info['month'];
        
        try {
            // Count present days for this student in this specific month
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as present_count
                FROM daily_attendance 
                WHERE student_id = ? 
                AND YEAR(scan_datetime) = ? 
                AND MONTH(scan_datetime) = ?
                AND status IN ('present', 'late')
            ");
            $stmt->execute([$student_id, $target_year, $target_month]);
            $result = $stmt->fetch();
            
            $monthly_attendance[$month_abbr] = [
                'present' => $result['present_count'] ?? 0,
                'year' => $target_year,
                'month' => $target_month
            ];
            
        } catch (PDOException $e) {
            // If there's an error, set to 0
            $monthly_attendance[$month_abbr] = [
                'present' => 0,
                'year' => $target_year,
                'month' => $target_month
            ];
        }
    }
    
} catch (PDOException $e) {
    $error = 'Could not load student data: ' . $e->getMessage();
}

// Handle form submission
if ($_POST && isset($_POST['save_sf9'])) {
    try {
        // Handle empty values for age (convert empty string to NULL)
        $age = (!empty($_POST['age']) && is_numeric($_POST['age'])) ? (int)$_POST['age'] : null;
        
        // Update SF9 form data
        $stmt = $pdo->prepare("
            UPDATE sf9_forms SET 
                lrn = ?, age = ?, sex = ?, track_strand = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['lrn'],
            $age,
            $_POST['sex'],
            $_POST['track_strand'],
            $sf9_data['id']
        ]);
        
        // Save attendance data
        $months = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
        foreach ($months as $month) {
            if (isset($_POST['attendance'][$month])) {
                $attendance = $_POST['attendance'][$month];
                
                // Convert empty strings to 0 for integer fields
                $school_days = (!empty($attendance['school_days']) && is_numeric($attendance['school_days'])) ? (int)$attendance['school_days'] : 0;
                $present = (!empty($attendance['present']) && is_numeric($attendance['present'])) ? (int)$attendance['present'] : 0;
                $absent = (!empty($attendance['absent']) && is_numeric($attendance['absent'])) ? (int)$attendance['absent'] : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO sf9_attendance (sf9_form_id, month, school_days, days_present, days_absent)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    school_days = VALUES(school_days),
                    days_present = VALUES(days_present),
                    days_absent = VALUES(days_absent)
                ");
                $stmt->execute([
                    $sf9_data['id'],
                    $month,
                    $school_days,
                    $present,
                    $absent
                ]);
            }
        }
        
        // Save core values
        $core_values = ['Maka-Diyos', 'Makatao', 'Makakalikasan', 'Makabansa'];
        foreach ($core_values as $value) {
            if (isset($_POST['core_values'][$value])) {
                $quarters = $_POST['core_values'][$value];
                $stmt = $pdo->prepare("
                    INSERT INTO sf9_core_values (sf9_form_id, core_value, quarter_1, quarter_2, quarter_3, quarter_4)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quarter_1 = VALUES(quarter_1),
                    quarter_2 = VALUES(quarter_2),
                    quarter_3 = VALUES(quarter_3),
                    quarter_4 = VALUES(quarter_4)
                ");
                $stmt->execute([
                    $sf9_data['id'],
                    $value,
                    $quarters['q1'] ?? null,
                    $quarters['q2'] ?? null,
                    $quarters['q3'] ?? null,
                    $quarters['q4'] ?? null
                ]);
            }
        }
        
        $success = 'SF9 data saved successfully!';
        
    } catch (PDOException $e) {
        $error = 'Could not save SF9 data: ' . $e->getMessage();
    }
}

// Start output buffering for page content
ob_start();
?>

<style>
    @media print {
        .no-print { display: none !important; }
        .sidebar, .top-header { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        body { background: white !important; }
        .sf9-container { margin: 0 !important; max-width: none !important; }
    }
    
    .sf9-container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.2;
    }
    
    .sf9-header {
        text-align: center;
        margin-bottom: 5px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    
    .student-info-grid {
        margin-top: -5px;
    }
    
    .sf9-title {
        font-size: 16px;
        font-weight: bold;
        margin: 5px 0;
    }
    
    .student-info-section {
        margin-bottom: 20px;
    }
    
    .sf9-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    
    .sf9-table th,
    .sf9-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        text-align: center;
        vertical-align: middle;
        font-size: 10px;
    }
    
    .sf9-table th {
        background-color: #f0f0f0;
        font-weight: bold;
    }
    
    .section-title {
        font-weight: bold;
        background-color: #e0e0e0;
        padding: 4px;
        border: 1px solid #000;
        text-align: center;
        font-size: 11px;
    }
    
    .editable-field {
        border: none;
        background: transparent;
        width: 100%;
        text-align: center;
        padding: 2px;
        font-size: 10px;
    }
    
    .editable-field:focus {
        background-color: #ffffcc;
        outline: 1px solid #0066cc;
    }
    
    .core-values-section {
        margin: 20px 0;
    }
    
    .core-values-section table {
        font-size: 10px;
    }
    
    .core-values-section th,
    .core-values-section td {
        padding: 2px 4px;
    }
    
    .core-values-section {
        margin: 20px 0;
    }
    
    .print-buttons {
        text-align: center;
        margin: 20px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .btn {
        padding: 10px 20px;
        margin: 0 10px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
</style>

<?php if (isset($error)): ?>
    <div class="alert alert-danger no-print"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success no-print"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="print-buttons no-print">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print SF9
    </button>
    <button onclick="saveSF9()" class="btn btn-success">
        <i class="fas fa-save"></i> Save & Print
    </button>
    <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Student
    </a>
</div>

<form id="sf9Form" method="POST">
<div class="sf9-container">
    <!-- Two-Column Layout: Student Info (Left) | Header (Right) -->
    <div style="display: grid; grid-template-columns: 40% 60%; gap: 15px; margin-bottom: 15px; align-items: start;">
        
        <!-- Left Column: Student Information -->
        <div style="padding-top: 10px;">
            <div>
                <table style="width: 100%; font-size: 12px;">
                    <tr>
                        <td style="width: 120px;"><strong>Name:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo strtoupper(htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name'])); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>LRN:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <input type="text" name="lrn" value="<?php echo htmlspecialchars($sf9_data['lrn'] ?? $student['student_number']); ?>" 
                                   class="editable-field" style="letter-spacing: 8px; font-weight: bold; font-size: 11px;">
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>Grade:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <?php echo htmlspecialchars($student['grade_level']); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>School Year:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <?php echo htmlspecialchars($student['school_year']); ?>
                        </td>
                    </tr>
                      <tr>
                        <td style="width: 80px;"><strong>Section:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo htmlspecialchars($student['section']); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>Track/Strand:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <input type="text" name="track_strand" 
                                   value="<?php echo htmlspecialchars($sf9_data['track_strand'] ?? 'ABM'); ?>" 
                                   class="editable-field" style="width: 100%;">
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>Sex:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <select name="sex" class="editable-field">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($sf9_data['sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($sf9_data['sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>Age:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <input type="number" name="age" value="<?php echo htmlspecialchars($sf9_data['age'] ?? ''); ?>" 
                                   class="editable-field">
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Right Column: Header with Logo -->
        <div style="text-align: center; padding: 0;">
            <img src="img/sf9.png" alt="SF9 Header" style="width: 70%; max-width: 70%; height: auto; display: block; margin: 0 auto;">
        </div>
    </div>

    <!-- Learning Progress and Achievement -->
    <div class="section-title" style="text-align: center; margin-bottom: 20px;">REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</div>
    
    <!-- Semester Cards Container -->
    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
        <!-- First Semester Card -->
        <div style="flex: 1; border: 2px solid #000; padding: 10px; height: 500px; overflow: hidden;">
            <div class="section-title">First Semester</div>
            
            <table class="sf9-table" style="font-size: 11px;">
                <thead>
                    <tr>
                        <th style="width: 40%;">Subjects</th>
                        <th style="width: 15%;">Q1</th>
                        <th style="width: 15%;">Q2</th>
                        <th style="width: 15%;">Final</th>
                        <th style="width: 15%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Core Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">CORE SUBJECTS</td></tr>
                    <?php foreach ($first_sem_core as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for core subjects if needed -->
                    <?php for ($i = count($first_sem_core); $i < 8; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <!-- Applied Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">APPLIED SUBJECTS</td></tr>
                    <?php foreach ($first_sem_applied as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for applied subjects if needed -->
                    <?php for ($i = count($first_sem_applied); $i < 1; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <!-- Specialized Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">SPECIALIZED SUBJECTS</td></tr>
                    <?php foreach ($first_sem_specialized as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for specialized subjects if needed -->
                    <?php for ($i = count($first_sem_specialized); $i < 2; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <tr style="height: 20px;">
                        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px;">General Average</td>
                        <td><input type="number" step="0.01" class="editable-field" style="font-weight: bold;"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Second Semester Card -->
        <div style="flex: 1; border: 2px solid #000; padding: 10px; height: 500px; overflow: hidden;">
            <div class="section-title">Second Semester</div>
            
            <table class="sf9-table" style="font-size: 11px;">
                <thead>
                    <tr>
                        <th style="width: 40%;">Subjects</th>
                        <th style="width: 15%;">Q1</th>
                        <th style="width: 15%;">Q2</th>
                        <th style="width: 15%;">Final</th>
                        <th style="width: 15%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Core Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">CORE SUBJECTS</td></tr>
                    <?php foreach ($second_sem_core as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for core subjects if needed -->
                    <?php for ($i = count($second_sem_core); $i < 8; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <!-- Applied Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">APPLIED SUBJECTS</td></tr>
                    <?php foreach ($second_sem_applied as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for applied subjects if needed -->
                    <?php for ($i = count($second_sem_applied); $i < 1; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <!-- Specialized Subjects -->
                    <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">SPECIALIZED SUBJECTS</td></tr>
                    <?php foreach ($second_sem_specialized as $subject): ?>
                    <tr>
                        <td style="text-align: left; padding: 2px; font-size: 10px;"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                        <td style="padding: 1px;"><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                        <td style="padding: 1px; font-size: 9px;"><?php echo htmlspecialchars($subject['remarks']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for specialized subjects if needed -->
                    <?php for ($i = count($second_sem_specialized); $i < 2; $i++): ?>
                    <tr>
                        <td><input type="text" class="editable-field" style="text-align: left; font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="number" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                        <td><input type="text" class="editable-field" style="font-size: 9px; padding: 1px;"></td>
                    </tr>
                    <?php endfor; ?>
                    
                    <tr style="height: 20px;">
                        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px;">General Average</td>
                        <td><input type="number" step="0.01" class="editable-field" style="font-weight: bold;"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Report on Attendance Section -->
    <div style="border: 2px solid #000; padding: 15px; margin-top: 20px;">
        <div class="section-title">REPORT ON ATTENDANCE</div>
        
        <table class="sf9-table" style="font-size: 11px;">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 80px;">Month</th>
                    <th rowspan="2" style="width: 80px;">No. of School Days</th>
                    <th rowspan="2" style="width: 80px;">No. of Days Present</th>
                    <th rowspan="2" style="width: 80px;">No. of Days Absent</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $months = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
                $total_school_days = 0;
                $total_present = 0;
                $total_absent = 0;
                
                foreach ($months as $month): 
                    // Get existing attendance data from sf9_attendance table
                    $attendance_data = [];
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM sf9_attendance WHERE sf9_form_id = ? AND month = ?");
                        $stmt->execute([$sf9_data['id'], $month]);
                        $attendance_data = $stmt->fetch() ?: [];
                    } catch (Exception $e) {
                        $attendance_data = [];
                    }
                    
                    // Get automatic count from daily_attendance table
                    $auto_present = $monthly_attendance[$month]['present'] ?? 0;
                    
                    // Use manual data if exists, otherwise use auto-calculated
                    $school_days = $attendance_data['school_days'] ?? 0;
                    $present = $attendance_data['days_present'] ?? $auto_present;
                    $absent = $attendance_data['days_absent'] ?? 0;
                    
                    $total_school_days += $school_days;
                    $total_present += $present;
                    $total_absent += $absent;
                ?>
                <tr>
                    <td style="font-weight: bold; text-align: center;"><?php echo $month; ?></td>
                    <td style="text-align: center;">
                        <input type="number" 
                               name="attendance[<?php echo $month; ?>][school_days]" 
                               value="<?php echo $school_days; ?>" 
                               class="editable-field" 
                               style="width: 60px; text-align: center;" 
                               min="0" max="31">
                    </td>
                    <td style="text-align: center; background-color: #e8f5e8;">
                        <input type="number" 
                               name="attendance[<?php echo $month; ?>][present]" 
                               value="<?php echo $present; ?>" 
                               class="editable-field auto-calculated" 
                               style="width: 60px; text-align: center; background-color: #e8f5e8;" 
                               min="0" max="31"
                               title="Auto-calculated from scanner data: <?php echo $auto_present; ?> days">
                        <small style="display: block; color: #666; font-size: 8px;">
                            Auto: <?php echo $auto_present; ?>
                        </small>
                    </td>
                    <td style="text-align: center;">
                        <input type="number" 
                               name="attendance[<?php echo $month; ?>][absent]" 
                               value="<?php echo $absent; ?>" 
                               class="editable-field" 
                               style="width: 60px; text-align: center;" 
                               min="0" max="31">
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Totals Row -->
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td style="text-align: center;">TOTAL</td>
                    <td style="text-align: center;" id="totalSchoolDays"><?php echo $total_school_days; ?></td>
                    <td style="text-align: center; background-color: #d4f4d4;" id="totalPresent"><?php echo $total_present; ?></td>
                    <td style="text-align: center;" id="totalAbsent"><?php echo $total_absent; ?></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 10px; font-size: 10px; color: #666;">
            <strong>Note:</strong> 
            <span style="background-color: #e8f5e8; padding: 2px 4px; border-radius: 3px;">Green highlighted fields</span> 
            are automatically calculated from barcode scanner attendance data. You can manually override these values if needed.
        </div>
    </div>

    <!-- Core Values Section (Landscape Layout) -->
    <div class="core-values-section">
        <!-- Core Values -->
        <div style="border: 2px solid #000; padding: 15px;">
            <div class="section-title">REPORT ON LEARNER'S OBSERVED VALUES</div>
            
            <table class="sf9-table">
                <thead>
                    <tr>
                        <th rowspan="2">Core Values</th>
                        <th rowspan="2">Behavior Statements</th>
                        <th colspan="4">Quarter</th>
                    </tr>
                    <tr>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td rowspan="2"><strong>1. Maka-Diyos</strong></td>
                        <td style="text-align: left; font-size: 9px;">Expresses one's spiritual beliefs while respecting the spiritual beliefs of others</td>
                        <td rowspan="2">
                            <select name="core_values[Maka-Diyos][q1]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Maka-Diyos][q2]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Maka-Diyos][q3]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Maka-Diyos][q4]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-size: 9px;">Shows adherence to ethical principles by upholding truth in all undertakings</td>
                    </tr>
                    
                    <tr>
                        <td rowspan="2"><strong>2. Makatao</strong></td>
                        <td style="text-align: left; font-size: 9px;">Is sensitive to individual, social and cultural differences</td>
                        <td rowspan="2">
                            <select name="core_values[Makatao][q1]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makatao][q2]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makatao][q3]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makatao][q4]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-size: 9px;">Demonstrates contributions towards solidarity</td>
                    </tr>
                    
                    <tr>
                        <td rowspan="2"><strong>3. Makakalikasan</strong></td>
                        <td style="text-align: left; font-size: 9px;">Cares for the environment and utilizes resources wisely, judiciously, and economically</td>
                        <td rowspan="2">
                            <select name="core_values[Makakalikasan][q1]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makakalikasan][q2]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makakalikasan][q3]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makakalikasan][q4]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-size: 9px;"></td>
                    </tr>
                    
                    <tr>
                        <td rowspan="2"><strong>4. Makabansa</strong></td>
                        <td style="text-align: left; font-size: 9px;">Demonstrates pride in being a Filipino; exercises the rights and responsibilities of a Filipino citizen</td>
                        <td rowspan="2">
                            <select name="core_values[Makabansa][q1]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makabansa][q2]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makabansa][q3]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                        <td rowspan="2">
                            <select name="core_values[Makabansa][q4]" class="editable-field">
                                <option value="">-</option>
                                <option value="AO">AO</option>
                                <option value="SO">SO</option>
                                <option value="RO">RO</option>
                                <option value="NO">NO</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-size: 9px;">Demonstrates appropriate behavior in carrying out activities in the school, community, and country</td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Legend Tables in Landscape Layout -->
            <div style="display: flex; gap: 30px; margin-top: 20px;">
                <div style="flex: 1; font-size: 10px;">
                    <div><strong>Observed Values</strong></div>
                    <table style="width: 100%; margin-top: 5px; border: 1px solid #000;">
                        <tr><td style="border: 1px solid #000; padding: 3px;"><strong>Marking</strong></td><td style="border: 1px solid #000; padding: 3px;"><strong>Non-numerical Rating</strong></td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">AO</td><td style="border: 1px solid #000; padding: 3px;">Always Observed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">SO</td><td style="border: 1px solid #000; padding: 3px;">Sometimes Observed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">RO</td><td style="border: 1px solid #000; padding: 3px;">Rarely Observed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">NO</td><td style="border: 1px solid #000; padding: 3px;">Not Observed</td></tr>
                    </table>
                </div>
                
                <div style="flex: 1; font-size: 10px;">
                    <div><strong>Learner Progress and Achievement</strong></div>
                    <table style="width: 100%; margin-top: 5px; border: 1px solid #000;">
                        <tr><td style="border: 1px solid #000; padding: 3px;"><strong>Descriptors</strong></td><td style="border: 1px solid #000; padding: 3px;"><strong>Grading Scale</strong></td><td style="border: 1px solid #000; padding: 3px;"><strong>Remarks</strong></td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">Outstanding</td><td style="border: 1px solid #000; padding: 3px;">90-100</td><td style="border: 1px solid #000; padding: 3px;">Passed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">Very Satisfactory</td><td style="border: 1px solid #000; padding: 3px;">85-89</td><td style="border: 1px solid #000; padding: 3px;">Passed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">Satisfactory</td><td style="border: 1px solid #000; padding: 3px;">80-84</td><td style="border: 1px solid #000; padding: 3px;">Passed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">Fairly Satisfactory</td><td style="border: 1px solid #000; padding: 3px;">75-79</td><td style="border: 1px solid #000; padding: 3px;">Passed</td></tr>
                        <tr><td style="border: 1px solid #000; padding: 3px;">Did Not Meet Expectations</td><td style="border: 1px solid #000; padding: 3px;">Below 75</td><td style="border: 1px solid #000; padding: 3px;">Failed</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Signatures Section -->
    <div style="display: flex; gap: 15px; margin-top: 20px;">
        <div style="flex: 1; border: 1px solid #000; padding: 10px; font-size: 11px;">
            <div style="text-align: center; font-weight: bold; margin-bottom: 15px;">PARENT/GUARDIAN'S SIGNATURE</div>
            <div style="margin: 10px 0;">
                <div><strong>1st Quarter:</strong></div>
                <div style="border-bottom: 1px solid #000; height: 25px; margin: 5px 0;"></div>
            </div>
            <div style="margin: 10px 0;">
                <div><strong>2nd Quarter:</strong></div>
                <div style="border-bottom: 1px solid #000; height: 25px; margin: 5px 0;"></div>
            </div>
            <div style="margin: 10px 0;">
                <div><strong>3rd Quarter:</strong></div>
                <div style="border-bottom: 1px solid #000; height: 25px; margin: 5px 0;"></div>
            </div>
            <div style="margin: 10px 0;">
                <div><strong>4th Quarter:</strong></div>
                <div style="border-bottom: 1px solid #000; height: 25px; margin: 5px 0;"></div>
            </div>
        </div>

        <div style="flex: 1; border: 1px solid #000; padding: 10px; font-size: 11px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="border-bottom: 1px solid #000; width: 200px; margin: 0 auto 5px; height: 25px;"></div>
                <div><strong>Class Adviser</strong></div>
            </div>
            <div style="text-align: center; margin-top: 50px;">
                <div style="border-bottom: 1px solid #000; width: 200px; margin: 0 auto 5px; height: 25px;"></div>
                <div><strong>Principal</strong></div>
            </div>
        </div>
    </div>

    <input type="hidden" name="save_sf9" value="1">
</div>
</form>

<div class="print-buttons no-print" style="margin-top: 30px;">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print SF9
    </button>
    <button onclick="saveSF9()" class="btn btn-success">
        <i class="fas fa-save"></i> Save & Print
    </button>
    <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Student
    </a>
</div>

<script>
function saveSF9() {
    // Submit the form to save data
    document.getElementById('sf9Form').submit();
}

// Auto-calculate totals for attendance
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for attendance calculations
    const attendanceInputs = document.querySelectorAll('input[name*="attendance"]');
    
    function calculateTotals() {
        let totalSchoolDays = 0;
        let totalPresent = 0;
        let totalAbsent = 0;
        
        const months = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
        
        months.forEach(month => {
            const schoolDays = parseInt(document.querySelector(`input[name="attendance[${month}][school_days]"]`)?.value) || 0;
            const present = parseInt(document.querySelector(`input[name="attendance[${month}][present]"]`)?.value) || 0;
            const absent = parseInt(document.querySelector(`input[name="attendance[${month}][absent]"]`)?.value) || 0;
            
            totalSchoolDays += schoolDays;
            totalPresent += present;
            totalAbsent += absent;
        });
        
        // Update total displays
        document.getElementById('totalSchoolDays').textContent = totalSchoolDays;
        document.getElementById('totalPresent').textContent = totalPresent;
        document.getElementById('totalAbsent').textContent = totalAbsent;
    }
    
    // Add event listeners to all attendance inputs
    attendanceInputs.forEach(input => {
        input.addEventListener('change', calculateTotals);
        input.addEventListener('input', calculateTotals);
    });
    
    // Calculate totals on page load
    calculateTotals();
    
    console.log('✅ SF9 Attendance auto-calculation ready!');
});
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
