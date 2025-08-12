<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

@include_once '../config.php';
@include_once 'mail/mail.php';

if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

$message = '';
$error_message = '';

$upload_dir = '../uploads/team/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role']);
        $status = trim($_POST['status'] ?? 'Active');
        $salary = floatval($_POST['salary'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? '');
        $image_path = '';

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            // Validate image type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_extensions)) {
                $error_message = "Only JPG, JPEG, PNG, and GIF images are allowed.";
            } else {
                // Validate file size (max 5MB)
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    $error_message = "Image size must be less than 5MB.";
                } else {
                    $file_name = uniqid('team_', true) . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $image_path = substr($file_path, 3); // Remove '../' from path
                    } else {
                        $error_message = "Error uploading image.";
                    }
                }
            }
        }
        
        // Validate required fields
        if (empty($full_name) || empty($email) || empty($role)) {
            $error_message = "Full Name, Email, and Role are required fields.";
        }
        
        // Validate email format
        if (empty($error_message) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        }
        
        // Check if email already exists
        if (empty($error_message)) {
            $check_email_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_email_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "A user with this email address already exists.";
            }
            $check_stmt->close();
        }
        
        if (empty($error_message)) {
            // Start transaction for data consistency
            $conn->begin_transaction();
            
            try {
                // Generate username and password
                $username_base = strtolower(preg_replace('/[^a-zA-Z]/', '', substr($full_name, 0, 8)));
                $last_four_digits_phone = substr(preg_replace('/[^0-9]/', '', $phone), -4);
                
                // Ensure we have at least 4 digits, pad with random numbers if needed
                if (strlen($last_four_digits_phone) < 4) {
                    $last_four_digits_phone = str_pad($last_four_digits_phone, 4, rand(1000, 9999), STR_PAD_LEFT);
                }
                
                // Create unique username
                $username = $username_base;
                $counter = 1;
                while (true) {
                    $check_username_sql = "SELECT id FROM users WHERE username = ? LIMIT 1";
                    $check_username_stmt = $conn->prepare($check_username_sql);
                    $check_username_stmt->bind_param("s", $username);
                    $check_username_stmt->execute();
                    $username_result = $check_username_stmt->get_result();
                    
                    if ($username_result->num_rows == 0) {
                        $check_username_stmt->close();
                        break; // Username is unique
                    }
                    
                    $check_username_stmt->close();
                    $username = $username_base . $counter;
                    $counter++;
                }
                
                // Generate password
                $plain_password = $username . '@' . $last_four_digits_phone;
                
                // Hash the password securely
                $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

                // Step 1: Insert into team_members table
                $sql_team = "INSERT INTO team_members (full_name, email, phone, role, status, salary, address, blood_group, image, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt_team = $conn->prepare($sql_team);
                
                if (!$stmt_team) {
                    throw new Exception("Error preparing team member insert statement: " . $conn->error);
                }
                
                $stmt_team->bind_param("sssssdsss", $full_name, $email, $phone, $role, $status, $salary, $address, $blood_group, $image_path);
                
                if (!$stmt_team->execute()) {
                    throw new Exception("Error adding team member: " . $stmt_team->error);
                }
                
                $team_member_id = $conn->insert_id;
                $stmt_team->close();

                // Step 2: Insert into users table (with hashed password)
                $sql_users = "INSERT INTO users (username, email, password,is_active, failed_attempts, created_at) VALUES (?, ?, ?, 1, 0, NOW())";
                $stmt_users = $conn->prepare($sql_users);
                
                if (!$stmt_users) {
                    throw new Exception("Error preparing user insert statement: " . $conn->error);
                }
                
                $stmt_users->bind_param("sss", $username, $email, $hashed_password);
                
                if (!$stmt_users->execute()) {
                    throw new Exception("Error adding user to 'users' table: " . $stmt_users->error);
                }
                
                $user_id = $conn->insert_id;
                $stmt_users->close();
                
                // Commit the transaction
                $conn->commit();
                
                // Log successful user creation
                error_log("New team member created: ID={$user_id}, Username={$username}, Email={$email}, Role={$role}");
                
                // Step 3: Send welcome email with plain password (outside transaction)
                $email_sent = false;
                try {
                    $email_sent = sendWelcomeEmail($email, $role, $full_name, $username, $plain_password);
                    
                    if ($email_sent) {
                        error_log("Welcome email sent successfully to: {$email}");
                    } else {
                        error_log("Failed to send welcome email to: {$email}");
                    }
                } catch (Exception $e) {
                    error_log("Exception sending welcome email: " . $e->getMessage());
                }
                
                // Clear sensitive data from memory
                $plain_password = null;
                $hashed_password = null;
                
                // Set success message
                if ($email_sent) {
                    $message = "Team member '{$full_name}' added successfully and welcome email sent!";
                } else {
                    $message = "Team member '{$full_name}' added successfully, but the welcome email failed to send.";
                }
                
                // Redirect to manage page
                header('Location: manage.php?success=' . urlencode($message));
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = $e->getMessage();
                error_log("Team member creation failed: " . $e->getMessage());
                
                // Clean up uploaded file if database insertion failed
                if ($image_path && file_exists('../' . $image_path)) {
                    unlink('../' . $image_path);
                }
            }
        }
    }
}

