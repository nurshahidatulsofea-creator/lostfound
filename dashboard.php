<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '{$_SESSION['user_id']}' AND is_read = 0"
))['total'];

$query = "SELECT * FROM items 
          WHERE status = 'pending' 
          OR (status = 'claimed' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY))
          ORDER BY created_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .report-buttons { display: flex; gap: 10px; }
        .btn-red, .btn-green { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; color: white; transition: 0.3s; }
        .btn-red { background-color: #e53e3e; }
        .btn-green { background-color: #48bb78; }
        .btn-red:hover { background-color: #c53030; }
        .btn-green:hover { background-color: #38a169; }
        .status-pending { background-color: #fefcbf; color: #b7791f; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .status-claimed { background-color: #c6f6d5; color: #276749; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .status-expired { background-color: #fed7d7; color: #9b2c2c; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .already-claimed-bar { display: block; margin-top: 12px; text-align: center; background: #edf2f7; color: #a0aec0; padding: 10px; border-radius: 8px; font-size: 12px; font-weight: bold; }
        .expired-bar { display: block; margin-top: 12px; text-align: center; background: #fed7d7; color: #9b2c2c; padding: 10px; border-radius: 8px; font-size: 12px; font-weight: bold; }
        .item-info p { word-wrap: break-word; overflow-wrap: break-word; white-space: normal; margin: 4px 0; font-size: 13px; line-height: 1.5; }

        /* Navbar styling */
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
        
        /* Button styling */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .btn-chat {
            flex: 1;
            text-align: center;
            background: #0ea5e9;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            transition: all 0.2s ease-in-out;
        }
        .btn-chat:hover {
            background: #0284c7;
            transform: translateY(-1px);
        }
        .btn-claim {
            flex: 1;
            text-align: center;
            background: #00a896;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            transition: all 0.2s ease-in-out;
        }
        .btn-claim:hover {
            background: #008f80;
            transform: translateY(-1px);
        }
        .btn-found {
            flex: 1;
            text-align: center;
            background: #38a169;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            transition: all 0.2s ease-in-out;
        }
        .btn-found:hover {
            background: #2f855a;
            transform: translateY(-1px);
        }
        
        .my-listing-bar {
            display: block;
            margin-top: 12px;
            text-align: center;
            background: #f7fafc;
            color: #4a5568;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            border: 1px dashed #cbd5e0;
        }
        
        .days-ago {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-links { gap: 15px; }
            .button-group { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo" style="height: 40px;">
        <span style="font-weight: bold; margin-left: 10px;">Lost & Found</span>
    </div>
    <div class="nav-links">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php">Dashboard</a>
        <?php endif; ?>
        <a href="dashboard.php" class="active">Home</a>
        <a href="browse_items.php">Browse Items</a>
        <a href="my_reports.php"> Reports</a>
        <a href="my_claims.php">Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php" class="notif-bell">
            Notifications
            <?php if ($notif_count > 0): ?>
                <span class="badge">
                    <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="wide-container">
    <header class="header-section">
        <div>
            <h2 style="font-weight: 800; font-size: 24px; color: #2d3748;">Recently Reported Items</h2>
            <p style="color: #718096; font-size: 14px;">View the latest lost and found items reported on campus</p>
        </div>
        <div class="report-buttons">
            <a href="report_lost.php" class="btn-red">Report Lost</a>
            <a href="report_found.php" class="btn-green">Report Found</a>
        </div>
    </header>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 30px;">
    <div class="item-grid">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                $status = $row['status'] ?? 'pending';
                $owner_id = $row['user_id'] ?? null;
                $is_owner = (isset($_SESSION['user_id']) && $owner_id == $_SESSION['user_id']);
                
                // Check if claimed item is older than 30 days
                $is_expired = false;
                if ($status == 'claimed') {
                    $claimed_date = strtotime($row['created_at']);
                    $days_old = floor((time() - $claimed_date) / (60 * 60 * 24));
                    if ($days_old > 30) {
                        $is_expired = true;
                    }
                }
                ?>
                
                <?php if (!$is_expired || $is_owner): ?>
                <div class="item-card">
                    <div class="type-badge <?php echo ($row['item_type'] == 'lost') ? 'bg-lost' : 'bg-found'; ?>">
                        <?php echo strtoupper($row['item_type']); ?>
                    </div>
                    <div class="img-placeholder">
                        <?php if (!empty($row['image_path']) && file_exists("uploads/" . $row['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($row['image_path']); ?>" alt="Item Image">
                        <?php else: ?>
                            <div style="background: #edf2f7; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <span style="color: #a0aec0; font-size: 12px;">No Image Provided</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <p><strong>Name :</strong> <?php echo htmlspecialchars($row['item_name']); ?></p>
                        <p><strong>Location :</strong> <?php echo htmlspecialchars($row['location']); ?></p>
                        <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($row['item_date'])); ?></p>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                        
                        <?php if ($is_expired && $is_owner): ?>
                            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                                <span class="status-expired">Expired (Archived)</span>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                                <?php if ($status == 'claimed'): ?>
                                    <span class="status-claimed">Claimed</span>
                                <?php else: ?>
                                    <span class="status-pending">Pending</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_owner): ?>
                            <span class="my-listing-bar">My Listing Record</span>
                        <?php else: ?>
                            <?php if ($status == 'pending'): ?>
                                <div class="button-group">
                                    <a href="view_chat.php?item_id=<?php echo $row['item_id']; ?>" class="btn-chat">
                                        💬 Chat Owner
                                    </a>
                                    <?php if ($row['item_type'] == 'found'): ?>
                                        <a href="claim_item.php?id=<?php echo $row['item_id']; ?>" class="btn-claim">
                                            📋 Claim with Proof
                                        </a>
                                    <?php else: ?>
                                        <a href="report_return.php?id=<?php echo $row['item_id']; ?>" class="btn-found">
                                            📋 I Found This (with Proof)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($status == 'claimed' && !$is_expired): ?>
                                <span class="already-claimed-bar">Already Claimed</span>
                            <?php elseif ($is_expired): ?>
                                <span class="expired-bar">⏰ This item has been archived (claimed &gt; 30 days)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($status == 'claimed'): ?>
                            <div class="days-ago">
                                <?php 
                                $claimed_date = strtotime($row['created_at']);
                                $days_old = floor((time() - $claimed_date) / (60 * 60 * 24));
                                echo "📅 Claimed " . $days_old . " days ago";
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; grid-column: 1/-1; color: #718096; padding: 50px;">No items reported yet.</p>
        <?php endif; ?>
    </div>
</div>
<?php include('includes/footer.php'); ?>
</body>
</html>