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
$client = null; // Initialize client data

// Check if client ID is provided in the URL
if (isset($_GET['id'])) {
    $client_id = (int)$_GET['id'];
    if ($conn instanceof mysqli) {
        // Fetch existing client data including status and initial_revenue
        $stmt = $conn->prepare("SELECT id, company_name, contact_person, email, phone, status, initial_revenue FROM clients WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $client_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $client = $result->fetch_assoc();
            } else {
                $error_message = "Client not found.";
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing client fetch statement: " . $conn->error;
        }
    } else {
        $error_message = "Database connection not available to fetch client.";
        // Mock data for demonstration if DB connection isn't real
        if ($client_id == 1) {
            $client = ['id' => 1, 'company_name' => 'Acme Corp', 'contact_person' => 'John Doe', 'email' => 'john@acme.com', 'phone' => '111-222-3333', 'address' => '123 Main St', 'status' => 'Active', 'initial_revenue' => '10000.00'];
        } else if ($client_id == 2) {
            $client = ['id' => 2, 'company_name' => 'Globex Inc.', 'contact_person' => 'Jane Smith', 'email' => 'jane@globex.com', 'phone' => '444-555-6666', 'address' => '456 Oak Ave', 'status' => 'Pending', 'initial_revenue' => '5000.00'];
        }
    }
} else {
    $error_message = "No client ID provided.";
}

// Handle form submission for updating client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client) {
    if ($conn instanceof mysqli || ($conn instanceof MockConnection && !$error_message)) {
        $client_id_post = (int)$_POST['client_id']; // Get ID from hidden field
        $company_name = $conn->real_escape_string($_POST['company_name']);
        $contact_person = $conn->real_escape_string($_POST['contact_person']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $status = $conn->real_escape_string($_POST['status']); // New: fetch status
        $initial_revenue = floatval($_POST['initial_revenue']); // New: fetch initial_revenue

        $sql = "UPDATE clients SET 
                    company_name = ?, 
                    contact_person = ?, 
                    email = ?, 
                    phone = ?, 
                    status = ?,             /* New: update status */
                    initial_revenue = ?     /* New: update initial_revenue */
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Bind new parameters for status and initial_revenue
            $stmt->bind_param("ssssssi", $company_name, $contact_person, $email, $phone, $status, $initial_revenue, $client_id_post); // 'd' for double (float)
            if ($stmt->execute()) {
                $message = "Client updated successfully!";
                // Redirect back to client management page after successful update
                header('Location: manage.php');
                exit();
            } else {
                $error_message = "Error updating client: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing update statement: " . $conn->error;
        }
    } else {
        $error_message = "Cannot update client: Database connection is not available.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client</title>
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

        /* Form Container */
        .form-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-top: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
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
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
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
                <h1>Edit Client</h1>
                <div class="header-actions">
                    <a href="manage.php" class="btn btn-primary">Manage Clients</a>
                    <a href="#" class="btn btn-secondary">View Client History</a>
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

            <?php if ($client): ?>
                <div class="form-container">
                    <h2 class="table-header-title">Edit Client Details (ID: <?= htmlspecialchars($client['id']) ?>)</h2>
                    <form method="post">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['id']) ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="company_name" class="form-label">Company Name:</label>
                                <input type="text" id="company_name" name="company_name" class="form-input" required value="<?= htmlspecialchars($client['company_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="contact_person" class="form-label">Contact Person:</label>
                                <input type="text" id="contact_person" name="contact_person" class="form-input" value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" id="email" name="email" class="form-input" required value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone:</label>
                                <input type="tel" id="phone" name="phone" class="form-input" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                            </div>
                             <div class="form-group">
                                <label for="status" class="form-label">Status:</label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="Active" <?= (isset($client['status']) && $client['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                    <option value="Pending" <?= (isset($client['status']) && $client['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="Inactive" <?= (isset($client['status']) && $client['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                             <div class="form-group">
                                <label for="initial_revenue" class="form-label">Initial Revenue:</label>
                                <input type="number" id="initial_revenue" name="initial_revenue" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($client['initial_revenue'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Update Client</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-info" style="display: block;">
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <div class="form-container">
                    <h2 class="table-header-title">No Client Selected</h2>
                    <p class="text-muted" style="color: var(--text-muted);">Please select a client from the <a href="manage.php" style="color: var(--primary-color); text-decoration: underline;">Client Management</a> page to edit.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
