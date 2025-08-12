<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

@include_once '../config.php';

// Initialize message variables
$message = '';
$error_message = '';
$task = null;
$team_members = [];

// Mock database connection (same as manage.php)
if (!isset($conn)) {
    $error_message = "Database connection not established. Please check 'config.php'.";
    class MockResult {
        public $data;
        public $fetch_assoc_index = 0;
        public function __construct($data = []) {
            $this->data = $data;
        }
        public function fetch_assoc() {
            if ($this->fetch_assoc_index < count($this->data)) {
                return $this->data[$this->fetch_assoc_index++];
            }
            return null;
        }
        public function num_rows() {
            return count($this->data);
        }
    }
    class MockConnection {
        public function real_escape_string($str) { return $str; }
        public function query($sql) {
            error_log("Attempted query on mock connection: " . $sql);
            if (strpos($sql, 'SELECT id, full_name FROM team_members') !== false) {
                return new MockResult([
                    ['id' => 1, 'full_name' => 'Alice Johnson'],
                    ['id' => 2, 'full_name' => 'Bob Williams'],
                    ['id' => 3, 'full_name' => 'Charlie Brown'],
                    ['id' => 4, 'full_name' => 'Diana Prince']
                ]);
            }
            return false;
        }
        public function error() { return "Mock DB Error: Connection not real."; }
        public function begin_transaction() {}
        public function commit() {}
        public function rollback() {}
        public function prepare($sql) {
            // Mock prepared statement for UPDATE task
            if (strpos($sql, "UPDATE tasks SET") !== false) {
                return new class($this) {
                    private $mock_conn;
                    private $bound_params;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) { $this->bound_params = $params; }
                    public function execute() { return true; }
                    public function get_result() { return new MockResult([]); }
                    public function fetch_assoc() { return false; }
                    public function close() {}
                };
            }
            // Mock prepared statement for SELECT task
            if (strpos($sql, "SELECT * FROM tasks WHERE id = ?") !== false || strpos($sql, "SELECT t.id, t.title, t.description, t.assigned_to, t.created_by, t.priority, t.status, t.due_date, t.estimated_hours, t.actual_hours, t.tags, t.notes, t.created_at, t.updated_at, tm.full_name as assigned_to_name, creator.full_name as created_by_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id JOIN team_members creator ON t.created_by = creator.id WHERE t.id = ?") !== false) {
                return new class($this) {
                    private $mock_conn;
                    private $bound_params;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) { $this->bound_params = $params; }
                    public function execute() { return true; }
                    public function get_result() {
                        $task_id = $this->bound_params[1];
                        // Mock data for a single task
                        $mock_task_data = [
                            'id' => $task_id,
                            'title' => 'Sample Task ' . $task_id,
                            'description' => 'This is a mock task description for task ' . $task_id . '.',
                            'assigned_to' => 1,
                            'created_by' => 3,
                            'priority' => 'High',
                            'status' => 'In Progress',
                            'due_date' => '2025-08-30',
                            'progress' => 50,
                            'estimated_hours' => 10.0,
                            'actual_hours' => 5.0,
                            'tags' => 'mock,data',
                            'notes' => 'Some mock notes for this task.',
                            'created_at' => '2025-08-01 10:00:00',
                            'updated_at' => '2025-08-10 14:30:00',
                            'assigned_to_name' => 'Alice Johnson',
                            'created_by_name' => 'Charlie Brown'
                        ];
                        return new MockResult([$mock_task_data]);
                    }
                    public function fetch_assoc() { return false; }
                    public function close() {}
                };
            }
            // Mock prepared statement for INSERT comment
            if (strpos($sql, "INSERT INTO task_comments") !== false) {
                return new class($this) {
                    private $mock_conn;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) { /* simulate binding */ }
                    public function execute() { return true; /* simulate success */ }
                    public function close() {}
                };
            }
            return false;
        }
    }
    $conn = new MockConnection();
}

