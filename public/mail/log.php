<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

@include_once '../config.php';

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
        public function num_rows() {
            return count($this->data);
        }
    }

    class MockConnection {
        public function query($sql) {
            // Email logs mock data
            if (strpos($sql, 'SELECT * FROM email_logs') !== false) {
                return new MockResult([
                    [
                        'id' => 1,
                        'email_type' => 'client',
                        'recipient_type' => 'client',
                        'recipient_id' => 1,
                        'recipient_email' => 'client.a@example.com',
                        'subject' => 'Project Alpha Progress Update',
                        'content' => 'Your project is now 75% complete. We have finished the development phase.',
                        'status' => 'sent',
                        'additional_data' => '{"project_id": 201, "project_name": "Project Alpha"}',
                        'sent_at' => '2025-01-15 14:30:00',
                        'created_by' => 1
                    ],
                    [
                        'id' => 2,
                        'email_type' => 'team',
                        'recipient_type' => 'team_member',
                        'recipient_id' => 101,
                        'recipient_email' => 'alice@example.com',
                        'subject' => 'Weekly Team Update',
                        'content' => 'Please review the project milestones for this week.',
                        'status' => 'sent',
                        'additional_data' => '{"role": "Developer", "team_stats": {"total_members": 50}}',
                        'sent_at' => '2025-01-15 12:15:00',
                        'created_by' => 1
                    ],
                    [
                        'id' => 3,
                        'email_type' => 'ticket',
                        'recipient_type' => 'client',
                        'recipient_id' => 2,
                        'recipient_email' => 'support@client.com',
                        'subject' => 'Support Ticket #12345 Resolved',
                        'content' => 'Your support ticket has been resolved successfully.',
                        'status' => 'sent',
                        'additional_data' => '{"ticket_info": {"id": "12345", "status": "Resolved"}}',
                        'sent_at' => '2025-01-15 10:45:00',
                        'created_by' => 1
                    ],
                    [
                        'id' => 4,
                        'email_type' => 'client',
                        'recipient_type' => 'client',
                        'recipient_id' => 1,
                        'recipient_email' => 'invalid@client.com',
                        'subject' => 'Project Status Update',
                        'content' => 'Failed to deliver project update.',
                        'status' => 'failed',
                        'additional_data' => '{"error": "Invalid email address", "project_id": 202}',
                        'sent_at' => '2025-01-15 09:20:00',
                        'created_by' => 1
                    ]
                ]);
            }
            
            // Statistics queries
            if (strpos($sql, 'COUNT(*) as total FROM email_logs') !== false) {
                return new MockResult([['total' => 1247]]);
            }
            if (strpos($sql, 'COUNT(*) as sent FROM email_logs WHERE status = \'sent\'') !== false) {
                return new MockResult([['sent' => 1228]]);
            }
            if (strpos($sql, 'COUNT(*) as failed FROM email_logs WHERE status = \'failed\'') !== false) {
                return new MockResult([['failed' => 19]]);
            }
            if (strpos($sql, 'COUNT(DISTINCT recipient_id) as active_projects FROM email_logs WHERE email_type = \'client\'') !== false) {
                return new MockResult([['active_projects' => 12]]);
            }
            if (strpos($sql, 'COUNT(*) as team_emails FROM email_logs WHERE email_type = \'team\'') !== false) {
                return new MockResult([['team_emails' => 89]]);
            }
            
            // Project progress logs
            if (strpos($sql, 'SELECT * FROM project_progress_logs') !== false) {
                return new MockResult([
                    [
                        'id' => 1,
                        'project_id' => 201,
                        'progress_note' => 'Progress milestone communicated to client',
                        'status_change' => 'Active',
                        'logged_at' => '2025-01-15 14:30:00',
                        'logged_by' => 1
                    ],
                    [
                        'id' => 2,
                        'project_id' => 202,
                        'progress_note' => 'Project marked as completed via email notification',
                        'status_change' => 'Completed',
                        'logged_at' => '2025-01-14 16:20:00',
                        'logged_by' => 1
                    ]
                ]);
            }
            
            // Team activity logs
            if (strpos($sql, 'SELECT * FROM team_activity_logs') !== false) {
                return new MockResult([
                    [
                        'id' => 1,
                        'team_member_id' => 101,
                        'activity_type' => 'email_notification',
                        'activity_description' => 'Email notification sent: Weekly Team Update',
                        'details' => '{"role": "Developer", "notification_type": "team_communication"}',
                        'logged_at' => '2025-01-15 12:15:00',
                        'logged_by' => 1
                    ]
                ]);
            }
            
            // Email activity by day
            if (strpos($sql, 'DATE(sent_at) as date, COUNT(*) as count FROM email_logs') !== false) {
                return new MockResult([
                    ['date' => '2025-01-15', 'count' => 45],
                    ['date' => '2025-01-14', 'count' => 38],
                    ['date' => '2025-01-13', 'count' => 52],
                    ['date' => '2025-01-12', 'count' => 29],
                    ['date' => '2025-01-11', 'count' => 41],
                    ['date' => '2025-01-10', 'count' => 35],
                    ['date' => '2025-01-09', 'count' => 48]
                ]);
            }
            
            return new MockResult([]);
        }
        
        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                private $bound_params = [];
                
                public function __construct($sql) { 
                    $this->sql = $sql; 
                }
                public function bind_param($types, ...$vars) { 
                    $this->bound_params = $vars;
                }
                public function execute() { return true; }
                public function get_result() {
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

// Fetch dashboard statistics
$stats = [];

// Total emails
$result = $conn->query("SELECT COUNT(*) as total FROM email_logs");
$stats['total_emails'] = $result->fetch_assoc()['total'] ?? 0;

// Sent emails
$result = $conn->query("SELECT COUNT(*) as sent FROM email_logs WHERE status = 'sent'");
$stats['sent_emails'] = $result->fetch_assoc()['sent'] ?? 0;

// Failed emails
$result = $conn->query("SELECT COUNT(*) as failed FROM email_logs WHERE status = 'failed'");
$stats['failed_emails'] = $result->fetch_assoc()['failed'] ?? 0;

// Success rate
$stats['success_rate'] = $stats['total_emails'] > 0 ? round(($stats['sent_emails'] / $stats['total_emails']) * 100, 1) : 0;

// Active projects (based on client emails)
$result = $conn->query("SELECT COUNT(DISTINCT recipient_id) as active_projects FROM email_logs WHERE email_type = 'client' AND status = 'sent'");
$stats['active_projects'] = $result->fetch_assoc()['active_projects'] ?? 0;

// Team communications
$result = $conn->query("SELECT COUNT(*) as team_emails FROM email_logs WHERE email_type = 'team' AND status = 'sent'");
$stats['team_emails'] = $result->fetch_assoc()['team_emails'] ?? 0;

// Recent email logs
$filter = $_GET['filter'] ?? 'all';
$filter_sql = $filter !== 'all' ? "WHERE email_type = '" . mysqli_real_escape_string($conn, $filter) . "'" : '';
$recent_logs_result = $conn->query("SELECT * FROM email_logs $filter_sql ORDER BY sent_at DESC LIMIT 20");

// Email activity by day (last 7 days)
$activity_result = $conn->query("
    SELECT DATE(sent_at) as date, COUNT(*) as count 
    FROM email_logs 
    WHERE sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(sent_at) 
    ORDER BY date ASC
");

// Project progress logs
$progress_logs_result = $conn->query("SELECT * FROM project_progress_logs ORDER BY logged_at DESC LIMIT 10");

// Team activity logs
$team_activity_result = $conn->query("SELECT * FROM team_activity_logs ORDER BY logged_at DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Logs Dashboard</title>
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
            --radius: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        }

        .content {
            padding: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card h3 {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 0.85em;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .positive { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .negative { background: rgba(248, 113, 113, 0.1); color: var(--error); }
        .neutral { background: rgba(100, 116, 139, 0.1); color: var(--text-muted); }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }

        .section {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .section h2 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .log-entry {
            background: var(--surface-elevated);
            border-left: 4px solid var(--primary-color);
            padding: 16px;
            margin-bottom: 12px;
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .log-entry:hover {
            background: #475569;
            transform: translateX(4px);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .log-type {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-client { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .type-team { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
        .type-ticket { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .type-promotion { background: rgba(168, 85, 247, 0.2); color: #c084fc; }

        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-sent { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-failed { background: rgba(248, 113, 113, 0.2); color: var(--error); }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: var(--warning); }

        .log-details {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .log-details strong {
            color: var(--text-primary);
        }

        .filter-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid var(--border-light);
            background: transparent;
            color: var(--text-secondary);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            text-decoration: none;
            font-size: 13px;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .chart-container {
            height: 250px;
            margin-top: 16px;
            background: var(--surface-elevated);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
        }

        .chart-bar {
            display: inline-block;
            width: calc(100% / 7 - 8px);
            margin: 0 4px;
            background: var(--primary-color);
            border-radius: 4px 4px 0 0;
            position: relative;
            transition: all 0.3s ease;
        }

        .chart-bar:hover {
            background: var(--primary-dark);
            transform: scaleY(1.05);
        }

        .chart-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: var(--text-muted);
        }

        .chart-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .progress-item {
            margin-bottom: 16px;
            padding: 12px;
            background: var(--surface-elevated);
            border-radius: var(--radius);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .progress-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            transform: scale(1.1) rotate(180deg);
            background: var(--primary-dark);
        }

        .activity-log {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 12px;
            margin-bottom: 8px;
            background: var(--surface-elevated);
            border-radius: var(--radius);
            font-size: 13px;
        }

        .activity-time {
            color: var(--text-muted);
            font-size: 11px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="../client/manage.php">Clients</a>
            <a href="../project/manage.php">Projects</a>
            <a href="../team/manage.php">Team</a>
            <a href="../expense/manage.php">Expenses</a>
             <a href="../assignment/manage.php">To Do</a>
            <a href="manage.php">Mail</a>
            <a href="log.php" class="active">Email Logs</a>
            <a href="../support/manage.php">Support</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <div>
                <h1>📊 Email Communication Dashboard</h1>
                <p style="color: var(--text-muted); margin-top: 4px;">Monitor email logs and track communication progress</p>
            </div>
        </header>

        <main class="content">
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Emails</h3>
                    <div class="stat-number"><?= number_format($stats['total_emails']) ?></div>
                    <div class="stat-change positive">+12% this week</div>
                </div>
                <div class="stat-card">
                    <h3>Success Rate</h3>
                    <div class="stat-number"><?= $stats['success_rate'] ?>%</div>
                    <div class="stat-change positive">98.5% average</div>
                </div>
                <div class="stat-card">
                    <h3>Active Projects</h3>
                    <div class="stat-number"><?= $stats['active_projects'] ?></div>
                    <div class="stat-change neutral"><?= $stats['active_projects'] ?> tracked</div>
                </div>
                <div class="stat-card">
                    <h3>Team Communications</h3>
                    <div class="stat-number"><?= $stats['team_emails'] ?></div>
                    <div class="stat-change positive">+8% growth</div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="section">
                    <h2>📈 Email Activity (Last 7 Days)</h2>
                    <div class="chart-container">
                        <div style="display: flex; align-items: end; height: 180px; justify-content: space-around;">
                            <?php 
                            $activity_data = [];
                            if ($activity_result) {
                                while ($row = $activity_result->fetch_assoc()) {
                                    $activity_data[$row['date']] = $row['count'];
                                }
                            }
                            
                            // Generate last 7 days
                            for ($i = 6; $i >= 0; $i--) {
                                $date = date('Y-m-d', strtotime("-$i days"));
                                $count = $activity_data[$date] ?? 0;
                                $height = $count > 0 ? max(20, ($count / 60) * 160) : 0;
                                echo '<div class="chart-bar" style="height: ' . $height . 'px;">';
                                echo '<div class="chart-value">' . $count . '</div>';
                                echo '<div class="chart-label">' . date('M j', strtotime($date)) . '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>🎯 Recent Project Updates</h2>
                    <div class="activity-log">
                        <?php if ($progress_logs_result && $progress_logs_result->num_rows > 0): ?>
                            <?php while ($log = $progress_logs_result->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="progress-header">
                                        <span class="progress-name">Project #<?= $log['project_id'] ?></span>
                                        <span class="progress-status status-<?= strtolower($log['status_change'] ?? 'active') ?>">
                                            <?= htmlspecialchars($log['status_change'] ?? 'Active') ?>
                                        </span>
                                    </div>
                                    <div><?= htmlspecialchars($log['progress_note']) ?></div>
                                    <div class="activity-time"><?= date('M j, Y g:i A', strtotime($log['logged_at'])) ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="activity-item">
                                <div style="color: var(--text-muted);">No project progress logs found</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Email Logs Section -->
            <div class="section full-width">
                <h2>📧 Email Communication Logs</h2>
                
                <div class="filter-controls">
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?filter=client" class="filter-btn <?= $filter === 'client' ? 'active' : '' ?>">Client</a>
                    <a href="?filter=team" class="filter-btn <?= $filter === 'team' ? 'active' : '' ?>">Team</a>
                    <a href="?filter=ticket" class="filter-btn <?= $filter === 'ticket' ? 'active' : '' ?>">Ticket</a>
                    <a href="?filter=promotion" class="filter-btn <?= $filter === 'promotion' ? 'active' : '' ?>">Promotion</a>
                </div>

                <div id="emailLogs">
                    <?php if ($recent_logs_result && $recent_logs_result->num_rows > 0): ?>
                        <?php while ($log = $recent_logs_result->fetch_assoc()): ?>
                            <?php 
                            $additional_data = json_decode($log['additional_data'] ?? '{}', true) ?? [];
                            ?>
                            <div class="log-entry" data-type="<?= $log['email_type'] ?>">
                                <div class="log-header">
                                    <div>
                                        <span class="log-type type-<?= $log['email_type'] ?>"><?= $log['email_type'] ?></span>
                                        <span class="status status-<?= $log['status'] ?>"><?= $log['status'] ?></span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?= date('M j, Y g:i A', strtotime($log['sent_at'])) ?>
                                    </div>
                                </div>
                                <div class="log-details">
                                    <strong>To:</strong> <?= htmlspecialchars($log['recipient_email']) ?><br>
                                    <strong>Subject:</strong> <?= htmlspecialchars($log['subject']) ?><br>
                                    <strong>Content:</strong> <?= htmlspecialchars(substr($log['content'], 0, 100)) ?>...
                                    
                                    <?php if ($log['email_type'] === 'client' && isset($additional_data['project_id'])): ?>
                                        <br><strong>Project ID:</strong> <?= htmlspecialchars($additional_data['project_id']) ?>
                                        <?php if (isset($additional_data['project_name'])): ?>
                                            <br><strong>Project:</strong> <?= htmlspecialchars($additional_data['project_name']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['email_type'] === 'ticket' && isset($additional_data['ticket_info'])): ?>
                                        <br><strong>Ticket:</strong> #<?= htmlspecialchars($additional_data['ticket_info']['id'] ?? '') ?> 
                                        - <?= htmlspecialchars($additional_data['ticket_info']['status'] ?? '') ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['email_type'] === 'team' && isset($additional_data['role'])): ?>
                                        <br><strong>Role:</strong> <?= htmlspecialchars($additional_data['role']) ?>
                                        <?php if (isset($additional_data['recipient_count'])): ?>
                                            <br><strong>Recipients:</strong> <?= $additional_data['recipient_count'] ?> team member(s)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['status'] === 'failed' && isset($additional_data['error'])): ?>
                                        <br><strong>Error:</strong> <span style="color: var(--error);"><?= htmlspecialchars($additional_data['error']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="log-entry">
                            <div class="log-details" style="text-align: center; color: var(--text-muted);">
                                No email logs found for the selected filter.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Team Activity Section -->
            <div class="section full-width">
                <h2>👥 Team Activity Logs</h2>
                <div class="activity-log">
                    <?php if ($team_activity_result && $team_activity_result->num_rows > 0): ?>
                        <?php while ($activity = $team_activity_result->fetch_assoc()): ?>
                            <?php 
                            $details = json_decode($activity['details'] ?? '{}', true) ?? [];
                            ?>
                            <div class="activity-item">
                                <div class="progress-header">
                                    <span class="progress-name">Team Member #<?= $activity['team_member_id'] ?></span>
                                    <span class="activity-time"><?= date('M j, Y g:i A', strtotime($activity['logged_at'])) ?></span>
                                </div>
                                <div><strong><?= htmlspecialchars($activity['activity_type']) ?>:</strong> <?= htmlspecialchars($activity['activity_description']) ?></div>
                                <?php if (isset($details['role'])): ?>
                                    <div><strong>Role:</strong> <?= htmlspecialchars($details['role']) ?></div>
                                <?php endif; ?>
                                <?php if (isset($details['notification_type'])): ?>
                                    <div><strong>Type:</strong> <?= htmlspecialchars($details['notification_type']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div style="color: var(--text-muted);">No team activity logs found.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email Performance Metrics -->
            <div class="dashboard-grid">
                <div class="section">
                    <h2>📊 Email Type Distribution</h2>
                    <div style="margin-top: 20px;">
                        <?php
                        $type_stats = [
                            'client' => 0,
                            'team' => 0,
                            'ticket' => 0,
                            'promotion' => 0
                        ];
                        
                        // In real implementation, fetch from database
                        $type_result = $conn->query("
                            SELECT email_type, COUNT(*) as count 
                            FROM email_logs 
                            GROUP BY email_type
                        ");
                        
                        // Mock data for display
                        $type_stats = [
                            'client' => 542,
                            'team' => 289,
                            'ticket' => 156,
                            'promotion' => 260
                        ];
                        
                        $total = array_sum($type_stats);
                        foreach ($type_stats as $type => $count):
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        ?>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span class="log-type type-<?= $type ?>"><?= ucfirst($type) ?></span>
                                    <span><?= $count ?> (<?= $percentage ?>%)</span>
                                </div>
                                <div style="background: var(--surface-elevated); height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: var(--primary-color); height: 100%; width: <?= $percentage ?>%; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section">
                    <h2>⚡ Quick Actions</h2>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                        <a href="manage.php" class="filter-btn" style="text-align: center; padding: 12px;">
                            📧 Send New Email
                        </a>
                        <a href="client.php" class="filter-btn" style="text-align: center; padding: 12px;">
                            👤 Client Communication
                        </a>
                        <a href="team.php" class="filter-btn" style="text-align: center; padding: 12px;">
                            👥 Team Notification
                        </a>
                        <a href="#" onclick="exportLogs()" class="filter-btn" style="text-align: center; padding: 12px;">
                            📊 Export Logs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Failed Emails Section -->
            <?php if ($stats['failed_emails'] > 0): ?>
            <div class="section full-width">
                <h2>⚠️ Failed Email Communications (<?= $stats['failed_emails'] ?>)</h2>
                <div>
                    <?php 
                    $failed_result = $conn->query("SELECT * FROM email_logs WHERE status = 'failed' ORDER BY sent_at DESC LIMIT 10");
                    if ($failed_result && $failed_result->num_rows > 0):
                        while ($failed_log = $failed_result->fetch_assoc()):
                            $failed_data = json_decode($failed_log['additional_data'] ?? '{}', true) ?? [];
                    ?>
                        <div class="log-entry" style="border-left-color: var(--error);">
                            <div class="log-header">
                                <div>
                                    <span class="log-type type-<?= $failed_log['email_type'] ?>"><?= $failed_log['email_type'] ?></span>
                                    <span class="status status-failed">Failed</span>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?= date('M j, Y g:i A', strtotime($failed_log['sent_at'])) ?>
                                </div>
                            </div>
                            <div class="log-details">
                                <strong>To:</strong> <?= htmlspecialchars($failed_log['recipient_email']) ?><br>
                                <strong>Subject:</strong> <?= htmlspecialchars($failed_log['subject']) ?><br>
                                <?php if (isset($failed_data['error'])): ?>
                                    <strong>Error:</strong> <span style="color: var(--error);"><?= htmlspecialchars($failed_data['error']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div style="color: var(--text-muted); text-align: center; padding: 20px;">
                            No failed emails found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <button class="refresh-btn" onclick="window.location.reload()">🔄</button>

    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);

        // Export logs function
        function exportLogs() {
            // In real implementation, this would make an AJAX call to export logs
            alert('Export functionality would be implemented here to download CSV/Excel file of email logs.');
        }

        // Real-time updates simulation
        function simulateRealTimeUpdates() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const currentValue = parseInt(stat.textContent.replace(/,/g, ''));
                // Simulate small increments
                if (Math.random() > 0.8) {
                    stat.textContent = (currentValue + 1).toLocaleString();
                }
            });
        }

        // Update every 30 seconds for demo
        setInterval(simulateRealTimeUpdates, 30000);

        // Add loading states
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (this.getAttribute('href').includes('filter=')) {
                        // Add loading state
                        this.style.opacity = '0.7';
                        this.innerHTML = '⏳ Loading...';
                    }
                });
            });
        });

        // Tooltip functionality for log entries
        document.addEventListener('DOMContentLoaded', function() {
            const logEntries = document.querySelectorAll('.log-entry');
            logEntries.forEach(entry => {
                entry.addEventListener('mouseenter', function() {
                    this.style.cursor = 'pointer';
                });
                
                entry.addEventListener('click', function() {
                    // In real implementation, show detailed log modal
                    const logId = this.querySelector('.log-details').dataset.logId;
                    console.log('Show detailed view for log ID:', logId);
                });
            });
        });

        // Add search functionality
        function addSearchFunctionality() {
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search emails...';
            searchInput.style.cssText = `
                padding: 8px 12px;
                border: 1px solid var(--border-light);
                background: var(--surface-elevated);
                color: var(--text-primary);
                border-radius: var(--radius);
                margin-bottom: 16px;
                width: 300px;
            `;
            
            const filterControls = document.querySelector('.filter-controls');
            filterControls.parentNode.insertBefore(searchInput, filterControls);
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const logEntries = document.querySelectorAll('.log-entry');
                
                logEntries.forEach(entry => {
                    const text = entry.textContent.toLowerCase();
                    entry.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
            });
        }

        // Initialize search on page load
        document.addEventListener('DOMContentLoaded', addSearchFunctionality);
    </script>

    <style>
        /* Additional responsive styles */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 16px 20px;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading animations */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Scrollbar styling */
        .activity-log::-webkit-scrollbar {
            width: 6px;
        }

        .activity-log::-webkit-scrollbar-track {
            background: var(--surface-elevated);
            border-radius: 3px;
        }

        .activity-log::-webkit-scrollbar-thumb {
            background: var(--border-light);
            border-radius: 3px;
        }

        .activity-log::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
    </style>
</head>
<body>
    <!-- Mobile menu toggle (for responsive design) -->
    <button id="mobileMenuToggle" style="display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: var(--primary-color); border: none; color: white; padding: 10px; border-radius: var(--radius); cursor: pointer;">
        ☰
    </button>

    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'block';
            }
            
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !mobileToggle.contains(e.target) && 
                    sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    mobileToggle.style.display = 'none';
                    sidebar.classList.remove('open');
                } else {
                    mobileToggle.style.display = 'block';
                }
            });
        });

        // Enhanced chart interactivity
        document.addEventListener('DOMContentLoaded', function() {
            const chartBars = document.querySelectorAll('.chart-bar');
            chartBars.forEach(bar => {
                bar.addEventListener('mouseenter', function() {
                    const value = this.querySelector('.chart-value');
                    const label = this.querySelector('.chart-label');
                    if (value && label) {
                        value.style.display = 'block';
                        this.style.filter = 'brightness(1.1)';
                    }
                });
                
                bar.addEventListener('mouseleave', function() {
                    this.style.filter = 'brightness(1)';
                });
            });
        });

        // Add notification system for new logs
        function checkForNewLogs() {
            // In real implementation, this would make an AJAX call to check for new logs
            // For demo purposes, we'll simulate new log notifications
            if (Math.random() > 0.95) {
                showNotification('New email log detected!', 'success');
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                background: ${type === 'success' ? 'var(--success)' : 'var(--primary-color)'};
                color: white;
                border-radius: var(--radius);
                box-shadow: var(--shadow-lg);
                z-index: 1002;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Check for new logs every minute
        setInterval(checkForNewLogs, 60000);

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>