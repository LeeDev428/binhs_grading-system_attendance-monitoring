<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'view_grades';
$page_title = 'Student Details';

// Get student ID from URL
$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    redirect('view_grades.php');
}

$student = null;
$grades = [];
$honors = [];

try {
    // Get student information
    $stmt = $pdo->prepare("
        SELECT s.*, 
               AVG(sg.final_grade) as general_average,
               COUNT(sg.id) as total_subjects,
               COUNT(CASE WHEN sg.status = 'FAILED' THEN 1 END) as failed_subjects
        FROM students s
        LEFT JOIN student_grades sg ON s.id = sg.student_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        redirect('view_grades.php');
    }
    
    // Get grades separated by semester
    // First semester grades
    $stmt = $pdo->prepare("
        SELECT sg.*, sub.subject_name, sub.subject_code, sub.subject_type, sub.is_first_sem, sub.is_second_sem
        FROM student_grades sg
        JOIN subjects sub ON sg.subject_id = sub.id
        WHERE sg.student_id = ? AND sub.is_first_sem = 1
        ORDER BY sub.subject_type, sub.subject_name
    ");
    $stmt->execute([$student_id]);
    $first_sem_grades = $stmt->fetchAll();
    
    // Second semester grades
    $stmt = $pdo->prepare("
        SELECT sg.*, sub.subject_name, sub.subject_code, sub.subject_type, sub.is_first_sem, sub.is_second_sem
        FROM student_grades sg
        JOIN subjects sub ON sg.subject_id = sub.id
        WHERE sg.student_id = ? AND sub.is_second_sem = 1
        ORDER BY sub.subject_type, sub.subject_name
    ");
    $stmt->execute([$student_id]);
    $second_sem_grades = $stmt->fetchAll();
    
    // All grades for compatibility
    $stmt = $pdo->prepare("
        SELECT sg.*, sub.subject_name, sub.subject_code, sub.subject_type
        FROM student_grades sg
        JOIN subjects sub ON sg.subject_id = sub.id
        WHERE sg.student_id = ?
        ORDER BY sub.subject_name
    ");
    $stmt->execute([$student_id]);
    $all_grades = $stmt->fetchAll();
    
    // Get honors
    $stmt = $pdo->prepare("
        SELECT * FROM student_honors
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$student_id]);
    $honors = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Could not load student data.';
}

// Start output buffering for page content
ob_start();
?>

<!-- Student Header -->
<div class="content-card fade-in">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i class="fas fa-user-graduate"></i>
                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
            </h2>
            <p class="card-subtitle">Student Number: <?php echo htmlspecialchars($student['student_number']); ?></p>
        </div>
        <div>
            <a href="view_grades.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to List
            </a>
            <a href="student_grades.php?edit=<?php echo $student['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i>
                Edit Grades
            </a>
            <a href="sf9_form.php?student_id=<?php echo $student['id']; ?>" class="btn btn-success">
                <i class="fas fa-print"></i>
                Print SF9
            </a>
            <a href="sf10_form.php?student_id=<?php echo $student['id']; ?>" class="btn btn-warning">
                <i class="fas fa-print"></i>
                Print SF10
            </a>
        </div>
    </div>
</div>

<!-- Student Information -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-info-circle"></i>
            Student Information
        </h3>
    </div>
    
    <div class="form-grid">
        <div class="info-item">
            <label class="info-label">Full Name</label>
            <div class="info-value">
                <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']); ?>
            </div>
        </div>
        
        <div class="info-item">
            <label class="info-label">Student Number</label>
            <div class="info-value"><?php echo htmlspecialchars($student['student_number']); ?></div>
        </div>
        
        <div class="info-item">
            <label class="info-label">Grade Level</label>
            <div class="info-value"><?php echo htmlspecialchars($student['grade_level']); ?></div>
        </div>
        
        <div class="info-item">
            <label class="info-label">Section</label>
            <div class="info-value"><?php echo htmlspecialchars($student['section'] ?: 'Not assigned'); ?></div>
        </div>
        
        <div class="info-item">
            <label class="info-label">School Year</label>
            <div class="info-value"><?php echo htmlspecialchars($student['school_year']); ?></div>
        </div>
        
        <div class="info-item">
            <label class="info-label">Enrollment Date</label>
            <div class="info-value"><?php echo date('M j, Y', strtotime($student['created_at'])); ?></div>
        </div>
    </div>
</div>

<!-- Academic Summary -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-line"></i>
            Academic Summary
        </h3>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-number">
                <?php echo $student['general_average'] ? number_format($student['general_average'], 2) : 'N/A'; ?>
            </div>
            <div class="stat-label">General Average</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-number"><?php echo $student['total_subjects']; ?></div>
            <div class="stat-label">Total Subjects</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo ($student['total_subjects'] - $student['failed_subjects']); ?></div>
            <div class="stat-label">Passed Subjects</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-number"><?php echo $student['failed_subjects']; ?></div>
            <div class="stat-label">Failed Subjects</div>
        </div>
    </div>
</div>

<!-- Detailed Grades -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-graduation-cap"></i>
            Subject Grades
        </h3>
        <p class="card-subtitle">Detailed breakdown of grades by subject and quarter</p>
    </div>
    
    <?php if (empty($grades)): ?>
        <div style="text-align: center; padding: 40px; color: #999;">
            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 15px;"></i>
            <p>No grades recorded for this student yet.</p>
            <a href="student_grades.php?edit=<?php echo $student['id']; ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add Grades
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Quarter 1</th>
                        <th>Quarter 2</th>
                        <th>Quarter 3</th>
                        <th>Quarter 4</th>
                        <th>Final Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $grade): ?>
                    <tr>
                        <td class="subject-name">
                            <strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($grade['subject_code']); ?></small>
                        </td>
                        <td class="grade-cell"><?php echo $grade['quarter_1'] > 0 ? number_format($grade['quarter_1'], 2) : '-'; ?></td>
                        <td class="grade-cell"><?php echo $grade['quarter_2'] > 0 ? number_format($grade['quarter_2'], 2) : '-'; ?></td>
                        <td class="grade-cell"><?php echo $grade['quarter_3'] > 0 ? number_format($grade['quarter_3'], 2) : '-'; ?></td>
                        <td class="grade-cell"><?php echo $grade['quarter_4'] > 0 ? number_format($grade['quarter_4'], 2) : '-'; ?></td>
                        <td class="final-grade-cell">
                            <span class="grade-badge <?php echo $grade['final_grade'] >= 95 ? 'badge-excellent' : ($grade['final_grade'] >= 85 ? 'badge-good' : ($grade['final_grade'] >= 75 ? 'badge-average' : 'badge-poor')); ?>">
                                <?php echo number_format($grade['final_grade'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $grade['status'] == 'PASSED' ? 'status-passed' : 'status-failed'; ?>">
                                <i class="fas <?php echo $grade['status'] == 'PASSED' ? 'fa-check' : 'fa-times'; ?>"></i>
                                <?php echo $grade['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Honors and Recognition -->
<?php if (!empty($honors)): ?>
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-trophy"></i>
            Honors and Recognition
        </h3>
    </div>
    
    <div class="honors-list">
        <?php foreach ($honors as $honor): ?>
        <div class="honor-item">
            <div class="honor-icon">
                <i class="fas fa-medal"></i>
            </div>
            <div class="honor-details">
                <h4><?php echo htmlspecialchars($honor['honor_type']); ?></h4>
                <p>General Average: <strong><?php echo number_format($honor['general_average'], 2); ?></strong></p>
                <small class="text-muted">
                    <?php echo htmlspecialchars($honor['school_year']); ?> - 
                    <?php echo date('M j, Y', strtotime($honor['created_at'])); ?>
                </small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
    .info-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #11998e;
    }
    
    .info-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: block;
    }
    
    .info-value {
        color: #333;
        font-size: 1rem;
        font-weight: 500;
    }
    
    .subject-name {
        text-align: left !important;
    }
    
    .grade-cell {
        text-align: center;
        font-weight: 500;
        color: #333;
    }
    
    .final-grade-cell {
        text-align: center;
    }
    
    .grade-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.9rem;
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
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-passed {
        background: #d4edda;
        color: #155724;
    }
    
    .status-failed {
        background: #f8d7da;
        color: #721c24;
    }
    
    .honors-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .honor-item {
        display: flex;
        align-items: center;
        padding: 20px;
        background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
        border-radius: 10px;
        border-left: 4px solid #ffc107;
    }
    
    .honor-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        margin-right: 20px;
    }
    
    .honor-details h4 {
        color: #e65100;
        margin-bottom: 5px;
        font-size: 1.1rem;
    }
    
    .honor-details p {
        color: #333;
        margin-bottom: 5px;
    }
    
    .text-muted {
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .honor-item {
            flex-direction: column;
            text-align: center;
        }
        
        .honor-icon {
            margin-right: 0;
            margin-bottom: 15px;
        }
    }
</style>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
