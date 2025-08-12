<?php
// Database connection (adjust as needed)
// Assuming config.php contains the database connection details in a $conn variable
@include_once '../config.php';

// Initialize message variables
$message = '';
$error_message = '';

// Check if $conn is set, if not, create a placeholder for demonstration
if (!isset($conn)) {
    // This is a placeholder for demonstration if config.php is not available.
    // In a real application, you would handle the database connection failure.
    $error_message = "Database connection not established. Please check 'config.php'.";
    // For demonstration, we'll create a dummy $conn object to prevent errors later.
    // In production, you would exit or redirect here.
    class MockConnection {
        public function real_escape_string($str) { return $str; }
        public function query($sql) {
            error_log("Attempted query on mock connection: " . $sql);
            // Simulate successful insert for projects if it contains 'INSERT INTO projects'
            if (strpos($sql, 'INSERT INTO projects') !== false) {
                return true;
            }
            // Simulate successful update for clients if it contains 'UPDATE clients'
            if (strpos($sql, 'UPDATE clients') !== false) {
                return true;
            }
            // Simulate clients fetching
            if (strpos($sql, 'SELECT id, name FROM clients') !== false) {
                // Return an object with fetch_assoc method and properties
                return new class {
                    public $data = [
                        ['id' => 1, 'name' => 'Acme Corp'],
                        ['id' => 2, 'name' => 'Globex Inc.']
                    ];
                    public $fetch_assoc_index = 0;
                    public function fetch_assoc() {
                        if ($this->fetch_assoc_index < count($this->data)) {
                            return $this->data[$this->fetch_assoc_index++];
                        }
                        return null;
                    }
                };
            }
            return false; // Simulate failure for other mock queries
        }
        public function fetch_assoc() { return false; }
        public function error() { return "Mock DB Error: Connection not real."; }
    }
    $conn = new MockConnection();
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($conn instanceof mysqli || ($conn instanceof MockConnection && !$error_message)) { // Proceed only if $conn is a real mysqli connection or mock without prior error
        $project_name = $conn->real_escape_string($_POST['project_name']);
        $description = $conn->real_escape_string($_POST['description']);
        $client_id = (int)$_POST['client_id'];
        // Existing fields
        $doc_link = $conn->real_escape_string($_POST['doc_link']);
        $image_link = $conn->real_escape_string($_POST['image_link']);
        $estimated_budget = floatval($_POST['estimated_budget']); // Convert to float
        $time_estimated = $conn->real_escape_string($_POST['time_estimated']);
        // New field
        $status = $conn->real_escape_string($_POST['status']);

        // Updated SQL INSERT statement to include new fields, including 'status'
        $sql = "INSERT INTO projects (project_name, description, client_id, doc_link, image_link, estimated_budget, time_estimated, status) VALUES (
            '$project_name', 
            '$description', 
            $client_id, 
            '$doc_link', 
            '$image_link', 
            $estimated_budget, 
            '$time_estimated',
            '$status'
        )";

        if ($conn->query($sql)) {
            // Increment the project count for the selected client
            // NOTE: Ensure your 'clients' table has a column named 'projects' (INT, default 0)
            $update_client_sql = "UPDATE clients SET projects = projects + 1 WHERE id = $client_id";
            if ($conn->query($update_client_sql)) {
                $message = "Project added successfully and client project count updated!";
            } else {
                $message = "Project added successfully, but client project count could not be updated: " . $conn->error;
            }
            
            // Redirect to dashboard.php after successful submission
            header('Location: manage.php'); // Assuming dashboard.php is in the parent directory
            exit(); // Important to stop script execution after redirection
        } else {
            $error_message = "Error adding project: " . $conn->error;
        }
    } else {
        $error_message = "Cannot add project: Database connection is not available.";
    }
}

// Fetch clients for dropdown
$clients = [];
if ($conn instanceof mysqli) { // Only attempt to query if it's a real mysqli connection
    $result = $conn->query("SELECT id, company_name  AS name FROM clients WHERE status = 'Active' ORDER BY name");
    // NOTE: If your 'clients' table has a 'company_name' column instead of 'name',
    // you should adjust the query to: "SELECT id, company_name as name FROM clients ORDER BY name"
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    } else {
        $error_message = "Error fetching clients: " . $conn->error;
    }
} else if (empty($error_message)) {
    $error_message = "Clients could not be loaded due to database connection issues.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project</title>
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
            display: <?php echo (!empty($message) || !empty($error_message)) ? 'block' : 'none'; ?>; /* Show only when content is present */
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


        /* Form Container */
        .form-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-top: 24px; /* Adjust as needed, was 24px originally */
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        /* Modified form-grid for two columns */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr; /* Default to single column on small screens */
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface-elevated);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
            outline: none;
            -webkit-appearance: none; /* Remove default styling for select */
            -moz-appearance: none; /* Remove default styling for select */
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px; /* Make space for the arrow */
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        /* Responsive Design */
        @media (min-width: 768px) { /* Apply two-column layout on screens wider than 768px */
            .form-grid {
                grid-template-columns: 1fr 1fr; /* Two columns */
            }
        }

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
                min-width: 800px;
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
            <a href="../expenses/manage.php">Expenses</a>
                          <a href="../assignment/manage.php">To Do</a>

            <a href="../mail/manage.php">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <!-- Changed header title to reflect page purpose -->
                <h1>Add New Project</h1>
                <div class="header-actions">
                    <!-- Adjusted buttons to be more relevant to project management -->
                    <a href="manage.php" class="btn btn-primary">Manage Projects</a>
                    <a href="#" class="btn btn-secondary">View Reports</a>
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

            <div class="form-container">
                <!-- Added a class for consistent heading style -->
                <h2 class="table-header-title">Add Project for Client</h2>
                <form method="post">
                    <div class="form-grid"> <!-- This grid now controls the two columns -->
                        <div class="form-group">
                            <label for="project_name" class="form-label">Project Name:</label>
                            <input type="text" id="project_name" name="project_name" class="form-input" required placeholder="e.g., Website Redesign">
                        </div>
                        <div class="form-group">
                            <label for="doc_link" class="form-label">Document Link (Optional):</label>
                            <input type="url" id="doc_link" name="doc_link" class="form-input" placeholder="e.g., https://docs.example.com/project_spec">
                        </div>
                        <div class="form-group">
                            <label for="description" class="form-label">Description:</label>
                            <textarea id="description" name="description" class="form-input" rows="5" required placeholder="Brief description of the project"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="image_link" class="form-label">Image Link (Optional):</label>
                            <input type="url" id="image_link" name="image_link" class="form-input" placeholder="e.g., https://example.com/project_mockup.jpg">
                        </div>
                        <div class="form-group">
                            <label for="client_id" class="form-label">Client:</label>
                            <select id="client_id" name="client_id" class="form-select" required>
                                <option value="">Select Client</option>
                                <?php if (!empty($clients)): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No clients found. Please add clients first.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="estimated_budget" class="form-label">Estimated Budget (USD):</label>
                            <input type="number" id="estimated_budget" name="estimated_budget" class="form-input" step="0.01" min="0" placeholder="e.g., 5000.00">
                        </div>
                        <div class="form-group">
                            <label for="time_estimated" class="form-label">Estimated Time:</label>
                            <input type="text" id="time_estimated" name="time_estimated" class="form-input" placeholder="e.g., 2 weeks, 80 hours">
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Project Status:</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="Planning">Planning</option>
                                <option value="Active">Active</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Add Project</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
