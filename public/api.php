<?php
// Simple Telegram Bot - Direct Run Script
const BOT_TOKEN = "8441678945:AAFmwSXzkBErmzQLmXkwzwtaDmIIvF05nP0";

// Database config - UPDATE THESE
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'zorqent';

function getDB() {
    global $host, $username, $password, $database;
    return new mysqli($host, $username, $password, $database);
}

function sendMessage($chatId, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId, 
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    file_get_contents($url, false, stream_context_create($options));
    echo "Sent: $text\n";
}

function sendWelcomeMessage($chatId) {
    $welcomeText = "🚀 <b>Welcome to Zorqent Technologies Official Bot!</b>\n\n";
    $welcomeText .= "📧 Please send your registered email address to verify your employment and access our internal communication system.\n\n";
    $welcomeText .= "🔒 <i>This bot is exclusively for Zorqent Technologies employees.</i>\n";
    $welcomeText .= "💡 Once verified, you'll receive important updates and announcements directly here.";
    
    sendMessage($chatId, $welcomeText);
}

function sendAlreadyRegisteredMessage($chatId, $employee) {
    $registeredMsg = "✅ <b>Already Registered!</b>\n\n";
    $registeredMsg .= "👋 <b>Welcome back, " . htmlspecialchars($employee['name']) . "!</b>\n\n";
    $registeredMsg .= "🎯 <b>Your Account Status:</b>\n";
    $registeredMsg .= "• <b>Status:</b> ✅ Active & Verified\n";
    $registeredMsg .= "• <b>Name:</b> " . htmlspecialchars($employee['name']) . "\n";
    $registeredMsg .= "• <b>Position:</b> " . htmlspecialchars($employee['role']) . "\n";
    $registeredMsg .= "• <b>Department:</b> Information and Technology\n";
    $registeredMsg .= "• <b>Email:</b> " . htmlspecialchars($employee['email']) . "\n\n";
    
    $registeredMsg .= "🏢 <b>Zorqent Technologies</b> - You have portal access\n\n";
    $registeredMsg .= "🔔 <b>Current Services Available:</b>\n";
    $registeredMsg .= "• ✅ Company updates and announcements\n";
    $registeredMsg .= "• ✅ Project notifications\n";
    $registeredMsg .= "• ✅ Emergency communications\n";
    $registeredMsg .= "• ✅ Team collaboration messages\n\n";
    
    $registeredMsg .= "💼 You're all set and connected to our internal communication system!\n";
    $registeredMsg .= "🚀 Stay tuned for important updates and announcements.";
    
    sendMessage($chatId, $registeredMsg);
}

function processMessage($chatId, $message, $firstName = '') {
    // Check if this is a /start command
    if (strtolower(trim($message)) === '/start') {
        sendWelcomeMessage($chatId);
        return;
    }
    
    $email = trim($message);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendMessage($chatId, "📩 Please send a valid email address to proceed with verification.");
        return;
    }
    
    $db = getDB();
    if ($db->connect_error) {
        sendMessage($chatId, "⚠️ <b>System Error</b>\nDatabase connection failed. Please try again in a few moments.");
        return;
    }
    
    // Check email exists and get employee details including chat_id
    $stmt = $db->prepare("SELECT id, full_name AS name, role, email, chat_id FROM team_members WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->num_rows) {
        $errorMsg = "❌ <b>Verification Failed</b>\n\n";
        $errorMsg .= "The email address <code>" . htmlspecialchars($email) . "</code> is not registered in our employee database.\n\n";
        $errorMsg .= "🏢 <i>This service is exclusively for Zorqent Technologies employees.</i>\n";
        $errorMsg .= "📞 If you believe this is an error, please contact HR or IT support.";
        
        sendMessage($chatId, $errorMsg);
        $db->close();
        return;
    }
    
    $employee = $result->fetch_assoc();
    
    // Check if user is already registered (has chat_id)
    if (!empty($employee['chat_id'])) {
        // User already registered - send already registered message
        sendAlreadyRegisteredMessage($chatId, $employee);
        
        // Log already registered attempt
        echo "Already registered user attempted re-registration: " . $employee['name'] . " (" . $email . ")\n";
        
        $db->close();
        return;
    }
    
    // New registration - Update chat ID
    $stmt = $db->prepare("UPDATE team_members SET chat_id = ? WHERE email = ?");
    $stmt->bind_param("is", $chatId, $email);
    
    if ($stmt->execute()) {
        // Send professional welcome message with employee details
        $welcomeMsg = "✅ <b>Registration Successful!</b>\n\n";
        $welcomeMsg .= "🎉 <b>Welcome, " . htmlspecialchars($employee['name']) . "!</b>\n\n";
        $welcomeMsg .= "👤 <b>Employee Details:</b>\n";
        $welcomeMsg .= "• <b>Name:</b> " . htmlspecialchars($employee['name']) . "\n";
        $welcomeMsg .= "• <b>Position:</b> " . htmlspecialchars($employee['role']) . "\n";
        $welcomeMsg .= "• <b>Department:</b> Information and Technology\n";
        $welcomeMsg .= "• <b>Email:</b> " . htmlspecialchars($email) . "\n\n";
        
        $welcomeMsg .= "🏢 <b>Zorqent Technologies</b> - Internal Communication System\n\n";
        $welcomeMsg .= "📱 <b>What's Next?</b>\n";
        $welcomeMsg .= "• You'll receive important company updates here\n";
        $welcomeMsg .= "• Project notifications and announcements\n";
        $welcomeMsg .= "• Emergency communications\n";
        $welcomeMsg .= "• Team collaboration messages\n\n";
        
        $welcomeMsg .= "🔔 <i>Stay connected and never miss important updates!</i>\n\n";
        $welcomeMsg .= "💼 Thank you for being a valuable part of the Zorqent Technologies family.\n";
        $welcomeMsg .= "🚀 Together, we're building the future of technology!";
        
        sendMessage($chatId, $welcomeMsg);
        
        // Log successful registration
        echo "New employee registered: " . $employee['name'] . " (" . $email . ")\n";
        
    } else {
        sendMessage($chatId, "⚠️ <b>Registration Error</b>\nSomething went wrong during registration. Please try again or contact IT support.");
    }
    
    $db->close();
}

// Main bot loop
echo "🤖 Zorqent Technologies Bot starting...\n";
echo "🔄 Listening for updates...\n";
$offset = 0;

while (true) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getUpdates?offset=" . ($offset + 1) . "&timeout=30";
    $response = json_decode(file_get_contents($url), true);
    
    if (!$response['ok']) {
        echo "❌ API Error: " . $response['description'] . "\n";
        sleep(5);
        continue;
    }
    
    foreach ($response['result'] as $update) {
        $offset = $update['update_id'];
        
        if (isset($update['message']['text'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'];
            $firstName = $update['message']['from']['first_name'] ?? '';
            
            echo "📨 Message from $chatId ($firstName): $text\n";
            processMessage($chatId, $text, $firstName);
        }
    }
    
    sleep(1);
}
?>