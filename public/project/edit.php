<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection
@include_once '../config.php';

// Check for database connection failure
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

// Initialize message variables
$message = '';
$error_message = '';
$project = null; // Initialize project data
$clients_list = []; // To store clients for the dropdown

// Fetch clients for dropdown
$sql_clients = "SELECT id, company_name FROM clients ORDER BY company_name ASC";
$result_clients = $conn->query($sql_clients);
if ($result_clients) {
    while ($row = $result_clients->fetch_assoc()) {
        $clients_list[] = $row;
    }
} else {
    $error_message = "Error fetching clients for dropdown: " . $conn->error;
}

// Check if project ID is provided in the URL
if (isset($_GET['id'])) {
    $project_id = (int)$_GET['id'];
    
    // Fetch the project details from the database
    $stmt = $conn->prepare("SELECT id, project_name, description, client_id, start_date, status, estimated_budget AS budget FROM projects WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $project = $result->fetch_assoc();
        } else {
            $error_message = "Project not found.";
        }
        $stmt->close();
    } else {
        $error_message = "Error preparing project fetch statement: " . $conn->error;
    }
} else {
    $error_message = "No project ID provided.";
}

// Handle form submission for updating project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project) {
    $project_id_post = (int)$_POST['project_id'];
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $client_id = (int)$_POST['client_id'];
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $status = $conn->real_escape_string($_POST['status']);
    $budget = floatval($_POST['budget'] ?? 0);

    // Basic validation
    if (empty($project_name) || empty($client_id) || empty($start_date)) {
        $error_message = "Project Name, Client, Start Date, and End Date are required fields.";
    } else {
        $sql = "UPDATE projects SET 
                project_name = ?, 
                description = ?, 
                client_id = ?, 
                start_date = ?, 
                status = ?, 
                estimated_budget = ? 
              WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssissdi", $project_name, $description, $client_id, $start_date,  $status, $budget, $project_id_post);
            if ($stmt->execute()) {
                $message = "Project '{$project_name}' updated successfully!";
                // Redirect after successful update to prevent form resubmission
                header('Location: manage.php');
                exit();
            } else {
                $error_message = "Error updating project: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing update statement: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project</title>
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
            /* For select dropdown arrow */
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
            resize: vertical; /* Allow vertical resizing for textareas */
            min-height: 80px;
            background-image: none; /* Remove arrow for textarea */
            padding-right: 16px; /* Reset padding for textarea */
        }


        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: var(--text-muted);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
            .form-group.full-width {
                grid-column: 1 / -1; /* Make specific form groups span full width */
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
            <a href="manage.php" class="active">Projects</a>
            <a href="../team/manage.php">Team</a>
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
                <h1>Edit Project</h1>
                <div class="header-actions">
                    <a href="manage.php" class="btn btn-primary">Manage Projects</a>
                </div>
            </div>
        </header>

        <main class="content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($project): ?>
                <div class="form-container">
                    <h2 class="table-header-title">Edit Project Details (ID: <?php echo htmlspecialchars($project['id']); ?>)</h2>
                    <form method="post">
                        <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id']); ?>">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="project_name" class="form-label">Project Name:</label>
                                <input type="text" id="project_name" name="project_name" class="form-input" required value="<?php echo htmlspecialchars($project['project_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="description" class="form-label">Description:</label>
                                <textarea id="description" name="description" class="form-textarea" rows="4" placeholder="Enter project description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="client_id" class="form-label">Client:</label>
                                <select id="client_id" name="client_id" class="form-select" required>
                                    <option value="">Select a Client</option>
                                    <?php foreach ($clients_list as $client): ?>
                                        <option value="<?php echo htmlspecialchars($client['id']); ?>" <?php echo (isset($project['client_id']) && $project['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status" class="form-label">Status:</label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="Pending" <?php echo (isset($project['status']) && $project['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                     <option value="Planning" <?php echo (isset($project['status']) && $project['status'] == 'Planning') ? 'selected' : ''; ?>>Planning</option>
                                    <option value="In Progress" <?php echo (isset($project['status']) && $project['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo (isset($project['status']) && $project['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="On Hold" <?php echo (isset($project['status']) && $project['status'] == 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                                    <option value="Cancelled" <?php echo (isset($project['status']) && $project['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="start_date" class="form-label">Start Date:</label>
                                <input type="date" id="start_date" name="start_date" class="form-input" required value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>">
                            </div>
                         
                            <div class="form-group">
                                <label for="budget" class="form-label">Budget ($):</label>
                                <input type="number" id="budget" name="budget" class="form-input" step="0.01" min="0" placeholder="0.00" value="<?php echo htmlspecialchars($project['budget'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Update Project</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info" style="display: block;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <div class="form-container">
                    <h2 class="table-header-title">No Project Selected</h2>
                    <p class="text-muted" style="color: var(--text-muted);">Please select a project from the <a href="manage.php" style="color: var(--primary-color); text-decoration: underline;">Project Management</a> page to edit.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>