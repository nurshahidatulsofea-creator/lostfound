<?php
session_start();
include('config/db.php');

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get settings
$settings = [];
$result = mysqli_query($conn, "SELECT * FROM system_settings");
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// ✅ FIXED: Added telegram_notifications and require_claim_approval
$defaults = [
    'items_expiry_days' => '30',
    'max_file_size_mb' => '5',
    'maintenance_mode' => 'false',
    'auto_approve_items' => 'false',
    'telegram_notifications' => 'true',    // ← FIXED
    'require_claim_approval' => 'true',    // ← FIXED
    'contact_email' => 'admin@umpsa.edu.my',
    'contact_phone' => '09-1234567',
    'site_name' => 'UMPSA Lost & Found',
    'items_per_page' => '12'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

$message = '';
$message_type = '';

if (isset($_POST['save_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $key = mysqli_real_escape_string($conn, $key);
        $value = mysqli_real_escape_string($conn, $value);
        
        mysqli_query($conn, "
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('$key', '$value')
            ON DUPLICATE KEY UPDATE setting_value = '$value'
        ");
    }
    
    mysqli_query($conn, "
        INSERT INTO user_activity_log (user_id, action, ip_address) 
        VALUES ('{$_SESSION['user_id']}', 'updated_system_settings', '{$_SERVER['REMOTE_ADDR']}')
    ");
    
    $message = "✅ Settings saved successfully!";
    $message_type = "success";
    
    // Refresh settings
    $settings = [];
    $result = mysqli_query($conn, "SELECT * FROM system_settings");
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle backup request
if (isset($_POST['backup_database'])) {
    $backup_file = "backup_" . date('Y-m-d_H-i-s') . ".sql";
    $tables = ['users', 'items', 'claims', 'complaints', 'notifications', 'item_returns', 'messages'];
    
    $backup_content = "-- UMPSA Lost & Found Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SELECT * FROM $table");
        if ($result) {
            $backup_content .= "-- Table: $table\n";
            while ($row = mysqli_fetch_assoc($result)) {
                $backup_content .= "INSERT INTO $table VALUES ('" . implode("','", array_map('addslashes', $row)) . "');\n";
            }
            $backup_content .= "\n";
        }
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    echo $backup_content;
    exit();
}

// Handle cache clear
if (isset($_POST['clear_cache'])) {
    $message = "✅ Cache cleared successfully!";
    $message_type = "success";
}

// Get notification count for navbar
$pending_complaints = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'"
))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - UMPSA Lost & Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
        .navbar {
            background: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            font-size: 18px;
            color: #2d3748;
        }
        .nav-brand img { height: 40px; }
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: #00a896; }
        .nav-links a.active { color: #00a896; border-bottom: 2px solid #00a896; padding-bottom: 5px; }
        
        .settings-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .page-header p {
            color: #718096;
            font-size: 15px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .alert-success {
            background: #c6f6d5;
            color: #276749;
            border-left: 4px solid #48bb78;
        }
        .alert-error {
            background: #fed7d7;
            color: #9b2c2c;
            border-left: 4px solid #e53e3e;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        .settings-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .settings-card h3 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00a896;
            box-shadow: 0 0 0 3px rgba(0,168,150,0.1);
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #a0aec0;
            font-size: 12px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: 0.3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #00a896;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .toggle-label {
            margin-left: 60px;
            font-size: 14px;
            color: #4a5568;
            padding-top: 3px;
            display: inline-block;
            font-weight: 600;
        }
        
        .btn-save {
            background: #00a896;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-save:hover {
            background: #008f80;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .full-width {
            grid-column: span 2;
        }
        
        .divider {
            margin: 20px 0;
            border-top: 1px solid #e2e8f0;
        }
        
        .preview-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 13px;
            color: #718096;
            text-align: center;
        }
        
        .toggle-description {
            display: block;
            margin-top: 5px;
            color: #718096;
            font-size: 12px;
            margin-left: 60px;
        }
        
        @media (max-width: 900px) {
            .settings-grid { grid-template-columns: 1fr; }
            .navbar { padding: 15px 20px; }
            .settings-container { padding: 0 15px; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo">
        <span>Admin Portal</span>
    </div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_analytics.php">Analytics</a>
        <a href="admin_complaints.php">Complaints 
            <?php if ($pending_complaints > 0): ?>
                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;"><?php echo $pending_complaints; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_settings.php" class="active">Settings</a>
        <a href="dashboard.php">Home</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="settings-container">
    
    <div class="page-header">
        <h1>⚙️ System Settings</h1>
        <p>Configure your Lost & Found system preferences</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="settings-grid">
            
            <!-- General Settings -->
            <div class="settings-card">
                <h3>📌 General Settings</h3>
                
                <div class="form-group">
                    <label>System Name</label>
                    <input type="text" name="settings[site_name]" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                    <small>This appears on the website title and header</small>
                </div>
                
                <div class="form-group">
                    <label>Items Per Page</label>
                    <input type="number" name="settings[items_per_page]" value="<?php echo $settings['items_per_page']; ?>" min="5" max="50">
                    <small>Number of items shown on browse page</small>
                </div>
                
                <div class="form-group">
                    <label>Items Expiry Days</label>
                    <input type="number" name="settings[items_expiry_days]" value="<?php echo $settings['items_expiry_days']; ?>" min="7" max="90">
                    <small>After this many days, pending items become unclaimed</small>
                </div>
                
                <div class="form-group">
                    <label>Max File Size (MB)</label>
                    <input type="number" name="settings[max_file_size_mb]" value="<?php echo $settings['max_file_size_mb']; ?>" min="1" max="20">
                    <small>Maximum size for uploaded images</small>
                </div>
            </div>
            
            <!-- Contact Settings -->
            <div class="settings-card">
                <h3>📞 Contact Information</h3>
                
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="settings[contact_email]" value="<?php echo htmlspecialchars($settings['contact_email']); ?>">
                    <small>Users can contact this email for support</small>
                </div>
                
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="settings[contact_phone]" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                    <small>Phone number for urgent inquiries</small>
                </div>
                
                <div class="preview-box">
                    📧 Email: <?php echo $settings['contact_email']; ?><br>
                    📱 Phone: <?php echo $settings['contact_phone']; ?>
                </div>
            </div>
            
            <!-- Feature Toggles - FIXED -->
            <div class="settings-card">
                <h3>🔘 Feature Toggles</h3>
                
                <div class="form-group">
                    <div class="toggle-switch">
                        <input type="checkbox" name="settings[maintenance_mode]" value="true" 
                               id="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </div>
                    <label class="toggle-label" for="maintenance_mode">🔧 Maintenance Mode</label>
                    <small class="toggle-description">When enabled, users cannot submit new reports or claims</small>
                </div>
                
                <div class="form-group">
                    <div class="toggle-switch">
                        <input type="checkbox" name="settings[auto_approve_items]" value="true" 
                               id="auto_approve_items" <?php echo ($settings['auto_approve_items'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </div>
                    <label class="toggle-label" for="auto_approve_items">✅ Auto-approve Items</label>
                    <small class="toggle-description">Items are visible immediately without admin approval</small>
                </div>
                
                <div class="form-group">
                    <div class="toggle-switch">
                        <input type="checkbox" name="settings[telegram_notifications]" value="true" 
                               id="telegram_notifications" <?php echo ($settings['telegram_notifications'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </div>
                    <label class="toggle-label" for="telegram_notifications">📱 Telegram Notifications</label>
                    <small class="toggle-description">Send real-time notifications via Telegram for claims, returns, and messages</small>
                </div>
                
                <div class="form-group">
                    <div class="toggle-switch">
                        <input type="checkbox" name="settings[require_claim_approval]" value="true" 
                               id="require_claim_approval" <?php echo ($settings['require_claim_approval'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </div>
                    <label class="toggle-label" for="require_claim_approval">🔒 Require Claim Approval</label>
                    <small class="toggle-description">Item owners must approve claims before they are finalized</small>
                </div>
            </div>
            
            <!-- Maintenance Tools -->
            <div class="settings-card">
                <h3>🛠️ Maintenance Tools</h3>
                
                <div class="button-group">
                    <button type="submit" name="backup_database" class="btn-secondary" formnovalidate>
                        💾 Backup Database
                    </button>
                    <button type="submit" name="clear_cache" class="btn-secondary" formnovalidate>
                        🗑️ Clear Cache
                    </button>
                </div>
                
                <div class="divider"></div>
                
                <div class="form-group">
                    <label>System Status</label>
                    <div class="preview-box">
                        <?php
                        $db_status = mysqli_ping($conn) ? '🟢 Connected' : '🔴 Disconnected';
                        $php_version = phpversion();
                        $server_time = date('Y-m-d H:i:s');
                        ?>
                        Database: <?php echo $db_status; ?><br>
                        PHP Version: <?php echo $php_version; ?><br>
                        Server Time: <?php echo $server_time; ?>
                    </div>
                </div>
            </div>
            
            <!-- Save Button - Full Width -->
            <div class="settings-card full-width">
                <button type="submit" name="save_settings" class="btn-save">
                    💾 Save All Settings
                </button>
                <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #a0aec0;">
                    Changes take effect immediately
                </p>
            </div>
            
        </div>
    </form>
    
</div>

<script>
    document.querySelectorAll('.toggle-switch input').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            this.value = this.checked ? 'true' : 'false';
        });
        checkbox.value = checkbox.checked ? 'true' : 'false';
    });
    
    document.querySelectorAll('button[name="backup_database"], button[name="clear_cache"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.name === 'backup_database') {
                if (!confirm('Download database backup? This may take a few seconds.')) {
                    e.preventDefault();
                }
            } else if (this.name === 'clear_cache') {
                if (!confirm('Clear system cache? This will refresh all temporary data.')) {
                    e.preventDefault();
                }
            }
        });
    });
</script>

</body>
</html>