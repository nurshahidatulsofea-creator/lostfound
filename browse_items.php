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

$user_id = $_SESSION['user_id'];
$search   = isset($_GET['search'])   ? $_GET['search']   : "";
$category = isset($_GET['category']) ? $_GET['category'] : "";

// Build the query with prepared statements to prevent SQL injection
$query = "SELECT * FROM items 
          WHERE user_id != ? 
          AND (status = 'pending' 
               OR (status = 'claimed' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)))";

$params = [];
$types = "i"; // user_id is integer

// Add search condition
if (!empty($search)) {
    $query .= " AND (item_name LIKE ? OR description LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add category condition
if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add ordering
$query .= " ORDER BY 
              CASE 
                  WHEN status = 'pending' THEN 1 
                  ELSE 2 
              END,
              created_at DESC";

// Prepare and execute the statement
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    // Bind parameters
    if (!empty($params)) {
        $bind_params = array_merge([$types, $_SESSION['user_id']], $params);
        mysqli_stmt_bind_param($stmt, ...$bind_params);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    die("Query preparation failed: " . mysqli_error($conn));
}

$categories = ["Electronics", "Documents", "Keys", "Bags", "Wallets", "Personal Belongings", "Others"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse All Items - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .status-pending {
            background-color: #fefcbf;
            color: #b7791f;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-claimed {
            background-color: #c6f6d5;
            color: #276749;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-expired {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .already-claimed-bar {
            display: block;
            margin-top: 12px;
            text-align: center;
            background: #edf2f7;
            color: #a0aec0;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }
        .expired-bar {
            display: block;
            margin-top: 12px;
            text-align: center;
            background: #fed7d7;
            color: #9b2c2c;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }
        .item-info p {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            margin: 4px 0;
            font-size: 13px;
            line-height: 1.5;
        }

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
        
        .days-ago {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 8px;
            text-align: center;
        }
        
        .claimed-badge-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .wide-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .search-input:focus {
            outline: none;
            border-color: #00a896;
        }
        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            min-width: 150px;
        }
        .btn-search {
            padding: 12px 30px;
            background: #00a896;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-search:hover {
            background: #008f80;
        }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .item-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .type-badge {
            padding: 6px 14px;
            font-size: 11px;
            font-weight: bold;
            color: white;
            text-align: center;
            letter-spacing: 1px;
        }
        .bg-found {
            background: #38a169;
        }
        .bg-lost {
            background: #e53e3e;
        }

        .img-placeholder {
            width: 100%;
            height: 200px;
            background: #f7fafc;
            overflow: hidden;
        }
        .img-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .category-label {
            display: inline-block;
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
            align-self: flex-start;
        }

        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .nav-links { gap: 15px; }
            .button-group { flex-direction: column; gap: 8px; }
            .filter-container { flex-direction: column; }
            .search-input, .filter-select, .btn-search { width: 100%; }
            .item-grid { grid-template-columns: 1fr; }
            .wide-container { padding: 20px 15px; }
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
        <a href="browse_items.php" class="active">Browse Items</a>
        <a href="my_reports.php"> Reports</a>
        <a href="my_claims.php"> Claims</a>
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

    <header style="text-align: center; margin-bottom: 30px;">
        <h2 style="font-weight: 800; font-size: 28px; color: #2d3748;">Browse All Items</h2>
        <p style="color: #718096;">Search and filter through all lost and found items</p>
        <p style="color: #a0aec0; font-size: 13px; margin-top: 5px;">
            ℹ️ Claimed items are hidden after 30 days
        </p>
    </header>

    <form action="" method="GET" class="filter-container">
        <input type="text" name="search" class="search-input"
               placeholder="What are you looking for?"
               value="<?php echo htmlspecialchars($search); ?>">

        <select name="category" class="filter-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>" <?php if ($category == $cat) echo 'selected'; ?>>
                    <?php echo $cat; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-search">Search</button>
        <?php if (!empty($search) || !empty($category)): ?>
            <a href="browse_items.php" class="btn-search" style="background: #718096; text-decoration: none; display: inline-flex; align-items: center;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="item-grid">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                $status = $row['status'] ?? 'pending';
                
                // Check if claimed item is older than 30 days
                $is_expired = false;
                $days_old = 0;
                if ($status == 'claimed') {
                    $claimed_date = strtotime($row['created_at']);
                    $days_old = floor((time() - $claimed_date) / (60 * 60 * 24));
                    if ($days_old > 30) {
                        $is_expired = true;
                    }
                }
                ?>
                
                <?php if (!$is_expired): ?>
                <div class="item-card">

                    <div class="type-badge <?php echo ($row['item_type'] == 'found') ? 'bg-found' : 'bg-lost'; ?>">
                        <?php echo strtoupper($row['item_type']); ?>
                    </div>

                    <div class="img-placeholder">
                        <?php
                        $image_path = "uploads/" . $row['image_path'];
                        if (!empty($row['image_path']) && file_exists($image_path)):
                        ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Item Image">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #edf2f7; color: #a0aec0; font-size: 12px;">
                                No Image Provided
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="item-info">
                        <span class="category-label"><?php echo htmlspecialchars($row['category']); ?></span>

                        <p><strong>Name :</strong> <?php echo htmlspecialchars($row['item_name']); ?></p>
                        <p><strong>Location :</strong> <?php echo htmlspecialchars($row['location']); ?></p>
                        <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($row['item_date'])); ?></p>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($row['description']); ?></p>

                        <?php if ($status == 'claimed'): ?>
                            <div class="claimed-badge-container">
                                <span class="status-claimed">Claimed</span>
                            </div>
                            <div class="days-ago">
                                📅 Claimed <?php echo $days_old; ?> days ago
                                <?php if ($days_old > 25): ?>
                                    <span style="color: #ed8936;">(Will be archived in <?php echo 30 - $days_old; ?> days)</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                                <span class="status-pending">Pending</span>
                            </div>
                        <?php endif; ?>

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
                        <?php elseif ($status == 'claimed'): ?>
                            <span class="already-claimed-bar">Already Claimed</span>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endif; ?>
            <?php endwhile; ?>

        <?php else: ?>
            <div style="text-align: center; grid-column: 1 / -1; padding: 60px; background: white; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <p style="color: #718096; font-size: 16px;">No items found matching your criteria.</p>
                <a href="browse_items.php" style="color: #00a896; font-weight: bold; text-decoration: none; margin-top: 10px; display: inline-block;">Clear All Filters</a>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include('includes/footer.php'); ?>

</body>
</html>