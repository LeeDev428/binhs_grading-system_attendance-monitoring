<?php
require_once 'config.php';
$pdo->query('UPDATE students SET is_scanned = 0');
echo 'All students reset to is_scanned = 0';
?>
