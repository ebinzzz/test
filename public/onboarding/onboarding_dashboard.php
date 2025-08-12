<?php
// Includes the header, sidebar, and fetches user data
include_once 'common_layout.php';

// Get current time for greeting
$current_hour = date('H');
if ($current_hour < 12) {
    $greeting = "Good Morning";
    $greeting_icon = "fas fa-sun";
} elseif ($current_hour < 17) {
    $greeting = "Good Afternoon";
    $greeting_icon = "fas fa-cloud-sun";
} else {
    $greeting = "Good Evening";
    $greeting_icon = "fas fa-moon";
}

$first_name = explode(' ', $full_name)[0];

// Calculate onboarding progress by checking database records
$progress_checks = [];
$completed_steps = 0;
$total_steps = 0;

// Define onboarding steps to check
$onboarding_steps = [
    'emergency_contact' => [
        'name' => 'Emergency Contact Form',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%Emergency Contact%' AND ps.status IN ('submitted', 'approved')"
    ],
    'tax_forms' => [
        'name' => 'Tax Forms (W-4)',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%Tax%' AND ps.status IN ('submitted', 'approved')"
    ],
    'direct_deposit' => [
        'name' => 'Direct Deposit Authorization',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%Direct Deposit%' AND ps.status IN ('submitted', 'approved')"
    ],
    'handbook' => [
        'name' => 'Employee Handbook Acknowledgment',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%Handbook%' AND ps.status IN ('submitted', 'approved')"
    ],
    'i9_verification' => [
        'name' => 'I-9 Employment Eligibility',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%I-9%' AND ps.status IN ('submitted', 'approved')"
    ],
    'nda' => [
        'name' => 'Non-Disclosure Agreement',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%Non-Disclosure%' AND ps.status IN ('submitted', 'approved')"
    ],
    'it_security' => [
        'name' => 'IT Security Agreement',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM paperwork_submissions ps 
                   JOIN paperwork_types pt ON ps.paperwork_type_id = pt.id 
                   WHERE ps.team_member_id = ? AND pt.name LIKE '%IT Security%' AND ps.status IN ('submitted', 'approved')"
    ],
    'profile_complete' => [
        'name' => 'Profile Information',
        'required' => true,
        'check' => "SELECT COUNT(*) FROM team_members WHERE id = ? AND full_name IS NOT NULL AND full_name != '' AND image IS NOT NULL"
    ]
];

// Check each step
foreach ($onboarding_steps as $step_key => $step_info) {
    $stmt = $conn->prepare($step_info['check']);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    
    $progress_checks[$step_key] = [
        'name' => $step_info['name'],
        'completed' => $count > 0,
        'required' => $step_info['required']
    ];
    
    if ($step_info['required']) {
        $total_steps++;
        if ($count > 0) {
            $completed_steps++;
        }
    }
    
    $stmt->close();
}

// Calculate overall progress
$onboarding_progress = $total_steps > 0 ? round(($completed_steps / $total_steps) * 100) : 0;

// Check paperwork status for action card
$paperwork_sql = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN ps.status IN ('submitted', 'approved') THEN 1 ELSE 0 END) as completed
                  FROM paperwork_types pt
                  LEFT JOIN paperwork_submissions ps ON pt.id = ps.paperwork_type_id AND ps.team_member_id = ?
                  WHERE pt.is_required = 1";

$stmt = $conn->prepare($paperwork_sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$paperwork_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$paperwork_total = $paperwork_result['total'];
$paperwork_completed = $paperwork_result['completed'] ?? 0;
$paperwork_status = ($paperwork_completed == $paperwork_total) ? 'complete' : 'pending';
$paperwork_status_text = ($paperwork_completed == $paperwork_total) ? 'Complete' : 'In Progress';

// Check if digital ID card has been generated (you can add this table later)
$id_card_status = 'pending'; // This can be checked against a digital_id_cards table when implemented
$id_card_status_text = 'Ready to Generate';
?>

<style>
:root {
    --primary-color: #667eea;
    --primary-light: #a8b9ff;
    --accent-color: #764ba2;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --success-color: #10b981;
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --bg-tertiary: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-white: #ffffff;
    --border-light: #e2e8f0;
    --radius-sm: 6px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-2xl: 24px;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}

/* Main Dashboard Container */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    min-height: 100vh;
}

