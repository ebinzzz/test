<?php
// Include necessary files and database connection
@include_once '../config.php';
// ... (Database connection and client/project fetching logic from manage.php) ...

$clients = []; // Fetch clients from DB
$projects = []; // Fetch projects from DB

// Handle form submission for client mail here
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... logic to call send_mail.php or process the mail directly ...
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Client Mail Management</title>
    </head>
<body>
    <div class="main-content">
        <header class="header">
            <h1>Client Mail Management</h1>
        </header>
        <main class="content">
            <div class="mail-card">
                <h3>Send Client Mail</h3>
                <form action="client_mail.php" method="POST" class="mail-form">
                    <button type="submit" class="btn btn-primary">Send Email</button>
                </form>
            </div>
            <div class="mail-history">
                <h3>Sent Client Mails</h3>
                </div>
        </main>
    </div>
</body>
</html>