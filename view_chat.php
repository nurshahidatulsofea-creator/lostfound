<?php
session_start();
include('config/db.php');
include_once('telegram_notify.php');

$notif_count = 0;
$user_id = $_SESSION['user_id'] ?? 0;
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
$receiver_id = 0;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'] ?? 0;

// 1. Fetch item specifications safely
$item_query = mysqli_query($conn, "SELECT * FROM items WHERE item_id = $item_id");
if (mysqli_num_rows($item_query) == 0) {
    die("Item not found or has been removed.");
}
$item = mysqli_fetch_assoc($item_query);

$owner_id = $item['user_id']; 

if ($user_id == $owner_id) {
  
    $receiver_id = isset($_GET['reply_to']) ? intval($_GET['reply_to']) : 0;
} else {
    
    $receiver_id = $owner_id;
}

if ($receiver_id > 0) {
    mysqli_query($conn, "UPDATE messages SET is_read = 1 
                         WHERE item_id = $item_id 
                         AND sender_id = $receiver_id 
                         AND receiver_id = $user_id");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $msg = mysqli_real_escape_string($conn, trim($_POST['message']));
    
  
    if ($receiver_id > 0) {
        $insert_sql = "INSERT INTO messages (item_id, sender_id, receiver_id, message_text) 
                       VALUES ($item_id, $user_id, $receiver_id, '$msg')";
        mysqli_query($conn, $insert_sql);
        
        $notif_msg = "New message received regarding item: " . mysqli_real_escape_string($conn, $item['item_name']);
        $notif_link = "view_chat.php?item_id=$item_id&reply_to=$user_id";
        mysqli_query($conn, "INSERT INTO notifications (user_id, item_id, message, link, is_read, created_at) 
                             VALUES ($receiver_id, $item_id, '$notif_msg', '$notif_link', 0, NOW())");
     
        $sender_name = $_SESSION['name'] ?? 'Someone';
        sendTelegram($receiver_id, "💬 New message from " . $sender_name . " about: " . $item['item_name'] . "\n\nReply here: http://localhost/lostfound/view_chat.php?item_id=" . $item_id . "&reply_to=" . $user_id);
        
        header("Location: view_chat.php?item_id=$item_id" . ($user_id == $owner_id ? "&reply_to=$receiver_id" : ""));
        exit();
    }
}
$history_sql = "SELECT * FROM messages 
                WHERE item_id = $item_id 
                AND ((sender_id = $user_id AND receiver_id = $receiver_id) 
                OR (sender_id = $receiver_id AND receiver_id = $user_id)) 
                ORDER BY sent_at ASC";
$history = mysqli_query($conn, $history_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Chat - UMPSA Lost & Found</title>
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
        
        /* Chat Layout */
        .chat-layout {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 600px;
        }
        .chat-header {
            background: linear-gradient(135deg, #00a896 0%, #028090 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .chat-header h3 {
            margin: 5px 0 0 0;
            font-size: 18px;
        }
        .chat-header p {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .secure-badge {
            background: rgba(255,255,255,0.2);
            font-size: 11px;
            padding: 5px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .back-nav-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.85;
            transition: 0.2s;
        }
        .back-nav-link:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        .message-stream {
            flex-grow: 1;
            padding: 25px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .msg-bubble {
            padding: 12px 18px;
            border-radius: 18px;
            max-width: 70%;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }
        .msg-sent {
            background: #00a896;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .msg-received {
            background: white;
            color: #1e293b;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .msg-time {
            font-size: 9px;
            opacity: 0.7;
            margin-top: 5px;
            text-align: right;
        }
        .msg-sent .msg-time { color: rgba(255,255,255,0.7); }
        .msg-received .msg-time { color: #94a3b8; }
        
        .chat-input-form {
            display: flex;
            padding: 20px;
            background: white;
            border-top: 1px solid #e2e8f0;
            gap: 12px;
        }
        .chat-input-form input {
            flex-grow: 1;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            outline: none;
            font-size: 14px;
            transition: 0.2s;
        }
        .chat-input-form input:focus {
            border-color: #00a896;
            box-shadow: 0 0 0 3px rgba(0,168,150,0.1);
        }
        .chat-submit-btn {
            background: #00a896;
            color: white;
            border: none;
            padding: 0 28px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }
        .chat-submit-btn:hover {
            background: #008f80;
            transform: scale(1.02);
        }
        
        .empty-chat {
            text-align: center;
            margin: auto;
            color: #94a3b8;
            font-size: 14px;
            padding: 40px;
        }
        .select-contact {
            text-align: center;
            margin: auto;
            color: #64748b;
            font-size: 14px;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .chat-layout { margin: 20px; height: 500px; }
            .msg-bubble { max-width: 85%; }
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
        <a href="my_reports.php">My Reports</a>
        <a href="my_claims.php">My Claims</a>
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

<div class="chat-layout">
    <div class="chat-header">
        <div>
            <a href="my_messages.php" class="back-nav-link">← Back to message</a>
            <h3>💬 <?php echo htmlspecialchars($item['item_name']); ?></h3>
            <p>📍 <?php echo htmlspecialchars($item['location']); ?> • <?php echo ucfirst($item['item_type']); ?></p>
        </div>
        <span class="secure-badge">🔒 Private & Secure</span>
    </div>

    <div class="message-stream">
        <?php if ($receiver_id == 0 && $user_id == $owner_id): ?>
            <div class="select-contact">
                <p style="font-size: 48px; margin-bottom: 15px;">💬</p>
                <p>Select a conversation from your notifications</p>
                <p style="font-size: 12px; margin-top: 10px;">When someone claims your item, you can chat with them here.</p>
                <a href="my_reports.php" style="display: inline-block; margin-top: 15px; color: #00a896;">View your reports →</a>
            </div>
        <?php elseif (mysqli_num_rows($history) > 0): ?>
            <?php while($msg = mysqli_fetch_assoc($history)): ?>
                <div class="msg-bubble <?php echo ($msg['sender_id'] == $user_id) ? 'msg-sent' : 'msg-received'; ?>">
                    <?php echo htmlspecialchars($msg['message_text']); ?>
                    <div class="msg-time">
                        <?php echo date('h:i A, d M', strtotime($msg['sent_at'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-chat">
                <p style="font-size: 48px; margin-bottom: 15px;">💬</p>
                <p>No messages yet</p>
                <p style="font-size: 12px; margin-top: 10px;">Send a message to start the conversation.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($receiver_id > 0 || $user_id != $owner_id): ?>
        <form class="chat-input-form" method="POST">
            <input type="text" name="message" placeholder="Type your message..." required autocomplete="off">
            <button type="submit" class="chat-submit-btn">Send →</button>
        </form>
    <?php else: ?>
        <div class="chat-input-form" style="background: #f7fafc;">
            <input type="text" placeholder="Select a conversation to start chatting..." disabled style="background: #edf2f7;">
            <button class="chat-submit-btn" disabled style="background: #cbd5e0;">Send</button>
        </div>
    <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>

</body>
</html>