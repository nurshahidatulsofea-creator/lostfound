<?php
session_start();
include('config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;
$error = '';


$user_items = mysqli_query($conn, "
    SELECT item_id, item_name, item_type, status 
    FROM items 
    WHERE user_id = '$user_id' 
    ORDER BY created_at DESC
    LIMIT 20
");


$contact_info = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
$contacts = [];
while ($row = mysqli_fetch_assoc($contact_info)) {
    $contacts[$row['setting_key']] = $row['setting_value'];
}


if (isset($_POST['submit_complaint'])) {
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $message = mysqli_real_escape_string($conn, trim($_POST['message']));
    $item_id = !empty($_POST['item_id']) ? intval($_POST['item_id']) : 'NULL';
    
 
    $complaint_image = '';
    if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['complaint_image']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_size = $_FILES['complaint_image']['size'];
        
        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 5242880) { // 5MB max
                $new_filename = 'complaint_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $filename);
                $upload_path = 'uploads/complaints/';
                
             
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['complaint_image']['tmp_name'], $upload_path . $new_filename)) {
                    $complaint_image = $new_filename;
                }
            } else {
                $error = "Image too large! Maximum 5MB.";
            }
        } else {
            $error = "Invalid file type! Please upload JPG, PNG, or GIF.";
        }
    }
    
    // Validation
    $errors = [];
    if (empty($subject)) {
        $errors[] = "Please enter a subject.";
    }
    if (empty($message)) {
        $errors[] = "Please enter your message.";
    }
    if (strlen($message) < 10) {
        $errors[] = "Please provide more details (at least 10 characters).";
    }
    
    if (empty($errors)) {
        $image_sql = !empty($complaint_image) ? ", complaint_image = '$complaint_image'" : "";
        $sql = "INSERT INTO complaints (user_id, subject, message, item_id $image_sql) 
                VALUES ('$user_id', '$subject', '$message', $item_id)";
        
        if (mysqli_query($conn, $sql)) {
            $success = true;
            mysqli_query($conn, "INSERT INTO user_activity_log (user_id, action, ip_address, user_agent) 
                                 VALUES ('$user_id', 'submitted_complaint', '{$_SERVER['REMOTE_ADDR']}', '{$_SERVER['HTTP_USER_AGENT']}')");
        } else {
            $error = "Failed to submit. Please try again later.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}


$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM notifications 
     WHERE user_id = '$user_id' AND is_read = 0"
))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Admin - UMPSA Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f7f6; }
        
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
        
        .contact-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .contact-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .contact-card h2 {
            margin-bottom: 10px;
            color: #2d3748;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }
        .form-group label .required {
            color: #e53e3e;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        /* File upload styling */
        .file-upload {
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-upload:hover {
            border-color: #00a896;
            background: #f0fff4;
        }
        .file-upload input {
            display: none;
        }
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .file-upload-label span {
            font-size: 14px;
            color: #718096;
        }
        .file-name {
            margin-top: 10px;
            font-size: 12px;
            color: #00a896;
            display: none;
        }
        .preview-image {
            margin-top: 10px;
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            display: none;
        }
        
        .btn-submit {
            background: #3182ce;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            font-size: 16px;
            transition: all 0.2s;
        }
        .btn-submit:hover {
            background: #2c5282;
            transform: translateY(-1px);
        }
        .alert-success {
            background: #c6f6d5;
            color: #276749;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #38a169;
        }
        .alert-error {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #e53e3e;
        }
        .info-box {
            background: #ebf8ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #3182ce;
        }
        .info-box h4 {
            margin-bottom: 10px;
            color: #2c5282;
        }
        .info-box p {
            margin: 5px 0;
            color: #4a5568;
        }
        .complaint-tip {
            background: #fefcbf;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #975a16;
        }
        
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .contact-card { padding: 25px; }
            .nav-links { gap: 15px; }
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
        <a href="my_reports.php">My Reports</a>
        <a href="my_claims.php">My Claims</a>
        <a href="my_messages.php">Messages</a>
        <a href="notifications.php" class="notif-bell">
            Notifications
            <?php if ($notif_count > 0): ?>
                <span class="badge"><?php echo $notif_count > 9 ? '9+' : $notif_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php">Profile</a>
    </div>
</nav>

<div class="contact-container">
    <div class="contact-card">
        <h2>📞 Contact System Administrator</h2>
        <p style="color: #718096; margin-bottom: 20px;">
            Having issues with a lost/found item? Need to report a problem? Fill out the form below and we'll get back to you within 24-48 hours.
        </p>
        
        <div class="info-box">
            <h4>📌 Before you submit:</h4>
            <p>• For lost ID cards or emergencies, contact campus security directly at <strong><?php echo $contacts['contact_phone'] ?? '09-1234567'; ?></strong></p>
            <p>• For general inquiries, use this form and we'll respond via your notifications panel</p>
            <p>• If your issue is about a specific item, please select it from the dropdown below</p>
            <p>• You can attach a screenshot or image as proof (max 5MB, JPG/PNG)</p>
        </div>
        
        <div class="complaint-tip">
            💡 <strong>Tip:</strong> Please be as specific as possible. Include dates, locations, and any relevant details to help us resolve your issue faster. You can attach a screenshot if needed.
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success">
                ✅ <strong>Message Sent!</strong><br>
                Your complaint has been submitted to the administrator. You will receive a response in your <a href="notifications.php" style="color: #276749; font-weight: bold;">Notifications</a> within 24-48 hours.
                <br><br>
                <a href="dashboard.php" style="color: #276749;">← Return to Dashboard</a>
            </div>
        <?php else: ?>
            
            <?php if ($error): ?>
                <div class="alert-error">
                    ⚠️ <strong>Please fix the following:</strong><br>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Subject <span class="required">*</span></label>
                    <input type="text" name="subject" required 
                           placeholder="e.g., Wrong item claimed, Spam report, Account issue, Item not mine"
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Related Item (Optional)</label>
                    <select name="item_id">
                        <option value="">-- Select an item (if applicable) --</option>
                        <?php while ($item = mysqli_fetch_assoc($user_items)): ?>
                            <option value="<?php echo $item['item_id']; ?>"
                                <?php echo (isset($_POST['item_id']) && $_POST['item_id'] == $item['item_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                (<?php echo ucfirst($item['item_type']); ?> - <?php echo ucfirst($item['status']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" required 
                        placeholder="Please describe your issue in detail. Include any relevant information that can help us assist you better..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Attach Screenshot / Image (Optional)</label>
                    <div class="file-upload" onclick="document.getElementById('complaint_image').click()">
                        <div class="file-upload-label">
                            🖼️
                            <span>Click to upload or drag and drop</span>
                            <span style="font-size: 11px;">Supports: JPG, PNG, GIF, WebP (Max 5MB)</span>
                        </div>
                        <input type="file" name="complaint_image" id="complaint_image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                    </div>
                    <div class="file-name" id="fileName"></div>
                    <img class="preview-image" id="imagePreview" alt="Preview">
                </div>
                
                <button type="submit" name="submit_complaint" class="btn-submit">
                    📨 Send Message to Admin
                </button>
            </form>
            
            <div class="direct-contact" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 13px; color: #718096;">
                <hr style="margin: 15px 0; border-color: #e2e8f0;">
                <p>📧 Email: <?php echo $contacts['contact_email'] ?? 'admin@umpsa.edu.my'; ?></p>
                <p>📱 Phone: <?php echo $contacts['contact_phone'] ?? '09-1234567'; ?></p>
                <p style="margin-top: 10px;">🕐 Office Hours: Monday-Friday, 9:00 AM - 5:00 PM</p>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<script>
function previewImage(input) {
    const fileName = document.getElementById('fileName');
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            fileName.textContent = '📎 ' + input.files[0].name;
            fileName.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
        fileName.style.display =none;
    }
}
</script>

<?php include('includes/footer.php'); ?>

</body>
</html>