<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$today = date('Y-m-d');

if (isset($_POST['submit_report'])) {
    $user_id = $_SESSION['user_id'];
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']); 
    $location = mysqli_real_escape_string($conn, $_POST['location']); 
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $item_date = $_POST['item_date'];
    
   
    if ($item_date > $today) {
        echo "<script>alert('Error: You cannot select a future date.'); window.history.back();</script>";
        exit();
    }

    $item_type = 'lost';
    $status = 'pending';

    $filename = $_FILES["item_image"]["name"];
    $tempname = $_FILES["item_image"]["tmp_name"];
    $new_filename = time() . "_" . $filename;
    $folder = "uploads/" . $new_filename;

    if (move_uploaded_file($tempname, $folder)) {
        $sql = "INSERT INTO items (user_id, item_type, item_name, category, description, phone_number, location, item_date, image_path, status) 
                VALUES ('$user_id', '$item_type', '$item_name', '$category', '$description', '$phone_number', '$location', '$item_date', '$new_filename', '$status')";
        
        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('Report Submitted Successfully!'); window.location.href='dashboard.php';</script>";
        }
    } else {
        echo "<script>alert('Please upload a valid image.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item - UMPSA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; }
        
        /* NAVBAR */
        .navbar { background: #fff; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .nav-brand { display: flex; align-items: center; font-weight: bold; color: #333; }
        .nav-links a { margin-left: 20px; text-decoration: none; color: #4a5568; font-size: 14px; font-weight: 600; }
        .btn-logout { background: #00a896; color: #fff !important; padding: 8px 20px; border-radius: 20px; text-decoration: none; transition: 0.3s; }
        .btn-logout:hover { background: #008f80; }

        /* CONTENT SPACING */
        .main-content { padding: 60px 20px; text-align: center; }
        .main-content h2 { 
            margin-bottom: 40px; /* Menambah jarak antara tajuk dan kotak form */
            font-weight: 800; 
            font-size: 28px; 
            color: #2d3748; 
        }

        /* FORM CARD */
        .form-card { background: #fff; width: 100%; max-width: 750px; margin: 0 auto; padding: 50px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        .form-row { display: flex; align-items: flex-start; margin-bottom: 25px; text-align: left; }
        .form-row label { width: 200px; font-weight: 700; font-size: 15px; color: #4a5568; padding-top: 12px; }
        .form-row input, .form-row textarea { flex: 1; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; outline: none; transition: 0.3s; }
        .form-row input:focus, .form-row textarea:focus { border-color: #3498db; background-color: #fff; }
        .form-row textarea { height: 120px; resize: none; }
        
        .btn-submit { background: #edf2f7; border: 1px solid #cbd5e0; padding: 12px 45px; border-radius: 25px; cursor: pointer; font-weight: 700; color: #4a5568; transition: 0.3s; }
        .btn-submit:hover { background: #e2e8f0; transform: translateY(-2px); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-brand">
            <img src="assets/images/umpsa-logo.png" alt="Logo" style="height: 35px; margin-right: 10px;">
            Lost & Found
        </div>
        <div class="nav-links">
           <a href="dashboard.php" class="active">Home</a>
            <a href="browse_items.php">Browse Items</a>
            <a href="my_reports.php">Reports</a>
            <a href="my_claims.php">Claims</a>
            <a href="my_messages.php">Messages</a>
            <a href="notifications.php" class="notif-bell">
                 Notifications
           <a href="profile.php">Profile</a>
        </div>
    </nav>

    <div class="main-content">
        <h2>Report Lost Item</h2>

        <div class="form-card">
            <form id="lostReportForm" action="" method="POST" enctype="multipart/form-data">
                
                <div class="form-row">
                    <label>Item Name :</label>
                    <input type="text" name="item_name" placeholder="e.g. iPad, Wallet" required>
                </div>

                <div class="form-row">
    <label>Category :</label>
    <select name="category" required style="flex: 1; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; outline: none; transition: 0.3s; font-size: 14px; color: #4a5568;">
        <option value="" disabled selected style="color: #a0aec0;">Select Category</option>
        <option value="Electronics">Electronics</option>
        <option value="Documents">Documents</option>
        <option value="Keys">Keys</option>
        <option value="Bags">Bags</option>
        <option value="Wallets">Wallets</option>
        <option value="Personal Belongings">Personal Belongings</option>
        <option value="Others">Others</option>
    </select>
</div>
                <div class="form-row">
                    <label>Location :</label>
                    <input type="text" name="location" placeholder="Where did you see it last?" required>
                </div>

                <div class="form-row">
                    <label>Date :</label>
                    <input type="date" name="item_date" id="item_date" max="<?php echo $today; ?>" required>
                </div>

                <div class="form-row">
                    <label>Item Description :</label>
                    <textarea name="description" placeholder="Color, brand, or any unique signs..." required></textarea>
                </div>

                <div class="form-row">
                    <label>Phone Number :</label>
                    <input type="text" name="phone_number" placeholder="e.g. 01123456789" required>
                </div>

                <div class="form-row">
                    <label>Upload Photo :</label>
                    <input type="file" name="item_image" id="item_image" accept="image/*" required style="background: transparent; border: none; padding-left: 0;">
                </div>

                <button type="submit" name="submit_report" class="btn-submit">Submit Report</button>

            </form>
        </div>
    </div>

    <script>
        document.getElementById('lostReportForm').onsubmit = function(e) {
            const itemDate = document.getElementById('item_date').value;
            const today = new Date().toISOString().split('T')[0];
            const fileInput = document.getElementById('item_image');

            if (itemDate > today) {
                alert("You cannot report an item lost in the future!");
                return false;
            }

            if (fileInput.files.length === 0) {
                alert("Please upload a picture of the item.");
                return false;
            }

            const inputs = this.querySelectorAll('[required]');
            for (let input of inputs) {
                if (!input.value.trim()) {
                    alert("Please complete all fields.");
                    return false;
                }
            }
        };
    </script>
    <?php include('includes/footer.php'); ?>

</body>
</html>