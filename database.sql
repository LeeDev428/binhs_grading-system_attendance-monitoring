-- Create database for BINHS Grading System & Attendance Monitoring
CREATE DATABASE IF NOT EXISTS `binhs_grading-system_attendance-monitoring_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `binhs_grading-system_attendance-monitoring_db`;

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert a default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`) VALUES 
('admin', 'admin@binhs.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Create sessions table for user sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
