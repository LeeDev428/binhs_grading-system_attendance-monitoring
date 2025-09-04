-- SF9 Database Tables for BINHS Grading System
-- Create these tables in your database

-- Enhanced Subjects table for SF9
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(255) NOT NULL,
    subject_code VARCHAR(50) UNIQUE NOT NULL,
    grade_level ENUM('Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12') NOT NULL,
    subject_type ENUM('CORE', 'APPLIED', 'SPECIALIZED') DEFAULT 'CORE',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grade_level (grade_level),
    INDEX idx_subject_type (subject_type),
    INDEX idx_active (is_active)
);

-- Table for SF9 Form Master Data
CREATE TABLE IF NOT EXISTS sf9_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    lrn VARCHAR(50),
    age INT,
    sex ENUM('Male', 'Female'),
    grade_level VARCHAR(20),
    section VARCHAR(50),
    track_strand TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_year (student_id, school_year)
);

-- Table for SF9 Attendance Records
CREATE TABLE IF NOT EXISTS sf9_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    month ENUM('Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul') NOT NULL,
    school_days INT DEFAULT 0,
    days_present INT DEFAULT 0,
    days_absent INT DEFAULT 0,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_month (sf9_form_id, month)
);

-- Table for SF9 Subject Grades (First Semester)
CREATE TABLE IF NOT EXISTS sf9_grades_first_sem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    subject_type ENUM('CORE', 'APPLIED', 'SPECIALIZED') NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    quarter_1 DECIMAL(5,2) DEFAULT NULL,
    quarter_2 DECIMAL(5,2) DEFAULT NULL,
    semester_final_grade DECIMAL(5,2) DEFAULT NULL,
    remarks VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE
);

-- Table for SF9 Subject Grades (Second Semester)
CREATE TABLE IF NOT EXISTS sf9_grades_second_sem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    subject_type ENUM('CORE', 'APPLIED', 'SPECIALIZED') NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    quarter_3 DECIMAL(5,2) DEFAULT NULL,
    quarter_4 DECIMAL(5,2) DEFAULT NULL,
    semester_final_grade DECIMAL(5,2) DEFAULT NULL,
    remarks VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE
);

-- Table for SF9 Core Values Assessment
CREATE TABLE IF NOT EXISTS sf9_core_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    core_value ENUM('Maka-Diyos', 'Makatao', 'Makakalikasan', 'Makabansa') NOT NULL,
    quarter_1 ENUM('AO', 'SO', 'RO', 'NO') DEFAULT NULL,
    quarter_2 ENUM('AO', 'SO', 'RO', 'NO') DEFAULT NULL,
    quarter_3 ENUM('AO', 'SO', 'RO', 'NO') DEFAULT NULL,
    quarter_4 ENUM('AO', 'SO', 'RO', 'NO') DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_value (sf9_form_id, core_value)
);

-- Table for SF9 General Average per Semester
CREATE TABLE IF NOT EXISTS sf9_general_average (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    semester ENUM('First', 'Second') NOT NULL,
    general_average DECIMAL(5,2) DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_semester (sf9_form_id, semester)
);

-- Table for SF9 Parent/Guardian Signatures
CREATE TABLE IF NOT EXISTS sf9_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    signature_type ENUM('1st_quarter', '2nd_quarter', '3rd_quarter', '4th_quarter') NOT NULL,
    signature_data TEXT DEFAULT NULL,
    signed_date DATE DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_signature (sf9_form_id, signature_type)
);

-- Table for SF9 Certificate of Transfer Data
CREATE TABLE IF NOT EXISTS sf9_transfer_certificate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf9_form_id INT NOT NULL,
    admitted_to_grade VARCHAR(50) DEFAULT NULL,
    admitted_to_section VARCHAR(50) DEFAULT NULL,
    eligibility_for_admission VARCHAR(100) DEFAULT NULL,
    admitted_in_date DATE DEFAULT NULL,
    principal_name VARCHAR(100) DEFAULT 'OLIVER P. CALIWAG',
    class_adviser_name VARCHAR(100) DEFAULT 'ARNOLD M. ARANAYDO',
    cancellation_date DATE DEFAULT NULL,
    cancellation_reason TEXT DEFAULT NULL,
    FOREIGN KEY (sf9_form_id) REFERENCES sf9_forms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_transfer (sf9_form_id)
);

-- Insert comprehensive subjects for Grade 11 (based on the SF9 form images)
INSERT IGNORE INTO subjects (subject_name, subject_code, grade_level, subject_type) VALUES

