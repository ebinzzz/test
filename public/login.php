<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Input validation
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $debug_info .= "Input validation passed. Username: '" . htmlspecialchars($username) . "'<br>";

        if (!$conn) {
            $error = "Database connection failed. Please try again later.";
            $debug_info .= "Database connection failed - conn is null<br>";
        } else {
            $debug_info .= "Database connection successful<br>";
            
            // Prepare statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT id, username, password, role, first_name, last_name, email, is_active, failed_attempts, last_failed_attempt 
                                    FROM users 
                                    WHERE username = ? OR email = ? ");
            
            if ($stmt) {
                $debug_info .= "Statement prepared successfully<br>";
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();

                $debug_info .= "Query executed. Rows returned: " . $result->num_rows . "<br>";

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if account is locked due to too many failed attempts
                    $max_attempts = 5;
                    $lockout_time = 15 * 60; // 15 minutes
                    $current_time = time();
                    
                    if ($user['failed_attempts'] >= $max_attempts) {
                        $last_failed = strtotime($user['last_failed_attempt']);
                        if ($current_time - $last_failed < $lockout_time) {
                            $remaining = $lockout_time - ($current_time - $last_failed);
                            $minutes = ceil($remaining / 60);
                            $error = "Account temporarily locked due to too many failed attempts. Try again in $minutes minutes.";
                            $stmt->close();
                            return;
                        }
                    }
                    
                    // Check if account is active
                    if ((int)$user['is_active'] !== 1) {
                        $error = "Your account is inactive. Please contact support.";
                    } else {
                        // Verify password using password_verify() for hashed passwords
                        // If you're still using plain text (NOT RECOMMENDED), use the else block
                        if (password_verify($password, $user['password'])) {
                            // Successful login - reset failed attempts
                            $reset_stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_failed_attempt = NULL WHERE id = ?");
                            $reset_stmt->bind_param("i", $user['id']);
                            $reset_stmt->execute();
                            $reset_stmt->close();
                            
                            // Set session variables
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                            $_SESSION['email'] = $user['email'];
                            
                            // Log successful login
                            error_log("Successful login for user: " . $user['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);

                            $success = "Login successful! Redirecting...";

                            // Redirect based on user role
                            switch ($user['role']) {
                                case 'admin':
                                    header("Location: dashboard.php");
                                    break;
                                case 'team_member':
                                    header("Location: onboarding/onboarding_dashboard.php");
                                    break;
                                default:
                                    $error = "Invalid user role. Please contact support.";
                                    session_destroy();
                                    break;
                            }
                            
                            if (!$error) {
                                exit();
                            }
                            
                        } else {
                            // Invalid password - increment failed attempts
                            $failed_attempts = $user['failed_attempts'] + 1;
                            $update_stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, last_failed_attempt = NOW() WHERE id = ?");
                            $update_stmt->bind_param("ii", $failed_attempts, $user['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            // Log failed login attempt
                            error_log("Failed login attempt for user: " . $user['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
                            
                            $remaining_attempts = $max_attempts - $failed_attempts;
                            if ($remaining_attempts > 0) {
                                $error = "Invalid username/email or password. $remaining_attempts attempts remaining before account lockout.";
                            } else {
                                $error = "Invalid username/email or password. Account has been temporarily locked.";
                            }
                        }
                    }
                } else {
                    // User not found - still show generic error to prevent username enumeration
                    $error = "Invalid username/email or password.";
                    // Log the attempt
                    error_log("Login attempt with non-existent username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
                }
                $stmt->close();
            } else {
                $error = "Database error. Please try again later.";
                $debug_info .= "Failed to prepare statement. Error: " . htmlspecialchars($conn->error) . "<br>";
                error_log("Database error in login: " . $conn->error);
            }
        }
    }
}

// Function to validate password strength (for registration/password changes)
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

// Rate limiting function
function checkRateLimit($ip) {
    $max_attempts = 10;
    $time_window = 15 * 60; // 15 minutes
    $current_time = time();
    
    // This would typically use a database table or cache like Redis
    // For now, using session as a simple example
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $attempts = $_SESSION['rate_limit'][$ip] ?? [];
    
    // Remove old attempts outside the time window
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    if (count($attempts) >= $max_attempts) {
        return false; // Rate limit exceeded
    }
    
    // Add current attempt
    $attempts[] = $current_time;
    $_SESSION['rate_limit'][$ip] = $attempts;
    
    return true;
}

// Check rate limit
$client_ip = $_SERVER['REMOTE_ADDR'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !checkRateLimit($client_ip)) {
    $error = "Too many login attempts. Please wait 15 minutes before trying again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Zorqnet Technology</title>
<style>
/* Your existing CSS exactly as before */
:root {
    --primary-color: #3b82f6;
    --primary-dark: #2563eb;
    --primary-light: #60a5fa;
    --background: #0f172a;
    --surface: #1e293b;
    --surface-elevated: #334155;
    --text-primary: #f8fafc;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --border: #334155;
    --border-light: #475569;
    --error: #f87171;
    --error-light: #1f2937;
    --error-dark: #7f1d1d;
    --success: #10b981;
    --success-light: #064e3b;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
    --radius: 8px;
    --radius-lg: 12px;
}

*{box-sizing:border-box;margin:0;padding:0}

body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;
    background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#334155 100%);
    min-height:100vh;display:flex;flex-direction:column;padding:0;line-height:1.6;
}

/* System Status Header */
.system-header {
    background: rgba(30, 41, 59, 0.9);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border);
    padding: 1rem 0;
}

.system-header-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.system-logo {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
}

.system-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Status indicators - Dark theme */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid;
    transition: all 0.3s ease;
}

.status-online {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
}

.status-offline {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

.status-connecting {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.3);
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.dot-online {
    background: #10b981;
}

.dot-offline {
    background: #ef4444;
}

.dot-connecting {
    background: #f59e0b;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Network indicator - Dark theme */
.network-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.signal-bars {
    display: flex;
    gap: 2px;
    align-items: end;
}

.signal-bar {
    width: 3px;
    background: rgba(203, 213, 225, 0.3);
    border-radius: 1px;
    transition: background-color 0.3s ease;
}

.signal-bar:nth-child(1) { height: 4px; }
.signal-bar:nth-child(2) { height: 6px; }
.signal-bar:nth-child(3) { height: 8px; }
.signal-bar:nth-child(4) { height: 10px; }

.signal-excellent .signal-bar { background: #10b981; }
.signal-good .signal-bar:nth-child(-n+3) { background: #f59e0b; }
.signal-poor .signal-bar:nth-child(-n+2) { background: #ef4444; }
.signal-weak .signal-bar:nth-child(1) { background: #ef4444; }

/* DateTime display - Dark theme */
.datetime-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Courier New', monospace;
}

.date-text {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.time-text {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    letter-spacing: 0.5px;
}

/* Main content area */
.main-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-container{
    background:var(--surface);
    border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xl);
    width:100%;
    max-width:420px;
    overflow:hidden;
}

.login-header{
    background:linear-gradient(135deg,#1e293b 0%,#334155 100%);
    color:var(--text-primary);
    padding:32px 32px 24px;
    text-align:center;
    position:relative;
    border-bottom:1px solid var(--border);
}

.login-header::before{
    content:'';
    position:absolute;
    top:0;left:0;right:0;bottom:0;
    background:linear-gradient(135deg,rgba(59,130,246,0.1) 0%,rgba(99,102,241,0.05) 100%);
    pointer-events:none;
}

.company-logo{
    font-size:28px;
    font-weight:700;
    margin-bottom:8px;
    letter-spacing:-0.025em;
    position:relative;
    z-index:1;
}

.login-subtitle{
    font-size:14px;
    opacity:0.9;
    font-weight:400;
    position:relative;
    z-index:1;
}

.login-form{padding:32px;}

.form-group{margin-bottom:24px;}

.form-label{
    display:block;
    font-size:14px;
    font-weight:600;
    color:var(--text-primary);
    margin-bottom:8px;
}

.form-input{
    width:100%;
    padding:12px 16px;
    border:2px solid var(--border);
    border-radius:var(--radius);
    font-size:16px;
    color:var(--text-primary);
    background:var(--surface-elevated);
    transition:all 0.2s ease;
    outline:none;
}

.form-input:focus{
    border-color:var(--primary-color);
    box-shadow:0 0 0 3px rgba(59,130,246,0.2);
    background:var(--surface);
}

.form-input:hover{
    border-color:var(--border-light);
    background:var(--surface);
}

.form-input::placeholder{color:var(--text-muted);}

.form-input.invalid {
    border-color:var(--error);
    box-shadow:0 0 0 3px rgba(248,113,113,0.2);
}

.login-button{
    width:100%;
    padding:12px 24px;
    background:var(--primary-color);
    color:white;
    border:none;
    border-radius:var(--radius);
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.2s ease;
    outline:none;
}

.login-button:hover{
    background:var(--primary-dark);
    transform:translateY(-1px);
    box-shadow:var(--shadow-lg);
}

.login-button:active{
    transform:translateY(0);
    box-shadow:var(--shadow-sm);
}

.login-button:disabled{
    background:var(--text-muted);
    cursor:not-allowed;
    transform:none;
    box-shadow:none;
}

.message{
    padding:12px 16px;
    border-radius:var(--radius);
    margin-bottom:24px;
    font-size:14px;
    font-weight:500;
    display:flex;
    align-items:center;
}

.error-message{
    background:var(--error-light);
    color:var(--error);
    border:1px solid var(--error-dark);
    border-left:4px solid var(--error);
}

.error-message::before{content:'⚠';margin-right:8px;font-size:16px;}

.success-message{
    background:var(--success-light);
    color:var(--success);
    border:1px solid var(--success);
    border-left:4px solid var(--success);
}

.success-message::before{content:'✓';margin-right:8px;font-size:16px;}

.login-footer{
    padding:24px 32px 32px;
    text-align:center;
    border-top:1px solid var(--border);
    background:var(--surface-elevated);
}

.footer-text{
    font-size:12px;
    color:var(--text-muted);
    margin-bottom:8px;
}

.password-requirements {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
    padding: 8px;
    background: rgba(59,130,246,0.05);
    border-radius: 4px;
    border-left: 2px solid var(--primary-color);
}

.requirement {
    display: flex;
    align-items: center;
    margin: 2px 0;
}

.requirement::before {
    content: '•';
    margin-right: 6px;
    color: var(--primary-color);
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .system-header-container {
        padding: 0 1rem;
        flex-direction: column;
        gap: 1rem;
    }

    .system-info {
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
    }

    .main-content {
        padding: 1rem;
    }
}
</style>
</head>
<body>
<!-- System Status Header -->
<header class="system-header">
    <div class="system-header-container">
        <div class="system-logo">Zorqnet Systems</div>
        <div class="system-info">
            <div class="info-item">
                <div class="status-badge status-online" id="systemStatus">
                    <span class="status-dot dot-online" id="statusDot"></span>
                    <span id="statusText">System Online</span>
                </div>
            </div>
            
            <div class="info-item">
                <div class="network-indicator">
                    <div class="signal-bars signal-excellent" id="signalBars">
                        <div class="signal-bar"></div>
                        <div class="signal-bar"></div>
                        <div class="signal-bar"></div>
                        <div class="signal-bar"></div>
                    </div>
                    <span id="networkStatus">Connected</span>
                </div>
            </div>
            
            <div class="info-item">
                <div class="datetime-display">
                    <div class="date-text" id="currentDate">Loading...</div>
                    <div class="time-text" id="currentTime">00:00:00</div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Main Login Content -->
<div class="main-content">
    <div class="login-container">
        <div class="login-header">
            <div class="company-logo">Zorqnet Technology</div>
            <div class="login-subtitle">Secure Portal Access</div>
        </div>

        <form method="POST" class="login-form" id="loginForm">
            <?php if ($error): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <input type="text" id="username" name="username" class="form-input"
                    placeholder="Enter your username or email" required autocomplete="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                    placeholder="Enter your password" required autocomplete="current-password">
                <div class="password-requirements" id="passwordHelp" style="display: none;">
                    <div class="requirement">Password must be at least 6 characters</div>
                    <div class="requirement">Use a mix of letters, numbers, and symbols for better security</div>
                </div>
            </div>

            <button type="submit" class="login-button" id="loginBtn">Sign In to Portal</button>
        </form>

        <div class="login-footer">
            <div class="footer-text">&copy; 2025 Zorqnet Technology. All rights reserved.</div>
            <?php if (isset($_GET['debug']) && $debug_info): ?>
                <div style="margin-top: 10px; padding: 10px; background: rgba(255,0,0,0.1); border: 1px solid #ff0000; border-radius: 4px; font-size: 11px; text-align: left;">
                    <strong>Debug Info:</strong><br>
                    <?php echo $debug_info; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Enhanced JavaScript for better user experience and security
let startTime = Date.now();
let isOnline = navigator.onLine;
let loginAttempts = 0;
const maxAttempts = 5;

// Client-side password validation
function validatePassword(password) {
    const errors = [];
    
    if (password.length < 6) {
        errors.push('Password must be at least 6 characters long');
    }
    
    return errors;
}

// Real-time password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const passwordHelp = document.getElementById('passwordHelp');
    const errors = validatePassword(password);
    
    if (password.length > 0 && errors.length > 0) {
        this.classList.add('invalid');
        passwordHelp.style.display = 'block';
    } else {
        this.classList.remove('invalid');
        passwordHelp.style.display = 'none';
    }
});

// Enhanced form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const loginBtn = document.getElementById('loginBtn');
    
    // Basic client-side validation
    if (!username || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
    }
    
    // Disable button to prevent double submission
    loginBtn.disabled = true;
    loginBtn.textContent = 'Signing In...';
    
    // Re-enable button after 5 seconds (in case of error)
    setTimeout(() => {
        loginBtn.disabled = false;
        loginBtn.textContent = 'Sign In to Portal';
    }, 5000);
});

// Auto-focus management
document.getElementById('username').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('password').focus();
    }
});

// Update date and time - Live every second
function updateDateTime() {
    const now = new Date();
    
    const dateOptions = { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
    };
    
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: false
    };
    
    const dateElement = document.getElementById('currentDate');
    const timeElement = document.getElementById('currentTime');
    
    if (dateElement && timeElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
        timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
    }
}

