<?php
session_start();
@include_once '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['email'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST) {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            // Verify current password from users table
            $sql = "SELECT password FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                
                if (password_verify($current_password, $user_data['password'])) {
                    // Update password in users table (not team_members)
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ? WHERE email = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $hashed_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Error updating password. Please try again.";
                    }
                    $update_stmt->close();
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } else {
                $error_message = "User not found.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        
        if (empty($full_name)) {
            $error_message = "Full name is required.";
        } else {
            // Update full_name in team_members table
            $update_sql = "UPDATE team_members SET full_name = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $full_name, $user_id);
            
            if ($update_stmt->execute()) {
                // Check if any rows were affected
                if ($update_stmt->affected_rows > 0) {
                    // Redirect to prevent resubmission
                    header("Location: settings.php?success=profile_updated");
                    exit();
                } else {
                    $error_message = "No profile found to update or no changes made.";
                }
            } else {
                $error_message = "Error updating profile. Please try again.";
            }
            $update_stmt->close();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'profile_updated') {
    $success_message = "Profile updated successfully!";
}

// Set page title for the common layout
$page_title = "Settings";

// NOW include the common layout after all PHP processing is done
include 'common_layout.php';
?>
<!-- Page Content -->
<div class="content-wrapper">
    <div class="page-header mb-4">
        <h2 class="page-title">Account Settings</h2>
        <p class="text-muted">Manage your account preferences and security settings</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="settings-container">
        <div class="row">
            <!-- Profile Information Card -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i>
                            Profile Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="settings-form">
                            <div class="form-group mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       class="form-control" 
                                       value="<?php echo $full_name; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" 
                                       id="email" 
                                       class="form-control" 
                                       value="<?php echo $user_id; ?>" 
                                       disabled>
                                <small class="form-text">Email cannot be changed</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" 
                                       id="role" 
                                       class="form-control" 
                                       value="<?php echo $role; ?>" 
                                       disabled>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="settings-form" id="passwordForm">
                            <div class="form-group mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           id="current_password" 
                                           name="current_password" 
                                           class="form-control" 
                                           required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           id="new_password" 
                                           name="new_password" 
                                           class="form-control" 
                                           minlength="6" 
                                           required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           class="form-control" 
                                           minlength="6" 
                                           required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_password" class="btn btn-warning">
                                <i class="fas fa-key"></i>
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Settings specific styles */
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: -15px;
}

.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding: 15px;
}

@media (max-width: 768px) {
    .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

.card-header {
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
    padding: 20px 25px;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}

.card-title {
    font-size: 1.25em;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95em;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 0.95em;
    transition: all 0.2s ease;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-control:disabled {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-text {
    font-size: 0.85em;
    color: var(--text-secondary);
    margin-top: 5px;
    display: block;
}

.password-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.password-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: var(--text-primary);
}

.btn-warning {
    background: var(--warning-color);
    color: var(--text-white);
}

.btn-warning:hover {
    background: #d97706;
}

.alert {
    padding: 16px 20px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 2em;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}
</style>

<script>
// Password visibility toggle
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        toggle.classList.remove('fa-eye');
        toggle.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        toggle.classList.remove('fa-eye-slash');
        toggle.classList.add('fa-eye');
    }
}

// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
});

// Auto-hide success/error messages after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(function() {
            alert.remove();
        }, 300);
    });
}, 5000);
</script>

</div> <!-- End content-wrapper -->
</div> <!-- End main-content -->
</body>
</html>