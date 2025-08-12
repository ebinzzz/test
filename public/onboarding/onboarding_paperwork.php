<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
@include_once '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit();
}

$user_email = $_SESSION['email'];
$success_message = '';
$error_message = '';

// Get team member ID
$sql = "SELECT id FROM team_members WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$user_data = $result->fetch_assoc();
$team_member_id = $user_data['id'];
$stmt->close();

// Handle file upload
if ($_POST && isset($_POST['submit_paperwork'])) {
    $paperwork_type_id = intval($_POST['paperwork_type_id']);
    
    if (isset($_FILES['paperwork_file']) && $_FILES['paperwork_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['paperwork_file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Invalid file type. Please upload PDF, DOC, DOCX, or image files only.";
        } elseif ($file['size'] > $max_size) {
            $error_message = "File size too large. Maximum size is 10MB.";
        } else {
            // Create upload directory
            $upload_dir = "../uploads/paperwork/{$team_member_id}/{$paperwork_type_id}/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Save to database
                $sql = "INSERT INTO paperwork_submissions (team_member_id, paperwork_type_id, status, file_path, original_filename, file_size, mime_type, submitted_at) 
                        VALUES (?, ?, 'submitted', ?, ?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE 
                        status = 'submitted', 
                        file_path = VALUES(file_path), 
                        original_filename = VALUES(original_filename), 
                        file_size = VALUES(file_size), 
                        mime_type = VALUES(mime_type), 
                        submitted_at = NOW()";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissis", $team_member_id, $paperwork_type_id, $upload_path, $file['name'], $file['size'], $file['type']);
                
                if ($stmt->execute()) {
                    $success_message = "Paperwork submitted successfully!";
                } else {
                    $error_message = "Error saving submission to database.";
                    unlink($upload_path); // Remove uploaded file
                }
                $stmt->close();
            } else {
                $error_message = "Error uploading file. Please try again.";
            }
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Get all paperwork types and user submissions
$sql = "SELECT pt.*, ps.status, ps.submitted_at, ps.file_path, ps.original_filename, ps.reviewer_notes
        FROM paperwork_types pt
        LEFT JOIN paperwork_submissions ps ON pt.id = ps.paperwork_type_id AND ps.team_member_id = ?
        ORDER BY pt.sort_order, pt.name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $team_member_id);
$stmt->execute();
$paperwork_result = $stmt->get_result();

$paperwork_items = [];
while ($row = $paperwork_result->fetch_assoc()) {
    $paperwork_items[] = $row;
}
$stmt->close();

// Calculate completion stats
$total_required = 0;
$completed_required = 0;
$total_optional = 0;
$completed_optional = 0;

foreach ($paperwork_items as $item) {
    if ($item['is_required']) {
        $total_required++;
        if (in_array($item['status'], ['submitted', 'approved'])) {
            $completed_required++;
        }
    } else {
        $total_optional++;
        if (in_array($item['status'], ['submitted', 'approved'])) {
            $completed_optional++;
        }
    }
}

$completion_percentage = $total_required > 0 ? round(($completed_required / $total_required) * 100) : 100;

// Set page title for the common layout
$page_title = "Onboarding Paperwork";

// Include the common layout
include 'common_layout.php';
?>

<style>
    .progress-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        color: white;
        padding: 30px;
        border-radius: var(--radius-xl);
        margin-bottom: 30px;
        box-shadow: var(--shadow-lg);
    }
    
    .progress-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .stat-card {
        background: rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: var(--radius-lg);
        text-align: center;
        backdrop-filter: blur(10px);
    }
    
    .stat-number {
        font-size: 2em;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .progress-bar {
        width: 100%;
        height: 8px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 4px;
        overflow: hidden;
        margin: 15px 0;
    }
    
    .progress-fill {
        height: 100%;
        background: var(--accent-color);
        border-radius: 4px;
        transition: width 0.3s ease;
    }
    
    .paperwork-grid {
        display: grid;
        gap: 25px;
    }
    
    .paperwork-card {
        background: var(--bg-primary);
        border-radius: var(--radius-xl);
        padding: 25px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
    }
    
    .paperwork-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .paperwork-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    
    .paperwork-title {
        font-size: 1.2em;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }
    
    .paperwork-description {
        color: var(--text-secondary);
        font-size: 0.9em;
        line-height: 1.5;
    }
    
    .status-badge {
        padding: 6px 12px;
        border-radius: var(--radius-md);
        font-size: 0.8em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-submitted {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-needs_revision {
        background: #fed7aa;
        color: #9a3412;
    }
    
    .required-badge {
        background: #fee2e2;
        color: #991b1b;
        padding: 4px 8px;
        border-radius: var(--radius-sm);
        font-size: 0.7em;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: 10px;
    }
    
    .paperwork-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-light);
    }
    
    .file-upload-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .file-input {
        flex: 1;
        min-width: 200px;
        padding: 8px 12px;
        border: 2px dashed var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        transition: border-color 0.2s ease;
    }
    
    .file-input:hover {
        border-color: var(--primary-color);
    }
    
    .upload-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .upload-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }
    
    .upload-btn:disabled {
        background: var(--text-light);
        cursor: not-allowed;
        transform: none;
    }
    
    .file-info {
        color: var(--text-secondary);
        font-size: 0.9em;
        margin-top: 10px;
    }
    
    .reviewer-notes {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 15px;
        margin-top: 15px;
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        font-weight: 500;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    
    @media (max-width: 768px) {
        .file-upload-form {
            flex-direction: column;
        }
        
        .file-input {
            min-width: unset;
        }
        
        .progress-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="progress-header">
    <h2 style="margin-bottom: 10px;">Onboarding Progress</h2>
    <p>Complete your required paperwork to finish the onboarding process</p>
    
    <div class="progress-bar">
        <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%"></div>
    </div>
    
    <div class="progress-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $completion_percentage; ?>%</div>
            <div>Overall Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $completed_required; ?>/<?php echo $total_required; ?></div>
            <div>Required Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $completed_optional; ?>/<?php echo $total_optional; ?></div>
            <div>Optional Items</div>
        </div>
    </div>
</div>

<div class="paperwork-grid">
    <?php foreach ($paperwork_items as $item): ?>
        <div class="paperwork-card">
            <div class="paperwork-header">
                <div>
                    <h3 class="paperwork-title">
                        <?php echo htmlspecialchars($item['name']); ?>
                        <?php if ($item['is_required']): ?>
                            <span class="required-badge">Required</span>
                        <?php endif; ?>
                    </h3>
                    <p class="paperwork-description">
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                </div>
                <div>
                    <?php
                    $status_class = 'status-pending';
                    $status_text = 'Pending';
                    
                    if ($item['status']) {
                        $status_class = 'status-' . $item['status'];
                        $status_text = ucfirst(str_replace('_', ' ', $item['status']));
                    }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </div>
            </div>
            
            <?php if ($item['status'] === 'approved' || $item['status'] === 'submitted'): ?>
                <div class="file-info">
                    <i class="fas fa-file-alt" style="margin-right: 5px;"></i>
                    <strong>File:</strong> <?php echo htmlspecialchars($item['original_filename']); ?>
                    <?php if ($item['submitted_at']): ?>
                        <br><i class="fas fa-calendar" style="margin-right: 5px;"></i>
                        <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($item['submitted_at'])); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($item['reviewer_notes']): ?>
                <div class="reviewer-notes">
                    <strong>Reviewer Notes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($item['reviewer_notes'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($item['status'] !== 'approved'): ?>
                <div class="paperwork-actions">
                    <form method="POST" enctype="multipart/form-data" class="file-upload-form">
                        <input type="hidden" name="paperwork_type_id" value="<?php echo $item['id']; ?>">
                        <input type="file" name="paperwork_file" class="file-input" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <button type="submit" name="submit_paperwork" class="upload-btn">
                            <?php echo ($item['status'] === 'submitted' || $item['status'] === 'needs_revision') ? 'Resubmit' : 'Upload'; ?>
                        </button>
                    </form>
                </div>
                <div class="file-info">
                    <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                    Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($completion_percentage === 100): ?>
    <div class="card text-center mt-4">
        <h3 style="color: var(--accent-color); margin-bottom: 15px;">
            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
            Congratulations!
        </h3>
        <p style="font-size: 1.1em;">You have successfully completed all required onboarding paperwork. Your submissions are being reviewed and you'll be notified of any updates.</p>
    </div>
<?php endif; ?>

    </div>
</div>

<script>
// Auto-hide success messages
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.remove();
            }, 300);
        }, 5000);
    }
});

// File input validation
document.addEventListener('change', function(e) {
    if (e.target.type === 'file') {
        const file = e.target.files[0];
        if (file) {
            const maxSize = 10 * 1024 * 1024; // 10MB
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (file.size > maxSize) {
                alert('File size too large. Maximum size is 10MB.');
                e.target.value = '';
                return;
            }
            
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload PDF, DOC, DOCX, or image files only.');
                e.target.value = '';
                return;
            }
        }
    }
});
</script>

</body>
</html>