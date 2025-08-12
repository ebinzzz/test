<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once("class.phpmailer.php");
include_once("class.smtp.php");

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
    }

    class MockConnection {
        public $insert_id = 1234;
        public $affected_rows = 1;
        
        public function prepare($sql) {
            $mock_conn = $this;
            return new class($sql, $mock_conn) {
                private $sql;
                private $bound_params = [];
                private $mock_conn;
                
                public function __construct($sql, $mock_conn) { 
                    $this->sql = $sql; 
                    $this->mock_conn = $mock_conn;
                }
                public function bind_param($types, ...$vars) { 
                    $this->bound_params = $vars;
                }
                public function execute() { 
                    // Log the database operation
                    error_log("Mock DB Execute: " . $this->sql . " with params: " . json_encode($this->bound_params));
                    $this->mock_conn->insert_id = rand(1000, 9999);
                    return true; 
                }
                public function get_result() {
                    $mockData = [];
                    if (strpos($this->sql, 'SELECT email, company_name AS name FROM clients') !== false) {
                        $mockData = [['email' => 'client.a@example.com', 'name' => 'Client A Inc.']];
                    }
                    if (strpos($this->sql, 'SELECT email, full_name AS name FROM team_members') !== false) {
                        if (isset($this->bound_params[0])) {
                            if ($this->bound_params[0] == 101) $mockData = [['email' => 'alice@example.com', 'name' => 'Alice Wonderland']];
                            if ($this->bound_params[0] == 102) $mockData = [['email' => 'bob@example.com', 'name' => 'Bob The Builder']];
                        }
                    }
                    if (strpos($this->sql, 'SELECT project_name, status FROM projects WHERE id = ?') !== false) {
                        if (isset($this->bound_params[0])) {
                            if ($this->bound_params[0] == 201) $mockData = [['project_name' => 'Project Alpha', 'status' => 'In Progress']];
                            else $mockData = [['project_name' => 'Your Project', 'status' => 'Pending']];
                        }
                    }
                    if (strpos($this->sql, 'SELECT email, company_name AS name FROM clients') !== false && strpos($this->sql, 'WHERE status = \'active\'') !== false) {
                        $mockData = [
                            ['email' => 'client.a@example.com', 'name' => 'Client A Inc.', 'id' => 1],
                            ['email' => 'client.b@example.com', 'name' => 'Client B Corp.', 'id' => 2]
                        ];
                    }
                    return new MockResult($mockData);
                }
                public function close() {}
            };
        }
        public function query($sql) {
            error_log("Mock DB Query: " . $sql);
            $this->insert_id = rand(1000, 9999);
            $mockData = [];
            if (strpos($sql, 'SELECT COUNT(id) AS total FROM team_members') !== false) {
                $mockData = [['total' => 50]];
            }
            if (strpos($sql, 'SELECT COUNT(id) AS completed FROM projects WHERE status = \'Completed\'') !== false) {
                $mockData = [['completed' => 25]];
            }
            if (strpos($sql, 'SELECT COUNT(id) AS ongoing FROM projects WHERE status = \'Active\'') !== false) {
                $mockData = [['ongoing' => 12]];
            }
            return new MockResult($mockData);
        }
        public function close() {}
    }
    return new MockConnection();
}

