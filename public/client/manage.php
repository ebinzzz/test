<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php"); // Adjust path to your login page if different
    exit();
}

// Database connection (adjust as needed)
@include_once '../config.php';

// Initialize message variables
$message = '';
$error_message = '';

// Check if $conn is set, if not, create a placeholder for demonstration
if (!isset($conn)) {
    $error_message = "Database connection not established. Please check 'config.php'.";
    // For demonstration, create a dummy $conn object to prevent errors.
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
    }
    class MockConnection {
        public function real_escape_string($str) { return $str; }
        public function query($sql) {
            error_log("Attempted query on mock connection: " . $sql);
            // Simulate results for clients counts based on status and total
            if (strpos($sql, "SELECT COUNT(*) as count FROM clients WHERE status = 'Active'") !== false) {
                 return new class extends MockResult {
                    public function __construct() { parent::__construct(); $this->fetch_assoc_index = 0; }
                    public function fetch_assoc() { if ($this->fetch_assoc_index === 0) { $this->fetch_assoc_index++; return ['count' => 1]; } return null; } // Mock active clients
                };
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM clients WHERE status = 'Pending'") !== false) {
                 return new class extends MockResult {
                    public function __construct() { parent::__construct(); $this->fetch_assoc_index = 0; }
                    public function fetch_assoc() { if ($this->fetch_assoc_index === 0) { $this->fetch_assoc_index++; return ['count' => 1]; } return null; } // Mock pending clients
                };
            } elseif (strpos($sql, 'SELECT SUM(initial_revenue) as total_revenue FROM clients') !== false) {
                 return new class extends MockResult {
                    public function __construct() { parent::__construct(); $this->fetch_assoc_index = 0; }
                    public function fetch_assoc() { if ($this->fetch_assoc_index === 0) { $this->fetch_assoc_index++; return ['total_revenue' => '15000.00']; } return null; } // Mock total revenue
                };
            } elseif (strpos($sql, 'SELECT SUM(projects) as total_projects FROM clients') !== false) { // Changed this line to reflect user's query
                 return new class extends MockResult {
                    public function __construct() { parent::__construct(); $this->fetch_assoc_index = 0; }
                    public function fetch_assoc() { if ($this->fetch_assoc_index === 0) { $this->fetch_assoc_index++; return ['total_projects' => 3]; } return null; } // Mock total projects
                };
            } elseif (strpos($sql, 'SELECT COUNT(*) as count FROM clients') !== false) {
                 return new class extends MockResult {
                    public function __construct() { parent::__construct(); $this->fetch_assoc_index = 0; }
                    public function fetch_assoc() { if ($this->fetch_assoc_index === 0) { $this->fetch_assoc_index++; return ['count' => 2]; } return null; } // Mock total clients
                };
            } elseif (strpos($sql, 'SELECT id, company_name, contact_person, email, phone, projects as project_count, status, initial_revenue, created_at FROM clients') !== false) {
                return new MockResult([
                    ['id' => 1, 'company_name' => 'Acme Corp', 'contact_person' => 'John Doe', 'email' => 'john@acme.com', 'phone' => '111-222-3333', 'projects' => 2, 'status' => 'Active', 'initial_revenue' => '10000.00', 'created_at' => '2023-01-15 10:00:00'],
                    ['id' => 2, 'company_name' => 'Globex Inc.', 'contact_person' => 'Jane Smith', 'email' => 'jane@globex.com', 'phone' => '444-555-6666', 'projects' => 1, 'status' => 'Pending', 'initial_revenue' => '5000.00', 'created_at' => '2023-03-20 14:30:00']
                ]);
            }
            // Simulate successful delete for clients
            if (strpos($sql, 'DELETE FROM clients WHERE id =') !== false) {
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
            // Mock prepare for delete logic
            if (strpos($sql, "DELETE FROM clients WHERE id = ?") !== false) {
                return new class($this) { // Pass mock connection to access its query method
                    private $mock_conn;
                    private $bound_params;
                    public function __construct($mock_conn_instance) {
                        $this->mock_conn = $mock_conn_instance;
                    }
                    public function bind_param(...$params) { $this->bound_params = $params; }
                    public function execute() { /* No real execution */ return true; } // Simulate success
                    public function get_result() { return new MockResult([]); }
                    public function fetch_assoc() { return false; }
                    public function close() {}
                };
            }
            return false; // For other prepares if any
        }
    }
    $conn = new MockConnection();
}

