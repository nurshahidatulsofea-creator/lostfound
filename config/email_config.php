<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

function sendResetEmail($to_email, $to_name, $reset_link) {
    $mail = new PHPMailer(true);
    
    try {
        // UPDATE THESE WITH YOUR GMAIL DETAILS:
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rc24299@adab.umpsa.edu.my';  
        $mail->Password   = 'ojwr bete yfnz xzpj';  
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('noreply@umpsa.edu.my', 'UMPSA Lost & Found');
        $mail->addAddress($to_email, $to_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - UMPSA Lost & Found';
        
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Password Reset Request</h2>
            <p>Hello <strong>$to_name</strong>,</p>
            <p>Click the link below to reset your password (valid for 30 seconds):</p>
            <p><a href='$reset_link' style='background: #00a896; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
            <p>Or copy this link: <br>$reset_link</p>
            <p>If you didn't request this, please ignore this email.</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>UMPSA Lost & Found System</p>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $to_name,\n\nClick this link to reset your password: $reset_link\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>