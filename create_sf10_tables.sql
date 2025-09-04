-- SF10 Forms table
CREATE TABLE IF NOT EXISTS sf10_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    lrn VARCHAR(50),
    grade_level VARCHAR(20),
    section VARCHAR(50),
    track_strand VARCHAR(100),
    age INT NULL,
    sex ENUM('Male', 'Female') NULL,
    remedial_period VARCHAR(100),
    remedial_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_year (student_id, school_year)
);

-- SF10 Remedial Grades table
CREATE TABLE IF NOT EXISTS sf10_remedial_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sf10_form_id INT NOT NULL,
    subject_id INT NOT NULL,
    original_grade DECIMAL(5,2),
    remedial_grade DECIMAL(5,2),
    action_taken ENUM('PASSED', 'FAILED'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sf10_form_id) REFERENCES sf10_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sf10_subject (sf10_form_id, subject_id)
);
