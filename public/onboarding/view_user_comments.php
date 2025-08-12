<?php
session_start();

// Redirect to login if the user is not authenticated

// Database connection (adjust as needed).
@include_once '../config.php';

// Initialize variables
$task_id = null;
$comments = [];
$error_message = '';
$task_title = '';

// Get task ID from URL
if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
    $task_id = (int)$_GET['task_id'];
} else {
    $error_message = "Invalid task ID provided.";
}

// --- Mock Database Connection and Data Block ---
// This provides a fallback for testing without a real database.
if (!isset($conn)) {
    $error_message = "Database connection not established. Using mock data for demonstration.";
    
    class MockResult {
        public $data;
        private $fetch_assoc_index = 0;
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
            ['id' => 1, 'task_id' => 1, 'comment' => 'This task is progressing well. Need to focus on the frontend integration.', 'created_by_name' => 'Alice Johnson', 'created_at' => '2025-08-10 14:30:00'],
            ['id' => 2, 'task_id' => 1, 'comment' => 'Updated the database schema. Ready for testing phase.', 'created_by_name' => 'Bob Williams', 'created_at' => '2025-08-11 09:15:00'],
            ['id' => 3, 'task_id' => 2, 'comment' => 'Initial wireframes are complete. Awaiting feedback.', 'created_by_name' => 'Charlie Brown', 'created_at' => '2025-08-11 16:45:00'],
        ];

        public function prepare($sql) {
            if (strpos($sql, "SELECT title FROM tasks") !== false) {
                return new class() {
                    public function bind_param(...$params) {}
                    public function execute() { return true; }
                    public function get_result() { return new MockResult([['title' => 'Sample Task Title']]); }
                    public function close() {}
                };
            } elseif (strpos($sql, "SELECT c.id, c.comment") !== false) {
                return new class() {
                    public $data_to_return;
                    public function bind_param(...$params) {
                        [$types, $task_id] = $params;
                        $this->data_to_return = array_values(array_filter(MockConnection::$comments, function($comment) use ($task_id) {
                            return $comment['task_id'] == $task_id;
                        }));
                        usort($this->data_to_return, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                    }
                    public function execute() { return true; }
                    public function get_result() { return new MockResult($this->data_to_return); }
                    public function close() {}
                };
            }
            return false;
        }
    }
    $conn = new MockConnection();
}
// --- End Mock Database Block ---


// Check for messages passed via URL parameters after a redirect.
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

