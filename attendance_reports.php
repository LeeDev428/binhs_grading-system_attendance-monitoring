<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$page_title = 'Attendance Reports';
$current_page = 'attendance_reports';

// Initialize variables
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$grade_level = $_GET['grade_level'] ?? '';
$section = $_GET['section'] ?? '';
$status_filter = $_GET['status'] ?? '';
$export_format = $_GET['export'] ?? '';

// Get all grade levels and sections for filters
$grade_levels = [];
$sections = [];

try {
    $stmt = $pdo->query("SELECT DISTINCT grade_level FROM students WHERE grade_level IS NOT NULL ORDER BY grade_level");
    $grade_levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $error_message = "Error fetching filter options: " . $e->getMessage();
}

// Build attendance query
$attendance_query = "
    SELECT 
        s.id,
        s.student_number,
        CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as full_name,
        s.grade_level,
        s.section,
        da.scan_date,
        da.scan_time,
        da.status,
        da.notes
    FROM students s
    LEFT JOIN daily_attendance da ON s.id = da.student_id 
        AND da.scan_date BETWEEN ? AND ?
    WHERE 1=1
";

$params = [$start_date, $end_date];

if (!empty($grade_level)) {
    $attendance_query .= " AND s.grade_level = ?";
    $params[] = $grade_level;
}

if (!empty($section)) {
    $attendance_query .= " AND s.section = ?";
    $params[] = $section;
}

if (!empty($status_filter)) {
    if ($status_filter === 'absent') {
        $attendance_query .= " AND da.status IS NULL";
    } else {
        $attendance_query .= " AND da.status = ?";
        $params[] = $status_filter;
    }
}

$attendance_query .= " ORDER BY s.grade_level, s.section, s.last_name, s.first_name, da.scan_date";

// Execute query
$attendance_records = [];
$summary_stats = [
    'total_students' => 0,
    'present_count' => 0,
    'late_count' => 0,
    'absent_count' => 0,
    'total_days' => 0
];