// Handle client deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($conn instanceof mysqli) {
        $conn->begin_transaction(); // Start transaction for atomicity

        // Delete the client record. ON DELETE CASCADE will handle associated projects.
        $sql_delete_client = "DELETE FROM clients WHERE id = ?";
        $stmt = $conn->prepare($sql_delete_client);
        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $conn->commit(); // Commit transaction if successful
                $message = "Client and all associated projects deleted successfully!";
            } else {
                $conn->rollback(); // Rollback on failure
                $error_message = "Error deleting client: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $conn->rollback(); // Rollback if prepare fails
            $error_message = "Error preparing client deletion statement: " . $conn->error;
        }
    } else {
        $error_message = "Cannot delete client: Database connection is not available.";
    }
    // Redirect to self to clear GET params and show updated list
    header('Location: manage.php');
    exit();
}

// Fetch total clients count
$total_clients = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM clients");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_clients = $row['count'];
    }
} else { // Mock connection handling
    $total_obj = $conn->query("SELECT COUNT(*) as count FROM clients");
    if ($total_obj && method_exists($total_obj, 'fetch_assoc')) {
        $row = $total_obj->fetch_assoc();
        $total_clients = $row['count'];
    }
}

// Fetch active clients count
$active_clients_count = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM clients WHERE status = 'Active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $active_clients_count = $row['count'];
    }
} else { // Mock connection handling
    $active_obj = $conn->query("SELECT COUNT(*) as count FROM clients WHERE status = 'Active'");
    if ($active_obj && method_exists($active_obj, 'fetch_assoc')) {
        $row = $active_obj->fetch_assoc();
        $active_clients_count = $row['count'];
    }
}

// Fetch pending clients count
$pending_clients_count = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM clients WHERE status = 'Pending'");
    if ($result) {
        $row = $result->fetch_assoc();
        $pending_clients_count = $row['count'];
    }
} else { // Mock connection handling
    $pending_obj = $conn->query("SELECT COUNT(*) as count FROM clients WHERE status = 'Pending'");
    if ($pending_obj && method_exists($pending_obj, 'fetch_assoc')) {
        $row = $pending_obj->fetch_assoc();
        $pending_clients_count = $row['count'];
    }
}

// Fetch total revenue
$total_revenue = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT SUM(initial_revenue) as total_revenue FROM clients");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_revenue = $row['total_revenue'] ?? 0;
    }
} else { // Mock connection handling
    $revenue_obj = $conn->query("SELECT SUM(initial_revenue) as total_revenue FROM clients");
    if ($revenue_obj && method_exists($revenue_obj, 'fetch_assoc')) {
        $row = $revenue_obj->fetch_assoc();
        $total_revenue = $row['total_revenue'];
    }
}

// Fetch total project count across all clients
$total_client_projects = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT SUM(projects) as total_projects FROM clients");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_client_projects = $row['total_projects'] ?? 0;
    }
} else { // Mock connection handling
    $projects_obj = $conn->query("SELECT SUM(projects) as total_projects FROM clients"); // Adjusted mock to match user's query
    if ($projects_obj && method_exists($projects_obj, 'fetch_assoc')) {
        $row = $projects_obj->fetch_assoc();
        $total_client_projects = $row['total_projects'];
    }
}


