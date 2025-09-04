-- BINHS Barcode Attendance System Database Setup
-- Run this script to add barcode functionality to your existing database

USE `binhs_grading-system_attendance-monitoring_db`;

-- Add barcode column to students table (if not exists)
ALTER TABLE `students` ADD COLUMN `barcode` VARCHAR(50) UNIQUE AFTER `student_number`;

-- Create daily attendance table for barcode scanning
CREATE TABLE IF NOT EXISTS `daily_attendance` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `barcode` VARCHAR(50) NOT NULL,
  `scan_date` DATE NOT NULL,
  `scan_time` TIME NOT NULL,
  `scan_datetime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('present', 'late') DEFAULT 'present',
  `notes` TEXT,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_student_date` (`student_id`, `scan_date`)
);

-- Sample data: Generate barcodes for existing students (optional)
-- UPDATE students SET barcode = CONCAT('BINHS-', student_number, '-', YEAR(CURDATE()), '-', SUBSTRING(MD5(RAND()), 1, 4)) WHERE barcode IS NULL;

SELECT 'Barcode attendance system database setup completed!' as message;
