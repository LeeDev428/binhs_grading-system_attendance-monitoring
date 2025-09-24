<?php
require_once 'config.php';

echo "<h2>All Subjects Debug</h2>";

// Get all subjects
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_type, subject_name");
$all_subjects = $stmt->fetchAll();

echo "<h3>All Subjects:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Subject Name</th><th>Type</th><th>Grade Level</th><th>is_first_sem</th><th>is_second_sem</th></tr>";
foreach ($all_subjects as $subject) {
    echo "<tr>";
    echo "<td>{$subject['id']}</td>";
    echo "<td>{$subject['subject_name']}</td>";
    echo "<td>{$subject['subject_type']}</td>";
    echo "<td>{$subject['grade_level']}</td>";
    echo "<td>{$subject['is_first_sem']}</td>";
    echo "<td>{$subject['is_second_sem']}</td>";
    echo "</tr>";
}
echo "</table>";

// Get first semester subjects
$stmt = $pdo->query("SELECT * FROM subjects WHERE is_first_sem = 1 ORDER BY subject_type, subject_name");
$first_sem = $stmt->fetchAll();

echo "<h3>First Semester Subjects (is_first_sem = 1):</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Subject Name</th><th>Type</th></tr>";
foreach ($first_sem as $subject) {
    echo "<tr>";
    echo "<td>{$subject['id']}</td>";
    echo "<td>{$subject['subject_name']}</td>";
    echo "<td>{$subject['subject_type']}</td>";
    echo "</tr>";
}
echo "</table>";

// Get second semester subjects
$stmt = $pdo->query("SELECT * FROM subjects WHERE is_second_sem = 1 ORDER BY subject_type, subject_name");
$second_sem = $stmt->fetchAll();

echo "<h3>Second Semester Subjects (is_second_sem = 1):</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Subject Name</th><th>Type</th></tr>";
foreach ($second_sem as $subject) {
    echo "<tr>";
    echo "<td>{$subject['id']}</td>";
    echo "<td>{$subject['subject_name']}</td>";
    echo "<td>{$subject['subject_type']}</td>";
    echo "</tr>";
}
echo "</table>";
?>