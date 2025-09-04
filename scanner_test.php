<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$current_page = 'scanner_test';
$page_title = 'Scanner Test Page';

ob_start();
?>

<div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
    <div class="card" style="border: 2px solid #11998e; border-radius: 15px; padding: 30px; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
        <div class="text-center mb-4">
            <h2 style="color: #11998e;">
                <i class="fas fa-qrcode"></i>
                Scanner Test Page
            </h2>
            <p class="text-muted">Test your TEKLEAD scanner and see what it outputs</p>
        </div>
        
        <div class="test-area" style="background: #f8f9fa; padding: 30px; border-radius: 10px; margin: 20px 0;">
            <label for="scannerTest" style="font-weight: bold; color: #333; margin-bottom: 15px; display: block;">
                <i class="fas fa-barcode"></i> Click in this field and scan a barcode:
            </label>
            <input type="text" 
                   id="scannerTest" 
                   class="form-control" 
                   style="font-size: 18px; padding: 15px; border: 3px solid #11998e; border-radius: 10px; text-align: center; font-family: monospace;"
                   placeholder="Scan barcode here..." 
                   autocomplete="off">
        </div>
        
        <div id="results" style="display: none; margin-top: 20px;">
            <h4 style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
                Scanner Results:
            </h4>
            <div class="alert alert-success" style="border-radius: 10px;">
                <strong>Scanned Value:</strong> <span id="scannedValue" style="font-family: monospace; font-size: 16px;"></span><br>
                <strong>Length:</strong> <span id="scannedLength"></span> characters<br>
                <strong>Type:</strong> <span id="scannedType"></span>
            </div>
        </div>
        
        <div class="instructions mt-4" style="background: #e3f2fd; padding: 20px; border-radius: 10px;">
            <h5 style="color: #1976d2;">
                <i class="fas fa-info-circle"></i>
                Instructions:
            </h5>
            <ol style="margin: 10px 0;">
                <li>Click in the input field above</li>
                <li>Use your TEKLEAD scanner to scan any barcode</li>
                <li>The results will appear below</li>
                <li>Test with different barcode types to see what works</li>
            </ol>
            
            <div class="mt-3">
                <strong>Compatible formats for your scanner:</strong>
                <ul style="margin: 10px 0;">
                    <li>Numeric barcodes (like: 1234567890)</li>
                    <li>Alphanumeric barcodes (like: ABC123)</li>
                    <li>CODE128, CODE39, EAN13 formats</li>
                </ul>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="attendance_scanner.php" class="btn btn-primary" style="background: linear-gradient(135deg, #11998e, #38ef7d); border: none; padding: 12px 25px; border-radius: 25px;">
                <i class="fas fa-arrow-right"></i>
                Go to Attendance Scanner
            </a>
            <button onclick="clearTest()" class="btn btn-secondary" style="margin-left: 10px; padding: 12px 25px; border-radius: 25px;">
                <i class="fas fa-trash"></i>
                Clear Test
            </button>
        </div>
    </div>
</div>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Arial', sans-serif;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #38ef7d;
        box-shadow: 0 0 15px rgba(56, 239, 125, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
    }
    
    .btn-secondary:hover {
        transform: translateY(-2px);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const scannerInput = document.getElementById('scannerTest');
        const resultsDiv = document.getElementById('results');
        
        // Keep focus on input
        scannerInput.focus();
        
        // Handle input changes
        scannerInput.addEventListener('input', function() {
            const value = this.value.trim();
            
            if (value.length > 0) {
                // Show results
                document.getElementById('scannedValue').textContent = value;
                document.getElementById('scannedLength').textContent = value.length;
                
                // Determine type
                let type = 'Unknown';
                if (/^\d+$/.test(value)) {
                    type = 'Numeric';
                } else if (/^[A-Z0-9\-]+$/i.test(value)) {
                    type = 'Alphanumeric';
                } else {
                    type = 'Mixed/Special Characters';
                }
                
                document.getElementById('scannedType').textContent = type;
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.style.display = 'none';
            }
        });
        
        // Refocus when clicking anywhere
        document.addEventListener('click', function() {
            scannerInput.focus();
        });
        
        // Handle Enter key
        scannerInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                console.log('Scanner sent Enter after:', this.value);
            }
        });
    });
    
    function clearTest() {
        document.getElementById('scannerTest').value = '';
        document.getElementById('results').style.display = 'none';
        document.getElementById('scannerTest').focus();
    }
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