// Real network ping test
let lastPingTime = 0;
let connectionQuality = 'unknown';

async function testRealPing() {
    try {
        const startTime = performance.now();
        
        const testUrls = [
            'https://www.google.com/favicon.ico',
            'https://www.cloudflare.com/favicon.ico'
        ];
        
        const testUrl = testUrls[Math.floor(Math.random() * testUrls.length)];
        
        await fetch(testUrl, {
            method: 'HEAD',
            mode: 'no-cors',
            cache: 'no-cache'
        });
        
        const endTime = performance.now();
        lastPingTime = Math.round(endTime - startTime);
        
        if (lastPingTime < 50) {
            connectionQuality = 'excellent';
        } else if (lastPingTime < 150) {
            connectionQuality = 'good';
        } else if (lastPingTime < 300) {
            connectionQuality = 'fair';
        } else {
            connectionQuality = 'poor';
        }
        
    } catch (error) {
        connectionQuality = 'offline';
    }
}

// Update network status
async function updateNetworkStatus() {
    const statusBadge = document.getElementById('systemStatus');
    const statusText = document.getElementById('statusText');
    const statusDot = document.getElementById('statusDot');
    const signalBars = document.getElementById('signalBars');
    const networkStatus = document.getElementById('networkStatus');
    
    if (!statusBadge || !statusText || !statusDot || !signalBars || !networkStatus) {
        return;
    }
    
    if (!navigator.onLine) {
        statusBadge.className = 'status-badge status-offline';
        statusText.textContent = 'System Offline';
        statusDot.className = 'status-dot dot-offline';
        signalBars.className = 'signal-bars';
        networkStatus.textContent = 'Offline';
        return;
    }
    
    try {
        statusBadge.className = 'status-badge status-connecting';
        statusText.textContent = 'Testing...';
        statusDot.className = 'status-dot dot-connecting';
        
        await testRealPing();
        
        if (connectionQuality === 'offline') {
            statusBadge.className = 'status-badge status-offline';
            statusText.textContent = 'Connection Failed';
            statusDot.className = 'status-dot dot-offline';
            signalBars.className = 'signal-bars';
            networkStatus.textContent = 'Failed';
        } else {
            statusBadge.className = 'status-badge status-online';
            statusText.textContent = 'System Online';
            statusDot.className = 'status-dot dot-online';
            
            switch (connectionQuality) {
                case 'excellent':
                    signalBars.className = 'signal-bars signal-excellent';
                    networkStatus.textContent = 'Excellent';
                    break;
                case 'good':
                    signalBars.className = 'signal-bars signal-good';
                    networkStatus.textContent = 'Good';
                    break;
                case 'fair':
                    signalBars.className = 'signal-bars signal-poor';
                    networkStatus.textContent = 'Fair';
                    break;
                case 'poor':
                    signalBars.className = 'signal-bars signal-weak';
                    networkStatus.textContent = 'Poor';
                    break;
                default:
                    signalBars.className = 'signal-bars signal-good';
                    networkStatus.textContent = 'Connected';
            }
        }
        
    } catch (error) {
        statusBadge.className = 'status-badge status-offline';
        statusText.textContent = 'Connection Error';
        statusDot.className = 'status-dot dot-offline';
        signalBars.className = 'signal-bars';
        networkStatus.textContent = 'Error';
    }
}

// Network status change listeners
window.addEventListener('online', () => {
    isOnline = true;
    setTimeout(updateNetworkStatus, 500);
});

window.addEventListener('offline', () => {
    isOnline = false;
    updateNetworkStatus();
});

// Security: Clear sensitive data on page unload
window.addEventListener('beforeunload', function() {
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.value = '';
    }
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', () => {
    updateDateTime();
    updateNetworkStatus();
    
    // Focus on appropriate field
    const usernameField = document.getElementById('username');
    const passwordField = document.getElementById('password');
    
    if (usernameField && !usernameField.value) {
        usernameField.focus();
    } else if (passwordField) {
        passwordField.focus();
    }
    
    // Clear password field on load for security
    if (passwordField) {
        passwordField.value = '';
    }
});

// Set up live time updates
setInterval(updateDateTime, 1000);

// Network monitoring intervals
setInterval(updateNetworkStatus, 8000);

// Start everything immediately
updateDateTime();
updateNetworkStatus();
</script>   
</body>
</html>