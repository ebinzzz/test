<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

@include_once '../config.php';

// Initialize variables
$task_id = null;
$comments = [];
$message = '';
$error_message = '';
$task_title = '';

// Get task ID from URL
if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
    $task_id = (int)$_GET['task_id'];
} else {
    $error_message = "Invalid task ID provided.";
}

// Mock database connection for testing
if (!isset($conn)) {
    class MockResult {
        public $data;
        public $fetch_assoc_index = 0;
        public function __construct($data = []) { $this->data = $data; }
        public function fetch_assoc() {
            if ($this->fetch_assoc_index < count($this->data)) {
                return $this->data[$this->fetch_assoc_index++];
            }
            return null;
        }
        public function num_rows() { return count($this->data); }
    }
    
    class MockConnection {
        public static $comments = [
            ['id' => 1, 'task_id' => 1, 'comment_text' => 'This task is progressing well. Need to focus on the frontend integration.', 'created_by_name' => 'Alice Johnson', 'created_at' => '2025-08-10 14:30:00'],
            ['id' => 2, 'task_id' => 1, 'comment_text' => 'Updated the database schema. Ready for testing phase.', 'created_by_name' => 'Bob Williams', 'created_at' => '2025-08-11 09:15:00'],
            ['id' => 3, 'task_id' => 1, 'comment_text' => 'Testing completed successfully. Moving to production deployment.', 'created_by_name' => 'Charlie Brown', 'created_at' => '2025-08-11 16:45:00']
        ];
        
        public function query($sql) {
            if (strpos($sql, 'SELECT title FROM tasks') !== false) {
                return new MockResult([['title' => 'Sample Task - Frontend Development']]);
            } elseif (strpos($sql, 'SELECT c.*, tm.full_name') !== false) {
                $task_comments = array_filter(self::$comments, function($comment) use ($sql) {
                    global $task_id;
                    return $comment['task_id'] == $task_id;
                });
                return new MockResult(array_values($task_comments));
            }
            return new MockResult([]);
        }
        
        public function prepare($sql) {
            if (strpos($sql, "INSERT INTO task_comments") !== false) {
                return new class() {
                    public function bind_param($types, $task_id, $comment_text, $user_id) {
                        $this->task_id = $task_id;
                        $this->comment_text = $comment_text;
                        $this->user_id = $user_id;
                    }
                    public function execute() { 
                        $new_id = max(array_column(MockConnection::$comments, 'id')) + 1;
                        MockConnection::$comments[] = [
                            'id' => $new_id,
                            'task_id' => $this->task_id,
                            'comment_text' => $this->comment_text,
                            'created_by_name' => 'Current User',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        return true;
                    }
                    public function close() {}
                };
            } elseif (strpos($sql, "DELETE FROM task_comments") !== false) {
                return new class() {
                    public function bind_param($types, $comment_id) {
                        $this->comment_id = $comment_id;
                    }
                    public function execute() {
                        MockConnection::$comments = array_filter(MockConnection::$comments, function($comment) {
                            return $comment['id'] != $this->comment_id;
                        });
                        MockConnection::$comments = array_values(MockConnection::$comments);
                        return true;
                    }
                    public function close() {}
                };
            }
            return false;
        }
        
        public function error() { return "Mock Error"; }
    }
    $conn = new MockConnection();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_comment']) && !empty($_POST['comment_text']) && $task_id) {
        // Add new comment
        $comment_text = trim($_POST['comment_text']);
        $created_by = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 1;
        
        if ($conn instanceof mysqli) {
            $sql = "INSERT INTO task_comments (task_id, comment, created_by, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("isi", $task_id, $comment_text, $created_by);
                if ($stmt->execute()) {
                    $message = "Comment added successfully!";
                } else {
                    $error_message = "Error adding comment: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            // Mock connection
            $stmt = $conn->prepare("INSERT INTO task_comments");
            if ($stmt) {
                $stmt->bind_param("isi", $task_id, $comment_text, $created_by);
                if ($stmt->execute()) {
                    $message = "Comment added successfully!";
                } else {
                    $error_message = "Error adding comment.";
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['delete_comment']) && isset($_POST['comment_id'])) {
        // Delete comment
        $comment_id = (int)$_POST['comment_id'];
        
        if ($conn instanceof mysqli) {
            $sql = "DELETE FROM task_comments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $comment_id);
                if ($stmt->execute()) {
                    $message = "Comment deleted successfully!";
                } else {
                    $error_message = "Error deleting comment: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            // Mock connection
            $stmt = $conn->prepare("DELETE FROM task_comments");
            if ($stmt) {
                $stmt->bind_param("i", $comment_id);
                if ($stmt->execute()) {
                    $message = "Comment deleted successfully!";
                } else {
                    $error_message = "Error deleting comment.";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch task title
if ($task_id) {
    if ($conn instanceof mysqli) {
        $sql = "SELECT title FROM tasks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $task_data = $result->fetch_assoc();
                $task_title = $task_data['title'];
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query("SELECT title FROM tasks");
        if ($result) {
            $task_data = $result->fetch_assoc();
            $task_title = $task_data['title'];

        }
    }
}

// Fetch comments for the task
if ($task_id) {
    if ($conn instanceof mysqli) {
        $sql = "SELECT c.*, tm.last_name as created_by_name 
                FROM task_comments c 
                LEFT JOIN users tm ON c.id = tm.id 
                WHERE c.task_id = ? 
                ORDER BY c.created_at DESC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Mock connection
        $result = $conn->query("SELECT c.*, tm.full_name as created_by_name FROM task_comments");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Comments - Zorqent Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --danger: #dc2626;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
            --radius: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--background); color: var(--text-primary); line-height: 1.6; font-size: 14px; display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: var(--surface); height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; border-right: 1px solid var(--border); z-index: 1000; }
        .sidebar-header { padding: 32px 24px 24px; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 20px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.025em; }
        .sidebar-nav { padding: 24px 0; }
        .sidebar-nav a { display: flex; align-items: center; padding: 12px 24px; color: var(--text-secondary); text-decoration: none; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { background: rgba(59, 130, 246, 0.1); color: var(--text-primary); border-left-color: var(--primary-color); }
        .sidebar-nav a.active { background: rgba(59, 130, 246, 0.15); color: var(--text-primary); border-left-color: var(--primary-color); }
        .sidebar-nav a.logout { margin-top: 24px; border-top: 1px solid var(--border); padding-top: 24px; color: #fca5a5; }
        .sidebar-nav a.logout:hover { background: rgba(248, 113, 113, 0.1); color: #fecaca; border-left-color: var(--error); }
        
        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; background: var(--background); }
        .header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 24px 32px; position: sticky; top: 0; z-index: 100; box-shadow: var(--shadow-sm); }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .header h1 { font-size: 24px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.025em; }
        .content { padding: 32px; max-width: 1200px; margin: 0 auto; }
        
        .alert { padding: 16px; border-radius: var(--radius); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-danger { background: rgba(248, 113, 113, 0.1); color: var(--error); border: 1px solid rgba(248, 113, 113, 0.2); }
        
        .comments-section { background: var(--surface); padding: 32px; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
        .comments-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .comments-header h3 { color: var(--text-primary); font-size: 20px; font-weight: 600; }
        .task-info { background: var(--surface-elevated); padding: 16px; border-radius: var(--radius); margin-bottom: 24px; }
        .task-info h4 { color: var(--text-primary); margin-bottom: 8px; }
        .task-info p { color: var(--text-secondary); }
        
        .comment { background: var(--surface-elevated); padding: 20px; border-radius: var(--radius); margin-bottom: 16px; border: 1px solid var(--border); }
        .comment:last-child { margin-bottom: 0; }
        .comment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .comment-author { color: var(--text-primary); font-weight: 600; }
        .comment-date { color: var(--text-muted); font-size: 12px; }
        .comment-text { color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px; }
        .comment-actions { display: flex; gap: 8px; }
        
        .add-comment-form { background: var(--surface-elevated); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border); margin-top: 24px; }
        .add-comment-form h4 { color: var(--text-primary); margin-bottom: 16px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-secondary); }
        .form-textarea { width: 100%; padding: 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-primary); font-size: 14px; resize: vertical; min-height: 120px; font-family: inherit; }
        
        .btn { display: inline-flex; align-items: center; padding: 10px 16px; border: none; border-radius: var(--radius); font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; transition: all 0.2s ease; outline: none; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-danger { background: var(--danger); color: white; font-size: 12px; padding: 6px 12px; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-1px); }
        .btn-secondary { background: var(--surface-elevated); color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--border); color: var(--text-primary); }
        
        .no-comments { text-align: center; padding: 40px; color: var(--text-muted); }
        .no-comments i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        
        .back-link { display: inline-flex; align-items: center; color: var(--text-secondary); text-decoration: none; margin-bottom: 24px; font-weight: 500; }
        .back-link:hover { color: var(--primary-color); }
        .back-link i { margin-right: 8px; }

        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; border-right: none; }
            .main-content { margin-left: 0; }
            .header { padding: 16px; }
            .header h1 { font-size: 20px; }
            .content { padding: 16px; }
            .comments-section { padding: 20px; }
            .add-comment-form { padding: 16px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Zorqent Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="../dashboard.php"><i class="fas fa-home" style="margin-right: 12px;"></i>Dashboard</a>
            <a href="../client/manage.php"><i class="fas fa-users" style="margin-right: 12px;"></i>Clients</a>
            <a href="../project/manage.php"><i class="fas fa-project-diagram" style="margin-right: 12px;"></i>Projects</a>
            <a href="../team/manage.php"><i class="fas fa-user-friends" style="margin-right: 12px;"></i>Team</a>
            <a href="../expense/manage.php"><i class="fas fa-wallet" style="margin-right: 12px;"></i>Expenses</a>
            <a href="manage.php" class="active"><i class="fas fa-tasks" style="margin-right: 12px;"></i>To Do</a>
            <a href="../mail/manage.php"><i class="fas fa-envelope" style="margin-right: 12px;"></i>Mail</a>
            <a href="../support/manage.php"><i class="fas fa-headset" style="margin-right: 12px;"></i>Support</a>
            <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt" style="margin-right: 12px;"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-content">
                <h1>Task Comments</h1>
            </div>
        </div>

        <div class="content">
            <a href="manage.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Tasks
            </a>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($task_id): ?>
                <div class="comments-section">
                    <div class="task-info">
                        <h4>Task: <?php echo htmlspecialchars($task_title); ?></h4>
                        <p>Task ID: #<?php echo $task_id; ?></p>
                    </div>
                <div class="add-comment-form">
                        <h4><i class="fas fa-plus-circle" style="margin-right: 8px;"></i>Add New Comment</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label for="comment_text">Comment</label>
                                <textarea id="comment_text" name="comment_text" class="form-textarea" placeholder="Write your comment here..." required></textarea>
                            </div>
                            <button type="submit" name="add_comment" class="btn btn-primary">
                                <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>
                                Post Comment
                            </button>
                        </form>
                    </div>
                    <br>
                    <div class="comments-header">
                        <h3><i class="fas fa-comments" style="margin-right: 10px;"></i>Comments (<?php echo count($comments); ?>)</h3>
                    </div>
                    
                    <div class="comments-list">
                        <?php if (empty($comments)): ?>
                            <div class="no-comments">
                                <i class="fas fa-comment-slash"></i>
                                <p>No comments yet. Be the first to add a comment!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <span class="comment-author">
                                            <i class="fas fa-user-circle" style="margin-right: 8px;"></i>
                                            <?php echo htmlspecialchars($comment['created_by_name']); ?>
                                        </span>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <span class="comment-date">
                                                <i class="fas fa-clock" style="margin-right: 4px;"></i>
                                                <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                            </span>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                <button type="submit" name="delete_comment" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>