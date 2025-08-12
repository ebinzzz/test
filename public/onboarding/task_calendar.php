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

// Get current month and year from URL parameters or use current date
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 2020 || $current_year > 2030) {
    $current_year = date('Y');
}

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
        public function prepare($sql) {
            return new MockPreparedStatement();
        }
        public function query($sql) {
            error_log("Attempted query on mock connection: " . $sql);

            // Mock team member lookup
            if (strpos($sql, 'SELECT id FROM team_members WHERE email') !== false) {
                return new MockResult([
                    ['id' => 1]
                ]);
            }

            // Mock tasks data for calendar view
            if (strpos($sql, 'SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id') !== false) {
                 return new MockResult([
                    ['id' => 101, 'title' => 'Implement User Authentication', 'description' => 'Develop and test the user login and registration system.', 'assigned_to' => 1, 'assigned_to_name' => 'Alice Johnson', 'priority' => 'High', 'status' => 'In Progress', 'due_date' => '2025-08-15'],
                    ['id' => 102, 'title' => 'Design Dashboard UI', 'description' => 'Create wireframes and mockups for the admin dashboard.', 'assigned_to' => 2, 'assigned_to_name' => 'Bob Williams', 'priority' => 'Medium', 'status' => 'Pending', 'due_date' => '2025-08-22'],
                    ['id' => 103, 'title' => 'Write API Documentation', 'description' => 'Document all REST API endpoints for developers.', 'assigned_to' => 4, 'assigned_to_name' => 'Diana Prince', 'priority' => 'Low', 'status' => 'Assigned', 'due_date' => '2025-08-28'],
                    ['id' => 104, 'title' => 'Database Optimization', 'description' => 'Optimize database queries and indexing.', 'assigned_to' => 3, 'assigned_to_name' => 'Charlie Brown', 'priority' => 'High', 'status' => 'Completed', 'due_date' => '2025-08-10'],
                    ['id' => 105, 'title' => 'Security Audit', 'description' => 'Conduct comprehensive security review.', 'assigned_to' => 1, 'assigned_to_name' => 'Alice Johnson', 'priority' => 'High', 'status' => 'On Hold', 'due_date' => '2025-09-05'],
                ]);
            }
            return false;
        }
        public function error() { return "Mock DB Error: Connection not real."; }
    }
    
    class MockPreparedStatement {
        public function bind_param($types, ...$values) { return true; }
        public function execute() { return true; }
        public function get_result() {
            // Mock result for team member lookup
            return new MockResult([['id' => 1]]);
        }
        public function close() { return true; }
    }
    
    $conn = new MockConnection();
}

// Get user email from session and fetch team member ID
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
$team_member_id = null;

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
        } else {
            $error_message = "Team member not found for email: " . htmlspecialchars($user_email);
        }
        $stmt_member->close();
    } else {
        $error_message = "Error preparing team member query: " . $conn->error;
    }
} elseif ($user_email) {
    // Mock team member ID for testing
    $team_member_id = 1;
} else {
    $error_message = "No email found in session. Please login again.";
}

// --- Logic to Fetch tasks for the current month ---
$tasks = [];
$start_date = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-01';
$end_date = date('Y-m-t', strtotime($start_date)); // Last day of the month

// Check if we need to show a specific task
$show_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$show_task_data = null;