// Handle form submission for updating a task
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_task'])) {
    $id = (int)$_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $assigned_to = (int)$_POST['assigned_to'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $due_date = $_POST['due_date'];
    $progress = (int)$_POST['progress'];
    $estimated_hours = (float)$_POST['estimated_hours'];
    $actual_hours = (float)$_POST['actual_hours'];
    $tags = $_POST['tags'];

    // Input validation
    if (empty($title) || empty($description) || empty($due_date)) {
        $error_message = "Title, Description, and Due Date are required fields.";
    } else {
        $sql_update = "UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, due_date = ?, progress = ?, estimated_hours = ?, actual_hours = ?, tags = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        
        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare($sql_update);
            if ($stmt) {
                $stmt->bind_param("ssisssiddsi", $title, $description, $assigned_to, $priority, $status, $due_date, $progress, $estimated_hours, $actual_hours, $tags, $id);
                if ($stmt->execute()) {
                    $message = "Task updated successfully!";
                    // Redirect after a short delay to see the message
                    header("Refresh:3; url=manage.php");
                } else {
                    $error_message = "Error updating task: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else {
            $message = "Task updated successfully! (Mock mode)";
            header("Refresh:3; url=manage.php");
        }
    }
}

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_comment'])) {
    $task_id = (int)$_POST['task_id'];
    $comment_text = trim($_POST['comment_text'] ?? '');
    // In a real application, you'd get the actual user ID from the session
    $user_id = $_SESSION[''] ?? 1; // Mock user ID

    if (!empty($comment_text)) {
        // SQL to insert comment (adjust table/columns as needed)
     $sql_insert_comment = "INSERT INTO task_comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        
        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare($sql_insert_comment);
            if ($stmt) {
                $stmt->bind_param("iis", $task_id, $user_id, $comment_text);
                if ($stmt->execute()) {
                    $message = "Comment added successfully!";
                } else {
                    $error_message = "Error adding comment: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing comment statement: " . $conn->error;
            }
        } else {
            $message = "Comment added successfully! (Mock mode)";
        }
    } else {
        $error_message = "Comment cannot be empty.";
    }
}

// Fetch task data for the form
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $task_id = (int)$_GET['id'];
    // Adjust SQL query if you also want to fetch assigned_to_name and created_by_name here
        $sql_fetch_task = "
SELECT 
    t.id, 
    t.title, 
    t.description, 
    t.assigned_to, 
    t.created_by, 
    t.priority, 
    t.status, 
    t.due_date, 
    t.progress, 
    t.estimated_hours, 
    t.actual_hours, 
    t.tags, 
    t.notes, 
    t.created_at, 
    t.updated_at, 
    tm.full_name AS assigned_to_name, 
    creator.last_name AS created_by_name
FROM 
    tasks t
JOIN 
    team_members tm ON t.assigned_to = tm.id
JOIN 
    users creator ON t.created_by = creator.id
WHERE 
    t.id = ?
";

    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare($sql_fetch_task);
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $task = $result->fetch_assoc();
            } else {
                $error_message = "Task not found.";
            }
            $stmt->close();
        }
    } else {
        // Mock data for display using the existing mock prepared statement
        $mock_stmt = $conn->prepare($sql_fetch_task);
        if ($mock_stmt) {
            $mock_result_obj = $mock_stmt->get_result();
            $task_data_array = $mock_result_obj->data;
            // Find the specific task by ID in mock data
            foreach ($task_data_array as $mock_task) {
                if ($mock_task['id'] == $task_id) {
                    $task = $mock_task;
                    break;
                }
            }
            if (!$task) {
                 $error_message = "Task not found. (Mock mode)";
            }
        } else {
            $error_message = "Mock task fetch failed.";
        }
    }
} else {
    $error_message = "No task ID provided.";
}

