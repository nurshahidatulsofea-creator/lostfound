<?php
session_start();
include('config/db.php');
include_once('telegram_notify.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = intval($_GET['item_id'] ?? 0);

$item = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM items WHERE item_id = $item_id AND user_id = '$user_id'"
));

if (!$item) {
    header("Location: my_reports.php");
    exit();
}

if (isset($_POST['action']) && isset($_POST['claim_id'])) {
    $claim_id  = intval($_POST['claim_id']);
    $action    = $_POST['action'];
    $new_status = ($action == 'approve') ? 'approved' : 'rejected';

    mysqli_query($conn,
        "UPDATE claims SET status = '$new_status' WHERE claim_id = $claim_id"
    );

    if ($new_status == 'approved') {
        mysqli_query($conn,
            "UPDATE items SET status = 'claimed' WHERE item_id = $item_id"
        );

        mysqli_query($conn,
            "UPDATE claims SET status = 'rejected' 
             WHERE item_id = $item_id AND claim_id != $claim_id"
        );
    }

    $claimer = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT user_id FROM claims WHERE claim_id = $claim_id"
    ));

    $notif_msg = mysqli_real_escape_string($conn,
        $new_status == 'approved'
            ? "Your claim for '" . $item['item_name'] . "' has been approved! Contact the owner to collect."
            : "Your claim for '" . $item['item_name'] . "' has been rejected by the owner."
    );

    // ✅ Telegram notification to claimer
    if ($new_status == 'approved') {
        sendTelegram($claimer['user_id'], "✅ Your claim for " . $item['item_name'] . " has been approved!\n\nContact the owner to collect your item.");
    } else {
        sendTelegram($claimer['user_id'], "❌ Your claim for " . $item['item_name'] . " has been rejected.");
    }

    mysqli_query($conn,
        "INSERT INTO notifications (user_id, item_id, message)
         VALUES ('{$claimer['user_id']}', '$item_id', '$notif_msg')"
    );

    $msg_type = $new_status == 'approved' ? 'approved' : 'rejected';
    echo "<script>alert('Claim has been " . ucfirst($new_status) . "!'); window.location.href='claim_details.php?item_id=$item_id';</script>";
    exit();
}

// Ambil semua claim requests untuk item ni
$claims = mysqli_query($conn,
    "SELECT claims.*, users.name, users.email, users.phone_number
     FROM claims 
     JOIN users ON claims.user_id = users.user_id
     WHERE claims.item_id = $item_id
     ORDER BY claims.claim_date DESC"
);

