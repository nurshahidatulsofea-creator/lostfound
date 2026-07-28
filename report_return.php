<?php
session_start();
include('config/db.php');
include_once('telegram_notify.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// PASTIKAN GUNA 'id' DARI URL
$item_id = intval($_GET['id'] ?? 0);

// Query untuk dapatkan item (HANYA item jenis 'lost')
$item = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM items WHERE item_id = $item_id AND item_type = 'lost'"
));

// JIKA ITEM TAK JUMPA, REDIRECT KE browse_items.php (BUKAN my_reports.php)
if (!$item) {
    echo "<script>alert('Item not found or already resolved.'); window.location.href='browse_items.php';</script>";
    exit();
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pickup_location = mysqli_real_escape_string($conn, $_POST['pickup_location']);
    $message         = mysqli_real_escape_string($conn, $_POST['message']);
    $finder_id       = $_SESSION['user_id'];

    if ($item['user_id'] == $finder_id) {
        $error = "You cannot report your own lost item as found.";
    } else {
        $check = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT return_id FROM item_returns 
             WHERE item_id = $item_id AND finder_user_id = $finder_id"
        ));

        if ($check) {
            $error = "You have already submitted a return request for this item.";
        } else {
            mysqli_query($conn,
                "INSERT INTO item_returns (item_id, finder_user_id, pickup_location, message)
                 VALUES ($item_id, $finder_id, '$pickup_location', '$message')"
            );
            
            $notif_msg = mysqli_real_escape_string($conn,
                "Someone found your lost item: " . $item['item_name'] . ". Check pickup details in My Reports!"
            );
            mysqli_query($conn,
                "INSERT INTO notifications (user_id, item_id, message)
                 VALUES ('{$item['user_id']}', '$item_id', '$notif_msg')"
            );
            
            sendTelegram($item['user_id'], "📦 Someone found your lost item: " . $item['item_name'] . "\n\nLogin to view: http://localhost/lostfound/return_details.php?item_id=" . $item_id);

            $success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I Found This Item - UMPSA Lost & Found</title>
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
        .btn-logout {
            background: #00a896;
            color: white !important;
            padding: 8px 20px;
            border-radius: 25px;
        }
        
        .claim-wrapper {
            background: #ffffff;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 560px;
            margin: 40px auto;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }
        .claim-wrapper h2 { font-size: 22px; font-weight: 800; color: #1a202c; margin: 0 0 4px; }
        .claim-wrapper .subtitle { font-size: 14px; color: #718096; margin: 0 0 24px; }

        .item-preview {
            display: flex; align-items: center; gap: 14px;
            background: #f7fafc; border: 1px solid #e2e8f0;
            border-radius: 10px; padding: 12px 16px; margin-bottom: 24px;
        }
        .item-preview img { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; }
        .no-img {
            width: 56px; height: 56px; border-radius: 8px; background: #e2e8f0;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; color: #a0aec0; text-align: center;
        }
        .item-preview .item-meta strong { font-size: 15px; font-weight: 700; color: #2d3748; display: block; }
        .item-preview .item-meta span { font-size: 13px; color: #718096; }
        .item-preview .item-meta span::before { content: '📍 '; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 700; color: #2d3748; margin-bottom: 8px; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%; padding: 12px 14px;
            border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; color: #2d3748; background: #fff;
            box-sizing: border-box; transition: border-color 0.2s; resize: vertical;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none; border-color: #38a169;
            box-shadow: 0 0 0 3px rgba(56,161,105,0.15);
        }

        .btn-submit {
            display: block; width: 100%; padding: 14px;
            background: #38a169; color: white; border: none;
            border-radius: 10px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background 0.2s; margin-top: 8px;
        }
        .btn-submit:hover { background: #2f855a; }
        .btn-cancel { display: block; text-align: center; margin-top: 14px; font-size: 14px; color: #718096; text-decoration: none; }
        .btn-cancel:hover { color: #2d3748; }

        .alert-success { background: #c6f6d5; color: #276749; padding: 14px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-error   { background: #fed7d7; color: #9b2c2c; padding: 14px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-links { gap: 15px; }
            .claim-wrapper { margin: 20px; padding: 25px; }
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
        <a href="dashboard.php">Home</a>
        <a href="browse_items.php">Browse Items</a>
        <a href="my_reports.php">My Reports</a>
        <a href="my_claims.php">My Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="claim-wrapper">

    <h2>I Found This Item</h2>
    <p class="subtitle">Please provide pickup details so the owner can collect it.</p>

    <div class="item-preview">
        <?php if (!empty($item['image_path']) && file_exists("uploads/" . $item['image_path'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item">
        <?php else: ?>
            <div class="no-img">No Image</div>
        <?php endif; ?>
        <div class="item-meta">
            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
            <span><?php echo htmlspecialchars($item['location']); ?></span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert-success">
            ✅ Your report has been submitted! The owner will be notified and can confirm the return.
        </div>
        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <a href="my_messages.php" style="flex:1; text-align:center; background:#0ea5e9; color:white; padding:14px; border-radius:10px; text-decoration:none; font-weight:bold;">
                💬 Go to Messages
            </a>
            <a href="dashboard.php" style="flex:1; text-align:center; background:#e2e8f0; color:#4a5568; padding:14px; border-radius:10px; text-decoration:none; font-weight:bold;">
                ← Dashboard
            </a>
        </div>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Where can the owner collect it?</label>
                <input type="text" name="pickup_location" required
                    placeholder="e.g. Library front desk, Block A room 3"
                    value="<?php echo htmlspecialchars($_POST['pickup_location'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Message to owner <span style="font-weight: 400; color: #a0aec0;">(optional)</span></label>
                <textarea name="message" rows="4"
                    placeholder="e.g. Found it near the canteen, kept it safe"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Return Report</button>
        </form>

        <a href="browse_items.php" class="btn-cancel">← Cancel</a>

    <?php endif; ?>

</div>

<?php include('includes/footer.php'); ?>

</body>
</html>