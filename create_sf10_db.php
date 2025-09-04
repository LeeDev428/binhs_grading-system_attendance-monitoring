<?php
require_once 'config.php';

try {
    $sql = file_get_contents('create_sf10_tables.sql');
    $pdo->exec($sql);
    echo 'SF10 tables created successfully!';
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
