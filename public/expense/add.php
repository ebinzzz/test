<?php
// --- Configuration and Initialization ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


@include_once '../config.php';

// Mock connection logic (as used in manage.php)
function getDatabaseConnection() {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        return $GLOBALS['conn'];
    }

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
    }
    
    class MockConnection {
        public function prepare($sql) {
            return new class {
                public function bind_param($types, ...$vars) {}
                public function execute() { return true; }
                public function close() {}
                public function error() { return 'Mock error'; }
            };
        }
        public function query($sql) {
            if (strpos($sql, 'SELECT id, company_name') !== false) {
                return new MockResult([['id' => 1, 'company_name' => 'Client A'], ['id' => 2, 'company_name' => 'Client B']]);
            }
            if (strpos($sql, 'SELECT id, project_name') !== false) {
                 return new MockResult([['id' => 1, 'project_name' => 'Project X'], ['id' => 2, 'project_name' => 'Project Y']]);
            }
            return new MockResult([]);
        }
    }
    return new MockConnection();
}

$conn = getDatabaseConnection();

// --- Handle AJAX Request for Projects ---
if (isset($_GET['client_id'])) {
    header('Content-Type: application/json');
    $client_id = (int)$_GET['client_id'];
    $projects = [];

    if ($conn instanceof mysqli) {
        $sql = "SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name";
        $stmt = $conn->prepare($sql);
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
        // Mock data for AJAX request
        if ($client_id == 1) {
            $projects = [['id' => 1, 'project_name' => 'Project Alpha'], ['id' => 2, 'project_name' => 'Project Beta']];
        } elseif ($client_id == 2) {
            $projects = [['id' => 3, 'project_name' => 'Project Gamma']];
        }
    }

    echo json_encode($projects);
    exit;
}

// --- Regular Page Load Logic ---
$clients = [];
$clients_result = $conn->query("SELECT id, company_name FROM clients WHERE status = 'Active' ORDER BY company_name");
if ($clients_result) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}

$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $expense_name = $_POST['expense_name'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $client_id = $_POST['client_id'] ?? null;
    $project_id = $_POST['project_id'] ?? null;
    $category = $_POST['category'] ?? '';
    $date_incurred = $_POST['date_incurred'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($expense_name) || empty($amount) || empty($date_incurred)) {
        $error_message = "Please fill in all required fields (Expense Name, Amount, Date).";
    } else {
        if ($conn instanceof mysqli) {
            $sql = "INSERT INTO expenses (expense_name, amount, client_id, project_id, category, date_incurred, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sddisss", $expense_name, $amount, $client_id, $project_id, $category, $date_incurred, $description);
                if ($stmt->execute()) {
                    $message = "New expense added successfully!";
                    $_POST = [];
                } else {
                    $error_message = "Error adding expense: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else {
            $message = "New expense added successfully (mock mode).";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Expense</title>
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }
        .content { padding: 32px; }
        .form-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            max-width: 800px;
            margin: 0 auto;
        }
        .form-card h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 16px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
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
        .btn-secondary {
            background: var(--surface-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border);
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
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            .header { padding: 20px 24px; }
            .content { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
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
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Add New Expense</h1>
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
            <div class="form-card">
                <h2>Expense Details</h2>
                <form action="add.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="expense_name">Expense Name <span style="color: red;">*</span></label>
                            <input type="text" id="expense_name" name="expense_name" required value="<?= htmlspecialchars($_POST['expense_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount <span style="color: red;">*</span></label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_id">Client</label>
                            <select id="client_id" name="client_id">
                                <option value="">-- Select Client --</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= htmlspecialchars($client['id']) ?>" <?= (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="project_id">Project</label>
                            <select id="project_id" name="project_id" disabled>
                                <option value="">-- Select Project --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">-- Select Category --</option>
                                <option value="Software" <?= (isset($_POST['category']) && $_POST['category'] == 'Software') ? 'selected' : '' ?>>Software</option>
                                <option value="Hardware" <?= (isset($_POST['category']) && $_POST['category'] == 'Hardware') ? 'selected' : '' ?>>Hardware</option>
                                <option value="Marketing" <?= (isset($_POST['category']) && $_POST['category'] == 'Marketing') ? 'selected' : '' ?>>Marketing</option>
                                <option value="Travel" <?= (isset($_POST['category']) && $_POST['category'] == 'Travel') ? 'selected' : '' ?>>Travel</option>
                                <option value="Office" <?= (isset($_POST['category']) && $_POST['category'] == 'Office') ? 'selected' : '' ?>>Office Supplies</option>
                                <option value="Other" <?= (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_incurred">Date Incurred <span style="color: red;">*</span></label>
                            <input type="date" id="date_incurred" name="date_incurred" required value="<?= htmlspecialchars($_POST['date_incurred'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="manage.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clientSelect = document.getElementById('client_id');
            const projectSelect = document.getElementById('project_id');

            // Initial state: disable project select if no client is chosen
            if (!clientSelect.value) {
                projectSelect.disabled = true;
            }

            // Event listener for client selection change
            clientSelect.addEventListener('change', function() {
                const clientId = this.value;

                // Clear previous projects
                projectSelect.innerHTML = '<option value="">-- Select Project --</option>';

                if (clientId) {
                    // Enable the project dropdown and show a loading state
                    projectSelect.disabled = false;
                    projectSelect.innerHTML = '<option value="">Loading projects...</option>';

                    // Fetch projects via AJAX
                    fetch(`add.php?client_id=${clientId}`)
                        .then(response => response.json())
                        .then(projects => {
                            // Clear loading state
                            projectSelect.innerHTML = '<option value="">-- Select Project --</option>';
                            if (projects.length > 0) {
                                projects.forEach(project => {
                                    const option = document.createElement('option');
                                    option.value = project.id;
                                    option.textContent = project.project_name;
                                    projectSelect.appendChild(option);
                                });
                            } else {
                                projectSelect.innerHTML = '<option value="">No projects found for this client</option>';
                                projectSelect.disabled = true;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching projects:', error);
                            projectSelect.innerHTML = '<option value="">Error loading projects</option>';
                            projectSelect.disabled = true;
                        });
                } else {
                    // If no client is selected, disable the project dropdown
                    projectSelect.disabled = true;
                }
            });
        });
    </script>
</body>
</html>