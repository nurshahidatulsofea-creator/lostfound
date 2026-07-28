<?php
session_start();
include('config/db.php');

// Add this after session_start() and include
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '{$_SESSION['user_id']}' AND is_read = 0"
))['total'] ?? 0;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Define dulu kat luar block
$claim_id = null;
$claim    = null;

if (isset($_GET['id'])) {
    $claim_id = mysqli_real_escape_string($conn, $_GET['id']);

    $query_text = "SELECT claims.*, items.item_name, items.item_type, items.location, items.image_path 
                   FROM claims 
                   JOIN items ON claims.item_id = items.item_id 
                   WHERE claims.claim_id = '$claim_id'";

    $query = mysqli_query($conn, $query_text);
    $claim = mysqli_fetch_assoc($query);

    if (!$claim) {
        echo "Claim record not found!";
        exit();
    }
} else {
    // Kalau takde id dalam URL, balik dashboard
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_POST['action']) && $claim_id) {
    $new_status = ($_POST['action'] == 'approve') ? 'approved' : 'rejected';

    $update = mysqli_query($conn, "UPDATE claims SET status = '$new_status' WHERE claim_id = '$claim_id'");

    if ($update) {
        if ($new_status == 'approved') {
            $item_id = $claim['item_id'];
            mysqli_query($conn, "UPDATE items SET status = 'claimed' WHERE item_id = '$item_id'");
        }

        echo "<div style='position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #c6f6d5; color: #2f855a; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); font-family: sans-serif; text-align: center; z-index: 10000; border: 2px solid #9ae6b4;'>
                <h2 style='margin-bottom: 10px;'>Success!</h2>
                <p>Status updated to <strong>" . ucfirst($new_status) . "</strong>.</p>
                <p style='font-size: 12px; margin-top: 10px; color: #666;'>Redirecting...</p>
              </div>";

        echo "<script>setTimeout(function() { window.location.href = 'admin_dashboard.php'; }, 2000);</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Approval - Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f4f7f6; }

        .navbar { background: #fff; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .nav-brand { display: flex; align-items: center; font-weight: bold; color: #333; }
        .nav-links a { margin-left: 20px; text-decoration: none; color: #4a5568; font-size: 14px; font-weight: 600; }
        .nav-links a.active { color: #e53e3e !important; font-weight: bold; }
        .btn-logout { background: #00a896; color: #fff !important; padding: 8px 20px; border-radius: 20px; transition: 0.3s; text-decoration: none; }
        .btn-logout:hover { background: #008f80; }

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; text-align: left; }
        .btn-back { background: #cbd5e0; padding: 8px 20px; border-radius: 8px; text-decoration: none; color: #333; font-size: 13px; font-weight: bold; display: inline-block; margin-bottom: 20px; transition: 0.3s; }
        .btn-back:hover { background: #adb5bd; }

        .view-card { background: #fff; padding: 50px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .view-card h3 { margin-bottom: 30px; font-size: 24px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; font-weight: 800; }

        .info-row { display: flex; margin-bottom: 25px; font-size: 16px; align-items: flex-start; }
        .info-label { width: 200px; font-weight: bold; color: #555; }
        .info-value { flex: 1; color: #333; }

        .reason-box { background: #fffaf0; padding: 15px; border-radius: 10px; border: 1px dashed #f6ad55; color: #744210; font-style: italic; line-height: 1.5; }

        .img-box { width: 150px; height: 100px; background: #eee; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; }
        .img-box img { width: 100%; height: 100%; object-fit: cover; }

        .action-buttons { display: flex; justify-content: center; gap: 20px; margin-top: 50px; }
        .btn-approve { background: #76c852; color: white; border: none; padding: 15px 40px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.3s; }
        .btn-reject  { background: #d00000; color: white; border: none; padding: 15px 40px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 16px; transition: 0.3s; }
        .btn-approve:hover { background: #5eb13a; transform: translateY(-2px); }
        .btn-reject:hover  { background: #a50000; transform: translateY(-2px); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-brand">
            <img src="assets/images/umpsa-logo.png" alt="Logo" style="height: 35px; margin-right: 10px;">
            Lost & Found
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="active">Dashboard</a>
            <a href="dashboard.php">Home</a>
            <a href="browse_items.php">Browse Items</a>
            <a href="my_reports.php">Reports</a>
            <a href="my_claims.php">Claims</a>
            <a href="notifications.php" class="notif-bell">
                 Notifications
                <?php if ($notif_count > 0): ?>
                    <span class="badge">
                        <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                    </span>
                <?php endif; ?>
            <a href="profile.php">Profile</a>
        </div>
    </nav>

    <div class="container">
        <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>

        <div class="view-card">
            <h3>Claim Approval Details</h3>

            <div class="info-row">
                <div class="info-label">Item Name :</div>
                <div class="info-value"><?php echo htmlspecialchars($claim['item_name']); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Item Type :</div>
                <div class="info-value"><?php echo ucfirst($claim['item_type']); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Location :</div>
                <div class="info-value"><?php echo htmlspecialchars($claim['location']); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label">Claim Reason :</div>
                <div class="info-value">
                    <div class="reason-box">
                        <?php echo !empty($claim['claim_text']) ? htmlspecialchars($claim['claim_text']) : "No reason provided."; ?>
                    </div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Current Status :</div>
                <div class="info-value" style="font-weight: bold; color: #b7791f; text-transform: uppercase;">
                    <?php echo ucfirst($claim['status']); ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Item Image :</div>
                <div class="info-value">
                    <div class="img-box">
                        <?php if (!empty($claim['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($claim['image_path']); ?>" alt="Item">
                        <?php else: ?>
                            No Image
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form action="" method="POST" class="action-buttons">
                <button type="submit" name="action" value="approve" class="btn-approve">Approve Claim</button>
                <button type="submit" name="action" value="reject"  class="btn-reject">Reject Claim</button>
            </form>
        </div>
    </div>

</body>
</html>