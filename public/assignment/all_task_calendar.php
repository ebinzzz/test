<?php
session_start();

// Redirect to login if the admin is not authenticated.
// This check is crucial for securing admin-only content.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database connection (adjust as needed).
// The '@' symbol prevents errors if the file doesn't exist, which is handled by the mock logic.
@include_once '../config.php';

// Initialize variables
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$tasks_for_month = [];
$error_message = '';

// Ensure month and year are within valid ranges
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 1900 || $current_year > 2100) { // Arbitrary reasonable range
    $current_year = date('Y');
}

// Calculate calendar properties
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$number_of_days = date('t', $first_day_of_month);
$date_components = getdate($first_day_of_month);
$month_name = $date_components['month'];
$day_of_week = $date_components['wday']; // 0 for Sunday, 6 for Saturday

// Adjust day_of_week to be 0 for Monday, 6 for Sunday (common calendar display)
$day_of_week = ($day_of_week == 0) ? 6 : $day_of_week - 1; // Now 0=Mon, ..., 6=Sun

// Calculate previous and next month/year
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

// --- Mock Database Connection and Data Block ---
// This provides a fallback for testing without a real database.
if (!isset($conn)) {
    $error_message = "Database connection not established. Using mock data for demonstration.";
    
    class MockResult {
        public $data;
        private $fetch_assoc_index = 0;
        public function __construct($data = []) { $this->data = $data; }
        public function fetch_assoc() {
            if ($this->fetch_assoc_index < count($this->data)) {
                return $this->data[$this->fetch_assoc_index++];
            }
            return null;
        }
        public function num_rows() { return count($this->data); }
    }
    class MockConnection {
        // Mock data for ALL tasks
        public static $all_tasks_mock_data = [
            // Tasks for August 2025 (adjust for current month for better demo)
            ['id' => 101, 'title' => 'Implement User Authentication', 'due_date' => '2025-08-15', 'status' => 'In Progress', 'priority' => 'High', 'assigned_to_name' => 'Alice Johnson'],
            ['id' => 102, 'title' => 'Design Dashboard UI', 'due_date' => '2025-08-20', 'status' => 'Pending', 'priority' => 'Medium', 'assigned_to_name' => 'Bob Williams'],
            ['id' => 103, 'title' => 'Write API Documentation', 'due_date' => '2025-08-22', 'status' => 'Assigned', 'priority' => 'Low', 'assigned_to_name' => 'Diana Prince'],
            ['id' => 105, 'title' => 'Client Meeting', 'due_date' => '2025-08-15', 'status' => 'Pending', 'priority' => 'High', 'assigned_to_name' => 'Charlie Brown'],
            ['id' => 106, 'title' => 'Bug Fixes Round 1', 'due_date' => '2025-08-18', 'status' => 'In Progress', 'priority' => 'High', 'assigned_to_name' => 'Bob Williams'],
            // Tasks for September 2025
            ['id' => 104, 'title' => 'Review Codebase', 'due_date' => '2025-09-05', 'status' => 'Assigned', 'priority' => 'High', 'assigned_to_name' => 'Alice Johnson'],
            ['id' => 107, 'title' => 'Prepare Q3 Report', 'due_date' => '2025-09-10', 'status' => 'Pending', 'priority' => 'Medium', 'assigned_to_name' => 'Diana Prince'],
            ['id' => 109, 'title' => 'Update Security Protocols', 'due_date' => '2025-09-10', 'status' => 'In Progress', 'priority' => 'High', 'assigned_to_name' => 'Alice Johnson'],
            // Tasks for July 2025
            ['id' => 108, 'title' => 'Project Kickoff', 'due_date' => '2025-07-28', 'status' => 'Completed', 'priority' => 'High', 'assigned_to_name' => 'Alice Johnson'],
            ['id' => 110, 'title' => 'Onboarding New Hire', 'due_date' => '2025-07-10', 'status' => 'Completed', 'priority' => 'Medium', 'assigned_to_name' => 'Bob Williams'],
        ];

        public function prepare($sql) {
            // Mock fetching tasks for a specific month and year
            if (strpos($sql, "SELECT t.id, t.title, t.due_date, t.status, t.priority, tm.full_name as assigned_to_name FROM tasks t") !== false) {
                return new class() {
                    public $data_to_return = [];
                    public $bound_params = [];
                    public function bind_param(...$params) {
                        $this->bound_params = $params;
                    }
                    public function execute() {
                        // Assuming the query is for month and year filtering
                        $month = $this->bound_params[1];
                        $year = $this->bound_params[2];

                        $this->data_to_return = array_values(array_filter(MockConnection::$all_tasks_mock_data, function($task) use ($month, $year) {
                            $task_date = new DateTime($task['due_date']);
                            return $task_date->format('n') == $month && $task_date->format('Y') == $year;
                        }));
                        return true;
                    }
                    public function get_result() {
                        return new MockResult($this->data_to_return);
                    }
                    public function close() {}
                };
            }
            return false;
        }
    }
    $conn = new MockConnection();
}
// --- End Mock Database Block ---

