<?php
require_once 'config.php';

$error = '';
$success = '';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // First try to get user data with full_name, fallback if column doesn't exist
            try {
                $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                // If full_name column doesn't exist, query without it
                $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                if ($user) {
                    $user['full_name'] = $user['username']; // Use username as fallback
                }
            }
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                
                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BINHS Grading System</title>
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
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your account</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
                
                <div class="form-footer">
                    <p>Don't have an account? <a href="register.php">Create one here</a></p>
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
            button.textContent = 'Signing In...';
        });
    </script>
</body>
</html>