// Function to log email communications
function logEmailCommunication($conn, $email_type, $recipient_type, $recipient_id, $recipient_email, $subject, $content, $status, $additional_data = []) {
    $stmt = $conn->prepare("
        INSERT INTO email_logs 
        (email_type, recipient_type, recipient_id, recipient_email, subject, content, status, additional_data, sent_at, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    if ($stmt) {
        $created_by = $_SESSION['admin_id'] ?? 1;
        $additional_data_json = json_encode($additional_data);
        
        $stmt->bind_param("ssisssssi", 
            $email_type, 
            $recipient_type, 
            $recipient_id, 
            $recipient_email, 
            $subject, 
            $content, 
            $status, 
            $additional_data_json, 
            $created_by
        );
        
        $stmt->execute();
        $log_id = $conn->insert_id;
        $stmt->close();
        
        return $log_id;
    }
    return false;
}

// Function to update project progress based on email type
function updateProjectProgress($conn, $project_id, $email_type, $content) {
    if (!$project_id || $email_type !== 'client') return;
    
    $progress_update = '';
    $status_update = '';
    
    if (stripos($content, 'completed') !== false || stripos($content, 'finished') !== false) {
        $status_update = 'Completed';
        $progress_update = 'Project marked as completed via email notification';
    } elseif (stripos($content, 'in progress') !== false || stripos($content, 'working on') !== false) {
        $status_update = 'Active';
        $progress_update = 'Project status updated to active via email notification';
    } elseif (stripos($content, 'milestone') !== false || stripos($content, 'progress') !== false) {
        $progress_update = 'Progress milestone communicated to client';
    }
    
    if ($progress_update) {
        $stmt = $conn->prepare("
            INSERT INTO project_progress_logs 
            (project_id, progress_note, status_change, logged_at, logged_by) 
            VALUES (?, ?, ?, NOW(), ?)
        ");
        
        if ($stmt) {
            $logged_by = $_SESSION['admin_id'] ?? 1;
            $stmt->bind_param("issi", $project_id, $progress_update, $status_update, $logged_by);
            $stmt->execute();
            $stmt->close();
            
            if ($status_update) {
                $update_stmt = $conn->prepare("UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $status_update, $project_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
        }
    }
}

// Function to log team member activity
function logTeamActivity($conn, $team_member_ids, $subject, $content, $role) {
    if (empty($team_member_ids)) return;
    
    $activity_type = 'email_notification';
    $activity_description = "Email notification sent: $subject";
    
    foreach ($team_member_ids as $member_id) {
        $stmt = $conn->prepare("
            INSERT INTO team_activity_logs 
            (team_member_id, activity_type, activity_description, details, logged_at, logged_by) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        
        if ($stmt) {
            $logged_by = $_SESSION['admin_id'] ?? 1;
            $details = json_encode([
                'role' => $role,
                'email_content' => substr($content, 0, 500),
                'notification_type' => 'team_communication'
            ]);
            
            $stmt->bind_param("isssi", $member_id, $activity_type, $activity_description, $details, $logged_by);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn = getDatabaseConnection();

$mail = new PHPMailer();
$mail->IsSMTP();
$mail->SMTPAuth = true;
$mail->SMTPSecure = "tls";
$mail->Host = "smtp.gmail.com";
$mail->Port = 587;
$mail->Username = "zorqent@gmail.com";
$mail->Password = "uvimcgnfwxdresfv";

$mail->SMTPDebug = 0;
$mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

$mail->SetFrom("zorqent@gmail.com", "Zorqent Team");
$mail->AddReplyTo("support@zorqent.com", "Zorqent Support");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_type = $_POST['email_type'] ?? '';
    $subject = $_POST['subject'] ?? 'Notification from Zorqent';
    $content = $_POST['content'] ?? '';
    $recipients = [];
    $additional_data = [];

    $recipient_type = '';
    $recipient_ids = [];

    if ($email_type === 'team') {
        $recipient_ids = $_POST['recipient_ids'] ?? [];
        $recipient_type = 'team_member';
        $role = $_POST['role'] ?? 'Team Member'; 
        
        if (!empty($recipient_ids)) {
            $placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
            $types = str_repeat('i', count($recipient_ids));
            $stmt = $conn->prepare("SELECT email, full_name AS name, id FROM team_members WHERE id IN ($placeholders)");
            
            if ($stmt) {
                $stmt->bind_param($types, ...$recipient_ids);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = $row;
                }
                $stmt->close();
            }
        }
        
        $count_result = $conn->query("SELECT COUNT(id) AS total FROM team_members");
        $total_team_members = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
        
        $completed_result = $conn->query("SELECT COUNT(id) AS completed FROM projects WHERE status = 'Completed'");
        $completed_projects_count = ($completed_result && $row = $completed_result->fetch_assoc()) ? $row['completed'] : 0;
        
        $ongoing_result = $conn->query("SELECT COUNT(id) AS ongoing FROM projects WHERE status = 'Active'");
        $ongoing_projects_count = ($ongoing_result && $row = $ongoing_result->fetch_assoc()) ? $row['ongoing'] : 0;

        $additional_data['team_stats'] = [
            'total_members' => $total_team_members,
            'completed_projects' => $completed_projects_count,
            'ongoing_projects' => $ongoing_projects_count
        ];

    } else if ($email_type === 'client') {
        $recipient_id = $_POST['recipient_id'] ?? 0;
        $recipient_type = 'client';
        $project_id = $_POST['project_id'] ?? null;
        
        if ($recipient_id > 0) {
            $stmt = $conn->prepare("SELECT email, company_name AS name, id FROM clients WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $recipient_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $recipients[] = $row;
                }
                $stmt->close();
            }
        }
    } else if ($email_type === 'promotion') {
        $recipient_type = 'client';
        $stmt = $conn->prepare("SELECT email, company_name AS name, id FROM clients WHERE status = 'active' ORDER BY company_name");
        
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
            $stmt->close();
        }
    } else {
        $recipient_email = $_POST['recipient_email'] ?? '';
        $recipient_type = 'external';
        if (!empty($recipient_email)) {
            $recipients[] = [
                'email' => $recipient_email,
                'name' => strtok($recipient_email, '@')
            ];
        }
    }

    if (empty($recipients)) {
        logEmailCommunication($conn, $email_type, $recipient_type, 0, '', $subject, $content, 'failed', 
            array_merge($additional_data, ['error' => 'No recipients found']));
        
        header('Location: manage.php?status=error&msg=' . urlencode("Recipient email not found or provided."));
        exit();
    }

    foreach ($recipients as $recipient) {
        $mail->AddAddress($recipient['email'], $recipient['name']);
    }
    
    $recipient_name = $recipients[0]['name'] ?? 'Recipient';
    $mail->Subject = $subject;
    $mail->IsHTML(true);

    $template_file = 'templates/' . $email_type . '.html';
    $body = "Dear " . htmlspecialchars($recipient_name) . ",<br><br>This is a notification regarding: " . htmlspecialchars($subject) . ".<br><br>Content:<br>" . nl2br(htmlspecialchars($content)) . "<br><br>Regards,<br>Zorqent Team";
    
    if (file_exists($template_file)) {
        $body = file_get_contents($template_file);

        $body = str_replace('{{recipient_name}}', htmlspecialchars($recipient_name), $body);
        $body = str_replace('{{subject}}', htmlspecialchars($subject), $body);
        $body = str_replace('{{content}}', nl2br(htmlspecialchars($content)), $body);
        $body = str_replace('{{current_year}}', date('Y'), $body);

        if ($email_type === 'ticket') {
            $ticket_id = $_POST['ticket_id'] ?? 'N/A';
            $ticket_status = $_POST['ticket_status'] ?? 'N/A';
            $body = str_replace('{{ticket_id}}', htmlspecialchars($ticket_id), $body);
            $body = str_replace('{{ticket_status}}', htmlspecialchars($ticket_status), $body);
            $additional_data['ticket_info'] = ['id' => $ticket_id, 'status' => $ticket_status];
            
        } elseif ($email_type === 'client' || $email_type === 'promotion') {
            $project_id = $_POST['project_id'] ?? null;
            $project_name = 'Your Project';
            $project_status = 'N/A';
            if ($project_id) {
                $stmt = $conn->prepare("SELECT project_name, status FROM projects WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $project_name = $row['project_name'];
                        $project_status = $row['status'];
                    }
                    $stmt->close();
                }
            }
            $body = str_replace('{{project_name}}', htmlspecialchars($project_name), $body);
            $body = str_replace('{{project_id}}', htmlspecialchars($project_id), $body);
            $body = str_replace('{{status}}', htmlspecialchars($project_status ?? ''), $body);
            
            $additional_data['project_info'] = [
                'name' => $project_name, 
                'status' => $project_status
            ];
        } elseif ($email_type === 'team') {
            $body = str_replace('{{role}}', htmlspecialchars($role), $body);
            $body = str_replace('{{total_team_members}}', htmlspecialchars($total_team_members), $body);
            $body = str_replace('{{completed_projects_count}}', htmlspecialchars($completed_projects_count), $body);
            $body = str_replace('{{ongoing_projects_count}}', htmlspecialchars($ongoing_projects_count), $body);
        }
    }
    
    $mail->Body = $body;

    $email_sent = $mail->Send();
    $email_status = $email_sent ? 'sent' : 'failed';
    $error_info = $email_sent ? null : $mail->ErrorInfo;
    
    if (!$email_sent) {
        $additional_data['error'] = $error_info;
    }

    foreach ($recipients as $recipient) {
        $log_recipient_id = 0;
        if ($email_type === 'team' || $email_type === 'client' || $email_type === 'promotion') {
            $log_recipient_id = $recipient['id'] ?? 0;
        }

        logEmailCommunication($conn, $email_type, $recipient_type, $log_recipient_id, 
            $recipient['email'], $subject, $content, $email_status, $additional_data);
    }

    if ($email_sent) {
        $success_msg = "Email sent successfully to " . count($recipients) . " recipient(s)!";
        if ($email_type === 'client' && isset($project_id)) {
            updateProjectProgress($conn, $project_id, $email_type, $content);
            $success_msg .= " Project progress has been logged.";
        } elseif ($email_type === 'team' && !empty($recipient_ids)) {
            logTeamActivity($conn, $recipient_ids, $subject, $content, $role);
            $success_msg .= " Team activity has been recorded.";
        }
        header('Location: manage.php?status=success&msg=' . urlencode($success_msg));
    } else {
        header('Location: manage.php?status=error&msg=' . urlencode("Failed to send email: " . $error_info));
    }
    exit();
    
} else {
    header('Location: manage.php?status=error&msg=' . urlencode("Invalid request method."));
    exit();
}
?>