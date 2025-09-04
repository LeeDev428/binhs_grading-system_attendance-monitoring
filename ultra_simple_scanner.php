<?php
/**
 * ULTRA SIMPLE SCANNER - NO FANCY JAVASCRIPT
 * Your scanner works in Notepad, it will work here too!
 */

require_once 'config.php';

if ($_POST && isset($_POST['scan'])) {
    $barcode = trim($_POST['scan']);
    echo "<div style='background: yellow; padding: 20px; margin: 20px; border: 5px solid green; font-size: 2rem; text-align: center;'>";
    echo "🎉 BARCODE RECEIVED: <strong>$barcode</strong>";
    echo "</div>";
    
    if (!empty($barcode)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $student = $stmt->fetch();
            
            if ($student) {
                if ($student['is_scanned'] == 0) {
                    $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
                    $result = $stmt->execute([$student['id']]);
                    
                    if ($result) {
                        echo "<div style='background: lightgreen; padding: 20px; margin: 20px; border: 5px solid darkgreen; font-size: 2rem; text-align: center;'>";
                        echo "✅ SUCCESS! {$student['first_name']} {$student['last_name']} marked as scanned!<br>";
                        echo "is_scanned changed from 0 to 1";
                        echo "</div>";
                    } else {
                        echo "<div style='background: lightcoral; padding: 20px; margin: 20px; border: 5px solid red; font-size: 1.5rem; text-align: center;'>";
                        echo "❌ Database update failed";
                        echo "</div>";
                    }
                } else {
                    echo "<div style='background: orange; padding: 20px; margin: 20px; border: 5px solid darkorange; font-size: 1.5rem; text-align: center;'>";
                    echo "⚠️ {$student['first_name']} {$student['last_name']} already scanned today!";
                    echo "</div>";
                }
            } else {
                echo "<div style='background: lightcoral; padding: 20px; margin: 20px; border: 5px solid red; font-size: 1.5rem; text-align: center;'>";
                echo "❌ No student found with barcode: $barcode";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: lightcoral; padding: 20px; margin: 20px; border: 5px solid red; font-size: 1.5rem; text-align: center;'>";
            echo "❌ Error: " . $e->getMessage();
            echo "</div>";
        }
    }
}

// Show current students
try {
    $stmt = $pdo->query("SELECT first_name, last_name, barcode, is_scanned FROM students WHERE barcode IS NOT NULL ORDER BY last_name");
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ULTRA SIMPLE SCANNER</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f0f0f0; 
        }
        .scan-box { 
            background: white; 
            padding: 30px; 
            border: 5px solid #007bff; 
            border-radius: 10px; 
            text-align: center; 
            margin: 20px 0;
        }
        .scan-input { 
            width: 80%; 
            padding: 20px; 
            font-size: 2rem; 
            border: 3px solid #28a745; 
            text-align: center; 
            border-radius: 5px;
        }
        .scan-input:focus { 
            border-color: #007bff; 
            background: #e3f2fd; 
        }
        .scan-button { 
            padding: 20px 40px; 
            font-size: 1.5rem; 
            background: #28a745; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            margin: 20px;
        }
        .scan-button:hover { 
            background: #218838; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            margin: 20px 0;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background: #007bff; 
            color: white; 
        }
        .scanned { 
            background: #d4edda; 
        }
        .not-scanned { 
            background: #f8f9fa; 
        }
    </style>
</head>
<body>
    <h1 style="text-align: center; color: #007bff;">🔥 ULTRA SIMPLE SCANNER</h1>
    <h2 style="text-align: center; color: #28a745;">Your TEKLEAD scanner works in Notepad = It will work here!</h2>
    
    <div class="scan-box">
        <h3>📱 SCAN YOUR BARCODE HERE</h3>
        <form method="POST" action="">
            <input type="text" 
                   name="scan" 
                   class="scan-input" 
                   placeholder="Click here and scan barcode..." 
                   autofocus 
                   autocomplete="off">
            <br>
            <button type="submit" class="scan-button">
                🚀 SUBMIT BARCODE
            </button>
        </form>
        
        <p style="margin-top: 20px; color: #666;">
            <strong>Instructions:</strong><br>
            1. Click in the input box above<br>
            2. Scan barcode with your TEKLEAD scanner<br>
            3. Click SUBMIT BARCODE button<br>
            4. Watch for SUCCESS message
        </p>
        
        <div style="margin-top: 20px;">
            <strong>Test Barcodes:</strong>
            <button type="button" onclick="document.querySelector('input[name=scan]').value='2500008'" style="margin: 5px; padding: 10px;">2500008</button>
            <button type="button" onclick="document.querySelector('input[name=scan]').value='2500009'" style="margin: 5px; padding: 10px;">2500009</button>
            <button type="button" onclick="document.querySelector('input[name=scan]').value='25student-004'" style="margin: 5px; padding: 10px;">25student-004</button>
        </div>
    </div>
    
    <h3>📊 Current Students Status</h3>
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Barcode</th>
                <th>is_scanned</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
            <tr class="<?php echo $student['is_scanned'] ? 'scanned' : 'not-scanned'; ?>">
                <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                <td><code><?php echo htmlspecialchars($student['barcode']); ?></code></td>
                <td>
                    <span style="background: <?php echo $student['is_scanned'] ? '#28a745' : '#6c757d'; ?>; color: white; padding: 5px 10px; border-radius: 3px;">
                        <?php echo $student['is_scanned']; ?>
                    </span>
                </td>
                <td>
                    <?php if ($student['is_scanned']): ?>
                        <span style="color: #dc3545; font-weight: bold;">✅ SCANNED TODAY</span>
                    <?php else: ?>
                        <span style="color: #28a745; font-weight: bold;">🎯 READY TO SCAN</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="text-align: center; margin: 30px;">
        <a href="reset_quick.php" style="background: #ffc107; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            🔄 RESET ALL STUDENTS (Testing)
        </a>
    </div>
    
    <script>
        // Keep focus on input field
        function keepFocus() {
            const input = document.querySelector('input[name="scan"]');
            if (document.activeElement !== input) {
                input.focus();
            }
        }
        
        // Focus input when page loads
        document.querySelector('input[name="scan"]').focus();
        
        // Re-focus every 2 seconds
        setInterval(keepFocus, 2000);
        
        // Focus when clicking anywhere
        document.addEventListener('click', keepFocus);
        
        console.log('✅ Ultra Simple Scanner ready! Just scan and click submit.');
    </script>
</body>
</html>
