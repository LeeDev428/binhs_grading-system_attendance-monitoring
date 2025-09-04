<?php
/**
 * SIMPLE BARCODE TEST - Direct Database Update
 * Test this first to make sure barcode scanning works
 */

require_once 'config.php';

$message = '';

if ($_POST && isset($_POST['test_barcode'])) {
    $barcode = trim($_POST['test_barcode']);
    
    try {
        // Find student by barcode
        $stmt = $pdo->prepare("SELECT * FROM students WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $student = $stmt->fetch();
        
        if ($student) {
            if ($student['is_scanned'] == 1) {
                $message = "❌ {$student['first_name']} {$student['last_name']} already scanned today!";
            } else {
                // Update is_scanned to 1
                $stmt = $pdo->prepare("UPDATE students SET is_scanned = 1 WHERE id = ?");
                $result = $stmt->execute([$student['id']]);
                
                if ($result) {
                    $message = "✅ SUCCESS! {$student['first_name']} {$student['last_name']} marked as scanned (is_scanned = 1)";
                } else {
                    $message = "❌ Failed to update database";
                }
            }
        } else {
            $message = "❌ Barcode not found: {$barcode}";
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get current student statuses
try {
    $stmt = $pdo->query("
        SELECT 
            id, first_name, last_name, barcode, is_scanned,
            CASE WHEN is_scanned = 1 THEN 'SCANNED TODAY' ELSE 'CAN SCAN' END as status
        FROM students 
        WHERE barcode IS NOT NULL 
        ORDER BY last_name, first_name
        LIMIT 10
    ");
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barcode Scanner Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-input {
            font-size: 1.5rem;
            padding: 15px;
            text-align: center;
            border: 3px solid #007bff;
        }
        .test-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-barcode"></i> BARCODE SCANNER TEST</h1>
        <div class="alert alert-info">
            <strong>Instructions:</strong><br>
            1. Click in the input field below<br>
            2. Scan your barcode with TEKLEAD scanner<br>
            3. Press Enter or click Submit<br>
            4. Watch for the SUCCESS message and database update
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, '✅') !== false ? 'success' : 'danger'; ?>">
            <h4><?php echo $message; ?></h4>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>🔥 SCAN BARCODE HERE</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="testForm">
                    <div class="mb-3">
                        <label for="test_barcode" class="form-label">Barcode Input:</label>
                        <input type="text" 
                               id="test_barcode" 
                               name="test_barcode" 
                               class="form-control test-input" 
                               placeholder="Click here and scan barcode..."
                               autofocus
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check"></i> SUBMIT BARCODE
                    </button>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <strong>Test Barcodes:</strong> 
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('test_barcode').value='2500001'">2500001</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('test_barcode').value='2500002'">2500002</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('test_barcode').value='2500003'">2500003</button>
                        </small>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h4>Current Student Status</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Barcode</th>
                                <th>is_scanned</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr class="<?php echo $student['is_scanned'] ? 'table-success' : 'table-light'; ?>">
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($student['barcode']); ?></code></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['is_scanned'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $student['is_scanned']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $student['is_scanned'] ? 'danger' : 'primary'; ?>">
                                        <?php echo $student['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="manual_reset.php" class="btn btn-warning">
                <i class="fas fa-undo"></i> Reset All Students (is_scanned = 0)
            </a>
            <a href="attendance_scanner.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Main Scanner
            </a>
        </div>
    </div>
    
    <script>
        // Auto-submit when Enter is pressed
        document.getElementById('test_barcode').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                console.log('Enter pressed, submitting barcode:', this.value);
                document.getElementById('testForm').submit();
            }
        });
        
        // Auto-submit after barcode input (for TEKLEAD scanner)
        let timeout;
        document.getElementById('test_barcode').addEventListener('input', function() {
            clearTimeout(timeout);
            const value = this.value.trim();
            console.log('Barcode input:', value);
            
            if (value.length >= 6) {
                timeout = setTimeout(() => {
                    console.log('Auto-submitting barcode:', value);
                    document.getElementById('testForm').submit();
                }, 500);
            }
        });
        
        // Keep focus on input
        document.addEventListener('click', function() {
            document.getElementById('test_barcode').focus();
        });
        
        console.log('Barcode test ready! Scan your barcode now.');
    </script>
</body>
</html>
