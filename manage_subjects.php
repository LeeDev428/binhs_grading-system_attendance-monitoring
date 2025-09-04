<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page variables for layout
$current_page = 'manage_subjects';
$page_title = 'Manage Subjects';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_subject'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO subjects (subject_name, subject_code, grade_level, subject_type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['subject_name'],
                $_POST['subject_code'],
                $_POST['grade_level'],
                $_POST['subject_type']
            ]);
            $success = 'Subject added successfully!';
        } catch (PDOException $e) {
            $error = 'Could not add subject: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_subject'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$_POST['subject_id']]);
            $success = 'Subject deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Could not delete subject: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_subject'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE subjects SET 
                    subject_name = ?, subject_code = ?, grade_level = ?, subject_type = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['subject_name'],
                $_POST['subject_code'],
                $_POST['grade_level'],
                $_POST['subject_type'],
                $_POST['subject_id']
            ]);
            $success = 'Subject updated successfully!';
        } catch (PDOException $e) {
            $error = 'Could not update subject: ' . $e->getMessage();
        }
    }
}

// Get all subjects
try {
    $stmt = $pdo->query("
        SELECT * FROM subjects 
        ORDER BY subject_type, grade_level, subject_name
    ");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
    $error = 'Could not load subjects: ' . $e->getMessage();
}

// Start output buffering for page content
ob_start();
?>

<style>
    .subjects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .subject-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-left: 4px solid #11998e;
    }
    
    .subject-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
    
    .subject-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .type-core {
        background: #d4edda;
        color: #155724;
    }
    
    .type-applied {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .type-specialized {
        background: #fff3cd;
        color: #856404;
    }
    
    .subject-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .subject-code {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .subject-grade {
        color: #11998e;
        font-weight: 500;
        margin-bottom: 15px;
    }
    
    .subject-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    
    .btn {
        padding: 6px 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background-color 0.2s ease;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .btn-warning {
        background: #ffc107;
        color: #212529;
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
    
    .add-subject-fab {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(17, 153, 142, 0.4);
        transition: all 0.3s ease;
        z-index: 1000;
    }
    
    .add-subject-fab:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(17, 153, 142, 0.6);
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        position: relative;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #333;
    }
    
    .close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #aaa;
    }
    
    .close:hover {
        color: #000;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border: 2px solid #e1e5e9;
        border-radius: 5px;
        font-size: 1rem;
        transition: border-color 0.2s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #11998e;
        box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
    }
    
    .subjects-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e1e5e9;
        background: white;
        color: #666;
        border-radius: 20px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-btn.active,
    .filter-btn:hover {
        border-color: #11998e;
        background: #11998e;
        color: white;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #11998e;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }
</style>

<?php if (isset($error)): ?>
    <div class="content-card fade-in" style="border-left: 4px solid #dc3545;">
        <div style="padding: 20px; background-color: #f8d7da; color: #721c24; border-radius: 4px;">
            <h4><i class="fas fa-exclamation-triangle"></i> Error</h4>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="content-card fade-in" style="border-left: 4px solid #28a745;">
        <div style="padding: 20px; background-color: #d4edda; color: #155724; border-radius: 4px;">
            <h4><i class="fas fa-check-circle"></i> Success</h4>
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- Statistics Overview -->
<div class="stats-cards">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($subjects); ?></div>
        <div class="stat-label">Total Subjects</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">
            <?php echo count(array_filter($subjects, function($s) { return $s['subject_type'] == 'CORE'; })); ?>
        </div>
        <div class="stat-label">Core Subjects</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">
            <?php echo count(array_filter($subjects, function($s) { return $s['subject_type'] == 'APPLIED'; })); ?>
        </div>
        <div class="stat-label">Applied Subjects</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">
            <?php echo count(array_filter($subjects, function($s) { return $s['subject_type'] == 'SPECIALIZED'; })); ?>
        </div>
        <div class="stat-label">Specialized Subjects</div>
    </div>
</div>

<!-- Subjects Header -->
<div class="subjects-header">
    <div>
        <h2 style="margin: 0; color: #333;">All Subjects</h2>
        <p style="margin: 5px 0 0 0; color: #666;">Manage all subjects in the system</p>
    </div>
    <div class="filter-buttons">
        <button class="filter-btn active" onclick="filterSubjects('all')">All</button>
        <button class="filter-btn" onclick="filterSubjects('CORE')">Core</button>
        <button class="filter-btn" onclick="filterSubjects('APPLIED')">Applied</button>
        <button class="filter-btn" onclick="filterSubjects('SPECIALIZED')">Specialized</button>
    </div>
</div>

<!-- Subjects Grid -->
<div class="subjects-grid" id="subjectsGrid">
    <?php foreach ($subjects as $subject): ?>
    <div class="subject-card" data-type="<?php echo $subject['subject_type']; ?>">
        <div class="subject-type-badge type-<?php echo strtolower($subject['subject_type']); ?>">
            <?php echo htmlspecialchars($subject['subject_type']); ?>
        </div>
        <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
        <div class="subject-code">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></div>
        <div class="subject-grade"><?php echo htmlspecialchars($subject['grade_level']); ?></div>
        <div class="subject-actions">
            <button class="btn btn-warning btn-sm" onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button class="btn btn-danger btn-sm" onclick="deleteSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($subjects)): ?>
<div class="content-card fade-in">
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 4rem; color: #ccc; margin-bottom: 20px;">
            <i class="fas fa-book"></i>
        </div>
        <h3 style="color: #666; margin-bottom: 10px;">No Subjects Found</h3>
        <p style="color: #999; margin-bottom: 30px;">Start by adding subjects using the + button below.</p>
    </div>
</div>
<?php endif; ?>

<!-- Add Subject FAB -->
<button class="add-subject-fab" onclick="openAddModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Add/Edit Subject Modal -->
<div id="subjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Add New Subject</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="subjectForm" method="POST">
            <input type="hidden" id="subjectId" name="subject_id">
            <input type="hidden" id="formAction" name="add_subject" value="1">
            
            <div class="form-group">
                <label class="form-label" for="subjectName">Subject Name</label>
                <input type="text" id="subjectName" name="subject_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="subjectCode">Subject Code</label>
                <input type="text" id="subjectCode" name="subject_code" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="gradeLevel">Grade Level</label>
                <select id="gradeLevel" name="grade_level" class="form-control" required>
                    <option value="">Select Grade Level</option>
                    <option value="Grade 11">Grade 11</option>
                    <option value="Grade 12">Grade 12</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="subjectType">Subject Type</label>
                <select id="subjectType" name="subject_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="CORE">Core Subject</option>
                    <option value="APPLIED">Applied Subject</option>
                    <option value="SPECIALIZED">Specialized Subject</option>
                </select>
            </div>
            
            <div style="text-align: right; margin-top: 30px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Add Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_subject" value="1">
    <input type="hidden" id="deleteSubjectId" name="subject_id">
</form>

<script>
// Modal functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Subject';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Add Subject';
    document.getElementById('subjectForm').reset();
    document.getElementById('subjectId').value = '';
    document.getElementById('formAction').name = 'add_subject';
    document.getElementById('subjectModal').style.display = 'block';
}

function editSubject(subject) {
    document.getElementById('modalTitle').textContent = 'Edit Subject';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Subject';
    document.getElementById('subjectId').value = subject.id;
    document.getElementById('subjectName').value = subject.subject_name;
    document.getElementById('subjectCode').value = subject.subject_code;
    document.getElementById('gradeLevel').value = subject.grade_level;
    document.getElementById('subjectType').value = subject.subject_type;
    document.getElementById('formAction').name = 'update_subject';
    document.getElementById('subjectModal').style.display = 'block';
}

function deleteSubject(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        document.getElementById('deleteSubjectId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function closeModal() {
    document.getElementById('subjectModal').style.display = 'none';
}

// Filter functions
function filterSubjects(type) {
    const cards = document.querySelectorAll('.subject-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter cards
    cards.forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('subjectModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Auto-generate subject code based on name and type
document.getElementById('subjectName').addEventListener('input', function() {
    const name = this.value;
    const type = document.getElementById('subjectType').value;
    
    if (name && type) {
        // Generate code from first letters of words
        const words = name.split(' ');
        const initials = words.map(word => word.charAt(0).toUpperCase()).join('');
        const code = type + '-' + initials;
        document.getElementById('subjectCode').value = code;
    }
});

document.getElementById('subjectType').addEventListener('change', function() {
    const name = document.getElementById('subjectName').value;
    const type = this.value;
    
    if (name && type) {
        const words = name.split(' ');
        const initials = words.map(word => word.charAt(0).toUpperCase()).join('');
        const code = type + '-' + initials;
        document.getElementById('subjectCode').value = code;
    }
});
</script>

<?php
$page_content = ob_get_clean();
include 'layout.php';
?>
