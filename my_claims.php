<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'] ?? 0;

// Get all claims submitted by this user
$claims_query = mysqli_query($conn, "
    SELECT c.*, i.item_name, i.item_type, i.image_path, i.location 
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    WHERE c.user_id = '$user_id'
    ORDER BY c.claim_date DESC
");

// Get all returns submitted by this user
$returns_query = mysqli_query($conn, "
    SELECT r.*, i.item_name, i.item_type, i.image_path, i.location 
    FROM item_returns r
    JOIN items i ON r.item_id = i.item_id
    WHERE r.finder_user_id = '$user_id'
    ORDER BY r.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims & Returns - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #2d3748;
        }
        .page-header p {
            color: #718096;
            font-size: 14px;
        }
        
        .section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section h2 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .claim-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            gap: 15px;
            flex-wrap: wrap;
        }
        .claim-card:last-child { border-bottom: none; }
        
        .claim-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: #edf2f7;
        }
        .no-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: #edf2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .claim-info {
            flex: 1;
        }
        .claim-info h4 {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .claim-info p {
            font-size: 13px;
            color: #718096;
            margin: 2px 0;
        }
        
        .status-pending {
            background: #fefcbf;
            color: #b7791f;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-approved {
            background: #c6f6d5;
            color: #276749;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-rejected {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .claim-card { flex-direction: column; text-align: center; }
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
        <a href="my_claims.php" class="active">Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php" class="notif-bell">
            Notifications
            <?php if ($notif_count > 0): ?>
                <span class="badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1>📋 My Claims & Returns</h1>
        <p>Track the status of items you have claimed or returned</p>
    </div>
    
    <!-- Claims Section -->
    <div class="section">
        <h2>📌 Claims Submitted</h2>
        <?php if (mysqli_num_rows($claims_query) > 0): ?>
            <?php while ($claim = mysqli_fetch_assoc($claims_query)): ?>
                <div class="claim-card">
                    <?php if (!empty($claim['image_path']) && file_exists("uploads/" . $claim['image_path'])): ?>
                        <img src="uploads/<?php echo $claim['image_path']; ?>" class="claim-img" alt="Item">
                    <?php else: ?>
                        <div class="no-img">📦</div>
                    <?php endif; ?>
                    
                    <div class="claim-info">
                        <h4><?php echo htmlspecialchars($claim['item_name']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($claim['location']); ?></p>
                        <p>📅 Submitted: <?php echo date('d/m/Y', strtotime($claim['claim_date'])); ?></p>
                        <?php if (!empty($claim['claim_text'])): ?>
                            <p>📝 Proof: <?php echo htmlspecialchars(substr($claim['claim_text'], 0, 50)); ?>...</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <span class="status-<?php echo $claim['status']; ?>">
                            <?php echo ucfirst($claim['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No claims submitted yet.</p>
                <p style="font-size: 13px;">Go to <a href="browse_items.php">Browse Items</a> to claim an item.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Returns Section -->
    <div class="section">
        <h2>📌 Returns Submitted (I Found This)</h2>
        <?php if (mysqli_num_rows($returns_query) > 0): ?>
            <?php while ($return = mysqli_fetch_assoc($returns_query)): ?>
                <div class="claim-card">
                    <?php if (!empty($return['image_path']) && file_exists("uploads/" . $return['image_path'])): ?>
                        <img src="uploads/<?php echo $return['image_path']; ?>" class="claim-img" alt="Item">
                    <?php else: ?>
                        <div class="no-img">📦</div>
                    <?php endif; ?>
                    
                    <div class="claim-info">
                        <h4><?php echo htmlspecialchars($return['item_name']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($return['location']); ?></p>
                        <p>📅 Submitted: <?php echo date('d/m/Y', strtotime($return['created_at'])); ?></p>
                        <?php if (!empty($return['message'])): ?>
                            <p>📝 Message: <?php echo htmlspecialchars(substr($return['message'], 0, 50)); ?>...</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <span class="status-<?php echo $return['status']; ?>">
                            <?php echo ucfirst($return['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No returns submitted yet.</p>
                <p style="font-size: 13px;">Go to <a href="browse_items.php">Browse Items</a> to report a found item.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('includes/footer.php'); ?>
</body>
</html>