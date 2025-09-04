<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'view_grades';
$page_title = 'Student Grades Overview';

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
    
    /* Search functionality */
    .search-container {
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .search-input {
        max-width: 300px;
        padding: 10px 15px;
        border: 2px solid #e1e5e9;
        border-radius: 25px;
        font-size: 0.9rem;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #11998e;
        background: white;
        box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 15px;
        border: 2px solid #e1e5e9;
        background: white;
        color: #666;
        border-radius: 20px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-btn:hover,
    .filter-btn.active {
        border-color: #11998e;
        background: #11998e;
        color: white;
    }
    
    /* Print styles */
    @media print {
        .sidebar,
        .top-header,
        .btn,
        .action-buttons,
        .search-container {
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
        
        .search-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-input {
            max-width: 100%;
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
        
        window.layoutUtils.showAlert('Data exported successfully!', 'success');
    }
    
    function printStudentReport(studentId) {
        // Open student detail in new window for printing
        const printWindow = window.open(`view_student_detail.php?id=${studentId}&print=1`, '_blank');
        printWindow.addEventListener('load', function() {
            printWindow.print();
        });
    }
    
    // Add search functionality
    function addSearchFunctionality() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search students...';
        searchInput.className = 'form-control';
        searchInput.style.maxWidth = '300px';
        searchInput.style.marginBottom = '20px';
        
        const tableCard = document.querySelector('.content-card:last-child');
        const cardHeader = tableCard.querySelector('.card-header');
        cardHeader.appendChild(searchInput);
        
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
    
    // Initialize search when page loads
    document.addEventListener('DOMContentLoaded', addSearchFunctionality);
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .students-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-collapse: collapse;
        }
        
        .students-table th {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .students-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .students-table tr:hover {
            background: #f8f9fa;
        }
        
        .grade-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .grade-excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .grade-good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .grade-average {
            background: #fff3cd;
            color: #856404;
        }
        
        .grade-poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        .honor-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #ffd700;
            color: #333;
        }
        
        .action-btn {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            margin-right: 5px;
        }
        
        .action-btn:hover {
            background: #138496;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #11998e;
        }
        
        .stats-label {
            color: #666;
            margin-top: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="grades-container">
        <div class="header-section">
            <div>
                <h1 style="color: #11998e; margin-bottom: 5px;">Student Grades Overview</h1>
                <p style="color: #666;">View and manage all student academic records</p>
            </div>
            <div>
                <a href="dashboard.php" class="back-btn">← Dashboard</a>
                <a href="student_grades.php" class="add-btn">+ Add New Student</a>
            </div>
        </div>
        
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3 style="color: #666; margin-bottom: 10px;">No Students Found</h3>
                <p style="color: #999; margin-bottom: 30px;">Start by adding student grades using the form above.</p>
                <a href="student_grades.php" class="add-btn">Add First Student</a>
            </div>
        <?php else: ?>
            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($students); ?></div>
                    <div class="stats-label">Total Students</div>
                </div>
                <div class="stats-card">
                    <div class="stats-number">
                        <?php 
                        $honor_students = array_filter($students, function($s) { return !empty($s['honor_type']); });
                        echo count($honor_students);
                        ?>
                    </div>
                    <div class="stats-label">Honor Students</div>
                </div>
                <div class="stats-card">
                    <div class="stats-number">
                        <?php 
                        $failing_students = array_filter($students, function($s) { return $s['failed_subjects'] > 0; });
                        echo count($failing_students);
                        ?>
                    </div>
                    <div class="stats-label">Students with Failed Subjects</div>
                </div>
                <div class="stats-card">
                    <div class="stats-number">
                        <?php 
                        $avg_grade = array_sum(array_column($students, 'general_average')) / count($students);
                        echo number_format($avg_grade, 2);
                        ?>
                    </div>
                    <div class="stats-label">Overall Average</div>
                </div>
            </div>
            
            <!-- Students Table -->
            <table class="students-table">
                <thead>
                    <tr>
                        <th>Student Number</th>
                        <th>Name</th>
                        <th>Grade & Section</th>
                        <th>School Year</th>
                        <th>General Average</th>
                        <th>Failed Subjects</th>
                        <th>Honors</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($student['student_number']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                            <?php if ($student['middle_name']): ?>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($student['middle_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($student['grade_level'] . ' - ' . $student['section']); ?></td>
                        <td><?php echo htmlspecialchars($student['school_year']); ?></td>
                        <td>
                            <?php if ($student['general_average']): ?>
                                <?php 
                                $avg = $student['general_average'];
                                $class = '';
                                if ($avg >= 95) $class = 'grade-excellent';
                                elseif ($avg >= 85) $class = 'grade-good';
                                elseif ($avg >= 75) $class = 'grade-average';
                                else $class = 'grade-poor';
                                ?>
                                <span class="grade-badge <?php echo $class; ?>">
                                    <?php echo number_format($avg, 2); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">No grades</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['failed_subjects'] > 0): ?>
                                <span class="grade-badge grade-poor"><?php echo $student['failed_subjects']; ?></span>
                            <?php else: ?>
                                <span style="color: #28a745;">✓ None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['honor_type']): ?>
                                <span class="honor-badge"><?php echo htmlspecialchars($student['honor_type']); ?></span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_student_detail.php?id=<?php echo $student['id']; ?>" class="action-btn">View Details</a>
                            <a href="student_grades.php?edit=<?php echo $student['id']; ?>" class="action-btn" style="background: #28a745;">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
