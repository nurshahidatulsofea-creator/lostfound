<?php
session_start();
include('config/db.php');
include_once 'match_engine.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '{$_SESSION['user_id']}' AND is_read = 0"
))['total'];

$user_id = $_SESSION['user_id'];

// Handle Mark as Completed
if (isset($_POST['mark_done'])) {
    $item_id = mysqli_real_escape_string($conn, $_POST['item_id']);
    $update_sql = "UPDATE items SET status = 'claimed' WHERE item_id = '$item_id' AND user_id = '$user_id'";
    if (mysqli_query($conn, $update_sql)) {
        echo "<script>alert('Status Updated to Resolved!'); window.location.href='my_reports.php';</script>";
        exit();
    } else {
        $error = mysqli_error($conn);
        echo "<script>alert('Database Error: $error');</script>";
    }
}

// Handle Delete Request (POST method)
if (isset($_POST['delete_item']) && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    
    // Verify the item belongs to the user
    $check_query = "SELECT item_id, image_path FROM items WHERE item_id = ? AND user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $item_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if ($row = mysqli_fetch_assoc($check_result)) {
        // Delete the item
        $delete_query = "DELETE FROM items WHERE item_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $item_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Delete the image file if it exists
            if (!empty($row['image_path']) && file_exists("uploads/" . $row['image_path'])) {
                unlink("uploads/" . $row['image_path']);
            }
            
            $_SESSION['delete_success'] = true;
            header("Location: my_reports.php");
            exit();
        }
    }
    $_SESSION['delete_error'] = true;
    header("Location: my_reports.php");
    exit();
}

// Check for session messages
$show_success = isset($_SESSION['delete_success']) ? $_SESSION['delete_success'] : false;
$show_error = isset($_SESSION['delete_error']) ? $_SESSION['delete_error'] : false;
unset($_SESSION['delete_success']);
unset($_SESSION['delete_error']);

