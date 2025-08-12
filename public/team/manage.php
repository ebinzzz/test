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
            if (strpos($sql, "SELECT COUNT(*) as count FROM team_members WHERE status = 'Active'") !== false) {
                 return new MockResult([['count' => 3]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM team_members WHERE status = 'On Leave'") !== false) {
                 return new MockResult([['count' => 1]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM team_members WHERE status = 'Inactive'") !== false) {
                 return new MockResult([['count' => 0]]);
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM team_members") !== false) {
                 return new MockResult([['count' => 4]]);
            } elseif (strpos($sql, 'SELECT SUM(salary) as total_salary_cost FROM team_members') !== false) {
                 return new MockResult([['total_salary_cost' => '250000.00']]);
            } elseif (strpos($sql, 'SELECT id, full_name, email, phone, role, status, joined_date, salary, profile_picture FROM team_members') !== false) {
                return new MockResult([
                    ['id' => 1, 'full_name' => 'Alice Johnson', 'email' => 'alice@example.com', 'phone' => '123-456-7890', 'role' => 'Software Engineer', 'status' => 'Active', 'joined_date' => '2022-01-01 09:00:00', 'salary' => '75000.00', 'profile_picture' => 'default_profile.png'],
                    ['id' => 2, 'full_name' => 'Bob Williams', 'email' => 'bob@example.com', 'phone' => '098-765-4321', 'role' => 'UI/UX Designer', 'status' => 'Active', 'joined_date' => '2022-03-10 10:00:00', 'salary' => '60000.00', 'profile_picture' => 'default_profile.png'],
                    ['id' => 3, 'full_name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'phone' => '555-123-4567', 'role' => 'Project Manager', 'status' => 'On Leave', 'joined_date' => '2021-06-15 11:00:00', 'salary' => '80000.00', 'profile_picture' => 'default_profile.png'],
                    ['id' => 4, 'full_name' => 'Diana Prince', 'email' => 'diana@example.com', 'phone' => '777-888-9999', 'role' => 'QA Engineer', 'status' => 'Active', 'joined_date' => '2023-02-20 12:00:00', 'salary' => '35000.00', 'profile_picture' => 'default_profile.png']
                ]);
            }
            if (strpos($sql, 'DELETE FROM team_members WHERE id =') !== false) {
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
            if (strpos($sql, "DELETE FROM team_members WHERE id = ?") !== false) {
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

// Handle team member deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($conn instanceof mysqli) {
        $conn->begin_transaction();
        $sql_delete_member = "DELETE FROM team_members WHERE id = ?";
        $stmt = $conn->prepare($sql_delete_member);
        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $conn->commit();
                $message = "Team member deleted successfully!";
            } else {
                $conn->rollback();
                $error_message = "Error deleting team member: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $conn->rollback();
            $error_message = "Error preparing team member deletion statement: " . $conn->error;
        }
    } else {
        $error_message = "Cannot delete team member: Database connection is not available.";
    }
    header('Location: manage.php');
    exit();
}

// Fetch statistics
$total_members = 0;
$active_members = 0;
$on_leave_members = 0;
$total_salary_cost = 0;

if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM team_members");
    if ($result) { $total_members = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM team_members WHERE status = 'Active'");
    if ($result) { $active_members = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT COUNT(*) as count FROM team_members WHERE status = 'On Leave'");
    if ($result) { $on_leave_members = $result->fetch_assoc()['count']; }

    $result = $conn->query("SELECT SUM(salary) as total_salary_cost FROM team_members");
    if ($result) { $total_salary_cost = $result->fetch_assoc()['total_salary_cost'] ?? 0; }

} else { // Mock connection statistics
    $total_members_obj = $conn->query("SELECT COUNT(*) as count FROM team_members");
    if ($total_members_obj && method_exists($total_members_obj, 'fetch_assoc')) {
        $total_members = $total_members_obj->fetch_assoc()['count'];
    }
    $active_members_obj = $conn->query("SELECT COUNT(*) as count FROM team_members WHERE status = 'Active'");
    if ($active_members_obj && method_exists($active_members_obj, 'fetch_assoc')) {
        $active_members = $active_members_obj->fetch_assoc()['count'];
    }
    $on_leave_members_obj = $conn->query("SELECT COUNT(*) as count FROM team_members WHERE status = 'On Leave'");
    if ($on_leave_members_obj && method_exists($on_leave_members_obj, 'fetch_assoc')) {
        $on_leave_members = $on_leave_members_obj->fetch_assoc()['count'];
    }
    $total_salary_cost_obj = $conn->query("SELECT SUM(salary) as total_salary_cost FROM team_members");
    if ($total_salary_cost_obj && method_exists($total_salary_cost_obj, 'fetch_assoc')) {
        $total_salary_cost = $total_salary_cost_obj->fetch_assoc()['total_salary_cost'];
    }
}


// Fetch all team members
$team_members = [];
if ($conn instanceof mysqli) {
    $sql = "SELECT id, full_name, email, phone, role, status, joined_date, salary, profile_picture FROM team_members ORDER BY full_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $team_members[] = $row;
        }
    } else {
        $error_message = "Error fetching team members: " . $conn->error;
    }
} else { // Mock connection handling
    $mock_members_obj = $conn->query("SELECT id, full_name, email, phone, role, status, joined_date, salary, profile_picture FROM team_members");
    if ($mock_members_obj && property_exists($mock_members_obj, 'data')) {
        $team_members = $mock_members_obj->data;
    } else if (empty($error_message)) {
        $error_message = "Team members could not be loaded due to database connection issues (mock data unavailable).";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - Zorqent Technology</title>
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

        /* Team Member Table */
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
            gap: 10px;
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
        .status-on-leave {
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
            flex-wrap: wrap;
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
                min-width: 900px;
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
        function confirmTeamMemberDelete(memberId) {
            if (confirm("Are you sure you want to delete this team member? This action cannot be undone.")) {
                window.location.href = '?delete_id=' + memberId;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.querySelector('.alert');
            if (alertBox && alertBox.style.display !== 'none') {
                setTimeout(() => {
                    alertBox.style.transition = 'opacity 0.5s ease';
                    alertBox.style.opacity = '0';
                    setTimeout(() => alertBox.remove(), 500);
                }, 3000);
            }

            document.getElementById('statusFilter').addEventListener('change', function () {
                let selected = this.value.toLowerCase();
                let rows = document.querySelectorAll('#teamMembersTable tbody tr');

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
            <a href="../client/manage.php">Clients</a>
            <a href="../project/manage.php">Projects</a>
            <a href="manage.php" class="active">Team</a>
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
                <h1>Team Management</h1>
                <div class="header-actions">
                    <a href="add.php" class="btn btn-primary">Add New Team Member</a>
                    <a href="#" class="btn btn-secondary">Export Team Data</a>
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

            <!-- Team Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Members</h3>
                    <div class="value"><?php echo $total_members; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Members</h3>
                    <div class="value"><?php echo $active_members; ?></div>
                </div>
                <div class="stat-card">
                    <h3>On Leave</h3>
                    <div class="value"><?php echo $on_leave_members; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Salary Cost</h3>
                    <div class="value">$<?php echo number_format($total_salary_cost, 2); ?></div>
                </div>
            </div>

            <!-- Team Members Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Team Members</h2>
                    <div class="header-actions">
                        <select id="statusFilter" class="form-select" style="width: auto;">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="on leave">On Leave</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <a href="add.php" class="btn btn-primary btn-sm">Add Member</a>
                    </div>
                </div>
                <table class="table" id="teamMembersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Salary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($team_members)): ?>
                            <?php foreach ($team_members as $member): ?>
                                <tr data-status="<?php echo strtolower(htmlspecialchars($member['status'] ?? '')); ?>">
                                    <td><strong><?php echo htmlspecialchars($member['full_name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($member['role'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($member['status'] ?? ''))); ?>">
                                            <?php echo htmlspecialchars($member['status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($member['joined_date'] ?? 'now')); ?></td>
                                    <td>$<?php echo number_format($member['salary'] ?? 0, 2); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <button type="button" onclick="confirmTeamMemberDelete(<?php echo $member['id']; ?>)" class="btn btn-danger btn-sm">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 20px;">No team members found. Start by adding a new one!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
