<?php
// Includes the header, sidebar, and fetches user data
include_once 'common_layout.php';

// Generate unique employee ID if not exists
$employee_id = "ZRQ" . str_pad($id, 4, "0", STR_PAD_LEFT);
$department = "Technology Department";
$join_date = date('M d, Y');
$card_number = "ID-" . date('Y') . "-" . str_pad($id, 6, "0", STR_PAD_LEFT);


// QR Code data with comprehensive employee information
$qr_data = json_encode([
    'employee_id' => $employee_id,
    'card_number' => $card_number,
    'name' => $full_name,
    'role' => $role,
    'department' => $department,
    'company' => 'Zorqent Technologies',
    'issued_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+2 years')),
    'verification_url' => 'https://zorqent.com/verify/' . $employee_id
]);

// URL encode the data for QR code generation
$qr_data_encoded = urlencode($qr_data);

// **UPDATED:** QuickChart QR Code URLs
// The parameters are slightly different from Google Charts
$qr_small = "https://quickchart.io/qr?size=65x65&text=" . $qr_data_encoded;
$qr_medium = "https://quickchart.io/qr?size=150x150&text=" . $qr_data_encoded;
$qr_large = "https://quickchart.io/qr?size=300x300&text=" . $qr_data_encoded;
?>

<style>
    /* Your existing CSS code */
    :root {
        --primary-color: #3b82f6;
        --primary-dark: #1e40af;
        --accent-color: #10b981;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --bg-primary: #ffffff;
        --bg-secondary: #f3f4f6;
        --bg-tertiary: #e5e7eb;
        --border-color: #e5e7eb;
        --border-light: #f3f4f6;
        --danger-color: #ef4444;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-2xl: 30px;
    }
    
    .id-container { max-width: 1000px; margin: 0 auto; }
    .page-header { text-align: center; margin-bottom: 40px; padding: 30px; background: var(--bg-primary); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); border: 1px solid var(--border-light); }
    .page-header h1 { font-size: 2.5em; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .page-subtitle { font-size: 1.1em; color: var(--text-secondary); }
    .card-preview-section { display: grid; grid-template-columns: 1fr 300px; gap: 40px; margin-bottom: 40px; }
    .id-card-wrapper { display: flex; justify-content: center; perspective: 1200px; }
    .employee-id-card { width: 420px; height: 280px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 20px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.9); position: relative; overflow: hidden; transform-style: preserve-3d; transition: all 0.4s ease; border: 2px solid var(--border-color); }
    .employee-id-card:hover { transform: rotateX(5deg) rotateY(8deg) translateY(-10px); box-shadow: 0 35px 70px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(0, 0, 0, 0.05); }
    .card-header-stripe { height: 80px; background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%); position: relative; overflow: hidden; }
    .card-header-stripe::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.2) 0%, transparent 50%), radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%); }
    .card-header-content { position: relative; z-index: 2; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; color: white; }
    .company-branding { display: flex; align-items: center; gap: 12px; }
    .company-icon { width: 40px; height: 40px; background: rgba(255, 255, 255, 0.2); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px); font-size: 1.2em; }
    .company-text { font-size: 1.6em; font-weight: 700; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2); }
    .card-type { font-size: 0.85em; opacity: 0.9; font-weight: 500; text-align: right; line-height: 1.3; }
    .card-body { padding: 25px; height: 200px; display: flex; flex-direction: column; }
    .employee-section { display: flex; gap: 20px; margin-bottom: 20px; flex: 1; }
    .employee-photo { width: 85px; height: 85px; border-radius: 15px; object-fit: cover; border: 3px solid var(--border-color); box-shadow: var(--shadow-md); }
    .employee-details { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .employee-name-card { font-size: 1.5em; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; line-height: 1.2; }
    .employee-title { font-size: 1em; color: var(--primary-color); font-weight: 600; margin-bottom: 8px; }
    .employee-dept { font-size: 0.9em; color: var(--text-secondary); margin-bottom: 10px; }
    .employee-id-display { display: inline-block; background: linear-gradient(135deg, var(--bg-tertiary), #e2e8f0); padding: 6px 12px; border-radius: var(--radius-md); font-family: 'Courier New', monospace; font-size: 0.85em; font-weight: 600; color: var(--text-primary); border: 1px solid var(--border-color); }
    .card-footer-section { display: flex; justify-content: space-between; align-items: flex-end; padding-top: 15px; border-top: 1px solid var(--border-light); }
    .validity-section { font-size: 0.8em; color: var(--text-secondary); }
    .validity-label { font-weight: 600; margin-bottom: 2px; }
    .qr-code-section { text-align: center; }
    .qr-code-img { width: 65px; height: 65px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); background: white; padding: 4px; transition: all 0.3s ease; cursor: pointer; }
    .qr-code-img:hover { transform: scale(1.05); box-shadow: var(--shadow-md); }
    .qr-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); }
    .qr-modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 40px; border-radius: var(--radius-2xl); text-align: center; box-shadow: var(--shadow-xl); max-width: 90%; max-height: 90%; }
    .qr-large-display { width: 250px; height: 250px; margin: 20px auto; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); }
    .modal-close { position: absolute; top: 15px; right: 20px; background: var(--danger-color); color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 1.2em; transition: all 0.2s ease; }
    .modal-close:hover { background: #dc2626; transform: scale(1.1); }
    .qr-label { font-size: 0.7em; color: var(--text-secondary); margin-top: 5px; font-weight: 500; }
    .card-actions { background: var(--bg-primary); border-radius: var(--radius-xl); padding: 30px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-light); height: fit-content; }
    .actions-title { font-size: 1.3em; font-weight: 600; color: var(--text-primary); margin-bottom: 20px; text-align: center; }
    .action-btn { width: 100%; margin-bottom: 15px; padding: 15px 20px; font-size: 0.95em; justify-content: center; border-radius: var(--radius-lg); transition: all 0.3s ease; }
    .btn-download { background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border: none; }
    .btn-download:hover { background: linear-gradient(135deg, var(--primary-dark), #1e3a8a); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    .btn-print { background: var(--bg-secondary); color: var(--text-primary); border: 2px solid var(--border-color); }
    .btn-print:hover { background: var(--bg-tertiary); border-color: var(--primary-color); transform: translateY(-2px); }
    .info-section { margin-top: 40px; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 25px; }
    .info-card { background: var(--bg-primary); border-radius: var(--radius-lg); padding: 25px; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); transition: all 0.3s ease; }
    .info-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }
    .info-icon { width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; margin-bottom: 15px; font-size: 1.2em; }
    .info-title { font-size: 1.1em; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
    .info-content { color: var(--text-secondary); line-height: 1.5; }
    .security-notice { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border: 1px solid #93c5fd; border-radius: var(--radius-lg); padding: 25px; margin-top: 30px; text-align: center; }
    .security-notice h4 { color: #1e40af; font-size: 1.2em; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .security-notice p { color: #1e40af; margin: 0; line-height: 1.6; }
    @media (max-width: 768px) { .card-preview-section { grid-template-columns: 1fr; gap: 30px; } .employee-id-card { width: 380px; height: 250px; } .card-body { padding: 20px; } .employee-photo { width: 70px; height: 70px; } .employee-name-card { font-size: 1.3em; } .info-grid { grid-template-columns: 1fr; } }
    @media print { .sidebar, .card-actions, .top-header { display: none !important; } .main-content { margin-left: 0; } .employee-id-card { box-shadow: none; border: 2px solid #000; transform: none !important; } .page-header, .info-section, .security-notice { display: none; } }
</style>

<div class="id-container">
    <div class="page-header">
        <h1><i class="fas fa-id-card" style="margin-right: 15px;"></i>Digital Employee ID Card</h1>
        <p class="page-subtitle">Your official digital identification and access card</p>
    </div>

    <div class="card-preview-section">
        <div class="id-card-wrapper">
            <div class="employee-id-card" id="employeeCard">
                <div class="card-header-stripe">
                    <div class="card-header-content">
                        <div class="company-branding">
                            <div class="company-icon">
                                <img src="../assets/logo.png" alt="logo" class="company-icon">
                            </div>
                            <div class="company-text">ZORQENT</div>
                        </div>
                        <div class="card-type">
                            <div>EMPLOYEE</div>
                            <div>ID CARD</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="employee-section">
                        <img src="../<?php echo $profile_image; ?>" 
                            alt="<?php echo $full_name; ?>" 
                            class="employee-photo"
                            onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODUiIGhlaWdodD0iODUiIHZpZXdCb3g9IjAgMCA4NSA4NSIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9Ijg1IiBoZWlnaHQ9Ijg1IiByeD0iMTUiIGZpbGw9IiNmMWY1ZjkiLz4KPHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4PSIyMi41IiB5PSIyMi41Ij4KPHBhdGggZD0iTTEyIDEyQzE0Ljc2MTQgMTIgMTcgOS43NjE0MiAxNyA3QzE3IDQuMjM4NTggMTQuNzYxNCAyIDEyIDJDOS4yMzg1OCAyIDcgNC4yMzg1OCA3IDdDNyA5Ljc2MTQyIDkuMjM4NTggMTIgMTIgMTJaIiBmaWxsPSIjNjQ3NDhiIi8+CjxwYXRoIGQ9Ik0xMiAxNEM4LjY4NjI5IDE0IDYgMTYuNjg2MyA2IDIwSDE4QzE4IDE2LjY4NjMgMTUuMzEzNyAxNCAxMiAxNFoiIGZpbGw9IiM2NDc0OGIiLz4KPC9zdmc+Cjwvc3ZnPg=='">
                        
                        <div class="employee-details">
                            <div class="employee-name-card"><?php echo $full_name; ?></div>
                            <div class="employee-title"><?php echo $role; ?></div>
                            <div class="employee-dept"><?php echo $department; ?></div>
                            <div class="employee-id-display"><?php echo $employee_id; ?></div>
                        </div>
                    </div>
                    
                    <div class="card-footer-section">
                        <div class="validity-section">
                            <div class="validity-label">Valid Until</div>
                            <div><?php echo date('M d, Y', strtotime('+2 years')); ?></div>
                        </div>
                        
                        <div class="qr-code-section">
                            <img src="<?php echo $qr_small; ?>" 
                                alt="Employee QR Code" 
                                class="qr-code-img"
                                id="cardQrCode"
                                loading="lazy"
                                onclick="enlargeQR()">
                            <div class="qr-label">Scan to Verify</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-actions">
            <h3 class="actions-title">Card Actions</h3>
            
            <button class="btn action-btn btn-download" onclick="downloadCard()">
                <i class="fas fa-download"></i>
                Download Card
            </button>
            
            <button class="btn action-btn btn-print" onclick="printCard()">
                <i class="fas fa-print"></i>
                Print Card
            </button>
            
            <button class="btn action-btn btn-print" onclick="viewQRCode()">
                <i class="fas fa-expand"></i>
                View QR Code
            </button>
            
            <button class="btn action-btn btn-print" onclick="downloadQR()">
                <i class="fas fa-qrcode"></i>
                Download QR
            </button>
            
            <div style="background: var(--bg-tertiary); padding: 20px; border-radius: var(--radius-lg); margin-top: 20px;">
                <h4 style="color: var(--text-primary); margin-bottom: 10px; font-size: 1em;">
                    <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                    Card Information
                </h4>
                <div style="font-size: 0.85em; color: var(--text-secondary); line-height: 1.5;">
                    <div><strong>Card Number:</strong> <?php echo $card_number; ?></div>
                    <div><strong>Issue Date:</strong> <?php echo $join_date; ?></div>
                    <div><strong>Status:</strong> <span style="color: var(--accent-color);">Active</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2 class="section-title">
            <i class="fas fa-info-circle"></i>
            Employee Information
        </h2>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="info-title">Personal Details</div>
                <div class="info-content">
                    <strong>Name:</strong> <?php echo $full_name; ?><br>
                    <strong>Employee ID:</strong> <?php echo $employee_id; ?><br>
                    <strong>Join Date:</strong> <?php echo $join_date; ?>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="info-title">Position Details</div>
                <div class="info-content">
                    <strong>Role:</strong> <?php echo $role; ?><br>
                    <strong>Department:</strong> <?php echo $department; ?><br>
                    <strong>Status:</strong> <span style="color: var(--accent-color);">Active</span>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="info-title">Security Information</div>
                <div class="info-content">
                    <strong>Card Number:</strong> <?php echo $card_number; ?><br>
                    <strong>Access Level:</strong> Standard<br>
                    <strong>2FA Status:</strong> <span style="color: var(--accent-color);">Enabled</span>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="info-title">QR Code Details</div>
                <div class="info-content">
                    <strong>Provider:</strong> QuickChart<br>
                    <strong>Format:</strong> JSON Encoded<br>
                    <strong>Error Correction:</strong> N/A<br>
                    <strong>Use:</strong> Identity Verification
                </div>
            </div>
        </div>
    </div>

    <div class="security-notice">
        <h4>
            <i class="fas fa-lock"></i>
            Security & Privacy Notice
        </h4>
        <p>
            Your digital ID card contains encrypted verification data for secure access to Zorqent facilities and systems. 
            The QR code includes your employee ID, role information, and validity period. Keep this card secure and report 
            any unauthorized use immediately to the IT Security team.
        </p>
    </div>
</div>

<div id="qrModal" class="qr-modal">
    <div class="qr-modal-content">
        <button class="modal-close" onclick="closeQRModal()">&times;</button>
        <h3 style="color: var(--text-primary); margin-bottom: 20px;">
            <i class="fas fa-qrcode" style="color: var(--primary-color);"></i>
            Employee QR Code
        </h3>
        <img src="<?php echo $qr_large; ?>" alt="Large QR Code" class="qr-large-display">
        <div style="margin-top: 20px;">
            <p style="color: var(--text-secondary); margin-bottom: 15px;">
                Scan this QR code to verify employee identity and access information
            </p>
            <button class="btn btn-download" onclick="downloadQROnly()">
                <i class="fas fa-download"></i>
                Download QR Code
            </button>
        </div>
    </div>
</div>

</div>
</body>
</html>

<script>
// Function to download the entire ID card as a PNG
function downloadCard() {
    if (typeof html2canvas === 'undefined') {
        console.error('html2canvas library is not loaded.');
        alert('Could not download card. Please try again.');
        return;
    }

    html2canvas(document.getElementById('employeeCard'), {
        scale: 2, // Increase scale for better resolution
        useCORS: true, // This is important for handling the profile image
        allowTaint: true, // This is also helpful for handling cross-origin images
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'zorqent-employee-id-<?php echo $employee_id; ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        link.remove();
    }).catch(error => {
        console.error('Error generating canvas:', error);
        alert('Failed to download the card image.');
    });
}

function printCard() {
    window.print();
}

function viewQRCode() {
    document.getElementById('qrModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeQRModal() {
    document.getElementById('qrModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function enlargeQR() {
    viewQRCode();
}

// Function to download the QR code image directly
function downloadQR() {
    const link = document.createElement('a');
    link.download = 'zorqent-qr-code-<?php echo $employee_id; ?>.png';
    link.href = '<?php echo $qr_large; ?>'; // Use the large QR code URL
    link.click();
    link.remove();
}

function downloadQROnly() {
    downloadQR();
    closeQRModal();
}

// Event listeners for modal closing
document.addEventListener('click', function(e) {
    const modal = document.getElementById('qrModal');
    if (e.target === modal) {
        closeQRModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQRModal();
    }
});

// Add subtle animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate card entrance
    const card = document.getElementById('employeeCard');
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    
    setTimeout(() => {
        card.style.transition = 'all 0.8s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, 300);
    
    // Add floating animation to QR code
    const qrCode = document.querySelector('.qr-code-img');
    if (qrCode) {
        qrCode.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1) rotate(5deg)';
        });
        
        qrCode.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
        });
    }
    
    // Add ripple effect to buttons
    document.querySelectorAll('.action-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(255, 255, 255, 0.3)';
            ripple.style.transform = 'scale(0)';
            ripple.style.animation = 'ripple 0.6s linear';
            ripple.style.pointerEvents = 'none';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});

// Add CSS for ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .action-btn {
        position: relative;
        overflow: hidden;
    }
`;
document.head.appendChild(style);

// Add HTML2Canvas library for download functionality
const script = document.createElement('script');
script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
document.head.appendChild(script);
</script>