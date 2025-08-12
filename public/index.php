<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zorqnet Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #fafbfc;
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e1e8ed;
            padding: 1rem 0;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .system-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Status indicators */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid;
            transition: all 0.3s ease;
        }

        .status-online {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        .status-offline {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .status-connecting {
            background: #fffbeb;
            color: #d97706;
            border-color: #fed7aa;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .dot-online {
            background: #16a34a;
        }

        .dot-offline {
            background: #dc2626;
        }

        .dot-connecting {
            background: #d97706;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Network indicator */
        .network-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .signal-bars {
            display: flex;
            gap: 2px;
            align-items: end;
        }

        .signal-bar {
            width: 3px;
            background: #cbd5e1;
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

        /* DateTime display */
        .datetime-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Courier New', monospace;
        }

        .date-text {
            font-size: 0.8rem;
            color: #64748b;
        }

        .time-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            letter-spacing: 0.5px;
        }

        /* Main Content */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .portal-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            text-align: center;
            border: 1px solid #e1e8ed;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .portal-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .portal-subtitle {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .login-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }

        .login-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            margin-bottom: 1rem;
        }

        .login-button:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .forgot-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #3b82f6;
        }

        /* Features List */
        .features {
            margin-top: 2rem;
            text-align: left;
        }

        .features-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .feature-icon {
            color: #10b981;
            font-size: 0.9rem;
        }

        /* Footer */
        .footer {
            background: white;
            border-top: 1px solid #e1e8ed;
            padding: 1.5rem 0;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: #3b82f6;
        }

        .footer-text {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .system-info {
                flex-wrap: wrap;
                gap: 1rem;
                justify-content: center;
            }

            .main-container {
                padding: 1rem;
            }

            .portal-card {
                padding: 2rem;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo">Zorqnet</div>
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

    <!-- Main Content -->
    <main class="main-container">
        <div class="portal-card">
            <h1 class="portal-title">Portal Access</h1>
            <p class="portal-subtitle">Access the management system portal</p>
            
            <div class="login-section">
                <button class="login-button" id="loginBtn">
                    Login to Portal
                </button>
                <a href="#" class="forgot-link">Need access? Contact support</a>
            </div>

            <div class="features">
                <div class="features-title">What you get:</div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>Real-time dashboard</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>Team collaboration tools</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>Project management</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>Analytics & reporting</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="#">Help Center</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Support</a>
            </div>
            <div class="footer-text">
                © 2025 Zorqnet. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        let startTime = Date.now();
        let isOnline = navigator.onLine;

        // Update date and time - Live every second
        function updateDateTime() {
            const now = new Date();
            
            // Format options for better display
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
                hour12: false // 24-hour format
            };
            
            // Update the display elements
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
                
                // Test multiple endpoints for more accurate results
                const testUrls = [
                    'https://www.google.com/favicon.ico',
                    'https://www.cloudflare.com/favicon.ico',
                    'https://httpbin.org/get'
                ];
                
                // Use a random URL to avoid caching
                const testUrl = testUrls[Math.floor(Math.random() * testUrls.length)];
                
                const response = await fetch(testUrl, {
                    method: 'HEAD',
                    mode: 'no-cors',
                    cache: 'no-cache'
                });
                
                const endTime = performance.now();
                lastPingTime = Math.round(endTime - startTime);
                
                // Determine connection quality based on ping
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
                // Fallback ping test using image load
                try {
                    const startTime = performance.now();
                    const img = new Image();
                    
                    await new Promise((resolve, reject) => {
                        img.onload = resolve;
                        img.onerror = reject;
                        img.src = 'https://www.google.com/favicon.ico?' + Date.now();
                    });
                    
                    const endTime = performance.now();
                    lastPingTime = Math.round(endTime - startTime);
                    
                    if (lastPingTime < 100) {
                        connectionQuality = 'good';
                    } else if (lastPingTime < 300) {
                        connectionQuality = 'fair';
                    } else {
                        connectionQuality = 'poor';
                    }
                } catch (imgError) {
                    connectionQuality = 'offline';
                }
            }
        }

        // Update network status based on real connectivity
        async function updateNetworkStatus() {
            const statusBadge = document.getElementById('systemStatus');
            const statusText = document.getElementById('statusText');
            const statusDot = document.getElementById('statusDot');
            const signalBars = document.getElementById('signalBars');
            const networkStatus = document.getElementById('networkStatus');
            
            if (!statusBadge || !statusText || !statusDot || !signalBars || !networkStatus) {
                return; // Elements don't exist
            }
            
            // Check if browser reports online
            if (!navigator.onLine) {
                statusBadge.className = 'status-badge status-offline';
                statusText.textContent = 'System Offline';
                statusDot.className = 'status-dot dot-offline';
                signalBars.className = 'signal-bars';
                networkStatus.textContent = 'Offline';
                return;
            }
            
            // Test actual connectivity
            try {
                // Set connecting state
                statusBadge.className = 'status-badge status-connecting';
                statusText.textContent = 'Testing...';
                statusDot.className = 'status-dot dot-connecting';
                
                // Perform real network test
                await testRealPing();
                
                // Update status based on real test results
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
                    
                    // Update signal bars based on real connection quality
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

        // Login button functionality
        document.getElementById('loginBtn').addEventListener('click', () => {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.textContent = 'Redirecting...';
            loginBtn.disabled = true;
            
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 500);
        });

        // Portal card hover effects
        const portalCard = document.querySelector('.portal-card');
        if (portalCard) {
            portalCard.addEventListener('mouseenter', () => {
                portalCard.style.transform = 'translateY(-2px)';
                portalCard.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            });

            portalCard.addEventListener('mouseleave', () => {
                portalCard.style.transform = 'translateY(0)';
                portalCard.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
            });
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            const loginBtn = document.getElementById('loginBtn');
            if (e.key === 'Enter' && loginBtn && !loginBtn.disabled) {
                loginBtn.click();
            }
        });

        // Network status change listeners
        window.addEventListener('online', () => {
            isOnline = true;
            setTimeout(updateNetworkStatus, 500); // Small delay to ensure connection is stable
        });

        window.addEventListener('offline', () => {
            isOnline = false;
            updateNetworkStatus();
        });

        // Connection quality monitoring
        let connectionMonitor = {
            failedTests: 0,
            successfulTests: 0,
            
            updateQuality() {
                const totalTests = this.failedTests + this.successfulTests;
                if (totalTests > 0) {
                    const successRate = this.successfulTests / totalTests;
                    if (successRate < 0.5) {
                        connectionQuality = 'poor';
                    } else if (successRate < 0.8) {
                        connectionQuality = 'fair';
                    }
                }
            }
        };

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', () => {
            updateDateTime(); // Initial update
            updateNetworkStatus();
        });

        // Set up live time updates - updates every second
        setInterval(updateDateTime, 1000);
        
        // Network monitoring intervals
        setInterval(updateNetworkStatus, 5000); // Test real network every 8 seconds
        
        // Quick network quality check every 15 seconds
        setInterval(() => {
            if (navigator.onLine) {
                testRealPing().then(() => {
                    connectionMonitor.successfulTests++;
                }).catch(() => {
                    connectionMonitor.failedTests++;
                    connectionMonitor.updateQuality();
                });
            }
        }, 5000);

        // Start everything immediately
        updateDateTime();
        updateNetworkStatus();
    </script>
</body>
</html>