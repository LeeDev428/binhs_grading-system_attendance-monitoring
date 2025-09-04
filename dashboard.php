<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Set page variables for layout
$current_page = 'dashboard';
$page_title = 'Dashboard';

// Get some statistics for the dashboard
try {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $total_students = $stmt->fetch()['total'];
    
    // Students with honors
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as total FROM student_honors");
    $honor_students = $stmt->fetch()['total'];
    
    // Average grade
    $stmt = $pdo->query("SELECT AVG(final_grade) as avg_grade FROM student_grades WHERE final_grade > 0");
    $avg_grade = $stmt->fetch()['avg_grade'] ?? 0;
    
    // Failed subjects count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM student_grades WHERE status = 'FAILED'");
    $failed_subjects = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $total_students = 0;
    $honor_students = 0;
    $avg_grade = 0;
    $failed_subjects = 0;
}

// Start output buffering for page content
ob_start();
?>

<!-- Dashboard Overview -->
<div class="content-card fade-in">
    <div class="card-header">
        <div>
            <h2 class="card-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User'); ?>!</h2>
            <p class="card-subtitle">Here's an overview of your academic management system</p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($total_students); ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-number"><?php echo number_format($honor_students); ?></div>
            <div class="stat-label">Honor Students</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-number"><?php echo number_format($avg_grade, 1); ?></div>
            <div class="stat-label">Average Grade</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($failed_subjects); ?></div>
            <div class="stat-label">Failed Subjects</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
        <p class="card-subtitle">Frequently used features and tools</p>
    </div>
    
    <div class="form-grid">
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h4>Add Student Grades</h4>
            <p>Input grades for students across all subjects</p>
            <a href="student_grades.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Input Grades
            </a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-list-alt"></i>
            </div>
            <h4>View All Grades</h4>
            <p>Browse and manage existing student grades</p>
            <a href="view_grades.php" class="btn btn-info">
                <i class="fas fa-eye"></i> View Grades
            </a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h4>Attendance Tracking</h4>
            <p>Monitor and record student attendance with barcode scanner</p>
            <a href="attendance_scanner.php" class="btn btn-success">
                <i class="fas fa-qrcode"></i> Scan Attendance
            </a>
        </div>
        
        <div class="action-card">
            <div class="action-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <h4>Generate Reports</h4>
            <p>Create academic and performance reports</p>
            <a href="attendance_reports.php" class="btn btn-warning">
                <i class="fas fa-file-alt"></i> View Reports
            </a>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="content-card fade-in">
    <div class="card-header">
        <h3 class="card-title">Recent Activity</h3>
        <p class="card-subtitle">Latest updates and changes in the system</p>
    </div>
    
    <div class="activity-list">
        <?php
        try {
            $stmt = $pdo->query("
                SELECT 
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    sub.subject_name,
                    sg.final_grade,
                    sg.created_at
                FROM student_grades sg
                JOIN students s ON sg.student_id = s.id
                JOIN subjects sub ON sg.subject_id = sub.id
                ORDER BY sg.created_at DESC
                LIMIT 5
            ");
            $recent_grades = $stmt->fetchAll();
            
            if ($recent_grades):
        ?>
            <?php foreach ($recent_grades as $grade): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="activity-content">
                    <p><strong><?php echo htmlspecialchars($grade['student_name']); ?></strong> 
                       received a grade of <strong><?php echo $grade['final_grade']; ?></strong> 
                       in <?php echo htmlspecialchars($grade['subject_name']); ?></p>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($grade['created_at'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No recent activity to display</p>
            </div>
        <?php endif; ?>
        <?php } catch (PDOException $e) { ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                Unable to load recent activity
            </div>
        <?php } ?>
    </div>
</div>

<style>
    .action-card {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        text-align: center;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        border-color: #11998e;
    }
    
    .action-icon {
        font-size: 3rem;
        color: #11998e;
        margin-bottom: 15px;
    }
    
    .action-card h4 {
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .action-card p {
        color: #666;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }
    
    .activity-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        align-items: flex-start;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-content p {
        margin: 0 0 5px 0;
        color: #333;
    }
    
    .text-muted {
        color: #666;
        font-size: 0.85rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
    }
</style>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
