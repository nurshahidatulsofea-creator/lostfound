<?php
session_start();
include('config/db.php');

// Initialize variables to avoid warnings
$item = null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$notif_count = 0;

// Security: Hanya admin boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get notification count
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications WHERE is_read = 0"
))['total'] ?? 0;

// 1. Ambil ID item dari URL
if ($id > 0) {
    $query = mysqli_query($conn, "SELECT * FROM items WHERE item_id = '$id'");
    $item = mysqli_fetch_assoc($query);
}

if (!$item) {
    echo "Item not found!";
    exit();
}

// 2. PROSES UPDATE DENGAN AUTO-DELAY REDIRECT
if (isset($_POST['action'])) {
    $new_status = ($_POST['action'] == 'verify') ? 'verified' : 'rejected';
    
    $update = mysqli_query($conn, "UPDATE items SET status = '$new_status' WHERE item_id = '$id'");
    
    if ($update) {
        echo "<div style='
                position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
                background: #c6f6d5; color: #2f855a; padding: 30px; border-radius: 15px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1); font-family: sans-serif;
                text-align: center; z-index: 10000; border: 2px solid #9ae6b4;
              '>
                <h2 style='margin-bottom: 10px;'>✅ Success!</h2>
                <p>Item status updated to <strong>" . ucfirst($new_status) . "</strong>.</p>
                <p style='font-size: 12px; margin-top: 10px; color: #666;'>Redirecting to dashboard in 2 seconds...</p>
              </div>";
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'admin_dashboard.php';
                }, 2000);
              </script>";
        exit(); 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Item - Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f4f7f6; }
        
        .navbar { 
            background: #fff; 
            padding: 15px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #e2e8f0; 
        }
        .nav-brand { display: flex; align-items: center; font-weight: bold; color: #333; }
        .nav-brand img { height: 35px; margin-right: 10px; }
        .nav-links a { margin-left: 20px; text-decoration: none; color: #4a5568; font-size: 14px; font-weight: 600; }
        .nav-links a.active { color: #e53e3e !important; font-weight: bold; } 
        .btn-logout { background: #00a896; color: #fff !important; padding: 8px 20px; border-radius: 20px; text-decoration: none; }
        .btn-logout:hover { background: #008f80; }

        .container { max-width: 850px; margin: 40px auto; padding: 0 20px; }
        
        .btn-back { 
            background: #cbd5e0; 
            padding: 8px 20px; 
            border-radius: 5px; 
            text-decoration: none; 
            color: #333; 
            font-size: 13px; 
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        .btn-back:hover { background: #adb5bd; }

        .view-card { 
            background: #fff; 
            padding: 40px; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border: 1px solid #eee;
        }
        .view-card h3 { margin-bottom: 25px; font-size: 24px; color: #333; font-weight: 800; border-bottom: 1px solid #eee; padding-bottom: 15px; }

        .info-row { display: flex; margin-bottom: 20px; font-size: 15px; border-bottom: 1px solid #f9f9f9; padding-bottom: 10px; }
        .info-label { width: 200px; font-weight: bold; color: #555; }
        .info-value { flex: 1; color: #333; }

        .img-box { 
            width: 150px; 
            height: 100px; 
            background: #eee; 
            border-radius: 8px; 
            overflow: hidden; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border: 1px solid #ddd;
        }
        .img-box img { width: 100%; height: 100%; object-fit: cover; }

        .action-buttons { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; }
        .btn-verify { background: #48bb78; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-reject { background: #e53e3e; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-verify:hover { background: #38a169; }
        .btn-reject:hover { background: #c53030; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo">
        <span>Lost & Found</span>
    </div>
    <div class="nav-links">
        <a href="admin_dashboard.php" class="active">Dashboard</a>
        <a href="dashboard.php">Home</a>
        <a href="browse_items.php">Browse Items</a>
        <a href="my_reports.php">Reports</a>
        <a href="my_claims.php">Claims</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
    
    <div class="view-card">
        <h3>Verify Reported Item</h3>

        <div class="info-row">
            <div class="info-label">Item Name :</div>
            <div class="info-value"><?php echo htmlspecialchars($item['item_name']); ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Item Type :</div>
            <div class="info-value"><?php echo ucfirst($item['item_type']); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Reported Date :</div>
            <div class="info-value"><?php echo date('d/m/Y', strtotime($item['item_date'])); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Location :</div>
            <div class="info-value"><?php echo htmlspecialchars($item['location']); ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Description :</div>
            <div class="info-value"><?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : 'No description provided.'; ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Current Status :</div>
            <div class="info-value" style="color: #b7791f; font-weight: bold; text-transform: uppercase;">
                <?php echo ucfirst($item['status']); ?>
            </div>
        </div>

        <div class="info-row" style="border-bottom: none;">
            <div class="info-label">Item Image :</div>
            <div class="info-value">
                <div class="img-box">
                    <?php if(!empty($item['image_path']) && file_exists("uploads/" . $item['image_path'])) { ?>
                        <img src="uploads/<?php echo $item['image_path']; ?>" alt="Item Image">
                    <?php } else { ?>
                        <span style="font-size: 11px; color: #999;">No Image</span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <form action="" method="POST" class="action-buttons">
            <button type="submit" name="action" value="verify" class="btn-verify">Verify Item</button>
            <button type="submit" name="action" value="reject" class="btn-reject">Reject Item</button>
        </form>
    </div>
</div>

</body>
</html>