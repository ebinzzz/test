<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

@include_once '../config.php';

// The getDatabaseConnection() function would be included from a common file
function getDatabaseConnection() {
    // ... (Your existing getDatabaseConnection function) ...
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
        public function query($sql) {
            if (strpos($sql, 'SELECT id, company_name FROM clients') !== false) {
                return new MockResult([
                    ['id' => 1, 'company_name' => 'Client A Inc.'],
                    ['id' => 2, 'company_name' => 'Client B Solutions'],
                ]);
            }
            if (strpos($sql, 'SELECT id, full_name, email FROM team_members') !== false) {
                return new MockResult([
                    ['id' => 101, 'full_name' => 'Alice Wonderland', 'email' => 'alice@example.com'],
                    ['id' => 102, 'full_name' => 'Bob The Builder', 'email' => 'bob@example.com'],
                ]);
            }
            if (strpos($sql, 'SELECT id, project_name FROM projects WHERE client_id = 1') !== false) {
                return new MockResult([
                    ['id' => 201, 'project_name' => 'Project Alpha'],
                    ['id' => 202, 'project_name' => 'Project Beta'],
                ]);
            }
            if (strpos($sql, 'SELECT id, project_name FROM projects WHERE client_id = 2') !== false) {
                return new MockResult([
                    ['id' => 203, 'project_name' => 'Project Gamma'],
                ]);
            }
            return new MockResult([]);
        }
        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                public function __construct($sql) {
                    $this->sql = $sql;
                }
                public function bind_param($types, ...$vars) {}
                public function execute() { return true; }
                public function get_result() {
                    if (strpos($this->sql, 'SELECT email FROM clients WHERE id = ?') !== false) {
                        return new MockResult([['email' => 'client.a@example.com']]);
                    }
                    if (strpos($this->sql, 'SELECT email FROM team_members WHERE id = ?') !== false) {
                        return new MockResult([['email' => 'alice@example.com']]);
                    }
                    return new MockResult([]);
                }
                public function close() {}
            };
        }
        public function close() {}
    }
    return new MockConnection();
}

$conn = getDatabaseConnection();

$clients = [];
$clients_result = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name");
if ($clients_result) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Client Mail</title>
    <style>
        /* CSS from your original file here. It's a good idea to put this in a separate file, like style.css */
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

        .content {
            padding: 32px;
        }
        
        .mail-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .mail-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .mail-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .mail-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mail-form label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .mail-form select, .mail-form input[type="text"], .mail-form input[type="email"], .mail-form textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            background: var(--surface-elevated);
            color: var(--text-primary);
            resize: vertical;
        }
        .mail-form textarea { min-height: 120px; }

        .mail-form select:focus, .mail-form input:focus, .mail-form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
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
            <a href="../expenses/manage.php">Expenses</a>
            <a href="manage.php" class="active">Mail</a>
            <a href="../support/manage.php">Support</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-content">
                <h1>Send Client Mail</h1>
            </div>
        </header>

        <main class="content">
            <div class="mail-card">
                <h3>Send Client Mail</h3>
                <form action="send_mail.php" method="POST" class="mail-form">
                    <input type="hidden" name="email_type" value="client">
                    <label for="client_id_select">Select Client:</label>
                    <select name="recipient_id" id="client_id_select" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['id']) ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="project_id_select">Select Project:</label>
                    <select name="project_id" id="project_id_select" disabled required>
                        <option value="">-- Select a Client First --</option>
                    </select>
                    <label for="client_subject">Subject:</label>
                    <input type="text" name="subject" id="client_subject" value="Project Update from Zorqent" required>
                    <label for="client_content">Content:</label>
                    <textarea name="content" id="client_content" required placeholder="Enter your email content here..."></textarea>
                    <button type="submit" class="btn btn-primary">Send Email</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        document.getElementById('client_id_select').addEventListener('change', function() {
            var clientId = this.value;
            var projectSelect = document.getElementById('project_id_select');
            
            projectSelect.innerHTML = '<option value="">-- Loading Projects --</option>';
            projectSelect.disabled = true;

            if (clientId) {
                // Using a separate PHP script to fetch projects via AJAX
                fetch('get_projects.php?client_id=' + clientId)
                    .then(response => response.json())
                    .then(projects => {
                        let options = '<option value="">-- Select Project --</option>';
                        projects.forEach(project => {
                            options += `<option value="${project.id}">${project.project_name}</option>`;
                        });
                        projectSelect.innerHTML = options;
                        projectSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error fetching projects:', error);
                        projectSelect.innerHTML = '<option value="">-- No Projects Found --</option>';
                        projectSelect.disabled = true;
                    });
            } else {
                projectSelect.innerHTML = '<option value="">-- Select a Client First --</option>';
                projectSelect.disabled = true;
            }
        });
    </script>
</body>
</html>