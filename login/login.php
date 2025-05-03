<?php
session_start();

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "u450075158_ucgs";
$password = "Ucgs12345";
$dbname = "u450075158_ucgs";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Query the users table
    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? AND status = "Active"');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'Administrator') {
                header('Location: /admin/adminDashboard.php');
                exit();
            } else {
                header('Location: /user/Userdashboard.php');
                exit();
            }
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Invalid email or password.';
    }

    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Community Login</title>
    <link rel="stylesheet" href="/css/login.css">
    <style>
        .password-toggle svg {
            width: 1em;
            height: 1em;
            vertical-align: middle;
            fill: currentColor;
        }
        .svg-eye { display: inline-block; }
        .svg-eye-slash { display: none; }
        .password-toggle.show-password .svg-eye { display: none; }
        .password-toggle.show-password .svg-eye-slash { display: inline-block; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="hero-section">
            <img src="/assets/img/BG.jpg" alt="Church Community Illustration" class="hero-image">
            <h2>Welcome to Our Church Community</h2>
            <p>Connect, share, and grow together in faith</p>
        </div>

        <div class="login-container">
            <img src="/assets/img/Logo.png" alt="Church Logo" class="logo">
            <h1 class="form-title">UCGS Member Login</h1>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="/login/login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group password-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility">

                            <!-- SVG fallback -->
                            <svg class="svg-eye" viewBox="0 0 24 24">
                                <path d="M12 9a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3m0 8a5 5 0 0 1-5-5 5 5 0 0 1 5-5 5 5 0 0 1 5 5 5 5 0 0 1-5 5m0-12.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5z"/>
                            </svg>
                            <svg class="svg-eye-slash" viewBox="0 0 24 24">
                                <path d="M11.83 9L15 12.16V12a3 3 0 0 0-3-3h-.17m-4.3.8l1.55 1.55c-.05.21-.08.42-.08.65a3 3 0 0 0 3 3c.22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53a5 5 0 0 1-5-5c0-.79.2-1.53.53-2.2M2 4.27l2.28 2.28.45.45C3.08 8.3 1.78 10 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.43.42L19.73 22 21 20.73 3.27 3M12 7a5 5 0 0 1 5 5c0 .64-.13 1.26-.36 1.82l2.93 2.93c1.5-1.25 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-4 .7l2.17 2.15C10.74 7.13 11.35 7 12 7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn">Sign In</button>
                
                <div class="forgot-password">
                    <a href="/login/Forgot_password.php">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
          const passwordToggle = document.querySelector('.password-toggle');
          if (passwordToggle) {
              passwordToggle.addEventListener('click', function() {
                  const passwordInput = document.getElementById('password');
                  
                  // Toggle password visibility
                  const isPassword = passwordInput.type === 'password';
                  passwordInput.type = isPassword ? 'text' : 'password';
                  
                  // Toggle all icon types
                  // Font Awesome
                  const faEye = this.querySelector('.fa-eye');
                  const faEyeSlash = this.querySelector('.fa-eye-slash');
                  if (faEye && faEyeSlash) {
                      faEye.style.display = isPassword ? 'none' : 'block';
                      faEyeSlash.style.display = isPassword ? 'block' : 'none';
                  }
                  
                  // Bootstrap Icons
                  const biEye = this.querySelector('.bi-eye');
                  const biEyeSlash = this.querySelector('.bi-eye-slash');
                  if (biEye && biEyeSlash) {
                      biEye.style.display = isPassword ? 'none' : 'block';
                      biEyeSlash.style.display = isPassword ? 'block' : 'none';
                  }
                  
                  // SVG Icons
                  this.classList.toggle('show-password', isPassword);
                  
                  // Focus the password field after toggle
                  passwordInput.focus();
              });
          }
      });
    </script>
</body>
</html>