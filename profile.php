<?php
require_once 'config.php';

// Set page variables for layout
$page_title = 'Profile';
$current_page = 'profile';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check for success parameter
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Profile updated successfully!";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a profile update (has the profile fields)
    if (isset($_POST['full_name']) && isset($_POST['email']) && isset($_POST['username'])) {
        try {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            
            // Validate inputs
            if (empty($full_name) || empty($email) || empty($username)) {
                $error = "All fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                // Check if email or username already exists for other users
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
                $check_stmt->execute([$email, $username, $user_id]);
                
                if ($check_stmt->fetch()) {
                    $error = "Email or username already exists.";
                } else {
                    // Update profile
                    $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ?, updated_at = NOW() WHERE id = ?");
                    $result = $update_stmt->execute([$full_name, $email, $username, $user_id]);
                    
                    if ($result) {
                        // Update session data
                        $_SESSION['username'] = $username;
                        $_SESSION['full_name'] = $full_name;
                        
                        // Force redirect to prevent form resubmission
                        header("Location: profile.php?updated=1");
                        exit();
                    } else {
                        $error = "Failed to update profile. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Check if this is a password change (has password fields)
    elseif (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validate password inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_pass = $stmt->fetch();
                
                if (!$user_pass || !password_verify($current_password, $user_pass['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $result = $update_stmt->execute([$hashed_password, $user_id]);
                    
                    if ($result) {
                        header("Location: profile.php?password_changed=1");
                        exit();
                    } else {
                        $error = "Failed to change password. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Check for update messages
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = "Profile updated successfully!";
}
if (isset($_GET['password_changed']) && $_GET['password_changed'] == '1') {
    $message = "Password changed successfully!";
}

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT username, email, full_name, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error fetching user data: " . $e->getMessage();
}

// Start output buffering to capture content
ob_start();
?>

<style>
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .profile-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .card-header {
        background: #2c3e50;
        color: white;
        padding: 15px 20px;
        margin: 0;
        font-size: 18px;
        font-weight: 500;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #2c3e50;
    }
    
    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }
    
    .btn {
        background: #3498db;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.3s ease;
    }
    
    .btn:hover {
        background: #2980b9;
    }
    
    .btn-danger {
        background: #e74c3c;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .alert {
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .info-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .info-table th, .info-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .info-table th {
        background: #f8f9fa;
        font-weight: 500;
        color: #2c3e50;
        width: 150px;
    }
    
    .form-row {
        display: flex;
        gap: 15px;
    }
    
    .form-row .form-group {
        flex: 1;
    }
    
    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }
        
        .profile-container {
            padding: 10px;
        }
    }
</style>

<div class="profile-container">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Debug Information (remove this after testing) -->
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
            <strong>Debug Info:</strong><br>
            Form submitted: <?php 
                if (isset($_POST['update_profile'])) {
                    echo 'Profile Update (update_profile=' . $_POST['update_profile'] . ')';
                } elseif (isset($_POST['change_password'])) {
                    echo 'Password Change (change_password=' . $_POST['change_password'] . ')';
                } else {
                    echo 'Unknown - Available POST keys: ' . implode(', ', array_keys($_POST));
                }
            ?><br>
            User ID: <?php echo $user_id; ?><br>
            POST data: <?php echo json_encode($_POST); ?>
        </div>
    <?php endif; ?>
    
    <!-- Account Information -->
    <div class="profile-card">
        <h2 class="card-header">Account Information</h2>
        <div class="card-body">
            <table class="info-table">
                <tr>
                    <th>User ID:</th>
                    <td><?php echo htmlspecialchars($user_id); ?></td>
                </tr>
                <tr>
                    <th>Username:</th>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                </tr>
                <tr>
                    <th>Full Name:</th>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                </tr>
                <tr>
                    <th>Role:</th>
                    <td><span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
                </tr>
                <tr>
                    <th>Member Since:</th>
                    <td><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Edit Profile -->
    <div class="profile-card">
        <h2 class="card-header">Edit Profile</h2>
        <div class="card-body">
            <form method="POST" action="profile.php" id="profileForm">
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                
                <button type="submit" name="update_profile" value="1" class="btn">Update Profile</button>
            </form>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="profile-card">
        <h2 class="card-header">Change Password</h2>
        <div class="card-body">
            <form method="POST" action="profile.php" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <button type="submit" name="change_password" value="1" class="btn btn-danger">Change Password</button>
            </form>
        </div>
    </div>
</div>

<script>
    // Add form validation and feedback
    document.addEventListener('DOMContentLoaded', function() {
        const profileForm = document.getElementById('profileForm');
        const passwordForm = document.getElementById('passwordForm');
        
        // Profile form validation
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const requiredFields = profileForm.querySelectorAll('input[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                        field.style.backgroundColor = '#fdf2f2';
                    } else {
                        field.style.borderColor = '#ddd';
                        field.style.backgroundColor = '#fff';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                // Show loading state
                const submitBtn = profileForm.querySelector('button[type="submit"]');
                submitBtn.textContent = 'Updating...';
                submitBtn.disabled = true;
            });
        }
        
        // Password form validation
        if (passwordForm) {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.style.borderColor = '#e74c3c';
                        confirmPassword.style.backgroundColor = '#fdf2f2';
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.style.borderColor = '#27ae60';
                        confirmPassword.style.backgroundColor = '#f8fff8';
                        confirmPassword.setCustomValidity('');
                    }
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
            
            passwordForm.addEventListener('submit', function(e) {
                const requiredFields = passwordForm.querySelectorAll('input[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                        field.style.backgroundColor = '#fdf2f2';
                    } else {
                        field.style.borderColor = '#ddd';
                        field.style.backgroundColor = '#fff';
                    }
                });
                
                if (newPassword.value !== confirmPassword.value) {
                    isValid = false;
                    alert('Passwords do not match!');
                }
                
                if (newPassword.value.length < 6) {
                    isValid = false;
                    alert('Password must be at least 6 characters long!');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                const submitBtn = passwordForm.querySelector('button[type="submit"]');
                submitBtn.textContent = 'Changing Password...';
                submitBtn.disabled = true;
            });
        }
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
    });
</script>

<?php
// Capture the content and store it in $page_content
$page_content = ob_get_clean();

// Include the layout
include 'layout.php';
?>
