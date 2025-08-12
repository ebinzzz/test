<?php
// --- Configuration and Initialization ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


@include_once '../config.php';

function getDatabaseConnection() {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        return $GLOBALS['conn'];
    }

    // Fallback Mock Connection for demonstration/development
    class MockResult {
        private $data;
        private $pointer = 0;

        public function __construct($data) {
            $this->data = $data;
        }

        public function fetch_assoc() {
            if ($this->pointer < count($this->data)) {
                return $this->data[$this->pointer++];
            }
            return null;
        }
        public function fetch_all($mode = MYSQLI_ASSOC) {
            return $this->data;
        }

        public function num_rows() {
            return count($this->data);
        }
    }

    class MockConnection {
        public function query($sql) {
            if (strpos($sql, 'SELECT id, company_name') !== false) {
                return new MockResult([
                    ['id' => 1, 'company_name' => 'Client A'],
                    ['id' => 2, 'company_name' => 'Client B'],
                    ['id' => 3, 'company_name' => 'Client C']
                ]);
            }
            if (strpos($sql, 'SELECT id, project_name FROM projects WHERE client_id') !== false) {
                return new MockResult([
                    ['id' => 101, 'project_name' => 'Project X'],
                    ['id' => 102, 'project_name' => 'Project Y']
                ]);
            }
            if (strpos($sql, 'SELECT id, project_name') !== false) {
                return new MockResult([
                    ['id' => 101, 'project_name' => 'Project X'],
                    ['id' => 102, 'project_name' => 'Project Y'],
                    ['id' => 103, 'project_name' => 'Project Z']
                ]);
            }
            if (strpos($sql, 'SELECT SUM(amount)') !== false) {
                if (strpos($sql, 'INTERVAL 30 DAY') !== false || strpos($sql, 'date_incurred >=') !== false) {
                    return new MockResult([['total' => 450.00]]);
                }
                return new MockResult([['total' => 1500.75]]);
            }
            if (strpos($sql, 'SELECT COUNT(*)') !== false) {
                return new MockResult([['count' => 25]]);
            }
            if (strpos($sql, 'GROUP BY category') !== false || strpos($sql, 'GROUP BY e.category') !== false) {
                $mock_data = [
                    ['category' => 'Software', 'total' => 650.00],
                    ['category' => 'Hardware', 'total' => 300.25],
                    ['category' => 'Marketing', 'total' => 550.50]
                ];
                return new MockResult($mock_data);
            }
            $mock_expenses = [];
            for ($i = 1; $i <= 10; $i++) {
                $mock_expenses[] = [
                    'id' => $i, 
                    'expense_name' => "Mock Expense #{$i}", 
                    'amount' => rand(50, 500),
                    'client_name' => $i % 2 == 0 ? 'Client A' : 'Client B', 
                    'project_name' => $i % 2 == 0 ? 'Project X' : 'Project Y',
                    'category' => ['Software', 'Hardware', 'Marketing'][rand(0, 2)], 
                    'date_incurred' => date('Y-m-d', strtotime("-{$i} days"))
                ];
            }
            return new MockResult($mock_expenses);
        }

        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                private $params = [];
                private $types = '';
                
                public function __construct($sql) {
                    $this->sql = $sql;
                }
                
                public function bind_param($types, ...$vars) {
                    $this->types = $types;
                    $this->params = $vars;
                    return true;
                }
                
                public function execute() { 
                    return true; 
                }
                
                public function get_result() {
                    // Mock different results based on query type
                    if (strpos($this->sql, 'SELECT id, project_name FROM projects WHERE client_id') !== false) {
                        return new MockResult([
                            ['id' => 101, 'project_name' => 'Project X'],
                            ['id' => 102, 'project_name' => 'Project Y']
                        ]);
                    }
                    if (strpos($this->sql, 'SELECT SUM(e.amount)') !== false) {
                        if (strpos($this->sql, 'date_incurred >=') !== false && count($this->params) > 0) {
                            return new MockResult([['total' => 450.00]]);
                        }
                        return new MockResult([['total' => 1500.75]]);
                    }
                    if (strpos($this->sql, 'SELECT COUNT(*)') !== false) {
                        return new MockResult([['count' => 25]]);
                    }
                    if (strpos($this->sql, 'GROUP BY e.category') !== false) {
                        return new MockResult([
                            ['category' => 'Software', 'total' => 650.00],
                            ['category' => 'Hardware', 'total' => 300.25],
                            ['category' => 'Marketing', 'total' => 550.50]
                        ]);
                    }
                    
                    // Default mock expenses
                    $mock_expenses = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $mock_expenses[] = [
                            'id' => $i, 
                            'expense_name' => "Mock Expense #{$i}", 
                            'amount' => rand(50, 500),
                            'client_name' => $i % 2 == 0 ? 'Client A' : 'Client B', 
                            'project_name' => $i % 2 == 0 ? 'Project X' : 'Project Y',
                            'category' => ['Software', 'Hardware', 'Marketing'][rand(0, 2)], 
                            'date_incurred' => date('Y-m-d', strtotime("-{$i} days"))
                        ];
                    }
                    return new MockResult($mock_expenses);
                }
                
                public function close() {}
                public function error() { return ''; }
            };
        }
        public function close() {}
    }
    return new MockConnection();
}

