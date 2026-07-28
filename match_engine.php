<?php
/**
 * Calculate match score between two items for AI matching
 * @param array $item1 First item from database
 * @param array $item2 Second item from database
 * @return int Match score (0-100)
 */
function calculateMatchScore($item1, $item2) {
    $score = 0;

    // 1. Category Matching (Weight: 40%)
    $cat1 = trim(strtolower($item1['category'] ?? ''));
    $cat2 = trim(strtolower($item2['category'] ?? ''));
    
    if (!empty($cat1) && !empty($cat2) && $cat1 === $cat2) {
        $score += 40;
    } else {
        return 0; // If categories don't match, immediate reject
    }

    // 2. Location Analysis (Weight: 30%)
    $loc1 = strtolower(trim($item1['location'] ?? ''));
    $loc2 = strtolower(trim($item2['location'] ?? ''));
    
    if ($loc1 === $loc2) {
        $score += 30;
    } elseif (strpos($loc1, $loc2) !== false || strpos($loc2, $loc1) !== false) {
        $score += 20; // Partial string match
    }

    // 3. Text Descriptive Similarity (Weight: 30%)
    $desc1 = strtolower(trim($item1['description'] ?? ''));
    $desc2 = strtolower(trim($item2['description'] ?? ''));
    
    similar_text($desc1, $desc2, $textPercent);
    $score += ($textPercent / 100) * 30;

    return round($score);
}

/**
 * Get potential matches for an item (excludes user's own items)
 */
