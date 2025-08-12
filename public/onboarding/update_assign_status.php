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

// Mock database connection if config.php is not found or connection fails
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

            // Mock tasks data for fetching, including 'assigned_to_name' for the JOIN scenario
            if (strpos($sql, 'SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id') !== false) {
                 return new MockResult([
                    ['id' => 101, 'title' => 'Implement User Authentication', 'description' => 'Develop and test the user login and registration system.', 'assigned_to' => 1, 'assigned_to_name' => 'Alice Johnson', 'priority' => 'High', 'status' => 'In Progress', 'due_date' => '2025-08-20'],
                    ['id' => 102, 'title' => 'Design Dashboard UI', 'description' => 'Create wireframes and mockups for the admin dashboard.', 'assigned_to' => 2, 'assigned_to_name' => 'Bob Williams', 'priority' => 'Medium', 'status' => 'Pending', 'due_date' => '2025-08-25'],
                    ['id' => 103, 'title' => 'Write API Documentation', 'description' => 'Document all REST API endpoints for developers.', 'assigned_to' => 4, 'assigned_to_name' => 'Diana Prince', 'priority' => 'Low', 'status' => 'Assigned', 'due_date' => '2025-09-01'],
                ]);
            }
            // Fallback mock for simple task fetching (if no join is implied, though the above is preferred)
            if (strpos($sql, 'SELECT id, title, description, assigned_to, priority, status, due_date FROM tasks') !== false) {
                return new MockResult([
                    ['id' => 101, 'title' => 'Implement User Authentication', 'description' => 'Develop and test the user login and registration system.', 'assigned_to' => 1, 'priority' => 'High', 'status' => 'In Progress', 'due_date' => '2025-08-20'],
                    ['id' => 102, 'title' => 'Design Dashboard UI', 'description' => 'Create wireframes and mockups for the admin dashboard.', 'assigned_to' => 2, 'priority' => 'Medium', 'status' => 'Pending', 'due_date' => '2025-08-25'],
                    ['id' => 103, 'title' => 'Write API Documentation', 'description' => 'Document all REST API endpoints for developers.', 'assigned_to' => 4, 'priority' => 'Low', 'status' => 'Assigned', 'due_date' => '2025-09-01'],
                ]);
            }


            return false;
        }
        public function fetch_assoc() { return false; }
        public function error() { return "Mock DB Error: Connection not real."; }
        public function begin_transaction() {}
        public function commit() {}
        public function rollback() {}
        public function prepare($sql) {
            // Mock prepare for UPDATE
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
            // Mock prepare for SELECT tasks (e.g., in view_comments context for title)
         
            return false;
        }
    }
    $conn = new MockConnection();
}
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
$team_member_id = null;
$error_message = null;

if ($user_email && $conn instanceof mysqli) {
    // Fetch team member ID using email
    $sql_member = "SELECT id FROM team_members WHERE email = ?";
    $stmt_member = $conn->prepare($sql_member);
    if ($stmt_member) {
        $stmt_member->bind_param('s', $user_email);
        $stmt_member->execute();
        $result_member = $stmt_member->get_result();
        if ($result_member && $row = $result_member->fetch_assoc()) {
            $team_member_id = $row['id'];
            $team_members_id = $conn->real_escape_string($team_member_id);


            $sql = "UPDATE tasks SET view = 'true' WHERE assigned_to = '$team_members_id'";

            // Execute the query
            if ($conn->query($sql) === TRUE) {
                echo "";
            } else {
                echo "Error updating record: " . $conn->error;
            }

        
        } else {
            $error_message = "Team member not found for email: " . htmlspecialchars($user_email);
        }
        $stmt_member->close();
    } else {
        $error_message = "Error preparing team member query: " . $conn->error;
    }
} 

// --- Logic to Fetch all tasks for display ---
$tasks = [];

if ($conn instanceof mysqli && $team_member_id !== null && !$error_message) {
    // Fetch tasks including their assigned team member's name using prepared statement
    $sql_fetch = "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name
                  FROM tasks t
                  JOIN team_members tm ON t.assigned_to = tm.id 
                  WHERE t.assigned_to = ?
                  ORDER BY t.due_date ASC";
    
    $stmt_fetch = $conn->prepare($sql_fetch);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param('i', $team_member_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        } else {
            $error_message = "Error fetching tasks: " . $conn->error;
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Error preparing task fetch query: " . $conn->error;
    }
} else {
    // Mock fetch logic that correctly gets the data and enriches it
    // Only execute if no database connection or for testing purposes
    if (!($conn instanceof mysqli)) {
        // Add your mock data logic here if needed for testing
        $tasks = []; // Mock tasks array
    }
}