$conn = getDatabaseConnection();

// Handle AJAX request for projects
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_projects' && isset($_GET['client_id'])) {
    header('Content-Type: application/json');
    $client_id = (int)$_GET['client_id'];
    $projects = [];
    
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name");
        if ($stmt) {
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Mock projects for development
        $projects = [
            ['id' => 101, 'project_name' => 'Project X'],
            ['id' => 102, 'project_name' => 'Project Y']
        ];
    }
    
    echo json_encode($projects);
    exit();
}

// Fetch all clients for the dropdown
$all_clients = [];
if ($conn instanceof mysqli) {
    $clients_result = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name");
    if ($clients_result) {
        while ($row = $clients_result->fetch_assoc()) {
            $all_clients[] = $row;
        }
    }
} else {
    // Mock clients for development
    $all_clients = [
        ['id' => 1, 'company_name' => 'Client A'], 
        ['id' => 2, 'company_name' => 'Client B'],
        ['id' => 3, 'company_name' => 'Client C']
    ];
}

$message = '';
$error_message = '';

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($conn instanceof mysqli) {
        $sql_delete = "DELETE FROM expenses WHERE id = ?";
        $stmt = $conn->prepare($sql_delete);
        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "Expense deleted successfully!";
            } else {
                $error_message = "Error deleting expense: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $message = "Expense deleted successfully (mock mode).";
    }
    header('Location: manage.php');
    exit();
}

// Build filter conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($_GET['client_id'])) {
    $conditions[] = "e.client_id = ?";
    $params[] = (int)$_GET['client_id'];
    $types .= 'i';
}
if (!empty($_GET['project_id'])) {
    $conditions[] = "e.project_id = ?";
    $params[] = (int)$_GET['project_id'];
    $types .= 'i';
}
if (!empty($_GET['category'])) {
    $conditions[] = "e.category LIKE ?";
    $params[] = '%' . $_GET['category'] . '%';
    $types .= 's';
}
if (!empty($_GET['start_date'])) {
    $conditions[] = "e.date_incurred >= ?";
    $params[] = $_GET['start_date'];
    $types .= 's';
}
if (!empty($_GET['end_date'])) {
    $conditions[] = "e.date_incurred <= ?";
    $params[] = $_GET['end_date'];
    $types .= 's';
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

// Helper function to execute prepared statements
function executeQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch statistics
$total_expenses = 0;
$expenses_count = 0;
$last_30_days_expenses = 0;

// Get total expenses
$sql_total = "SELECT SUM(e.amount) AS total FROM expenses e LEFT JOIN clients c ON e.client_id = c.id LEFT JOIN projects p ON e.project_id = p.id" . $where_clause;
$result = executeQuery($conn, $sql_total, $types, $params);
if ($result) {
    $row = $result->fetch_assoc();
    $total_expenses = $row['total'] ?? 0;
}

// Get expenses count
$sql_count = "SELECT COUNT(*) AS count FROM expenses e LEFT JOIN clients c ON e.client_id = c.id LEFT JOIN projects p ON e.project_id = p.id" . $where_clause;
$result = executeQuery($conn, $sql_count, $types, $params);
if ($result) {
    $row = $result->fetch_assoc();
    $expenses_count = $row['count'] ?? 0;
}

// Get last 30 days expenses
$last_30_conditions = $conditions;
$last_30_params = $params;
$last_30_types = $types;

$last_30_conditions[] = "e.date_incurred >= ?";
$last_30_params[] = date('Y-m-d', strtotime('-30 days'));
$last_30_types .= 's';

$last_30_where_clause = '';
if (!empty($last_30_conditions)) {
    $last_30_where_clause = " WHERE " . implode(" AND ", $last_30_conditions);
}

$sql_30_days = "SELECT SUM(e.amount) AS total FROM expenses e LEFT JOIN clients c ON e.client_id = c.id LEFT JOIN projects p ON e.project_id = p.id" . $last_30_where_clause;
$result = executeQuery($conn, $sql_30_days, $last_30_types, $last_30_params);
if ($result) {
    $row = $result->fetch_assoc();
    $last_30_days_expenses = $row['total'] ?? 0;
}

// Fetch chart data
$category_data = [];
$sql_chart = "SELECT e.category, SUM(e.amount) AS total FROM expenses e LEFT JOIN clients c ON e.client_id = c.id LEFT JOIN projects p ON e.project_id = p.id" . $where_clause . " GROUP BY e.category ORDER BY total DESC";
$result = executeQuery($conn, $sql_chart, $types, $params);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category_data[] = $row;
    }
}

