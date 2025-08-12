<?php
session_start();

// Protect the page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'config.php';

// Prepare and execute queries to get the counts for each card
$clients_count = 0;
$projects_count = 0;
$revenue_sum = 0;
$support_count = 0;
$team_count = 0;
$invoices_pending_count = 0;

// Get total number of clients
$sql_clients = "SELECT COUNT(*) FROM clients";
$result_clients = $conn->query($sql_clients);
if ($result_clients && $result_clients->num_rows > 0) {
    $clients_count = $result_clients->fetch_row()[0];
}

// Get total number of active projects (adjust status as per your DB)
$sql_projects = "SELECT COUNT(*) FROM projects WHERE status = 'In Progress'";
$result_projects = $conn->query($sql_projects);
if ($result_projects && $result_projects->num_rows > 0) {
    $projects_count = $result_projects->fetch_row()[0];
}

// Get monthly revenue (assuming you have an 'invoices' table with 'amount' and 'status')
/*$sql_revenue = "SELECT SUM(amount) FROM invoices WHERE status = 'Paid' AND MONTH(created_at) = MONTH(CURRENT_DATE())";
$result_revenue = $conn->query($sql_revenue);
if ($result_revenue && $result_revenue->num_rows > 0) {
    $revenue_sum = $result_revenue->fetch_row()[0];
    $revenue_sum = number_format($revenue_sum, 2);
} else {
    $revenue_sum = "0.00";
}
*/
// Get pending support tickets (assuming a 'support_tickets' table)
/*$sql_support = "SELECT COUNT(*) FROM support_tickets WHERE status != 'Resolved'";
$result_support = $conn->query($sql_support);
if ($result_support && $result_support->num_rows > 0) {
    $support_count = $result_support->fetch_row()[0];
}
*/
// Get total number of team members (assuming a 'team_members' table)
$sql_team = "SELECT COUNT(*) FROM team_members";
$result_team = $conn->query($sql_team);
if ($result_team && $result_team->num_rows > 0) {
    $team_count = $result_team->fetch_row()[0];
}
/*
// Get pending invoices
$sql_invoices = "SELECT COUNT(*) FROM invoices WHERE status = 'Pending'";
$result_invoices = $conn->query($sql_invoices);
if ($result_invoices && $result_invoices->num_rows > 0) {
    $invoices_pending_count = $result_invoices->fetch_row()[0];
}
*/
// Close the database connection
$conn->close();

// Optional: you can use these session vars for personalization
$username = $_SESSION['username'] ?? 'Unknown User';
$full_name = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Zorqent Technology - Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Your existing CSS code from the prompt goes here */
:root {
    --primary-color: #2563eb;
    --primary-dark: #1d4ed8;
    --secondary-color: #64748b;
    --background: #f8fafc;
    --surface: #ffffff;
    --surface-elevated: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --border: #e2e8f0;
    --border-light: #f1f5f9;
    --sidebar-bg: #1e293b;
    --sidebar-text: #f8fafc;
    --sidebar-text-muted: #cbd5e1;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 8px;
    --radius-lg: 12px;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
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
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--sidebar-text);
    letter-spacing: -0.025em;
}

.sidebar-nav {
    padding: 24px 0;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 12px 24px;
    color: var(--sidebar-text-muted);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.sidebar-nav a:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--sidebar-text);
    border-left-color: var(--primary-color);
}

.sidebar-nav a.active {
    background: rgba(37, 99, 235, 0.1);
    color: var(--sidebar-text);
    border-left-color: var(--primary-color);
}

.sidebar-nav a.logout {
    margin-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 24px;
    color: #fca5a5;
}

.sidebar-nav a.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #fecaca;
    border-left-color: var(--error);
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 0;
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

.header h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.025em;
}

/* Content Area */
.content {
    padding: 32px;
}

/* Cards Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

/* Card Component */
.card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
    border-color: var(--primary-color);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.card-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.card-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.card-change {
    font-size: 12px;
    font-weight: 500;
    margin-top: 8px;
    display: flex;
    align-items: center;
}

.card-change.positive {
    color: var(--success);
}

.card-change.negative {
    color: var(--error);
}

.card-change.neutral {
    color: var(--text-muted);
}

/* Card Accent Colors */
.card.clients {
    border-left: 4px solid #3b82f6;
}

.card.projects {
    border-left: 4px solid var(--success);
}

.card.revenue {
    border-left: 4px solid #8b5cf6;
}

.card.support {
    border-left: 4px solid var(--warning);
}

.card.team {
    border-left: 4px solid #06b6d4;
}

.card.invoices {
    border-left: 4px solid #f97316;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }
    
    .main-content {
        margin-left: 240px;
    }
    
    .content {
        padding: 24px;
    }
    
    .dashboard-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
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
    
    .header h1 {
        font-size: 20px;
    }
    
    .content {
        padding: 20px;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .card {
        padding: 20px;
    }
    
    .card-value {
        font-size: 28px;
    }
}

@media (max-width: 480px) {
    .header {
        padding: 16px 20px;
    }
    
    .content {
        padding: 16px;
    }
    
    .card {
        padding: 16px;
    }
}

/* Focus States for Accessibility */
.sidebar-nav a:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: -2px;
}

/* Print Styles */
@media print {
    .sidebar {
        display: none;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid var(--border);
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
            <a href="#" class="active">Dashboard</a>
            <a href="client/manage.php">Clients</a>
            <a href="project/manage.php">Projects</a>
            <a href="team/manage.php">Team</a>
            <a href="expense/manage.php">Expenses</a>
             <a href="assignment/manage.php">To Do</a>
            <a href="mail/manage.php">Mail</a>
            <a href="support/manage.php">Support</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Dashboard Overview</h1>
        </header>

        <main class="content">
            <div class="dashboard-grid">
                <div class="card clients">
                    <div class="card-header">
                        <h3 class="card-title">Total Clients</h3>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($clients_count); ?></div>
                    <div class="card-change positive">
                        ↗ +12% from last month
                    </div>
                </div>

                <div class="card projects">
                    <div class="card-header">
                        <h3 class="card-title">Active Projects</h3>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($projects_count); ?></div>
                    <div class="card-change positive">
                        ↗ +2 new projects
                    </div>
                </div>

                <div class="card revenue">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Revenue</h3>
                    </div>
                    <div class="card-value">$<?php echo htmlspecialchars($revenue_sum); ?></div>
                    <div class="card-change positive">
                        ↗ +8% from last month
                    </div>
                </div>

                <div class="card support">
                    <div class="card-header">
                        <h3 class="card-title">Support Tickets</h3>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($support_count); ?></div>
                    <div class="card-change neutral">
                        → 3 pending resolution
                    </div>
                </div>

                <div class="card team">
                    <div class="card-header">
                        <h3 class="card-title">Team Members</h3>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($team_count); ?></div>
                    <div class="card-change positive">
                        ↗ +1 new hire
                    </div>
                </div>

                <div class="card invoices">
                    <div class="card-header">
                        <h3 class="card-title">Pending Invoices</h3>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($invoices_pending_count); ?></div>
                    <div class="card-change negative">
                        ↘ 2 overdue
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<script>
function loadTelegramData() {
    fetch('chat/test.php'); // run silently
}
loadTelegramData();
setInterval(loadTelegramData, 5000);
</script>
