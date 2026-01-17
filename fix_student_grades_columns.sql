-- Fix student_grades table to explicitly allow NULL values for grade columns
-- This prevents "Data truncated" warnings when inserting NULL values

USE `binhs_grading-system_attendance-monitoring_db`;

ALTER TABLE `student_grades` 
MODIFY COLUMN `quarter_1` decimal(5,2) DEFAULT NULL,
MODIFY COLUMN `quarter_2` decimal(5,2) DEFAULT NULL,
MODIFY COLUMN `quarter_3` decimal(5,2) DEFAULT NULL,
MODIFY COLUMN `quarter_4` decimal(5,2) DEFAULT NULL,
MODIFY COLUMN `final_grade` decimal(5,2) DEFAULT NULL,
MODIFY COLUMN `status` enum('PASSED','FAILED') COLLATE utf8mb4_unicode_ci DEFAULT NULL;
