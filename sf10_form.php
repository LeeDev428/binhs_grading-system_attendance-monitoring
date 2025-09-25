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
$current_page = 'sf10_form';
$page_title = 'SF10 - Senior High School Student Permanent Record';

// Get student information
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        redirect('view_grades.php');
    }
    
    // Get student grades separated by semester
    // 1st Semester Subjects
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
    
    // 2nd Semester Subjects
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
    
    // Get or create SF10 form data
    $stmt = $pdo->prepare("SELECT * FROM sf10_forms WHERE student_id = ? AND school_year = ?");
    $stmt->execute([$student_id, $student['school_year']]);
    $sf10_data = $stmt->fetch();
    
    if (!$sf10_data) {
        // Create new SF10 form
        $stmt = $pdo->prepare("
            INSERT INTO sf10_forms (student_id, school_year, lrn, grade_level, section, track_strand)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student_id,
            $student['school_year'],
            $student['student_number'],
            $student['grade_level'],
            $student['section'],
            'Remedial Classes'
        ]);
        $sf10_form_id = $pdo->lastInsertId();
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM sf10_forms WHERE id = ?");
        $stmt->execute([$sf10_form_id]);
        $sf10_data = $stmt->fetch();
    }
    
    // Get existing remedial grades
    $remedial_grades = [];
    if ($sf10_data) {
        $stmt = $pdo->prepare("
            SELECT * FROM sf10_remedial_grades 
            WHERE sf10_form_id = ?
        ");
        $stmt->execute([$sf10_data['id']]);
        $grades_data = $stmt->fetchAll();
        
        foreach ($grades_data as $grade) {
            $remedial_grades[$grade['subject_id']] = $grade;
        }
    }
    
} catch (PDOException $e) {
    $error = 'Could not load student data: ' . $e->getMessage();
}