// Fetch tasks for the current month and year
if ($conn instanceof mysqli) {
    // Fetches ALL tasks for the selected month/year.
    // Ensure 'tasks' table has 'id', 'title', 'due_date', 'status', 'priority', 'assigned_to' columns.
    // Ensure 'team_members' table has 'id', 'full_name' columns.
    $sql = "SELECT t.id, t.title, t.due_date, t.status, t.priority, tm.full_name as assigned_to_name 
            FROM tasks t
            LEFT JOIN team_members tm ON t.assigned_to = tm.id
            WHERE MONTH(t.due_date) = ? AND YEAR(t.due_date) = ?
            ORDER BY t.due_date ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $current_month, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks_for_month[] = $row;
        }
        $stmt->close();
    } else {
        $error_message = "Error preparing tasks query: " . $conn->error;
    }
} else {
    // Mock connection logic for fetching tasks
    $stmt = $conn->prepare("SELECT t.id, t.title, t.due_date, t.status, t.priority, tm.full_name as assigned_to_name FROM tasks t LEFT JOIN team_members tm ON t.assigned_to = tm.id WHERE MONTH(t.due_date) = ? AND YEAR(t.due_date) = ? ORDER BY t.due_date ASC");
    if ($stmt) {
        $stmt->bind_param("ii", $current_month, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tasks_for_month[] = $row;
        }
    } else {
        $error_message = "Error preparing mock tasks query.";
    }
}

// Organize tasks by day
$tasks_by_day = [];
foreach ($tasks_for_month as $task) {
    $day = date('j', strtotime($task['due_date']));
    $tasks_by_day[$day][] = $task;
}

