<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = '$user_id'");

$notifs = mysqli_query($conn,
    "SELECT notifications.*, items.item_name, items.item_type, items.user_id as item_owner_id
     FROM notifications 
     JOIN items ON notifications.item_id = items.item_id
     WHERE notifications.user_id = '$user_id'
     ORDER BY notifications.created_at DESC"
);

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
    <title>Notifications - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
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
        .btn-logout {
            background: #00a896;
            color: white !important;
            padding: 8px 20px;
            border-radius: 25px;
        }
        
        .notif-bell {
            position: relative;
            margin-left: 20px;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            font-weight: 600;
        }
        .notif-bell .badge {
            position: absolute;
            top: -8px;
            right: -10px;
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
        
        .notif-list { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .notif-item { background: white; border-radius: 12px; padding: 18px 20px; margin-bottom: 14px; border: 1px solid #e2e8f0; display: flex; gap: 16px; align-items: flex-start; transition: 0.2s; }
        .notif-item:hover { border-color: #bee3f8; background: #f0f8ff; }
        .notif-item.unread { border-left: 4px solid #3182ce; background: #ebf8ff; }
        .notif-icon { font-size: 24px; flex-shrink: 0; }
        .notif-body p { font-size: 14px; color: #2d3748; margin: 0 0 4px; }
        .notif-body span { font-size: 12px; color: #a0aec0; }
        .notif-body a { font-size: 13px; color: #00a896; text-decoration: none; font-weight: bold; margin-top: 8px; display: inline-block; }
        .notif-body a:hover { text-decoration: underline; color: #008f80; }
        .empty-state { text-align: center; padding: 80px 20px; color: #a0aec0; }
        .chat-link {
            background: #e6f7f5;
            padding: 5px 12px;
            border-radius: 20px;
            margin-top: 8px;
            display: inline-block;
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
        <a href="notifications.php" class="active">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="notif-list">
    <h2 style="font-weight: 800; font-size: 22px; margin-bottom: 6px;">🔔 Notifications</h2>
    <p style="color: #718096; font-size: 14px; margin-bottom: 24px;">Updates on your reported items and messages</p>

    <?php if (mysqli_num_rows($notifs) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($notifs)): ?>
            <div class="notif-item <?php echo !$row['is_read'] ? 'unread' : ''; ?>">
                <div class="notif-icon">
                    <?php if ($row['item_type'] == 'lost'): ?>
                        🔍
                    <?php else: ?>
                        📦
                    <?php endif; ?>
                </div>
                <div class="notif-body">
                    <p><?php echo htmlspecialchars($row['message']); ?></p>
                    <span><?php echo date('d/m/Y h:i A', strtotime($row['created_at'])); ?></span>
                    <br>
                    
                    <?php 
                    // Determine the correct link based on notification message
                    $message_lower = strtolower($row['message']);
                    
                    // For claim approval/rejection - go to chat with the other user
                    if (strpos($message_lower, 'approved') !== false || strpos($message_lower, 'rejected') !== false) {
                        // Get the item owner and the claimant
                        $claim_data = mysqli_fetch_assoc(mysqli_query($conn, "
                            SELECT c.user_id as claimant_id, i.user_id as owner_id
                            FROM claims c
                            JOIN items i ON c.item_id = i.item_id
                            WHERE c.item_id = {$row['item_id']}
                            ORDER BY c.claim_date DESC LIMIT 1
                        "));
                        
                        if ($claim_data) {
                            // Determine who to chat with
                            if ($user_id == $claim_data['owner_id']) {
                                $chat_with = $claim_data['claimant_id'];
                                $chat_role = 'Claimer';
                            } else {
                                $chat_with = $claim_data['owner_id'];
                                $chat_role = 'Owner';
                            }
                            ?>
                            <a href="view_chat.php?item_id=<?php echo $row['item_id']; ?>&reply_to=<?php echo $chat_with; ?>" class="chat-link">
                                💬 Chat with <?php echo $chat_role; ?> →
                            </a>
                            <?php
                        } else {
                            ?>
                            <a href="my_messages.php" class="chat-link">
                                💬 Go to Messages →
                            </a>
                            <?php
                        }
                    }
                    // For message notifications
                    elseif (strpos($message_lower, 'message') !== false) {
                        $msg_sender = mysqli_fetch_assoc(mysqli_query($conn, 
                            "SELECT sender_id FROM messages WHERE item_id = {$row['item_id']} AND receiver_id = $user_id ORDER BY sent_at DESC LIMIT 1"
                        ));
                        $reply_to = $msg_sender['sender_id'] ?? 0;
                        ?>
                        <a href="view_chat.php?item_id=<?php echo $row['item_id']; ?>&reply_to=<?php echo $reply_to; ?>" class="chat-link">
                            💬 View & Reply to Message →
                        </a>
                    <?php
                    }
                    // For claim requests (pending)
                    elseif (strpos($message_lower, 'claim request') !== false) {
                        ?>
                        <a href="claim_details.php?item_id=<?php echo $row['item_id']; ?>">
                            📋 View Claim Details →
                        </a>
                        <?php
                    }
                    // For return requests
                    elseif ($row['item_type'] == 'lost') {
                        ?>
                        <a href="return_details.php?item_id=<?php echo $row['item_id']; ?>">
                            📍 View Return Details →
                        </a>
                        <?php
                    }
                    // Default
                    else {
                        ?>
                        <a href="view_chat.php?item_id=<?php echo $row['item_id']; ?>">
                            💬 View Details →
                        </a>
                        <?php
                    }
                    ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <p style="font-size: 48px; margin-bottom: 15px;">🔔</p>
            <p style="margin-top: 12px; font-size: 15px;">No notifications yet.</p>
            <p style="font-size: 13px; margin-top: 6px;">You'll be notified when someone claims or finds your item.</p>
        </div>
    <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>
</body>
</html>