<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$grade_level = $_GET['grade_level'] ?? '';
$student_id = $_GET['student_id'] ?? null;

if (empty($grade_level)) {
    echo json_encode(['error' => 'Grade level is required']);
    exit;
}

try {
    // Get first semester subjects for this grade level
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE grade_level = ? AND is_first_sem = 1 ORDER BY subject_type, subject_name");
    $stmt->execute([$grade_level]);
    $first_semester_subjects = $stmt->fetchAll();
    
    // Get second semester subjects for this grade level  
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE grade_level = ? AND is_second_sem = 1 ORDER BY subject_type, subject_name");
    $stmt->execute([$grade_level]);
    $second_semester_subjects = $stmt->fetchAll();
    
    // Get existing grades if editing a student
    $existing_grades = [];
    if ($student_id) {
        $stmt = $pdo->prepare("
            SELECT sg.*, s.subject_name, s.subject_type 
            FROM student_grades sg 
            JOIN subjects s ON sg.subject_id = s.id 
            WHERE sg.student_id = ? AND s.grade_level = ?
        ");
        $stmt->execute([$student_id, $grade_level]);
        $grades = $stmt->fetchAll();
        
        foreach ($grades as $grade) {
            $existing_grades[$grade['subject_id']] = $grade;
        }
    }
    
    echo json_encode([
        'success' => true,
        'first_semester_subjects' => $first_semester_subjects,
        'second_semester_subjects' => $second_semester_subjects,
        'existing_grades' => $existing_grades
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>