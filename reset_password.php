<?php
session_start();
include('config/db.php');

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

$error = '';
$success = false;
$user_id = null;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

$check_token = mysqli_query($conn, "
    SELECT user_id, email, name, reset_token, reset_token_expiry 
    FROM users 
    WHERE reset_token = '$token'
");

if (mysqli_num_rows($check_token) == 0) {
    $error = "Invalid reset link. The token does not exist in our system.";
} else {
    $user = mysqli_fetch_assoc($check_token);
    $user_id = $user['user_id'];
    
    $current_time = date('Y-m-d H:i:s');
    $expiry_time = $user['reset_token_expiry'];
    
    if ($expiry_time < $current_time) {
        $error = "This reset link has expired (expired at: " . date('h:i A', strtotime($expiry_time)) . "). Please request a new one.";

        mysqli_query($conn, "UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE user_id = '$user_id'");
    } else {
      
        if (isset($_POST['reset_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update = mysqli_query($conn, "
                    UPDATE users 
                    SET password = '$hashed_password', 
                        reset_token = NULL, 
                        reset_token_expiry = NULL 
                    WHERE user_id = '$user_id'
                ");
                
                if ($update) {
                    $success = true;
                } else {
                    $error = "Database error. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UMPSA Lost & Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body {
            background: linear-gradient(135deg, #00a896 0%, #028090 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .reset-container {
            background: white;
            border-radius: 20px;
            padding: 45px 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            text-align: center;
        }
        .logo { width: 100px; margin-bottom: 20px; }
        h2 { color: #2d3748; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; }
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .btn-submit {
            width: 100%;
            background: #00a896;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-submit:hover { background: #008f80; }
        .alert-error {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #c6f6d5;
            color: #276749;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-login { display: block; margin-top: 20px; color: #00a896; text-decoration: none; }
        .info-text { font-size: 12px; color: #718096; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="reset-container">
        <img src="assets/images/umpsa-logo.png" alt="UMPSA Logo" class="logo">
        
        <?php if ($success): ?>
            <div class="alert-success">
                <strong>✅ Password Reset Successful!</strong>
                <p style="margin-top: 10px;">You can now login with your new password.</p>
            </div>
            <a href="login.php" class="btn-login">← Back to Login</a>
            
        <?php elseif ($error): ?>
            <div class="alert-error">
                ⚠️ <?php echo $error; ?>
            </div>
            <a href="forgot_password.php" class="btn-login">Request New Reset Link →</a>
            <br>
            <a href="login.php" style="display: inline-block; margin-top: 15px; color: #718096; text-decoration: none; font-size: 13px;">← Back to Login</a>
            
        <?php else: ?>
            <h2>Create New Password</h2>
            <p style="color: #718096; margin-bottom: 20px;">Enter your new password below</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                    <div class="info-text">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn-submit">Reset Password</button>
            </form>
            
            <a href="login.php" style="display: inline-block; margin-top: 20px; color: #718096; text-decoration: none;">← Back to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>