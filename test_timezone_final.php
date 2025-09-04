<?php
require_once 'config.php';

echo "<h2>Philippines Timezone Test</h2>";
echo "<p><strong>Current Philippines Time:</strong> " . date('Y-m-d H:i:s T') . "</p>";
echo "<p><strong>Date:</strong> " . date('Y-m-d') . "</p>";
echo "<p><strong>Time (24hr):</strong> " . date('H:i:s') . "</p>";
echo "<p><strong>Time (12hr):</strong> " . date('g:i A') . "</p>";
echo "<p><strong>Timezone:</strong> " . date_default_timezone_get() . "</p>";

// Test attendance time logic
$current_time = date('H:i');
$status = ($current_time > '08:00') ? 'late' : 'present';
echo "<p><strong>Current Status Logic:</strong> " . $status . " (time: {$current_time})</p>";

echo "<br><h3>JavaScript Timezone Test</h3>";
?>
<script>
    // Test JavaScript timezone
    const now = new Date();
    const philippinesTime = now.toLocaleTimeString('en-US', { timeZone: 'Asia/Manila' });
    const localTime = now.toLocaleTimeString();
    
    document.write("<p><strong>Philippines Time (JS):</strong> " + philippinesTime + "</p>");
    document.write("<p><strong>Local Time (JS):</strong> " + localTime + "</p>");
    
    // Test full datetime
    const fullDateTime = now.toLocaleString('en-US', { 
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.write("<p><strong>Full Philippines DateTime:</strong> " + fullDateTime + "</p>");
</script>