// Fetch task title and comments for display.
if ($task_id) {
    // Fetch task title
    $task_title_sql = "SELECT title FROM tasks WHERE id = ?";
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare($task_title_sql);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $task_title = $row['title'];
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare($task_title_sql);
        $stmt->bind_param("i", $task_id);
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $task_title = $row['title'];
        }
    }

    // Fetch comments
    $comments_sql = "SELECT c.id, c.comment, tm.last_name as created_by_name, c.created_at 
                     FROM task_comments c 
                     LEFT JOIN users tm ON c.created_by = tm.id 
                     WHERE c.task_id = ? 
                     ORDER BY c.created_at DESC";
    
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare($comments_sql);
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare($comments_sql);
        $stmt->bind_param("i", $task_id);
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
    }
}
include_once 'common_layout.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comments for: <?php echo htmlspecialchars($task_title ? $task_title : 'Task Not Found'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* 
        ISOLATED COMMENTS PAGE STYLES
        All styles are scoped to .comments-page-wrapper to prevent interference with common_layout.php
        This ensures complete isolation from the sidebar and main layout styles
        */
        .comments-page-wrapper {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            background-color: #f3f4f6 !important;
            color: #1f2937 !important;
            margin: 0 !important;
            padding: 20px !important;
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        .comments-page-wrapper * {
            box-sizing: border-box;
        }
        
        .comments-page-wrapper .comments-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .comments-page-wrapper .comments-page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .comments-page-wrapper .comments-page-header h1 {
            font-size: 2.5em !important;
            font-weight: 700 !important;
            color: #1f2937 !important;
            margin-bottom: 10px !important;
            margin-top: 0 !important;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .comments-page-wrapper .comments-page-subtitle {
            font-size: 1.1em !important;
            color: #6b7280 !important;
            margin: 0 !important;
        }
        
        .comments-page-wrapper .comments-alert {
            padding: 15px !important;
            margin-bottom: 20px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .comments-page-wrapper .comments-alert.error {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #ef4444 !important;
        }
        
        .comments-page-wrapper .comments-alert.info {
            background-color: #e0f2fe !important;
            color: #075985 !important;
            border: 1px solid #38bdf8 !important;
        }
        
        .comments-page-wrapper .comments-btn {
            padding: 10px 15px !important;
            background-color: #3b82f6 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: background-color 0.2s !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            font-family: inherit !important;
        }
        
        .comments-page-wrapper .comments-btn:hover {
            background-color: #1e40af !important;
        }
        
        .comments-page-wrapper .comments-back-btn {
            margin-bottom: 20px !important;
        }
        
        .comments-page-wrapper .comments-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 15px !important;
        }
        
        .comments-page-wrapper .comment-card {
            background-color: #ffffff !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
            padding: 20px !important;
            border: 1px solid #e5e7eb !important;
            position: relative !important;
        }
        
        .comments-page-wrapper .comment-card .comment-user-name {
            font-weight: 700 !important;
            color: #1f2937 !important;
            font-size: 1em !important;
            margin-bottom: 5px !important;
            margin-top: 0 !important;
        }
        
        .comments-page-wrapper .comment-card .comment-text {
            color: #4b5563 !important;
            line-height: 1.5 !important;
            margin-top: 0 !important;
            margin-bottom: 10px !important;
            white-space: pre-wrap !important;
        }
        
        .comments-page-wrapper .comment-card .comment-timestamp {
            font-size: 0.8em !important;
            color: #9ca3af !important;
            text-align: right !important;
            border-top: 1px solid #e5e7eb !important;
            padding-top: 10px !important;
            margin-top: 10px !important;
            margin-bottom: 0 !important;
        }

        /* Mobile responsiveness for comments page */
        @media (max-width: 768px) {
            .comments-page-wrapper {
                padding: 10px !important;
            }
            
            .comments-page-wrapper .comments-page-header {
                padding: 20px !important;
                margin-bottom: 20px !important;
            }
            
            .comments-page-wrapper .comments-page-header h1 {
                font-size: 2em !important;
            }
            
            .comments-page-wrapper .comment-card {
                padding: 15px !important;
            }
        }
    </style>
</head>
<body>

<!-- Wrap everything in a scoped container to prevent CSS conflicts -->
<div class="comments-page-wrapper">
    <div class="comments-container">
        <div class="comments-page-header">
            <h1>
                <i class="fas fa-comments" style="margin-right: 15px;"></i>
                Comments for: <?php echo htmlspecialchars($task_title ? $task_title : 'Task Not Found'); ?>
            </h1>
            <p class="comments-page-subtitle">A chronological view of all comments related to this task.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="comments-alert error">
                <i class="fas fa-times-circle"></i> 
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <a href="update_assign_status.php" class="comments-btn comments-back-btn">
            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back to Dashboard
        </a>

        <?php if (!empty($comments)): ?>
            <div class="comments-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-card">
                        <div class="comment-user-name">
                            <i class="fas fa-user-circle" style="margin-right: 5px; color: #6b7280;"></i>
                            <?php echo htmlspecialchars($comment['created_by_name'] ?? 'Unknown User'); ?>
                        </div>
                        <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                        <div class="comment-timestamp">
                            Posted on: <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($comment['created_at']))); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="comments-alert info">
                <i class="fas fa-info-circle"></i>
                No comments found for this task.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>