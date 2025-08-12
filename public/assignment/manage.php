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
            
            // Mock task statistics
            if (strpos($sql, "SELECT COUNT(*) as count FROM tasks") !== false) {
                return new MockResult([['count' => 8]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE status = 'Pending'") !== false) {
                return new MockResult([['count' => 3]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE status = 'In Progress'") !== false) {
                return new MockResult([['count' => 3]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE status = 'Completed'") !== false) {
                return new MockResult([['count' => 1]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE status = 'On Hold'") !== false) {
                return new MockResult([['count' => 1]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE priority = 'Critical'") !== false) {
                return new MockResult([['count' => 1]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM tasks WHERE due_date < CURDATE()") !== false) {
                return new MockResult([['count' => 1]]);
            }
            
            // Mock tasks data
            elseif (strpos($sql, 'SELECT t.*, tm_assigned.full_name as assigned_name, tm_created.full_name as created_name FROM tasks t') !== false) {
                return new MockResult([
                    [
                        'id' => 1, 'title' => 'Implement User Authentication', 'description' => 'Develop login/logout functionality with session management',
                        'assigned_to' => 1, 'created_by' => 1, 'priority' => 'High', 'status' => 'In Progress',
                        'due_date' => '2025-08-20', 'created_at' => '2025-08-10 09:00:00', 'updated_at' => '2025-08-11 14:30:00',
                        'progress' => 60, 'estimated_hours' => 16.0, 'actual_hours' => 10.5, 'tags' => 'backend,security',
                        'assigned_name' => 'Alice Johnson', 'created_name' => 'Alice Johnson'
                    ],
                    [
                        'id' => 2, 'title' => 'Design Dashboard UI', 'description' => 'Create responsive dashboard design with modern UI components',
                        'assigned_to' => 2, 'created_by' => 1, 'priority' => 'Medium', 'status' => 'Completed',
                        'due_date' => '2025-08-15', 'created_at' => '2025-08-09 10:00:00', 'updated_at' => '2025-08-15 16:00:00',
                        'progress' => 100, 'estimated_hours' => 24.0, 'actual_hours' => 14.5, 'tags' => 'frontend,ui/ux',
                        'assigned_name' => 'Bob Williams', 'created_name' => 'Alice Johnson'
                    ],
                    [
                        'id' => 3, 'title' => 'Database Optimization', 'description' => 'Optimize database queries for better performance',
                        'assigned_to' => 1, 'created_by' => 3, 'priority' => 'High', 'status' => 'Pending',
                        'due_date' => '2025-08-25', 'created_at' => '2025-08-09 11:00:00', 'updated_at' => '2025-08-09 11:00:00',
                        'progress' => 0, 'estimated_hours' => 12.0, 'actual_hours' => 0, 'tags' => 'database,performance',
                        'assigned_name' => 'Alice Johnson', 'created_name' => 'Charlie Brown'
                    ],
                    [
                        'id' => 4, 'title' => 'Mobile App Testing', 'description' => 'Comprehensive testing of mobile application features',
                        'assigned_to' => 4, 'created_by' => 3, 'priority' => 'Medium', 'status' => 'In Progress',
                        'due_date' => '2025-08-18', 'created_at' => '2025-08-08 12:00:00', 'updated_at' => '2025-08-11 10:00:00',
                        'progress' => 40, 'estimated_hours' => 20.0, 'actual_hours' => 3.0, 'tags' => 'testing,mobile',
                        'assigned_name' => 'Diana Prince', 'created_name' => 'Charlie Brown'
                    ],
                    [
                        'id' => 5, 'title' => 'API Documentation', 'description' => 'Complete API documentation with examples and usage guidelines',
                        'assigned_to' => 2, 'created_by' => 1, 'priority' => 'Low', 'status' => 'Pending',
                        'due_date' => '2025-08-30', 'created_at' => '2025-08-07 13:00:00', 'updated_at' => '2025-08-10 09:00:00',
                        'progress' => 15, 'estimated_hours' => 8.0, 'actual_hours' => 0, 'tags' => 'documentation,api',
                        'assigned_name' => 'Bob Williams', 'created_name' => 'Alice Johnson'
                    ],
                    [
                        'id' => 6, 'title' => 'Bug Fix - Payment Gateway', 'description' => 'Fix payment processing issues reported by users',
                        'assigned_to' => 1, 'created_by' => 3, 'priority' => 'Critical', 'status' => 'In Progress',
                        'due_date' => '2025-08-12', 'created_at' => '2025-08-11 08:00:00', 'updated_at' => '2025-08-11 15:00:00',
                        'progress' => 80, 'estimated_hours' => 6.0, 'actual_hours' => 2.5, 'tags' => 'bugfix,payment',
                        'assigned_name' => 'Alice Johnson', 'created_name' => 'Charlie Brown'
                    ],
                    [
                        'id' => 7, 'title' => 'Team Performance Review', 'description' => 'Conduct quarterly performance reviews for all team members',
                        'assigned_to' => 3, 'created_by' => 3, 'priority' => 'Medium', 'status' => 'Pending',
                        'due_date' => '2025-08-28', 'created_at' => '2025-08-06 14:00:00', 'updated_at' => '2025-08-06 14:00:00',
                        'progress' => 0, 'estimated_hours' => 4.0, 'actual_hours' => 0, 'tags' => 'hr,review',
                        'assigned_name' => 'Charlie Brown', 'created_name' => 'Charlie Brown'
                    ],
                    [
                        'id' => 8, 'title' => 'Server Migration', 'description' => 'Migrate application to new cloud infrastructure',
                        'assigned_to' => 1, 'created_by' => 3, 'priority' => 'High', 'status' => 'On Hold',
                        'due_date' => '2025-09-01', 'created_at' => '2025-08-05 15:00:00', 'updated_at' => '2025-08-10 11:00:00',
                        'progress' => 25, 'estimated_hours' => 32.0, 'actual_hours' => 0, 'tags' => 'devops,infrastructure',
                        'assigned_name' => 'Alice Johnson', 'created_name' => 'Charlie Brown'
                    ]
                ]);
            }
            
            // Mock team members data
            elseif (strpos($sql, 'SELECT id, full_name FROM team_members') !== false) {
                return new MockResult([
                    ['id' => 1, 'full_name' => 'Alice Johnson'],
                    ['id' => 2, 'full_name' => 'Bob Williams'],
                    ['id' => 3, 'full_name' => 'Charlie Brown'],
                    ['id' => 4, 'full_name' => 'Diana Prince']
                ]);
            }
            
            if (strpos($sql, 'DELETE FROM tasks WHERE id =') !== false) {
                return true;
            }
            return false;
        }
        public function fetch_assoc() { return false; }
        public function error() { return "Mock DB Error: Connection not real."; }
        public function begin_transaction() {}
        public function commit() {}
        public function rollback() {}
        public function prepare($sql) {
            if (strpos($sql, "DELETE FROM tasks WHERE id = ?") !== false || strpos($sql, "UPDATE tasks SET status") !== false) {
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
            return false;
        }
    }
    $conn = new MockConnection();
}

// Handle task deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($conn instanceof mysqli) {
        $conn->begin_transaction();
        $sql_delete_task = "DELETE FROM tasks WHERE id = ?";
        $stmt = $conn->prepare($sql_delete_task);
        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $conn->commit();
                $message = "Task deleted successfully!";
            } else {
                $conn->rollback();
                $error_message = "Error deleting task: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $conn->rollback();
            $error_message = "Error preparing task deletion statement: " . $conn->error;
        }
    } else {
        $message = "Task deleted successfully! (Mock mode)";
    }
    header('Location: manage.php');
    exit();
}

// Handle quick status update
if (isset($_POST['update_status']) && isset($_POST['task_id']) && isset($_POST['new_status'])) {
    $task_id = (int)$_POST['task_id'];
    $new_status = $_POST['new_status'];
    $progress = $_POST['progress'] ?? 0;
    
    if ($conn instanceof mysqli) {
        $sql_update = "UPDATE tasks SET status = ?, progress = ?, updated_at = CURRENT_TIMESTAMP";
        if ($new_status === 'Completed') {
            $sql_update .= ", completed_at = CURRENT_TIMESTAMP";
        }
        $sql_update .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql_update);
        if ($stmt) {
            $stmt->bind_param("sii", $new_status, $progress, $task_id);
            if ($stmt->execute()) {
                $message = "Task status updated successfully!";
            } else {
                $error_message = "Error updating task status: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $message = "Task status updated successfully! (Mock mode)";
    }
    header('Location: manage.php');
    exit();
}

// Fetch statistics
$total_tasks = 0;
$pending_tasks = 0;
$in_progress_tasks = 0;
$completed_tasks = 0;
$on_hold_tasks = 0;
$critical_tasks = 0;
$overdue_tasks = 0;

if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM tasks");
    if ($result) { $total_tasks = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'Pending'");
    if ($result) { $pending_tasks = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'In Progress'");
    if ($result) { $in_progress_tasks = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'Completed'");
    if ($result) { $completed_tasks = $result->fetch_assoc()['count']; }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'On Hold'");
    if ($result) { $on_hold_tasks = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE priority = 'Critical'");
    if ($result) { $critical_tasks = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('Completed', 'Cancelled')");
    if ($result) { $overdue_tasks = $result->fetch_assoc()['count']; }

} else { // Mock connection statistics
    $total_tasks = 8;
    $pending_tasks = 3;
    $in_progress_tasks = 3;
    $completed_tasks = 1;
    $on_hold_tasks = 1;
    $critical_tasks = 1;
    $overdue_tasks = 1;
}

// Fetch all tasks with team member names
$tasks = [];
if ($conn instanceof mysqli) {
   $sql = "SELECT t.*, 
            tm_assigned.full_name as assigned_name, 
            CONCAT(u_created.first_name, ' ', u_created.last_name) as created_name 
        FROM tasks t 
        LEFT JOIN team_members tm_assigned ON t.assigned_to = tm_assigned.id 
        LEFT JOIN users u_created ON t.created_by = u_created.id 
        ORDER BY t.created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    } else {
        $error_message = "Error fetching tasks: " . $conn->error;
    }
} else { // Mock connection handling
    $mock_tasks_obj = $conn->query("SELECT t.*, tm_assigned.full_name as assigned_name, tm_created.full_name as created_name FROM tasks t");
    if ($mock_tasks_obj && property_exists($mock_tasks_obj, 'data')) {
        $tasks = $mock_tasks_obj->data;
    }
}

// Fetch team members for assignment dropdown
$team_members = [];
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
    <title>Task Management - Zorqent Technology</title>
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

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .stat-card h3 {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-card.overdue .value {
            color: var(--critical);
        }

        .stat-card.critical .value {
            color: var(--critical);
        }

        /* Tasks Table */
        .table-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--surface-elevated);
            padding: 16px 24px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
            vertical-align: top;
        }

        .table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
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

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
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
        .status-on-hold {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        .status-cancelled {
            background: rgba(248, 113, 113, 0.2);
            color: #fca5a5;
        }

        /* Progress Bar */
        .progress-container {
            width: 80px;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        /* Task Title */
        .task-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .task-description {
            font-size: 12px;
            color: var(--text-muted);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Tags */
        .task-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 8px;
        }

        .tag {
            background: rgba(59, 130, 246, 0.1);
            color: #93c5fd;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }

        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Quick Status Form */
        .quick-status-form {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .form-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 6px 32px 6px 12px;
            color: var(--text-primary);
            font-size: 12px;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 16px;
        }

        .form-input {
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 6px 12px;
            color: var(--text-primary);
            font-size: 12px;
            width: 60px;
        }

        /* Due Date Styling */
        .due-date {
            font-size: 12px;
        }

        .due-date.overdue {
            color: var(--critical);
            font-weight: 600;
        }

        .due-date.due-soon {
            color: var(--warning);
            font-weight: 500;
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
            .table-container {
                overflow-x: auto;
            }
            .table {
                min-width: 1200px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 16px;
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
        function confirmTaskDelete(taskId) {
            if (confirm("Are you sure you want to delete this task? This action cannot be undone.")) {
                window.location.href = '?delete_id=' + taskId;
            }
        }

        function updateTaskStatus(taskId, selectElement) {
            const form = selectElement.closest('.quick-status-form');
            const progressInput = form.querySelector('input[name="progress"]');
            const status = selectElement.value;
            
            // Auto-set progress based on status
            if (status === 'Pending') progressInput.value = 0;
            else if (status === 'Completed') progressInput.value = 100;
            
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.querySelector('.alert');
            if (alertBox && alertBox.style.display !== 'none') {
                setTimeout(() => {
                    alertBox.style.transition = 'opacity 0.5s ease';
                    alertBox.style.opacity = '0';
                    setTimeout(() => alertBox.remove(), 500);
                }, 4000);
            }

            // Status filter functionality
            document.getElementById('statusFilter').addEventListener('change', function () {
                let selected = this.value.toLowerCase().replace(' ', '-');
                let rows = document.querySelectorAll('#tasksTable tbody tr');

                rows.forEach(row => {
                    let status = row.getAttribute('data-status');
                    if (selected === 'all' || status === selected) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Priority filter functionality
            document.getElementById('priorityFilter').addEventListener('change', function () {
                let selected = this.value.toLowerCase();
                let rows = document.querySelectorAll('#tasksTable tbody tr');

                rows.forEach(row => {
                    let priority = row.getAttribute('data-priority');
                    if (selected === 'all' || priority === selected) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Assignee filter functionality
            document.getElementById('assigneeFilter').addEventListener('change', function () {
                let selected = this.value;
                let rows = document.querySelectorAll('#tasksTable tbody tr');

                rows.forEach(row => {
                    let assignee = row.getAttribute('data-assignee');
                    if (selected === 'all' || assignee === selected) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
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
                <h1>Task Management</h1>
                <div class="header-actions">
                    <a href="add.php" class="btn btn-primary">Create New Task</a>
                    <a href="all_task_calendar.php" class="btn btn-secondary">Task Calendar</a>
                    <a href="reports.php" class="btn btn-secondary">Reports</a>
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

            <!-- Task Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Tasks</h3>
                    <div class="value"><?php echo $total_tasks; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="value"><?php echo $pending_tasks; ?></div>
                </div>
                <div class="stat-card">
                    <h3>In Progress</h3>
                    <div class="value"><?php echo $in_progress_tasks; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed</h3>
                    <div class="value"><?php echo $completed_tasks; ?></div>
                </div>
                <div class="stat-card">
                    <h3>On Hold</h3>
                    <div class="value"><?php echo $on_hold_tasks; ?></div>
                </div>
                <div class="stat-card critical">
                    <h3>Critical</h3>
                    <div class="value"><?php echo $critical_tasks; ?></div>
                </div>
                <div class="stat-card overdue">
                    <h3>Overdue</h3>
                    <div class="value"><?php echo $overdue_tasks; ?></div>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Tasks</h2>
                    <div class="table-filters">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on-hold">On Hold</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select id="priorityFilter" class="form-select">
                            <option value="all">All Priority</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <select id="assigneeFilter" class="form-select">
                            <option value="all">All Assignees</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="add.php" class="btn btn-primary btn-sm">Add Task</a>
                    </div>
                </div>
                <table class="table" id="tasksTable">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Due Date</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tasks)): ?>
                            <?php foreach ($tasks as $task): 
                                $due_date = strtotime($task['due_date']);
                                $today = time();
                                $days_until_due = ($due_date - $today) / (60 * 60 * 24);
                                
                                $due_class = '';
                                if ($task['status'] !== 'Completed' && $task['status'] !== 'Cancelled') {
                                    if ($days_until_due < 0) $due_class = 'overdue';
                                    else if ($days_until_due <= 3) $due_class = 'due-soon';
                                }
                            ?>
                                <tr data-status="<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($task['status'] ?? ''))); ?>" 
                                    data-priority="<?php echo strtolower(htmlspecialchars($task['priority'] ?? '')); ?>"
                                    data-assignee="<?php echo $task['assigned_to']; ?>">
                                    <td>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title'] ?? 'N/A'); ?></div>
                                        <div class="task-description"><?php echo htmlspecialchars($task['description'] ?? ''); ?></div>
                                        <?php if (!empty($task['tags'])): ?>
                                            <div class="task-tags">
                                                <?php foreach (explode(',', $task['tags']) as $tag): ?>
                                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['assigned_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo strtolower(htmlspecialchars($task['priority'] ?? '')); ?>">
                                            <?php echo htmlspecialchars($task['priority'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="quick-status-form">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="new_status" onchange="updateTaskStatus(<?php echo $task['id']; ?>, this)" class="form-select">
                                                <option value="Pending" <?php echo ($task['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="In Progress" <?php echo ($task['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="Completed" <?php echo ($task['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="On Hold" <?php echo ($task['status'] === 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                                                <option value="Cancelled" <?php echo ($task['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="number" name="progress" value="<?php echo $task['progress']; ?>" min="0" max="100" class="form-input" placeholder="%">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: <?php echo $task['progress']; ?>%"></div>
                                        </div>
                                        <small><?php echo $task['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <div class="due-date <?php echo $due_class; ?>">
                                            <?php echo date('M j, Y', strtotime($task['due_date'] ?? 'now')); ?>
                                        </div>
                                        <?php if ($due_class === 'overdue'): ?>
                                            <small style="color: var(--critical);">Overdue</small>
                                        <?php elseif ($due_class === 'due-soon'): ?>
                                            <small style="color: var(--warning);">Due Soon</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['created_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="view.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                            <a href="edit.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <button type="button" onclick="confirmTaskDelete(<?php echo $task['id']; ?>)" class="btn btn-danger btn-sm">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">No tasks found. Create your first task to get started!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>