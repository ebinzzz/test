<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot Monitor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .controls {
            margin: 20px 0;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .log {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 14px;
            margin-top: 20px;
        }
        .timestamp {
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    
    <script>
        let reloadInterval;
        let countdownInterval;
        let isRunning = false;
        let countdown = 5;

        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.innerHTML = `<span class="timestamp">[${timestamp}]</span> ${message}`;
            
            if (type === 'error') {
                logEntry.style.color = '#dc3545';
            } else if (type === 'success') {
                logEntry.style.color = '#28a745';
            }
            
            logDiv.appendChild(logEntry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function updateStatus(message, type = 'info') {
            const statusDiv = document.getElementById('status');
            statusDiv.textContent = message;
            statusDiv.className = `status ${type}`;
        }

        function updateCountdown() {
            const countdownSpan = document.getElementById('countdown');
            if (countdownSpan) {
                countdownSpan.textContent = countdown;
            }
            
            if (countdown > 0) {
                countdown--;
            } else {
                countdown = 5; // Reset countdown
            }
        }

        async function executeBot() {
            try {
              
                
                const response = await fetch('test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                });
                
                if (response.ok) {
                    const result = await response.text();
                 
                    
                    // Log any output from PHP script
                    if (result.trim()) {
                        log(`PHP Output: ${result.trim()}`);
                    }
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            } catch (error) {
                
            }
        }

        function startAutoReload() {
            if (isRunning) return;
            
            isRunning = true;
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            
            log('Auto-reload started (5 second intervals)', 'success');
            updateStatus(`Bot is running... Next check in ${countdown} seconds`, 'success');
            
            // Execute immediately
            executeBot();
            
            // Set up intervals
            reloadInterval = setInterval(executeBot, 5000);
            countdownInterval = setInterval(updateCountdown, 1000);
        }

        function stopAutoReload() {
            if (!isRunning) return;
            
            isRunning = false;
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            
            if (reloadInterval) {
                clearInterval(reloadInterval);
                reloadInterval = null;
            }
            
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            countdown = 5;
            log('Auto-reload stopped', 'info');
            updateStatus('Bot monitoring stopped', 'info');
        }

        function manualReload() {
            log('Manual reload triggered');
            executeBot();
        }

        function clearLog() {
            document.getElementById('log').innerHTML = '';
            log('Log cleared');
        }

        // Auto-start when page loads
        window.addEventListener('load', function() {
            log('Page loaded, starting auto-reload...');
            setTimeout(startAutoReload, 1000);
        });

        // Stop when page unloads
        window.addEventListener('beforeunload', stopAutoReload);
    </script>
</body>
</html>