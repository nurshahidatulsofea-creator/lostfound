<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// FIXED: Removed the stray "/" on line 11
$pending_complaints = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'"
))['total'] ?? 0;

// Ban user
if (isset($_POST['ban_user'])) {
    $user_id = intval($_POST['user_id']);
    $reason = mysqli_real_escape_string($conn, $_POST['ban_reason'] ?? 'Violation of platform rules');
    
    mysqli_query($conn, "UPDATE users SET is_banned = 1, ban_reason = '$reason', banned_at = NOW() WHERE user_id = $user_id");
    
    echo "<script>alert('User has been banned.'); window.location.href='admin_users.php';</script>";
    exit();
}

// Unban user
if (isset($_POST['unban_user'])) {
    $user_id = intval($_POST['user_id']);
    mysqli_query($conn, "UPDATE users SET is_banned = 0, ban_reason = NULL, banned_at = NULL WHERE user_id = $user_id");
    
    echo "<script>alert('User has been unbanned.'); window.location.href='admin_users.php';</script>";
    exit();
}

// Make admin
if (isset($_POST['make_admin'])) {
    $user_id = intval($_POST['user_id']);
    mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE user_id = $user_id");
    
    echo "<script>alert('User promoted to admin.'); window.location.href='admin_users.php';</script>";
    exit();
}

// Remove admin
if (isset($_POST['remove_admin'])) {
    $user_id = intval($_POST['user_id']);
    if ($user_id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE users SET role = 'user' WHERE user_id = $user_id");
        echo "<script>alert('Admin privileges removed.'); window.location.href='admin_users.php';</script>";
    } else {
        echo "<script>alert('You cannot remove your own admin privileges!');</script>";
    }
}

// Reset password
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $temp_password = 'password123';
    $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE user_id = $user_id");
    
    echo "<script>alert('Password reset to: $temp_password (tell user to change it)'); window.location.href='admin_users.php';</script>";
    exit();
}

// Delete user
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    if ($user_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM items WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM claims WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM complaints WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM notifications WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM messages WHERE sender_id = $user_id OR receiver_id = $user_id");
        mysqli_query($conn, "DELETE FROM user_activity_log WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
        
        echo "<script>alert('User and all associated data deleted permanently.'); window.location.href='admin_users.php';</script>";
    } else {
        echo "<script>alert('You cannot delete your own account!');</script>";
    }
    exit();
}

// ============================================
// GET FILTERED USER LIST
// ============================================

$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = "WHERE 1=1";

if ($filter == 'banned') {
    $where .= " AND u.is_banned = 1";
} elseif ($filter == 'admins') {
    $where .= " AND u.role = 'admin'";
} elseif ($filter == 'users') {
    $where .= " AND u.role = 'user' AND u.is_banned = 0";
}

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%')";
}