/* Layout Grid System */
.dashboard-grid {
    display: grid;
    gap: 25px;
    margin-bottom: 30px;
}

/* Welcome Section - Full Width */
.welcome-section {
    grid-column: 1 / -1;
}

.welcome-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: var(--radius-2xl);
    padding: 30px;
    color: var(--text-white);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
}

.welcome-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: gentle-pulse 4s ease-in-out infinite;
}

@keyframes gentle-pulse {
    0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.5; }
    50% { transform: scale(1.1) rotate(180deg); opacity: 0.8; }
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.greeting-section {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.greeting-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}

.greeting-text h2 {
    font-size: 2em;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1.2;
}

.greeting-text p {
    font-size: 1.1em;
    opacity: 0.9;
}

.welcome-message {
    background: rgba(255, 255, 255, 0.15);
    border-radius: var(--radius-lg);
    padding: 20px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 0.95em;
    line-height: 1.6;
}

/* Progress and Info Section */
.progress-info-section {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
}

/* Progress Card */
.progress-card {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 30px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-light);
    height: fit-content;
}

.progress-header {
    text-align: center;
    margin-bottom: 25px;
}

.progress-header h3 {
    font-size: 1.5em;
    color: var(--text-primary);
    margin-bottom: 10px;
    font-weight: 700;
}

.progress-header p {
    color: var(--text-secondary);
    font-size: 0.95em;
}

.circular-progress {
    position: relative;
    width: 140px;
    height: 140px;
    margin: 0 auto 25px;
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-bg {
    fill: none;
    stroke: var(--bg-tertiary);
    stroke-width: 8;
}

.progress-ring-fill {
    fill: none;
    stroke: var(--primary-color);
    stroke-width: 8;
    stroke-linecap: round;
    stroke-dasharray: 314.16;
    stroke-dashoffset: 314.16;
    transition: stroke-dashoffset 0.8s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-percentage {
    font-size: 2em;
    font-weight: 700;
    color: var(--primary-color);
    display: block;
}

.progress-label {
    font-size: 0.9em;
    color: var(--text-secondary);
}

.progress-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 25px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2.2em;
    font-weight: 700;
    color: var(--primary-color);
    display: block;
}

.stat-label {
    font-size: 0.85em;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.progress-details {
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
}

.progress-details h4 {
    margin-bottom: 15px;
    color: var(--text-primary);
    font-size: 1.1em;
    font-weight: 600;
}

.progress-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-light);
}

.progress-item:last-child {
    border-bottom: none;
}

.progress-item-name {
    font-size: 0.9em;
    color: var(--text-primary);
    font-weight: 500;
}

.progress-item-status {
    font-size: 0.8em;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-weight: 600;
}

.progress-complete {
    background: #d1fae5;
    color: #065f46;
}

.progress-incomplete {
    background: #fee2e2;
    color: #991b1b;
}

/* Info and Tips Section */
.info-tips-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
}

.info-container {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 25px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-light);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-box {
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    padding: 20px;
    border: 1px solid var(--border-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
}

.info-box:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-light);
}

.info-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 15px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
    color: white;
    box-shadow: var(--shadow-sm);
}

.info-box:nth-child(1) .info-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.info-box:nth-child(2) .info-icon {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}

.info-box:nth-child(3) .info-icon {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
}

.info-box:nth-child(4) .info-icon {
    background: linear-gradient(135deg, #43e97b, #38f9d7);
}

.info-title {
    font-size: 1em;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.info-text {
    font-size: 0.9em;
    color: var(--text-secondary);
    line-height: 1.4;
}

/* Tips Container */
.tips-container {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 25px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-light);
}

.tips-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.tips-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffd89b, #19547b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3em;
    box-shadow: var(--shadow-sm);
    flex-shrink: 0;
}

.tips-title {
    font-size: 1.2em;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}

.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tip-item {
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    padding: 15px;
    margin-bottom: 12px;
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    line-height: 1.5;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    padding-left: 45px;
}

.tip-item:hover {
    transform: translateX(5px);
    border-color: var(--primary-light);
    background: var(--bg-primary);
}