$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Details - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body { background-color: #f7fafc; }

        .page-container { max-width: 700px; margin: 40px auto; padding: 0 20px; }

        .item-summary {
            background: white; border-radius: 14px;
            padding: 20px 24px; border: 1px solid #e2e8f0;
            display: flex; align-items: center;
            gap: 16px; margin-bottom: 24px;
        }
        .item-summary img { width: 70px; height: 70px; object-fit: cover; border-radius: 10px; }
        .no-img {
            width: 70px; height: 70px; border-radius: 10px;
            background: #edf2f7; display: flex;
            align-items: center; justify-content: center;
            font-size: 11px; color: #a0aec0; text-align: center;
        }
        .item-summary h3 { font-size: 17px; font-weight: 700; color: #2d3748; margin: 0 0 4px; }
        .item-summary p  { font-size: 13px; color: #718096; margin: 2px 0 0; }

        .claim-card {
            background: white; border-radius: 14px;
            padding: 24px; border: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }
        .claim-card.approved { border: 2px solid #9ae6b4; background: #f0fff4; }
        .claim-card.rejected { opacity: 0.6; }

        .finder-header {
            display: flex; align-items: center;
            gap: 12px; margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: #c6f6d5; display: flex;
            align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700;
            color: #276749; flex-shrink: 0;
        }
        .finder-header strong { font-size: 15px; color: #2d3748; display: block; }
        .finder-header span   { font-size: 13px; color: #718096; }

        .detail-row { display: flex; gap: 10px; margin-bottom: 14px; align-items: flex-start; }
        .detail-label {
            font-size: 12px; font-weight: 700; color: #718096;
            text-transform: uppercase; min-width: 120px; padding-top: 2px;
        }
        .detail-value { font-size: 14px; color: #2d3748; line-height: 1.5; }

        .proof-box {
            background: #fffaf0; border-radius: 8px;
            padding: 12px 14px; font-size: 14px;
            color: #744210; line-height: 1.6;
            font-style: italic; border: 1px dashed #f6ad55;
        }
        .proof-img {
            width: 100%; max-width: 300px;
            border-radius: 8px; border: 1px solid #e2e8f0;
            margin-top: 6px;
        }

        .status-pill {
            display: inline-block; padding: 4px 12px;
            border-radius: 20px; font-size: 12px; font-weight: 700;
        }
        .pill-pending  { background: #fefcbf; color: #b7791f; }
        .pill-approved { background: #c6f6d5; color: #276749; }
        .pill-rejected { background: #fed7d7; color: #9b2c2c; }

        /* Action buttons - Approve / Reject / Chat */
        .action-buttons { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .btn-approve {
            flex: 1; background: #38a169; color: white;
            border: none; padding: 11px;
            border-radius: 8px; font-size: 13px;
            font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .btn-approve:hover { background: #2f855a; }
        .btn-reject {
            flex: 1; background: #e53e3e; color: white;
            border: none; padding: 11px;
            border-radius: 8px; font-size: 13px;
            font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .btn-reject:hover { background: #c53030; }
        .btn-chat {
            flex: 1; background: #0ea5e9; color: white;
            border: none; padding: 11px;
            border-radius: 8px; font-size: 13px;
            font-weight: bold;
            cursor: pointer; text-decoration: none;
            text-align: center;
            transition: 0.3s;
            display: inline-block;
        }
        .btn-chat:hover { background: #0284c7; }

        .date-tag { font-size: 12px; color: #a0aec0; margin-top: 14px; text-align: right; }

        .empty-state {
            background: white; border-radius: 14px;
            padding: 60px 20px; text-align: center;
            border: 1px solid #e2e8f0;
        }

        .btn-back {
            display: inline-block; margin-bottom: 20px;
            font-size: 14px; color: #718096;
            text-decoration: none; font-weight: 600;
        }
        .btn-back:hover { color: #2d3748; }

        .notif-bell {
            position: relative; margin-left: 20px;
            text-decoration: none; color: #4a5568;
            font-size: 14px; font-weight: 600;
        }
        .notif-bell .badge-notif {
            position: absolute; top: -8px; right: -10px;
            background: #e53e3e; color: white; border-radius: 50%;
            width: 18px; height: 18px; font-size: 11px;
            display: flex;
            align-items: center; justify-content: center;
            font-weight: bold;
        }

        .claimed-banner {
            background: #c6f6d5; color: #276749;
            border-radius: 10px; padding: 14px 18px;
            font-size: 14px; font-weight: 600;
            margin-bottom: 20px; border: 1px solid #9ae6b4;
            text-align: center;
        }

        @media (max-width: 768px) {
            .action-buttons { flex-direction: column; }
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
        <a href="my_reports.php">Reports</a>
        <a href="my_claims.php">Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php" class="notif-bell">
             Notifications
            <?php if ($notif_count > 0): ?>
                <span class="badge-notif">
                    <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="profile.php">Profile</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="page-container">

    <a href="notifications.php" class="btn-back">← Back</a>

    <h2 style="font-weight: 800; font-size: 22px; color: #2d3748; margin-bottom: 6px;">Claim Requests</h2>
    <p style="color: #718096; font-size: 14px; margin-bottom: 20px;">Review and approve who can claim your found item</p>

    <!-- Item already claimed banner -->
    <?php if ($item['status'] == 'claimed'): ?>
        <div class="claimed-banner">
             This item has already been claimed and resolved.
        </div>
    <?php endif; ?>

    <!-- Item summary -->
    <div class="item-summary">
        <?php if (!empty($item['image_path']) && file_exists("uploads/" . $item['image_path'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item">
        <?php else: ?>
            <div class="no-img">No Image</div>
        <?php endif; ?>
        <div>
            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
            <p>📍 <?php echo htmlspecialchars($item['location']); ?></p>
            <p><?php echo htmlspecialchars($item['description']); ?></p>
        </div>
    </div>

    <!-- Claim requests -->
    <?php if (mysqli_num_rows($claims) > 0): ?>
        <?php while ($c = mysqli_fetch_assoc($claims)): ?>
            <div class="claim-card <?php echo $c['status']; ?>">

                <div class="finder-header">
                    <div class="avatar">
                        <?php echo strtoupper(substr($c['name'], 0, 2)); ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                        <span><?php echo htmlspecialchars($c['email']); ?></span>
                    </div>
                    <div style="margin-left: auto;">
                        <?php
                        $pill = match($c['status']) {
                            'approved' => 'pill-approved',
                            'rejected' => 'pill-rejected',
                            default    => 'pill-pending'
                        };
                        ?>
                        <span class="status-pill <?php echo $pill; ?>">
                            <?php echo ucfirst($c['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Contact -->
                <div class="detail-row">
                    <span class="detail-label"> Contact</span>
                    <span class="detail-value"><?php echo htmlspecialchars($c['phone_number'] ?? '-'); ?></span>
                </div>

                <!-- Proof text -->
                <?php if (!empty($c['claim_text'])): ?>
                <div class="detail-row">
                    <span class="detail-label"> Proof</span>
                    <span class="detail-value">
                        <div class="proof-box"><?php echo htmlspecialchars($c['claim_text']); ?></div>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Proof image -->
                <?php if (!empty($c['claim_image']) && file_exists("uploads/claims/" . $c['claim_image'])): ?>
                <div class="detail-row">
                    <span class="detail-label"> Image</span>
                    <span class="detail-value">
                        <img src="uploads/claims/<?php echo htmlspecialchars($c['claim_image']); ?>"
                             alt="Proof" class="proof-img">
                    </span>
                </div>
                <?php endif; ?>

                <p class="date-tag">Submitted on <?php echo date('d/m/Y h:i A', strtotime($c['claim_date'])); ?></p>

                <!-- ✅ Action Buttons: Approve / Reject / Chat -->
                <?php if ($item['status'] == 'pending' && $c['status'] == 'pending'): ?>
                    <div class="action-buttons">
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve"
                                onclick="return confirm('Approve this claim? Item will be marked as claimed.')">
                                 ✅ Approve
                            </button>
                        </form>
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                            <button type="submit" name="action" value="reject" class="btn-reject"
                                onclick="return confirm('Reject this claim request?')">
                                 ❌ Reject
                            </button>
                        </form>
                        <!-- CHAT BUTTON -->
                        <a href="view_chat.php?item_id=<?php echo $item_id; ?>&reply_to=<?php echo $c['user_id']; ?>" class="btn-chat">
                            💬 Chat
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Show Chat button even after approved/rejected -->
                <?php if ($item['status'] == 'claimed' || $c['status'] != 'pending'): ?>
                    <div class="action-buttons">
                        <a href="view_chat.php?item_id=<?php echo $item_id; ?>&reply_to=<?php echo $c['user_id']; ?>" class="btn-chat">
                            💬 Chat with Claimer
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        <?php endwhile; ?>

    <?php else: ?>
        <div class="empty-state">
            <p style="font-size: 30px; margin-bottom: 10px;">📭</p>
            <p style="color: #a0aec0; font-size: 15px;">No claim requests for this item yet.</p>
        </div>
    <?php endif; ?>

</div>

<?php include('includes/footer.php'); ?>

</body>
</html>