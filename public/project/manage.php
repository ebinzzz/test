<?php
// Database connection (adjust as needed)
// Assuming config.php contains the database connection details in a $conn variable
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
            // Simulate results for projects and clients
            if (strpos($sql, 'SELECT id, name FROM clients') !== false || strpos($sql, 'SELECT id, company_name as name FROM clients') !== false) {
                return new MockResult([
                    ['id' => 1, 'name' => 'Acme Corp'],
                    ['id' => 2, 'name' => 'Globex Inc.']
                ]);
            } elseif (strpos($sql, 'SELECT COUNT(*) as count FROM projects') !== false) {
                return new class extends MockResult {
                    public function __construct() {
                        parent::__construct();
                        $this->fetch_assoc_index = 0;
                    }
                    public function fetch_assoc() {
                        if ($this->fetch_assoc_index === 0) {
                            $this->fetch_assoc_index++;
                            return ['count' => 5]; // Mock total projects
                        }
                        return null;
                    }
                };
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM projects WHERE status = 'Active' OR status='In Progress'") !== false) {
                return new class extends MockResult {
                    public function __construct() {
                        parent::__construct();
                        $this->fetch_assoc_index = 0;
                    }
                    public function fetch_assoc() {
                        if ($this->fetch_assoc_index === 0) {
                            $this->fetch_assoc_index++;
                            return ['count' => 2]; // Mock active projects
                        }
                        return null;
                    }
                };
            } elseif (strpos($sql, "SELECT COUNT(*) as count FROM projects WHERE status = 'Planning'") !== false) {
                return new class extends MockResult {
                    public function __construct() {
                        parent::__construct();
                        $this->fetch_assoc_index = 0;
                    }
                    public function fetch_assoc() {
                        if ($this->fetch_assoc_index === 0) {
                            $this->fetch_assoc_index++;
                            return ['count' => 1]; // Mock pending projects (Planning status)
                        }
                        return null;
                    }
                };
            } elseif (strpos($sql, 'SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id') !== false || strpos($sql, 'SELECT p.*, c.company_name as client_name FROM projects p JOIN clients c ON p.client_id = c.id') !== false) {
                return new MockResult([
                    ['id' => 101, 'project_name' => 'New Website', 'description' => 'Develop a modern corporate website.', 'client_id' => 1, 'client_name' => 'Acme Corp', 'doc_link' => 'https://docs.acme.com/web', 'image_link' => 'https://placehold.co/100x50/ff0000/ffffff?text=Web', 'estimated_budget' => '15000.00', 'time_estimated' => '3 months', 'status' => 'Active'],
                    ['id' => 102, 'project_name' => 'Mobile App v1', 'description' => 'Build a cross-platform mobile application.', 'client_id' => 2, 'client_name' => 'Globex Inc.', 'doc_link' => 'https://docs.globex.com/app', 'image_link' => 'https://placehold.co/100x50/0000ff/ffffff?text=App', 'estimated_budget' => '25000.00', 'time_estimated' => '6 weeks', 'status' => 'Planning'],
                    ['id' => 103, 'project_name' => 'CRM Integration', 'description' => 'Integrate CRM with existing systems.', 'client_id' => 1, 'client_name' => 'Acme Corp', 'doc_link' => '', 'image_link' => '', 'estimated_budget' => '8000.00', 'time_estimated' => '1 month', 'status' => 'Completed']
                ]);
            }
            return false;
        }
        public function fetch_assoc() { return false; }
        public function error() { return "Mock DB Error: Connection not real."; }
    }
    $conn = new MockConnection();
}

// Handle project deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($conn instanceof mysqli) {
        // First, get the client_id of the project being deleted to decrement their project_count
        $stmt_get_client = $conn->prepare("SELECT client_id FROM projects WHERE id = ?");
        $stmt_get_client->bind_param("i", $delete_id);
        $stmt_get_client->execute();
        $result_get_client = $stmt_get_client->get_result();
        $project_to_delete = $result_get_client->fetch_assoc();
        $stmt_get_client->close();

        if ($project_to_delete) {
            $client_id_to_decrement = $project_to_delete['client_id'];

            $conn->begin_transaction(); // Start transaction for atomicity

            $sql_delete = "DELETE FROM projects WHERE id = $delete_id";
            if ($conn->query($sql_delete)) {
                // Decrement the project count for the associated client
                $sql_update_client = "UPDATE clients SET projects = GREATEST(0, projects - 1) WHERE id = $client_id_to_decrement";
                if ($conn->query($sql_update_client)) {
                    $conn->commit(); // Commit transaction if both successful
                    $message = "Project deleted successfully and client project count updated!";
                } else {
                    $conn->rollback(); // Rollback if client update fails
                    $error_message = "Error deleting project: " . $conn->error . " (Client count not updated).";
                }
            } else {
                $conn->rollback(); // Rollback if project delete fails
                $error_message = "Error deleting project: " . $conn->error;
            }
            // Redirect after deletion attempt (successful or not)
            header('Location: manage.php'); // Redirect to self to clear GET params
            exit();
        } else {
            $error_message = "Project not found for deletion.";
        }
    } else {
        $error_message = "Cannot delete project: Database connection is not available.";
    }
}

// Fetch total projects count
$total_projects = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM projects");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_projects = $row['count'];
    }
} else { // Mock connection handling
    $total_obj = $conn->query("SELECT COUNT(*) as count FROM projects");
    if ($total_obj && method_exists($total_obj, 'fetch_assoc')) {
        $row = $total_obj->fetch_assoc();
        $total_projects = $row['count'];
    }
}