// Optional: Display error message if needed
if ($error_message) {
    echo "<div class='error'>" . $error_message . "</div>";
}
include_once 'common_layout.php';
// This include needs to be after $tasks is populated if it uses $tasks data
// If common_layout.php contains the main HTML structure, it should be adjusted or called differently.
// For this standalone file, we assume common_layout.php provides common functions/setup, not the main page layout itself.
// include_once 'common_layout.php'; // Keep if it's for functions, remove if it prints HTML
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assigned Tasks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .task-list-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            padding: 20px;
            color: #1f2937;
        }
        .task-list-container .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .task-list-container .page-header { 
            text-align: center; 
            margin-bottom: 40px; 
            padding: 30px; 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border: 1px solid #e5e7eb; 
        }
        .task-list-container .page-header h1 { 
            font-size: 2.5em; 
            font-weight: 700; 
            color: #1f2937; 
            margin-bottom: 10px; 
            background: linear-gradient(135deg, #3b82f6, #10b981); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            background-clip: text; 
        }
        .task-list-container .page-subtitle { 
            font-size: 1.1em; 
            color: #6b7280; 
        }
        .task-list-container .view-controls {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }
        .task-list-container .view-btn {
            padding: 12px 24px;
            background-color: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-list-container .view-btn:hover {
            background-color: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .task-list-container .calendar-btn {
            background-color: #059669;
        }
        .task-list-container .calendar-btn:hover {
            background-color: #047857;
        }
        .task-list-container .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .task-list-container .alert.success { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .task-list-container .alert.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .task-list-container .alert.info { background-color: #e0f2fe; color: #075985; border: 1px solid #38bdf8; } /* Added info alert style */
        .task-list-container .task-list { 
            display: grid; 
            gap: 20px; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
        }
        .task-list-container .task-card { 
            background-color: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            padding: 20px; 
            border: 1px solid #e5e7eb; 
            display: flex; 
            flex-direction: column; 
        }
        .task-list-container .task-card h3 { 
            margin-top: 0; 
            font-size: 1.25em; 
            color: #1e40af; 
        }
        .task-list-container .task-card p { 
            font-size: 0.9em; 
            color: #6b7280; 
            flex-grow: 1; 
        }
        .task-list-container .task-card .task-actions { 
            margin-top: 15px; 
            display: flex; /* Use flexbox for buttons */
            justify-content: flex-end; /* Align buttons to the right */
            gap: 10px; /* Space between buttons */
        }
        .task-list-container .task-card .status-indicator { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.8em; 
            font-weight: 600; 
            margin-left: 10px; 
        }
        .task-list-container .status-Pending { background-color: #fef3c7; color: #92400e; }
        .task-list-container .status-Assigned { background-color: #dbeafe; color: #1e40af; }
        .task-list-container .status-InProgress { background-color: #d1fae5; color: #065f46; }
        .task-list-container .status-OnHold { background-color: #a29ed6ff; color: #510a87ff; }
        .task-list-container .status-Completed { background-color: #cffafe; color: #0e7490; }
        .task-list-container .priority-High { color: #ef4444; }
        .task-list-container .priority-Medium { color: #f59e0b; }
        .task-list-container .priority-Low { color: #10b981; }
        .task-list-container .btn { 
            padding: 10px 15px; 
            background-color: #3b82f6; 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: background-color 0.2s; 
            font-weight: 600; 
            text-decoration: none; 
            display: inline-flex; /* Use inline-flex for button content alignment */
            align-items: center;
            justify-content: center;
            text-align: center; 
        }
        .task-list-container .btn:hover { 
            background-color: #1e40af; 
        }
        /* Specific style for comments button */
        .task-list-container .comments-btn {
            background-color: #6b7280; /* A different color for distinction */
        }
        .task-list-container .comments-btn:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body>

<div class="task-list-container">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-tasks" style="margin-right: 15px;"></i>View Assigned Tasks</h1>
            <p class="page-subtitle">View details for all active projects and tasks</p>
        </div>

        <!-- View Controls -->
        <div class="view-controls">
            <a href="#" class="view-btn">
                <i class="fas fa-list"></i>
                List View
            </a>
            <a href="task_calendar.php" class="view-btn calendar-btn">
                <i class="fas fa-calendar-alt"></i>
                Calendar View
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="task-list">
            <?php if (empty($tasks)): ?>
                <div class="alert info">No tasks found.</div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <h3>
                            <?php echo htmlspecialchars($task['title']); ?>
                            <span class="status-indicator status-<?php echo str_replace(' ', '', htmlspecialchars($task['status'])); ?>">
                                <?php echo htmlspecialchars($task['status']); ?>
                            </span>
                        </h3>
                        <p>
                            <strong>Assigned to:</strong> <?php echo htmlspecialchars($task['assigned_to_name'] ?? 'N/A'); ?><br>
                            <strong>Due Date:</strong> <?php echo htmlspecialchars($task['due_date']); ?><br>
                            <strong>Priority:</strong> <span class="priority-<?php echo htmlspecialchars($task['priority']); ?>"><?php echo htmlspecialchars($task['priority']); ?></span>
                        </p>
                        <div class="task-actions">
                            <!-- View Details Button -->
                            <a href="view_task.php?id=<?php echo htmlspecialchars($task['id']); ?>" class="btn">
                                <i class="fas fa-info-circle" style="margin-right: 5px;"></i> View Details
                            </a>
                            <!-- View Comments Button -->
                            <a href="view_user_comments.php?task_id=<?php echo htmlspecialchars($task['id']); ?>" class="btn comments-btn">
                                <i class="fas fa-comments" style="margin-right: 5px;"></i> View Comments
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>