.tip-item::before {
    content: '✓';
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    background: linear-gradient(135deg, #a8e6cf, #88d8a3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
    color: white;
    font-weight: bold;
}

.tip-item:last-child {
    margin-bottom: 0;
}

/* Action Section */
.action-section {
    margin-top: 30px;
}

.section-title {
    font-size: 1.8em;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.action-card {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 30px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
    display: block;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
    transition: left 0.5s ease;
}

.action-card:hover::before {
    left: 100%;
}

.action-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-light);
    text-decoration: none;
    color: inherit;
}

.card-icon-wrapper {
    width: 70px;
    height: 70px;
    margin-bottom: 20px;
    position: relative;
}

.card-icon {
    width: 100%;
    height: 100%;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    color: white;
    box-shadow: var(--shadow-md);
    position: relative;
    z-index: 2;
}

.paperwork-card .card-icon {
    background: linear-gradient(135deg, #f59e0b, #f97316);
}

.id-card-action .card-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.card-title {
    font-size: 1.3em;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.card-description {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 15px;
    font-size: 0.95em;
}

.card-progress {
    margin-bottom: 15px;
    color: var(--text-secondary);
    font-size: 0.9em;
}

.card-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-lg);
    font-size: 0.85em;
    font-weight: 600;
    margin-top: auto;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-complete {
    background: #d1fae5;
    color: #065f46;
}

/* Coming Soon Cards */
.coming-soon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
}

.coming-soon-card {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 40px;
    text-align: center;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-md);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.coming-soon-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.coming-soon-icon {
    font-size: 3em;
    margin-bottom: 20px;
}

.coming-soon-card:nth-child(1) .coming-soon-icon {
    color: var(--accent-color);
}

.coming-soon-card:nth-child(2) .coming-soon-icon {
    color: var(--warning-color);
}

.coming-soon-card:nth-child(3) .coming-soon-icon {
    color: var(--danger-color);
}

.coming-soon-card h3 {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 1.2em;
    font-weight: 600;
}

.coming-soon-card p {
    color: var(--text-secondary);
    line-height: 1.5;
    margin-bottom: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: var(--radius-lg);
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9em;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--accent-color);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .welcome-progress-section {
        grid-template-columns: 1fr;
    }
    
    .info-tips-section {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 15px;
    }
    
    .dashboard-grid {
        gap: 20px;
    }
    
    .welcome-progress-section {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .welcome-card {
        padding: 20px;
    }
    
    .greeting-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        text-align: center;
    }
    
    .greeting-section.mobile-row {
        flex-direction: row;
        align-items: center;
        text-align: left;
    }
    
    .greeting-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2em;
    }
    
    .greeting-text h2 {
        font-size: 1.6em;
    }
    
    .greeting-text p {
        font-size: 1em;
    }
    
    .welcome-message {
        padding: 18px;
    }
    
    .progress-card {
        padding: 25px 20px;
    }
    
    .circular-progress {
        width: 120px;
        height: 120px;
    }
    
    .progress-percentage {
        font-size: 1.8em;
    }
    
    .stat-number {
        font-size: 1.8em;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .info-container, .tips-container {
        padding: 20px;
    }
    
    .tips-title {
        font-size: 1.1em;
    }
    
    .tip-item {
        padding: 12px;
        padding-left: 40px;
        font-size: 0.9em;
    }
    
    .quick-actions, .coming-soon-grid {
        grid-template-columns: 1fr;
    }
    
    .action-card, .coming-soon-card {
        padding: 25px 20px;
    }
    
    .section-title {
        font-size: 1.5em;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 10px;
    }
    
    .welcome-card {
        padding: 20px 15px;
    }
    
    .greeting-section {
        flex-direction: row;
        align-items: center;
        text-align: left;
    }
    
    .greeting-icon {
        width: 45px;
        height: 45px;
    }
    
    .greeting-text h2 {
        font-size: 1.4em;
    }
    
    .progress-stats {
        gap: 20px;
    }
    
    .progress-details {
        padding: 15px;
    }
    
    .progress-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .tip-item {
        padding-left: 15px;
    }
    
    .tip-item::before {
        display: none;
    }
}