// Fetch team members for the dropdown
if ($conn instanceof mysqli) {
    $sql = "SELECT id, full_name FROM team_members WHERE status = 'Active' ORDER BY full_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $team_members[] = $row;
        }
    }
} else {
    $mock_members_obj = $conn->query("SELECT id, full_name FROM team_members");
    if ($mock_members_obj && property_exists($mock_members_obj, 'data')) {
        $team_members = $mock_members_obj->data;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - Zorqent Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--background); color: var(--text-primary); line-height: 1.6; font-size: 14px; display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: var(--surface); height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; border-right: 1px solid var(--border); z-index: 1000; }
        .sidebar-header { padding: 32px 24px 24px; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 20px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.025em; }
        .sidebar-nav { padding: 24px 0; }
        .sidebar-nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { background: rgba(59, 130, 246, 0.1); color: var(--text-primary); border-left-color: var(--primary-color); }
        .sidebar-nav a.active { background: rgba(59, 130, 246, 0.15); color: var(--text-primary); border-left-color: var(--primary-color); }
        .sidebar-nav a.logout { margin-top: 24px; border-top: 1px solid var(--border); padding-top: 24px; color: #fca5a5; }
        .sidebar-nav a.logout:hover { background: rgba(248, 113, 113, 0.1); color: #fecaca; border-left-color: var(--error); }
        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; background: var(--background); }
        .header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 24px 32px; position: sticky; top: 0; z-index: 100; box-shadow: var(--shadow-sm); }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .header h1 { font-size: 24px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.025em; }
        .content { padding: 32px; }
        .alert { padding: 16px; border-radius: var(--radius); margin-bottom: 24px; font-weight: 500; display: <?php echo (!empty($message) || !empty($error_message)) ? 'flex' : 'none'; ?>; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-danger { background: rgba(248, 113, 113, 0.1); color: var(--error); border: 1px solid rgba(248, 113, 113, 0.2); }
        .form-container { background: var(--surface); padding: 32px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); width:100%; margin: 0 auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-secondary); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px; background: var(--surface-elevated); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-primary); font-size: 14px; }
        .form-textarea { resize: vertical; min-height: 120px; }
        .form-actions { margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px; }
        .btn { display: inline-flex; align-items: center; padding: 10px 16px; border: none; border-radius: var(--radius); font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; transition: all 0.2s ease; outline: none; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--surface-elevated); color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--border); color: var(--text-primary); }

        /* Comment Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 2000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            backdrop-filter: blur(5px); /* Blur effect */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--surface);
            margin: auto;
            padding: 30px;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-close {
            color: var(--text-secondary);
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
        }

        .modal h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.8em;
            text-align: center;
        }

        .modal-form-group {
            margin-bottom: 20px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .modal-textarea {
            width: 100%;
            padding: 12px;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .modal-submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .modal-submit-btn:hover {
            background-color: #0e9f6e; /* darker accent */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; border-right: none; }
            .main-content { margin-left: 0; }
            .header { padding: 16px; }
            .header h1 { font-size: 20px; }
            .header-content { flex-direction: column; align-items: flex-start; }
            .form-grid { grid-template-columns: 1fr; }
            .content { padding: 16px; }
            .modal-content { width: 95%; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Zorqent Admin</h2>
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
                <h1>Edit Task</h1>
                <?php if ($task): ?>
                    <a href="view_comments.php?task_id=<?php echo htmlspecialchars($task['id']); ?>" class="btn btn-secondary">
                        <i class="fas fa-comment-alt" style="margin-right: 8px;"></i> View Comments
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($task): ?>
            <div class="form-container">
                <form action="edit.php?id=<?php echo $task['id']; ?>" method="POST">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($task['id']); ?>">
                    <div class="form-group">
                        <label for="title">Task Title</label>
                        <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-textarea" required><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="assigned_to">Assigned To</label>
                            <select id="assigned_to" name="assigned_to" class="form-select" required>
                                <?php foreach ($team_members as $member): ?>
                                    <option value="<?php echo htmlspecialchars($member['id']); ?>" <?php echo ($member['id'] == $task['assigned_to']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-select" required>
                                <option value="Critical" <?php echo ($task['priority'] == 'Critical') ? 'selected' : ''; ?>>Critical</option>
                                <option value="High" <?php echo ($task['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                                <option value="Medium" <?php echo ($task['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="Low" <?php echo ($task['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="Pending" <?php echo ($task['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo ($task['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="On Hold" <?php echo ($task['status'] == 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                                <option value="Completed" <?php echo ($task['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo ($task['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-input" value="<?php echo htmlspecialchars($task['due_date']); ?>" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="progress">Progress (%)</label>
                            <input type="number" id="progress" name="progress" class="form-input" min="0" max="100" value="<?php echo htmlspecialchars($task['progress']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="estimated_hours">Estimated Hours</label>
                            <input type="number" id="estimated_hours" name="estimated_hours" class="form-input" step="0.1" value="<?php echo htmlspecialchars($task['estimated_hours']); ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="actual_hours">Actual Hours</label>
                            <input type="number" id="actual_hours" name="actual_hours" class="form-input" step="0.1" value="<?php echo htmlspecialchars($task['actual_hours']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="tags">Tags (comma-separated)</label>
                            <input type="text" id="tags" name="tags" class="form-input" value="<?php echo htmlspecialchars($task['tags']); ?>">
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="manage.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_task" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Comment Modal HTML -->
  
    <script>
        function openCommentModal(taskId) {
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('commentModal').style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scrolling background
        }

        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('commentModal');
            if (event.target == modal) {
                closeCommentModal();
            }
        }

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCommentModal();
            }
        });
    </script>
</body>
</html>