// Function to generate a more secure password (optional upgrade)
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '@#$%&*';
    
    $all_chars = $uppercase . $lowercase . $numbers . $special;
    
    // Ensure at least one character from each set
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest randomly
    for ($i = 4; $i < $length; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

// Alternative: If you want to use more secure passwords, replace the password generation with:
// $plain_password = generateSecurePassword(10);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Team Member</title>
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-color: #64748b;
            --background: #0f172a;
            --surface: #1e293b;
            --surface-elevated: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border: #334155;
            --border-light: #475569;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #f87171;
            --inactive: #6b7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
            --radius: 8px;
            --radius-lg: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: var(--surface);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            border-right: 1px solid var(--border);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 32px 24px 24px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .sidebar-nav {
            padding: 24px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }

        .sidebar-nav a.active {
            background: rgba(59, 130, 246, 0.15);
            color: var(--text-primary);
            border-left-color: var(--primary-color);
        }

        .sidebar-nav a.logout {
            margin-top: 24px;
            border-top: 1px solid var(--border);
            padding-top: 24px;
            color: #fca5a5;
        }

        .sidebar-nav a.logout:hover {
            background: rgba(248, 113, 113, 0.1);
            color: #fecaca;
            border-left-color: var(--error);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background: var(--background);
        }

        /* Header */
        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--surface-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            color: var(--text-primary);
        }

        /* Content Area */
        .content {
            padding: 32px;
        }

        /* Success/Error Message */
        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-weight: 500;
            display: <?php echo (!empty($message) || !empty($error_message)) ? 'block' : 'none'; ?>;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-danger {
            background: rgba(248, 113, 113, 0.1);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        /* Form Container */
        .form-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-top: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface-elevated);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
            outline: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
            background-image: none;
            padding-right: 16px;
        }
        .form-input[type="file"] {
            padding-right: 16px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: var(--text-muted);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .main-content {
                margin-left: 0;
            }
            .header {
                padding: 20px 24px;
            }
            .content {
                padding: 20px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 16px 20px;
            }
            .content {
                padding: 16px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.querySelector('.alert');
            if (alertBox && alertBox.style.display !== 'none') {
                setTimeout(() => {
                    alertBox.style.transition = 'opacity 0.5s ease';
                    alertBox.style.opacity = '0';
                    setTimeout(() => alertBox.remove(), 500);
                }, 3000);
            }
        });
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Zorqent Technology</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="../dashboard.php">Dashboard</a>
            <a href="../client/manage.php">Clients</a>
            <a href="../project/manage.php">Projects</a>
            <a href="manage.php" class="active">Team</a>
            <a href="../expense/manage.php">Expenses</a>
                          <a href="../assignment/manage.php">To Do</a>

            <a href="../mail/manage.php">Mail</a>
            <a href="#">Support</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Add New Team Member</h1>
                <div class="header-actions">
                    <a href="manage.php" class="btn btn-primary">Manage Team</a>
                </div>
            </div>
        </header>

        <main class="content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h2 style="margin-bottom: 24px; font-size: 20px; font-weight: 600; color: var(--text-primary);">Member Information</h2>

                <form method="POST" action="add.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="Enter email address" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="Enter phone number">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="role">Role</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="">Select role</option>
                                <option value="Software Engineer">Software Engineer</option>
                                <option value="UI/UX Designer">UI/UX Designer</option>
                                <option value="Project Manager">Project Manager</option>
                                <option value="QA Engineer">QA Engineer</option>
                                <option value="Business Analyst">Business Analyst</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" class="form-textarea" placeholder="Enter full address"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="blood_group">Blood Group</label>
                            <select id="blood_group" name="blood_group" class="form-select">
                                <option value="">Select blood group</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="image">Profile Image</label>
                            <input type="file" id="image" name="image" class="form-input" accept="image/*">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="salary">Salary</label>
                            <input type="number" id="salary" name="salary" class="form-input" placeholder="0.00" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="On Leave">On Leave</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 24px; display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">Add Member</button>
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>