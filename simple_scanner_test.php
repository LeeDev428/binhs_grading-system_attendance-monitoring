<?php
// SUPER SIMPLE SCANNER TEST - NO COMPLEX LOGIC
if ($_POST) {
    echo "<h1 style='color: green; font-size: 3rem;'>🎉 SCANNER IS WORKING!</h1>";
    echo "<h2>Received data:</h2>";
    echo "<pre style='background: yellow; padding: 20px; font-size: 1.5rem;'>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['scan_input'])) {
        $barcode = trim($_POST['scan_input']);
        echo "<h2 style='color: blue;'>Barcode: '$barcode'</h2>";
        
        // Try to update database
        require_once 'config.php';
        try {
            $stmt = $pdo->prepare('UPDATE students SET is_scanned = 1 WHERE barcode = ?');
            $result = $stmt->execute([$barcode]);
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                echo "<h1 style='color: green; background: yellow; padding: 20px;'>✅ SUCCESS! DATABASE UPDATED!</h1>";
                echo "<h2>Student with barcode '$barcode' marked as scanned!</h2>";
            } else {
                echo "<h1 style='color: red;'>❌ No student found with barcode '$barcode'</h1>";
            }
            
            // Show current status
            $stmt = $pdo->query('SELECT first_name, last_name, barcode, is_scanned FROM students WHERE barcode IS NOT NULL');
            echo "<h3>Current Database Status:</h3>";
            echo "<table border='1' style='font-size: 1.2rem;'>";
            echo "<tr><th>Name</th><th>Barcode</th><th>is_scanned</th></tr>";
            while ($row = $stmt->fetch()) {
                $color = $row['is_scanned'] ? 'lightgreen' : 'lightcoral';
                echo "<tr style='background: $color;'>";
                echo "<td>{$row['first_name']} {$row['last_name']}</td>";
                echo "<td>{$row['barcode']}</td>";
                echo "<td>{$row['is_scanned']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } catch (Exception $e) {
            echo "<h2 style='color: red;'>Database Error: " . $e->getMessage() . "</h2>";
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SUPER SIMPLE SCANNER TEST</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin: 50px; }
        .big-input { 
            width: 80%; 
            padding: 30px; 
            font-size: 2rem; 
            border: 5px solid red; 
            text-align: center; 
            background: lightyellow;
        }
        .big-input:focus { 
            border-color: green; 
            background: lightgreen; 
        }
        .big-button {
            padding: 20px 40px;
            font-size: 1.5rem;
            background: blue;
            color: white;
            border: none;
            margin: 20px;
        }
    </style>
</head>
<body>
    <h1>🔥 SUPER SIMPLE SCANNER TEST</h1>
    <h2>If your scanner works, you'll see a SUCCESS message</h2>
    
    <form method="POST" action="">
        <p><strong>STEP 1:</strong> Click in the box below</p>
        <p><strong>STEP 2:</strong> Scan your barcode</p>
        <p><strong>STEP 3:</strong> Press the SUBMIT button</p>
        
        <br>
        <input type="text" 
               name="scan_input" 
               class="big-input" 
               placeholder="CLICK HERE AND SCAN BARCODE" 
               autofocus>
        <br>
        <button type="submit" class="big-button">SUBMIT</button>
    </form>
    
    <hr>
    <h3>Available Test Barcodes:</h3>
    <button onclick="document.querySelector('input[name=scan_input]').value='2500008'">2500008</button>
    <button onclick="document.querySelector('input[name=scan_input]').value='2500009'">2500009</button>
    <button onclick="document.querySelector('input[name=scan_input]').value='25student-004'">25student-004</button>
    
    <script>
        // Focus input
        document.querySelector('input[name=scan_input]').focus();
        
        // Log everything
        document.querySelector('input[name=scan_input]').addEventListener('input', function(e) {
            console.log('INPUT DETECTED:', e.target.value);
        });
        
        document.querySelector('input[name=scan_input]').addEventListener('keydown', function(e) {
            console.log('KEY PRESSED:', e.key, e.keyCode);
            if (e.key === 'Enter') {
                console.log('ENTER PRESSED - SUBMITTING FORM');
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>
