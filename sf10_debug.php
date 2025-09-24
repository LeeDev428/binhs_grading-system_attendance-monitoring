<?php
require_once 'config.php';

echo "=== SF10 FORM DEBUG ===\n";

// Check if we have a student to test with
$stmt = $pdo->query("SELECT id, first_name, last_name, grade_level, school_year FROM students LIMIT 1");
$student = $stmt->fetch();

if ($student) {
    echo "Testing with student: {$student['first_name']} {$student['last_name']}\n";
    echo "Student ID: {$student['id']}\n";
    echo "Grade Level: {$student['grade_level']}\n";
    echo "School Year: {$student['school_year']}\n\n";
    
    // Check first semester subjects for this student
    echo "=== FIRST SEMESTER SUBJECTS FOR THIS STUDENT ===\n";
    $stmt = $pdo->prepare("
        SELECT s.id, s.subject_name, s.grade_level, s.is_first_sem
        FROM subjects s
        WHERE s.grade_level = ? AND s.is_first_sem = 1
        ORDER BY s.subject_name
    ");
    $stmt->execute([$student['grade_level']]);
    $first_sem = $stmt->fetchAll();
    
    echo "Found " . count($first_sem) . " first semester subjects for grade level '{$student['grade_level']}':\n";
    foreach ($first_sem as $subj) {
        echo "- ID: {$subj['id']}, Name: {$subj['subject_name']}, Grade Level: {$subj['grade_level']}\n";
    }
    
    // Check second semester subjects for this student
    echo "\n=== SECOND SEMESTER SUBJECTS FOR THIS STUDENT ===\n";
    $stmt = $pdo->prepare("
        SELECT s.id, s.subject_name, s.grade_level, s.is_second_sem
        FROM subjects s
        WHERE s.grade_level = ? AND s.is_second_sem = 1
        ORDER BY s.subject_name
    ");
    $stmt->execute([$student['grade_level']]);
    $second_sem = $stmt->fetchAll();
    
    echo "Found " . count($second_sem) . " second semester subjects for grade level '{$student['grade_level']}':\n";
    foreach ($second_sem as $subj) {
        echo "- ID: {$subj['id']}, Name: {$subj['subject_name']}, Grade Level: {$subj['grade_level']}\n";
    }
    
    // Check all subjects with semester flags
    echo "\n=== ALL SUBJECTS WITH SEMESTER FLAGS ===\n";
    $stmt = $pdo->query("SELECT id, subject_name, grade_level, is_first_sem, is_second_sem FROM subjects WHERE is_first_sem = 1 OR is_second_sem = 1");
    $sem_subjects = $stmt->fetchAll();
    
    echo "All subjects with semester assignments:\n";
    foreach ($sem_subjects as $subj) {
        echo sprintf("- ID: %2d, Name: %-20s, Grade: %-10s, 1st: %s, 2nd: %s\n", 
            $subj['id'], 
            $subj['subject_name'], 
            $subj['grade_level'] ?: 'NULL',
            $subj['is_first_sem'] ? 'Yes' : 'No',
            $subj['is_second_sem'] ? 'Yes' : 'No'
        );
    }
    
    // Check if the problem is grade_level filtering
    echo "\n=== POTENTIAL ISSUE ANALYSIS ===\n";
    if (count($second_sem) == 0) {
        echo "PROBLEM: No second semester subjects found for student's grade level!\n";
        echo "Student grade level: '{$student['grade_level']}'\n";
        
        // Check what grade levels are assigned to semester subjects
        $stmt = $pdo->query("SELECT DISTINCT grade_level FROM subjects WHERE is_second_sem = 1");
        $grade_levels = $stmt->fetchAll();
        echo "Grade levels assigned to second semester subjects: ";
        foreach ($grade_levels as $gl) {
            echo "'{$gl['grade_level']}' ";
        }
        echo "\n";
    }
    
} else {
    echo "No students found in database!\n";
}
?>