// You might include a common layout here, but ensure its CSS does not conflict.
// For admin panel, common_layout.php might define a sidebar or header.
// include_once 'common_layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - All Tasks Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* CSS Variables */
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
        /* Base styles from common_layout.php */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
            display: flex; /* Crucial for sidebar and main-content layout */
            min-height: 100vh;
        }
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
        .main-content {
            flex: 1;
            margin-left: 280px; /* Offset for the fixed sidebar */
            min-height: 100vh;
            background: var(--background);
        }
        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }
        .content { /* This will wrap the calendar-container */
            padding: 32px;
        }
        /* End of common_layout.php related styles */

        /* Calendar specific styles - adapted for admin theme */
        .calendar-container {
            max-width: 960px; /* Adjust as needed within the main-content area */
            margin: 0 auto; /* Center within the content area */
            padding: 25px;
            background-color: var(--surface-elevated); /* Use admin surface color */
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md); /* Use admin shadow */
            border: 1px solid var(--border-light);
            color: var(--text-primary); /* Ensure text is visible on dark surface */
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px 0;
            border-bottom: 2px solid var(--border); /* Use admin border color */
        }
        .calendar-header h2 {
            font-size: 2em; /* Adjusted size to fit well */
            font-weight: 700;
            color: var(--text-primary); /* Use primary text color for headings */
            margin: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--success)); /* Use defined color variables */
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .calendar-header .nav-btn {
            background-color: var(--secondary-color); /* Use secondary color for nav buttons */
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .calendar-header .nav-btn:hover {
            background-color: var(--inactive); /* Darker secondary on hover */
            transform: translateY(-1px);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            text-align: center;
        }
        .calendar-weekday, .calendar-day {
            padding: 12px 6px;
            border-radius: var(--radius);
            background-color: var(--surface); /* Use a slightly lighter surface for day cells */
            border: 1px solid var(--border);
            min-height: 100px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
            color: var(--text-secondary); /* Text color for days */
        }
        .calendar-weekday {
            font-weight: 700;
            color: var(--primary-color); /* Highlight weekdays with primary color */
            background-color: var(--surface-elevated); /* Elevated surface for weekdays */
            border-color: var(--border-light);
        }
        .calendar-day {
            font-weight: 600;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
        }
        .calendar-day.empty {
            background-color: var(--surface);
            color: var(--text-muted);
            box-shadow: none; /* No shadow for empty days */
        }
        .calendar-day-number {
            font-size: 1.3em;
            margin-bottom: 8px;
            width: 100%;
            text-align: right;
            padding-right: 8px;
            color: var(--text-primary); /* Primary text color for day numbers */
        }
        .calendar-day.today {
            background-color: rgba(var(--primary-color-rgb, 59, 130, 246), 0.2); /* Light primary tint for today */
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-color); /* Primary color highlight */
        }

        /* Task styling within calendar day */
        .task-list {
            margin-top: 5px;
            width: 100%;
            list-style: none;
            padding: 0;
            font-size: 0.85em;
            text-align: left;
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--text-muted) var(--surface); /* Adjusted scrollbar for dark theme */
        }
        .task-list::-webkit-scrollbar {
            width: 7px;
        }
        .task-list::-webkit-scrollbar-track {
            background: var(--surface);
            border-radius: 10px;
        }
        .task-list::-webkit-scrollbar-thumb {
            background-color: var(--text-muted);
            border-radius: 10px;
            border: 2px solid var(--surface);
        }

        .task-item {
            background-color: rgba(var(--primary-color-rgb, 59, 130, 246), 0.1); /* Lighter primary tint */
            color: var(--primary-color);
            border: 1px solid rgba(var(--primary-color-rgb, 59, 130, 246), 0.3);
            border-radius: var(--radius);
            padding: 6px 10px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.2s ease;
            cursor: help;
        }
        .task-item:hover {
            background-color: rgba(var(--primary-color-rgb, 59, 130, 246), 0.2);
            color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        /* Priority colors using CSS variables */
        .task-item.priority-High { border-left: 5px solid var(--error); }
        .task-item.priority-Medium { border-left: 5px solid var(--warning); }
        .task-item.priority-Low { border-left: 5px solid var(--success); }

        .alert {
            padding: 16px;
            margin-bottom: 24px;
            border-radius: var(--radius);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
        }
        .alert.error { 
            background: rgba(248, 113, 113, 0.1);
            color: var(--error);
            border: 1px solid rgba(248, 113, 113, 0.2);
        }
        .alert.info { 
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        /* Back button */
        .back-to-dashboard-btn {
            margin-top: 30px;
            display: inline-block;
            padding: 10px 16px;
            background-color: var(--secondary-color);
            color: var(--text-primary);
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .back-to-dashboard-btn:hover {
            background-color: var(--inactive);
            transform: translateY(-1px);
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .sidebar { 
                transform: translateX(-100%); /* Hide sidebar by default on small screens */
                width: 100%; /* Full width when shown */
            }
            .main-content { 
                margin-left: 0; /* No offset */
                width: 100%; /* Take full width */
            }
            .header {
                padding: 20px 24px;
            }
            .content {
                padding: 20px;
            }
            .calendar-container {
                padding: 15px;
            }
            .calendar-header h2 {
                font-size: 1.8em;
            }
            .calendar-header .nav-btn {
                padding: 8px 12px;
                font-size: 0.8em;
            }
            .calendar-grid {
                gap: 5px;
            }
            .calendar-weekday, .calendar-day {
                min-height: 70px; /* Adjust height for smaller screens */
            }
            .calendar-day-number {
                font-size: 1em;
            }
            .task-item {
                font-size: 0.75em;
                padding: 3px 6px;
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
            <a href="../admin/admin_dashboard.php">Dashboard</a>
            <a href="../client/manage.php">Clients</a>
            <a href="../project/manage.php">Projects</a>
            <a href="manage.php">Team</a>
            <a href="../expense/manage.php">Expenses</a>
             <a href="manage.php" class="active">To Do</a>
            <a href="../mail/manage.php">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="admin_all_tasks_calendar.php" class="active">Task Calendar</a> <!-- Set active for this page -->
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>All Tasks Calendar Overview</h1>
        </div>
        <div class="content">
            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn">
                        <i class="fas fa-arrow-left"></i> Previous
                    </a>
                    <h2>All Tasks: <?php echo htmlspecialchars($month_name) . ' ' . htmlspecialchars($current_year); ?></h2>
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">
                        Next <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert error"><i class="fas fa-times-circle"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="calendar-grid">
                    <?php
                    $days_of_week = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($days_of_week as $day): ?>
                        <div class="calendar-weekday"><?php echo $day; ?></div>
                    <?php endforeach;

                    // Fill leading empty days
                    for ($i = 0; $i < $day_of_week; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor;

                    // Populate days with tasks
                    for ($day = 1; $day <= $number_of_days; $day++):
                        $is_today = ($day == date('j') && $current_month == date('n') && $current_year == date('Y')) ? 'today' : '';
                    ?>
                        <div class="calendar-day <?php echo $is_today; ?>">
                            <span class="calendar-day-number"><?php echo $day; ?></span>
                            <?php if (isset($tasks_by_day[$day]) && !empty($tasks_by_day[$day])): ?>
                                <ul class="task-list">
                                    <?php foreach ($tasks_by_day[$day] as $task): ?>
                                        <li class="task-item priority-<?php echo htmlspecialchars($task['priority']); ?>" 
                                            title="<?php echo htmlspecialchars($task['title'] . ' - Assigned to: ' . ($task['assigned_to_name'] ?? 'N/A') . ' - Status: ' . $task['status']); ?>">
                                            <?php echo htmlspecialchars($task['title']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endfor;

                    // Fill trailing empty days to complete the last week
                    $remaining_days = (7 - (($day_of_week + $number_of_days) % 7)) % 7;
                    for ($i = 0; $i < $remaining_days; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                </div>

                <a href="manage.php" class="btn back-to-dashboard-btn">
                    <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back to Admin Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>