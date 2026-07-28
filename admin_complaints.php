<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_POST['respond_complaint'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $response = mysqli_real_escape_string($conn, $_POST['admin_response']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    mysqli_query($conn, "
        UPDATE complaints 
        SET admin_response = '$response', 
            status = '$status', 
            resolved_at = NOW()
        WHERE complaint_id = $complaint_id
    ");
    
   
    $complaint_query = mysqli_query($conn, "
        SELECT c.user_id, u.name 
        FROM complaints c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.complaint_id = $complaint_id
    ");
    
    if ($complaint_query && mysqli_num_rows($complaint_query) > 0) {
        $complaint_data = mysqli_fetch_assoc($complaint_query);
        
        $notif_msg = mysqli_real_escape_string($conn, "Admin responded to your complaint: " . substr($response, 0, 80) . "...");
        mysqli_query($conn, "
            INSERT INTO notifications (user_id, message, created_at) 
            VALUES ('{$complaint_data['user_id']}', '$notif_msg', NOW())
        ");
    }
    
    echo "<script>alert('Response sent to user!'); window.location.href='admin_complaints.php';</script>";
    exit();
}


$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$where = ($status_filter != 'all') ? "WHERE c.status = '$status_filter'" : "";

$complaints = mysqli_query($conn, "
    SELECT c.*, u.name as user_name, u.email as user_email, 
           i.item_name as related_item
    FROM complaints c
    JOIN users u ON c.user_id = u.user_id
    LEFT JOIN items i ON c.item_id = i.item_id
    $where
    ORDER BY 
        CASE WHEN c.status = 'pending' THEN 1 ELSE 2 END,
        c.created_at DESC
");

// Get counts for stats
$counts = ['pending' => 0, 'resolved' => 0, 'rejected' => 0];
$result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[$row['status']] = $row['count'];
    }
}

// Get notification count for navbar
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'"
))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Management - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
        .navbar { background: #fff; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .nav-brand { display: flex; align-items: center; font-weight: bold; }
        .nav-links a { margin-left: 20px; text-decoration: none; color: #4a5568; font-size: 14px; font-weight: 600; }
        .nav-links a.active { color: #3182ce; border-bottom: 2px solid #3182ce; padding-bottom: 5px; }
        .btn-logout { background: #00a896; color: #fff !important; padding: 8px 20px; border-radius: 20px; text-decoration: none; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .stats-row { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-badge { background: white; padding: 15px 25px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-badge .number { font-size: 28px; font-weight: bold; }
        .stat-badge.pending .number { color: #ed8936; }
        .stat-badge.resolved .number { color: #48bb78; }
        
        .filter-bar { margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 20px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #4a5568; }
        .filter-btn.active { background: #3182ce; color: white; border-color: #3182ce; }
        
        .complaint-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #e2e8f0;
        }
        .complaint-card.pending { border-left-color: #ed8936; }
        .complaint-card.resolved { border-left-color: #48bb78; opacity: 0.8; }
        .complaint-card.rejected { border-left-color: #ef4444; opacity: 0.8; }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .complaint-subject { font-size: 18px; font-weight: bold; color: #2d3748; }
        .complaint-meta { font-size: 12px; color: #718096; margin-top: 5px; }
        .complaint-message {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            line-height: 1.5;
        }
        .admin-response {
            background: #ebf8ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 3px solid #3182ce;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pending { background: #fefcbf; color: #b7791f; }
        .badge-resolved { background: #c6f6d5; color: #276749; }
        .badge-rejected { background: #fed7d7; color: #9b2c2c; }
        
        .response-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .response-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin: 10px 0;
            resize: vertical;
        }
        .btn-response { background: #3182ce; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .complaint-header { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo" style="height: 35px;">
        <span style="font-weight: bold; margin-left: 10px;">Admin Portal</span>
    </div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php">Users</a>
        <a href="admin_analytics.php">Analytics</a>
        <a href="admin_complaints.php" class="active">Complaints 
            <?php if ($counts['pending'] > 0): ?>
                <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px;"><?php echo $counts['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_settings.php">Settings</a>
        <a href="dashboard.php">Home</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container">
    <h2 style="margin-bottom: 10px;">📞 Complaints & Support Tickets</h2>
    <p style="color: #718096; margin-bottom: 20px;">Review and respond to user complaints and inquiries</p>
    
    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-badge pending">
            <div class="number"><?php echo $counts['pending']; ?></div>
            <div>Pending</div>
        </div>
        <div class="stat-badge resolved">
            <div class="number"><?php echo $counts['resolved'] + $counts['rejected']; ?></div>
            <div>Resolved</div>
        </div>
        <div class="stat-badge">
            <div class="number"><?php echo array_sum($counts); ?></div>
            <div>Total</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
        <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=resolved" class="filter-btn <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
        <a href="?status=rejected" class="filter-btn <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
    </div>
    
    <!-- Complaints List -->
    <?php if ($complaints && mysqli_num_rows($complaints) > 0): ?>
        <?php while ($complaint = mysqli_fetch_assoc($complaints)): ?>
            <div class="complaint-card <?php echo $complaint['status']; ?>">
                <div class="complaint-header">
                    <div>
                        <div class="complaint-subject">
                            <?php echo htmlspecialchars($complaint['subject']); ?>
                        </div>
                        <div class="complaint-meta">
                            From: <strong><?php echo htmlspecialchars($complaint['user_name']); ?></strong> • 
                            <?php echo htmlspecialchars($complaint['user_email']); ?> •
                            <?php echo date('d/m/Y H:i', strtotime($complaint['created_at'])); ?>
                            <?php if ($complaint['related_item']): ?>
                                • Related Item: <?php echo htmlspecialchars($complaint['related_item']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-<?php echo $complaint['status']; ?>">
                            <?php echo ucfirst($complaint['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="complaint-message">
                    <strong>Message:</strong><br>
                    <?php echo nl2br(htmlspecialchars($complaint['message'])); ?>
                </div>
                
                <?php if (!empty($complaint['admin_response'])): ?>
                    <div class="admin-response">
                        <strong>📝 Admin Response:</strong><br>
                        <?php echo nl2br(htmlspecialchars($complaint['admin_response'])); ?>
                        <div class="complaint-meta" style="margin-top: 8px;">
                            Resolved on: <?php echo date('d/m/Y H:i', strtotime($complaint['resolved_at'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($complaint['status'] == 'pending'): ?>
                    <div class="response-form">
                        <form method="POST">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['complaint_id']; ?>">
                            <textarea name="admin_response" rows="3" placeholder="Type your response here..." required></textarea>
                            <div style="display: flex; gap: 10px;">
                                <select name="status" style="padding: 8px; border-radius: 6px;">
                                    <option value="resolved">✓ Mark as Resolved</option>
                                    <option value="rejected">✗ Reject</option>
                                </select>
                                <button type="submit" name="respond_complaint" class="btn-response">Send Response</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <p style="font-size: 48px; margin-bottom: 10px;">📭</p>
            <p>No complaints found.</p>
            <p style="font-size: 13px; margin-top: 10px;">When users submit complaints, they will appear here.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>