/* Dark mode support (optional) */
@media (prefers-color-scheme: dark) {
    :root {
        --bg-primary: #1e293b;
        --bg-secondary: #334155;
        --bg-tertiary: #475569;
        --text-primary: #f1f5f9;
        --text-secondary: #cbd5e1;
        --border-light: #475569;
    }
}
</style>

<!-- Dashboard Content -->
<div class="dashboard-container">
    <div class="dashboard-grid">
        
        <!-- Welcome and Progress Section -->
        <section class="welcome-progress-section">
            <div class="welcome-card">
                <div class="welcome-content">
                    <div class="greeting-section mobile-row">
                        <div class="greeting-icon">
                            <i class="<?php echo $greeting_icon; ?>"></i>
                        </div>
                        <div class="greeting-text">
                            <h2><?php echo $greeting; ?>, <?php echo $first_name; ?>!</h2>
                            <p>Ready to start your journey with us?</p>
                        </div>
                    </div>
                    
                    <div class="welcome-message">
                        <p><strong>Welcome to Zorqent Technologies!</strong></p>
                        <p>We're excited to have you on board. This portal will guide you through your onboarding process, ensuring you have all the tools, information, and access you need to succeed in your new role.</p>
                    </div>
                </div>
            </div>
            
            <div class="progress-card">
                <div class="progress-header">
                    <h3>Onboarding Progress</h3>
                    <p>
                        <?php if ($onboarding_progress == 100): ?>
                            Congratulations! 🎉
                        <?php elseif ($onboarding_progress >= 75): ?>
                            Almost there! 
                        <?php elseif ($onboarding_progress >= 50): ?>
                            Great progress!
                        <?php elseif ($onboarding_progress >= 25): ?>
                            Getting started!
                        <?php else: ?>
                            Let's begin!
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="circular-progress">
                    <svg class="progress-ring" width="140" height="140">
                        <circle class="progress-ring-bg" cx="70" cy="70" r="50"></circle>
                        <circle class="progress-ring-fill" cx="70" cy="70" r="50"></circle>
                    </svg>
                    <div class="progress-text">
                        <span class="progress-percentage"><?php echo $onboarding_progress; ?>%</span>
                        <span class="progress-label">Complete</span>
                    </div>
                </div>
                
                <div class="progress-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $completed_steps; ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_steps - $completed_steps; ?></span>
                        <span class="stat-label">Remaining</span>
                    </div>
                </div>

                <!-- Progress Details -->
                <div class="progress-details">
                    <h4>Progress Breakdown</h4>
                    <?php foreach ($progress_checks as $key => $step): ?>
                        <div class="progress-item">
                            <span class="progress-item-name"><?php echo $step['name']; ?></span>
                            <span class="progress-item-status <?php echo $step['completed'] ? 'progress-complete' : 'progress-incomplete'; ?>">
                                <?php echo $step['completed'] ? '✓ Done' : 'Pending'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Information and Tips Section -->
    <section class="info-tips-section">
        <!-- Company Info -->
        <div class="info-container">
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-title">Working Hours</div>
                    <div class="info-text">Mon-Fri<br>9:00 AM - 6:00 PM</div>
                </div>
                
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="info-title">Start Date</div>
                    <div class="info-text">Aug 11, 2025<br>Today!</div>
                </div>
                
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="info-title">IT Support</div>
                    <div class="info-text">help@zorqent.com<br>Ext: 101</div>
                </div>
                
                <div class="info-box">
                    <div class="info-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="info-title">HR Contact</div>
                    <div class="info-text">hr@zorqent.com<br>Ext: 102</div>
                </div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="tips-container">
            <div class="tips-header">
                <div class="tips-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="tips-title">Quick Tips for Your First Week</div>
            </div>
            
            <ul class="tips-list">
                <li class="tip-item">Complete all required paperwork within 3 business days</li>
                <li class="tip-item">Schedule a meet & greet with your team lead</li>
                <li class="tip-item">Set up your workspace and development environment</li>
                <li class="tip-item">Review the employee handbook and company policies</li>
            </ul>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="action-section">
        <h2 class="section-title">
            <i class="fas fa-rocket"></i>
            Quick Actions
        </h2>
        
        <div class="quick-actions">
            <a href="onboarding_paperwork.php" class="action-card paperwork-card">
                <div class="card-icon-wrapper">
                    <div class="card-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
                <h3 class="card-title">Complete Paperwork</h3>
                <p class="card-description">Fill out essential documents, employment agreements, and compliance forms to finalize your onboarding.</p>
                <div class="card-progress">
                    <?php echo $paperwork_completed; ?> of <?php echo $paperwork_total; ?> forms completed
                </div>
                <span class="card-status status-<?php echo $paperwork_status; ?>">
                    <i class="fas fa-<?php echo ($paperwork_status == 'complete') ? 'check' : 'clock'; ?>"></i>
                    <?php echo $paperwork_status_text; ?>
                </span>
            </a>
            
            <a href="digital_id_card.php" class="action-card id-card-action">
                <div class="card-icon-wrapper">
                    <div class="card-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
                <h3 class="card-title">Digital ID Card</h3>
                <p class="card-description">Generate and download your official digital employee ID card with secure QR code verification.</p>
                <span class="card-status status-<?php echo $id_card_status; ?>">
                    <i class="fas fa-id-card"></i>
                    <?php echo $id_card_status_text; ?>
                </span>
            </a>
        </div>
        
        <!-- Coming Soon Cards -->
        <div class="coming-soon-grid">
            <div class="coming-soon-card">
                <div class="coming-soon-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Meet Your Team</h3>
                <p>Connect with your colleagues and learn about your department structure.</p>
                <button class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Coming Soon
                </button>
            </div>
            
            <div class="coming-soon-card">
                <div class="coming-soon-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Training Modules</h3>
                <p>Access company policies, procedures, and role-specific training materials.</p>
                <button class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Coming Soon
                </button>
            </div>
            
            <div class="coming-soon-card">
                <div class="coming-soon-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Setup Tools</h3>
                <p>Configure your workspace, software access, and development environment.</p>
                <button class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Coming Soon
                </button>
            </div>
        </div>
    </section>

