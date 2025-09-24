<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'student_grades';
$page_title = 'Student Grades Input';

$error = '';
$success = '';

// Check if we're editing an existing student
$edit_student_id = $_GET['edit'] ?? null;
$edit_student = null;
$existing_grades = [];

if ($edit_student_id) {
    try {
        // Get student data
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$edit_student_id]);
        $edit_student = $stmt->fetch();
        
        if ($edit_student) {
            // Get existing grades
            $stmt = $pdo->prepare("
                SELECT sg.*, s.subject_name, s.subject_type 
                FROM student_grades sg 
                JOIN subjects s ON sg.subject_id = s.id 
                WHERE sg.student_id = ?
            ");
            $stmt->execute([$edit_student_id]);
            $grades = $stmt->fetchAll();
            
            foreach ($grades as $grade) {
                $existing_grades[$grade['subject_id']] = $grade;
            }
        }
    } catch (PDOException $e) {
        $error = 'Could not load student data for editing.';
    }
}

// Get subjects organized by semester and type
try {
    // Get first semester subjects
    $stmt = $pdo->query("SELECT * FROM subjects WHERE is_first_sem = 1 ORDER BY subject_type, subject_name");
    $first_sem_subjects = $stmt->fetchAll();
    
    // Get second semester subjects
    $stmt = $pdo->query("SELECT * FROM subjects WHERE is_second_sem = 1 ORDER BY subject_type, subject_name");
    $second_sem_subjects = $stmt->fetchAll();
    
    // Get all subjects for form processing
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_type, subject_name");
    $all_subjects = $stmt->fetchAll();
    
    // Organize first semester subjects by type
    $first_sem_by_type = [
        'CORE' => [],
        'APPLIED' => [],
        'SPECIALIZED' => []
    ];
    
    foreach ($first_sem_subjects as $subject) {
        $type = $subject['subject_type'];
        if (isset($first_sem_by_type[$type])) {
            $first_sem_by_type[$type][] = $subject;
        }
    }
    
    // Organize second semester subjects by type
    $second_sem_by_type = [
        'CORE' => [],
        'APPLIED' => [],
        'SPECIALIZED' => []
    ];
    
    foreach ($second_sem_subjects as $subject) {
        $type = $subject['subject_type'];
        if (isset($second_sem_by_type[$type])) {
            $second_sem_by_type[$type][] = $subject;
        }
    }
    
} catch (PDOException $e) {
    $first_sem_by_type = ['CORE' => [], 'APPLIED' => [], 'SPECIALIZED' => []];
    $second_sem_by_type = ['CORE' => [], 'APPLIED' => [], 'SPECIALIZED' => []];
    $error = 'Could not load subjects. Please contact administrator.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name']);
    $student_number = trim($_POST['student_number']);
    $barcode = trim($_POST['barcode']);
    $grade_level = $_POST['grade_level'];
    $section = trim($_POST['section']);
    $school_year = $_POST['school_year'];
    $student_id = $_POST['student_id'] ?? null; // For editing existing student
    
    // Generate shorter barcode if empty (like Google examples)
    if (empty($barcode)) {
        // Create simple 8-digit numeric barcode: YY + 6-digit student number
        $year_short = substr(date('Y'), -2); // Get last 2 digits of year (25 for 2025)
        $barcode = $year_short . str_pad($student_number, 6, '0', STR_PAD_LEFT);
    }
    
    // Validate basic info
    if (empty($first_name) || empty($last_name) || empty($student_number) || empty($grade_level) || empty($school_year)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($student_id) {
                // Update existing student
                $stmt = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, middle_name = ?, student_number = ?, barcode = ?, grade_level = ?, section = ?, school_year = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $middle_name, $student_number, $barcode, $grade_level, $section, $school_year, $student_id]);
            } else {
                // Check if student already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_number = ?");
                $stmt->execute([$student_number]);
                $existing_student = $stmt->fetch();
                
                if ($existing_student) {
                    $student_id = $existing_student['id'];
                } else {
                    // Insert new student
                    $stmt = $pdo->prepare("INSERT INTO students (first_name, last_name, middle_name, student_number, barcode, grade_level, section, school_year, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$first_name, $last_name, $middle_name, $student_number, $barcode, $grade_level, $section, $school_year, $_SESSION['user_id']]);
                    $student_id = $pdo->lastInsertId();
                }
            }
            
            // Process grades for each subject
            $total_grade = 0;
            $subject_count = 0;
            $has_failed = false;
            
            foreach ($all_subjects as $subject) {
                $q1 = floatval($_POST["q1_subject_{$subject['id']}"] ?? 0);
                $q2 = floatval($_POST["q2_subject_{$subject['id']}"] ?? 0);
                $q3 = floatval($_POST["q3_subject_{$subject['id']}"] ?? 0);
                $q4 = floatval($_POST["q4_subject_{$subject['id']}"] ?? 0);
                
                // Calculate final grade based on semester assignment
                if ($subject['is_first_sem'] == 1) {
                    // First semester subjects: average of Q1 and Q2
                    $final_grade = ($q1 + $q2) / 2;
                } elseif ($subject['is_second_sem'] == 1) {
                    // Second semester subjects: average of Q3 and Q4
                    $final_grade = ($q3 + $q4) / 2;
                } else {
                    // Year-long subjects: average of all quarters
                    $final_grade = ($q1 + $q2 + $q3 + $q4) / 4;
                }
                
                $status = $final_grade >= 75 ? 'PASSED' : 'FAILED';
                
                if ($status == 'FAILED') {
                    $has_failed = true;
                }
                
                // Only count subjects with grades for GPA calculation
                if ($final_grade > 0) {
                    $total_grade += $final_grade;
                    $subject_count++;
                }
                
                // Insert or update grade
                $stmt = $pdo->prepare("INSERT INTO student_grades (student_id, subject_id, quarter_1, quarter_2, quarter_3, quarter_4, final_grade, status, school_year, created_by) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE 
                                     quarter_1 = VALUES(quarter_1), quarter_2 = VALUES(quarter_2), 
                                     quarter_3 = VALUES(quarter_3), quarter_4 = VALUES(quarter_4), 
                                     final_grade = VALUES(final_grade), status = VALUES(status), 
                                     updated_at = CURRENT_TIMESTAMP");
                $stmt->execute([$student_id, $subject['id'], $q1, $q2, $q3, $q4, $final_grade, $status, $school_year, $_SESSION['user_id']]);
            }
            
            // Calculate honors if applicable
            if ($subject_count > 0 && !$has_failed) {
                $general_average = $total_grade / $subject_count;
                $honor_type = null;
                
                if ($general_average >= 98) {
                    $honor_type = 'With Highest Honors';
                } elseif ($general_average >= 95) {
                    $honor_type = 'With High Honors';
                } elseif ($general_average >= 90) {
                    $honor_type = 'With Honors';
                }
                
                // Insert honors if applicable
                if ($honor_type) {
                    $stmt = $pdo->prepare("INSERT INTO student_honors (student_id, honor_type, general_average, school_year, created_by) 
                                         VALUES (?, ?, ?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE 
                                         honor_type = VALUES(honor_type), general_average = VALUES(general_average)");
                    $stmt->execute([$student_id, $honor_type, $general_average, $school_year, $_SESSION['user_id']]);
                }
            }
            
            $pdo->commit();
            $success = 'Student grades have been successfully saved!';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error saving student grades. Please try again.';
        }
    }
}

// Current school year
$current_year = date('Y');
$next_year = $current_year + 1;
$default_school_year = $current_year . '-' . $next_year;

// Start output buffering for page content
ob_start();
?>
<style>
.semester-section {
    margin: 30px 0;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    overflow: hidden;
}

.semester-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.semester-title {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.semester-section .subject-type-section {
    margin: 0;
    border: none;
    border-radius: 0;
}

.semester-section .subject-type-section:not(:last-child) {
    border-bottom: 1px solid #e1e5e9;
}

.semester-section .subject-type-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e1e5e9;
}

.semester-section .grades-table th {
    background-color: #f1f3f4;
}
</style>

<!-- Student Information Card -->
<div class="content-card fade-in">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <div class="card-header">
        <h2 class="card-title">
            <?php echo $edit_student ? 'Edit Student Grades' : 'Student Grades Input Form'; ?>
        </h2>
        <p class="card-subtitle">
            <?php echo $edit_student ? 'Update student information and grades for all subjects' : 'Enter student information and grades for all subjects'; ?>
        </p>
    </div>
    
    <form method="POST" action="student_grades.php" id="gradesForm">
        <?php if ($edit_student): ?>
            <input type="hidden" name="student_id" value="<?php echo $edit_student['id']; ?>">
        <?php endif; ?>
        <!-- Student Information Section -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-user"></i>
                Student Information
            </h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="student_number">Student Number *</label>
                    <input type="text" id="student_number" name="student_number" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['student_number'] ?? $_POST['student_number'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['middle_name'] ?? $_POST['middle_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="grade_level">Grade Level *</label>
                    <select id="grade_level" name="grade_level" class="form-control" required>
                        <option value="">Select Grade Level</option>
                        <option value="Grade 11" <?php echo ($edit_student['grade_level'] ?? $_POST['grade_level'] ?? '') === 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="Grade 12" <?php echo ($edit_student['grade_level'] ?? $_POST['grade_level'] ?? '') === 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="section">Section</label>
                    <input type="text" id="section" name="section" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['section'] ?? $_POST['section'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="school_year">School Year *</label>
                    <input type="text" id="school_year" name="school_year" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_student['school_year'] ?? $_POST['school_year'] ?? $default_school_year); ?>" 
                           placeholder="e.g., 2024-2025" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="barcode">Student Barcode</label>
                    <div class="barcode-input-group">
                        <input type="text" id="barcode" name="barcode" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_student['barcode'] ?? $_POST['barcode'] ?? ''); ?>" 
                               placeholder="Enter or generate barcode">
                        <button type="button" id="generateBarcode" class="btn btn-secondary">
                            <i class="fas fa-barcode"></i> Generate
                        </button>
                    </div>
                    <small class="form-text text-muted">A unique barcode for attendance tracking. Leave empty to auto-generate.</small>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Grades Input Card -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-graduation-cap"></i>
            Subject Grades
        </h3>
        <p class="card-subtitle">Enter grades based on subject semester assignment. 1st Semester (Q1, Q2) and 2nd Semester (Q3, Q4).</p>
    </div>
    
    <!-- First Semester Section -->
    <div class="semester-section">
        <div class="semester-header">
            <h3 class="semester-title">
                <i class="fas fa-calendar-alt"></i>
                First Semester (1st & 2nd Quarter)
            </h3>
        </div>
        
        <?php if (!empty($first_sem_by_type['CORE'])): ?>
        <!-- First Semester Core Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('first-core')">
                <h4 class="subject-type-title">
                    <i class="fas fa-book"></i>
                    Core Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="first-core-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="first-core-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 1</th>
                                <th>Quarter 2</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($first_sem_by_type['CORE'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q1_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_1'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="1"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q2_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_2'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="2"
                                           form="gradesForm">
                                </td>
                                <!-- Hidden Q3 and Q4 fields to maintain form consistency -->
                                <input type="hidden" name="q3_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q4_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($first_sem_by_type['APPLIED'])): ?>
        <!-- First Semester Applied Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('first-applied')">
                <h4 class="subject-type-title">
                    <i class="fas fa-laptop"></i>
                    Applied Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="first-applied-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="first-applied-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 1</th>
                                <th>Quarter 2</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($first_sem_by_type['APPLIED'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q1_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_1'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="1"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q2_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_2'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="2"
                                           form="gradesForm">
                                </td>
                                <!-- Hidden Q3 and Q4 fields to maintain form consistency -->
                                <input type="hidden" name="q3_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q4_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($first_sem_by_type['SPECIALIZED'])): ?>
        <!-- First Semester Specialized Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('first-specialized')">
                <h4 class="subject-type-title">
                    <i class="fas fa-tools"></i>
                    Specialized Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="first-specialized-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="first-specialized-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 1</th>
                                <th>Quarter 2</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($first_sem_by_type['SPECIALIZED'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q1_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_1'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="1"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q2_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_2'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="2"
                                           form="gradesForm">
                                </td>
                                <!-- Hidden Q3 and Q4 fields to maintain form consistency -->
                                <input type="hidden" name="q3_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q4_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_1'] + $existing_grade['quarter_2']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Second Semester Section -->
    <div class="semester-section">
        <div class="semester-header">
            <h3 class="semester-title">
                <i class="fas fa-calendar-alt"></i>
                Second Semester (3rd & 4th Quarter)
            </h3>
        </div>
        
        <?php if (!empty($second_sem_by_type['CORE'])): ?>
        <!-- Second Semester Core Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('second-core')">
                <h4 class="subject-type-title">
                    <i class="fas fa-book"></i>
                    Core Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="second-core-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="second-core-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 3</th>
                                <th>Quarter 4</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($second_sem_by_type['CORE'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <!-- Hidden Q1 and Q2 fields to maintain form consistency -->
                                <input type="hidden" name="q1_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q2_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q3_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_3'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="3"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q4_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_4'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="4"
                                           form="gradesForm">
                                </td>
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($second_sem_by_type['APPLIED'])): ?>
        <!-- Second Semester Applied Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('second-applied')">
                <h4 class="subject-type-title">
                    <i class="fas fa-laptop"></i>
                    Applied Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="second-applied-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="second-applied-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 3</th>
                                <th>Quarter 4</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($second_sem_by_type['APPLIED'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <!-- Hidden Q1 and Q2 fields to maintain form consistency -->
                                <input type="hidden" name="q1_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q2_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q3_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_3'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="3"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q4_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_4'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="4"
                                           form="gradesForm">
                                </td>
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($second_sem_by_type['SPECIALIZED'])): ?>
        <!-- Second Semester Specialized Subjects -->
        <div class="subject-type-section">
            <div class="subject-type-header" onclick="toggleSection('second-specialized')">
                <h4 class="subject-type-title">
                    <i class="fas fa-tools"></i>
                    Specialized Subjects
                    <i class="fas fa-chevron-down toggle-icon" id="second-specialized-icon"></i>
                </h4>
            </div>
            <div class="subject-content" id="second-specialized-content">
                <div class="table-responsive">
                    <table class="data-table grades-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Subject</th>
                                <th>Quarter 3</th>
                                <th>Quarter 4</th>
                                <th>Semester Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($second_sem_by_type['SPECIALIZED'] as $subject): 
                                $existing_grade = $existing_grades[$subject['id']] ?? null;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                </td>
                                <!-- Hidden Q1 and Q2 fields to maintain form consistency -->
                                <input type="hidden" name="q1_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <input type="hidden" name="q2_subject_<?php echo $subject['id']; ?>" value="0" form="gradesForm">
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q3_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_3'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="3"
                                           form="gradesForm">
                                </td>
                                <td>
                                    <input type="number" class="grade-input quarter-grade" 
                                           name="q4_subject_<?php echo $subject['id']; ?>" 
                                           value="<?php echo $existing_grade ? $existing_grade['quarter_4'] : ''; ?>"
                                           min="0" max="100" step="0.01"
                                           data-subject="<?php echo $subject['id']; ?>" data-quarter="4"
                                           form="gradesForm">
                                </td>
                                <td class="final-grade" id="final_<?php echo $subject['id']; ?>">
                                    <?php echo $existing_grade ? number_format(($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2, 2) : '-'; ?>
                                </td>
                                <td class="status-cell" id="status_<?php echo $subject['id']; ?>">
                                    <?php 
                                    if($existing_grade) {
                                        $sem_avg = ($existing_grade['quarter_3'] + $existing_grade['quarter_4']) / 2;
                                        echo $sem_avg >= 75 ? 'PASSED' : 'FAILED';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Academic Summary Card -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-line"></i>
            Academic Summary
        </h3>
        <p class="card-subtitle">Overview of student's academic performance</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-number" id="generalAverage">-</div>
            <div class="stat-label">General Average</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-medal"></i>
            </div>
            <div class="stat-number" id="academicStanding" style="font-size: 1.2rem;">-</div>
            <div class="stat-label">Academic Standing</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-number" id="honorsStatus" style="font-size: 1.2rem;">-</div>
            <div class="stat-label">Honors</div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <button type="submit" form="gradesForm" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
            <i class="fas fa-save"></i>
            <?php echo $edit_student ? 'Update Student Grades' : 'Save Student Grades'; ?>
        </button>
        <a href="view_grades.php" class="btn btn-secondary" style="margin-left: 10px;">
            <i class="fas fa-list"></i>
            View All Grades
        </a>
        <?php if ($edit_student): ?>
            <a href="student_grades.php" class="btn btn-outline-secondary" style="margin-left: 10px;">
                <i class="fas fa-plus"></i>
                Add New Student
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-section {
        margin-bottom: 30px;
    }
    
    .section-title {
        color: #11998e;
        margin-bottom: 20px;
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .barcode-input-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .barcode-input-group .form-control {
        flex: 1;
    }
    
    .barcode-input-group .btn {
        white-space: nowrap;
        padding: 8px 16px;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        color: white;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .barcode-input-group .btn:hover {
        background: linear-gradient(135deg, #0d8c7f 0%, #32d970 100%);
        transform: translateY(-2px);
    }
    
    .subject-type-section {
        margin-bottom: 25px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        background: white;
    }
    
    .subject-type-header {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 15px 20px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .subject-type-header:hover {
        background: linear-gradient(135deg, #0d8c7f 0%, #32d970 100%);
    }
    
    .subject-type-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: space-between;
    }
    
    .subject-type-title i {
        font-size: 1.2rem;
    }
    
    .toggle-icon {
        transition: transform 0.3s ease;
    }
    
    .toggle-icon.rotated {
        transform: rotate(180deg);
    }
    
    .subject-content {
        padding: 0;
        max-height: 1000px;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .subject-content.collapsed {
        max-height: 0;
    }
    
    .grades-table {
        margin-bottom: 0;
    }
    
    .grade-input {
        width: 80px;
        padding: 8px 10px;
        border: 2px solid #e1e5e9;
        border-radius: 6px;
        text-align: center;
        font-weight: 500;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .grade-input:focus {
        border-color: #11998e;
        background: white;
        box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
        outline: none;
    }
    
    .final-grade {
        font-weight: bold;
        color: #11998e;
        background: #e8f5e8 !important;
        text-align: center;
    }
    
    .status-cell {
        font-weight: bold;
        text-align: center;
    }
    
    .status-passed {
        color: #28a745;
    }
    
    .status-failed {
        color: #dc3545;
    }
    
    .subject-name {
        text-align: left !important;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 8px;
    }
    
    .text-muted {
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .grade-input {
            width: 60px;
            padding: 6px;
            font-size: 0.9rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 4px;
            font-size: 0.85rem;
        }
    }
</style>

<script>
    // Toggle section visibility
    function toggleSection(sectionType) {
        const content = document.getElementById(sectionType + '-content');
        const icon = document.getElementById(sectionType + '-icon');
        
        content.classList.toggle('collapsed');
        icon.classList.toggle('rotated');
    }
    
    // Calculate final grades and status in real-time
    function calculateGrades() {
        const subjects = <?php echo json_encode(array_column($all_subjects, 'id')); ?>;
        let totalGrade = 0;
        let subjectCount = 0;
        let hasFailed = false;
        
        subjects.forEach(subjectId => {
            const q1 = parseFloat(document.querySelector(`input[data-subject="${subjectId}"][data-quarter="1"]`).value) || 0;
            const q2 = parseFloat(document.querySelector(`input[data-subject="${subjectId}"][data-quarter="2"]`).value) || 0;
            const q3 = parseFloat(document.querySelector(`input[data-subject="${subjectId}"][data-quarter="3"]`).value) || 0;
            const q4 = parseFloat(document.querySelector(`input[data-subject="${subjectId}"][data-quarter="4"]`).value) || 0;
            
            const finalGrade = (q1 + q2 + q3 + q4) / 4;
            const status = finalGrade >= 75 ? 'PASSED' : 'FAILED';
            
            // Update display
            const finalCell = document.getElementById(`final_${subjectId}`);
            const statusCell = document.getElementById(`status_${subjectId}`);
            
            if (finalGrade > 0) {
                finalCell.textContent = finalGrade.toFixed(2);
                statusCell.textContent = status;
                statusCell.className = `status-cell ${status === 'PASSED' ? 'status-passed' : 'status-failed'}`;
                
                totalGrade += finalGrade;
                subjectCount++;
                
                if (status === 'FAILED') {
                    hasFailed = true;
                }
            } else {
                finalCell.textContent = '-';
                statusCell.textContent = '-';
                statusCell.className = 'status-cell';
            }
        });
        
        // Calculate and display general average
        if (subjectCount > 0) {
            const generalAverage = totalGrade / subjectCount;
            document.getElementById('generalAverage').textContent = generalAverage.toFixed(2);
            
            // Determine academic standing
            let standing = 'Good Standing';
            let honors = 'None';
            
            if (hasFailed) {
                standing = 'Needs Improvement';
            } else {
                if (generalAverage >= 98) {
                    honors = 'With Highest Honors';
                    standing = 'Excellent';
                } else if (generalAverage >= 95) {
                    honors = 'With High Honors';
                    standing = 'Very Good';
                } else if (generalAverage >= 90) {
                    honors = 'With Honors';
                    standing = 'Very Good';
                }
            }
            
            document.getElementById('academicStanding').textContent = standing;
            document.getElementById('honorsStatus').textContent = honors;
            
            // Color coding for honors
            const honorsElement = document.getElementById('honorsStatus');
            if (honors !== 'None') {
                honorsElement.style.color = '#28a745';
            } else {
                honorsElement.style.color = '#6c757d';
            }
        } else {
            document.getElementById('generalAverage').textContent = '-';
            document.getElementById('academicStanding').textContent = '-';
            document.getElementById('honorsStatus').textContent = '-';
        }
    }
    
    // Add event listeners to all grade inputs
    document.querySelectorAll('.quarter-grade').forEach(input => {
        input.addEventListener('input', calculateGrades);
    });
    
    // Initialize calculations on page load
    document.addEventListener('DOMContentLoaded', function() {
        calculateGrades();
        
        // Barcode generator functionality
        document.getElementById('generateBarcode').addEventListener('click', function() {
            const studentNumber = document.getElementById('student_number').value;
            const currentYear = new Date().getFullYear();
            
            if (studentNumber.trim() === '') {
                alert('Please enter student number first');
                document.getElementById('student_number').focus();
                return;
            }
            
            // Generate shorter numeric barcode (like Google image examples)
            // Format: YY + 6-digit student number (e.g., 25000001)
            const yearShort = new Date().getFullYear().toString().slice(-2);
            const paddedNumber = studentNumber.padStart(6, '0');
            const generatedBarcode = `${yearShort}${paddedNumber}`;
            
            document.getElementById('barcode').value = generatedBarcode;
        });
    });
    
    // Form validation
    document.getElementById('gradesForm').addEventListener('submit', function(e) {
        const requiredFields = ['student_number', 'first_name', 'last_name', 'grade_level', 'school_year'];
        let isValid = true;
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementsByName(fieldName)[0];
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#dc3545';
            } else {
                field.style.borderColor = '#e1e5e9';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>