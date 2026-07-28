<?php
session_start();
include('config/db.php'); 

$message = "";


if (isset($_POST['login'])) {
    $email = trim($_POST['email']); 
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($_SESSION['role'] == 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $message = "Invalid Password.";
        }
    } else {
        $message = "Username not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UMPSA Lost & Found</title>
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #00a896; /* Warna Teal UMPSA */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background: white;
            /* --- BESARKAN PADDING & LEBAR --- */
            padding: 50px 45px; 
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 480px; /* Lebar ditingkatkan dari 400px ke 480px */
            text-align: center;
        }

        .logo-umpsa {
            width: 130px; /* Besarkan sikit logo */
            height: auto;
            margin-bottom: 20px;
        }

        h3 {
            font-size: 20px; /* Besarkan sikit font tajuk */
            margin: 10px 0 5px 0;
            color: #333;
            font-weight: bold;
        }

        .sub-title {
            font-size: 13px;
            color: #777;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 16px; /* Kotak input lebih tinggi/gemuk */
            background-color: #d1d8e0; 
            border: none;
            border-radius: 10px;
            font-size: 16px;
            box-sizing: border-box;
            outline: none;
            transition: 0.3s;
        }

        .form-group input:focus {
            background-color: #c5cedb;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 30px; /* Lebih rounded */
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }

        .btn-login:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .register-link {
            margin-top: 25px;
            font-size: 14px;
            display: block;
            text-decoration: none;
            color: #3498db;
            font-weight: 600;
        }

        .register-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <img src="assets/images/umpsa-logo.png" alt="UMPSA Logo" class="logo-umpsa">

        <h3>Lost & Found System</h3>
        <p class="sub-title">Universiti Malaysia Pahang Al-Sultan Abdullah</p>

        <?php if ($message != ""): ?>
            <p style="color: #e53e3e; font-size: 14px; background: #fff5f5; padding: 10px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #feb2b2;">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <!-- Add this inside your <form> tag, before the login button -->
            <div style="text-align: right; margin-bottom: 15px;">
             <a href="forgot_password.php" style="color: #718096; text-decoration: none; font-size: 12px;">Forgot Password?</a>
            </div>
            
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>

        <a href="register.php" class="register-link">Don't have an account? Register here</a>
    </div>

</body>
</html>