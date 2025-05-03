<?php
session_start();

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    // Validate email
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email exists in database
        $conn = new mysqli('localhost', 'u450075158_ucgs', 'Ucgs12345', 'u450075158_ucgs');
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        // Verify the required columns exist
        $columns = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
        if ($columns->num_rows === 0) {
            $error = 'System error: Missing required database columns. Please contact administrator.';
        } else {
            $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 300); // 5 minutes expiration (300 seconds)
                
                // Store token in database
                $update = $conn->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?');
                $update->bind_param('sss', $token, $expires, $email);
                
if ($update->execute()) {
    // Send email with HTML template
    $resetLink = "https://mediumblue-giraffe-359913.hostingersite.com/login/Reset_password.php?token=$token";
    $subject = '🔐 Password Reset Request - United Church of the Good Shepherd';
    
    // Modern HTML email template
    $message = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap");
            
            body {
                font-family: "Poppins", Arial, sans-serif;
                line-height: 1.6;
                color: #4a4a4a;
                max-width: 600px;
                margin: 0 auto;
                padding: 0;
                background-color: #f7f9fc;
            }
            .container {
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0,0,0,0.05);
                margin: 20px auto;
                border: 1px solid #e0e6ed;
            }
            .header {
                text-align: center;
                padding: 30px 20px;
                background: linear-gradient(135deg, #6e8efb, #a777e3);
                color: white;
            }
            .logo {
                max-height: 50px;
                margin-bottom: 15px;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            }
            .content {
                padding: 30px;
            }
            .button {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #6e8efb, #a777e3);
                color: white !important;
                text-decoration: none;
                border-radius: 30px;
                margin: 20px 0;
                font-weight: 500;
                text-align: center;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(110, 142, 251, 0.3);
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(110, 142, 251, 0.4);
            }
            .footer {
                margin-top: 20px;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #888;
                background-color: #f7f9fc;
                border-top: 1px solid #e0e6ed;
                border-radius: 0 0 12px 12px;
            }
            .text-center {
                text-align: center;
            }
            .divider {
                height: 1px;
                background: linear-gradient(to right, transparent, #e0e6ed, transparent);
                margin: 25px 0;
            }
            .highlight-box {
                background: #f7f9fc;
                border-left: 4px solid #6e8efb;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
            }
            .social-icons {
                margin: 20px 0;
            }
            .social-icons a {
                display: inline-block;
                margin: 0 8px;
                color: #6e8efb;
                font-size: 18px;
            }
            @media (prefers-color-scheme: dark) {
                .container {
                    background: #2a2e35;
                    border-color: #3d424a;
                }
                body {
                    background: #1e2227;
                    color: #e0e6ed;
                }
                .content {
                    background: #2a2e35;
                }
                .highlight-box {
                    background: #3d424a;
                    border-left-color: #a777e3;
                }
                .footer {
                    background: #1e2227;
                    border-color: #3d424a;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://mediumblue-giraffe-359913.hostingersite.com/assets/img/Logo.png" alt="UCGS Logo" class="logo">
                <h2 style="margin:0;font-weight:600;">Password Reset Request</h2>
            </div>
            
            <div class="content">
                <p>Hello,</p>
                <p>We received a request to reset your password for your United Church of Good Shepherd account.</p>
                
                <div class="text-center">
                    <a href="'.$resetLink.'" class="button">Reset Your Password</a>
                </div>
                
                <div class="divider"></div>
                
                <div class="highlight-box">
                    <p style="margin:0;"><strong>Important:</strong> This link will expire in 5 minutes for security reasons.</p>
                    <p style="margin:10px 0 0 0;">If you didn\'t request this password reset, please ignore this email.</p>
                </div>
                
                <p>Need help? Reply to this email or contact us at <a href="mailto:unitedchurch@ucgs.com" style="color:#6e8efb;text-decoration:none;">unitedchurch@ucgs.com</a></p>
                
                <div class="social-icons">
                    •<a href="https://www.facebook.com/groups/165090796887381" style="color:#6e8efb;">Facebook</a>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; '.date('Y').' United Church of the Good Shepherd. All rights reserved.</p>
                <p>72 I. Lopez St, Mandaluyong City, 1550 Metro Manila</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Email headers
    $headers = "From: United Church of the Good Shepherd <unitedchurch@ucgs.com>\r\n";
    $headers .= "Reply-To: unitedchurch@ucgs.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1 (Highest)\r\n";
    
    if (mail($email, $subject, $message, $headers)) {
        $success = 'Password reset link sent to your email.';
    } else {
        $error = 'Email could not be sent. Please try again.';
    }
} else {
    $error = 'Database error. Please try again.';
}
                $update->close();
            } else {
                // Don't reveal whether email exists
                $success = 'If an account exists with this email, a reset link has been sent.';
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Church Community</title>
    <link rel="stylesheet" href="../css/forgot_password.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="hero-section">
            <img src="../assets/img/BG.jpg" alt="Church Community Illustration" class="hero-image">
            <h2>Reset Your Password</h2>
            <p>Enter your email to receive a password reset link</p>
        </div>

        <div class="login-container">
            <img src="../assets/img/Logo.png" alt="Church Logo" class="logo">
            <h1 class="form-title">Password Recovery</h1>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($email) ?>" 
                           required
                           autocomplete="email">
                </div>

                <button type="submit" class="btn">Send Reset Link</button>
                
                <div class="back-to-login">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>