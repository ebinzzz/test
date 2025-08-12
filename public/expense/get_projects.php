<?php
// --- Configuration and Initialization ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Ensure this path is correct for your file structure
@include_once '../config.php'; 
header('Content-Type: application/json');

function getDatabaseConnection() {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        return $GLOBALS['conn'];
    }

    // Mock connection for development
    class MockResult {
        private $data;
        public function __construct($data) { $this->data = $data; }
        public function fetch_all($mode = MYSQLI_ASSOC) { return $this->data; }
        public function close() {}
    }
    class MockConnection {
        public function prepare($sql) {
            $mock_data = [];
            if (strpos($sql, 'WHERE client_id = ?') !== false) {
                $mock_data = [
                    ['id' => 101, 'project_name' => 'Mock Project Alpha'],
                    ['id' => 102, 'project_name' => 'Mock Project Beta']
                ];
            }
            return new class($mock_data) {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function bind_param($types, ...$vars) {}
                public function execute() { return true; }
                public function get_result() { return new MockResult($this->data); }
                public function close() {}
            };
        }
    }
    return new MockConnection();
}

$conn = getDatabaseConnection();

$projects = [];
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int)$_GET['client_id'];
    
    // Using prepared statement to prevent SQL injection
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name");
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
        // Mock data for development
        if ($client_id == 1) {
            $projects = [['id' => 101, 'project_name' => 'Website Redesign'], ['id' => 102, 'project_name' => 'Mobile App Development']];
        } else if ($client_id == 2) {
            $projects = [['id' => 201, 'project_name' => 'Marketing Campaign'], ['id' => 202, 'project_name' => 'SEO Audit']];
        }
    }
}

echo json_encode($projects);