-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for binhs_grading-system_attendance-monitoring_db
CREATE DATABASE IF NOT EXISTS `binhs_grading-system_attendance-monitoring_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `binhs_grading-system_attendance-monitoring_db`;

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.daily_attendance
CREATE TABLE IF NOT EXISTS `daily_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scan_date` date NOT NULL,
  `scan_time` time NOT NULL,
  `scan_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('present','late') COLLATE utf8mb4_unicode_ci DEFAULT 'present',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_date` (`student_id`,`scan_date`),
  CONSTRAINT `daily_attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.daily_reset_log
CREATE TABLE IF NOT EXISTS `daily_reset_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reset_date` date NOT NULL,
  `reset_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `students_reset` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`reset_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf10_forms
CREATE TABLE IF NOT EXISTS `sf10_forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lrn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_strand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` int DEFAULT NULL,
  `sex` enum('Male','Female') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remedial_period` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remedial_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_year` (`student_id`,`school_year`),
  CONSTRAINT `sf10_forms_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf10_remedial_grades
CREATE TABLE IF NOT EXISTS `sf10_remedial_grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf10_form_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `original_grade` decimal(5,2) DEFAULT NULL,
  `remedial_grade` decimal(5,2) DEFAULT NULL,
  `action_taken` enum('PASSED','FAILED') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sf10_subject` (`sf10_form_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `sf10_remedial_grades_ibfk_1` FOREIGN KEY (`sf10_form_id`) REFERENCES `sf10_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sf10_remedial_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_attendance
CREATE TABLE IF NOT EXISTS `sf9_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `student_id` int NOT NULL,
  `month` enum('Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul') COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_days` int DEFAULT '0',
  `days_present` int DEFAULT '0',
  `days_absent` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_month` (`sf9_form_id`,`month`),
  CONSTRAINT `sf9_attendance_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_core_values
CREATE TABLE IF NOT EXISTS `sf9_core_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `core_value` enum('Maka-Diyos','Makatao','Makakalikasan','Makabansa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter_1` enum('AO','SO','RO','NO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quarter_2` enum('AO','SO','RO','NO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quarter_3` enum('AO','SO','RO','NO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quarter_4` enum('AO','SO','RO','NO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_value` (`sf9_form_id`,`core_value`),
  CONSTRAINT `sf9_core_values_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_forms
CREATE TABLE IF NOT EXISTS `sf9_forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lrn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` int DEFAULT NULL,
  `sex` enum('Male','Female') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_strand` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_year` (`student_id`,`school_year`),
  CONSTRAINT `sf9_forms_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_general_average
CREATE TABLE IF NOT EXISTS `sf9_general_average` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `semester` enum('First','Second') COLLATE utf8mb4_unicode_ci NOT NULL,
  `general_average` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_semester` (`sf9_form_id`,`semester`),
  CONSTRAINT `sf9_general_average_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_grades_first_sem
CREATE TABLE IF NOT EXISTS `sf9_grades_first_sem` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `subject_type` enum('CORE','APPLIED','SPECIALIZED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter_1` decimal(5,2) DEFAULT NULL,
  `quarter_2` decimal(5,2) DEFAULT NULL,
  `semester_final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sf9_form_id` (`sf9_form_id`),
  CONSTRAINT `sf9_grades_first_sem_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_grades_second_sem
CREATE TABLE IF NOT EXISTS `sf9_grades_second_sem` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `subject_type` enum('CORE','APPLIED','SPECIALIZED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter_3` decimal(5,2) DEFAULT NULL,
  `quarter_4` decimal(5,2) DEFAULT NULL,
  `semester_final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sf9_form_id` (`sf9_form_id`),
  CONSTRAINT `sf9_grades_second_sem_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_records
CREATE TABLE IF NOT EXISTS `sf9_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter` int NOT NULL,
  `days_present` int DEFAULT '0',
  `days_absent` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_quarter` (`student_id`,`school_year`,`quarter`),
  CONSTRAINT `sf9_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_signatures
CREATE TABLE IF NOT EXISTS `sf9_signatures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `signature_type` enum('1st_quarter','2nd_quarter','3rd_quarter','4th_quarter') COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature_data` text COLLATE utf8mb4_unicode_ci,
  `signed_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_signature` (`sf9_form_id`,`signature_type`),
  CONSTRAINT `sf9_signatures_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.sf9_transfer_certificate
CREATE TABLE IF NOT EXISTS `sf9_transfer_certificate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sf9_form_id` int NOT NULL,
  `admitted_to_grade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admitted_to_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eligibility_for_admission` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admitted_in_date` date DEFAULT NULL,
  `principal_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'OLIVER P. CALIWAG',
  `class_adviser_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'ARNOLD M. ARANAYDO',
  `cancellation_date` date DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_form_transfer` (`sf9_form_id`),
  CONSTRAINT `sf9_transfer_certificate_ibfk_1` FOREIGN KEY (`sf9_form_id`) REFERENCES `sf9_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.students
CREATE TABLE IF NOT EXISTS `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_scanned` tinyint(1) DEFAULT '0',
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.student_grades
CREATE TABLE IF NOT EXISTS `student_grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `quarter_1` decimal(5,2) DEFAULT NULL,
  `quarter_2` decimal(5,2) DEFAULT NULL,
  `quarter_3` decimal(5,2) DEFAULT NULL,
  `quarter_4` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `status` enum('PASSED','FAILED') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_subject_year` (`student_id`,`subject_id`,`school_year`),
  KEY `subject_id` (`subject_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `student_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `student_grades_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=676 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.student_honors
CREATE TABLE IF NOT EXISTS `student_honors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `honor_type` enum('With Highest Honors','With High Honors','With Honors') COLLATE utf8mb4_unicode_ci NOT NULL,
  `general_average` decimal(5,2) NOT NULL,
  `school_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter` enum('Q1','Q2','Q3','Q4','Final') COLLATE utf8mb4_unicode_ci DEFAULT 'Final',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `student_honors_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_honors_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.subjects
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade_level` enum('Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` enum('CORE','APPLIED','SPECIALIZED') COLLATE utf8mb4_unicode_ci DEFAULT 'CORE',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_first_sem` tinyint(1) DEFAULT '0',
  `is_second_sem` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `idx_grade_level` (`grade_level`),
  KEY `idx_subject_type` (`subject_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher','student') COLLATE utf8mb4_unicode_ci DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table binhs_grading-system_attendance-monitoring_db.user_sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