-- FIRST SEMESTER SUBJECTS
-- Core Subjects (First Semester)
('Oral Communication in Context', 'CORE-OCC', 'Grade 11', 'CORE'),
('Komunikasyon at Pananaliksik sa Wika at Kulturang Pilipino', 'CORE-KOM', 'Grade 11', 'CORE'),
('21st Century Literature from the Philippines and the World', 'CORE-LIT21', 'Grade 11', 'CORE'),
('General Mathematics', 'CORE-GMATH', 'Grade 11', 'CORE'),
('Statistics and Probability', 'CORE-STAT', 'Grade 11', 'CORE'),
('Earth and Life Science', 'CORE-ELS', 'Grade 11', 'CORE'),
('Physical Science', 'CORE-PS', 'Grade 11', 'CORE'),
('Personal Development', 'CORE-PD', 'Grade 11', 'CORE'),
('Physical Education and Health 1', 'CORE-PE1', 'Grade 11', 'CORE'),

-- Applied Subjects (First Semester)
('Empowerment Technologies', 'APPLIED-EMPTECH', 'Grade 11', 'APPLIED'),

-- Specialized Subjects - ABM Track (First Semester)
('Business Mathematics', 'SPEC-BUSMATH', 'Grade 11', 'SPECIALIZED'),
('Fundamentals of Accountancy, Business and Management 1', 'SPEC-FABM1', 'Grade 11', 'SPECIALIZED'),
('Organization and Management', 'SPEC-OM', 'Grade 11', 'SPECIALIZED'),

-- SECOND SEMESTER SUBJECTS
-- Core Subjects (Second Semester)
('Reading and Writing Skills', 'CORE-RWS', 'Grade 11', 'CORE'),
('Pagbasa at Pagsusuri ng Iba''t Ibang Teksto Tungo sa Pananaliksik', 'CORE-PAGBASA', 'Grade 11', 'CORE'),
('Physical Education and Health 2', 'CORE-PE2', 'Grade 11', 'CORE'),

-- Applied Subjects (Second Semester)
('Practical Research 1', 'APPLIED-PR1', 'Grade 11', 'APPLIED'),

-- Specialized Subjects - ABM Track (Second Semester)
('Applied Economics', 'SPEC-APPECON', 'Grade 11', 'SPECIALIZED'),
('Business Ethics and Social Responsibility', 'SPEC-BESR', 'Grade 11', 'SPECIALIZED'),
('Fundamentals of Accountancy, Business and Management 2', 'SPEC-FABM2', 'Grade 11', 'SPECIALIZED'),
('Principles of Marketing', 'SPEC-PM', 'Grade 11', 'SPECIALIZED'),

-- Additional Grade 11 Subjects for other tracks (commonly found in SF9)
-- STEM Track Subjects
('Pre-Calculus', 'SPEC-PRECAL', 'Grade 11', 'SPECIALIZED'),
('Basic Calculus', 'SPEC-BASICCAL', 'Grade 11', 'SPECIALIZED'),
('General Biology 1', 'SPEC-GENBIO1', 'Grade 11', 'SPECIALIZED'),
('General Biology 2', 'SPEC-GENBIO2', 'Grade 11', 'SPECIALIZED'),
('General Chemistry 1', 'SPEC-GENCHEM1', 'Grade 11', 'SPECIALIZED'),
('General Chemistry 2', 'SPEC-GENCHEM2', 'Grade 11', 'SPECIALIZED'),
('General Physics 1', 'SPEC-GENPHY1', 'Grade 11', 'SPECIALIZED'),
('General Physics 2', 'SPEC-GENPHY2', 'Grade 11', 'SPECIALIZED'),

-- HUMSS Track Subjects
('Creative Writing', 'SPEC-CRWRITE', 'Grade 11', 'SPECIALIZED'),
('Introduction to World Religions and Belief Systems', 'SPEC-WRBS', 'Grade 11', 'SPECIALIZED'),
('Creative Nonfiction', 'SPEC-CRNF', 'Grade 11', 'SPECIALIZED'),
('Disciplines and Ideas in the Social Sciences', 'SPEC-DISS', 'Grade 11', 'SPECIALIZED'),

-- GAS Track Core Subjects
('Humanities 1', 'SPEC-HUM1', 'Grade 11', 'SPECIALIZED'),
('Humanities 2', 'SPEC-HUM2', 'Grade 11', 'SPECIALIZED'),
('Social Science 1', 'SPEC-SOCSCI1', 'Grade 11', 'SPECIALIZED'),
('Social Science 2', 'SPEC-SOCSCI2', 'Grade 11', 'SPECIALIZED'),
('Applied Economics', 'SPEC-APPLIEDECON', 'Grade 11', 'SPECIALIZED'),

-- TVL Track Common Subjects
('Entrepreneurship', 'SPEC-ENTREP', 'Grade 11', 'SPECIALIZED'),
('English for Academic and Professional Purposes', 'SPEC-EAPP', 'Grade 11', 'SPECIALIZED');
