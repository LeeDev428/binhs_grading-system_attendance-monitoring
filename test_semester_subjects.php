<?php
require_once 'config.php';

echo "<h2>Subject Semester Assignment Test</h2>";

try {
    $stmt = $pdo->query("SELECT id, subject_name, subject_code, subject_type, is_first_sem, is_second_sem FROM subjects ORDER BY subject_type, subject_name");
    $all_subjects = $stmt->fetchAll();
    
    // Organize subjects by semester
    $first_semester_subjects = [];
    $second_semester_subjects = [];
    
    foreach ($all_subjects as $subject) {
        if ($subject['is_first_sem'] == 1) {
            $first_semester_subjects[] = $subject;
        }
        if ($subject['is_second_sem'] == 1) {
            $second_semester_subjects[] = $subject;
        }
    }
    
    echo "<h3>First Semester Subjects (" . count($first_semester_subjects) . " subjects)</h3>";
    if (empty($first_semester_subjects)) {
        echo "<p style='color: red;'><strong>NO SUBJECTS ASSIGNED TO FIRST SEMESTER!</strong></p>";
        echo "<p>This means no subjects have is_first_sem = 1</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>1st Sem</th><th>2nd Sem</th></tr>";
        foreach ($first_semester_subjects as $subject) {
            echo "<tr>";
            echo "<td>" . $subject['id'] . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_code']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_type']) . "</td>";
            echo "<td>" . ($subject['is_first_sem'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($subject['is_second_sem'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Second Semester Subjects (" . count($second_semester_subjects) . " subjects)</h3>";
    if (empty($second_semester_subjects)) {
        echo "<p style='color: red;'><strong>NO SUBJECTS ASSIGNED TO SECOND SEMESTER!</strong></p>";
        echo "<p>This means no subjects have is_second_sem = 1</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>1st Sem</th><th>2nd Sem</th></tr>";
        foreach ($second_semester_subjects as $subject) {
            echo "<tr>";
            echo "<td>" . $subject['id'] . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_code']) . "</td>";
            echo "<td>" . htmlspecialchars($subject['subject_type']) . "</td>";
            echo "<td>" . ($subject['is_first_sem'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($subject['is_second_sem'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>All Subjects (for reference)</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>1st Sem</th><th>2nd Sem</th></tr>";
    foreach ($all_subjects as $subject) {
        echo "<tr>";
        echo "<td>" . $subject['id'] . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_code']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_type']) . "</td>";
        echo "<td style='color: " . ($subject['is_first_sem'] ? 'green' : 'red') . ";'>" . ($subject['is_first_sem'] ? 'Yes' : 'No') . "</td>";
        echo "<td style='color: " . ($subject['is_second_sem'] ? 'green' : 'red') . ";'>" . ($subject['is_second_sem'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>