$users = mysqli_query($conn, "
    SELECT u.*, 
           COUNT(DISTINCT i.item_id) as reports_count,
           COUNT(DISTINCT c.complaint_id) as complaints_count
    FROM users u
    LEFT JOIN items i ON u.user_id = i.user_id
    LEFT JOIN complaints c ON u.user_id = c.user_id
    $where
    GROUP BY u.user_id
    ORDER BY u.user_id DESC
");

// Get counts for stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'user'"))['total'] ?? 0;
$banned_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE is_banned = 1"))['total'] ?? 0;
$admin_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'admin'"))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin | UMPSA Lost & Found</title>
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
            position: sticky;
            top: 0;
            z-index: 100;
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
        .nav-links a.active { color: #00a896; border-bottom: 2px solid #00a896; padding-bottom: 5px; }
        .btn-logout {
            background: #00a896;
            color: white !important;
            padding: 8px 20px;
            border-radius: 25px;
        }
        .btn-logout:hover { background: #008f80; }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .page-header p {
            color: #718096;
            font-size: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .icon { font-size: 32px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2d3748; }
        .stat-card .label { color: #718096; font-size: 13px; margin-top: 5px; }
        .stat-card.danger .number { color: #e53e3e; }
        
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .filter-btn:hover { background: #f7fafc; }
        .filter-btn.active { background: #00a896; color: white; border-color: #00a896; }
        
        .search-box input {
            padding: 8px 15px;
            width: 250px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }
        .search-box input:focus { border-color: #00a896; }
        
        .user-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        .user-table th {
            background: #f7fafc;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #2d3748;
        }
        .user-table tr:hover { background: #f7fafc; }
        
        .badge-admin {
            background: #c6f6d5;
            color: #276749;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-banned {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
            cursor: help;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-ban { background: #ef4444; color: white; }
        .btn-ban:hover { background: #dc2626; }
        .btn-unban { background: #10b981; color: white; }
        .btn-unban:hover { background: #059669; }
        .btn-admin { background: #00a896; color: white; }
        .btn-admin:hover { background: #008f80; }
        .btn-reset { background: #f59e0b; color: white; }
        .btn-reset:hover { background: #d97706; }
        .btn-delete { background: #e53e3e; color: white; }
        .btn-delete:hover { background: #c53030; }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .container { padding: 0 15px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box input { width: 100%; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <img src="assets/images/umpsa-logo.png" alt="Logo">
        <span>Admin Portal</span>
    </div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_users.php" class="active">Users</a>
        <a href="admin_analytics.php">Analytics</a>
        <a href="admin_complaints.php">Complaints 
            <?php if ($pending_complaints > 0): ?>
                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;"><?php echo $pending_complaints; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_settings.php">Settings</a>
        <a href="dashboard.php">Home</a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="container">
    
    <div class="page-header">
        <h1>👥 User Management</h1>
        <p>Manage users, ban/unban accounts, assign admin roles, and reset passwords</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">👤</div>
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="icon">👑</div>
            <div class="number"><?php echo $admin_count; ?></div>
            <div class="label">Administrators</div>
        </div>
        <div class="stat-card danger">
            <div class="icon">🚫</div>
            <div class="number"><?php echo $banned_users; ?></div>
            <div class="label">Banned Users</div>
        </div>
    </div>
    
    <div class="filter-bar">
        <div class="filter-buttons">
            <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">All Users</a>
            <a href="?filter=users" class="filter-btn <?php echo $filter == 'users' ? 'active' : ''; ?>">Regular Users</a>
            <a href="?filter=admins" class="filter-btn <?php echo $filter == 'admins' ? 'active' : ''; ?>">Admins</a>
            <a href="?filter=banned" class="filter-btn <?php echo $filter == 'banned' ? 'active' : ''; ?>">Banned Users</a>
        </div>
        
        <div class="search-box">
            <form method="GET" action="">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="🔍 Search by name or email..." 
                       value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
            </form>
        </div>
    </div>
    
    <div class="user-table-container">
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Reports</th>
                    <th>Complaints</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users && mysqli_num_rows($users) > 0): ?>
                    <?php while ($user = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number'] ?? '-'); ?></td>
                            <td><?php echo $user['reports_count']; ?></td>
                            <td><?php echo $user['complaints_count']; ?></td>
                            <td>
                                <?php if ($user['role'] == 'admin'): ?>
                                    <span class="badge-admin">👑 Admin</span>
                                <?php endif; ?>
                                <?php if (isset($user['is_banned']) && $user['is_banned'] == 1): ?>
                                    <span class="badge-banned" title="<?php echo htmlspecialchars($user['ban_reason'] ?? 'No reason provided'); ?>">
                                        🚫 Banned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="btn-group">
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    
                                    <?php if (isset($user['is_banned']) && $user['is_banned'] == 1): ?>
                                        <button type="submit" name="unban_user" class="btn-small btn-unban" 
                                                onclick="return confirm('Unban <?php echo addslashes($user['name']); ?>?')">
                                            ✅ Unban
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-small btn-ban" 
                                                onclick="promptBanReason(<?php echo $user['user_id']; ?>, '<?php echo addslashes($user['name']); ?>')">
                                            🚫 Ban
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['role'] != 'admin'): ?>
                                        <button type="submit" name="make_admin" class="btn-small btn-admin" 
                                                onclick="return confirm('Make <?php echo addslashes($user['name']); ?> an admin?')">
                                            👑 Make Admin
                                        </button>
                                    <?php elseif ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button type="submit" name="remove_admin" class="btn-small btn-ban" 
                                                onclick="return confirm('Remove admin privileges from <?php echo addslashes($user['name']); ?>?')">
                                            ⬇️ Remove Admin
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" name="reset_password" class="btn-small btn-reset" 
                                            onclick="return confirm('Reset password for <?php echo addslashes($user['name']); ?>?\nNew password will be: password123')">
                                        🔑 Reset PW
                                    </button>
                                    
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button type="submit" name="delete_user" class="btn-small btn-delete" 
                                                onclick="return confirm('⚠️ PERMANENT ACTION!\n\nDelete user <?php echo addslashes($user['name']); ?> and ALL their data?\n\nThis cannot be undone!')">
                                            🗑️ Delete
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            No users found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
</div>

<script>
function promptBanReason(userId, userName) {
    var reason = prompt("Enter ban reason for " + userName + ":", "Spam / Fake reports / Harassment / Multiple violations");
    if (reason && reason.trim() !== "") {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        var userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        
        var banReasonInput = document.createElement('input');
        banReasonInput.type = 'hidden';
        banReasonInput.name = 'ban_reason';
        banReasonInput.value = reason.trim();
        
        var banButton = document.createElement('input');
        banButton.type = 'hidden';
        banButton.name = 'ban_user';
        banButton.value = '1';
        
        form.appendChild(userIdInput);
        form.appendChild(banReasonInput);
        form.appendChild(banButton);
        document.body.appendChild(form);
        
        if (confirm("Are you sure you want to ban " + userName + "?\nReason: " + reason.trim())) {
            form.submit();
        }
    }
}
</script>

</body>
</html>