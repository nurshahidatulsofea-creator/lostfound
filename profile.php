<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get user data
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$user_id'");
$user = mysqli_fetch_assoc($user_query);

// Get user statistics
$stats = [];

// Total items reported
$stats['total_items'] = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE user_id = '$user_id'"
))['total'] ?? 0;

// Total claims made
$stats['total_claims'] = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM claims WHERE user_id = '$user_id'"
))['total'] ?? 0;

// Total complaints
$stats['total_complaints'] = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM complaints WHERE user_id = '$user_id'"
))['total'] ?? 0;

// Items found/returned
$stats['items_found'] = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE user_id = '$user_id' AND item_type = 'found'"
))['total'] ?? 0;

$stats['items_lost'] = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE user_id = '$user_id' AND item_type = 'lost'"
))['total'] ?? 0;

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    if (empty($name)) {
        $error = "Name cannot be empty.";
    } else {
        mysqli_query($conn, "UPDATE users SET name = '$name', phone_number = '$phone' WHERE user_id = '$user_id'");
        $_SESSION['name'] = $name;
        $message = "Profile updated successfully!";
        
        // Refresh user data
        $user_query = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$user_id'");
        $user = mysqli_fetch_assoc($user_query);
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        if (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE user_id = '$user_id'");
            $message = "Password changed successfully!";
        }
    } else {
        $error = "Current password is incorrect.";
    }
}

// Get notification count for navbar
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
        /* Navbar styling - SAMA dengan Messages page */
        .navbar {
            background: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
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
        .nav-links a.active { 
            color: #00a896; 
            border-bottom: 2px solid #00a896; 
            padding-bottom: 5px;
        }
        
        .notif-bell {
            position: relative;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            font-weight: 600;
        }
        .notif-bell .badge {
            position: absolute;
            top: -8px;
            right: -15px;
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #00a896 0%, #028090 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            position: relative;
        }
        .profile-avatar {
            background: rgba(255,255,255,0.2);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .profile-info h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .profile-info p {
            opacity: 0.9;
            margin: 5px 0;
        }
        .profile-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
        }
        
        /* Logout Button in Profile */
        .logout-btn-profile {
            position: absolute;
            bottom: 20px;
            right: 30px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .logout-btn-profile:hover {
            background: #e53e3e;
            border-color: #e53e3e;
            transform: translateY(-2px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .icon { font-size: 32px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #00a896; }
        .stat-card .label { color: #718096; font-size: 13px; margin-top: 5px; }
        
        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        /* Form Groups */
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
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #00a896;
            box-shadow: 0 0 0 3px rgba(0,168,150,0.1);
        }
        
        .btn-save {
            background: #00a896;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-save:hover { background: #008f80; transform: translateY(-2px); }
        
        .alert-success {
            background: #c6f6d5;
            color: #276749;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .info-text {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr; }
            .navbar { padding: 15px 20px; }
            .profile-header { flex-direction: column; text-align: center; }
            .logout-btn-profile { position: static; margin-top: 20px; width: 100%; }
            .nav-links { gap: 15px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo">
        <span>Lost & Found</span>
    </div>
    <div class="nav-links">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php">Dashboard</a>
        <?php endif; ?>
        <a href="dashboard.php">Home</a>
        <a href="browse_items.php">Browse Items</a>
        <a href="my_reports.php">Reports</a>
        <a href="my_claims.php">Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php" class="active">Profile</a>
    </div>
</nav>

<div class="profile-container">
    
    <?php if ($message): ?>
        <div class="alert-success">✅ <?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert-error">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Profile Header with Logout Button -->
    <div class="profile-header">
        <div class="profile-avatar">
            👤
        </div>
        <div class="profile-info">
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <p>📧 <?php echo htmlspecialchars($user['email']); ?></p>
            <p>📱 <?php echo htmlspecialchars($user['phone_number'] ?? 'Not set'); ?></p>
            <span class="profile-badge">
                <?php echo $user['role'] == 'admin' ? '👑 Administrator' : '🎓 Student'; ?>
            </span>
            <?php if ($user['last_login']): ?>
                <p style="font-size: 12px; margin-top: 10px;">Last login: <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Logout Button INSIDE Profile -->
        <form method="POST">
            <button type="submit" name="logout" class="logout-btn-profile" onclick="return confirm('Are you sure you want to logout?')">
                🚪 Logout
            </button>
        </form>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">📦</div>
            <div class="number"><?php echo $stats['total_items']; ?></div>
            <div class="label">Items Reported</div>
        </div>
        <div class="stat-card">
            <div class="icon">📋</div>
            <div class="number"><?php echo $stats['total_claims']; ?></div>
            <div class="label">Claims Made</div>
        </div>
        <div class="stat-card">
            <div class="icon">🔍</div>
            <div class="number"><?php echo $stats['items_lost']; ?></div>
            <div class="label">Lost Items</div>
        </div>
        <div class="stat-card">
            <div class="icon">✅</div>
            <div class="number"><?php echo $stats['items_found']; ?></div>
            <div class="label">Found Items</div>
        </div>
    </div>
    
    <!-- Two Column Layout -->
    <div class="two-columns">
        
        <!-- Edit Profile Form -->
        <div class="card">
            <h3>✏️ Edit Profile</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <div class="info-text">Email cannot be changed</div>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="Enter your phone number">
                </div>
                <button type="submit" name="update_profile" class="btn-save">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password Form -->
        <div class="card">
            <h3>🔐 Change Password</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                    <div class="info-text">Minimum 6 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-save">Change Password</button>
            </form>
        </div>
        
    </div>
    
</div>

<?php include('includes/footer.php'); ?>

</body>
</html>