<?php
// login/not-found.php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container" style="text-align: center; padding: 2rem;">
        <h1>404 - Page Not Found</h1>
        <p>The page you requested could not be found.</p>
        <a href="../login/login.php" class="btn">Return to Login</a>
    </div>
</body>
</html>