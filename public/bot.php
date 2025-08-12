<?php
// Simple Telegram Bot with Debug Info
const BOT_TOKEN = "8441678945:AAFmwSXzkBErmzQLmXkwzwtaDmIIvF05nP0";
const TELEGRAM_API = "https://api.telegram.org/bot" . BOT_TOKEN;

$lastUpdateId = 0;

// Test bot token first
function testBot() {
    $url = TELEGRAM_API . "/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TelegramBot/1.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "🔍 Testing bot connection...\n";
    echo "URL: $url\n";
    echo "HTTP Code: $httpCode\n";
    
    if ($error) {
        echo "❌ Connection Error: $error\n";
        echo "💡 Possible solutions:\n";
        echo "   - Check your internet connection\n";
        echo "   - Try using a VPN if Telegram is blocked\n";
        echo "   - Check if curl is working: curl -I https://api.telegram.org\n";
        return false;
    }
    
    if ($httpCode == 0) {
        echo "❌ Cannot reach Telegram API (HTTP Code 0)\n";
        echo "💡 This usually means:\n";
        echo "   - No internet connection\n";
        echo "   - Firewall blocking the connection\n";
        echo "   - DNS issues\n";
        echo "   - Telegram API might be blocked in your region\n";
        return false;
    }
    
    $result = json_decode($response, true);
    if ($result && $result['ok']) {
        echo "✅ Bot token is valid!\n";
        echo "Bot name: " . $result['result']['first_name'] . "\n";
        echo "Bot username: @" . $result['result']['username'] . "\n";
        return true;
    } else {
        echo "❌ Bot token is invalid or API error\n";
        echo "Response: " . $response . "\n";
        echo "💡 Check your bot token with @BotFather\n";
        return false;
    }
}

// Send message function with error handling
function sendMessage($chatId, $text) {
    $url = TELEGRAM_API . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TelegramBot/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "❌ Curl error: " . curl_error($ch) . "\n";
    }
    
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    echo "📤 Sending message... HTTP: $httpCode\n";
    
    if (!$result || !$result['ok']) {
        echo "❌ Failed to send message\n";
        echo "Response: " . $response . "\n";
        return false;
    } else {
        echo "✅ Message sent successfully!\n";
        return true;
    }
}

// Get updates function with error handling
function getUpdates($offset = 0) {
    $url = TELEGRAM_API . "/getUpdates?offset=" . $offset . "&timeout=30";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TelegramBot/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "❌ Curl error in getUpdates: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode != 200) {
        echo "❌ HTTP Error: $httpCode\n";
        echo "Response: " . $response . "\n";
        return false;
    }
    
    return $result;
}

// Clear any existing webhooks
function clearWebhook() {
    echo "🔄 Clearing any existing webhooks...\n";
    $url = TELEGRAM_API . "/setWebhook";
    $data = ['url' => ''];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($result && $result['ok']) {
        echo "✅ Webhook cleared\n";
    } else {
        echo "⚠️ Webhook clear failed or no webhook was set\n";
    }
}

echo "🚀 Starting Telegram Bot...\n";
echo str_repeat("-", 40) . "\n";

// Test the bot first
if (!testBot()) {
    echo "Cannot continue with invalid bot token.\n";
    exit(1);
}

// Clear webhooks
clearWebhook();

echo "\n🤖 Bot is now listening for messages...\n";
echo "💡 Send a message to your bot on Telegram to test!\n";
echo str_repeat("-", 40) . "\n";

$consecutiveErrors = 0;

while (true) {
    echo "📡 Checking for updates (offset: " . ($lastUpdateId + 1) . ")...\n";
    
    $updates = getUpdates($lastUpdateId + 1);
    
    if (!$updates) {
        $consecutiveErrors++;
        echo "❌ Failed to get updates. Consecutive errors: $consecutiveErrors\n";
        
        if ($consecutiveErrors >= 5) {
            echo "Too many consecutive errors. Exiting.\n";
            break;
        }
        
        sleep(5);
        continue;
    }
    
    $consecutiveErrors = 0; // Reset error counter
    
    if ($updates['ok'] && count($updates['result']) > 0) {
        echo "📥 Received " . count($updates['result']) . " update(s)\n";
        
        foreach ($updates['result'] as $update) {
            $lastUpdateId = $update['update_id'];
            echo "Processing update ID: $lastUpdateId\n";
            
            if (isset($update['message'])) {
                $message = $update['message'];
                $chatId = $message['chat']['id'];
                $text = $message['text'] ?? '';
                $firstName = $message['from']['first_name'] ?? 'User';
                
                echo "📨 Message from {$firstName} (Chat ID: {$chatId}): '{$text}'\n";
                
                // Simple reply logic
                if ($text == '/start') {
                    sendMessage($chatId, "Hello {$firstName}! I'm a simple bot. Send me any message and I'll reply!");
                } elseif (strpos(strtolower($text), 'hello') !== false) {
                    sendMessage($chatId, "Hello there, {$firstName}! 👋");
                } elseif (strpos(strtolower($text), 'how are you') !== false) {
                    sendMessage($chatId, "I'm doing great, thanks for asking! How are you?");
                } elseif (strpos(strtolower($text), 'bye') !== false) {
                    sendMessage($chatId, "Goodbye {$firstName}! Have a great day! 👋");
                } else {
                    sendMessage($chatId, "You said: " . $text);
                }
            } else {
                echo "⚠️ Update doesn't contain a message\n";
            }
        }
    } else {
        echo "💤 No new messages\n";
    }
    
    usleep(500000); // 0.5 second delay
}
?>