function getPotentialMatches($conn, $item_id, $user_id) {
    $item_query = mysqli_query($conn, "SELECT * FROM items WHERE item_id = $item_id");
    $item = mysqli_fetch_assoc($item_query);
    
    if (!$item) {
        return [];
    }
    
    $opposite_type = ($item['item_type'] == 'lost') ? 'found' : 'lost';
    $category = mysqli_real_escape_string($conn, $item['category']);
    $search_term = mysqli_real_escape_string($conn, $item['item_name']);
    
    // Find potential matches
   $matches_query = mysqli_query($conn, "
    SELECT * FROM items 
    WHERE item_type = '$opposite_type' 
    AND status = 'pending'
    AND category = '$category'
    AND user_id != '$user_id'
");
    
    $potential_matches = [];
    
    if ($matches_query && mysqli_num_rows($matches_query) > 0) {
        while ($other_item = mysqli_fetch_assoc($matches_query)) {
            $score = calculateMatchScore($item, $other_item);
            if ($score >= 40) {
                $potential_matches[] = [
                    'item' => $other_item,
                    'score' => $score
                ];
            }
        }
    }
    
    usort($potential_matches, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $potential_matches;
}

// =========================================================================
// VISUAL INTERFACE COMPONENT
// This only runs when the file is accessed directly in the browser via URL
// =========================================================================
if (basename($_SERVER['PHP_SELF']) == 'match_engine.php') {
    session_start();
    include('config/db.php');

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Get notification count for navbar
    $notif_count = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as total FROM notifications 
         WHERE user_id = '$user_id' AND is_read = 0"
    ))['total'] ?? 0;

    // Fetch item_id from URL query string (?item_id=XX)
    if (!isset($_GET['item_id'])) {
        header("Location: my_reports.php");
        exit();
    }

    $item_id = intval($_GET['item_id']);

    // Fetch current user item details
    $item_query = mysqli_query($conn, "SELECT * FROM items WHERE item_id = $item_id AND user_id = '$user_id'");
    $currentItem = mysqli_fetch_assoc($item_query);

    if (!$currentItem) {
        echo "<script>alert('Report not found or unauthorized!'); window.location.href='my_reports.php';</script>";
        exit();
    }

    // Get potential matches using the function
    $matches = getPotentialMatches($conn, $item_id, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Smart Match Engine - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        
        /* Standardized Master Navbar Styling */
        .navbar {
            background: white !important;
            padding: 15px 40px !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-bottom: 1px solid #e2e8f0 !important;
            flex-wrap: wrap !important;
            width: 100% !important;
        }
        .nav-brand {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            font-weight: bold !important;
            font-size: 18px !important;
            color: #2d3748 !important;
        }
        .nav-brand img { height: 40px !important; }
        .nav-links {
            display: flex !important;
            gap: 20px !important;
            align-items: center !important;
            flex-wrap: wrap !important;
        }
        .nav-links a {
            text-decoration: none !important;
            color: #4a5568 !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            transition: color 0.2s !important;
        }
        .nav-links a:hover { color: #00a896 !important; }
        .nav-links a.active { color: #00a896 !important; border-bottom: 2px solid #00a896 !important; padding-bottom: 5px !important; }

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .btn-back { display: inline-block; margin-bottom: 20px; color: #718096; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-back:hover { color: #2d3748; }

        .current-item-summary {
            background: #ebf8ff; border: 1px solid #bee3f8; border-radius: 12px; padding: 20px; margin-bottom: 30px;
        }
        
        .match-card {
            background: white; border-radius: 14px; padding: 24px; border: 1px solid #e2e8f0; margin-bottom: 16px;
            display: flex; gap: 20px; align-items: center; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .match-score-badge {
            position: absolute; top: 15px; right: 20px; background: #e6f4ea; color: #137333;
            padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; border: 1px solid #ceead6;
        }
        .match-img { width: 90px; height: 90px; object-fit: cover; border-radius: 10px; background: #edf2f7; }
        .no-img { width: 90px; height: 90px; background: #edf2f7; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #a0aec0; }
        
        .match-details { flex: 1; }
        .match-details h3 { font-size: 18px; color: #2d3748; margin-bottom: 6px; font-weight: 700; }
        .match-details p { font-size: 14px; color: #4a5568; margin: 3px 0; }
        
        .btn-action {
            display: inline-block; background: #00a896; color: white; text-decoration: none;
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: bold; margin-top: 10px; transition: 0.2s;
        }
        .btn-action:hover { background: #008f80; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 14px; border: 1px solid #e2e8f0; color: #a0aec0; }

        @media (max-width: 768px) {
            .navbar { padding: 15px 20px !important; }
            .match-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .match-score-badge { position: static; margin-bottom: 10px; display: inline-block; }
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
        <a href="my_reports.php" class="active">My Reports</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container">
    <a href="my_reports.php" class="btn-back">← Back to My Reports</a>
    
    <div class="current-item-summary">
        <span style="font-size: 12px; font-weight: bold; text-transform: uppercase; color: #2b6cb0;">Your Report Details:</span>
        <h2 style="margin: 5px 0 0 0; color: #2d3748; font-size: 22px; font-weight: 800;"><?php echo htmlspecialchars($currentItem['item_name']); ?></h2>
        <p style="font-size: 14px; color: #4a5568; margin-top: 5px;">📍 Location: <?php echo htmlspecialchars($currentItem['location']); ?> | 📂 Category: <?php echo htmlspecialchars($currentItem['category']); ?></p>
    </div>

    <h2 style="font-weight: 800; font-size: 20px; color: #2d3748; margin-bottom: 15px;">✨ AI Smart Match Suggestions</h2>
    <p style="color: #718096; font-size: 14px; margin-bottom: 25px;">Cross-referencing engine outputs showing similar items nearby:</p>

    <div class="match-list">
        <?php 
        if (!empty($matches)) {
            foreach ($matches as $match) {
                $other_item = $match['item'];
                $score = $match['score'];
                ?>
                <div class="match-card">
                    <div class="match-score-badge">
                        Score: <?php echo $score; ?>% Match
                    </div>

                    <?php if (!empty($other_item['image_path']) && file_exists("uploads/" . $other_item['image_path'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($other_item['image_path']); ?>" class="match-img" alt="Match">
                    <?php else: ?>
                        <div class="no-img">📦</div>
                    <?php endif; ?>

                    <div class="match-details">
                        <h3><?php echo htmlspecialchars($other_item['item_name']); ?></h3>
                        <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($other_item['location']); ?></p>
                        <p><strong>📝 Description:</strong> <?php echo htmlspecialchars($other_item['description']); ?></p>
                        
                        <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
    <a href="view_chat.php?item_id=<?php echo $other_item['item_id']; ?>" class="btn-action">
        💬 Open Secure Chat
    </a>
    <?php if ($currentItem['item_type'] == 'lost'): ?>
        <a href="claim_item.php?id=<?php echo $other_item['item_id']; ?>" 
           style="display:inline-block; background:#7c3aed; color:white; text-decoration:none;
                  padding:8px 16px; border-radius:8px; font-size:13px; font-weight:bold; transition:0.2s;"
           onmouseover="this.style.background='#6d28d9'" 
           onmouseout="this.style.background='#7c3aed'">
            📋 Claim with Proof
        </a>
    <?php else: ?>
        <a href="report_return.php?id=<?php echo $other_item['item_id']; ?>" 
           style="display:inline-block; background:#d97706; color:white; text-decoration:none;
                  padding:8px 16px; border-radius:8px; font-size:13px; font-weight:bold; transition:0.2s;"
           onmouseover="this.style.background='#b45309'" 
           onmouseout="this.style.background='#d97706'">
            📋 I Found This (with Proof)
        </a>
    <?php endif; ?>
</div>
                    </div>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="empty-state">
                <p style="font-size: 40px; margin-bottom: 10px;">🔍</p>
                <p style="font-weight: 600;">No high-probability matches found right now.</p>
                <p style="font-size: 13px; margin-top: 5px;">We will highlight any newly reported matching items directly on your dashboard profile as soon as they drop!</p>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<?php include('includes/footer.php'); ?>

</body>
</html>
<?php 
} // End of visual interface wrapper check
?>