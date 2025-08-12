<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
@include_once '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['email'];
    
// Fetch user data from the database
$sql = "SELECT id,full_name, role, image FROM team_members WHERE email= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $id = htmlspecialchars($user['id']);
    $full_name = htmlspecialchars($user['full_name']);
    $role = htmlspecialchars($user['role']);
    $profile_image = htmlspecialchars($user['image'] ?? 'default_user.png');
} else {
    // Fallback if user ID is not found
    $id = 0;
    $full_name = "Guest";
    $role = "N/A";
    $profile_image = "default_user.png";
}

// Get notification count - tasks assigned to this user where view = false
$notification_count = 0;
if ($id > 0) {
    $notification_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND view = 'false'";
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->bind_param("i", $id);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();

    if ($notification_result->num_rows > 0) {
        $notification_data = $notification_result->fetch_assoc();
        $notification_count = $notification_data['count'];
    }
    $notification_stmt->close();
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zorqent Team Portal</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    :root {
        --primary-color: #2563eb;
        --primary-light: #3b82f6;
        --primary-dark: #1d4ed8;
        --accent-color: #10b981;
        --accent-light: #34d399;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        
        --bg-primary: #ffffff;
        --bg-secondary: #f8fafc;
        --bg-tertiary: #f1f5f9;
        --sidebar-bg: #1e293b;
        --sidebar-hover: #334155;
        
        --text-primary: #0f172a;
        --text-secondary: #64748b;
        --text-light: #94a3b8;
        --text-white: #ffffff;
        
        --border-color: #e2e8f0;
        --border-light: #f1f5f9;
        
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --radius-2xl: 1.5rem;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        line-height: 1.6;
    }

    /* Sidebar Styles */
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0f172a 100%);
        color: var(--text-white);
        padding: 0;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        z-index: 1000;
    }

    .sidebar-header {
        padding: 30px 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
    }

    .company-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-bottom: 25px;
    }

    .logo-icon {
        width: 45px;
        height: 45px;
        background: white;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
        color: white;
        box-shadow: var(--shadow-md);
    }

    .logo-text {
        font-size: 1.8em;
        font-weight: 700;
        background: linear-gradient(135deg, #ffffff, #e2e8f0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        backdrop-filter: blur(10px);
    }

    .profile-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary-light);
        box-shadow: var(--shadow-md);
    }

    .profile-info h4 {
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 2px;
        color: var(--text-white);
    }

    .profile-info p {
        font-size: 0.9em;
        color: var(--text-light);
        margin: 0;
    }

    /* Navigation Styles */
    .sidebar-nav {
        padding: 25px 0;
    }

    .nav-section {
        margin-bottom: 30px;
    }

    .nav-section-title {
        font-size: 0.75em;
        font-weight: 600;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0 25px;
        margin-bottom: 12px;
    }

    .nav-menu {
        list-style: none;
        padding: 0;
    }

    .nav-item {
        margin-bottom: 4px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 25px;
        color: var(--text-light);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
        position: relative;
    }

    .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--primary-color);
        transform: scaleY(0);
        transition: transform 0.2s ease;
    }

    .nav-link:hover,
    .nav-link.active {
        background: var(--sidebar-hover);
        color: var(--text-white);
        transform: translateX(8px);
    }

    .nav-link.active::before {
        transform: scaleY(1);
    }

    .nav-icon {
        width: 20px;
        text-align: center;
        font-size: 1.1em;
    }

    /* Main Content Styles */
    .main-content {
        margin-left: 280px;
        min-height: 100vh;
        background: var(--bg-secondary);
    }

    .top-header {
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border-color);
        padding: 20px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .page-title {
        font-size: 1.75em;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .notification-btn {
        position: relative;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--text-secondary);
    }

    .notification-btn:hover {
        background: var(--primary-color);
        color: var(--text-white);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .content-wrapper {
        padding: 30px;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .sidebar {
            width: 250px;
        }
        
        .main-content {
            margin-left: 250px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .mobile-toggle {
            display: block;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px;
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        
        .content-wrapper {
            padding: 20px;
        }
    }

    /* Utility Classes */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.95em;
    }

    .btn-primary {
        background: var(--primary-color);
        color: var(--text-white);
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .card {
        background: var(--bg-primary);
        border-radius: var(--radius-xl);
        padding: 25px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-light);
    }

    .text-muted {
        color: var(--text-secondary);
    }

    .text-center {
        text-align: center;
    }

    .mb-4 {
        margin-bottom: 1.5rem;
    }

    .mt-4 {
        margin-top: 1.5rem;
    }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="company-logo">
            <div class="logo-icon">
              <img src="../assets/logo.png" alt="logo" class="logo-icon">
            </div>
            <div class="logo-text">Zorqent</div>
        </div>
        
        <div class="user-profile">
            <img src="../<?php echo $profile_image; ?>" 
                 alt="Profile" 
                 class="profile-avatar"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjUiIGN5PSIyNSIgcj0iMjUiIGZpbGw9IiNlMmU4ZjAiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4PSIxMyIgeT0iMTMiPgo8cGF0aCBkPSJNMTIgMTJDMTQuNzYxNCAxMiAxNyA5Ljc2MTQyIDE3IDdDMTcgNC4yMzg1OCAxNC43NjE0IDIgMTIgMkM5LjIzODU4IDIgNyA0LjIzODU4IDcgN0M3IDkuNzYxNDIgOS4yMzg1OCAxMiAxMiAxMloiIGZpbGw9IiM2NDc0OGIiLz4KPHBhdGggZD0iTTEyIDE0QzguNjg2MjkgMTQgNiAxNi42ODYzIDYgMjBIMThDMTggMTYuNjg2MyAxNS4zMTM3IDE0IDEyIDE0WiIgZmlsbD0iIzY0NzQ4YiIvPgo8L3N2Zz4KPC9zdmc+'">
            <div class="profile-info">
                <h4><?php echo $full_name; ?></h4>
                <p><?php echo $role; ?></p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Onboarding</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="onboarding_dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'onboarding_dashboard.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="onboarding_paperwork.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'onboarding_paperwork.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <span>Paperwork</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="digital_id_card.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'digital_id_card.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-id-card"></i>
                        <span>Digital ID Card</span>
                    </a>
                </li>
                  <li class="nav-item">
                    <a href="update_assign_status.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'update_assign_status.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-list"></i>
                        <span>To Do</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-question-circle"></i>
                        <span>Help & Support</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
       
</aside>

<div class="main-content">
    <header class="top-header">
        <div>
            <button class="mobile-toggle" style="display: none;" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Welcome to Zorqent</h1>
        </div>
        
        <div class="header-actions">
            <button class="notification-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($notification_count > 0): ?>
                    <span style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7em; font-weight: 600; display: flex; align-items: center; justify-content: center; border: 2px solid white;"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </button>
            <button class="notification-btn" title="Messages">
                <i class="fas fa-envelope"></i>
            </button>
        </div>
    </header>
    
    <div class="content-wrapper">
        <!-- Your existing content goes here -->

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('mobile-open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-toggle');
    
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('mobile-open');
    }
});
</script>

</body>
</html>