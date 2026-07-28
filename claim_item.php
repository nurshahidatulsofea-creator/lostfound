<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: browse_items.php");
    exit();
}

$item_id = mysqli_real_escape_string($conn, $_GET['id']);
$user_id = $_SESSION['user_id'];

$query = mysqli_query($conn, "SELECT * FROM items WHERE item_id = '$item_id'");
$item  = mysqli_fetch_assoc($query);

if ($item['user_id'] == $user_id) {
    echo "<script>alert('You cannot claim an item that you reported yourself!'); window.location.href='browse_items.php';</script>";
    exit();
}

if (isset($_POST['submit_claim'])) {
    $reason       = mysqli_real_escape_string($conn, $_POST['reason']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $claim_date   = date('Y-m-d H:i:s');
    $claim_image  = "";

    if (!empty($_FILES["proof_image"]["name"])) {
        $filename    = $_FILES["proof_image"]["name"];
        $tempname    = $_FILES["proof_image"]["tmp_name"];
        $target_dir  = "uploads/claims/";
        $claim_image = "claim_" . time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
        $target_file = $target_dir . $claim_image;

        if (!is_dir($target_dir)) {
            echo "<script>alert('Error: Folder $target_dir tidak dijumpai!');</script>";
        } else {
            if (!move_uploaded_file($tempname, $target_file)) {
                $error_code  = $_FILES["proof_image"]["error"];
                echo "<script>alert('Error Muat Naik: Kod $error_code.');</script>";
                $claim_image = "";
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO claims (item_id, user_id, claim_text, phone_number, claim_image, claim_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iissss", $item_id, $user_id, $reason, $phone_number, $claim_image, $claim_date);

    if ($stmt->execute()) {
        $notif_msg = mysqli_real_escape_string($conn,
            "Someone submitted a claim request for your item: " . $item['item_name']
        );
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, item_id, message)
             VALUES ('{$item['user_id']}', '$item_id', '$notif_msg')"
        );


                    include_once('telegram_notify.php');
                    sendTelegram($item['user_id'], "🔔 New claim request for your item: " . $item['item_name'] . "\n\nLogin to approve: http://localhost/lostfound/claim_details.php?item_id=" . $item_id);

        echo "<script>alert('Claim Request Submitted Successfully!'); window.location.href='browse_items.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Claim - UMPSA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f4f7f6; padding: 20px; }
        .claim-container { max-width: 550px; margin: 40px auto; }
        .claim-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .item-preview { display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .item-preview img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; }
        .no-img { width: 70px; height: 70px; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #a0aec0; text-align: center; }

        textarea, input[type="text"] {
            width: 100%; padding: 12px; border-radius: 8px;
            border: 1px solid #cbd5e0; margin-top: 5px; outline: none;
        }
        input[type="text"]:focus, textarea:focus { border-color: #3182ce; }

        .upload-section { margin-top: 15px; padding: 15px; border: 2px dashed #e2e8f0; border-radius: 10px; }
        .btn-confirm { width: 100%; background: #3182ce; color: white; border: none; padding: 15px; border-radius: 10px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .btn-confirm:hover { background: #2b6cb0; }
        .or-divider { text-align: center; margin: 15px 0; color: #a0aec0; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="claim-container">
    <div class="claim-card">
        <h2>Claim Confirmation</h2>
        <p style="font-size: 14px; color: #718096;">Please provide proof and contact details for verification.</p>

        <div class="item-preview">
            <?php if (!empty($item['image_path']) && file_exists("uploads/" . $item['image_path'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item">
            <?php else: ?>
                <div class="no-img">No Image</div>
            <?php endif; ?>
            <div>
                <h4 style="margin: 0;"><?php echo htmlspecialchars($item['item_name']); ?></h4>
                <p style="margin: 0; font-size: 12px; color: #a0aec0;">📍 <?php echo htmlspecialchars($item['location']); ?></p>
            </div>
        </div>

        <form id="claimForm" action="" method="POST" enctype="multipart/form-data">

            <label style="font-size: 14px; font-weight: 600; color: #4a5568;">Your Contact Number:</label>
            <input type="text" name="phone_number" id="phone_number" placeholder="e.g. 01123456789" required
                   value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">

            <div style="margin-top: 20px;">
                <label style="font-size: 14px; font-weight: 600; color: #4a5568;">Option 1: Description (Text)</label>
                <textarea name="reason" id="reason" rows="4"
                    placeholder="Describe unique marks, serial number, etc."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
            </div>

            <div class="or-divider">OR</div>

            <label style="font-size: 14px; font-weight: 600; color: #4a5568;">Option 2: Upload Proof Image</label>
            <div class="upload-section">
                <input type="file" name="proof_image" id="proof_image" accept="image/*">
            </div>

            <button type="submit" name="submit_claim" class="btn-confirm">Submit Claim Request</button>
            <a href="browse_items.php" style="display: block; text-align: center; margin-top: 15px; color: #a0aec0; text-decoration: none; font-size: 13px;">Cancel</a>
        </form>
    </div>
</div>

<script>
    document.getElementById('claimForm').onsubmit = function () {
        const reason = document.getElementById('reason').value.trim();
        const phone  = document.getElementById('phone_number').value.trim();
        const image  = document.getElementById('proof_image').files.length;

        if (phone === "") {
            alert("Please provide your contact number so the owner can reach you.");
            return false;
        }

        if (reason === "" && image === 0) {
            alert("Please provide at least one form of proof (Text Description or Image).");
            return false;
        }
        return true;
    };
</script>

<?php include('includes/footer.php'); ?>

</body>
</html>