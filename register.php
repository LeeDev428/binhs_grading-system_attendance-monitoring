<?php
require_once 'config.php';

$error = '';
$success = '';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password, role) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $full_name, $hashed_password, $role])) {
                    $success = 'Account created successfully! You can now sign in.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BINHS Grading System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="logo">
                <h1 class="system-title">Student Grades and Attendance System</h1>
                <p class="system-subtitle">Personalized education management for teachers, administrators, and students. Effortless tracking of classes, grades, attendance, and more.</p>
                <ul class="features">
                    <li>Real-time grade tracking</li>
                    <li>Attendance monitoring</li>
                    <li>Student progress reports</li>
                    <li>Secure data management</li>
                </ul>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="form-container">
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Join our education management system</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="register.php">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
                
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.classList.add('loading');
            button.disabled = true;
            button.textContent = 'Creating Account...';
        });
        
        // Password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
