-- Additional tables for student grades and subjects
USE `binhs_grading-system_attendance-monitoring_db`;

-- Create subjects table
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(100) NOT NULL,
  `subject_code` VARCHAR(20),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert the subjects you mentioned
INSERT INTO `subjects` (`subject_name`, `subject_code`) VALUES 
('21st Century Literature', '21CL'),
('Earth and Life Science', 'ELS'),
('General Mathematics', 'GENMATH'),
('Komunikasyon at Pananaliksik', 'KP'),
('Oral Communication', 'OC'),
('HOPE 1', 'HOPE1'),
('Business Mathematics', 'BUSMATH'),
('Organization and Management', 'OM');

-- Create students table
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_number` VARCHAR(20) UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `middle_name` VARCHAR(50),
  `grade_level` VARCHAR(20),
  `section` VARCHAR(50),
  `school_year` VARCHAR(20),
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
);

-- Create student_grades table
CREATE TABLE IF NOT EXISTS `student_grades` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `quarter_1` DECIMAL(5,2) DEFAULT 0.00,
  `quarter_2` DECIMAL(5,2) DEFAULT 0.00,
  `quarter_3` DECIMAL(5,2) DEFAULT 0.00,
  `quarter_4` DECIMAL(5,2) DEFAULT 0.00,
  `final_grade` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('PASSED', 'FAILED') DEFAULT 'PASSED',
  `remarks` TEXT,
  `school_year` VARCHAR(20),
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  UNIQUE KEY `unique_student_subject_year` (`student_id`, `subject_id`, `school_year`)
);

-- Create honors table
CREATE TABLE IF NOT EXISTS `student_honors` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `honor_type` ENUM('With Highest Honors', 'With High Honors', 'With Honors') NOT NULL,
  `general_average` DECIMAL(5,2) NOT NULL,
  `school_year` VARCHAR(20) NOT NULL,
  `quarter` ENUM('Q1', 'Q2', 'Q3', 'Q4', 'Final') DEFAULT 'Final',
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
);
