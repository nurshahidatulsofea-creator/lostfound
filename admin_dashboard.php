<?php
session_start();
include('config/db.php');


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get notification count for admin
$pending_complaints = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'"
))['total'] ?? 0;



$total_users = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM users WHERE role = 'user'"
))['total'] ?? 0;

$banned_users = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM users WHERE is_banned = 1"
))['total'] ?? 0;

$admin_count = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM users WHERE role = 'admin'"
))['total'] ?? 0;


$total_items = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items"
))['total'] ?? 0;

$pending_items = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE status = 'pending'"
))['total'] ?? 0;

$claimed_items = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE status = 'claimed'"
))['total'] ?? 0;

$lost_items = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE item_type = 'lost'"
))['total'] ?? 0;

$found_items = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM items WHERE item_type = 'found'"
))['total'] ?? 0;


$resolution = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) as resolved
    FROM items
"));
$resolution_rate = ($resolution['total'] > 0) ? round(($resolution['resolved'] / $resolution['total']) * 100, 1) : 0;


$recent_items = mysqli_query($conn, "
    SELECT i.*, u.name as user_name 
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    ORDER BY i.created_at DESC
    LIMIT 5
");


$recent_complaints = mysqli_query($conn, "
    SELECT c.*, u.name as user_name 
    FROM complaints c
    JOIN users u ON c.user_id = u.user_id
    ORDER BY c.created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UMPSA Lost & Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
        /* Navbar */
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
        .btn-logout {
            background: #00a896;
            color: white !important;
            padding: 8px 20px;
            border-radius: 25px;
        }
        .btn-logout:hover { background: #008f80; }
        
        /* Main Container */
        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #00a896 0%, #028090 100%);
            color: white;
            padding: 35px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .welcome-section h1 { font-size: 28px; margin-bottom: 8px; }
        .welcome-section p { opacity: 0.9; font-size: 15px; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-card .icon { font-size: 35px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2d3748; margin: 8px 0; }
        .stat-card .label { color: #718096; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .stat-card.pending .number { color: #ed8936; }
        .stat-card.resolved .number { color: #48bb78; }
        .stat-card.danger .number { color: #e53e3e; }
        
        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card h3 a {
            font-size: 13px;
            color: #00a896;
            text-decoration: none;
            font-weight: normal;
        }
        .card h3 a:hover { text-decoration: underline; }
        
        /* Item List */
        .item-list { list-style: none; }
        .item-list li {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .item-list li:last-child { border-bottom: none; }
        .item-name { font-weight: 600; color: #2d3748; }
        .item-meta { font-size: 12px; color: #a0aec0; margin-top: 5px; }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-pending { background: #fefcbf; color: #b7791f; }
        .badge-claimed { background: #c6f6d5; color: #276749; }
        .badge-lost { background: #fed7d7; color: #9b2c2c; }
        .badge-found { background: #bee3f8; color: #2a69ac; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .quick-btn {
            background: #f7fafc;
            padding: 18px 15px;
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            color: #2d3748;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        .quick-btn:hover {
            background: #00a896;
            color: white;
            transform: translateY(-2px);
        }
        .quick-btn .emoji { font-size: 28px; display: block; margin-bottom: 8px; }
        .quick-btn span { font-size: 14px; font-weight: 600; }
        
        /* Responsive */
        @media (max-width: 900px) {
            .two-columns { grid-template-columns: 1fr; }
            .navbar { padding: 15px 20px; }
            .dashboard-container { padding: 0 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
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
        <a href="admin_dashboard.php" class="active">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_analytics.php">Analytics</a>
        <a href="admin_complaints.php">Complaints 
            <?php if ($pending_complaints > 0): ?>
                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;"><?php echo $pending_complaints; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_settings.php">Settings</a>
        <a href="dashboard.php">Home</a>
       <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="dashboard-container">
    
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! 👋</h1>
        <p>Here's what's happening with your Lost & Found system today.</p>
    </div>
    
    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">👥</div>
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">Total Users</div>
            <small style="color: #a0aec0;"><?php echo $banned_users; ?> banned</small>
        </div>
        <div class="stat-card">
            <div class="icon">📦</div>
            <div class="number"><?php echo $total_items; ?></div>
            <div class="label">Total Items</div>
            <small style="color: #a0aec0;"><?php echo $pending_items; ?> pending</small>
        </div>
        <div class="stat-card resolved">
            <div class="icon">✅</div>
            <div class="number"><?php echo $resolution_rate; ?>%</div>
            <div class="label">Resolution Rate</div>
            <small style="color: #a0aec0;"><?php echo $resolution['resolved']; ?>/<?php echo $resolution['total']; ?> resolved</small>
        </div>
        <div class="stat-card">
            <div class="icon">📞</div>
            <div class="number <?php echo $pending_complaints > 0 ? 'pending' : ''; ?>"><?php echo $pending_complaints; ?></div>
            <div class="label">Pending Complaints</div>
        </div>
    </div>
    
    <!-- Two Column Layout -->
    <div class="two-columns">
        
        <!-- Recent Items -->
        <div class="card">
            <h3>
                📋 Recent Items
                <a href="browse_items.php">View all →</a>
            </h3>
            <ul class="item-list">
                <?php if (mysqli_num_rows($recent_items) > 0): ?>
                    <?php while ($item = mysqli_fetch_assoc($recent_items)): ?>
                        <li>
                            <div>
                                <div class="item-name"><?php echo htmlspecialchars(substr($item['item_name'], 0, 30)); ?></div>
                                <div class="item-meta">
                                    <?php echo htmlspecialchars($item['user_name']); ?> • 
                                    <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-<?php echo $item['status'] == 'claimed' ? 'claimed' : 'pending'; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                                <span class="badge badge-<?php echo $item['item_type']; ?>">
                                    <?php echo ucfirst($item['item_type']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li style="text-align: center; color: #a0aec0;">No items yet</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Recent Complaints -->
        <div class="card">
            <h3>
                📞 Recent Complaints
                <a href="admin_complaints.php">View all →</a>
            </h3>
            <ul class="item-list">
                <?php if (mysqli_num_rows($recent_complaints) > 0): ?>
                    <?php while ($complaint = mysqli_fetch_assoc($recent_complaints)): ?>
                        <li>
                            <div>
                                <div class="item-name"><?php echo htmlspecialchars(substr($complaint['subject'], 0, 35)); ?></div>
                                <div class="item-meta">
                                    <?php echo htmlspecialchars($complaint['user_name']); ?> • 
                                    <?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-<?php echo $complaint['status']; ?>">
                                    <?php echo ucfirst($complaint['status']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li style="text-align: center; color: #a0aec0;">No complaints yet</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <h3>⚡ Quick Actions</h3>
        <div class="quick-actions">
            <a href="admin_users.php" class="quick-btn">
                <div class="emoji">👥</div>
                <span>Manage Users</span>
            </a>
            <a href="admin_complaints.php" class="quick-btn">
                <div class="emoji">📞</div>
                <span>View Complaints</span>
            </a>
            <a href="admin_analytics.php" class="quick-btn">
                <div class="emoji">📊</div>
                <span>View Analytics</span>
            </a>
            <a href="admin_settings.php" class="quick-btn">
                <div class="emoji">⚙️</div>
                <span>System Settings</span>
            </a>
            <a href="browse_items.php?status=pending" class="quick-btn">
                <div class="emoji">📋</div>
                <span>Pending Items</span>
            </a>
            <a href="my_reports.php" class="quick-btn">
                <div class="emoji">📝</div>
                <span>My Reports</span>
            </a>
        </div>
    </div>
    
</div>

</body>
</html>