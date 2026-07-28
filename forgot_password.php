<?php
session_start();
include('config/db.php');
include('config/email_config.php');

date_default_timezone_set('Asia/Kuala_Lumpur');

$error = '';
$success = false;
$reset_link_display = '';
$email_sent = false;

if (isset($_POST['send_reset'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    $check_user = mysqli_query($conn, "SELECT user_id, name FROM users WHERE email = '$email'");
    
    if (mysqli_num_rows($check_user) > 0) {
        $user = mysqli_fetch_assoc($check_user);
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        mysqli_query($conn, "UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE user_id = '{$user['user_id']}'");
        mysqli_query($conn, "UPDATE users SET reset_token = '$token', reset_token_expiry = '$expiry' WHERE user_id = '{$user['user_id']}'");
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $reset_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/reset_password.php?token=" . $token;
        
        // Try to send email
        $email_result = sendResetEmail($email, $user['name'], $reset_link);
        
        if ($email_result['success']) {
            $email_sent = true;
            $success = true;
        } else {
            $reset_link_display = $reset_link;
            $success = true;
        }
    } else {
        $error = "Email address not found in our system.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UMPSA Lost & Found</title>
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
        .forgot-container {
            background: white;
            border-radius: 25px;
            padding: 50px 45px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            text-align: center;
        }
        .logo { width: 120px; margin-bottom: 25px; }
        h2 { color: #2d3748; margin-bottom: 12px; font-size: 28px; }
        .subtitle { color: #718096; font-size: 15px; margin-bottom: 35px; }
        .form-group { margin-bottom: 25px; text-align: left; }
        .form-group label { 
            display: block; 
            margin-bottom: 10px; 
            font-weight: 600; 
            color: #4a5568;
            font-size: 15px;
        }
        .form-group input {
            width: 100%;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #00a896;
            box-shadow: 0 0 0 3px rgba(0,168,150,0.1);
        }
        .btn-submit {
            width: 100%;
            background: #00a896;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover { background: #008f80; transform: translateY(-2px); }
        .alert-success {
            background: #c6f6d5;
            color: #276749;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        /* BIG RESET LINK BOX */
        .reset-link-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7f5 100%);
            padding: 30px 25px;
            border-radius: 20px;
            margin: 25px 0;
            border: 3px solid #00a896;
            box-shadow: 0 10px 30px rgba(0,168,150,0.2);
        }
        .reset-link-box .label {
            font-size: 18px;
            font-weight: bold;
            color: #00a896;
            margin-bottom: 20px;
            display: block;
            letter-spacing: 1px;
        }
        .reset-link-box .label-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 10px;
        }
        .reset-link-box a {
            color: #00a896;
            font-weight: bold;
            word-break: break-all;
            font-size: 16px;
            line-height: 1.8;
            text-decoration: none;
            background: white;
            padding: 18px 20px;
            display: block;
            border-radius: 12px;
            border: 2px solid #00a896;
            transition: all 0.3s;
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        .reset-link-box a:hover {
            background: #00a896;
            color: white;
            text-decoration: underline;
            transform: scale(1.01);
        }
        .warning-note {
            font-size: 14px;
            color: #ed8936;
            margin-top: 20px;
            padding: 15px;
            background: #fffaf0;
            border-radius: 12px;
            border-left: 4px solid #ed8936;
            text-align: left;
        }
        .warning-note strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .email-sent-note {
            font-size: 14px;
            color: #276749;
            margin-top: 15px;
            padding: 15px;
            background: #f0fff4;
            border-radius: 12px;
            border-left: 4px solid #48bb78;
        }
        .btn-back { 
            display: inline-block; 
            margin-top: 20px; 
            color: #718096; 
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: #f7fafc;
            color: #00a896;
        }
        hr { margin: 25px 0; border-color: #e2e8f0; }
        .copy-btn {
            background: #00a896;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            background: #008f80;
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <img src="assets/images/umpsa-logo.png" alt="UMPSA Logo" class="logo">
        
        <?php if ($success): ?>
            <div class="alert-success">
                <?php if ($email_sent): ?>
                    <strong>✅ Email Sent!</strong><br>
                    We've sent a password reset link to your email address.
                    <div class="email-sent-note">
                        📧 Check your inbox (and spam folder) for the reset link.
                    </div>
                <?php else: ?>
                    <strong>🔐 PASSWORD RESET LINK GENERATED</strong><br>
                    Your unique reset link is below (valid for 30 seconds):
                <?php endif; ?>
                
                <?php if ($reset_link_display): ?>
                    <div class="reset-link-box">
                        <span class="label-icon">🔗</span>
                        <span class="label">CLICK TO RESET YOUR PASSWORD</span>
                        <a href="<?php echo $reset_link_display; ?>" target="_blank">
                            <?php echo $reset_link_display; ?>
                        </a>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo $reset_link_display; ?>')">
                            📋 Copy Link to Clipboard
                        </button>
                        <div class="warning-note">
                            <strong>⏰ Important Information:</strong>
                            • This link will expire in <strong>30 seconds</strong><br>
                            • For security, you can only use this link once<br>
                            • If the link expires, please request a new one<br>
                            • 💡 <em>For FYP demonstration: In production, this link would be sent to your email</em>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <a href="login.php" class="btn-back">← Back to Login</a>
            
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert-error" style="background: #fed7d7; color: #9b2c2c; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <h2>🔐 Forgot Password?</h2>
            <p class="subtitle">Enter your email address and we'll help you reset your password</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>📧 Email Address</label>
                    <input type="email" name="email" placeholder="your@adab.umpsa.edu.my" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" name="send_reset" class="btn-submit">Send Reset Link</button>
            </form>
            
            <hr>
            <a href="login.php" class="btn-back">← Back to Login</a>
        <?php endif; ?>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ Link copied to clipboard!\n\nPaste it in your browser to reset your password.');
            }, function() {
                alert('⚠️ Could not copy. Please copy the link manually.');
            });
        }
    </script>
</body>
</html>