if ($team_member_id && $conn instanceof mysqli) {
    if ($show_task_id) {
        // If looking for a specific task, get all tasks assigned to this user
        $sql_fetch = "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name
                        FROM tasks t
                        JOIN team_members tm ON t.assigned_to = tm.id
                        WHERE t.assigned_to = ?
                        ORDER BY t.due_date ASC";
        $stmt = $conn->prepare($sql_fetch);
        $stmt->bind_param('i', $team_member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        $stmt->close();
    } else {
        // Fetch tasks for the current month assigned to this user
        $sql_fetch = "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name
                        FROM tasks t
                        JOIN team_members tm ON t.assigned_to = tm.id
                        WHERE t.assigned_to = ? AND t.due_date BETWEEN ? AND ?
                        ORDER BY t.due_date ASC";
        $stmt = $conn->prepare($sql_fetch);
        $stmt->bind_param('iss', $team_member_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        } else {
            $error_message = "Error fetching tasks: " . $conn->error;
        }
        $stmt->close();
    }
} elseif ($team_member_id) {
    // Mock fetch logic - get all tasks assigned to this user (mock ID = 1)
    $mock_tasks_obj = $conn->query("SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, tm.full_name as assigned_to_name FROM tasks t JOIN team_members tm ON t.assigned_to = tm.id");
    if ($mock_tasks_obj && property_exists($mock_tasks_obj, 'data')) {
        if ($show_task_id) {
            // Filter tasks assigned to this user (mock ID = 1)
            foreach ($mock_tasks_obj->data as $task) {
                if ($task['assigned_to'] == $team_member_id) {
                    $tasks[] = $task;
                }
            }
        } else {
            // Filter mock tasks for current month and assigned to this user
            foreach ($mock_tasks_obj->data as $task) {
                if ($task['assigned_to'] == $team_member_id) {
                    $task_date = strtotime($task['due_date']);
                    $task_month = date('n', $task_date);
                    $task_year = date('Y', $task_date);
                    if ($task_month == $current_month && $task_year == $current_year) {
                        $tasks[] = $task;
                    }
                }
            }
        }
    }
}

// Group tasks by date for easier calendar rendering
$tasks_by_date = [];
foreach ($tasks as $task) {
    $date_key = date('j', strtotime($task['due_date'])); // Day of month without leading zeros
    if (!isset($tasks_by_date[$date_key])) {
        $tasks_by_date[$date_key] = [];
    }
    $tasks_by_date[$date_key][] = $task;
    
    // If this is the task we want to show, store its data
    if ($show_task_id && $task['id'] == $show_task_id) {
        $show_task_data = $task;
        // Set the calendar to show the month of this task
        $task_date = strtotime($task['due_date']);
        $current_month = date('n', $task_date);
        $current_year = date('Y', $task_date);
        // Recalculate calendar data for the task's month
        $first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
        $days_in_month = date('t', $first_day_of_month);
        $first_day_of_week = date('w', $first_day_of_month);
        $month_name = date('F', $first_day_of_month);
        
        // Recalculate navigation dates
        $prev_month = $current_month - 1;
        $prev_year = $current_year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        
        $next_month = $current_month + 1;
        $next_year = $current_year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }
    }
}

// Calculate calendar data - ALWAYS calculate this regardless of task availability
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$first_day_of_week = date('w', $first_day_of_month); // 0 = Sunday, 6 = Saturday
$month_name = date('F', $first_day_of_month);

// Navigation dates - ALWAYS calculate this
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

