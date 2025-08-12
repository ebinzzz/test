<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$task = null;

// Mock database connection for demonstration purposes
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
            return false;
        }
        public function prepare($sql) {
            if (strpos($sql, "SELECT t.id, t.title, t.description, t.assigned_to, t.created_by, t.priority, t.status, t.due_date, t.estimated_hours, t.tags, t.notes, t.created_at, t.updated_at, tm.full_name as assigned_to_name, creator.full_name as created_by_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id JOIN team_members creator ON t.created_by = creator.id WHERE t.id = ?") !== false) {
                return new class($this) {
                    private $mock_conn;
                    private $bound_params;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) { $this->bound_params = $params; }
                    public function execute() { return true; }
                    public function get_result() {
                        return new MockResult([
                            ['id' => 101, 'title' => 'Implement User Authentication', 'description' => 'Develop and test the user login and registration system.', 'assigned_to' => 1, 'created_by' => 1, 'priority' => 'High', 'status' => 'In Progress', 'due_date' => '2025-08-20', 'estimated_hours' => 40.0, 'tags' => 'auth, security, frontend', 'notes' => 'Ensure robust password hashing.', 'created_at' => '2025-08-01 10:00:00', 'updated_at' => '2025-08-10 14:30:00', 'assigned_to_name' => 'Alice Johnson', 'created_by_name' => 'Alice Johnson']
                        ]);
                    }
                    public function close() {}
                };
            }
            if (strpos($sql, "UPDATE tasks SET status = ?, priority = ?, updated_at = NOW() WHERE id = ?") !== false) {
                return new class($this) {
                    private $mock_conn;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) {}
                    public function execute() { return true; }
                    public function get_result() { return new MockResult([]); }
                    public function close() {}
                };
            }
            return false;
        }
    }
    $conn = new MockConnection();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = "No task ID specified.";
} else {
    $task_id = (int)$_GET['id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
        $status = trim($_POST['status'] ?? '');
        $priority = trim($_POST['priority'] ?? '');

        if (!empty($status) && !empty($priority)) {
            if ($conn instanceof mysqli) {
                $sql_update = "UPDATE tasks SET status = ?, priority = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                if ($stmt) {
                    $stmt->bind_param("ssi", $status, $priority, $task_id);
                    if ($stmt->execute()) {
                        $message = "Task #" . $task_id . " updated successfully!";
                        header("refresh:2;url=update_assign_status.php");
                    } else {
                        $error_message = "Error updating task: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing update statement: " . $conn->error;
                }
            } else {
                $message = "Task #" . $task_id . " updated successfully! (Mock mode)";
                header("refresh:2;url=update_assign_status.php");
            }
        } else {
            $error_message = "Invalid data submitted for task update.";
        }
    }

    if ($conn instanceof mysqli) {
        $sql_fetch = "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.estimated_hours, t.tags, t.notes, t.created_at, t.updated_at, tm.full_name as assigned_to_name, creator.last_name as created_by_name
                      FROM tasks t
                      JOIN team_members tm ON t.assigned_to = tm.id
                      JOIN users creator ON t.created_by = creator.id
                      WHERE t.id = ?";
        $stmt = $conn->prepare($sql_fetch);
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $task = $result->fetch_assoc();
            } else {
                $error_message = "Task with ID " . htmlspecialchars($task_id) . " not found.";
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing statement to fetch task details: " . $conn->error;
        }
    } else {
        $mock_result = $conn->prepare("SELECT t.id, t.title, t.description, t.assigned_to, t.created_by, t.priority, t.status, t.due_date, t.estimated_hours, t.tags, t.notes, t.created_at, t.updated_at, tm.full_name as assigned_to_name, creator.full_name as created_by_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id JOIN team_members creator ON t.created_by = creator.id WHERE t.id = ?");
        if ($mock_result) {
            $result_obj = $mock_result->get_result();
            $task = $result_obj->fetch_assoc();
            if ($task && $task['id'] != $task_id) {
                $task = null;
                $error_message = "Task with ID " . htmlspecialchars($task_id) . " not found in mock data.";
            }
        }
    }
}
include_once 'common_layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - <?php echo htmlspecialchars($task['title'] ?? 'Not Found'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles specific to this page to prevent conflicts */
        .task-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
        }
        .task-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .task-card h2 {
            color: #1e40af;
            margin-top: 0;
            font-size: 2em;
        }
        .task-card p {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .task-card strong {
            font-weight: 600;
            color: #1f2937;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .alert.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .status-indicator {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-Pending { background-color: #fef3c7; color: #92400e; }
        .status-Assigned { background-color: #dbeafe; color: #1e40af; }
        .status-InProgress { background-color: #d1fae5; color: #065f46; }
        .status-OnHold { background-color: #bcbad5ff; color: #510a87ff; }
        .status-Completed { background-color: #cffafe; color: #0e7490; }
        .priority-High { color: #ef4444; }
        .priority-Medium { color: #f59e0b; }
        .priority-Low { color: #10b981; }

        .task-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-card {
            background-color: #f3f4f6; /* Secondary background color for cards */
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .info-card h4 {
            margin: 0 0 10px;
            font-size: 0.9em;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
        }
        .info-card p {
            margin: 0;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        /* Specific styling for the Description card to allow more space */
        .info-card.description-card {
            grid-column: 1 / -1; /* Spans across all columns */
        }
        .description-card p {
            font-weight: 400; /* Regular font weight for long text */
        }

        .update-form-flex {
            display: flex;
            gap: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
        }
        .update-form-flex .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .update-form-flex .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .update-form-flex .form-group select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            width: 100%;
        }

        .update-submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: 600;
            margin-top: 10px;
        }
        .update-submit-btn:hover {
            background-color: #1e40af;
        }
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #6b7280;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.2s;
            font-weight: 600;
        }
        .back-btn:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body>
<div class="task-container">
    <?php if ($message): ?>
        <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($task): ?>
        <h1 style="text-align: center; font-size: 2.5em; font-weight: 700;">Task Details</h1>
        <div class="task-card">
            <h2><?php echo htmlspecialchars($task['title']); ?></h2>
            
            <div class="task-info-grid">
                <div class="info-card description-card">
                    <h4>Description</h4>
                    <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                </div>
                <div class="info-card">
                    <h4>Assigned To</h4>
                    <p><?php echo htmlspecialchars($task['assigned_to_name']); ?></p>
                </div>
                <div class="info-card">
                    <h4>Due Date</h4>
                    <p><?php echo htmlspecialchars(date('F j, Y', strtotime($task['due_date']))); ?></p>
                </div>
                <div class="info-card">
                    <h4>Priority</h4>
                    <p><span class="priority-<?php echo htmlspecialchars($task['priority']); ?>"><?php echo htmlspecialchars($task['priority']); ?></span></p>
                </div>
                <div class="info-card">
                    <h4>Status</h4>
                    <p><span class="status-indicator status-<?php echo str_replace(' ', '', htmlspecialchars($task['status'])); ?>"><?php echo htmlspecialchars($task['status']); ?></span></p>
                </div>
                <div class="info-card">
                    <h4>Estimated Hours</h4>
                    <p><?php echo htmlspecialchars($task['estimated_hours']); ?></p>
                </div>
                <div class="info-card">
                    <h4>Tags</h4>
                    <p><?php echo htmlspecialchars($task['tags']); ?></p>
                </div>
                <div class="info-card">
                    <h4>Created By</h4>
                    <p><?php echo htmlspecialchars($task['created_by_name']); ?></p>
                </div>
                <div class="info-card">
                    <h4>Created At</h4>
                    <p><small><?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($task['created_at']))); ?></small></p>
                </div>
                <div class="info-card">
                    <h4>Last Updated</h4>
                    <p><small><?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($task['updated_at']))); ?></small></p>
                </div>
            </div>
            
            <form action="view_task.php?id=<?php echo htmlspecialchars($task['id']); ?>" method="POST" class="update-form">
                <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                
                <div class="update-form-flex">
                    <div class="form-group">
                        <label for="status">Update Status:</label>
                        <select name="status" id="status" required>
                            <option value="Assigned" <?php echo ($task['status'] == 'Assigned') ? 'selected' : ''; ?>>Assigned</option>
                            <option value="In Progress" <?php echo ($task['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Pending" <?php echo ($task['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Completed" <?php echo ($task['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="OnHold" <?php echo ($task['status'] == 'OnHold') ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="priority">Update Priority:</label>
                        <select name="priority" id="priority" required>
                            <option value="Low" <?php echo ($task['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo ($task['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo ($task['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                </div>
                
                <input type="submit" name="update_task" value="Update Task" class="update-submit-btn">
            </form>
        </div>
        <a href="update_assign_status.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Tasks</a>
    <?php else: ?>
        <p>No task data available.</p>
    <?php endif; ?>
</div>

</body>
</html>