$chart_labels = json_encode(array_column($category_data, 'category'));
$chart_data = json_encode(array_column($category_data, 'total'));

// Fetch all expenses for the table
$expenses = [];
$sql = "SELECT e.*, c.company_name AS client_name, p.project_name FROM expenses e
        LEFT JOIN clients c ON e.client_id = c.id
        LEFT JOIN projects p ON e.project_id = p.id" . $where_clause . " ORDER BY e.date_incurred DESC";
$result = executeQuery($conn, $sql, $types, $params);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            margin-left: 280px;
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

        .filter-controls {
            background: var(--surface-elevated);
            padding: 16px;
            border-radius: var(--radius);
            margin-top: 24px;
            border: 1px solid var(--border);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filter-form label {
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
            margin-right: 4px;
        }

        .filter-form input, .filter-form select, .filter-form button {
            padding: 8px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            background: var(--surface);
            color: var(--text-primary);
        }

        .filter-form input:focus, .filter-form select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

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

        .content {
            padding: 32px;
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
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
        
        .chart-container {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 32px;
            height: 350px;
            position: relative;
        }
        
        #expenseChart {
            max-height: 100%;
            width: auto;
            margin: auto;
            display: block;
        }

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
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
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
        }

        .table tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 1024px) {
            .sidebar { width: 240px; }
            .main-content { margin-left: 240px; }
            .header { padding: 20px 24px; }
            .content { padding: 20px; }
            .table-container { overflow-x: auto; }
            .table { min-width: 800px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
        }

        @media (max-width: 768px) {
            .sidebar { 
                transform: translateX(-100%); 
                width: 280px; 
                transition: transform 0.3s ease-in-out;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content { margin-left: 0; }
            .header { padding: 20px 24px; }
            .content { padding: 20px; }
            .table-container { overflow-x: auto; }
            .table { min-width: 800px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table thead, .table tbody, .table th, .table td, .table tr {
                display: block;
            }
            .table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .table tr {
                border-bottom: 1px solid var(--border);
                margin-bottom: 10px;
                background: var(--surface-elevated);
                border-radius: var(--radius);
                padding: 10px;
            }
            .table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            .table td:before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: var(--text-secondary);
                text-align: left;
            }
            .table td.table-actions-cell:before {
                content: 'Actions';
            }
            .table td:last-child {
                border-bottom: none;
            }
        }

        @media (max-width: 480px) {
            .header { padding: 16px 20px; }
            .content { padding: 16px; }
        }
    </style>
    <script>
        function confirmDelete(expenseId) {
            if (confirm("Are you sure you want to delete this expense? This action cannot be undone.")) {
                window.location.href = '?delete_id=' + expenseId;
            }
        }
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
            <a href="manage.php" class="active">Expenses</a>
                          <a href="../assignment/manage.php">To Do</a>

            <a href="../mail/manage.php">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Expense Dashboard</h1>
                <div class="header-actions">
                    <a href="add.php" class="btn btn-primary">Add New Expense</a>
                    <a href="#" class="btn btn-secondary">Export Data</a>
                </div>
            </div>
            <div class="filter-controls">
                <form method="GET" action="manage.php" class="filter-form">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label for="client_id">Client:</label>
                        <select id="client_id" name="client_id" onchange="fetchProjects(this.value)">
                            <option value="">All Clients</option>
                            <?php foreach ($all_clients as $client): ?>
                                <option value="<?= htmlspecialchars($client['id']) ?>" <?= (!empty($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label for="project_id">Project:</label>
                        <select id="project_id" name="project_id">
                            <option value="">All Projects</option>
                            <?php
                                // If a client is already selected, populate projects for the initial page load
                                if (!empty($_GET['client_id'])) {
                                    $client_id = (int)$_GET['client_id'];
                                    if ($conn instanceof mysqli) {
                                        $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name");
                                        if ($stmt) {
                                            $stmt->bind_param("i", $client_id);
                                            $stmt->execute();
                                            $projects_result = $stmt->get_result();
                                            while ($project = $projects_result->fetch_assoc()) {
                                                $selected = (!empty($_GET['project_id']) && $_GET['project_id'] == $project['id']) ? 'selected' : '';
                                                echo "<option value=\"{$project['id']}\" {$selected}>" . htmlspecialchars($project['project_name']) . "</option>";
                                            }
                                            $stmt->close();
                                        }
                                    } else {
                                        // Mock projects for selected client
                                        $mock_projects = [
                                            ['id' => 101, 'project_name' => 'Project X'],
                                            ['id' => 102, 'project_name' => 'Project Y']
                                        ];
                                        foreach ($mock_projects as $project) {
                                            $selected = (!empty($_GET['project_id']) && $_GET['project_id'] == $project['id']) ? 'selected' : '';
                                            echo "<option value=\"{$project['id']}\" {$selected}>" . htmlspecialchars($project['project_name']) . "</option>";
                                        }
                                    }
                                }
                            ?>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label for="category">Category:</label>
                        <input type="text" id="category" name="category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>" placeholder="Enter category">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label for="start_date">From Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <label for="end_date">To Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                    </div>

                    <div style="display: flex; gap: 8px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <a href="manage.php" class="btn btn-secondary btn-sm">Clear</a>
                    </div>
                </form>
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

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Expenses</h3>
                    <div class="value">$<?= number_format($total_expenses, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Last 30 Days</h3>
                    <div class="value">$<?= number_format($last_30_days_expenses, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Records</h3>
                    <div class="value"><?= $expenses_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Expense</h3>
                    <div class="value">$<?= $expenses_count > 0 ? number_format($total_expenses / $expenses_count, 2) : '0.00' ?></div>
                </div>
            </div>

            <div class="chart-container">
                <h2 style="font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px; text-align: center;">Expenses by Category</h2>
                <?php if (!empty($category_data)): ?>
                    <canvas id="expenseChart"></canvas>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 20px;">No expense data available for charting.</p>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2>All Expenses</h2>
                    <a href="add.php" class="btn btn-primary btn-sm">Add Expense</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Expense Name</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expenses)): ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td data-label="Date"><?= htmlspecialchars($expense['date_incurred']) ?></td>
                                    <td data-label="Expense Name"><?= htmlspecialchars($expense['expense_name']) ?></td>
                                    <td data-label="Client"><?= htmlspecialchars($expense['client_name'] ?? 'N/A') ?></td>
                                    <td data-label="Project"><?= htmlspecialchars($expense['project_name'] ?? 'N/A') ?></td>
                                    <td data-label="Category"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></td>
                                    <td data-label="Amount">$<?= number_format($expense['amount'], 2) ?></td>
                                    <td class="table-actions-cell">
                                        <div class="table-actions">
                                            <a href="edit.php?id=<?= $expense['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <button type="button" onclick="confirmDelete(<?= $expense['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">No expenses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        const categoryLabels = <?= $chart_labels ?>;
        const categoryData = <?= $chart_data ?>;
        
        if (categoryData && categoryData.length > 0) {
            const ctx = document.getElementById('expenseChart').getContext('2d');
            const chartColors = [
                '#3b82f6', // primary-color
                '#10b981', // success
                '#f59e0b', // warning
                '#f87171', // error
                '#64748b', // secondary
                '#a855f7', // purple
                '#ec4899', // pink
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: chartColors.slice(0, categoryData.length),
                        borderWidth: 1,
                        borderColor: '#334155'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#cbd5e1'
                            }
                        },
                        title: {
                            display: false
                        }
                    }
                }
            });
        }

        function fetchProjects(clientId) {
            const projectDropdown = document.getElementById('project_id');
            // Clear existing options
            projectDropdown.innerHTML = '<option value="">All Projects</option>';

            if (clientId === "") {
                return;
            }
            
            // Use jQuery for AJAX request
            $.ajax({
                url: window.location.pathname,
                type: 'GET',
                data: { 
                    ajax: 'get_projects',
                    client_id: clientId 
                },
                dataType: 'json',
                success: function(projects) {
                    if (projects && projects.length > 0) {
                        projects.forEach(function(project) {
                            const option = document.createElement('option');
                            option.value = project.id;
                            option.textContent = project.project_name;
                            projectDropdown.appendChild(option);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Failed to fetch projects:", error);
                    console.log("Response:", xhr.responseText);
                }
            });
        }
    </script>
</body>
</html>