try {
    $stmt = $pdo->prepare($attendance_query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Calculate summary statistics
    $unique_students = [];
    $unique_dates = [];
    
    foreach ($attendance_records as $record) {
        $unique_students[$record['id']] = true;
        
        if ($record['scan_date']) {
            $unique_dates[$record['scan_date']] = true;
            
            if ($record['status'] === 'present') {
                $summary_stats['present_count']++;
            } elseif ($record['status'] === 'late') {
                $summary_stats['late_count']++;
            }
        } else {
            $summary_stats['absent_count']++;
        }
    }
    
    $summary_stats['total_students'] = count($unique_students);
    $summary_stats['total_days'] = count($unique_dates);
    
} catch (Exception $e) {
    $error_message = "Error fetching attendance records: " . $e->getMessage();
}

// Handle export
if ($export_format === 'csv' && !empty($attendance_records)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Student Number',
        'Full Name',
        'Grade Level',
        'Section',
        'Date',
        'Time',
        'Status',
        'Notes'
    ]);
    
    // CSV data
    foreach ($attendance_records as $record) {
        fputcsv($output, [
            $record['student_number'],
            $record['full_name'],
            $record['grade_level'],
            $record['section'],
            $record['scan_date'] ?? 'No Record',
            $record['scan_time'] ?? 'No Record',
            $record['status'] ?? 'Absent',
            $record['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Start output buffering for page content
ob_start();
?>

<!-- Attendance Reports Content -->
<div class="reports-container">
    <div class="reports-header">
        <h1><i class="fas fa-chart-line"></i> Attendance Reports</h1>
        <p>Comprehensive attendance tracking and analysis</p>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Filters Section -->
    <div class="filters-section">
        <div class="section-header">
            <h3><i class="fas fa-filter"></i> Report Filters</h3>
        </div>
        
        <form method="GET" action="" class="filters-form">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="start_date"><i class="fas fa-calendar-alt"></i> Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                
                <div class="filter-group">
                    <label for="end_date"><i class="fas fa-calendar-alt"></i> End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                
                <div class="filter-group">
                    <label for="grade_level"><i class="fas fa-layer-group"></i> Grade Level:</label>
                    <select id="grade_level" name="grade_level">
                        <option value="">All Grades</option>
                        <?php foreach ($grade_levels as $grade): ?>
                            <option value="<?php echo htmlspecialchars($grade); ?>" 
                                    <?php echo $grade_level === $grade ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grade); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="section"><i class="fas fa-users"></i> Section:</label>
                    <select id="section" name="section">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $sect): ?>
                            <option value="<?php echo htmlspecialchars($sect); ?>" 
                                    <?php echo $section === $sect ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sect); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status"><i class="fas fa-check-circle"></i> Status:</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
            
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="attendance_reports.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
    
    <!-- Summary Statistics -->
    <?php if (!empty($attendance_records)): ?>
        <div class="summary-section">
            <div class="section-header">
                <h3><i class="fas fa-chart-pie"></i> Summary Statistics</h3>
            </div>
            
            <div class="summary-cards">
                <div class="summary-card total">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $summary_stats['total_students']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="summary-card present">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $summary_stats['present_count']; ?></h3>
                        <p>Present Records</p>
                    </div>
                </div>
                
                <div class="summary-card late">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $summary_stats['late_count']; ?></h3>
                        <p>Late Records</p>
                    </div>
                </div>
                
                <div class="summary-card absent">
                    <div class="card-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $summary_stats['absent_count']; ?></h3>
                        <p>Absent Records</p>
                    </div>
                </div>
                
                <div class="summary-card days">
                    <div class="card-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $summary_stats['total_days']; ?></h3>
                        <p>Total Days</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Records Section -->
    <div class="records-section">
        <div class="section-header">
            <h3><i class="fas fa-table"></i> Attendance Records</h3>
            <?php if (!empty($attendance_records)): ?>
                <span class="record-count"><?php echo count($attendance_records); ?> records found</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($attendance_records)): ?>
            <div class="no-records">
                <div class="no-records-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>No Records Found</h3>
                <p>No attendance records match your current filters. Try adjusting the date range or filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> Student Number</th>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-layer-group"></i> Grade Level</th>
                            <th><i class="fas fa-users"></i> Section</th>
                            <th><i class="fas fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-clock"></i> Time</th>
                            <th><i class="fas fa-check-circle"></i> Status</th>
                            <th><i class="fas fa-sticky-note"></i> Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td class="student-number"><?php echo htmlspecialchars($record['student_number']); ?></td>
                                <td class="student-name"><?php echo htmlspecialchars($record['full_name']); ?></td>
                                <td class="grade-level"><?php echo htmlspecialchars($record['grade_level']); ?></td>
                                <td class="section"><?php echo htmlspecialchars($record['section']); ?></td>
                                <td class="date">
                                    <?php if ($record['scan_date']): ?>
                                        <i class="fas fa-calendar-check"></i>
                                        <?php echo date('M d, Y', strtotime($record['scan_date'])); ?>
                                    <?php else: ?>
                                        <span class="no-record">No Record</span>
                                    <?php endif; ?>
                                </td>
                                <td class="time">
                                    <?php if ($record['scan_time']): ?>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('g:i A', strtotime($record['scan_time'])); ?>
                                    <?php else: ?>
                                        <span class="no-record">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="status">
                                    <?php 
                                    $status = $record['status'] ?? 'Absent';
                                    $status_class = 'status-' . strtolower($status);
                                    $status_icon = '';
                                    
                                    switch ($status) {
                                        case 'present':
                                            $status_icon = 'fas fa-check-circle';
                                            break;
                                        case 'late':
                                            $status_icon = 'fas fa-clock';
                                            break;
                                        default:
                                            $status_icon = 'fas fa-times-circle';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="<?php echo $status_icon; ?>"></i>
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td class="notes">
                                    <?php echo htmlspecialchars($record['notes'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Attendance Reports Styles */
.reports-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.reports-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.reports-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.reports-header p {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.section-header h3 {
    margin: 0;
    color: #495057;
    font-size: 1.4rem;
}

.record-count {
    background: #e9ecef;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #495057;
    font-size: 0.95rem;
}

.filter-group label i {
    margin-right: 5px;
    color: #6c757d;
}

.filter-group select,
.filter-group input {
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #007bff;
}

.filter-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn i {
    font-size: 16px;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #1e7e34;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    transform: translateY(-2px);
}

/* Summary Section */
.summary-section {
    margin-bottom: 30px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.summary-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    border-left: 4px solid;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
}

.summary-card.total { border-left-color: #007bff; }
.summary-card.present { border-left-color: #28a745; }
.summary-card.late { border-left-color: #ffc107; }
.summary-card.absent { border-left-color: #dc3545; }
.summary-card.days { border-left-color: #6f42c1; }

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.summary-card.total .card-icon { background: #007bff; }
.summary-card.present .card-icon { background: #28a745; }
.summary-card.late .card-icon { background: #ffc107; }
.summary-card.absent .card-icon { background: #dc3545; }
.summary-card.days .card-icon { background: #6f42c1; }

.card-content h3 {
    margin: 0 0 5px 0;
    font-size: 2.2rem;
    font-weight: bold;
    color: #495057;
}

.card-content p {
    margin: 0;
    color: #6c757d;
    font-weight: 500;
}

/* Records Section */
.records-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.table-responsive {
    overflow-x: auto;
    margin-top: 15px;
}

.records-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.records-table th,
.records-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.records-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    position: sticky;
    top: 0;
    z-index: 10;
}

.records-table th i {
    margin-right: 5px;
    color: #6c757d;
}

.records-table tbody tr:hover {
    background: #f8f9fa;
}

.records-table tbody tr:nth-child(even) {
    background: rgba(0,0,0,0.02);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-present {
    background: #d4edda;
    color: #155724;
}

.status-late {
    background: #fff3cd;
    color: #856404;
}

.status-absent {
    background: #f8d7da;
    color: #721c24;
}

.no-record {
    color: #6c757d;
    font-style: italic;
}

.student-number {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.student-name {
    font-weight: 500;
}

/* No Records */
.no-records {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-records-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-records h3 {
    margin: 0 0 10px 0;
    color: #495057;
}

.no-records p {
    margin: 0;
    font-size: 1.1rem;
}

/* Alert */
.alert {
    padding: 15px 20px;
    margin-bottom: 20px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive Design */
@media (max-width: 768px) {
    .reports-container {
        padding: 15px;
    }
    
    .reports-header {
        padding: 20px;
    }
    
    .reports-header h1 {
        font-size: 2rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .filter-actions {
        justify-content: stretch;
    }
    
    .filter-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .summary-card {
        padding: 20px 15px;
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .card-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .card-content h3 {
        font-size: 1.8rem;
    }
    
    .records-table {
        font-size: 12px;
    }
    
    .records-table th,
    .records-table td {
        padding: 10px 8px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .reports-header h1 {
        font-size: 1.8rem;
    }
    
    .reports-header p {
        font-size: 1rem;
    }
}
</style>

<?php
$page_content = ob_get_clean();
require_once 'layout.php';
?>