// Handle form submission
if ($_POST && isset($_POST['save_sf10'])) {
    try {
        // Handle empty values for age (convert empty string to NULL)
        $age = (!empty($_POST['age']) && is_numeric($_POST['age'])) ? (int)$_POST['age'] : null;
        
        // Update SF10 form data
        $stmt = $pdo->prepare("
            UPDATE sf10_forms SET 
                lrn = ?, age = ?, sex = ?, track_strand = ?, 
                remedial_period = ?, remedial_year = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['lrn'],
            $age,
            $_POST['sex'],
            $_POST['track_strand'],
            $_POST['remedial_period'] ?? '',
            $_POST['remedial_year'] ?? '',
            $sf10_data['id']
        ]);
        
        // Save remedial grades
        foreach ($subjects as $subject) {
            $remedial_grade_key = 'remedial_grade_' . $subject['id'];
            $action_taken_key = 'action_taken_' . $subject['id'];
            
            if (isset($_POST[$remedial_grade_key]) && !empty($_POST[$remedial_grade_key])) {
                $remedial_grade = floatval($_POST[$remedial_grade_key]);
                $action_taken = $_POST[$action_taken_key] ?? null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO sf10_remedial_grades (sf10_form_id, subject_id, original_grade, remedial_grade, action_taken)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    original_grade = VALUES(original_grade),
                    remedial_grade = VALUES(remedial_grade),
                    action_taken = VALUES(action_taken),
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    $sf10_data['id'],
                    $subject['id'],
                    $subject['final_grade'] ?: null,
                    $remedial_grade,
                    $action_taken
                ]);
            }
        }
        
        $success = 'SF10 data saved successfully!';
        
    } catch (PDOException $e) {
        $error = 'Could not save SF10 data: ' . $e->getMessage();
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
        .sf10-container { margin: 0 !important; max-width: none !important; }
    }
    
    .sf10-container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.2;
    }
    
    .sf10-header {
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    
    .sf10-title {
        font-size: 16px;
        font-weight: bold;
        margin: 5px 0;
    }
    
    .student-info-section {
        margin-bottom: 20px;
    }
    
    .sf10-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    
    .sf10-table th,
    .sf10-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        text-align: center;
        vertical-align: middle;
        font-size: 10px;
    }
    
    .sf10-table th {
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
    
    .remedial-section {
        border: 2px solid #000;
        margin-bottom: 20px;
        padding: 15px;
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
        <i class="fas fa-print"></i> Print SF10
    </button>
    <button onclick="saveSF10()" class="btn btn-success">
        <i class="fas fa-save"></i> Save & Print
    </button>
    <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Student
    </a>
</div>

<form id="sf10Form" method="POST">
<div class="sf10-container">
    <!-- Header -->
    <div class="sf10-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="text-align: left;">
                <strong>DepEd SF10</strong>
            </div>
            <div style="text-align: center; flex: 1;">
                <div><strong>Republic of the Philippines</strong></div>
                <div><strong>DEPARTMENT OF EDUCATION</strong></div>
                <div><strong>IV-A CALABARZON</strong></div>
                <div><strong>DIVISION OF BIÑAN CITY</strong></div>
                <br>
                <div class="sf10-title">BIÑAN INTEGRATED NATIONAL HIGH SCHOOL</div>
                <div><strong>Senior High School</strong></div>
                <div>Brgy. Sto. Domingo, City of Biñan, Laguna</div>
                <div>Tel. No.: (049) 511-4425</div>
                <br>
                <div><strong>SENIOR HIGH SCHOOL STUDENT PERMANENT RECORD</strong></div>
                <div><strong>(Learner's Record for Remedial Classes)</strong></div>
            </div>
            <div style="text-align: right;">
                <div style="width: 80px; height: 80px; border: 1px solid #000; display: inline-block;">
                    <!-- School Logo Space -->
                </div>
            </div>
        </div>
    </div>

    <!-- Student Information -->
    <div class="student-info-grid">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Basic Student Info -->
            <div>
                <table style="width: 100%; font-size: 12px;">
                    <tr>
                        <td style="width: 120px;"><strong>LAST NAME:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo strtoupper(htmlspecialchars($student['last_name'])); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>FIRST NAME:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo strtoupper(htmlspecialchars($student['first_name'])); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>MIDDLE NAME:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo strtoupper(htmlspecialchars($student['middle_name'])); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>LRN:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <input type="text" name="lrn" value="<?php echo htmlspecialchars($sf10_data['lrn'] ?? $student['student_number']); ?>" 
                                   class="editable-field" style="letter-spacing: 8px; font-weight: bold; font-size: 11px;">
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Additional Info -->
            <div>
                <table style="width: 100%; font-size: 12px;">
                    <tr>
                        <td style="width: 100px;"><strong>SEX:</strong></td>
                        <td style="border-bottom: 1px solid #000;">
                            <select name="sex" class="editable-field">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($sf10_data['sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($sf10_data['sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>GRADE LEVEL:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo htmlspecialchars($student['grade_level']); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>SECTION:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo htmlspecialchars($student['section']); ?>
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height: 8px;"></td></tr>
                    <tr>
                        <td><strong>SY:</strong></td>
                        <td style="border-bottom: 1px solid #000; padding: 5px;">
                            <?php echo htmlspecialchars($student['school_year']); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Remedial Information -->
        <div style="margin-bottom: 20px;">
            <table style="width: 100%; font-size: 12px;">
                <tr>
                    <td style="width: 150px;"><strong>REMEDIAL PERIOD:</strong></td>
                    <td style="border-bottom: 1px solid #000; width: 200px;">
                        <input type="text" name="remedial_period" 
                               value="<?php echo htmlspecialchars($sf10_data['remedial_period'] ?? ''); ?>" 
                               class="editable-field" placeholder="e.g., Summer 2024">
                    </td>
                    <td style="width: 50px;"></td>
                    <td style="width: 100px;"><strong>SCHOOL:</strong></td>
                    <td style="border-bottom: 1px solid #000;">
                        BIÑAN INTEGRATED NATIONAL HIGH SCHOOL
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 1ST SEMESTER - SCHOLASTIC RECORD -->
    <div class="remedial-section">
        <div class="section-title">SCHOLASTIC RECORD - SEM 1ST - SY: 2023-2024</div>
        
        <table class="sf10-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 40%;">SUBJECTS</th>
                    <th colspan="2">Quarter</th>
                    <th rowspan="2" style="width: 15%;">SEM FINAL GRADE</th>
                    <th rowspan="2" style="width: 15%;">ACTION TAKEN</th>
                </tr>
                <tr>
                    <th style="width: 15%;">1ST</th>
                    <th style="width: 15%;">2ND</th>
                </tr>
            </thead>
            <tbody>
                <!-- Core Subjects -->
                <?php 
                $first_sem_core = array_filter($first_sem_subjects, function($s) { return $s['subject_type'] == 'CORE'; });
                if (!empty($first_sem_core)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">CORE</td></tr>
                <?php foreach ($first_sem_core as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_1st_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Applied Subjects -->
                <?php 
                $first_sem_applied = array_filter($first_sem_subjects, function($s) { return $s['subject_type'] == 'APPLIED'; });
                if (!empty($first_sem_applied)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">APPLIED</td></tr>
                <?php foreach ($first_sem_applied as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_1st_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Specialized Subjects -->
                <?php 
                $first_sem_specialized = array_filter($first_sem_subjects, function($s) { return $s['subject_type'] == 'SPECIALIZED'; });
                if (!empty($first_sem_specialized)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">SPECIALIZED</td></tr>
                <?php foreach ($first_sem_specialized as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_1']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_2']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_1st_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- General Average Section for 1st Semester -->
        <div style="margin-top: 20px;">
            <table style="width: 100%; font-size: 12px;">
                <tr>
                    <td style="width: 300px;"><strong>General Ave. for the Semester:</strong></td>
                    <td style="width: 100px; border-bottom: 1px solid #000; text-align: center;">
                        <input type="number" class="editable-field" step="0.01" style="font-weight: bold;" name="general_avg_1st">
                    </td>
                    <td style="width: 50px;"></td>
                    <td><strong>PASSED</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 1ST SEMESTER - REMEDIAL CLASSES -->
    <div class="remedial-section">
        <div class="section-title">REMEDIAL CLASSES - SEM 1ST</div>
        
        <table class="sf10-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th style="width: 35%;">SUBJECTS</th>
                    <th style="width: 16%;">SEM FINAL GRADE</th>
                    <th style="width: 16%;">REMEDIAL CLASS MARK</th>
                    <th style="width: 16%;">RECOMPUTED FINAL GRADE</th>
                    <th style="width: 17%;">ACTION TAKEN</th>
                </tr>
            </thead>
            <tbody>
                <!-- Empty rows for remedial subjects -->
                <?php for ($i = 0; $i < 8; $i++): ?>
                <tr>
                    <td><input type="text" class="editable-field" style="text-align: left;" name="remedial_1st_subject_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_1st_sem_grade_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_1st_class_mark_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_1st_final_<?php echo $i; ?>"></td>
                    <td>
                        <select class="editable-field" name="remedial_1st_action_<?php echo $i; ?>">
                            <option value="">-</option>
                            <option value="PASSED">PASSED</option>
                            <option value="FAILED">FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- 2ND SEMESTER - SCHOLASTIC RECORD -->
    <div class="remedial-section">
        <div class="section-title">SCHOLASTIC RECORD - SEM 2ND - SY: 2023-2024</div>
        
        <table class="sf10-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 40%;">SUBJECTS</th>
                    <th colspan="2">Quarter</th>
                    <th rowspan="2" style="width: 15%;">SEM FINAL GRADE</th>
                    <th rowspan="2" style="width: 15%;">ACTION TAKEN</th>
                </tr>
                <tr>
                    <th style="width: 15%;">1ST</th>
                    <th style="width: 15%;">2ND</th>
                </tr>
            </thead>
            <tbody>
                <!-- Core Subjects -->
                <?php 
                $second_sem_core = array_filter($second_sem_subjects, function($s) { return $s['subject_type'] == 'CORE'; });
                if (!empty($second_sem_core)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">CORE</td></tr>
                <?php foreach ($second_sem_core as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_2nd_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Applied Subjects -->
                <?php 
                $second_sem_applied = array_filter($second_sem_subjects, function($s) { return $s['subject_type'] == 'APPLIED'; });
                if (!empty($second_sem_applied)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">APPLIED</td></tr>
                <?php foreach ($second_sem_applied as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_2nd_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Specialized Subjects -->
                <?php 
                $second_sem_specialized = array_filter($second_sem_subjects, function($s) { return $s['subject_type'] == 'SPECIALIZED'; });
                if (!empty($second_sem_specialized)): ?>
                <tr><td colspan="5" class="section-title" style="font-size: 10px; padding: 3px;">SPECIALIZED</td></tr>
                <?php foreach ($second_sem_specialized as $subject): ?>
                <tr>
                    <td style="text-align: left; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_3']); ?></td>
                    <td><?php echo htmlspecialchars($subject['quarter_4']); ?></td>
                    <td><?php echo htmlspecialchars($subject['final_grade']); ?></td>
                    <td>
                        <select class="editable-field" name="action_taken_2nd_<?php echo $subject['id']; ?>">
                            <option value="">-</option>
                            <option value="PASSED" <?php echo ($subject['remarks'] == 'PASSED') ? 'selected' : ''; ?>>PASSED</option>
                            <option value="FAILED" <?php echo ($subject['remarks'] == 'FAILED') ? 'selected' : ''; ?>>FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- General Average Section for 2nd Semester -->
        <div style="margin-top: 20px;">
            <table style="width: 100%; font-size: 12px;">
                <tr>
                    <td style="width: 300px;"><strong>General Ave. for the Semester:</strong></td>
                    <td style="width: 100px; border-bottom: 1px solid #000; text-align: center;">
                        <input type="number" class="editable-field" step="0.01" style="font-weight: bold;" name="general_avg_2nd">
                    </td>
                    <td style="width: 50px;"></td>
                    <td><strong>PASSED</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 2ND SEMESTER - REMEDIAL CLASSES -->
    <div class="remedial-section">
        <div class="section-title">REMEDIAL CLASSES - SEM 2ND</div>
        
        <table class="sf10-table" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th style="width: 35%;">SUBJECTS</th>
                    <th style="width: 16%;">SEM FINAL GRADE</th>
                    <th style="width: 16%;">REMEDIAL CLASS MARK</th>
                    <th style="width: 16%;">RECOMPUTED FINAL GRADE</th>
                    <th style="width: 17%;">ACTION TAKEN</th>
                </tr>
            </thead>
            <tbody>
                <!-- Empty rows for remedial subjects -->
                <?php for ($i = 0; $i < 8; $i++): ?>
                <tr>
                    <td><input type="text" class="editable-field" style="text-align: left;" name="remedial_2nd_subject_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_2nd_sem_grade_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_2nd_class_mark_<?php echo $i; ?>"></td>
                    <td><input type="number" class="editable-field" step="0.01" min="0" max="100" name="remedial_2nd_final_<?php echo $i; ?>"></td>
                    <td>
                        <select class="editable-field" name="remedial_2nd_action_<?php echo $i; ?>">
                            <option value="">-</option>
                            <option value="PASSED">PASSED</option>
                            <option value="FAILED">FAILED</option>
                        </select>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures Section -->
    <div style="display: flex; gap: 30px; margin-top: 30px;">
        <div style="flex: 1; text-align: center;">
            <div style="margin-bottom: 50px;">
                <div style="border-bottom: 1px solid #000; width: 250px; margin: 0 auto 5px; height: 25px;"></div>
                <div><strong>Certified True and Correct:</strong></div>
                <div style="margin-top: 20px;">
                    <div style="border-bottom: 1px solid #000; width: 250px; margin: 0 auto 5px; height: 25px;"></div>
                    <div><strong>Signature of Adviser over Printed Name</strong></div>
                </div>
            </div>
        </div>

        <div style="flex: 1; text-align: center;">
            <div>
                <div style="border-bottom: 1px solid #000; width: 250px; margin: 0 auto 5px; height: 25px;"></div>
                <div><strong>Date Checked (MM/DD/YYYY):</strong></div>
                <div style="margin-top: 40px;">
                    <div style="border-bottom: 1px solid #000; width: 250px; margin: 0 auto 5px; height: 25px;"></div>
                    <div><strong>Signature of Authorized Person per School Division Superintendent's Designation</strong></div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="save_sf10" value="1">
</div>
</form>

<div class="print-buttons no-print" style="margin-top: 30px;">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print SF10
    </button>
    <button onclick="saveSF10()" class="btn btn-success">
        <i class="fas fa-save"></i> Save & Print
    </button>
    <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Student
    </a>
</div>

<script>
function saveSF10() {
    // Submit the form to save data
    document.getElementById('sf10Form').submit();
}

// Auto-calculate totals
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for grade calculations
    const gradeInputs = document.querySelectorAll('input[type="number"]');
    gradeInputs.forEach(input => {
        input.addEventListener('change', function() {
            // You can add calculation logic here if needed
        });
    });
});
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
