<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'BINHS Grading System'; ?></title>
    <!-- Bootstrap CSS - Load FIRST so main_layout.css can override it -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Layout CSS - Load LAST to override Bootstrap -->
    <link rel="stylesheet" href="main_layout.css">
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>BINHS</span>
                </div>
                <div class="system-name">Grading & Attendance System</div>
            </div>
            
            <div class="sidebar-menu">
                <ul class="menu-list">
                    <li class="menu-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="menu-item has-submenu <?php echo (in_array($current_page, ['student_grades', 'view_grades', 'grade_reports'])) ? 'active' : ''; ?>">
                        <a href="#" class="menu-toggle">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Grade Management</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="student_grades.php" class="<?php echo ($current_page == 'student_grades') ? 'active' : ''; ?>">
                                <i class="fas fa-plus-circle"></i>Input Student Grades</a></li>
                            <li><a href="view_grades.php" class="<?php echo ($current_page == 'view_grades') ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i>View All Grades</a></li>
                            <li><a href="manage_subjects.php" class="<?php echo ($current_page == 'manage_subjects') ? 'active' : ''; ?>">
                                <i class="fas fa-book"></i>Manage Subjects</a></li>
                        </ul>
                    </li>
                    
                    <li class="menu-item has-submenu <?php echo (in_array($current_page, ['attendance_scanner', 'attendance', 'attendance_reports'])) ? 'active' : ''; ?>">
                        <a href="#" class="menu-toggle">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance Tracking</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </a>
                        <ul class="submenu">
                            <li><a href="attendance_scanner.php" class="<?php echo ($current_page == 'attendance_scanner') ? 'active' : ''; ?>">
                                <i class="fas fa-qrcode"></i>Manual <br>Scanning</a></li>
                            <li><a href="attendance_reports.php" class="<?php echo ($current_page == 'attendance_reports') ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line"></i>Attendance Reports</a></li>
                        </ul>
                    </li>
                    
               
                    
                
                    
                
                    
                   
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'user'); ?></div>
                    </div>
                </div>
                <a href="dashboard.php?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                       
                        
                        <div class="user-menu">
                            <button class="header-btn user-btn">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown">
                                <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                                <hr>
                                <a href="dashboard.php?logout=1"><i class="fas fa-sign-out-alt"></i>Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
                <?php echo $page_content ?? ''; ?>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Main Layout JS -->
    <script src="main_layout.js"></script>
</body>
</html>
