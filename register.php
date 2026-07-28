<?php
session_start();
include('config/db.php');

$message = ""; 

if (isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, $_POST['full_name']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $password = $_POST['password'];
    $role = 'user';
    
    $errors = [];
    
    $is_admin_email = preg_match('/@admin\.com$/', $email);
    $email_valid = false;
    
    if ($is_admin_email) {
        $email_valid = true;
        $role = 'admin';
    } elseif (preg_match("/^[a-zA-Z0-9._%+-]+@(adab\.umpsa\.edu\.my|adab\.my)$/", $email)) {
        $email_valid = true;
        $role = 'user';
    }
    
    if (!$email_valid) {
        $errors[] = "Email must be @adab.umpsa.edu.my, @adab.my, or name@admin.com";
    }
    
    $phone_clean = preg_replace('/[\s\-]/', '', $phone);
    $phone_valid = false;
    
    if (preg_match('/^(010|012|013|014|016|017|018|019)\d{7}$/', $phone_clean)) {
        $phone_valid = true;
    } elseif (preg_match('/^011\d{8}$/', $phone_clean)) {
        $phone_valid = true;
    }
    
    if (!$phone_valid) {
        $errors[] = "Invalid phone number! Use: 010,012,013,014,016,017,018,019 (10 digits) or 011 (11 digits)";
    }
    
    $password_errors = [];
    
    if (strlen($password) < 8) {
        $password_errors[] = "At least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $password_errors[] = "At least 1 uppercase letter (A-Z)";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $password_errors[] = "At least 1 lowercase letter (a-z)";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $password_errors[] = "At least 1 number (0-9)";
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $password_errors[] = "At least 1 special character (!@#$%^&* etc.)";
    }
    
    if (!empty($password_errors)) {
        $errors[] = "Password requirements:<br> • " . implode("<br> • ", $password_errors);
    }
    
    if (empty($errors)) {
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $check_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        
        if (mysqli_num_rows($check_email) > 0) {
            $message = "<div class='msg-error'>❌ Email already registered!</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone_number, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone_clean, $hashed_password, $role);
            
            if ($stmt->execute()) {
                if ($role == 'admin') {
                    echo "<script>alert('✅ Admin Account Created Successfully!'); window.location.href='login.php';</script>";
                } else {
                    echo "<script>alert('✅ Registration Successful!'); window.location.href='login.php';</script>";
                }
                exit();
            } else {
                $message = "<div class='msg-error'>Error: " . $conn->error . "</div>";
            }
        }
    } else {
        $message = "<div class='msg-error'>❌ Please fix the following:<br> • " . implode("<br> • ", $errors) . "</div>";
    }
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UMPSA Lost & Found</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #00a896;
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 60px 50px;
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 550px;
            text-align: center;
        }

        .register-container img {
            height: 90px;
            margin-bottom: 20px;
        }

        .system-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .sub-title {
            font-size: 14px;
            color: #777;
            margin-bottom: 35px;
        }

        .form-group { 
            margin-bottom: 20px;
            text-align: left; 
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 18px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #dbe2e8;
            outline: none;
            font-size: 15px;
            color: #333;
            transition: 0.3s;
        }

        .form-group input:focus {
            border-color: #3498db;
            background: #fff;
        }

        .form-group input.error {
            border-color: #e53e3e;
            background: #fff5f5;
        }

        .form-group input.valid {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .error-popup {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #e53e3e;
            color: white;
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 5px;
            z-index: 100;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .error-popup.show {
            display: block;
        }

        .error-popup::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 15px;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid #e53e3e;
        }

        .password-hint {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 5px;
            padding-left: 5px;
            line-height: 1.4;
        }

        .password-requirements {
            font-size: 11px;
            color: #718096;
            margin-top: 8px;
            padding: 8px;
            background: #f7fafc;
            border-radius: 8px;
            display: none;
        }

        .password-requirements.show {
            display: block;
        }

        .password-requirements ul {
            margin-left: 20px;
            margin-top: 5px;
        }

        .requirement-met {
            color: #48bb78;
            text-decoration: line-through;
        }

        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 18px;
            width: 100%;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            font-size: 18px;
            margin-top: 15px;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover { 
            background: #2980b9;
            transform: translateY(-2px);
        }

        .msg-error {
            background: #fff5f5;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid #feb2b2;
            text-align: left;
        }

        .footer-link { margin-top: 25px; font-size: 14px; }
        .footer-link a { color: #3498db; text-decoration: none; font-weight: 600; }
    </style>
</head>

<body>

    <div class="register-container">
        
        <img src="assets/images/umpsa-logo.png" alt="UMPSA Logo">
        
        <div class="system-title">Lost & Found Management System</div>
        <div class="sub-title">Universiti Malaysia Pahang Al-Sultan Abdullah</div>

        <?php echo $message; ?>

        <form action="" method="POST" id="registerForm">
            
            <div class="form-group">
                <input type="text" name="full_name" id="fullName" placeholder="Full Name" required>
            </div>

            <div class="form-group">
                <input type="email" name="email" id="email" placeholder="Email Address" required>
                <div class="error-popup" id="emailError"></div>
            </div>

            <div class="form-group">
                <input type="tel" name="phone_number" id="phone" placeholder="Phone Number" required>
                <div class="error-popup" id="phoneError"></div>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" placeholder="Create Password" required>
                <div class="error-popup" id="passwordError"></div>
                <div class="password-hint">
                    Password must contain:<br>
                    • 8+ characters<br>
                    • Uppercase & lowercase letters<br>
                    • Numbers & special characters (!@#$%^&*)
                </div>
            </div>

            <button type="submit" name="register" class="btn-primary">Register Now</button>
            
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>

    </div>

    <script>
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        
        const emailError = document.getElementById('emailError');
        const phoneError = document.getElementById('phoneError');
        const passwordError = document.getElementById('passwordError');
        
        function validateEmail() {
            const email = emailInput.value.trim().toLowerCase();
            
            if (email.length === 0) {
                emailError.classList.remove('show');
                emailInput.classList.remove('error', 'valid');
                return false;
            }
            
            if (/@admin\.com$/.test(email)) {
                emailError.classList.remove('show');
                emailInput.classList.add('valid');
                emailInput.classList.remove('error');
                return true;
            }
            else if (/^[a-zA-Z0-9._%+-]+@(adab\.umpsa\.edu\.my|adab\.my)$/.test(email)) {
                emailError.classList.remove('show');
                emailInput.classList.add('valid');
                emailInput.classList.remove('error');
                return true;
            }
            else {
                emailError.textContent = ' Invalid email! Use @adab.umpsa.edu.my or @adab.my ';
                emailError.classList.add('show');
                emailInput.classList.add('error');
                emailInput.classList.remove('valid');
                return false;
            }
        }
        
        function validatePhone() {
            let phone = phoneInput.value.trim();
            phone = phone.replace(/[\s\-]/g, '');
            
            if (phone.length === 0) {
                phoneError.classList.remove('show');
                phoneInput.classList.remove('error', 'valid');
                return false;
            }
            
            if (/^(010|012|013|014|016|017|018|019)\d{7}$/.test(phone)) {
                phoneError.classList.remove('show');
                phoneInput.classList.add('valid');
                phoneInput.classList.remove('error');
                return true;
            }
            else if (/^011\d{8}$/.test(phone)) {
                phoneError.classList.remove('show');
                phoneInput.classList.add('valid');
                phoneInput.classList.remove('error');
                return true;
            }
            else {
                phoneError.textContent = ' Invalid phone number! ';
                phoneError.classList.add('show');
                phoneInput.classList.add('error');
                phoneInput.classList.remove('valid');
                return false;
            }
        }
        
        function validatePassword() {
            const password = passwordInput.value;
            
            if (password.length === 0) {
                passwordError.classList.remove('show');
                passwordInput.classList.remove('error', 'valid');
                return false;
            }
            
            const hasMinLength = password.length >= 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            const isValid = hasMinLength && hasUpperCase && hasLowerCase && hasNumber && hasSpecialChar;
            
            if (isValid) {
                passwordError.classList.remove('show');
                passwordInput.classList.add('valid');
                passwordInput.classList.remove('error');
                return true;
            } else {
                let missing = [];
                if (!hasMinLength) missing.push("8+ characters");
                if (!hasUpperCase) missing.push("Uppercase letter (A-Z)");
                if (!hasLowerCase) missing.push("Lowercase letter (a-z)");
                if (!hasNumber) missing.push("Number (0-9)");
                if (!hasSpecialChar) missing.push("Special character (!@#$%^&*)");
                
                passwordError.textContent = 'Password missing: ' + missing.join(", ");
                passwordError.classList.add('show');
                passwordInput.classList.add('error');
                passwordInput.classList.remove('valid');
                return false;
            }
        }
        
        emailInput.addEventListener('input', validateEmail);
        phoneInput.addEventListener('input', validatePhone);
        passwordInput.addEventListener('input', validatePassword);
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const isEmailValid = validateEmail();
            const isPhoneValid = validatePhone();
            const isPasswordValid = validatePassword();
            
            if (!isEmailValid || !isPhoneValid || !isPasswordValid) {
                e.preventDefault();
                alert('Please fix the errors before submitting.\n\nPassword must have:\n- At least 8 characters\n- Uppercase letter\n- Lowercase letter\n- Number\n- Special character (!@#$%^&*)');
            }
        });
    </script>

</body>
</html>