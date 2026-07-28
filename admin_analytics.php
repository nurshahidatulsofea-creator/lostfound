<?php
session_start();
include('config/db.php');

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$category_data = mysqli_query($conn, "
    SELECT category, COUNT(*) as count 
    FROM items 
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category 
    ORDER BY count DESC
");

$categories = [];
$category_counts = [];
while ($row = mysqli_fetch_assoc($category_data)) {
    $categories[] = $row['category'];
    $category_counts[] = $row['count'];
}

$monthly_data = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        DATE_FORMAT(created_at, '%b %Y') as month_name,
        COUNT(*) as total,
        SUM(CASE WHEN item_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
        SUM(CASE WHEN item_type = 'found' THEN 1 ELSE 0 END) as found_count
    FROM items 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

$months = [];
$lost_trend = [];
$found_trend = [];
while ($row = mysqli_fetch_assoc($monthly_data)) {
    $months[] = $row['month_name'];
    $lost_trend[] = $row['lost_count'];
    $found_trend[] = $row['found_count'];
}


$status_data = mysqli_query($conn, "
    SELECT status, COUNT(*) as count 
    FROM items 
    GROUP BY status
");

$status_labels = [];
$status_counts = [];
$status_colors = [
    'pending' => '#ed8936',
    'claimed' => '#48bb78',
    'unclaimed' => '#e53e3e'
];
while ($row = mysqli_fetch_assoc($status_data)) {
    $status_labels[] = ucfirst($row['status']);
    $status_counts[] = $row['count'];
}

// 4. Top users (most reports)
$top_users = mysqli_query($conn, "
    SELECT u.name, COUNT(i.item_id) as report_count
    FROM users u
    JOIN items i ON u.user_id = i.user_id
    GROUP BY u.user_id
    ORDER BY report_count DESC
    LIMIT 5
");

// 5. Resolution rate over time
$resolution_data = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) as resolved
    FROM items 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

$resolution_months = [];
$resolution_rates = [];
while ($row = mysqli_fetch_assoc($resolution_data)) {
    $resolution_months[] = $row['month'];
    $rate = ($row['total'] > 0) ? round(($row['resolved'] / $row['total']) * 100, 1) : 0;
    $resolution_rates[] = $rate;
}

// 6. Popular locations
$top_locations = mysqli_query($conn, "
    SELECT location, COUNT(*) as count 
    FROM items 
    WHERE location IS NOT NULL AND location != ''
    GROUP BY location 
    ORDER BY count DESC 
    LIMIT 5
");

// 7. User activity (last 7 days)
$activity_data = mysqli_query($conn, "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as reports
    FROM items 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

$activity_dates = [];
$activity_counts = [];
while ($row = mysqli_fetch_assoc($activity_data)) {
    $activity_dates[] = date('d M', strtotime($row['date']));
    $activity_counts[] = $row['reports'];
}

// 8. Summary stats
$summary = [];
$summary['total_items'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM items"))['total'];
$summary['total_users'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'user'"))['total'];
$summary['total_claims'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM claims"))['total'];
$summary['total_complaints'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM complaints"))['total'];
$summary['avg_response_time'] = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours 
    FROM complaints 
    WHERE resolved_at IS NOT NULL
"))['avg_hours'] ?? 0;

// Get notification count for navbar
$pending_complaints = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM complaints WHERE status = 'pending'"
))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - UMPSA Lost & Found</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
        /* Navbar */
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
        
        /* Container */
        .analytics-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        /* Header */
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
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .summary-card:hover { transform: translateY(-3px); }
        .summary-card .icon { font-size: 32px; margin-bottom: 10px; }
        .summary-card .number { font-size: 32px; font-weight: bold; color: #00a896; }
        .summary-card .label { color: #718096; font-size: 13px; margin-top: 5px; }
        
        /* Chart Grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .chart-card h3 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        .chart-card canvas { max-height: 300px; width: 100% !important; }
        
        /* Full width card */
        .full-width-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .full-width-card h3 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        /* Top Users Table */
        .top-users-table {
            width: 100%;
            border-collapse: collapse;
        }
        .top-users-table th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
        }
        .top-users-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .rank {
            font-weight: bold;
            color: #00a896;
        }
        
        /* Locations List */
        .locations-list {
            list-style: none;
        }
        .locations-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .locations-list li:last-child { border-bottom: none; }
        .location-name { font-weight: 500; color: #2d3748; }
        .location-count { 
            background: #00a896; 
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .chart-grid { grid-template-columns: 1fr; }
            .navbar { padding: 15px 20px; }
            .analytics-container { padding: 0 15px; }
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
        <a href="admin_users.php">Users</a>
        <a href="admin_analytics.php" class="active">Analytics</a>
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

<div class="analytics-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <h1>📊 Analytics Dashboard</h1>
        <p>Monitor system performance, trends, and user activity</p>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="icon">📦</div>
            <div class="number"><?php echo $summary['total_items']; ?></div>
            <div class="label">Total Items Reported</div>
        </div>
        <div class="summary-card">
            <div class="icon">👥</div>
            <div class="number"><?php echo $summary['total_users']; ?></div>
            <div class="label">Active Users</div>
        </div>
        <div class="summary-card">
            <div class="icon">📋</div>
            <div class="number"><?php echo $summary['total_claims']; ?></div>
            <div class="label">Total Claims Submitted</div>
        </div>
        <div class="summary-card">
            <div class="icon">📞</div>
            <div class="number"><?php echo $summary['total_complaints']; ?></div>
            <div class="label">Total Complaints</div>
        </div>
    </div>
    
    <!-- Chart Grid Row 1 -->
    <div class="chart-grid">
        <!-- Category Chart -->
        <div class="chart-card">
            <h3>📂 Items by Category</h3>
            <canvas id="categoryChart"></canvas>
        </div>
        
        <!-- Status Distribution Chart -->
        <div class="chart-card">
            <h3>📌 Item Status Distribution</h3>
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    
    <!-- Chart Grid Row 2 -->
    <div class="chart-grid">
        <!-- Monthly Trend Chart -->
        <div class="chart-card">
            <h3>📈 Monthly Trend (Lost vs Found)</h3>
            <canvas id="trendChart"></canvas>
        </div>
        
        <!-- Resolution Rate Chart -->
        <div class="chart-card">
            <h3>✅ Resolution Rate Over Time</h3>
            <canvas id="resolutionChart"></canvas>
        </div>
    </div>
    
    <!-- Two Column Layout for Bottom Content -->
    <div class="chart-grid">
        <!-- Top Users -->
        <div class="chart-card">
            <h3>🏆 Top Contributors</h3>
            <table class="top-users-table">
                <thead>
                    <tr><th>Rank</th><th>User Name</th><th>Reports</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    if (mysqli_num_rows($top_users) > 0):
                        while ($user = mysqli_fetch_assoc($top_users)): 
                    ?>
                        <tr>
                            <td class="rank">#<?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo $user['report_count']; ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="3" style="text-align: center; color: #a0aec0;">No data yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Popular Locations -->
        <div class="chart-card">
            <h3>📍 Most Common Locations</h3>
            <ul class="locations-list">
                <?php if (mysqli_num_rows($top_locations) > 0): ?>
                    <?php while ($loc = mysqli_fetch_assoc($top_locations)): ?>
                        <li>
                            <span class="location-name">📍 <?php echo htmlspecialchars($loc['location']); ?></span>
                            <span class="location-count"><?php echo $loc['count']; ?> items</span>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li style="text-align: center; color: #a0aec0;">No location data yet</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- User Activity Chart (Full Width) -->
    <div class="full-width-card">
        <h3>📅 User Activity (Last 7 Days)</h3>
        <canvas id="activityChart" style="max-height: 300px; width: 100%;"></canvas>
    </div>
    
</div>

<script>
    // ============================================
    // 1. CATEGORY CHART (Bar Chart)
    // ============================================
    const ctx1 = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($categories); ?>,
            datasets: [{
                label: 'Number of Items',
                data: <?php echo json_encode($category_counts); ?>,
                backgroundColor: '#00a896',
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
    
    // ============================================
    // 2. STATUS CHART (Pie Chart)
    // ============================================
    const ctx2 = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($status_counts); ?>,
                backgroundColor: ['#ed8936', '#48bb78', '#e53e3e'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    
    // ============================================
    // 3. MONTHLY TREND CHART (Line Chart)
    // ============================================
    const ctx3 = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx3, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [
                {
                    label: 'Lost Items',
                    data: <?php echo json_encode($lost_trend); ?>,
                    borderColor: '#e53e3e',
                    backgroundColor: 'rgba(229, 62, 62, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e53e3e'
                },
                {
                    label: 'Found Items',
                    data: <?php echo json_encode($found_trend); ?>,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#48bb78'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
    
    // ============================================
    // 4. RESOLUTION RATE CHART (Line Chart)
    // ============================================
    const ctx4 = document.getElementById('resolutionChart').getContext('2d');
    new Chart(ctx4, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($resolution_months); ?>,
            datasets: [{
                label: 'Resolution Rate (%)',
                data: <?php echo json_encode($resolution_rates); ?>,
                borderColor: '#00a896',
                backgroundColor: 'rgba(0, 168, 150, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#00a896',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Resolution Rate (%)' }
                }
            }
        }
    });
    
    // ============================================
    // 5. USER ACTIVITY CHART (Bar Chart)
    // ============================================
    const ctx5 = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx5, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($activity_dates); ?>,
            datasets: [{
                label: 'Reports Submitted',
                data: <?php echo json_encode($activity_counts); ?>,
                backgroundColor: '#00a896',
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
</script>

</body>
</html>