$query  = "SELECT * FROM items WHERE user_id = '$user_id' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .item-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px; }
        .item-card { background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; position: relative; display: flex; flex-direction: column; justify-content: space-between; }
        .img-placeholder img { width: 100%; height: 200px; object-fit: cover; }
        .item-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .status-badge { display: inline-block; background: #fefcbf; color: #744210; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: bold; margin-top: 10px; align-self: flex-start; }
        .btn-done { background: #48bb78; color: white; border: none; padding: 12px; border-radius: 10px; width: 100%; font-weight: bold; cursor: pointer; margin-top: 15px; transition: 0.3s; }
        .btn-done:hover { background: #38a169; transform: translateY(-2px); }
        .badge-completed { background: #c6f6d5; color: #2f855a; padding: 10px; border-radius: 10px; text-align: center; font-weight: bold; margin-top: 15px; }
        .type-badge { position: absolute; top: 10px; right: 10px; padding: 5px 10px; border-radius: 5px; color: white; font-size: 11px; font-weight: bold; text-transform: uppercase; z-index: 10; }
        .bg-lost  { background: #e53e3e; }
        .bg-found { background: #3182ce; }
        
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
        
        .action-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-edit, .btn-delete {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #edf2f7;
            color: #4a5568;
            border: 1px solid #cbd5e0;
        }
        .btn-edit:hover {
            background: #e2e8f0;
        }
        .btn-delete {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        .btn-delete:hover {
            background: #fed7d7;
        }
        
        /* Smart Match Banner */
        .smart-match-banner {
            background: #f0fdf4;
            border: 1px dashed #22c55e;
            border-left: 4px solid #22c55e;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .smart-match-title {
            color: #166534;
            font-weight: 800;
            font-size: 13px;
            display: block;
        }
        .smart-match-desc {
            font-size: 12px;
            color: #14532d;
            margin: 4px 0 8px 0;
        }
        .smart-match-btn {
            display: inline-block;
            background: #22c55e;
            color: white;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            padding: 6px 12px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .smart-match-btn:hover {
            background: #16a34a;
        }
        
        /* Notification Messages */
        .notification-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .notification-success {
            background: #c6f6d5;
            color: #276749;
            border: 1px solid #9ae6b4;
        }
        .notification-error {
            background: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #feb2b2;
        }
        
        /* Confirmation Dialog */
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .confirm-dialog.active {
            display: flex;
        }
        .confirm-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .confirm-content h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 20px;
        }
        .confirm-content p {
            color: #718096;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .btn-confirm {
            background: #e53e3e;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-confirm:hover {
            background: #c53030;
        }
        .btn-cancel {
            background: #e2e8f0;
            color: #4a5568;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-cancel:hover {
            background: #cbd5e0;
        }
        
        /* Delete Form */
        .delete-form {
            flex: 1;
            display: inline;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-links { gap: 15px; }
            .action-group { flex-direction: column; }
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
        <a href="my_reports.php" class="active">Reports</a>
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

<div class="wide-container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <header style="margin-bottom: 30px;">
        <h2 style="font-weight: 800; font-size: 26px;">My Reported Items</h2>
        <p style="color: #718096;">Update the status of your items once they are resolved.</p>
    </header>

    <?php if ($show_success): ?>
        <div class="notification-message notification-success">
            ✅ Item successfully deleted!
        </div>
    <?php endif; ?>

    <?php if ($show_error): ?>
        <div class="notification-message notification-error">
            ❌ Failed to delete item. Please try again.
        </div>
    <?php endif; ?>

    <div class="item-grid">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="item-card" id="item-<?php echo $row['item_id']; ?>">

                    <div class="type-badge <?php echo ($row['item_type'] == 'lost') ? 'bg-lost' : 'bg-found'; ?>">
                        <?php echo strtoupper($row['item_type']); ?>
                    </div>

                    <div class="img-placeholder">
                        <?php if (!empty($row['image_path']) && file_exists("uploads/" . $row['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($row['image_path']); ?>" alt="Item Image">
                        <?php else: ?>
                            <div style="height: 200px; background: #edf2f7; display: flex; align-items: center; justify-content: center; color: #a0aec0;">No Image</div>
                        <?php endif; ?>
                    </div>

                    <div class="item-info">
                        <p style="margin: 0 0 5px 0;"><strong>Item:</strong> <?php echo htmlspecialchars($row['item_name']); ?></p>
                        <p style="margin: 0 0 5px 0;"><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></p>

                        <?php $status = $row['status'] ?? 'pending'; ?>
                        <div class="status-badge">
                            Status: <?php echo ($status == 'claimed') ? 'Claimed / Resolved' : ucfirst($status); ?>
                        </div>

                        <?php 
                        if ($status !== 'claimed') {
                            $matches = getPotentialMatches($conn, $row['item_id'], $user_id);
                            
                            if (!empty($matches)) {
                                $highest_score = $matches[0]['score'];
                                ?>
                                <div class="smart-match-banner">
                                    <span class="smart-match-title">✨ AI Smart Match Found (<?php echo $highest_score; ?>%)</span>
                                    <p class="smart-match-desc">Our system found a matching report from another user!</p>
                                   <a href="match_engine.php?item_id=<?php echo $row['item_id']; ?>" class="smart-match-btn">
                                    View Potential Match →
                                </a>
                                </div>
                                <?php
                            }
                        }
                        ?>

                        <?php if ($status !== 'claimed'): ?>
                            <div class="action-group">
                                <?php
                                    $edit_page = ($row['item_type'] == 'lost') ? 'edit_lost.php' : 'edit_report.php';
                                ?>
                                <a href="<?php echo $edit_page; ?>?id=<?php echo $row['item_id']; ?>" class="btn-edit">Edit Info</a>
                                
                                <!-- Delete Form -->
                                <form method="POST" action="my_reports.php" class="delete-form" onsubmit="return confirmDelete(<?php echo $row['item_id']; ?>)">
                                    <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                                    <button type="submit" name="delete_item" class="btn-delete" style="width:100%;">Delete</button>
                                </form>
                            </div>

                            <form action="my_reports.php" method="POST">
                                <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                                <button type="submit" name="mark_done" class="btn-done"
                                    onclick="return confirm('Confirm this item as resolved?')">
                                    Mark as Completed
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="badge-completed">Successfully Resolved</div>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 100px; background: white; border-radius: 15px;">
                <p style="color: #a0aec0;">You haven't reported any items yet.</p>
                <a href="report_lost.php" style="color: #3182ce; text-decoration: none; font-weight: bold; display: block; margin-top: 10px;">Report an Item Now</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDelete(itemId) {
        return confirm('Are you sure you want to delete this report? This will permanently remove the item.');
    }
</script>

<?php include('includes/footer.php'); ?>
</body>
</html>