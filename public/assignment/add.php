<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection
include_once '../config.php';

// Initialize message variables
$message = '';
$error_message = '';

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    $error_message = "Database connection not established. Please check 'config.php'.";
    exit($error_message);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $priority = $_POST['priority'] ?? 'Medium';
    $status = $_POST['status'] ?? 'Pending';
    $due_date = $_POST['due_date'] ?? '';
    $estimated_hours = (float)($_POST['estimated_hours'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Get current user ID from session
    $created_by = null;
    
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $created_by = (int)$_SESSION['user_id'];
    }
    
    // Debug: Uncomment these lines temporarily to see what's happening
    // echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
    // echo "Created by value: " . ($created_by ?? 'NULL') . "<br>";
    // echo "Session contents: " . print_r($_SESSION, true) . "<br>";

    // Validation
    $errors = [];
    if (empty($title)) {
        $errors[] = "Task title is required.";
    }
    if (empty($description)) {
        $errors[] = "Task description is required.";
    }
    if ($assigned_to <= 0) {
        $errors[] = "Please select a team member to assign this task.";
    }
    if (empty($due_date)) {
        $errors[] = "Due date is required.";
    } elseif (strtotime($due_date) < strtotime('today')) {
        $errors[] = "Due date cannot be in the past.";
    }
    if ($estimated_hours < 0) {
        $errors[] = "Estimated hours cannot be negative.";
    }
   
    
    // Validate assigned_to exists in team_members
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT id FROM team_members WHERE id = ? AND status = 'Active'");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $assigned_to);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows == 0) {
                $errors[] = "The selected team member is not valid or not active.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Error validating team member: " . $conn->error;
        }
    }

    // Insert task
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert task with created_by from session
            $sql_insert = "INSERT INTO tasks (title, description, assigned_to, created_by, priority, status, due_date, estimated_hours, tags, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql_insert);
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("ssiisssdss", $title, $description, $assigned_to, $created_by, $priority, $status, $due_date, $estimated_hours, $tags, $notes);
            
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            
            $stmt->close();
            $conn->commit();
            
            $message = "Task created successfully!";
            header("refresh:2;url=manage.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error creating task: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch active team members for assignment dropdown
$team_members = [];
$sql = "SELECT id, full_name, email, role FROM team_members WHERE status = 'Active' ORDER BY full_name ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $team_members[] = $row;
    }
} else {
    $error_message = "Error fetching team members: " . $conn->error;
}

