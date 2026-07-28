<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '{$user_id}' AND is_read = 0"
))['total'];

// 1. Semak ID barang yang dihantar melalui URL (?id=...)
if (!isset($_GET['id'])) {
    header("Location: my_reports.php");
    exit();
}

$item_id = mysqli_real_escape_string($conn, $_GET['id']);

// 2. Tarik data lama - pastikan item_type = 'lost' dan milik user ini
$query = "SELECT * FROM items WHERE item_id = '$item_id' AND user_id = '$user_id' AND item_type = 'lost'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<script>alert('Report not found or unauthorized!'); window.location.href='my_reports.php';</script>";
    exit();
}

$item = mysqli_fetch_assoc($result);

// 3. Proses apabila borang disubmit untuk kemas kini
if (isset($_POST['update_report'])) {
    $item_name     = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category      = mysqli_real_escape_string($conn, $_POST['category']);
    $location      = mysqli_real_escape_string($conn, $_POST['location']);
    $description   = mysqli_real_escape_string($conn, $_POST['description']);
    $phone_number  = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $item_date     = $_POST['item_date'];

    if ($item_date > $today) {
        echo "<script>alert('Error: You cannot select a future date.'); window.history.back();</script>";
        exit();
    }

    // Semak proses muat naik gambar baru
    if (!empty($_FILES["item_image"]["name"])) {
        $filename     = $_FILES["item_image"]["name"];
        $tempname     = $_FILES["item_image"]["tmp_name"];
        $new_filename = time() . "_" . $filename;
        $folder       = "uploads/" . $new_filename;

        if (move_uploaded_file($tempname, $folder)) {
            // Padam gambar lama dari server jika ada
            if (!empty($item['image_path']) && file_exists("uploads/" . $item['image_path'])) {
                unlink("uploads/" . $item['image_path']);
            }
            // Update query berserta gambar baru
            $sql = "UPDATE items SET item_name='$item_name', category='$category', description='$description', 
                    phone_number='$phone_number', location='$location', item_date='$item_date', image_path='$new_filename' 
                    WHERE item_id='$item_id' AND user_id='$user_id' AND item_type='lost'";
        } else {
            echo "<script>alert('Failed to upload new image.'); window.history.back();</script>";
            exit();
        }
    } else {
        // Update query TANPA menukar gambar lama
        $sql = "UPDATE items SET item_name='$item_name', category='$category', description='$description', 
                phone_number='$phone_number', location='$location', item_date='$item_date' 
                WHERE item_id='$item_id' AND user_id='$user_id' AND item_type='lost'";
    }

    // Jalankan query ke pangkalan data
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Report Updated Successfully!'); window.location.href='my_reports.php';</script>";
        exit();
    } else {
        $error = mysqli_error($conn);
        echo "<script>alert('Failed to update report. Error: $error');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lost Report - UMPSA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; }

        .navbar { background: #fff; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .nav-brand { display: flex; align-items: center; font-weight: bold; color: #333; }
        .nav-links a { margin-left: 20px; text-decoration: none; color: #4a5568; font-size: 14px; font-weight: 600; }
        .btn-logout { background: #00a896; color: #fff !important; padding: 8px 20px; border-radius: 20px; text-decoration: none; }

        .main-content { padding: 60px 20px; text-align: center; }
        .main-content h2 { margin-bottom: 40px; font-weight: 800; font-size: 28px; color: #2d3748; }

        .form-card { background: #fff; width: 100%; max-width: 750px; margin: 0 auto; padding: 50px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        .form-row { display: flex; align-items: flex-start; margin-bottom: 25px; text-align: left; }
        .form-row label { width: 200px; font-weight: 700; font-size: 15px; color: #4a5568; padding-top: 12px; }
        .form-row input, .form-row textarea, .form-row select { flex: 1; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; outline: none; transition: 0.3s; font-size: 14px; color: #4a5568; }
        .form-row input:focus, .form-row textarea:focus, .form-row select:focus { border-color: #3498db; background-color: #fff; }
        .form-row textarea { height: 120px; resize: none; }

        .current-img { max-width: 200px; border-radius: 10px; margin-top: 5px; }

        .btn-submit { background: #00a896; border: none; padding: 12px 45px; border-radius: 25px; cursor: pointer; font-weight: 700; color: #fff; transition: 0.3s; }
        .btn-submit:hover { background: #008f80; transform: translateY(-2px); }

        .btn-back {
    display: inline-block;
    background: #edf2f7;
    color: #4a5568;
    border: 1px solid #cbd5e0;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.3s;
    margin-bottom: 20px;
}
.btn-back:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-brand">
            <img src="assets/images/umpsa-logo.png" alt="Logo" style="height: 35px; margin-right: 10px;">
            Lost & Found
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
                    <span class="badge">
                        <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                    </span>
                <?php endif; ?>
            <a href="profile.php">Profile</a>
        </div>
    </nav>

    <div class="main-content">
       <div style="max-width: 750px; margin: 0 auto 15px auto; text-align: left;">
    <a href="my_reports.php" class="btn-back">← Back to My Reports</a>
</div>
<h2>Edit Lost Item Report</h2>  

        <div class="form-card">
            <form id="editLostForm" action="" method="POST" enctype="multipart/form-data">

                <div class="form-row">
                    <label>Item Name :</label>
                    <input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label>Category :</label>
                    <select name="category" required>
                        <option value="Electronics"        <?php if(($item['category'] ?? '') == 'Electronics')        echo 'selected'; ?>>Electronics</option>
                        <option value="Documents"          <?php if(($item['category'] ?? '') == 'Documents')          echo 'selected'; ?>>Documents</option>
                        <option value="Keys"               <?php if(($item['category'] ?? '') == 'Keys')               echo 'selected'; ?>>Keys</option>
                        <option value="Bags"               <?php if(($item['category'] ?? '') == 'Bags')               echo 'selected'; ?>>Bags</option>
                        <option value="Wallets"            <?php if(($item['category'] ?? '') == 'Wallets')            echo 'selected'; ?>>Wallets</option>
                        <option value="Personal Belongings"<?php if(($item['category'] ?? '') == 'Personal Belongings') echo 'selected'; ?>>Personal Belongings</option>
                        <option value="Others"             <?php if(($item['category'] ?? '') == 'Others')             echo 'selected'; ?>>Others</option>
                    </select>
                </div>

                <div class="form-row">
                    <label>Last Seen Location :</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label>Date Lost :</label>
                    <input type="date" name="item_date" id="item_date" max="<?php echo $today; ?>" value="<?php echo $item['item_date'] ?? ''; ?>" required>
                </div>

                <div class="form-row">
                    <label>Item Description :</label>
                    <textarea name="description" required><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <label>Phone Number :</label>
                    <input type="text" name="phone_number" value="<?php echo htmlspecialchars($item['phone_number'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label>Current Photo :</label>
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="Current Image" class="current-img">
                    <?php else: ?>
                        <p style="color: #999; padding-top: 12px;">No image uploaded.</p>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <label>Change Photo :</label>
                    <input type="file" name="item_image" id="item_image" accept="image/*" style="background: transparent; border: none; padding-left: 0;">
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" name="update_report" class="btn-submit">Update Report</button>
                </div>

            </form>
        </div>
    </div>

    <script>
        document.getElementById('editLostForm').onsubmit = function(e) {
            const itemDate = document.getElementById('item_date').value;
            const today = new Date().toISOString().split('T')[0];

            if (itemDate > today) {
                alert("You cannot select a future date!");
                return false;
            }
        };
    </script>

    <?php include('includes/footer.php'); ?>

</body>
</html>