// Fetch all clients with status and initial_revenue
$clients = [];
if ($conn instanceof mysqli) {
    $sql = "SELECT id, company_name, contact_person, email, phone, projects as project_count, status, initial_revenue, created_at FROM clients ORDER BY company_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    } else {
        $error_message = "Error fetching clients: " . $conn->error;
    }
} else { // Mock connection handling
    $mock_clients_obj = $conn->query("SELECT id, company_name, contact_person, email, phone, projects as project_count, status, initial_revenue, created_at FROM clients");
    if ($mock_clients_obj && property_exists($mock_clients_obj, 'data')) {
        $clients = $mock_clients_obj->data;
    } else if (empty($error_message)) {
        $error_message = "Clients could not be loaded due to database connection issues (mock data unavailable).";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients</title>
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; /* Changed to Inter */
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
            /* Control display dynamically via PHP message variables */
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

        /* Client Table */
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
            flex-wrap: wrap; /* Allow items to wrap */
            gap: 10px; /* Space between items */
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

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }
        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        .status-inactive {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-muted);
        }

        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }

        /* Form Select (for filter) */
        .form-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
            border-radius: 6px;
            text-align: center;
            background-color: grey;
            color:white;
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
                min-width: 900px; /* Ensure table is wide enough to scroll on small screens */
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        function confirmClientDelete(clientId) {
            if (confirm("Are you sure you want to delete this client? This will PERMANENTLY delete the client and ALL their associated projects due to database cascade rules.")) {
                window.location.href = '?delete_id=' + clientId;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alert messages
            const alertBox = document.querySelector('.alert');
            if (alertBox && alertBox.style.display !== 'none') {
                setTimeout(() => {
                    alertBox.style.transition = 'opacity 0.5s ease';
                    alertBox.style.opacity = '0';
                    setTimeout(() => alertBox.remove(), 500);
                }, 3000);
            }

            // Status filter logic
            document.getElementById('statusFilter').addEventListener('change', function () {
                let selected = this.value.toLowerCase();
                let rows = document.querySelectorAll('#clientsTable tbody tr');

                rows.forEach(row => {
                    let status = row.getAttribute('data-status');
                    if (selected === 'all' || status === selected) {
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
            <a href="manage.php" class="active">Clients</a>
            <a href="../project/manage.php">Projects</a>
            <a href="../team/manage.php">Team</a>
            <a href="../expense/manage.php">Expenses</a>
                          <a href="../assignment/manage.php">To Do</a>

            <a href="../mail/manage.php">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Client Management</h1>
                <div class="header-actions">
                    <a href="add.php" class="btn btn-primary">Add New Client</a>
                    <a href="#" class="btn btn-secondary">Export Clients</a>
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

            <!-- Client Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Clients</h3>
                    <div class="value"><?= $total_clients ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Clients</h3>
                    <div class="value"><?= $active_clients_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Clients</h3>
                    <div class="value"><?= $pending_clients_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Projects</h3>
                    <div class="value"><?= $total_client_projects ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">$<?= number_format($total_revenue, 2) ?></div>
                </div>
            </div>

            <!-- Clients Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Clients</h2>
                    <div class="header-actions">
                        <select id="statusFilter" class="form-select" style="width: auto;">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <a href="add.php" class="btn btn-primary btn-sm">Add Client</a>
                    </div>
                </div>
                <table class="table" id="clientsTable">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Projects</th>
                            <th>Revenue</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($clients)): ?>
                            <?php foreach ($clients as $client): ?>
                                <tr data-status="<?= strtolower(htmlspecialchars($client['status'] ?? '')) ?>">
                                    <td><strong><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($client['contact_person'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($client['email'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(htmlspecialchars($client['status'] ?? '')) ?>">
                                            <?= htmlspecialchars($client['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($client['project_count'] ?? 0) ?></td>
                                    <td>$<?= number_format($client['initial_revenue'] ?? 0, 2) ?></td>
                                    <td><?= date('M j, Y', strtotime($client['created_at'] ?? 'now')) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <button type="button" onclick="confirmClientDelete(<?= $client['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 20px;">No clients found. Start by adding a new one!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
