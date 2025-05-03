<?php
session_start();
include '../config/db_connection.php';

$error = '';
$success = '';
$email = '';
$token_email = ''; // Email associated with the token
$valid_token = false;

// Check if token is valid and get associated email
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    $conn = new mysqli('localhost', 'u450075158_ucgs', 'Ucgs12345', 'u450075158_ucgs');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // Check token validity and get user data
    $stmt = $conn->prepare('SELECT email, reset_expires FROM users WHERE reset_token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token_email = $user['email'];
        
        // Check if token is expired
        if (strtotime($user['reset_expires']) < time()) {
            $error = 'This reset link has expired. Please request a new one.';
        } else {
            $email = $token_email; // Pre-populate with token's email
            $valid_token = true;
        }
    } else {
        $error = 'Invalid reset link. Please request a new one.';
    }
    
    $stmt->close();
    $conn->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $email = $_POST['email'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate that the submitted email matches the token's email
    if ($email !== $token_email) {
        $error = 'You can only reset the password for the email that requested the reset link.';
    } elseif (empty($new_password)) {
        $error = 'Please enter a new password';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Update password in database
        $conn = new mysqli('localhost', 'u450075158_ucgs', 'Ucgs12345', 'u450075158_ucgs');
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?');
        $update_stmt->bind_param('ss', $hashed_password, $email);
        
        if ($update_stmt->execute()) {
            $success = 'Your password has been updated successfully!';
        } else {
            $error = 'Failed to update password. Please try again.';
        }
        
        $update_stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | UCGS</title>
    <link rel="stylesheet" href="../css/reset_password.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="hero-section">
            <img src="../assets/img/BG.jpg" alt="Church Community Illustration" class="hero-image">
            <h2>Set a New Password</h2>
            <p>Create a strong password to secure your account</p>
        </div>

        <div class="login-container">
            <img src="../assets/img/Logo.png" alt="Church Logo" class="logo">
            <h1 class="form-title">Reset Password</h1>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
                <div class="back-to-login">
                    <a href="login.php">Return to Login</a>
                </div>
            <?php else: ?>
                <?php if (!$valid_token): ?>
                    <div class="error-message">Invalid or expired reset link. Please request a new one.</div>
                    <div class="back-to-login">
                        <a href="login.php">Return to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   readonly
                                   required
                                   autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                            <span class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>

                        <button type="submit" class="btn">Reset Password</button>
                        
                        <div class="back-to-login">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = field.nextElementSibling; // The eye icon
    
    // Toggle password visibility
    if (field.type === 'password') {
        field.type = 'text';
        toggle.classList.add('show-password');
    } else {
        field.type = 'password';
        toggle.classList.remove('show-password');
    }
}
    </script>
</body>
</html>