<?php
header('Content-Type: application/json');

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
        public function fetch_all($mode = MYSQLI_ASSOC) {
            return $this->data;
        }
    }

    class MockConnection {
        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                private $bound_id;
                public function __construct($sql) { $this->sql = $sql; }
                public function bind_param($types, ...$vars) { $this->bound_id = $vars[0]; }
                public function execute() { return true; }
                public function get_result() {
                    $mockData = [];
                    if (strpos($this->sql, 'SELECT id, project_name FROM projects WHERE client_id = ?') !== false) {
                        if ($this->bound_id == 1) {
                            $mockData = [['id' => 201, 'project_name' => 'Project Alpha'], ['id' => 202, 'project_name' => 'Project Beta']];
                        } elseif ($this->bound_id == 2) {
                            $mockData = [['id' => 203, 'project_name' => 'Project Gamma']];
                        }
                    }
                    return new MockResult($mockData);
                }
                public function close() {}
            };
        }
    }
    return new MockConnection();
}

$conn = getDatabaseConnection();
$clientId = $_GET['client_id'] ?? null;
$projects = [];

if ($clientId) {
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT id, project_name FROM projects WHERE client_id = ?");
        $stmt->bind_param("i", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $stmt->close();
    } else {
        // Mock data logic for development
        if ($clientId == 1) {
            $projects = [['id' => 201, 'project_name' => 'Project Alpha'], ['id' => 202, 'project_name' => 'Project Beta']];
        } elseif ($clientId == 2) {
            $projects = [['id' => 203, 'project_name' => 'Project Gamma']];
        }
    }
}
echo json_encode($projects);
?>