<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get notification count
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'] ?? 0;

// Get all conversations for this user
$conversations = mysqli_query($conn, "
    SELECT DISTINCT 
        m.item_id,
        i.item_name,
        i.item_type,
        i.status,
        i.image_path,
        CASE 
            WHEN m.sender_id = '$user_id' THEN m.receiver_id 
            ELSE m.sender_id 
        END as other_user_id,
        u.name as other_user_name,
        (SELECT message_text FROM messages 
         WHERE item_id = m.item_id 
         AND ((sender_id = '$user_id' AND receiver_id = other_user_id) 
              OR (sender_id = other_user_id AND receiver_id = '$user_id'))
         ORDER BY sent_at DESC LIMIT 1) as last_message,
        (SELECT sent_at FROM messages 
         WHERE item_id = m.item_id 
         AND ((sender_id = '$user_id' AND receiver_id = other_user_id) 
              OR (sender_id = other_user_id AND receiver_id = '$user_id'))
         ORDER BY sent_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages 
         WHERE item_id = m.item_id 
         AND receiver_id = '$user_id' 
         AND is_read = 0) as unread_count
    FROM messages m
    JOIN items i ON m.item_id = i.item_id
    JOIN users u ON u.user_id = CASE 
        WHEN m.sender_id = '$user_id' THEN m.receiver_id 
        ELSE m.sender_id 
    END
    WHERE m.sender_id = '$user_id' OR m.receiver_id = '$user_id'
    GROUP BY m.item_id, other_user_id
    ORDER BY last_message_time DESC
");

// Mark messages as read when viewing a conversation
if (isset($_GET['item_id']) && isset($_GET['reply_to'])) {
    $item_id = intval($_GET['item_id']);
    $reply_to = intval($_GET['reply_to']);
    mysqli_query($conn, "UPDATE messages SET is_read = 1 
                         WHERE item_id = $item_id 
                         AND sender_id = $reply_to 
                         AND receiver_id = '$user_id'");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages - UMPSA Lost & Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
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
        
        /* Badge Warna - Found Hijau, Lost Merah */
        .badge-found {
            background: #48bb78;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
        }
        .badge-lost {
            background: #e53e3e;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #2d3748;
        }
        .page-header p {
            color: #718096;
            font-size: 14px;
        }
        
        .conversation-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
        }
        .conversation-item:hover {
            background: #f7fafc;
        }
        .conversation-item.unread {
            background: #ebf8ff;
        }
        
        .item-image {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            object-fit: cover;
            background: #edf2f7;
            margin-right: 15px;
        }
        .no-image {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: #edf2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .conversation-info {
            flex: 1;
        }
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 5px;
            flex-wrap: wrap;
        }
        .item-name {
            font-weight: bold;
            color: #2d3748;
            font-size: 16px;
        }
        .other-user {
            font-size: 12px;
            color: #718096;
            margin-left: 8px;
        }
        .timestamp {
            font-size: 11px;
            color: #a0aec0;
        }
        .last-message {
            font-size: 13px;
            color: #4a5568;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
        .unread-badge {
            background: #e53e3e;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 16px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .last-message { max-width: 150px; }
            .conversation-item { padding: 15px; }
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
        <a href="my_messages.php" class="active">Messages</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1>💬 Messages</h1>
        <p>Your conversations about lost and found items</p>
    </div>
    
    <div class="conversation-list">
        <?php if ($conversations && mysqli_num_rows($conversations) > 0): ?>
            <?php while ($chat = mysqli_fetch_assoc($conversations)): ?>
                <a href="view_chat.php?item_id=<?php echo $chat['item_id']; ?>&reply_to=<?php echo $chat['other_user_id']; ?>" 
                   class="conversation-item <?php echo ($chat['unread_count'] > 0) ? 'unread' : ''; ?>">
                    
                    <?php if (!empty($chat['image_path']) && file_exists("uploads/" . $chat['image_path'])): ?>
                        <img src="uploads/<?php echo $chat['image_path']; ?>" class="item-image" alt="Item">
                    <?php else: ?>
                        <div class="no-image">📦</div>
                    <?php endif; ?>
                    
                    <div class="conversation-info">
                        <div class="conversation-header">
                            <div>
                                <span class="item-name"><?php echo htmlspecialchars($chat['item_name']); ?></span>
                                <span class="badge-<?php echo $chat['item_type']; ?>">
                                    <?php echo ucfirst($chat['item_type']); ?>
                                </span>
                                <span class="other-user">• with <?php echo htmlspecialchars($chat['other_user_name']); ?></span>
                                <?php if ($chat['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $chat['unread_count']; ?> new</span>
                                <?php endif; ?>
                            </div>
                            <div class="timestamp">
                                <?php if ($chat['last_message_time']): ?>
                                    <?php echo date('d/m/Y h:i A', strtotime($chat['last_message_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="last-message">
                            <?php echo htmlspecialchars(substr($chat['last_message'] ?? 'No messages yet', 0, 60)); ?>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 48px; margin-bottom: 15px;">💬</p>
                <p>No conversations yet</p>
                <p style="font-size: 13px; margin-top: 10px;">When someone messages you about an item, it will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('includes/footer.php'); ?>

</body>
</html>