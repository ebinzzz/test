<?php
// --- Configuration and Initialization ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// This assumes your config.php contains a working MySQLi connection
@include_once '../config.php';

// A simple function to get a database connection
function getDatabaseConnection() {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        return $GLOBALS['conn'];
    }
    // Handle case where connection is not set
    die("Database connection failed.");
}

$conn = getDatabaseConnection();
$expense = null;
$message = '';
$error_message = '';

// Check if an expense ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = "Invalid expense ID provided.";
    header('Location: manage.php');
    exit();
}

$expense_id = (int)$_GET['id'];

// --- Handle Form Submission for Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $expense_name = htmlspecialchars(trim($_POST['expense_name']));
    $amount = (float)$_POST['amount'];
    $client_id = (int)$_POST['client_id'];
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $category = htmlspecialchars(trim($_POST['category']));
    $date_incurred = htmlspecialchars(trim($_POST['date_incurred']));

    // Simple validation
    if (empty($expense_name) || $amount <= 0 || empty($date_incurred) || empty($client_id)) {
        $error_message = "Please fill in all required fields correctly.";
    } else {
        // Use a prepared statement to prevent SQL injection
        $sql = "UPDATE expenses SET expense_name = ?, amount = ?, client_id = ?, project_id = ?, category = ?, date_incurred = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sdiissi", $expense_name, $amount, $client_id, $project_id, $category, $date_incurred, $expense_id);

            if ($stmt->execute()) {
                $message = "Expense updated successfully!";
                // Redirect back to the dashboard after a successful update
                header('Location: manage.php');
                exit();
            } else {
                $error_message = "Error updating expense: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Failed to prepare the SQL statement: " . $conn->error;
        }
    }
}

// --- Fetch Current Expense Data to Pre-fill the Form ---
if (empty($error_message)) {
    $sql = "SELECT * FROM expenses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $expense_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $expense = $result->fetch_assoc();
        $stmt->close();
        
        if (!$expense) {
            $error_message = "Expense not found.";
            header('Location: manage.php');
            exit();
        }
    } else {
        $error_message = "Failed to fetch expense data: " . $conn->error;
    }
}

// --- Fetch Clients for Dropdowns ---
$clients = [];
$sql_clients = "SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name";
$result_clients = $conn->query($sql_clients);
if ($result_clients) {
    while ($row = $result_clients->fetch_assoc()) {
        $clients[] = $row;
    }
}

// --- Fetch Projects for the pre-selected client
$projects = [];
$initial_client_id = $expense['client_id'] ?? null;
if ($initial_client_id) {
    $sql_projects = "SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name";
    $stmt = $conn->prepare($sql_projects);
    if ($stmt) {
        $stmt->bind_param("i", $initial_client_id);
        $stmt->execute();
        $result_projects = $stmt->get_result();
        while ($row = $result_projects->fetch_assoc()) {
            $projects[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense</title>
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
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.025em;
        }

        .content {
            padding: 32px;
        }
        
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card-header {
            background: var(--surface-elevated);
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            background: var(--background);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-control,
#category {
    width: 100%;
    padding: 12px;
    font-size: 14px;
    border-radius: var(--radius);
    border: 1px solid var(--border-light);
    background: var(--background);
    color: var(--text-primary);
    transition: all 0.2s ease;
}

#category:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
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
        
        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            font-weight: 500;
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
        
        @media (max-width: 1024px) {
            .sidebar { width: 240px; }
            .main-content { margin-left: 240px; }
            .header { padding: 20px 24px; }
            .content { padding: 20px; }
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
            .header-content { flex-direction: column; align-items: flex-start; gap: 10px; }
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
            <div class="header-content">
                <h1>Edit Expense</h1>
            </div>
        </header>

        <main class="content">
            <div class="card">
                <div class="card-header">
                    <h2>Expense Details</h2>
                </div>
                <div class="card-body">
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
                    
                    <?php if ($expense): ?>
                    <form action="edit.php?id=<?= htmlspecialchars($expense_id) ?>" method="POST">
                        <div class="form-group">
                            <label for="expense_name">Expense Name</label>
                            <input type="text" class="form-control" id="expense_name" name="expense_name" value="<?= htmlspecialchars($expense['expense_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= htmlspecialchars($expense['amount']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="client_id">Client</label>
                            <select class="form-control" id="client_id" name="client_id" required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= htmlspecialchars($client['id']) ?>" <?= ($client['id'] == $expense['client_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="project_id">Project</label>
                            <select class="form-control" id="project_id" name="project_id">
                                <option value="">Select Project (Optional)</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= htmlspecialchars($project['id']) ?>" <?= ($project['id'] == $expense['project_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value=""><?= htmlspecialchars($expense['category']) ?></option>
                                <option value="Software" <?= (isset($_POST['category']) && $_POST['category'] == 'Software') ? 'selected' : '' ?>>Software</option>
                                <option value="Hardware" <?= (isset($_POST['category']) && $_POST['category'] == 'Hardware') ? 'selected' : '' ?>>Hardware</option>
                                <option value="Marketing" <?= (isset($_POST['category']) && $_POST['category'] == 'Marketing') ? 'selected' : '' ?>>Marketing</option>
                                <option value="Travel" <?= (isset($_POST['category']) && $_POST['category'] == 'Travel') ? 'selected' : '' ?>>Travel</option>
                                <option value="Office" <?= (isset($_POST['category']) && $_POST['category'] == 'Office') ? 'selected' : '' ?>>Office Supplies</option>
                                <option value="Other" <?= (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_incurred">Date Incurred</label>
                            <input type="date" class="form-control" id="date_incurred" name="date_incurred" value="<?= htmlspecialchars($expense['date_incurred']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Expense</button>
                        <a href="manage.php" class="btn btn-secondary">Cancel</a>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    document.getElementById('client_id').addEventListener('change', function() {
        var clientId = this.value;
        var projectDropdown = document.getElementById('project_id');
        
        // Clear previous options
        projectDropdown.innerHTML = '<option value="">Select Project (Optional)</option>';
        
        if (clientId) {
            // Make an AJAX call to fetch projects
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_projects.php?client_id=' + clientId, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var projects = JSON.parse(xhr.responseText);
                    projects.forEach(function(project) {
                        var option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.project_name;
                        projectDropdown.appendChild(option);
                    });
                } else {
                    console.error('An error occurred fetching projects.');
                }
            };
            xhr.send();
        }
    });
    </script>
</body>
</html>