<?php
session_start();
include('config/db.php');

// Kawalan keselamatan: Hanya admin boleh akses [cite: 178]
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if (isset($_GET['id'])) {
    $item_id = $_GET['id'];
    
    // Jika admin klik Verify [cite: 434]
    if (isset($_GET['action']) && $_GET['action'] == 'approve') {
        $update = "UPDATE items SET status = 'verified' WHERE item_id = '$item_id'";
        mysqli_query($conn, $update);
        header("Location: admin_dashboard.php");
    }
    
    // Jika admin klik Reject [cite: 438]
    if (isset($_GET['action']) && $_GET['action'] == 'reject') {
        $delete = "DELETE FROM items WHERE item_id = '$item_id'";
        mysqli_query($conn, $delete);
        header("Location: admin_dashboard.php");
    }
}

// Ambil maklumat barang untuk paparan pengesahan [cite: 432]
$id = $_GET['id'];
$res = mysqli_query($conn, "SELECT * FROM items WHERE item_id = '$id'");
$item = mysqli_fetch_assoc($res);
?>

<?php include("includes/header.php"); ?>

<div class="container">
    <h2>Verify Item Report</h2>
    <div class="card">
        <p><strong>Item Name:</strong> <?php echo $item['item_name']; ?></p>
        <p><strong>Location:</strong> <?php echo $item['location']; ?></p>
        <p><strong>Description:</strong> <?php echo $item['description']; ?></p>
        
        <?php if($item['image_path']): ?>
            <img src="<?php echo $item['image_path']; ?>" width="200">
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="verify_item.php?id=<?php echo $id; ?>&action=approve" style="background: green; color: white; padding: 10px; text-decoration: none;">Approve & Verify</a>
            <a href="verify_item.php?id=<?php echo $id; ?>&action=reject" style="background: red; color: white; padding: 10px; text-decoration: none;" onclick="return confirm('Reject this report?')">Reject</a>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>