include_once 'common_layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Task Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .calendar-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            padding: 20px;
            color: #1f2937;
            min-height: 100vh;
        }
        .calendar-container .container { 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        .calendar-container .page-header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding: 30px; 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border: 1px solid #e5e7eb; 
        }
        .calendar-container .page-header h1 { 
            font-size: 2.5em; 
            font-weight: 700; 
            color: #1f2937; 
            margin-bottom: 10px; 
            background: linear-gradient(135deg, #059669, #3b82f6); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            background-clip: text; 
        }
        .calendar-container .user-info {
            background: #e0f2fe;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #0891b2;
        }
        .calendar-container .user-info strong {
            color: #0c4a6e;
        }
        .calendar-container .view-controls {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }
        .calendar-container .view-btn {
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
        .calendar-container .view-btn:hover {
            background-color: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .calendar-container .calendar-btn {
            background-color: #059669;
        }
        .calendar-container .calendar-btn:hover {
            background-color: #047857;
        }
        .calendar-container .calendar-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .calendar-container .nav-btn {
            padding: 10px 20px;
            background-color: #6b7280;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .calendar-container .nav-btn:hover {
            background-color: #4b5563;
            transform: translateY(-1px);
        }
        .calendar-container .month-year {
            font-size: 1.8em;
            font-weight: 700;
            color: #059669;
        }
        .calendar-container .calendar-grid {
            background: #ffffff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .calendar-container .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #059669;
            color: white;
        }
        .calendar-container .calendar-header-day {
            padding: 15px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1em;
        }
        .calendar-container .calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
        }
        .calendar-container .calendar-day {
            background: #ffffff;
            min-height: 120px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .calendar-container .calendar-day.other-month {
            background: #f9fafb;
            color: #9ca3af;
        }
        .calendar-container .calendar-day.today {
            background: #fef3c7;
            border: 2px solid #f59e0b;
        }
        .calendar-container .day-number {
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 5px;
            color: #374151;
        }
        .calendar-container .day-tasks {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .calendar-container .task-item {
            background: #3b82f6;
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .calendar-container .task-item:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .calendar-container .task-item.priority-High {
            background: #ef4444;
        }
        .calendar-container .task-item.priority-Medium {
            background: #f59e0b;
        }
        .calendar-container .task-item.priority-Low {
            background: #10b981;
        }
        .calendar-container .task-item.status-Completed {
            background: #6b7280;
            text-decoration: line-through;
        }
        .calendar-container .task-item.status-OnHold {
            background: #8b5cf6;
        }
        .calendar-container .more-tasks {
            font-size: 0.7em;
            color: #6b7280;
            font-weight: 500;
            margin-top: 2px;
        }
        .calendar-container .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .calendar-container .alert.error { 
            background-color: #fee2e2; 
            color: #991b1b; 
            border: 1px solid #ef4444; 
        }
        .calendar-container .no-tasks-message {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #0891b2;
            color: #0c4a6e;
        }

        /* Task Modal Styles */
        .task-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .task-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }
        .task-modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .task-modal-close:hover {
            color: #ef4444;
        }
        .task-modal h3 {
            color: #1e40af;
            margin-bottom: 15px;
        }
        .task-modal .task-detail {
            margin-bottom: 15px;
        }
        .task-modal .task-detail strong {
            color: #374151;
        }
        .task-modal .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .calendar-container .calendar-day {
                min-height: 80px;
                padding: 4px;
            }
            .calendar-container .day-number {
                font-size: 1em;
            }
            .calendar-container .task-item {
                font-size: 0.7em;
                padding: 2px 4px;
            }
            .calendar-container .calendar-navigation {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="calendar-container">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt" style="margin-right: 15px;"></i>My Task Calendar</h1>
            <p class="page-subtitle">View your assigned tasks in calendar format</p>
        </div>

        <!-- User Info -->
        <?php if ($user_email): ?>
        <div class="user-info">
            <i class="fas fa-user"></i> <strong>Logged in as:</strong> <?php echo htmlspecialchars($user_email); ?>
            <?php if ($team_member_id): ?>
                <span style="margin-left: 20px;"><i class="fas fa-id-badge"></i> <strong>Member ID:</strong> <?php echo $team_member_id; ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Error messages -->
        <?php if ($error_message): ?>
            <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Show message when no tasks for current month -->
        <?php if (empty($tasks) && !$error_message): ?>
            <div class="no-tasks-message">
                <i class="fas fa-info-circle"></i> 
                <strong>No tasks assigned for <?php echo $month_name . ' ' . $current_year; ?></strong>
                <p style="margin: 5px 0 0 0; font-size: 0.9em;">Navigate to other months to view tasks or check with your administrator.</p>
            </div>
        <?php endif; ?>

        <!-- Calendar Navigation - Always show -->
        <div class="calendar-navigation">
            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn">
                <i class="fas fa-chevron-left"></i>
                Previous
            </a>
            <div class="month-year">
                <?php echo $month_name . ' ' . $current_year; ?>
            </div>
            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">
                Next
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <!-- Calendar Grid - Always show -->
        <div class="calendar-grid">
            <!-- Calendar Header -->
            <div class="calendar-header">
                <div class="calendar-header-day">Sun</div>
                <div class="calendar-header-day">Mon</div>
                <div class="calendar-header-day">Tue</div>
                <div class="calendar-header-day">Wed</div>
                <div class="calendar-header-day">Thu</div>
                <div class="calendar-header-day">Fri</div>
                <div class="calendar-header-day">Sat</div>
            </div>

            <!-- Calendar Body -->
            <div class="calendar-body">
                <?php
                $today = date('j');
                $today_month = date('n');
                $today_year = date('Y');
                
                // Previous month's trailing days
                $prev_month_days = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
                for ($i = $first_day_of_week - 1; $i >= 0; $i--) {
                    $day = $prev_month_days - $i;
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '</div>';
                }

                // Current month's days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $is_today = ($day == $today && $current_month == $today_month && $current_year == $today_year);
                    $class = $is_today ? 'calendar-day today' : 'calendar-day';
                    
                    echo '<div class="' . $class . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '<div class="day-tasks">';
                    
                    // Display tasks for this day
                    if (isset($tasks_by_date[$day])) {
                        $task_count = 0;
                        $max_visible = 3;
                        
                        foreach ($tasks_by_date[$day] as $task) {
                            if ($task_count < $max_visible) {
                                $priority_class = 'priority-' . $task['priority'];
                                $status_class = 'status-' . str_replace(' ', '', $task['status']);
                                echo '<div class="task-item ' . $priority_class . ' ' . $status_class . '" ';
                                echo 'onclick="showTaskModal(' . htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8') . ')">';
                                echo htmlspecialchars($task['title']);
                                echo '</div>';
                            }
                            $task_count++;
                        }
                        
                        if ($task_count > $max_visible) {
                            echo '<div class="more-tasks">+' . ($task_count - $max_visible) . ' more</div>';
                        }
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }

                // Calculate remaining days to fill the grid (next month's leading days)
                $total_cells = 42; // 6 rows * 7 days
                $filled_cells = $first_day_of_week + $days_in_month;
                $remaining_cells = $total_cells - $filled_cells;
                
                for ($day = 1; $day <= $remaining_cells && $filled_cells < $total_cells; $day++) {
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '</div>';
                    $filled_cells++;
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div id="taskModal" class="task-modal">
    <div class="task-modal-content">
        <span class="task-modal-close" onclick="closeTaskModal()">&times;</span>
        <div id="taskModalBody">
            <!-- Task details will be loaded here -->
        </div>
    </div>
</div>

<script>
function showTaskModal(task) {
    const modal = document.getElementById('taskModal');
    const modalBody = document.getElementById('taskModalBody');
    
    // Status badge classes
    const statusClasses = {
        'Pending': 'style="background-color: #fef3c7; color: #92400e;"',
        'Assigned': 'style="background-color: #dbeafe; color: #1e40af;"',
        'In Progress': 'style="background-color: #d1fae5; color: #065f46;"',
        'On Hold': 'style="background-color: #a29ed6ff; color: #510a87ff;"',
        'Completed': 'style="background-color: #cffafe; color: #0e7490;"'
    };
    
    // Priority colors
    const priorityColors = {
        'High': 'color: #ef4444;',
        'Medium': 'color: #f59e0b;',
        'Low': 'color: #10b981;'
    };
    
    const statusBadge = statusClasses[task.status] || 'style="background-color: #e5e7eb; color: #374151;"';
    const priorityStyle = priorityColors[task.priority] || 'color: #6b7280;';
    
    modalBody.innerHTML = `
        <h3>${task.title}</h3>
        <div class="task-detail">
            <strong>Description:</strong><br>
            ${task.description || 'No description available'}
        </div>
        <div class="task-detail">
            <strong>Assigned to:</strong> ${task.assigned_to_name || 'N/A'}
        </div>
        <div class="task-detail">
            <strong>Due Date:</strong> ${task.due_date}
        </div>
        <div class="task-detail">
            <strong>Priority:</strong> <span style="${priorityStyle} font-weight: 600;">${task.priority}</span>
        </div>
        <div class="task-detail">
            <strong>Status:</strong> <span class="status-badge" ${statusBadge}>${task.status}</span>
        </div>
       
    `;
    
    modal.style.display = 'block';
}

function closeTaskModal() {
    document.getElementById('taskModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('taskModal');
    if (event.target === modal) {
        closeTaskModal();
    }
}

// Auto-show modal for specific task if task_id is provided
<?php if ($show_task_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    const taskData = <?php echo json_encode($show_task_data, JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
    showTaskModal(taskData);
});
<?php endif; ?>
</script>

</body>
</html>