// Set default values for form fields
$form_data = [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
    'assigned_to' => $_POST['assigned_to'] ?? '',
    'priority' => $_POST['priority'] ?? 'Medium',
    'status' => $_POST['status'] ?? 'Pending',
    'due_date' => $_POST['due_date'] ?? '',
    'estimated_hours' => $_POST['estimated_hours'] ?? '',
    'tags' => $_POST['tags'] ?? '',
    'notes' => $_POST['notes'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task - Zorqent Technology</title>
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
            --critical: #dc2626;
            --high: #f97316;
            --medium: #eab308;
            --low: #22c55e;
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

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary-color);
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

        .btn-lg {
            padding: 12px 24px;
            font-size: 16px;
        }

        /* Content Area */
        .content {
            padding: 32px;
         width:100%;
            margin: 0 auto;
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

        /* Form Card */
        .form-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .form-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-elevated);
        }

        .form-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-content {
            padding: 32px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group.required .form-label::after {
            content: '*';
            color: var(--error);
            margin-left: 4px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--background);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }

        .form-help {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Priority Preview */
        .priority-preview {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-top: 8px;
        }

        .priority-critical {
            background: rgba(220, 38, 38, 0.2);
            color: #fca5a5;
        }
        .priority-high {
            background: rgba(249, 115, 22, 0.2);
            color: #fdba74;
        }
        .priority-medium {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
        }
        .priority-low {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        /* Status Preview */
        .status-preview {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-top: 8px;
        }

        .status-pending {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-muted);
        }
        .status-in-progress {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }
        .status-completed {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        /* Tag Input */
        .tag-input-container {
            position: relative;
        }

        .tag-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-top: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .tag-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .tag-suggestion:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--text-primary);
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
            .form-content {
                padding: 24px;
            }
            .form-row {
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
            .form-content {
                padding: 20px;
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
                }, 5000);
            }

            // Priority preview
            const prioritySelect = document.getElementById('priority');
            const priorityPreview = document.getElementById('priority-preview');
            
            function updatePriorityPreview() {
                const priority = prioritySelect.value.toLowerCase();
                priorityPreview.className = `priority-preview priority-${priority}`;
                priorityPreview.textContent = prioritySelect.value;
            }
            
            prioritySelect.addEventListener('change', updatePriorityPreview);
            updatePriorityPreview();

            // Status preview
            const statusSelect = document.getElementById('status');
            const statusPreview = document.getElementById('status-preview');
            
            function updateStatusPreview() {
                const status = statusSelect.value.toLowerCase().replace(' ', '-');
                statusPreview.className = `status-preview status-${status}`;
                statusPreview.textContent = statusSelect.value;
            }
            
            statusSelect.addEventListener('change', updateStatusPreview);
            updateStatusPreview();

            // Tag suggestions
            const tagInput = document.getElementById('tags');
            const tagSuggestions = document.getElementById('tag-suggestions');
            const commonTags = [
                'frontend', 'backend', 'ui/ux', 'testing', 'bugfix', 'feature',
                'database', 'api', 'security', 'performance', 'mobile', 'web',
                'documentation', 'deployment', 'maintenance', 'research', 'review'
            ];

            tagInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                const lastTag = value.split(',').pop().trim();
                
                if (lastTag.length >= 1) {
                    const matches = commonTags.filter(tag => 
                        tag.includes(lastTag) && !value.includes(tag)
                    );
                    
                    if (matches.length > 0) {
                        tagSuggestions.innerHTML = matches.map(tag => 
                            `<div class="tag-suggestion" data-tag="${tag}">${tag}</div>`
                        ).join('');
                        tagSuggestions.style.display = 'block';
                    } else {
                        tagSuggestions.style.display = 'none';
                    }
                } else {
                    tagSuggestions.style.display = 'none';
                }
            });

            tagSuggestions.addEventListener('click', function(e) {
                if (e.target.classList.contains('tag-suggestion')) {
                    const tag = e.target.dataset.tag;
                    const currentTags = tagInput.value.split(',').map(t => t.trim());
                    currentTags[currentTags.length - 1] = tag;
                    tagInput.value = currentTags.join(', ') + ', ';
                    tagSuggestions.style.display = 'none';
                    tagInput.focus();
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!tagInput.contains(e.target) && !tagSuggestions.contains(e.target)) {
                    tagSuggestions.style.display = 'none';
                }
            });

            // Form validation
            const form = document.getElementById('task-form');
            form.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const description = document.getElementById('description').value.trim();
                const assignedTo = document.getElementById('assigned_to').value;
                const dueDate = document.getElementById('due_date').value;
                
                if (!title || !description || !assignedTo || !dueDate) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
                
                if (dueDate && new Date(dueDate) < new Date().setHours(0,0,0,0)) {
                    e.preventDefault();
                    alert('Due date cannot be in the past.');
                }
            });

            // Auto-resize textarea
            const textarea = document.getElementById('description');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
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
            <a href="../team/manage.php">Team</a>
            <a href="../expense/manage.php">Expenses</a>
  
            <a href="manage.php" class="active">To Do</a>
            <a href="../mail/manage.php">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <div>
                    <h1>Create New Task</h1>
                    <div class="breadcrumb">
                        <a href="manage.php">Tasks</a>
                        <span>›</span>
                        <span>Create New Task</span>
                    </div>
                </div>
                <div>
                    <a href="manage.php" class="btn btn-secondary">← Back to Tasks</a>
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
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-header">
                    <h2>Task Details</h2>
                    <p>Create a new task and assign it to a team member</p>
                </div>
                <div class="form-content">
                    <form method="post" id="task-form">
                        <div class="form-group required">
                            <label for="title" class="form-label">Task Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                class="form-input" 
                                placeholder="Enter a clear and descriptive task title"
                                value="<?php echo htmlspecialchars($form_data['title']); ?>"
                                required
                            >
                            <div class="form-help">Keep it concise but descriptive (e.g., "Implement user authentication system")</div>
                        </div>

                        <div class="form-group required">
                            <label for="description" class="form-label">Description</label>
                            <textarea 
                                id="description" 
                                name="description" 
                                class="form-textarea" 
                                placeholder="Provide detailed information about what needs to be done, including requirements, acceptance criteria, and any relevant context"
                                required
                            ><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                            <div class="form-help">Include all necessary details, requirements, and acceptance criteria</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group required">
                                <label for="assigned_to" class="form-label">Assign To</label>
                                <select id="assigned_to" name="assigned_to" class="form-select" required>
                                    <option value="">Select a team member</option>
                                    <?php foreach ($team_members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" <?php echo ($form_data['assigned_to'] == $member['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['full_name']); ?> - <?php echo htmlspecialchars($member['role']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help">Choose the team member who will be responsible for this task</div>
                            </div>

                            <div class="form-group required">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input 
                                    type="date" 
                                    id="due_date" 
                                    name="due_date" 
                                    class="form-input" 
                                    value="<?php echo htmlspecialchars($form_data['due_date']); ?>"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    required
                                >
                                <div class="form-help">When should this task be completed?</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="priority" class="form-label">Priority</label>
                                <select id="priority" name="priority" class="form-select">
                                    <option value="Low" <?php echo ($form_data['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo ($form_data['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo ($form_data['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                                    <option value="Critical" <?php echo ($form_data['priority'] === 'Critical') ? 'selected' : ''; ?>>Critical</option>
                                </select>
                                <span id="priority-preview" class="priority-preview priority-medium">Medium</span>
                                <div class="form-help">How urgent is this task?</div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label">Initial Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="Pending" <?php echo ($form_data['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo ($form_data['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo ($form_data['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <span id="status-preview" class="status-preview status-pending">Pending</span>
                                <div class="form-help">What's the starting status of this task?</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estimated_hours" class="form-label">Estimated Hours</label>
                            <input 
                                type="number" 
                                id="estimated_hours" 
                                name="estimated_hours" 
                                class="form-input" 
                                placeholder="0.0"
                                min="0"
                                step="0.5"
                                value="<?php echo htmlspecialchars($form_data['estimated_hours']); ?>"
                            >
                            <div class="form-help">How many hours do you estimate this task will take? (Optional but recommended for planning)</div>
                        </div>

                        <div class="form-group">
                            <label for="tags" class="form-label">Tags</label>
                            <div class="tag-input-container">
                                <input 
                                    type="text" 
                                    id="tags" 
                                    name="tags" 
                                    class="form-input" 
                                    placeholder="e.g., frontend, api, testing, bugfix"
                                    value="<?php echo htmlspecialchars($form_data['tags']); ?>"
                                >
                                <div id="tag-suggestions" class="tag-suggestions"></div>
                            </div>
                            <div class="form-help">Separate multiple tags with commas. Tags help categorize and filter tasks</div>
                        </div>

                        <div class="form-group">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea 
                                id="notes" 
                                name="notes" 
                                class="form-textarea" 
                                placeholder="Any additional information, links, references, or context that might be helpful"
                                rows="4"
                            ><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                            <div class="form-help">Include any additional context, links, or special instructions</div>
                        </div>

                        <div class="form-actions">
                            <a href="manage.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="create_task" class="btn btn-primary btn-lg">
                                Create Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Task Creation Tips -->
            <div class="form-card" style="margin-top: 32px;">
                <div class="form-header">
                    <h2>💡 Task Creation Tips</h2>
                    <p>Best practices for creating effective tasks</p>
                </div>
                <div class="form-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                        <div>
                            <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 12px;">📝 Clear Titles</h3>
                            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 8px;">Use action-oriented, specific titles:</p>
                            <ul style="color: var(--text-muted); font-size: 13px; margin-left: 20px;">
                                <li>✅ "Implement user login functionality"</li>
                                <li>✅ "Fix checkout page mobile layout"</li>
                                <li>❌ "Login stuff"</li>
                                <li>❌ "Website fixes"</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 12px;">📋 Detailed Descriptions</h3>
                            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 8px;">Include:</p>
                            <ul style="color: var(--text-muted); font-size: 13px; margin-left: 20px;">
                                <li>What needs to be done</li>
                                <li>Acceptance criteria</li>
                                <li>Relevant links or resources</li>
                                <li>Dependencies or requirements</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 12px;">⏰ Smart Priorities</h3>
                            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 8px;">Choose priority based on:</p>
                            <ul style="color: var(--text-muted); font-size: 13px; margin-left: 20px;">
                                <li><span style="color: var(--critical);">Critical:</span> Blocking issues</li>
                                <li><span style="color: var(--high);">High:</span> Important features</li>
                                <li><span style="color: var(--medium);">Medium:</span> Regular tasks</li>
                                <li><span style="color: var(--low);">Low:</span> Nice-to-haves</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 style="color: var(--text-primary); font-size: 16px; margin-bottom: 12px;">🏷️ Useful Tags</h3>
                            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 8px;">Common tag categories:</p>
                            <ul style="color: var(--text-muted); font-size: 13px; margin-left: 20px;">
                                <li>Technology: frontend, backend, api</li>
                                <li>Type: feature, bugfix, maintenance</li>
                                <li>Area: ui/ux, database, testing</li>
                                <li>Platform: web, mobile, desktop</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>