// Fetch active projects count
$active_projects = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Active' OR status='In Progress'");
    if ($result) {
        $row = $result->fetch_assoc();
        $active_projects = $row['count'];
    }
} else { // Mock connection handling
    $active_obj = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Active'");
    if ($active_obj && method_exists($active_obj, 'fetch_assoc')) {
        $row = $active_obj->fetch_assoc();
        $active_projects = $row['count'];
    }
}


// Fetch pending projects count (using 'Planning' as pending status)
$pending_projects = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Planning'OR status = 'Pending'");
    if ($result) {
        $row = $result->fetch_assoc();
        $pending_projects = $row['count'];
    }
} else { // Mock connection handling
    $pending_obj = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Planning'");
    if ($pending_obj && method_exists($pending_obj, 'fetch_assoc')) {
        $row = $pending_obj->fetch_assoc();
        $pending_projects = $row['count'];
    }
}
$completed_projects = 0;
if ($conn instanceof mysqli) {
    $result = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'");
    if ($result) {
        $row = $result->fetch_assoc();
        $completed_projects = $row['count'];
    }

}

// Fetch projects with client names
$projects = [];
if ($conn instanceof mysqli) {
    // Assuming 'clients' table has 'company_name'
    $sql = "SELECT p.*, c.company_name as client_name FROM projects p JOIN clients c ON p.client_id = c.id ORDER BY p.id DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    } else {
        $error_message = "Error fetching projects: " . $conn->error;
    }
} else { // Mock connection handling
    $mock_projects_obj = $conn->query("SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id");
    if ($mock_projects_obj && property_exists($mock_projects_obj, 'data')) {
        $projects = $mock_projects_obj->data;
    } else if (empty($error_message)) {
        $error_message = "Projects could not be loaded due to database connection issues (mock data unavailable).";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Dashboard</title>
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
            /* Only show if message content exists */
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

        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }

        /* Status Badges (added for better visual representation of project status) */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-Planning {
            background: rgba(245, 158, 11, 0.2); /* Orange tone for planning/pending */
            color: #fbbf24;
        }
        .status-Active {
            background: rgba(16, 185, 129, 0.2); /* Green tone for active */
            color: #34d399;
        }
        .status-OnHold {
            background: rgba(107, 114, 128, 0.2); /* Grey tone for on hold */
            color: var(--text-muted);
        }
        .status-Completed {
            background: rgba(34, 197, 94, 0.2); /* Darker green for completed */
            color: #4ade80;
        }
        .status-Cancelled {
            background: rgba(248, 113, 113, 0.2); /* Red tone for cancelled */
            color: #f87171;
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
                /* Add a toggle button for sidebar for full responsiveness */
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
                min-width: 1000px; /* Adjusted min-width to accommodate new columns */
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
            
            /* No need for specific form-grid media query here, as it defaults to 1fr already */
        }
    </style>
    <script>
        function confirmDelete(projectId) {
            // Using a simple confirm for demonstration. In a real app, use a custom modal.
            if (confirm("Are you sure you want to delete this project? This will also decrement the project count for the associated client.")) {
                window.location.href = '?delete_id=' + projectId;
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
            <a href="../dashboard.php" >Dashboard</a>
            <a href="../client/manage.php">Clients</a>
            <a href="manage.php" class="active">Projects</a> <!-- Updated to link to this dashboard page -->
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
                <h1>Project Dashboard</h1>
                <div class="header-actions">
                    <!-- Link to the add_project.php page -->
                    <a href="add.php" class="btn btn-primary">Create New Project</a>
                    <a href="#" class="btn btn-secondary">Export Projects</a>
                </div>
            </div>
        </header>

        <main class="content">
            <!-- Display success or error messages -->
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

            <!-- Project Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Projects</h3>
                    <div class="value"><?= $total_projects ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Projects</h3>
                    <div class="value"> <?= $active_projects ?> </div>
                </div>
                <div class="stat-card">
                    <h3>Pending Projects</h3>
                    <div class="value"> <?= $pending_projects ?> </div>
                </div>
                <!-- Add more stat cards as needed, e.g., Completed Projects -->
                <div class="stat-card">
                    <h3>Completed Projects</h3>
                    <div class="value"><?=$completed_projects?></div> <!-- Placeholder for future implementation -->
                </div>
            </div>

            <!-- Projects Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Projects</h2>
                    <a href="add.php" class="btn btn-primary btn-sm">Add Project</a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Client</th>
                            <th>Description</th>
                            <th>Budget</th>
                            <th>Time Est.</th>
                            <th>Doc Link</th>
                            <th>Image Link</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($projects)): ?>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?= htmlspecialchars($project['project_name']) ?></td>
                                    <td><?= htmlspecialchars($project['client_name'] ?? 'N/A') ?></td>
                                    <td><?= nl2br(htmlspecialchars(substr($project['description'], 0, 100))) ?><?= (strlen($project['description']) > 100) ? '...' : '' ?></td>
                                    <td>$<?= number_format($project['estimated_budget'] ?? 0, 2) ?></td>
                                    <td><?= htmlspecialchars($project['time_estimated'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($project['doc_link'])): ?>
                                            <a href="<?= htmlspecialchars($project['doc_link']) ?>" target="_blank" class="btn btn-secondary btn-sm">Doc</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($project['image_link'])): ?>
                                            <a href="<?= htmlspecialchars($project['image_link']) ?>" target="_blank" class="btn btn-secondary btn-sm">Image</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= str_replace(' ', '', htmlspecialchars($project['status'] ?? '')) ?>">
                                            <?= htmlspecialchars($project['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- Link to an edit page (you'd create edit_project.php next) -->
                                            <a href="edit.php?id=<?= $project['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <!-- Delete button with confirmation -->
                                            <button type="button" onclick="confirmDelete(<?= $project['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 20px;">No projects found. Start by creating a new one!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
