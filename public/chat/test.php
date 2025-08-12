<?php
// Load configuration
require_once '../config.php';

// Connect to Database
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Telegram API endpoint
$apiUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/getUpdates";

// Get updates
$response = file_get_contents($apiUrl);
if ($response === false) {
    die("Failed to connect to Telegram API.\n");
}

$data = json_decode($response, true);
if (!isset($data['ok']) || $data['ok'] !== true) {
    die("Telegram API returned error: " . json_encode($data) . "\n");
}

if (empty($data['result'])) {
    die("No messages found. Send an email address to the bot, then run this script.\n");
}

$lastUpdateId = null;
$latestMessages = []; // store last message per chat_id

// Step 1: Gather only last message for each chat_id
foreach ($data['result'] as $update) {
    $lastUpdateId = $update['update_id'];
    if (isset($update['message'])) {
        $chatId  = $update['message']['chat']['id'];
        $message = trim($update['message']['text'] ?? '');
        $latestMessages[$chatId] = [
            'update_id' => $update['update_id'],
            'chat_id'   => $chatId,
            'message'   => $message
        ];
    }
}

// Step 2: Process only the last message per chat
foreach ($latestMessages as $msgData) {
    $chatId  = $msgData['chat_id'];
    $message = $msgData['message'];

    if (filter_var($message, FILTER_VALIDATE_EMAIL)) {
        $email = $conn->real_escape_string($message);

        // Check if email exists
        $sql = "SELECT id FROM team_members WHERE email = '$email' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $updateSql = "UPDATE team_members SET chat_id = '$chatId' WHERE email = '$email'";
            if ($conn->query($updateSql) === TRUE) {
                sendTelegramMessage($BOT_TOKEN, $chatId, "✅ Chat registration success!");
            } else {
                sendTelegramMessage($BOT_TOKEN, $chatId, "⚠ Database error while saving chat ID.");
            }
        } else {
            sendTelegramMessage($BOT_TOKEN, $chatId, "❌ Not an employee of Zorqnet Technology.");
        }
    } else {
        sendTelegramMessage($BOT_TOKEN, $chatId, "📩 Please send a valid email address to register.");
    }
}

// Step 3: Mark all updates as processed
if ($lastUpdateId !== null) {
    file_get_contents($apiUrl . "?offset=" . ($lastUpdateId + 1));
}

$conn->close();

/**
 * Send message to Telegram chat
 */
function sendTelegramMessage($token, $chatId, $text) {
    $sendUrl = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text'    => $text
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    file_get_contents($sendUrl, false, stream_context_create($options));
}
