<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection (adjust as needed)
@include_once '../config.php';

// Initialize message variables
$message = '';
$error_message = '';
$member = null; // Initialize team member data

// Check if team member ID is provided in the URL
if (isset($_GET['id'])) {
    $member_id = (int)$_GET['id'];
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT id, full_name, email, phone, role, status, joined_date, salary, profile_picture FROM team_members WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $member = $result->fetch_assoc();
            } else {
                $error_message = "Team member not found.";
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing team member fetch statement: " . $conn->error;
        }
    } else {
        $error_message = "Database connection not available to fetch team member.";
        // Mock data for demonstration if DB connection isn't real
        if ($member_id == 1) {
            $member = ['id' => 1, 'full_name' => 'Alice Johnson', 'email' => 'alice@example.com', 'phone' => '123-456-7890', 'role' => 'Software Engineer', 'status' => 'Active', 'joined_date' => '2022-01-01 09:00:00', 'salary' => '75000.00', 'profile_picture' => 'default_profile.png'];
        } else if ($member_id == 2) {
            $member = ['id' => 2, 'full_name' => 'Bob Williams', 'email' => 'bob@example.com', 'phone' => '098-765-4321', 'role' => 'UI/UX Designer', 'status' => 'Active', 'joined_date' => '2022-03-10 10:00:00', 'salary' => '60000.00', 'profile_picture' => 'default_profile.png'];
        } else {
            $error_message = "Mock team member not found for ID: " . $member_id;
        }
    }
} else {
    $error_message = "No team member ID provided.";
}

// Handle form submission for updating team member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
    if ($conn instanceof mysqli || ($conn instanceof MockConnection && !$error_message)) {
        $member_id_post = (int)$_POST['member_id'];
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $role = $conn->real_escape_string($_POST['role']);
        $status = $conn->real_escape_string($_POST['status']);
        $salary = floatval($_POST['salary'] ?? 0);

        // Begin transaction to ensure both updates happen together
        $conn->begin_transaction();
        
        try {
            // Update team_members table
            $sql = "UPDATE team_members SET 
                        full_name = ?, 
                        email = ?, 
                        phone = ?, 
                        role = ?, 
                        status = ?, 
                        salary = ? 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing team member update statement: " . $conn->error);
            }
            
            $stmt->bind_param("sssssdi", $full_name, $email, $phone, $role, $status, $salary, $member_id_post);
            if (!$stmt->execute()) {
                throw new Exception("Error updating team member: " . $stmt->error);
            }
            $stmt->close();

            // Update users table based on status
            $is_active = ($status === 'Active') ? 1 : 0;
            $user_sql = "UPDATE users SET is_active = ? WHERE email = ?";
            $user_stmt = $conn->prepare($user_sql);
            
            if (!$user_stmt) {
                throw new Exception("Error preparing user update statement: " . $conn->error);
            }
            
            $user_stmt->bind_param("is", $is_active, $email);
            if (!$user_stmt->execute()) {
                throw new Exception("Error updating user status: " . $user_stmt->error);
            }
            $user_stmt->close();

            // Commit transaction
            $conn->commit();
            
            $message = "Team member '{$full_name}' and user status updated successfully!";
            header('Location: manage.php');
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Cannot update team member: Database connection is not available.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team Member</title>
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
            grid-template-columns: 1fr;
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

        .form-input, .form-select {
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

        .form-input:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
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
                <h1>Edit Team Member</h1>
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

            <?php if ($member): ?>
                <div class="form-container">
                    <h2 class="table-header-title">Edit Team Member Details (ID: <?= htmlspecialchars($member['id']) ?>)</h2>
                    <form method="post">
                        <input type="hidden" name="member_id" value="<?= htmlspecialchars($member['id']) ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name" class="form-label">Full Name:</label>
                                <input type="text" id="full_name" name="full_name" class="form-input" required value="<?= htmlspecialchars($member['full_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" id="email" name="email" class="form-input" required value="<?= htmlspecialchars($member['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone:</label>
                                <input type="tel" id="phone" name="phone" class="form-input" value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="role" class="form-label">Role:</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="Software Engineer" <?= (isset($member['role']) && $member['role'] == 'Software Engineer') ? 'selected' : '' ?>>Software Engineer</option>
                                    <option value="UI/UX Designer" <?= (isset($member['role']) && $member['role'] == 'UI/UX Designer') ? 'selected' : '' ?>>UI/UX Designer</option>
                                    <option value="Project Manager" <?= (isset($member['role']) && $member['role'] == 'Project Manager') ? 'selected' : '' ?>>Project Manager</option>
                                    <option value="QA Engineer" <?= (isset($member['role']) && $member['role'] == 'QA Engineer') ? 'selected' : '' ?>>QA Engineer</option>
                                    <option value="Business Analyst" <?= (isset($member['role']) && $member['role'] == 'Business Analyst') ? 'selected' : '' ?>>Business Analyst</option>
                                    <option value="Other" <?= (isset($member['role']) && $member['role'] == 'Other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status" class="form-label">Status:</label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="Active" <?= (isset($member['status']) && $member['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                    <option value="On Leave" <?= (isset($member['status']) && $member['status'] == 'On Leave') ? 'selected' : '' ?>>On Leave</option>
                                    <option value="Inactive" <?= (isset($member['status']) && $member['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="salary" class="form-label">Salary:</label>
                                <input type="number" id="salary" name="salary" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($member['salary'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Update Member</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info" style="display: block;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <div class="form-container">
                    <h2 class="table-header-title">No Team Member Selected</h2>
                    <p class="text-muted" style="color: var(--text-muted);">Please select a team member from the <a href="manage.php" style="color: var(--primary-color); text-decoration: underline;">Team Management</a> page to edit.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