</div>

<script>
// Update circular progress based on percentage
document.addEventListener('DOMContentLoaded', function() {
    const progressRing = document.querySelector('.progress-ring-fill');
    if (progressRing) {
        const radius = 50;
        const circumference = 2 * Math.PI * radius;
        const progress = <?php echo $onboarding_progress; ?>;
        
        progressRing.style.strokeDasharray = circumference;
        
        // Calculate offset for progress with animation
        const offset = circumference - (progress / 100) * circumference;
        
        // Animate the progress ring
        setTimeout(() => {
            progressRing.style.strokeDashoffset = offset;
        }, 300);
    }
});

// Add interactive hover effects to action cards
document.querySelectorAll('.action-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Add hover effects to info boxes
document.querySelectorAll('.info-box').forEach(box => {
    box.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px) scale(1.02)';
    });
    
    box.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Add hover effects to tip items
document.querySelectorAll('.tip-item').forEach(tip => {
    tip.addEventListener('mouseenter', function() {
        this.style.transform = 'translateX(8px)';
    });
    
    tip.addEventListener('mouseleave', function() {
        this.style.transform = 'translateX(0)';
    });
});

// Add hover effects to coming soon cards
document.querySelectorAll('.coming-soon-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Add smooth scroll behavior for better UX
document.documentElement.style.scrollBehavior = 'smooth';

// Performance optimization: Use requestAnimationFrame for smooth animations
function smoothTransform(element, property, startValue, endValue, duration = 300) {
    const startTime = performance.now();
    
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const easedProgress = 1 - Math.pow(1 - progress, 3); // easeOut
        const currentValue = startValue + (endValue - startValue) * easedProgress;
        
        element.style[property] = currentValue;
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    }
    
    requestAnimationFrame(animate);
}

// Enhanced mobile touch interactions
if ('ontouchstart' in window) {
    document.querySelectorAll('.action-card, .info-box, .coming-soon-card').forEach(element => {
        element.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        element.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

// Intersection Observer for fade-in animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe elements for animation
document.querySelectorAll('.action-card, .info-box, .coming-soon-card, .tips-container').forEach(element => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(element);
});
</script>

</body>
</html>